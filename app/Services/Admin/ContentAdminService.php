<?php

namespace App\Services\Admin;

use App\Enums\ContentStatus;
use App\Enums\LessonType;
use App\Models\Lesson;
use App\Models\Level;
use App\Models\Quiz;
use App\Models\QuizQuestion;
use App\Services\Cache\CacheKey;
use App\Services\Push\FcmService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Admin editing of curriculum content. Every write bumps the content
 * revision (old cache is skipped) and tells the apps to reload.
 */
class ContentAdminService
{
    public const LANGUAGES = ['fr', 'rn', 'en'];

    /** Lesson images live in public/uploads/lessons, served as-is by the web server. */
    public const IMAGE_DIR = 'uploads/lessons';

    public function __construct(private readonly FcmService $fcm) {}

    /** @return list<array<string, mixed>> */
    public function levels(): array
    {
        return Level::query()
            ->orderBy('order')
            ->with(['translations', 'lessons.translations', 'quiz.questions'])
            ->get()
            ->map(fn (Level $level) => $this->levelPayload($level, withLessons: true))
            ->all();
    }

    /** @param array<string, mixed> $data */
    public function saveLevel(?Level $level, array $data): Level
    {
        $level = DB::transaction(function () use ($level, $data) {
            $level ??= new Level(['slug' => 'level_'.Str::lower(Str::random(8))]);
            $level->fill([
                'order' => (int) ($data['order'] ?? $level->order ?? (Level::query()->max('order') + 1)),
                'part' => (int) ($data['part'] ?? $level->part ?? 3),
                'previous_level_id' => array_key_exists('previous_level_id', $data) ? $data['previous_level_id'] : $level->previous_level_id,
                'status' => ContentStatus::Published,
            ])->save();

            foreach ($data['translations'] ?? [] as $lang => $t) {
                $level->translations()->updateOrCreate(['language' => $lang], [
                    'title' => (string) ($t['title'] ?? ''),
                    'description' => $t['description'] ?? null,
                    'part_title' => $t['part_title'] ?? null,
                ]);
            }
            Quiz::query()->firstOrCreate(['level_id' => $level->id], ['status' => ContentStatus::Published]);

            return $level;
        });

        $this->changed();

        return $level->fresh(['translations']);
    }

    public function deleteLevel(Level $level): void
    {
        DB::transaction(function () use ($level) {
            // Keep the unlock chain intact: the next level now follows the previous one.
            Level::query()->where('previous_level_id', $level->id)->update(['previous_level_id' => $level->previous_level_id]);
            $level->delete();
        });
        $this->changed();
    }

    /** @return array<string, mixed> */
    public function lesson(Lesson $lesson): array
    {
        $lesson->load('translations');

        return [
            'id' => $lesson->id,
            'level_id' => $lesson->level_id,
            'code' => $lesson->code,
            'type' => $lesson->type instanceof \BackedEnum ? $lesson->type->value : $lesson->type,
            'order' => $lesson->order,
            'video_url' => $lesson->video_url,
            'image_url' => $lesson->imagePublicUrl(),
            'translations' => $lesson->translations->mapWithKeys(fn ($t) => [$t->language => [
                'title' => $t->title,
                'central_idea' => $t->central_idea,
                'body' => $t->body,
                'objectives' => $t->objectives ?? [],
                'key_scriptures' => $t->key_scriptures ?? [],
                'summary_points' => $t->summary_points ?? [],
                'reflection_questions' => $t->reflection_questions ?? [],
            ]]),
        ];
    }

