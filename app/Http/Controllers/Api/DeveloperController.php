<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Company;
use App\Models\EmailVerification;
use App\Models\ApiUsageLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use App\Mail\DeveloperVerificationMail;

class DeveloperController extends Controller
{
    /**
     * Register a new developer
     */
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'company_name' => 'required|string|max:255',
            'company_website' => 'nullable|url|max:255',
            'company_description' => 'nullable|string|max:1000',
            'developer_type' => 'required|string|in:individual,company',
            'api_usage_purpose' => 'required|string|max:1000',
            'expected_requests_per_month' => 'required|integer|min:1|max:1000000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Create company first
        $company = Company::create([
            'name' => $request->company_name,
            'website' => $request->company_website,
            'description' => $request->company_description,
            'type' => 'developer',
            'status' => 'pending_verification',
        ]);

        // Create user
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => 'developer',
            'is_verified' => false,
        ]);

        // Attach user to company
        $user->companies()->attach($company->id, [
            'is_primary' => true,
            'role' => 'owner'
        ]);

        // Create email verification token
        $token = Str::random(64);
        EmailVerification::create([
            'user_id' => $user->id,
            'new_email' => null, // For signup verification
            'token' => $token,
            'expires_at' => now()->addHours(24),
            'is_verified' => false,
        ]);

        // Send verification email
        try {
            Mail::to($user->email)->send(new DeveloperVerificationMail($user, $token));
        } catch (\Exception $e) {
            \Log::error('Failed to send developer verification email: ' . $e->getMessage());
        }

        return response()->json([
            'success' => true,
            'message' => 'Developer registration successful! Please check your email to verify your account.',
            'data' => [
                'user_id' => $user->id,
                'email' => $user->email,
                'company_id' => $company->id,
            ]
        ], 201);
    }

    /**
     * Verify developer email
     */
    public function verifyEmail(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required|string|size:64',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid verification token'
            ], 422);
        }

        $verification = EmailVerification::where('token', $request->token)
            ->where('expires_at', '>', now())
            ->where('is_verified', false)
            ->first();

        if (!$verification) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired verification token'
            ], 400);
        }

        // Mark user as verified
        $user = $verification->user;
        $user->update(['is_verified' => true]);

        // Mark verification as completed
        $verification->update(['is_verified' => true]);

        // Update company status
        $company = $user->companies()->first();
        if ($company) {
            $company->update(['status' => 'active']);
        }

        return response()->json([
            'success' => true,
            'message' => 'Email verified successfully! Your developer account is now active.',
            'data' => [
                'user_id' => $user->id,
                'email' => $user->email,
                'verified' => true
            ]
        ]);
    }

    /**
     * Resend verification email
     */
    public function resendVerification(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid email address'
            ], 422);
        }

        $user = User::where('email', $request->email)
            ->where('role', 'developer')
            ->where('is_verified', false)
            ->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Developer account not found or already verified'
            ], 404);
        }

        // Delete existing unverified tokens
        EmailVerification::where('user_id', $user->id)
            ->where('is_verified', false)
            ->delete();

        // Create new verification token
        $token = Str::random(64);
        EmailVerification::create([
            'user_id' => $user->id,
            'new_email' => null,
            'token' => $token,
            'expires_at' => now()->addHours(24),
            'is_verified' => false,
        ]);

        // Send verification email
        try {
            Mail::to($user->email)->send(new DeveloperVerificationMail($user, $token));
        } catch (\Exception $e) {
            \Log::error('Failed to resend developer verification email: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to send verification email. Please try again later.'
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Verification email sent successfully!'
        ]);
    }

    /**
     * Get developer dashboard data
     */
    public function dashboard(Request $request)
    {
        $user = $request->user();
        
        if ($user->role !== 'developer') {
            return response()->json([
                'success' => false,
                'message' => 'Access denied. Developer role required.'
            ], 403);
        }

        $company = $user->companies()->first();
        
        // Get real API usage statistics
        $totalRequests = ApiUsageLog::where('user_id', $user->id)->count();
        $thisMonthRequests = ApiUsageLog::where('user_id', $user->id)
            ->whereMonth('requested_at', now()->month)
            ->whereYear('requested_at', now()->year)
            ->count();
        
        // Get API key information for rate limits
        $apiKeys = \App\Models\ApiKey::where('user_id', $user->id)
            ->where('is_active', true)
            ->get();
        
        $maxRateLimit = $apiKeys->max('rate_limit') ?? 1000;
        $activeKeysCount = $apiKeys->count();
        
        return response()->json([
            'success' => true,
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'is_verified' => $user->is_verified,
                    'created_at' => $user->created_at,
                ],
                'company' => $company ? [
                    'id' => $company->id,
                    'name' => $company->name,
                    'website' => $company->website,
                    'description' => $company->description,
                    'status' => $company->status,
                ] : null,
                'api_stats' => [
                    'total_requests' => $totalRequests,
                    'requests_this_month' => $thisMonthRequests,
                    'rate_limit_remaining' => $maxRateLimit,
                    'active_api_keys' => $activeKeysCount,
                    'max_rate_limit' => $maxRateLimit,
                ]
            ]
        ]);
    }
}
