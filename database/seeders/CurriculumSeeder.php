<?php

namespace Database\Seeders;

use App\Enums\ContentStatus;
use App\Enums\LessonType;
use App\Models\Lesson;
use App\Models\Level;
use App\Models\Quiz;
use Illuminate\Database\Seeder;
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
                $removed = $level->lessons()->whereNotIn('code', $keptLessonCodes)->get(['id', 'code', 'slug']);
                // #region agent log
                file_put_contents('/home/bienvenu/Documents/CHRIS/.cursor/debug-936aec.log', json_encode([
                    'sessionId' => '936aec',
                    'runId' => 'post-fix',
                    'hypothesisId' => 'B',
                    'location' => 'CurriculumSeeder.php:cleanup-before-upsert',
                    'message' => 'Deleting obsolete lessons before upsert',
                    'data' => [
                        'trackId' => $trackId,
                        'levelId' => $level->id,
                        'keptCodes' => $keptLessonCodes,
                        'removed' => $removed->map(fn ($l) => [
                            'id' => $l->id, 'code' => $l->code, 'slug' => $l->slug,
                        ])->all(),
                    ],
                    'timestamp' => (int) (microtime(true) * 1000),
                ], JSON_UNESCAPED_UNICODE)."\n", FILE_APPEND);
                // #endregion
                $level->lessons()->whereNotIn('code', $keptLessonCodes)->delete();
            }

            foreach ($track['lessons'] ?? [] as $lessonData) {
                $lessonId = (string) $lessonData['id'];
                $type = LessonType::tryFrom((string) ($lessonData['type'] ?? 'main')) ?? LessonType::Main;
                $code = (string) $lessonData['code'];

                // #region agent log
                $byCode = Lesson::query()->where('code', $code)->first(['id', 'code', 'slug', 'level_id']);
                $bySlug = Lesson::query()->where('slug', $lessonId)->first(['id', 'code', 'slug', 'level_id']);
                file_put_contents('/home/bienvenu/Documents/CHRIS/.cursor/debug-936aec.log', json_encode([
                    'sessionId' => '936aec',
                    'runId' => 'post-fix',
                    'hypothesisId' => 'A',
                    'location' => 'CurriculumSeeder.php:before-upsert',
                    'message' => 'Lesson upsert preflight after cleanup',
                    'data' => [
                        'trackId' => $trackId,
                        'levelId' => $level->id,
                        'wantedCode' => $code,
                        'wantedSlug' => $lessonId,
                        'byCode' => $byCode?->toArray(),
                        'bySlug' => $bySlug?->toArray(),
                        'slugOwnedByOtherCode' => $bySlug !== null && $bySlug->code !== $code,
                    ],
                    'timestamp' => (int) (microtime(true) * 1000),
                ], JSON_UNESCAPED_UNICODE)."\n", FILE_APPEND);
                // #endregion

                try {
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
                } catch (\Throwable $e) {
                    // #region agent log
                    file_put_contents('/home/bienvenu/Documents/CHRIS/.cursor/debug-936aec.log', json_encode([
                        'sessionId' => '936aec',
                        'runId' => 'post-fix',
                        'hypothesisId' => 'B',
                        'location' => 'CurriculumSeeder.php:upsert-failed',
                        'message' => 'Lesson upsert threw',
                        'data' => [
                            'wantedCode' => $code,
                            'wantedSlug' => $lessonId,
                            'exception' => $e->getMessage(),
                            'slugOwnerCode' => $bySlug?->code,
                            'slugOwnerId' => $bySlug?->id,
                        ],
                        'timestamp' => (int) (microtime(true) * 1000),
                    ], JSON_UNESCAPED_UNICODE)."\n", FILE_APPEND);
                    // #endregion
                    throw $e;
                }

                // #region agent log
                file_put_contents('/home/bienvenu/Documents/CHRIS/.cursor/debug-936aec.log', json_encode([
                    'sessionId' => '936aec',
                    'runId' => 'post-fix',
                    'hypothesisId' => 'A',
                    'location' => 'CurriculumSeeder.php:after-upsert',
                    'message' => 'Lesson upsert succeeded',
                    'data' => [
                        'lessonId' => $lesson->id,
                        'code' => $lesson->code,
                        'slug' => $lesson->slug,
                    ],
                    'timestamp' => (int) (microtime(true) * 1000),
                ], JSON_UNESCAPED_UNICODE)."\n", FILE_APPEND);
                // #endregion

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

                $quiz->questions()->delete();

                foreach (array_values($quizData['questions'] ?? []) as $index => $questionData) {
                    $question = $quiz->questions()->create([
                        'order' => $index + 1,
                        'correct_index' => (int) ($questionData['correctIndex'] ?? 0),
                    ]);

                    foreach (['fr', 'rn', 'en'] as $language) {
                        $choices = [];
                        foreach ($questionData['choices'] ?? [] as $choice) {
                            $choices[] = is_array($choice)
                                ? ($choice[$language] ?? $choice['fr'] ?? '')
                                : (string) $choice;
                        }

                        $question->translations()->create([
                            'language' => $language,
                            'prompt' => $questionData['prompt'][$language]
                                ?? $questionData['prompt']['fr']
                                ?? '',
                            'choices' => $choices,
                        ]);
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

        $this->command?->info('Curriculum seeded from '.$path);
    }

    private function resolveSeedPath(): ?string
    {
        $path = database_path('seeders/demo/curriculum_seed.json');

        return File::isFile($path) ? $path : null;
    }
}
