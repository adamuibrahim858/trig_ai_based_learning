<?php

namespace App\Services\Certificate;

use App\Models\Certificate;
use App\Models\Course;
use App\Models\Test;
use App\Models\TestAttempt;
use App\Models\User;
use App\Services\Learning\LessonProgressionService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

class CertificateService
{
    public function __construct(
        protected LessonProgressionService $progressionService
    ) {}

    /**
     * Check if a student is eligible for certificate issuance.
     *
     * @return array{eligible: bool, reason: ?string, final_score: ?float}
     */
    public function checkEligibility(User $user, Course $course): array
    {
        // 1. Must satisfy all curriculum prerequisites
        $curriculumCheck = $this->progressionService->checkFinalExamEligibility($user, $course);
        if (!$curriculumCheck['eligible']) {
            return [
                'eligible' => false,
                'reason' => $curriculumCheck['reason'],
                'final_score' => null,
            ];
        }

        // 2. Final examination must be completed and passed with >= 70%
        $finalExam = Test::where('course_id', $course->id)
            ->where('type', 'final_exam')
            ->first();

        if (!$finalExam) {
            return [
                'eligible' => false,
                'reason' => 'No final examination configured for this course.',
                'final_score' => null,
            ];
        }

        $bestPassingAttempt = TestAttempt::where('user_id', $user->id)
            ->where('test_id', $finalExam->id)
            ->where('passed', true)
            ->orderByDesc('percentage')
            ->first();

        if (!$bestPassingAttempt) {
            return [
                'eligible' => false,
                'reason' => "You have not yet passed the comprehensive final examination with at least {$finalExam->pass_mark}%.",
                'final_score' => null,
            ];
        }

        return [
            'eligible' => true,
            'reason' => null,
            'final_score' => (float)$bestPassingAttempt->percentage,
        ];
    }

    /**
     * Issue a certificate if eligible. Prevent duplicate issuance.
     */
    public function issueCertificate(User $user, Course $course): Certificate
    {
        // Check for existing certificate
        $existing = Certificate::where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->first();

        if ($existing) {
            return $existing;
        }

        $eligibility = $this->checkEligibility($user, $course);
        if (!$eligibility['eligible']) {
            throw new HttpException(403, $eligibility['reason']);
        }

        return DB::transaction(function () use ($user, $course, $eligibility) {
            $certificateNumber = 'TRIG-' . strtoupper(Str::random(4)) . '-' . strtoupper(Str::random(4));

            return Certificate::create([
                'user_id' => $user->id,
                'course_id' => $course->id,
                'certificate_number' => $certificateNumber,
                'final_score' => $eligibility['final_score'],
                'issued_at' => now(),
            ]);
        });
    }

    /**
     * Generate PDF response for a certificate.
     */
    public function generatePdf(Certificate $certificate)
    {
        $user = $certificate->user;
        $course = $certificate->course;
        $verifyUrl = url("/api/v1/certificates/verify/{$certificate->certificate_number}");

        $pdf = Pdf::loadView('certificates.template', [
            'studentName' => $user->name,
            'courseTitle' => $course->title,
            'certificateNumber' => $certificate->certificate_number,
            'finalScore' => $certificate->final_score,
            'issuedDate' => $certificate->issued_at->format('F d, Y'),
            'verifyUrl' => $verifyUrl,
        ])->setPaper('a4', 'landscape');

        return $pdf->download("Certificate-{$certificate->certificate_number}.pdf");
    }

    /**
     * Verify a certificate number and return public minimal verification payload.
     */
    public function verifyCertificate(string $certificateNumber): ?array
    {
        $cert = Certificate::with(['user:id,name', 'course:id,title'])
            ->where('certificate_number', $certificateNumber)
            ->first();

        if (!$cert) {
            return null;
        }

        return [
            'valid' => true,
            'certificate_number' => $cert->certificate_number,
            'student_name' => $cert->user->name,
            'course_title' => $cert->course->title,
            'final_score' => $cert->final_score,
            'issued_at' => $cert->issued_at->toIso8601String(),
        ];
    }
}
