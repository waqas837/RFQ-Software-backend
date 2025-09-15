<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ItemTemplate;
use App\Enums\FieldType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ItemTemplateController extends Controller
{
    /**
     * Get all item templates.
     */
    public function index(Request $request)
    {
        try {
            $templates = ItemTemplate::when($request->category, function ($query, $category) {
                    $query->where('category', $category);
                })
                ->when($request->search, function ($query, $search) {
                    $query->where('name', 'like', "%{$search}%")
                          ->orWhere('description', 'like', "%{$search}%");
                })
                ->when($request->has('is_active'), function ($query) use ($request) {
                    $query->where('is_active', $request->boolean('is_active'));
                })
                ->orderBy('created_at', 'desc')
                ->paginate($request->per_page ?? 15);

            return response()->json([
                'success' => true,
                'data' => $templates,
                'message' => 'Templates retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve templates',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get a specific template.
     */
    public function show($id)
    {
        $template = ItemTemplate::findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $template
        ]);
    }

    /**
     * Create a new template.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'category' => 'nullable|string|max:100',
            'field_definitions' => 'required|array|min:1',
            'field_definitions.*.name' => 'required|string|max:255',
            'field_definitions.*.type' => 'required|string|in:' . implode(',', FieldType::toArray()),
            'field_definitions.*.required' => 'boolean',
            'field_definitions.*.sort_order' => 'integer|min:0',
            'field_definitions.*.options' => 'nullable|array',
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

        try {
            // If setting as default, unset other defaults
            if ($request->is_default) {
                ItemTemplate::where('is_default', true)->update(['is_default' => false]);
            }

            $template = ItemTemplate::create($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Template created successfully',
                'data' => $template
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create template',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update a template.
     */
    public function update(Request $request, $id)
    {
        $template = ItemTemplate::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'category' => 'nullable|string|max:100',
            'field_definitions' => 'sometimes|array|min:1',
            'field_definitions.*.name' => 'required|string|max:255',
            'field_definitions.*.type' => 'required|string|in:' . implode(',', FieldType::toArray()),
            'field_definitions.*.required' => 'boolean',
            'field_definitions.*.sort_order' => 'integer|min:0',
            'field_definitions.*.options' => 'nullable|array',
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

        try {
            // If setting as default, unset other defaults
            if ($request->is_default && !$template->is_default) {
                ItemTemplate::where('is_default', true)->update(['is_default' => false]);
            }

            $template->update($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Template updated successfully',
                'data' => $template
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update template',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a template.
     */
    public function destroy($id)
    {
        $template = ItemTemplate::findOrFail($id);
        
        // Check if template is being used by items
        if ($template->items()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete template that is being used by items'
            ], 422);
        }

        $template->delete();

        return response()->json([
            'success' => true,
            'message' => 'Template deleted successfully'
        ]);
    }

    /**
     * Get template categories.
     */
    public function categories()
    {
        $categories = ItemTemplate::select('category')
            ->whereNotNull('category')
            ->distinct()
            ->pluck('category')
            ->filter()
            ->values();

        return response()->json([
            'success' => true,
            'data' => $categories
        ]);
    }
}
