<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\ApiKey;
use App\Models\ApiUsageLog;
use Illuminate\Support\Facades\RateLimiter;
use Carbon\Carbon;

class ApiKeyAuth
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        // Get API key from header
        $apiKey = $request->header('X-API-Key') ?? $request->header('Authorization');
        
        // Remove 'Bearer ' prefix if present
        if ($apiKey && str_starts_with($apiKey, 'Bearer ')) {
            $apiKey = substr($apiKey, 7);
        }

        if (!$apiKey) {
            return response()->json([
                'success' => false,
                'message' => 'API key is required',
                'error' => 'MISSING_API_KEY'
            ], 401);
        }

        // Find the API key
        $key = ApiKey::where('key', $apiKey)->first();

        if (!$key) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid API key',
                'error' => 'INVALID_API_KEY'
            ], 401);
        }

        // Check if key is active and not expired
        if (!$key->isActive()) {
            return response()->json([
                'success' => false,
                'message' => 'API key is inactive or expired',
                'error' => 'INACTIVE_API_KEY'
            ], 401);
        }

        // Check rate limiting
        $rateLimitKey = 'api_key_' . $key->id;
        $rateLimit = $key->rate_limit ?? 1000; // requests per hour
        
        if (RateLimiter::tooManyAttempts($rateLimitKey, $rateLimit)) {
            $seconds = RateLimiter::availableIn($rateLimitKey);
            return response()->json([
                'success' => false,
                'message' => 'Rate limit exceeded. Try again in ' . $seconds . ' seconds',
                'error' => 'RATE_LIMIT_EXCEEDED',
                'retry_after' => $seconds
            ], 429);
        }

        // Hit the rate limiter
        RateLimiter::hit($rateLimitKey, 3600); // 1 hour window

        // Log the API usage
        $this->logApiUsage($key, $request);

        // Update last used timestamp
        $key->update(['last_used_at' => now()]);

        // Add user and API key to request for use in controllers
        $request->merge([
            'api_user' => $key->user,
            'api_key' => $key
        ]);

        return $next($request);
    }

    /**
     * Log API usage for analytics
     */
    private function logApiUsage(ApiKey $apiKey, Request $request)
    {
        $startTime = microtime(true);
        
        // We'll log the response time after the request is processed
        $request->attributes->set('api_start_time', $startTime);
        $request->attributes->set('api_key_id', $apiKey->id);
        $request->attributes->set('api_user_id', $apiKey->user_id);
    }
}