    /** @param array<string, mixed> $data */
    public function saveLesson(Level $level, ?Lesson $lesson, array $data): Lesson
    {
        $code = trim((string) ($data['code'] ?? $lesson?->code ?? ''));
        $clash = Lesson::query()->where('code', $code)->when($lesson, fn ($q) => $q->where('id', '!=', $lesson->id))->exists();
        if ($code === '' || $clash) {
            throw ValidationException::withMessages(['code' => ['This lesson code is empty or already used.']]);
        }

        $lesson = DB::transaction(function () use ($level, $lesson, $data, $code) {
            $lesson ??= new Lesson(['level_id' => $level->id]);
            $lesson->fill([
                'code' => $code,
                'slug' => $lesson->slug ?? Str::slug($level->slug.'-'.$code, '_'),
                'type' => LessonType::tryFrom((string) ($data['type'] ?? 'main')) ?? LessonType::Main,
                'order' => (int) ($data['order'] ?? $lesson->order ?? ($level->lessons()->max('order') + 1)),
                'status' => ContentStatus::Published,
            ]);
            if (array_key_exists('video_url', $data)) {
                $video = trim((string) $data['video_url']);
                $lesson->video_url = $video === '' ? null : $video;
            }
            $lesson->save();

            foreach ($data['translations'] ?? [] as $lang => $t) {
                $lesson->translations()->updateOrCreate(['language' => $lang], [
                    'title' => (string) ($t['title'] ?? ''),
                    'central_idea' => $t['central_idea'] ?? null,
                    'body' => isset($t['body']) && trim((string) $t['body']) !== '' ? (string) $t['body'] : null,
                    'objectives' => $this->lines($t['objectives'] ?? []),
                    'key_scriptures' => array_values(array_filter(
                        array_map(fn ($s) => ['reference' => trim((string) ($s['reference'] ?? '')), 'text' => trim((string) ($s['text'] ?? ''))], $t['key_scriptures'] ?? []),
                        fn ($s) => $s['reference'] !== '' || $s['text'] !== '',
                    )),
                    'summary_points' => $this->lines($t['summary_points'] ?? []),
                    'reflection_questions' => $this->lines($t['reflection_questions'] ?? []),
                ]);
            }

            return $lesson;
        });

        $this->changed();

        return $lesson;
    }

    public function deleteLesson(Lesson $lesson): void
    {
        $this->deleteImageFile($lesson->image_url);
        $lesson->delete();
        $this->changed();
    }

    /** Saves the file under public/uploads/lessons and replaces any old image. */
    public function saveLessonImage(Lesson $lesson, UploadedFile $file): Lesson
    {
        $dir = public_path(self::IMAGE_DIR);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $name = 'lesson-'.$lesson->id.'-'.Str::lower(Str::random(10)).'.'.($file->guessExtension() ?: 'jpg');
        $file->move($dir, $name);

        $old = $lesson->image_url;
        $lesson->forceFill(['image_url' => '/'.self::IMAGE_DIR.'/'.$name])->save();
        $this->deleteImageFile($old);
        $this->changed();

        return $lesson;
    }

    public function removeLessonImage(Lesson $lesson): Lesson
    {
        $this->deleteImageFile($lesson->image_url);
        $lesson->forceFill(['image_url' => null])->save();
        $this->changed();

        return $lesson;
    }

    /** Only ever deletes files inside our own upload folder. */
    private function deleteImageFile(?string $path): void
    {
        if ($path === null || ! str_starts_with($path, '/'.self::IMAGE_DIR.'/')) {
            return;
        }
        $file = public_path(ltrim($path, '/'));
        if (is_file($file) && basename(dirname($file)) === basename(self::IMAGE_DIR)) {
            @unlink($file);
        }
    }

    /** @return list<array<string, mixed>> */
    public function quiz(Level $level): array
    {
        $quiz = Quiz::query()->firstOrCreate(['level_id' => $level->id], ['status' => ContentStatus::Published]);

        return $quiz->questions()->orderBy('order')->with('translations')->get()
            ->map(fn (QuizQuestion $q) => $this->questionPayload($q))->all();
    }

