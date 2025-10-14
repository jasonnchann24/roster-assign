<?php

namespace App\Services;

use App\Models\Supplier;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;
use Tymon\JWTAuth\Claims\Collection;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Facades\JWTAuth;

class JWTService
{
    private function buildCacheKey(int $userId): string
    {
        return "refresh_token_{$userId}";
    }

    public function generateTokenPair(Supplier $supplier): array
    {
        $accessToken = JWTAuth::customClaims([
            'type' => 'access',
            'exp' => now()->addMinutes(30)->timestamp
        ])->fromUser($supplier);

        $refreshToken = JWTAuth::customClaims([
            'type' => 'refresh',
            'jti' => uniqid(),
            'exp' => now()->addDays(7)->timestamp
        ])->fromUser($supplier);
        $hashedRefresh = hash('sha256', $refreshToken);

        $cacheKey = $this->buildCacheKey($supplier->id);

        Cache::tags(['refresh_tokens'])->put(
            $cacheKey,
            $hashedRefresh,
            now()->addDays(7)
        );

        return [
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'token_type' => 'Bearer',
            'expires_in' => 1800, // 30 minutes in seconds
            'refresh_expires_in' => 604800 // 7 days in seconds
        ];
    }

    public function refreshTokens(string $accessToken, string $refreshToken): ?array
    {
        try {
            JWTAuth::setToken($accessToken);

            try {
                $accessPayload = JWTAuth::getPayload();
            } catch (TokenExpiredException $e) {
                // For refresh flow, we need to get payload from expired token
                // Use checkOrFail(false) to ignore expiration
                $accessPayload = JWTAuth::manager()->getPayloadFactory()->buildClaimsCollection()->toPlainArray();
                $accessPayload = Collection::make($accessPayload);
            }

            if ($accessPayload->get('type') !== 'access') {
                return null;
            }

            $userId = $accessPayload->get('sub');
            $supplier = Supplier::find($userId);

            if (!$supplier) {

                return null;
            }

            JWTAuth::setToken($refreshToken);
            $refreshPayload = JWTAuth::getPayload();

            if ($refreshPayload->get('type') !== 'refresh') {
                return null;
            }

            if ($refreshPayload->get('sub') != $userId) {
                return null;
            }

            $exp = $refreshPayload->get('exp');
            if ($exp && $exp < now()->timestamp) {
                return null;
            }

            $hashedToken = hash('sha256', $refreshToken);
            $cacheKey = $this->buildCacheKey($supplier->id);

            $storedToken = Cache::tags(['refresh_tokens'])->get($cacheKey);

            if ($storedToken !== $hashedToken) {
                return null;
            }

            Cache::tags(['refresh_tokens'])->forget($cacheKey);

            return $this->generateTokenPair($supplier);
        } catch (Throwable $e) {
            Log::error('Error refreshing tokens: ' . $e->getMessage());
            return null;
        }
    }

    public function revokeTokens(Supplier $supplier): void
    {
        Cache::tags(['refresh_tokens'])->forget($this->buildCacheKey($supplier->id));
    }

    public function revokeAllRefreshTokens(): void
    {
        Cache::tags(['refresh_tokens'])->flush();
    }
}
