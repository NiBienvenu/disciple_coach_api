<?php

namespace Tests\Feature\Api\V1;

use App\Enums\ContentStatus;
use App\Enums\LessonType;
use App\Models\Level;
use App\Models\Lesson;
use App\Models\Quiz;
use App\Models\QuizQuestion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CurriculumTest extends TestCase
{
    use RefreshDatabase;

    private Level $level;

    private Lesson $lesson;

    private Quiz $quiz;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedMinimalCurriculum();
    }

    public function test_anonymous_can_list_levels(): void
    {
        $response = $this->getJson('/api/v1/levels');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.lang', 'fr')
            ->assertJsonPath('data.0.slug', 'track_1')
            ->assertJsonPath('data.0.title', 'Niveau 1 : Fondation');

        $this->assertArrayNotHasKey('progress', $response->json('data.0') ?? []);
    }

    public function test_lesson_detail_has_content_and_no_progress(): void
    {
        $response = $this->getJson('/api/v1/lessons/'.$this->lesson->id.'?lang=en');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.lang', 'en')
            ->assertJsonPath('data.code', '1M01')
            ->assertJsonPath('data.title', 'How to experience God\'s love')
            ->assertJsonPath('data.content.central_idea', 'Confess sin daily.')
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'level_id',
                    'code',
                    'slug',
                    'type',
                    'order',
                    'title',
                    'content' => [
                        'central_idea',
                        'objectives',
                        'key_scriptures',
                        'summary_points',
                        'reflection_questions',
                    ],
                ],
            ]);

        $payload = $response->json('data');
        $this->assertArrayNotHasKey('progress', $payload);
        $this->assertArrayNotHasKey('completed_at', $payload);
        $this->assertArrayNotHasKey('is_favorite', $payload);
        $this->assertArrayNotHasKey('status_progress', $payload);
    }

    public function test_quiz_public_payload_has_no_correct_index(): void
    {
        $response = $this->getJson('/api/v1/levels/'.$this->level->id.'/quiz?lang=fr');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.level_id', $this->level->id)
            ->assertJsonPath('data.questions.0.prompt', 'Qu\'est-ce que la respiration spirituelle ?')
            ->assertJsonCount(2, 'data.questions.0.choices');

        $json = $response->getContent();
        $this->assertStringNotContainsString('correct_index', $json);
        $this->assertStringNotContainsString('correctIndex', $json);

        $question = $response->json('data.questions.0');
        $this->assertArrayHasKey('prompt', $question);
        $this->assertArrayHasKey('choices', $question);
        $this->assertArrayNotHasKey('correct_index', $question);

        $this->assertDatabaseHas('quiz_questions', [
            'id' => $this->quiz->questions()->first()->id,
            'correct_index' => 1,
        ]);
    }

    public function test_curriculum_index_includes_lessons(): void
    {
        $this->getJson('/api/v1/curriculum?lang=rn')
            ->assertOk()
            ->assertJsonPath('meta.lang', 'rn')
            ->assertJsonPath('data.0.lessons.0.title', 'Urukundo n\'ikigongwe');
    }

    private function seedMinimalCurriculum(): void
    {
        $this->level = Level::query()->create([
            'slug' => 'track_1',
            'order' => 1,
            'previous_level_id' => null,
            'status' => ContentStatus::Published,
        ]);

        foreach ([
            'fr' => ['Niveau 1 : Fondation', 'Les fondements.'],
            'rn' => ['Urwego rwa 1', 'Ishingiro.'],
            'en' => ['Level 1: Foundation', 'The foundations.'],
        ] as $language => [$title, $description]) {
            $this->level->translations()->create([
                'language' => $language,
                'title' => $title,
                'description' => $description,
            ]);
        }

        $this->lesson = Lesson::query()->create([
            'level_id' => $this->level->id,
            'code' => '1M01',
            'slug' => 'track_1_lesson_01',
            'type' => LessonType::Main,
            'order' => 1,
            'status' => ContentStatus::Published,
        ]);

        $this->lesson->translations()->create([
            'language' => 'fr',
            'title' => 'Comment expérimenter l\'amour',
            'central_idea' => 'Confesser ses péchés.',
            'objectives' => ['Objectif 1'],
            'key_scriptures' => [['reference' => '1 Jean 1.9', 'text' => 'Si nous confessons...']],
            'summary_points' => ['Point 1'],
            'reflection_questions' => ['Question 1?'],
        ]);

        $this->lesson->translations()->create([
            'language' => 'en',
            'title' => 'How to experience God\'s love',
            'central_idea' => 'Confess sin daily.',
            'objectives' => ['Objective 1'],
            'key_scriptures' => [['reference' => '1 John 1:9', 'text' => 'If we confess...']],
            'summary_points' => ['Point 1'],
            'reflection_questions' => ['Question 1?'],
        ]);

        $this->lesson->translations()->create([
            'language' => 'rn',
            'title' => 'Urukundo n\'ikigongwe',
            'central_idea' => 'Kwatura ivyaha.',
            'objectives' => ['Intego 1'],
            'key_scriptures' => [],
            'summary_points' => [],
            'reflection_questions' => [],
        ]);

        $this->quiz = Quiz::query()->create([
            'level_id' => $this->level->id,
            'status' => ContentStatus::Published,
        ]);

        /** @var QuizQuestion $question */
        $question = $this->quiz->questions()->create([
            'order' => 1,
            'correct_index' => 1,
        ]);

        $question->translations()->create([
            'language' => 'fr',
            'prompt' => 'Qu\'est-ce que la respiration spirituelle ?',
            'choices' => [
                'Jeûner chaque jour',
                'Confesser le péché puis recevoir l\'Esprit',
            ],
        ]);

        $question->translations()->create([
            'language' => 'en',
            'prompt' => 'What is spiritual breathing?',
            'choices' => [
                'Fasting every day',
                'Confess sin then receive the Spirit',
            ],
        ]);
    }
}
