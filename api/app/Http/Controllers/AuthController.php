<?php

namespace App\Http\Controllers;

use App\Http\Requests\SupplierFormRequest;
use App\Http\Resources\SupplierResource;
use App\Models\Supplier;
use App\Services\JWTService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    public function __construct(
        protected JWTService $jwtService
    ) {
        // 
    }

    /**
     * Determine if the request is from a mobile app
     */
    private function isCookieMode(Request $request): bool
    {
        $cookieMode = config('auth.cookie_mode');
        if ($cookieMode !== null) {
            return $cookieMode;
        }

        $userAgent =  $request->userAgent();
        $isWeb = preg_match('/Chrome|Firefox|Safari|Edge/', $userAgent);
        return !$isWeb;
    }

    /**
     * Create an HTTP-only cookie for the refresh token
     */
    private function createRefreshTokenCookie(string $refreshToken, int $expiresIn)
    {
        return cookie(
            'refresh_token',
            $refreshToken,
            $expiresIn / 60, // convert seconds to minutes
            '/',
            null,
            true,  // secure (HTTPS only)
            true,  // httpOnly
            false,
            'strict' // sameSite
        );
    }

    public function register(SupplierFormRequest $request)
    {
        $v = $request->validated();
        $supplier = Supplier::create($v);

        return $this->createdResponse(new SupplierResource($supplier), "Registered successfully");
    }

    public function login(Request $request)
    {
        $v = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string|min:6',
        ]);

        if (!Auth::attempt($v)) {
            return $this->unauthorizedResponse();
        }

        $supplier = Auth::user();
        $tokenData = $this->jwtService->generateTokenPair($supplier);

        $data = [
            'user' => new SupplierResource($supplier),
            'access_token' => $tokenData['access_token'],
            'token_type' => $tokenData['token_type'],
            'expires_in' => $tokenData['expires_in'],
        ];

        $response = $this->successWithData($data, "Login successful");

        if (!$this->isCookieMode($request)) {
            return $response
                ->header('X-Refresh-Token', $tokenData['refresh_token'])
                ->header('X-Refresh-Expires-In', $tokenData['refresh_expires_in']);
        }

        // For web: use HTTP-only cookie
        $refreshCookie = $this->createRefreshTokenCookie($tokenData['refresh_token'], $tokenData['refresh_expires_in']);

        return $response->cookie($refreshCookie);
    }

    public function refresh(Request $request)
    {
        $accessToken = $request->bearerToken();
        $isCookieMode = $this->isCookieMode($request);

        // Get refresh token from appropriate source
        $refreshToken = !$isCookieMode
            ? $request->header('X-Refresh-Token')
            : $request->cookie('refresh_token');

        if (!$refreshToken) {
            return $this->errorResponse('Refresh token missing', 400);
        }

        $result = $this->jwtService->refreshTokens($accessToken, $refreshToken);
        if (!$result) {
            return $this->unauthorizedResponse('Invalid or expired refresh token');
        }

        JWTAuth::setToken($result['access_token']);
        $supplier = JWTAuth::authenticate();

        $data = [
            'user' => new SupplierResource($supplier),
            'access_token' => $result['access_token'],
            'token_type' => $result['token_type'],
            'expires_in' => $result['expires_in'],
        ];

        $response = $this->successWithData($data, "Token refreshed successfully");

        if (!$isCookieMode) {
            return $response
                ->header('X-Refresh-Token', $result['refresh_token'])
                ->header('X-Refresh-Expires-In', $result['refresh_expires_in']);
        }

        // For web: set new refresh token cookie
        $refreshCookie = $this->createRefreshTokenCookie($result['refresh_token'], $result['refresh_expires_in']);

        return $response->cookie($refreshCookie);
    }

    public function logout(Request $request)
    {
        $supplier = Auth::user();
        $this->jwtService->revokeTokens($supplier);

        $response = $this->successWithMessage('Successfully logged out');
        if (!$this->isCookieMode($request)) {
            return $response;
        }

        // For web: clear the refresh token cookie
        $clearCookie = cookie()->forget('refresh_token');
        return $response->cookie($clearCookie);
    }

    public function me()
    {
        return $this->successWithData(new SupplierResource(Auth::user()));
    }
}
