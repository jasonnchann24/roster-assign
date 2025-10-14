<?php

namespace App\Services;

use App\Models\Supplier;
use Illuminate\Support\Facades\Cache;
use Throwable;
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
            'expires_in' => 1800
        ];
    }

    public function refreshTokens(string $accessToken, string $refreshToken): ?array
    {
        try {
            JWTAuth::setToken($accessToken);
            $accessPayload = JWTAuth::getPayload();

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
