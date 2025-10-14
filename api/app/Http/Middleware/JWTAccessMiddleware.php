<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;

class JWTAccessMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  Request  $request
     * @param  Closure  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $token = JWTAuth::parseToken();
            $payload = $token->getPayload();

            if ($payload->get('type') !== 'access') {
                return response()->json([
                    'status' => 'error',
                    'error' => 'Invalid token type. Access token required.'
                ], 401);
            }

            $user = JWTAuth::authenticate();

            if (!$user) {
                return response()->json([
                    'status' => 'error',
                    'error' => 'User not found'
                ], 401);
            }
        } catch (TokenExpiredException $e) {
            return response()->json([
                'status' => 'error',
                'error' => 'Token has expired'
            ], 401);
        } catch (TokenInvalidException $e) {
            return response()->json([
                'status' => 'error',
                'error' => 'Token is invalid'
            ], 401);
        } catch (JWTException $e) {
            return response()->json([
                'status' => 'error',
                'error' => 'Token not provided'
            ], 401);
        }

        return $next($request);
    }
}
