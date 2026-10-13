<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\CertificateResource;
use App\Models\Certificate;
use App\Models\Course;
use App\Services\Certificate\CertificateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CertificateController extends ApiController
{
    public function __construct(
        protected CertificateService $certificateService
    ) {}

    /**
     * GET /api/v1/certificates/eligibility
     * Check if authenticated student qualifies for certificate issuance.
     */
    public function eligibility(Request $request): JsonResponse
    {
        $courseId = $request->query('course_id');
        $course = $courseId ? Course::findOrFail($courseId) : Course::firstOrFail();

        $eligibility = $this->certificateService->checkEligibility($request->user(), $course);

        return $this->success($eligibility);
    }

    /**
     * GET /api/v1/certificates
     * List certificates issued to authenticated student.
     */
    public function index(Request $request): JsonResponse
    {
        $certificates = Certificate::with('course:id,title')
            ->where('user_id', $request->user()->id)
            ->latest('issued_at')
            ->get();

        return $this->success(CertificateResource::collection($certificates));
    }

    /**
     * POST /api/v1/certificates
     * Issue certificate for completed course.
     */
    public function issue(Request $request): JsonResponse
    {
        $courseId = $request->input('course_id');
        $course = $courseId ? Course::findOrFail($courseId) : Course::firstOrFail();

        $certificate = $this->certificateService->issueCertificate($request->user(), $course);

        return $this->success(
            new CertificateResource($certificate),
            'Certificate issued successfully.',
            201
        );
    }

    /**
     * GET /api/v1/certificates/{certificate}/download
     * Download or stream the certificate PDF.
     */
    public function download(Request $request, Certificate $certificate)
    {
        // Enforce ownership: only the owner or an admin may download the PDF
        if ($certificate->user_id !== $request->user()->id && $request->user()->role !== 'admin') {
            return $this->error('Unauthorized to download this certificate.', null, 403);
        }

        return $this->certificateService->generatePdf($certificate);
    }

    /**
     * GET /api/v1/certificates/verify/{certificateNumber}
     * Public endpoint to verify certificate authenticity.
     */
    public function verify(string $certificateNumber): JsonResponse
    {
        $data = $this->certificateService->verifyCertificate($certificateNumber);

        if (!$data) {
            return $this->error('Certificate not found or invalid.', null, 404);
        }

        return $this->success($data, 'Certificate verified successfully.');
    }
}
