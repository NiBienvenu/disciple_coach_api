<?php

use App\Http\Controllers\Api\V1\AdminController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CertificateController;
use App\Http\Controllers\Api\V1\CoachDashboardController;
use App\Http\Controllers\Api\V1\CoachRequestController;
use App\Http\Controllers\Api\V1\ContentAdminController;
use App\Http\Controllers\Api\V1\CoachingSessionController;
use App\Http\Controllers\Api\V1\CurriculumController;
use App\Http\Controllers\Api\V1\DeviceTokenController;
use App\Http\Controllers\Api\V1\GoalController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\InviteController;
use App\Http\Controllers\Api\V1\LessonController;
use App\Http\Controllers\Api\V1\LevelController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\ProgressController;
use App\Http\Controllers\Api\V1\QuizAttemptController;
use App\Http\Controllers\Api\V1\QuizController;
use App\Http\Controllers\Api\V1\RelationshipController;
use App\Http\Controllers\Api\V1\RelationshipMessageController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API v1 Routes
|--------------------------------------------------------------------------
|
| Public: health, auth register/login/password, curriculum reads (Wave C).
| Auth: profile, progress, quiz submit, coaching — never on public routes.
|
*/

Route::middleware('throttle:api')->group(function (): void {
    Route::get('/health', HealthController::class)->name('health');

    Route::prefix('auth')->group(function (): void {
        Route::post('/register', [AuthController::class, 'register'])->name('auth.register');
        Route::post('/login', [AuthController::class, 'login'])->name('auth.login');
        Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->name('auth.forgot-password');
        Route::post('/reset-password', [AuthController::class, 'resetPassword'])->name('auth.reset-password');
    });

    Route::get('/curriculum', [CurriculumController::class, 'index'])->name('curriculum.index');
    Route::get('/curriculum/revision', [CurriculumController::class, 'revision'])->name('curriculum.revision');
    Route::get('/levels', [LevelController::class, 'index'])->name('levels.index');
    Route::get('/levels/{level}', [LevelController::class, 'show'])->name('levels.show');
    Route::get('/levels/{level}/lessons', [LessonController::class, 'index'])->name('levels.lessons.index');
    Route::get('/lessons/{lesson}', [LessonController::class, 'show'])->name('lessons.show');
    Route::get('/levels/{level}/quiz', [QuizController::class, 'show'])->name('levels.quiz.show');
});

