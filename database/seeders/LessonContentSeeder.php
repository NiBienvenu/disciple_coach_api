<?php

namespace Database\Seeders;

use App\Enums\ContentStatus;
use App\Enums\LessonType;
use App\Models\Lesson;
use App\Models\LessonPage;
use App\Models\Level;
use App\Models\Quiz;
use App\Services\Cache\CacheKey;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Loads the Gnowbe-style curriculum from database/seeders/content:
 * levels.json + one JSON file per lesson (see content/SPEC.md).
 *
 * Replaces the pages of every lesson it knows and rebuilds each level quiz
 * from the lesson questions. Lessons of these levels that are not in the
 * files are removed.
 */
class LessonContentSeeder extends Seeder
{
    private const LANGUAGES = ['fr', 'rn', 'en'];

    /** Shared illustrations, tracked in git (unlike admin uploads). */
    private const IMAGE_DIR = 'images/lessons';

    /** Target size of a level quiz built from the lesson questions. */
    private const LEVEL_QUIZ_TARGET = 20;

    public function run(): void
    {
        $root = database_path('seeders/content');
        $levels = json_decode(File::get($root.'/levels.json'), true, 512, JSON_THROW_ON_ERROR)['levels'];

        $lessonsBySlug = [];
        foreach ($levels as $levelData) {
            $lessonsBySlug[$levelData['slug']] = $this->readLessons($root.'/'.$levelData['slug']);
        }
        $allCodes = collect($lessonsBySlug)->flatten(1)->pluck('code')->all();

        DB::transaction(function () use ($levels, $lessonsBySlug, $allCodes) {
            $levelIds = [];
            foreach ($levels as $levelData) {
                $levelIds[$levelData['slug']] = $this->saveLevel($levelData)->id;
            }

            // Old lessons go first, so a reused slug never hits the unique index.
            Lesson::query()->whereIn('level_id', array_values($levelIds))->whereNotIn('code', $allCodes)->delete();

            foreach ($levels as $levelData) {
                $level = Level::query()->findOrFail($levelIds[$levelData['slug']]);
                $level->update([
                    'previous_level_id' => $levelData['previous'] ? ($levelIds[$levelData['previous']] ?? null) : null,
                ]);

                $lessons = $lessonsBySlug[$levelData['slug']];
                foreach ($lessons as $lessonData) {
                    $this->saveLesson($level, $lessonData);
                }
                $this->saveLevelQuiz($level, $lessons);
            }
        });

        CacheKey::bumpRevision();

        $this->command?->info('Lesson pages loaded from '.$root.' (cache cleared)');
    }

