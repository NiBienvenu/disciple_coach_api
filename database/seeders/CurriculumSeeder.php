<?php

namespace Database\Seeders;

use App\Enums\ContentStatus;
use App\Enums\LessonType;
use App\Models\Level;
use App\Models\Quiz;
use Illuminate\Database\Seeder;
use App\Services\Cache\CacheKey;
use Illuminate\Support\Facades\File;

class CurriculumSeeder extends Seeder
{
    public function run(): void
    {
        $path = $this->resolveSeedPath();

        if ($path === null) {
            $this->command?->error('curriculum_seed.json not found.');

            return;
        }

        /** @var array{tracks: list<array<string, mixed>>} $payload */
        $payload = json_decode(File::get($path), true, 512, JSON_THROW_ON_ERROR);
        $tracks = $payload['tracks'] ?? [];

        /** @var array<string, Level> $levelsByTrackId */
        $levelsByTrackId = [];

        foreach ($tracks as $track) {
            $trackId = (string) $track['id'];

            $level = Level::query()->updateOrCreate(
                ['slug' => $trackId],
                [
                    'order' => (int) $track['order'],
                    'part' => (int) ($track['part'] ?? 3),
                    'previous_level_id' => null,
                    'status' => ContentStatus::Published,
                ],
            );

            $levelsByTrackId[$trackId] = $level;

            foreach (['fr', 'rn', 'en'] as $language) {
                $level->translations()->updateOrCreate(
                    ['language' => $language],
                    [
                        'title' => $track['title'][$language] ?? $track['title']['fr'] ?? $trackId,
                        'description' => $track['description'][$language] ?? $track['description']['fr'] ?? null,
                        'part_title' => $track['partTitle'][$language] ?? $track['partTitle']['fr'] ?? null,
                    ],
                );
            }

            $keptLessonCodes = [];
            foreach ($track['lessons'] ?? [] as $lessonData) {
                $keptLessonCodes[] = (string) $lessonData['code'];
            }

            // Drop obsolete lessons BEFORE upsert so reused slugs (e.g. track_1_lesson_01
            // moving from 1M01 → 1A01) do not hit lessons_slug_unique.
            if ($keptLessonCodes !== []) {
                $level->lessons()->whereNotIn('code', $keptLessonCodes)->delete();
            }

            foreach ($track['lessons'] ?? [] as $lessonData) {
                $lessonId = (string) $lessonData['id'];
                $type = LessonType::tryFrom((string) ($lessonData['type'] ?? 'main')) ?? LessonType::Main;
                $code = (string) $lessonData['code'];

                $lesson = $level->lessons()->updateOrCreate(
                    ['code' => $code],
                    [
                        'slug' => $lessonId,
                        'type' => $type,
                        'order' => (int) $lessonData['order'],
                        'status' => ContentStatus::Published,
                        'resource_url' => $lessonData['resource_url'] ?? null,
                    ],
                );

                foreach (['fr', 'rn', 'en'] as $language) {
                    $content = $lessonData['content'][$language] ?? $lessonData['content']['fr'] ?? [];

                    $lesson->translations()->updateOrCreate(
                        ['language' => $language],
                        [
                            'title' => $lessonData['title'][$language] ?? $lessonData['title']['fr'] ?? $lessonId,
                            'central_idea' => $content['centralIdea'] ?? null,
                            'objectives' => $content['objectives'] ?? [],
                            'key_scriptures' => $content['keyScriptures'] ?? [],
                            'summary_points' => $content['summaryPoints'] ?? [],
                            'reflection_questions' => $content['reflectionQuestions'] ?? [],
                        ],
                    );
                }
            }

            $quizData = $track['quiz'] ?? null;
            if (is_array($quizData)) {
                $quiz = Quiz::query()->updateOrCreate(
                    ['level_id' => $level->id],
                    ['status' => ContentStatus::Published],
                );

                // Update questions in place by order so their ids never change:
                // an app holding the quiz can still submit after a re-seed.
                $questionsData = array_values($quizData['questions'] ?? []);
                $quiz->questions()->where('order', '>', count($questionsData))->delete();

                foreach ($questionsData as $index => $questionData) {
                    $question = $quiz->questions()->updateOrCreate(
                        ['order' => $index + 1],
                        ['correct_index' => (int) ($questionData['correctIndex'] ?? 0)],
                    );

                    foreach (['fr', 'rn', 'en'] as $language) {
                        $choices = [];
                        foreach ($questionData['choices'] ?? [] as $choice) {
                            $choices[] = is_array($choice)
                                ? ($choice[$language] ?? $choice['fr'] ?? '')
                                : (string) $choice;
                        }

                        $question->translations()->updateOrCreate(
                            ['language' => $language],
                            [
                                'prompt' => $questionData['prompt'][$language]
                                    ?? $questionData['prompt']['fr']
                                    ?? '',
                                'choices' => $choices,
                            ],
                        );
                    }
                }
            }
        }

        foreach ($tracks as $track) {
            $trackId = (string) $track['id'];
            $previousTrackId = $track['previousTrackId'] ?? null;
            $level = $levelsByTrackId[$trackId] ?? null;

            if ($level === null) {
                continue;
            }

            $previousLevelId = null;
            if (is_string($previousTrackId) && isset($levelsByTrackId[$previousTrackId])) {
                $previousLevelId = $levelsByTrackId[$previousTrackId]->id;
            }

            $level->update(['previous_level_id' => $previousLevelId]);
        }

        // New revision: cached curriculum is bypassed and apps reload it.
        CacheKey::bumpRevision();

        $this->command?->info('Curriculum seeded from '.$path.' (cache cleared)');
    }

    private function resolveSeedPath(): ?string
    {
        $path = database_path('seeders/demo/curriculum_seed.json');

        return File::isFile($path) ? $path : null;
    }
}
