<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EmailTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class EmailTemplateController extends Controller
{
    /**
     * Get all email templates with pagination and filtering.
     */
    public function index(Request $request)
    {
        try {
            $query = EmailTemplate::with('creator')
                ->orderBy('created_at', 'desc');

            // Filter by type
            if ($request->has('type') && $request->type) {
                $query->where('type', $request->type);
            }

            // Filter by status
            if ($request->has('is_active')) {
                $query->where('is_active', $request->boolean('is_active'));
            }

            // Search by name or subject
            if ($request->has('search') && $request->search) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('subject', 'like', "%{$search}%");
                });
            }

            $templates = $query->paginate($request->get('per_page', 15));

            return response()->json([
                'success' => true,
                'data' => $templates
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching email templates: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch email templates'
            ], 500);
        }
    }

    /**
     * Get a specific email template.
     */
    public function show($id)
    {
        try {
            $template = EmailTemplate::with('creator')->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $template
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Email template not found'
            ], 404);
        }
    }

    /**
     * Create a new email template.
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'subject' => 'required|string|max:500',
                'content' => 'required|string',
                'type' => 'required|in:rfq_invitation,rfq_published,bid_submitted,bid_confirmation,deadline_reminder,status_change,po_generated,general_notification',
                'placeholders' => 'nullable|array',
                'is_active' => 'boolean',
                'is_default' => 'boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Generate slug from name
            $slug = EmailTemplate::generateSlug($request->name);

            // If this is a default template, unset other defaults of the same type
            if ($request->boolean('is_default')) {
                EmailTemplate::where('type', $request->type)
                    ->where('is_default', true)
                    ->update(['is_default' => false]);
            }

            $template = EmailTemplate::create([
                'name' => $request->name,
                'slug' => $slug,
                'subject' => $request->subject,
                'content' => $request->content,
                'type' => $request->type,
                'placeholders' => $request->placeholders,
                'is_active' => $request->boolean('is_active', true),
                'is_default' => $request->boolean('is_default', false),
                'created_by' => $request->user()->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Email template created successfully',
                'data' => $template->load('creator')
            ], 201);

        } catch (\Exception $e) {
            Log::error('Error creating email template: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to create email template'
            ], 500);
        }
    }

    /**
     * Update an email template.
     */
    public function update(Request $request, $id)
    {
        try {
            $template = EmailTemplate::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|required|string|max:255',
                'subject' => 'sometimes|required|string|max:500',
                'content' => 'sometimes|required|string',
                'type' => 'sometimes|required|in:rfq_invitation,rfq_published,bid_submitted,bid_confirmation,deadline_reminder,status_change,po_generated,general_notification',
                'placeholders' => 'nullable|array',
                'is_active' => 'boolean',
                'is_default' => 'boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // If name changed, generate new slug
            if ($request->has('name') && $request->name !== $template->name) {
                $slug = EmailTemplate::generateSlug($request->name);
                $template->slug = $slug;
            }

            // If this is a default template, unset other defaults of the same type
            if ($request->boolean('is_default') && !$template->is_default) {
                EmailTemplate::where('type', $template->type)
                    ->where('is_default', true)
                    ->update(['is_default' => false]);
            }

            $template->update($request->only([
                'name', 'subject', 'content', 'type', 'placeholders', 'is_active', 'is_default'
            ]));

            return response()->json([
                'success' => true,
                'message' => 'Email template updated successfully',
                'data' => $template->load('creator')
            ]);

        } catch (\Exception $e) {
            Log::error('Error updating email template: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update email template'
            ], 500);
        }
    }

    /**
     * Delete an email template.
     */
    public function destroy($id)
    {
        try {
            $template = EmailTemplate::findOrFail($id);
            $template->delete();

            return response()->json([
                'success' => true,
                'message' => 'Email template deleted successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Error deleting email template: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete email template'
            ], 500);
        }
    }

    /**
     * Create a new version of an email template.
     */
    public function createVersion(Request $request, $id)
    {
        try {
            $template = EmailTemplate::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|string|max:255',
                'subject' => 'sometimes|string|max:500',
                'content' => 'sometimes|string',
                'placeholders' => 'nullable|array',
                'is_active' => 'boolean',
                'is_default' => 'boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $newVersion = $template->createNewVersion($request->all());

            return response()->json([
                'success' => true,
                'message' => 'New version created successfully',
                'data' => $newVersion->load('creator')
            ], 201);

        } catch (\Exception $e) {
            Log::error('Error creating template version: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to create template version'
            ], 500);
        }
    }

    /**
     * Get template by slug.
     */
    public function getBySlug($slug)
    {
        try {
            $template = EmailTemplate::getLatestBySlug($slug);

            if (!$template) {
                return response()->json([
                    'success' => false,
                    'message' => 'Email template not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $template->load('creator')
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching template by slug: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch email template'
            ], 500);
        }
    }

    /**
     * Get default template by type.
     */
    public function getDefaultByType($type)
    {
        try {
            $template = EmailTemplate::getDefaultByType($type);

            if (!$template) {
                return response()->json([
                    'success' => false,
                    'message' => 'Default template not found for this type'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $template->load('creator')
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching default template: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch default template'
            ], 500);
        }
    }

    /**
     * Get available placeholders by template type.
     */
    public function getPlaceholders($type)
    {
        try {
            $placeholders = EmailTemplate::getPlaceholdersByType($type);

            return response()->json([
                'success' => true,
                'data' => $placeholders
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching placeholders: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch placeholders'
            ], 500);
        }
    }

    /**
     * Preview template with sample data.
     */
    public function preview(Request $request, $id)
    {
        try {
            $template = EmailTemplate::findOrFail($id);
            $sampleData = $request->get('sample_data', []);

            $preview = $template->replacePlaceholders($sampleData);

            return response()->json([
                'success' => true,
                'data' => [
                    'template' => $template,
                    'preview' => $preview,
                    'placeholders' => EmailTemplate::getPlaceholdersByType($template->type)
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error previewing template: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to preview template'
            ], 500);
        }
    }

    /**
     * Get template types.
     */
    public function getTypes()
    {
        try {
            $types = [
                'rfq_invitation' => 'RFQ Invitation',
                'rfq_published' => 'RFQ Published',
                'bid_submitted' => 'Bid Submitted',
                'bid_confirmation' => 'Bid Confirmation',
                'deadline_reminder' => 'Deadline Reminder',
                'status_change' => 'Status Change',
                'po_generated' => 'Purchase Order Generated',
                'general_notification' => 'General Notification',
            ];

            return response()->json([
                'success' => true,
                'data' => $types
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching template types: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch template types'
            ], 500);
        }
    }
}