    /**
     * Updates only the text of the given languages from the files, in place.
     *
     * Unlike run(), no page is deleted: page ids stay, so answers that point
     * to a page (faith decisions, lesson quiz answers) and admin images stay
     * too. A lesson whose page or question count no longer matches its file
     * (changed by an admin) is left alone.
     *
     * @param  list<string>  $languages
     */
    public function refreshTexts(array $languages): void
    {
        $root = database_path('seeders/content');
        $levels = json_decode(File::get($root.'/levels.json'), true, 512, JSON_THROW_ON_ERROR)['levels'];

        DB::transaction(function () use ($root, $levels, $languages) {
            foreach ($levels as $levelData) {
                $lessons = $this->readLessons($root.'/'.$levelData['slug']);
                foreach ($lessons as $data) {
                    $this->refreshLessonTexts($data, $languages);
                }
                $level = Level::query()->where('slug', $levelData['slug'])->first();
                if ($level !== null) {
                    $this->refreshLevelQuizTexts($level, $lessons, $languages);
                }
            }
        });

        CacheKey::bumpRevision();
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>  $languages
     */
    private function refreshLessonTexts(array $data, array $languages): void
    {
        $lesson = Lesson::query()->where('code', (string) $data['code'])->first();
        if ($lesson === null) {
            return;
        }

        $pages = $data['pages'] ?? [];
        $quiz = $data['quiz'] ?? [];
        $contentPages = $lesson->pages()->where('kind', LessonPage::KIND_CONTENT)->orderBy('order')->get();
        $questionPages = $lesson->pages()->where('kind', LessonPage::KIND_QUESTION)->orderBy('order')->get();
        if ($contentPages->count() !== count($pages) || $questionPages->count() !== count($quiz)) {
            $this->command?->warn("Skipped {$data['code']}: its pages were changed in the app.");

            return;
        }

        foreach ($languages as $lang) {
            $lesson->translations()->where('language', $lang)->update([
                'title' => $this->tr($data['title'], $lang),
                'central_idea' => $this->firstParagraph($this->tr($pages[0]['body'] ?? [], $lang)),
            ]);
            foreach ($contentPages as $i => $page) {
                $page->translations()->where('language', $lang)->update([
                    'title' => $this->tr($pages[$i]['title'] ?? [], $lang) ?: null,
                    'body' => $this->tr($pages[$i]['body'] ?? [], $lang) ?: null,
                ]);
            }
            foreach ($questionPages as $i => $page) {
                $question = $quiz[$i];
                $page->translations()->where('language', $lang)->update([
                    'title' => $this->tr($question['prompt'], $lang),
                    'body' => $this->tr($question['explanation'] ?? [], $lang) ?: null,
                    'choices' => json_encode(array_map(fn ($c) => $this->tr($c, $lang), $question['choices']), JSON_UNESCAPED_UNICODE),
                ]);
            }
        }
    }

    /**
     * Same picking as saveLevelQuiz(), text only.
     *
     * @param  list<array<string, mixed>>  $lessons
     * @param  list<string>  $languages
     */
    private function refreshLevelQuizTexts(Level $level, array $lessons, array $languages): void
    {
        $quiz = Quiz::query()->where('level_id', $level->id)->first();
        if ($quiz === null || $lessons === []) {
            return;
        }

        $perLesson = max(2, (int) ceil(self::LEVEL_QUIZ_TARGET / count($lessons)));
        $picked = [];
        foreach ($lessons as $lesson) {
            foreach (array_slice($lesson['quiz'] ?? [], 0, $perLesson) as $question) {
                $picked[] = $question;
            }
        }

        foreach ($picked as $index => $data) {
            $question = $quiz->questions()->where('order', $index + 1)->first();
            if ($question === null || (int) $question->correct_index !== (int) $data['correctIndex']) {
                continue;
            }
            foreach ($languages as $lang) {
                $question->translations()->updateOrCreate(['language' => $lang], [
                    'prompt' => $this->tr($data['prompt'], $lang),
                    'choices' => array_map(fn ($c) => $this->tr($c, $lang), $data['choices']),
                ]);
            }
        }
    }

    /** @return list<array<string, mixed>> */
    private function readLessons(string $dir): array
    {
        if (! File::isDirectory($dir)) {
            return [];
        }

        $lessons = [];
        foreach (File::files($dir) as $file) {
            if ($file->getExtension() === 'json') {
                $lessons[] = json_decode(File::get($file->getPathname()), true, 512, JSON_THROW_ON_ERROR);
            }
        }
        usort($lessons, fn ($a, $b) => [$a['order'] ?? 0, $a['code']] <=> [$b['order'] ?? 0, $b['code']]);

        return $lessons;
    }

    /** @param array<string, mixed> $data */
    private function saveLevel(array $data): Level
    {
        $level = Level::query()->updateOrCreate(
            ['slug' => $data['slug']],
            [
                'order' => (int) $data['order'],
                'part' => (int) $data['part'],
                'status' => ContentStatus::Published,
            ],
        );

        foreach (self::LANGUAGES as $lang) {
            $level->translations()->updateOrCreate(['language' => $lang], [
                'title' => $this->tr($data['title'], $lang),
                'description' => $this->tr($data['description'] ?? [], $lang) ?: null,
                'part_title' => $this->tr($data['partTitle'] ?? [], $lang) ?: null,
            ]);
        }
        Quiz::query()->firstOrCreate(['level_id' => $level->id], ['status' => ContentStatus::Published]);

        return $level;
    }

    /** @param array<string, mixed> $data */
    private function saveLesson(Level $level, array $data): void
    {
        $code = (string) $data['code'];
        $lesson = Lesson::query()->firstOrNew(['code' => $code]);
        $lesson->fill([
            'level_id' => $level->id,
            'slug' => $lesson->slug ?? Str::slug($level->slug.'-'.$code, '_'),
            'type' => LessonType::tryFrom((string) ($data['type'] ?? 'main')) ?? LessonType::Main,
            'order' => (int) ($data['order'] ?? 0),
            'status' => ContentStatus::Published,
        ])->save();

        $pages = $data['pages'] ?? [];
        foreach (self::LANGUAGES as $lang) {
            // The old single-screen fields are now pages; keep a short intro
            // for lists and search.
            $lesson->translations()->updateOrCreate(['language' => $lang], [
                'title' => $this->tr($data['title'], $lang),
                'central_idea' => $this->firstParagraph($this->tr($pages[0]['body'] ?? [], $lang)),
                'body' => null,
                'objectives' => [],
                'key_scriptures' => [],
                'summary_points' => [],
                'reflection_questions' => [],
            ]);
        }

        $lesson->pages()->delete();
        $order = 0;
        foreach ($pages as $page) {
            $saved = $lesson->pages()->create([
                'order' => ++$order,
                'kind' => LessonPage::KIND_CONTENT,
                'image_url' => $this->imagePath($page['image'] ?? null),
                'video_url' => $page['video_url'] ?? null,
            ]);
            foreach (self::LANGUAGES as $lang) {
                $saved->translations()->create([
                    'language' => $lang,
                    'title' => $this->tr($page['title'] ?? [], $lang) ?: null,
                    'body' => $this->tr($page['body'] ?? [], $lang) ?: null,
                ]);
            }
        }

        foreach ($data['quiz'] ?? [] as $question) {
            $saved = $lesson->pages()->create([
                'order' => ++$order,
                'kind' => LessonPage::KIND_QUESTION,
                'correct_index' => (int) $question['correctIndex'],
            ]);
            foreach (self::LANGUAGES as $lang) {
                $saved->translations()->create([
                    'language' => $lang,
                    'title' => $this->tr($question['prompt'], $lang),
                    'body' => $this->tr($question['explanation'] ?? [], $lang) ?: null,
                    'choices' => array_map(fn ($c) => $this->tr($c, $lang), $question['choices']),
                ]);
            }
        }

        // Yes / No decision pages close the lesson; they are not graded.
        foreach ($data['decision'] ?? [] as $decision) {
            $this->addDecisionPage($lesson, ++$order, $decision);
        }
    }

    /** @param array<string, mixed> $decision */
    public function addDecisionPage(Lesson $lesson, int $order, array $decision): void
    {
        $page = $lesson->pages()->create(['order' => $order, 'kind' => LessonPage::KIND_DECISION]);
        foreach (self::LANGUAGES as $lang) {
            $page->translations()->create([
                'language' => $lang,
                'title' => $this->tr($decision['prompt'], $lang),
                'body' => $this->tr($decision['explanation'] ?? [], $lang) ?: null,
                'choices' => array_map(fn ($c) => $this->tr($c, $lang), $decision['choices']),
            ]);
        }
    }

    /**
     * The level quiz reuses the lesson questions: an even share per lesson,
     * about LEVEL_QUIZ_TARGET in total. Questions are updated in place by
     * order so their ids stay stable.
     *
     * @param  list<array<string, mixed>>  $lessons
     */
    private function saveLevelQuiz(Level $level, array $lessons): void
    {
        if ($lessons === []) {
            return;
        }

        $perLesson = max(2, (int) ceil(self::LEVEL_QUIZ_TARGET / count($lessons)));
        $picked = [];
        foreach ($lessons as $lesson) {
            foreach (array_slice($lesson['quiz'] ?? [], 0, $perLesson) as $question) {
                $picked[] = $question;
            }
        }

        $quiz = Quiz::query()->firstOrCreate(['level_id' => $level->id], ['status' => ContentStatus::Published]);
        $quiz->questions()->where('order', '>', count($picked))->delete();

        foreach ($picked as $index => $data) {
            $question = $quiz->questions()->updateOrCreate(
                ['order' => $index + 1],
                ['correct_index' => (int) $data['correctIndex']],
            );
            foreach (self::LANGUAGES as $lang) {
                $question->translations()->updateOrCreate(['language' => $lang], [
                    'prompt' => $this->tr($data['prompt'], $lang),
                    'choices' => array_map(fn ($c) => $this->tr($c, $lang), $data['choices']),
                ]);
            }
        }
    }

    private function imagePath(?string $key): ?string
    {
        if ($key === null || $key === '') {
            return null;
        }
        $relative = self::IMAGE_DIR.'/'.$key.'.png';
        if (! File::exists(public_path($relative))) {
            $this->command?->warn("Missing illustration: {$relative}");

            return null;
        }

        return '/'.$relative;
    }

    /** @param array<string, string>|mixed $value */
    private function tr(mixed $value, string $lang): string
    {
        if (! is_array($value)) {
            return (string) $value;
        }

        return trim((string) ($value[$lang] ?? $value['fr'] ?? ''));
    }

    /** First paragraph of a page body, without the Markdown marks. */
    private function firstParagraph(string $body): ?string
    {
        $first = trim(explode("\n\n", trim($body))[0] ?? '');
        $plain = trim(preg_replace(['/^(> |- |\d+\. )/m', '/\*\*/'], '', $first) ?? '');

        return $plain === '' ? null : $plain;
    }
}