    /** @return array{pass_percent: int} */
    public function saveQuizSettings(Level $level, int $passPercent): array
    {
        $quiz = Quiz::query()->firstOrCreate(['level_id' => $level->id], ['status' => ContentStatus::Published]);
        $quiz->update(['pass_percent' => $passPercent]);
        $this->changed();

        return ['pass_percent' => $quiz->passPercent()];
    }

    /** @param array<string, mixed> $data */
    public function saveQuestion(Level $level, ?QuizQuestion $question, array $data): QuizQuestion
    {
        $question = DB::transaction(function () use ($level, $question, $data) {
            $quiz = Quiz::query()->firstOrCreate(['level_id' => $level->id], ['status' => ContentStatus::Published]);
            $question ??= new QuizQuestion(['quiz_id' => $quiz->id]);
            $choiceCount = count($data['translations']['fr']['choices'] ?? $data['translations'][array_key_first($data['translations'] ?? []) ?? 'fr']['choices'] ?? []);
            $correct = (int) ($data['correct_index'] ?? $question->correct_index ?? 0);
            if ($choiceCount > 0 && ($correct < 0 || $correct >= $choiceCount)) {
                throw ValidationException::withMessages(['correct_index' => ['The correct answer must be one of the choices.']]);
            }
            $question->fill([
                'order' => (int) ($data['order'] ?? $question->order ?? ($quiz->questions()->max('order') + 1)),
                'correct_index' => $correct,
            ])->save();

            foreach ($data['translations'] ?? [] as $lang => $t) {
                $question->translations()->updateOrCreate(['language' => $lang], [
                    'prompt' => (string) ($t['prompt'] ?? ''),
                    'choices' => $this->lines($t['choices'] ?? []),
                ]);
            }

            return $question;
        });

        $this->changed();

        return $question;
    }

    public function deleteQuestion(QuizQuestion $question): void
    {
        $question->delete();
        $this->changed();
    }

    /** @return array<string, mixed> */
    public function questionPayload(QuizQuestion $q): array
    {
        $q->loadMissing('translations');

        return [
            'id' => $q->id,
            'order' => $q->order,
            'correct_index' => $q->correct_index,
            'translations' => $q->translations->mapWithKeys(fn ($t) => [$t->language => [
                'prompt' => $t->prompt,
                'choices' => $t->choices ?? [],
            ]]),
        ];
    }

    /** @return array<string, mixed> */
    public function levelPayload(Level $level, bool $withLessons = false): array
    {
        $level->loadMissing('translations');
        $data = [
            'id' => $level->id,
            'slug' => $level->slug,
            'order' => $level->order,
            'part' => $level->part,
            'previous_level_id' => $level->previous_level_id,
            'translations' => $level->translations->mapWithKeys(fn ($t) => [$t->language => [
                'title' => $t->title,
                'description' => $t->description,
                'part_title' => $t->part_title,
            ]]),
        ];
        if ($withLessons) {
            $data['quiz_questions_count'] = $level->quiz?->questions->count() ?? 0;
            $data['lessons'] = $level->lessons->map(fn (Lesson $l) => [
                'id' => $l->id,
                'code' => $l->code,
                'type' => $l->type instanceof \BackedEnum ? $l->type->value : $l->type,
                'order' => $l->order,
                'title' => $l->translations->mapWithKeys(fn ($t) => [$t->language => $t->title]),
            ])->values()->all();
        }

        return $data;
    }

    /** @return list<string> */
    private function lines(mixed $value): array
    {
        $items = is_array($value) ? $value : preg_split('/\R/', (string) $value);

        return array_values(array_filter(array_map(fn ($v) => trim((string) $v), $items), fn ($v) => $v !== ''));
    }

    /** New revision + silent push so open apps reload content right away. */
    private function changed(): void
    {
        $revision = CacheKey::bumpRevision();
        $fcm = $this->fcm;
        dispatch(fn () => $fcm->sendToTopic('curriculum', ['type' => 'curriculum_changed', 'revision' => $revision]))->afterResponse();
    }
}
