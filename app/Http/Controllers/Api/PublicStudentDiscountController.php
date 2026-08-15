<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Domain\ShopifyInstallation;
use App\Models\Domain\StudentDiscountCampaign;
use App\Services\Shopify\ShopifyAuthService;
use App\Services\StudentDiscounts\StudentDiscountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class PublicStudentDiscountController extends Controller
{
    public function __construct(
        private readonly ShopifyAuthService $auth,
        private readonly StudentDiscountService $discounts,
    ) {}

    public function options(Request $request): JsonResponse
    {
        return $this->response($request, null, [], 204);
    }

    public function show(Request $request): JsonResponse
    {
        [$installation, $campaign] = $this->context($request);
        abort_unless((bool) $campaign->enabled, 404, 'Student discounts are not enabled for this store.');

        return $this->response($request, $campaign, [
            'enabled' => true,
            'campaign' => $this->discounts->publicCampaign($campaign),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        [$installation, $campaign] = $this->context($request);
        abort_unless((bool) $campaign->enabled, 404, 'Student discounts are not enabled for this store.');
        $this->assertOrigin($request, $campaign);
        if ($request->input('website')) {
            return $this->response($request, $campaign, ['ok' => true]);
        }

        $email = strtolower(trim((string) $request->input('email')));
        abort_unless(filter_var($email, FILTER_VALIDATE_EMAIL) && strlen($email) <= 254, 422, 'Enter a valid email address.');
        $key = 'student-discount:'.hash('sha256', $installation->storeId.'|'.$request->ip());
        if (RateLimiter::tooManyAttempts($key, 5)) {
            abort(429, 'Too many attempts. Please try again later.');
        }
        RateLimiter::hit($key, 3600);
        $ipHash = hash_hmac('sha256', (string) $request->ip(), (string) config('app.key'));
        $action = (string) $request->input('action');

        if ($action === 'CHECK_EDUCATION_EMAIL') {
            $payload = $this->discounts->educationEmail($installation, $campaign, $email, $ipHash);
        } elseif ($action === 'SUBMIT_STUDENT_ID') {
            abort_unless((bool) $campaign->studentIdEnabled, 422, 'Student ID verification is not enabled.');
            $request->validate([
                'fullName' => ['required', 'string', 'min:2', 'max:120'],
                'privacyConsent' => ['required', 'in:agreed'],
                'studentId' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            ], [
                'privacyConsent.in' => 'Please agree to the Privacy Policy and Terms of Service.',
                'studentId.required' => 'Upload a photo of your student ID.',
            ]);
            $payload = $this->discounts->studentId(
                $installation, $campaign, $email, trim((string) $request->input('fullName')),
                $request->file('studentId'), $ipHash,
            );
        } else {
            abort(422, 'This verification method is not supported.');
        }

        return $this->response($request, $campaign, $payload);
    }

    /** @return array{0: ShopifyInstallation, 1: StudentDiscountCampaign} */
    private function context(Request $request): array
    {
        $shop = $this->auth->normalizeShop((string) $request->input('shop'));
        abort_unless($shop !== null, 422, 'Enter a valid Shopify store domain.');
        /** @var ShopifyInstallation|null $installation */
        $installation = ShopifyInstallation::query()->where('shopDomain', $shop)
            ->where('clientId', config('shopify.client_id'))->where('status', 'INSTALLED')->first();
        abort_unless($installation !== null, 404, 'Student discounts are not configured for this store.');
        $campaign = $this->discounts->campaign($installation);
        $this->assertOrigin($request, $campaign);

        return [$installation, $campaign];
    }

    private function assertOrigin(Request $request, StudentDiscountCampaign $campaign): void
    {
        $origin = $this->origin($request);
        abort_unless($origin && in_array($origin, array_map(fn ($item) => rtrim(strtolower($item), '/'), $this->discounts->list($campaign->allowedOrigins)), true), 403, 'This storefront is not authorized.');
    }

    /** @param array<string, mixed> $data */
    private function response(Request $request, ?StudentDiscountCampaign $campaign, array $data, int $status = 200): JsonResponse
    {
        $origin = $this->origin($request);
        $allowed = ! $campaign || in_array($origin, array_map(fn ($item) => rtrim(strtolower($item), '/'), $this->discounts->list($campaign->allowedOrigins)), true);
        $headers = $origin && $allowed ? [
            'Access-Control-Allow-Origin' => $origin,
            'Access-Control-Allow-Methods' => 'GET, POST, OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type',
            'Access-Control-Max-Age' => '86400',
            'Vary' => 'Origin',
        ] : [];

        return response()->json($data, $status, $headers);
    }

    private function origin(Request $request): string
    {
        $origin = rtrim(strtolower((string) $request->header('Origin')), '/');

        return preg_match('#^https://[a-z0-9][a-z0-9.-]+$#', $origin) ? $origin : '';
    }
}
