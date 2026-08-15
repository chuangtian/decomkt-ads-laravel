<?php

namespace App\Http\Middleware;

use App\Models\Domain\ShopifyInstallation;
use App\Services\Shopify\ShopifyAuthService;
use App\Services\Shopify\ShopifyTokenService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class EnsureShopifySession
{
    public function __construct(
        private readonly ShopifyAuthService $auth,
        private readonly ShopifyTokenService $tokens,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        try {
            $token = $request->bearerToken();
            abort_unless(is_string($token) && $token !== '', 401, 'Shopify session token is required.');
            $payload = $this->auth->verifySessionToken($token);
            $installation = ShopifyInstallation::query()
                ->where('shopDomain', $payload['shop'])
                ->where('clientId', config('shopify.client_id'))
                ->where('status', 'INSTALLED')
                ->first();
            if ($installation && $installation->accessToken && $installation->accessTokenExpiresAt?->isPast() && $installation->refreshToken) {
                $installation = $this->tokens->refresh($installation);
            } elseif (! $installation || ! $installation->accessToken || ($installation->accessTokenExpiresAt && $installation->accessTokenExpiresAt->isPast())) {
                $installation = $this->tokens->fromSession($payload, $token);
            }
            $installation->forceFill(['lastSeenAt' => now()])->save();
            $request->attributes->set('shopifyInstallation', $installation);
            $request->attributes->set('shopifySession', $payload);
        } catch (Throwable $exception) {
            if ($exception instanceof HttpExceptionInterface && $exception->getStatusCode() === 401) {
                throw $exception;
            }

            $session = $request->attributes->get('shopifySession');
            Log::warning('Shopify session validation failed.', [
                'exception' => $exception::class,
                'shop' => is_array($session) ? ($session['shop'] ?? null) : null,
            ]);

            abort(401, 'Shopify session could not be validated. Please reopen the app.');
        }

        return $next($request);
    }
}
