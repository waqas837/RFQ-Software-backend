<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\ApiUsageLog;

class ApiUsageLogger
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
        $response = $next($request);

        // Only log if this is an API request with an API key
        if ($request->has('api_user_id') && $request->has('api_key_id')) {
            $this->logUsage($request, $response);
        }

        return $response;
    }

    /**
     * Log the API usage
     */
    private function logUsage(Request $request, $response)
    {
        $startTime = $request->attributes->get('api_start_time');
        $responseTime = $startTime ? round((microtime(true) - $startTime) * 1000) : null;

        ApiUsageLog::create([
            'user_id' => $request->get('api_user_id'),
            'api_key_id' => $request->get('api_key_id'),
            'endpoint' => $request->path(),
            'method' => $request->method(),
            'response_code' => $response->getStatusCode(),
            'response_time' => $responseTime,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'request_data' => $this->sanitizeRequestData($request),
            'response_data' => $this->sanitizeResponseData($response),
            'requested_at' => now()
        ]);
    }

    /**
     * Sanitize request data to remove sensitive information
     */
    private function sanitizeRequestData(Request $request)
    {
        $data = $request->all();
        
        // Remove sensitive fields
        $sensitiveFields = ['password', 'password_confirmation', 'api_key', 'token'];
        foreach ($sensitiveFields as $field) {
            if (isset($data[$field])) {
                $data[$field] = '[REDACTED]';
            }
        }

        return $data;
    }

    /**
     * Sanitize response data to remove sensitive information
     */
    private function sanitizeResponseData($response)
    {
        $content = $response->getContent();
        $data = json_decode($content, true);
        
        if (is_array($data)) {
            // Remove sensitive fields from response
            $sensitiveFields = ['password', 'api_key', 'token', 'secret'];
            foreach ($sensitiveFields as $field) {
                if (isset($data[$field])) {
                    $data[$field] = '[REDACTED]';
                }
            }
        }

        return $data;
    }
}