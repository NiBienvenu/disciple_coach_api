<?php

namespace App\Console\Commands;

use App\Enums\PreferredLanguage;
use App\Services\Cache\CurriculumCacheService;
use App\Services\Curriculum\CurriculumService;
use Illuminate\Console\Command;

class WarmCurriculumCacheCommand extends Command
{
    protected $signature = 'curriculum:warm-cache';

    protected $description = 'Warm Redis curriculum cache for all preferred languages';

    public function handle(CurriculumService $curriculum, CurriculumCacheService $cache): int
    {
        foreach (PreferredLanguage::cases() as $language) {
            $lang = $language->value;
            $this->info("Warming curriculum cache for {$lang}...");

            // Drop language buckets then regenerate (targeted, not Cache::flush).
            $cache->forgetLanguage($lang);

            $curriculum->getCurriculum($lang);
            $curriculum->getLevels($lang);

            foreach ($curriculum->getLevels($lang) as $level) {
                $id = $level['id'] ?? null;
                if ($id === null) {
                    continue;
                }
                $curriculum->getLevel($id, $lang, withLessons: true);
                $curriculum->getLessonsForLevel($id, $lang);
                $curriculum->getQuizForLevel($id, $lang);

                foreach ($level['lessons'] ?? [] as $lesson) {
                    if (isset($lesson['id'])) {
                        $curriculum->getLesson($lesson['id'], $lang);
                    }
                }
            }
        }

        $this->info('Curriculum cache warmed.');

        return self::SUCCESS;
    }
}