Route::middleware(['throttle:api', 'auth:sanctum'])->group(function (): void {
    Route::post('/auth/logout', [AuthController::class, 'logout'])->name('auth.logout');

    Route::get('/me', [ProfileController::class, 'show'])->name('me.show');
    Route::patch('/me', [ProfileController::class, 'update'])->name('me.update');
    Route::put('/me/roles', [ProfileController::class, 'updateRoles'])->name('me.roles');
    Route::put('/me/password', [ProfileController::class, 'updatePassword'])->name('me.password');

    // Wave D — progress + quiz + coach gate
    Route::get('/me/progress', [ProgressController::class, 'progress'])->name('me.progress');
    Route::put('/me/progress/lessons/{lesson}', [ProgressController::class, 'completeLesson'])->name('me.progress.lessons.update');
    Route::get('/me/level-progress', [ProgressController::class, 'levelProgress'])->name('me.level-progress');
    Route::get('/me/dashboard', [ProgressController::class, 'dashboard'])->name('me.dashboard');

    Route::get('/me/certificate', [CertificateController::class, 'show'])->name('me.certificate');
    Route::get('/me/certificate/download', [CertificateController::class, 'download'])->name('me.certificate.download');

    Route::post('/levels/{level}/quiz-attempts', [QuizAttemptController::class, 'store'])->name('levels.quiz-attempts.store');
    Route::get('/me/quiz-attempts', [QuizAttemptController::class, 'mine'])->name('me.quiz-attempts');

    Route::get('/coach/disciples/{user}/quiz-attempts', [QuizAttemptController::class, 'forDisciple'])->name('coach.disciples.quiz-attempts');
    Route::get('/coach/disciples/{user}/progress', [QuizAttemptController::class, 'discipleProgress'])->name('coach.disciples.progress');
    Route::post('/quiz-attempts/{attempt}/decide', [QuizAttemptController::class, 'decide'])->name('quiz-attempts.decide');

    // Wave E — coaching
    Route::post('/invites', [InviteController::class, 'store'])->name('invites.store');
    Route::post('/invites/{code}/redeem', [InviteController::class, 'redeem'])->name('invites.redeem');

    Route::get('/relationships', [RelationshipController::class, 'index'])->name('relationships.index');
    Route::get('/relationships/{relationship}', [RelationshipController::class, 'show'])->name('relationships.show');
    Route::post('/relationships/{relationship}/end', [RelationshipController::class, 'end'])->name('relationships.end');

    Route::get('/relationships/{relationship}/sessions', [CoachingSessionController::class, 'index'])->name('relationships.sessions.index');
    Route::post('/relationships/{relationship}/sessions', [CoachingSessionController::class, 'store'])->name('relationships.sessions.store');
    Route::patch('/relationships/{relationship}/sessions/{session}', [CoachingSessionController::class, 'update'])->name('relationships.sessions.update');

    Route::get('/relationships/{relationship}/goals', [GoalController::class, 'index'])->name('relationships.goals.index');
    Route::post('/relationships/{relationship}/goals', [GoalController::class, 'store'])->name('relationships.goals.store');
    Route::patch('/relationships/{relationship}/goals/{goal}', [GoalController::class, 'update'])->name('relationships.goals.update');
    Route::delete('/relationships/{relationship}/goals/{goal}', [GoalController::class, 'destroy'])->name('relationships.goals.destroy');
    Route::patch('/milestones/{milestone}', [GoalController::class, 'updateMilestone'])->name('milestones.update');

    Route::get('/relationships/{relationship}/messages', [RelationshipMessageController::class, 'index'])->name('relationships.messages.index');
    Route::post('/relationships/{relationship}/messages', [RelationshipMessageController::class, 'store'])->name('relationships.messages.store');
    Route::post('/relationships/{relationship}/read', [RelationshipMessageController::class, 'markRead'])->name('relationships.read');

    Route::get('/coach/dashboard', CoachDashboardController::class)->name('coach.dashboard');

    // Push notifications (FCM device tokens)
    Route::post('/me/device-tokens', [DeviceTokenController::class, 'store'])->name('me.device-tokens.store');
    Route::delete('/me/device-tokens', [DeviceTokenController::class, 'destroy'])->name('me.device-tokens.destroy');

    // Disciple picks a coach; coach accepts or declines.
    Route::get('/coaches', [CoachRequestController::class, 'coaches'])->name('coaches.index');
    Route::get('/coach-requests', [CoachRequestController::class, 'index'])->name('coach-requests.index');
    Route::post('/coach-requests', [CoachRequestController::class, 'store'])->name('coach-requests.store');
    Route::post('/coach-requests/{coachRequest}/accept', [CoachRequestController::class, 'accept'])->name('coach-requests.accept');
    Route::post('/coach-requests/{coachRequest}/decline', [CoachRequestController::class, 'decline'])->name('coach-requests.decline');
    Route::post('/coach-requests/{coachRequest}/cancel', [CoachRequestController::class, 'cancel'])->name('coach-requests.cancel');

    Route::middleware('role:admin')->prefix('admin')->group(function (): void {
        Route::get('/users', [AdminController::class, 'users'])->name('admin.users.index');
        Route::patch('/users/{user}/active', [AdminController::class, 'setActive'])->name('admin.users.active');
        Route::post('/users/{user}/coach-status', [AdminController::class, 'setCoachStatus'])->name('admin.users.coach-status');
        Route::get('/stats', [AdminController::class, 'stats'])->name('admin.stats');
        Route::get('/coach-roster', [AdminController::class, 'coachRoster'])->name('admin.coach-roster');

        // Curriculum content CRUD (all languages)
        Route::get('/content/levels', [ContentAdminController::class, 'levels'])->name('admin.content.levels');
        Route::post('/content/levels', [ContentAdminController::class, 'storeLevel'])->name('admin.content.levels.store');
        Route::patch('/content/levels/{level}', [ContentAdminController::class, 'updateLevel'])->name('admin.content.levels.update');
        Route::delete('/content/levels/{level}', [ContentAdminController::class, 'destroyLevel'])->name('admin.content.levels.destroy');
        Route::post('/content/levels/{level}/lessons', [ContentAdminController::class, 'storeLesson'])->name('admin.content.lessons.store');
        Route::get('/content/lessons/{lesson}', [ContentAdminController::class, 'showLesson'])->name('admin.content.lessons.show');
        Route::patch('/content/lessons/{lesson}', [ContentAdminController::class, 'updateLesson'])->name('admin.content.lessons.update');
        Route::delete('/content/lessons/{lesson}', [ContentAdminController::class, 'destroyLesson'])->name('admin.content.lessons.destroy');
        Route::post('/content/lessons/{lesson}/image', [ContentAdminController::class, 'storeLessonImage'])->name('admin.content.lessons.image.store');
        Route::delete('/content/lessons/{lesson}/image', [ContentAdminController::class, 'destroyLessonImage'])->name('admin.content.lessons.image.destroy');
        Route::get('/content/levels/{level}/quiz', [ContentAdminController::class, 'quiz'])->name('admin.content.quiz');
        Route::post('/content/levels/{level}/quiz/questions', [ContentAdminController::class, 'storeQuestion'])->name('admin.content.questions.store');
        Route::patch('/content/quiz-questions/{question}', [ContentAdminController::class, 'updateQuestion'])->name('admin.content.questions.update');
        Route::delete('/content/quiz-questions/{question}', [ContentAdminController::class, 'destroyQuestion'])->name('admin.content.questions.destroy');

        // Push diagnostics
        Route::get('/push/status', [AdminController::class, 'pushStatus'])->name('admin.push.status');
        Route::post('/push/test', [AdminController::class, 'pushTest'])->name('admin.push.test');
    });
});
