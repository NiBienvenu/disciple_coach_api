<?php

namespace App\Services\Certificates;

use App\Models\Certificate;
use App\Models\Level;
use App\Models\QuizAttempt;
use App\Models\User;
use App\Services\Cache\UserCacheService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CertificateService
{
    public function __construct(private readonly UserCacheService $userCache) {}

    /**
     * Issue end-of-journey certificate when level order=5 is advancement-approved.
     * PDF generated once and stored; subsequent calls return the existing row.
     */
    public function issueIfEligible(User $user, QuizAttempt $attempt, Level $level): ?Certificate
    {
        if ((int) $level->order !== 5) {
            return null;
        }

        if (! $attempt->advancement_approved) {
            return null;
        }

        $existing = Certificate::query()->where('user_id', $user->id)->first();
        if ($existing !== null) {
            return $existing;
        }

        $code = 'DC-'.strtoupper(Str::random(4)).'-'.$user->id.'-'.now()->format('Ymd');
        $name = $user->name ?: ($user->email ?? 'Disciple');

        $certificate = Certificate::query()->create([
            'user_id' => $user->id,
            'code' => $code,
            'recipient_name' => $name,
            'issued_at' => now(),
            'level_id' => $level->id,
            'quiz_attempt_id' => $attempt->id,
        ]);

        $pdfPath = $this->generatePdf($certificate);
        $certificate->update(['pdf_path' => $pdfPath]);

        $this->userCache->forget(CacheKeyCertificate::meta($user->id));

        return $certificate->fresh();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getMetaForUser(User $user): ?array
    {
        /** @var array{present: bool, data?: array<string, mixed>} $cached */
        $cached = $this->userCache->remember(
            CacheKeyCertificate::meta($user->id),
            function () use ($user) {
                $certificate = Certificate::query()->where('user_id', $user->id)->first();
                if ($certificate === null) {
                    return ['present' => false];
                }

                return [
                    'present' => true,
                    'data' => [
                        'id' => $certificate->id,
                        'code' => $certificate->code,
                        'recipient_name' => $certificate->recipient_name,
                        'issued_at' => $certificate->issued_at?->toIso8601String(),
                        'download_url' => '/api/v1/me/certificate/download',
                        'has_pdf' => $certificate->pdf_path !== null
                            && Storage::disk('public')->exists($certificate->pdf_path),
                    ],
                ];
            },
            3600,
        );

        return ($cached['present'] ?? false) ? ($cached['data'] ?? null) : null;
    }

    public function absolutePdfPath(Certificate $certificate): ?string
    {
        if ($certificate->pdf_path === null || ! Storage::disk('public')->exists($certificate->pdf_path)) {
            $path = $this->generatePdf($certificate);
            $certificate->update(['pdf_path' => $path]);
            $certificate->refresh();
        }

        return Storage::disk('public')->path($certificate->pdf_path);
    }

    private function generatePdf(Certificate $certificate): string
    {
        $relative = 'certificates/'.$certificate->user_id.'/'.$certificate->code.'.pdf';
        $issuedAt = $certificate->issued_at?->format('d/m/Y') ?? now()->format('d/m/Y');

        $pdf = SimplePdf::landscapeA4([
            ['text' => 'Campus pour Christ Burundi - DiscipleCoach', 'x' => 180, 'y' => 520, 'size' => 12],
            ['text' => 'Certificat de parcours / Certificate of Completion', 'x' => 160, 'y' => 470, 'size' => 18],
            ['text' => 'Est decerne a / Awarded to', 'x' => 300, 'y' => 410, 'size' => 12],
            ['text' => $certificate->recipient_name, 'x' => 250, 'y' => 370, 'size' => 22],
            ['text' => 'pour avoir acheve le parcours DiscipleCoach (niveaux 1 a 5),', 'x' => 150, 'y' => 300, 'size' => 11],
            ['text' => 'valide par un coach apres approbation du niveau final.', 'x' => 170, 'y' => 280, 'size' => 11],
            ['text' => 'for completing the DiscipleCoach discipleship journey (levels 1-5),', 'x' => 140, 'y' => 250, 'size' => 11],
            ['text' => 'confirmed by a coach upon final-level approval.', 'x' => 190, 'y' => 230, 'size' => 11],
            ['text' => 'Date: '.$issuedAt, 'x' => 320, 'y' => 160, 'size' => 11],
            ['text' => 'Ref: '.$certificate->code, 'x' => 300, 'y' => 135, 'size' => 11],
        ]);

        Storage::disk('public')->put($relative, $pdf);

        return $relative;
    }
}

final class CacheKeyCertificate
{
    public static function meta(int|string $userId): string
    {
        return 'user:v1:'.$userId.':certificate';
    }
}
