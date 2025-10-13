<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApiKey;
use App\Models\ApiUsageLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ApiKeyController extends Controller
{
    /**
     * Get all API keys for the authenticated user
     */
    public function index(Request $request)
    {
        $user = $request->user();
        
        if ($user->role !== 'developer') {
            return response()->json([
                'success' => false,
                'message' => 'Access denied. Developer role required.'
            ], 403);
        }

        $apiKeys = ApiKey::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($key) {
                return [
                    'id' => $key->id,
                    'name' => $key->name,
                    'key' => $key->getMaskedKey(),
                    'is_active' => $key->isActive(),
                    'last_used_at' => $key->last_used_at,
                    'expires_at' => $key->expires_at,
                    'rate_limit' => $key->rate_limit,
                    'created_at' => $key->created_at
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $apiKeys
        ]);
    }

    /**
     * Create a new API key
     */
    public function store(Request $request)
    {
        $user = $request->user();
        
        if ($user->role !== 'developer') {
            return response()->json([
                'success' => false,
                'message' => 'Access denied. Developer role required.'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'permissions' => 'nullable|array',
            'rate_limit' => 'nullable|integer|min:1|max:10000',
            'expires_at' => 'nullable|date|after:now'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $apiKey = ApiKey::create([
            'user_id' => $user->id,
            'name' => $request->name,
            'key' => ApiKey::generateKey(),
            'secret' => ApiKey::generateSecret(),
            'permissions' => $request->permissions ?? ['*'],
            'rate_limit' => $request->rate_limit ?? 1000,
            'expires_at' => $request->expires_at
        ]);

        return response()->json([
            'success' => true,
            'message' => 'API key created successfully',
            'data' => [
                'id' => $apiKey->id,
                'name' => $apiKey->name,
                'key' => $apiKey->key, // Only returned once during creation
                'secret' => $apiKey->secret, // Only returned once during creation
                'permissions' => $apiKey->permissions,
                'rate_limit' => $apiKey->rate_limit,
                'expires_at' => $apiKey->expires_at,
                'created_at' => $apiKey->created_at
            ]
        ], 201);
    }

    /**
     * Update an API key
     */
    public function update(Request $request, $id)
    {
        $user = $request->user();
        
        if ($user->role !== 'developer') {
            return response()->json([
                'success' => false,
                'message' => 'Access denied. Developer role required.'
            ], 403);
        }

        $apiKey = ApiKey::where('user_id', $user->id)->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'is_active' => 'sometimes|boolean',
            'permissions' => 'sometimes|array',
            'rate_limit' => 'sometimes|integer|min:1|max:10000',
            'expires_at' => 'sometimes|nullable|date|after:now'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $apiKey->update($request->only(['name', 'is_active', 'permissions', 'rate_limit', 'expires_at']));

        return response()->json([
            'success' => true,
            'message' => 'API key updated successfully',
            'data' => [
                'id' => $apiKey->id,
                'name' => $apiKey->name,
                'key' => $apiKey->getMaskedKey(),
                'is_active' => $apiKey->isActive(),
                'permissions' => $apiKey->permissions,
                'rate_limit' => $apiKey->rate_limit,
                'expires_at' => $apiKey->expires_at,
                'updated_at' => $apiKey->updated_at
            ]
        ]);
    }

    /**
     * Delete an API key
     */
    public function destroy(Request $request, $id)
    {
        $user = $request->user();
        
        if ($user->role !== 'developer') {
            return response()->json([
                'success' => false,
                'message' => 'Access denied. Developer role required.'
            ], 403);
        }

        $apiKey = ApiKey::where('user_id', $user->id)->findOrFail($id);
        $apiKey->delete();

        return response()->json([
            'success' => true,
            'message' => 'API key deleted successfully'
        ]);
    }

    /**
     * Get API usage statistics
     */
    public function usage(Request $request)
    {
        $user = $request->user();
        
        if ($user->role !== 'developer') {
            return response()->json([
                'success' => false,
                'message' => 'Access denied. Developer role required.'
            ], 403);
        }

        $totalRequests = ApiUsageLog::where('user_id', $user->id)->count();
        $thisMonthRequests = ApiUsageLog::where('user_id', $user->id)
            ->whereMonth('requested_at', now()->month)
            ->whereYear('requested_at', now()->year)
            ->count();

        $recentRequests = ApiUsageLog::where('user_id', $user->id)
            ->orderBy('requested_at', 'desc')
            ->limit(10)
            ->get(['endpoint', 'method', 'response_code', 'requested_at']);

        return response()->json([
            'success' => true,
            'data' => [
                'total_requests' => $totalRequests,
                'this_month_requests' => $thisMonthRequests,
                'recent_requests' => $recentRequests
            ]
        ]);
    }
}
