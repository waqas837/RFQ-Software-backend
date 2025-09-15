<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\Category;
use App\Models\ItemTemplate;
use App\Models\ItemCustomField;
use App\Enums\FieldType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class ItemController extends Controller
{
    /**
     * Get all items with pagination and filtering.
     */
    public function index(Request $request)
    {
        try {
            $items = Item::with(['category', 'creator', 'template', 'customFields'])
                ->when($request->search, function ($query, $search) {
                    $query->where('name', 'like', "%{$search}%")
                          ->orWhere('description', 'like', "%{$search}%")
                          ->orWhere('sku', 'like', "%{$search}%");
                })
                ->when($request->category_id, function ($query, $categoryId) {
                    $query->where('category_id', $categoryId);
                })
                ->when($request->template_id, function ($query, $templateId) {
                    $query->where('template_id', $templateId);
                })
                ->when($request->has('is_active'), function ($query) use ($request) {
                    $query->where('is_active', $request->boolean('is_active'));
                })
                ->when($request->sort_by, function ($query) use ($request) {
                    $direction = $request->sort_direction === 'desc' ? 'desc' : 'asc';
                    $query->orderBy($request->sort_by, $direction);
                }, function ($query) {
                    $query->orderBy('created_at', 'desc');
                })
                ->paginate($request->per_page ?? 15);

            return response()->json([
                'success' => true,
                'data' => $items,
                'message' => 'Items retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve items',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get a specific item.
     */
    public function show($id)
    {
        $item = Item::with(['category', 'creator', 'template', 'customFields'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $item
        ]);
    }

    /**
     * Create a new item.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'sku' => 'required|string|max:255|unique:items,sku',
            'description' => 'nullable|string',
            'category_id' => 'required|exists:categories,id',
            'template_id' => 'nullable|exists:item_templates,id',
            'specifications' => 'nullable|array',
            'unit_of_measure' => 'nullable|string|max:50',
            'is_active' => 'boolean',
            'custom_fields' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $item = Item::create([
                'name' => $request->name,
                'sku' => $request->sku,
                'description' => $request->description,
                'category_id' => $request->category_id,
                'template_id' => $request->template_id,
                'specifications' => $request->specifications,
                'unit_of_measure' => $request->unit_of_measure,
                'is_active' => $request->is_active ?? true,
                'created_by' => $request->user()->id,
                'company_id' => $request->user()->company_id ?? 1,
            ]);

            // Create custom fields if template is provided
            if ($request->template_id) {
                $template = ItemTemplate::find($request->template_id);
                if ($template) {
                    $template->createCustomFieldsForItem($item->id);
                }
            }

            // Handle custom field values
            if ($request->custom_fields) {
                foreach ($request->custom_fields as $fieldName => $fieldValue) {
                    $customField = $item->getCustomField($fieldName);
                    if ($customField) {
                        $customField->update(['field_value' => $fieldValue]);
                    }
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Item created successfully',
                'data' => $item->load(['category', 'creator', 'template', 'customFields'])
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create item',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update an item.
     */
    public function update(Request $request, $id)
    {
        $item = Item::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'sku' => 'sometimes|string|max:255|unique:items,sku,' . $id,
            'description' => 'nullable|string',
            'category_id' => 'sometimes|exists:categories,id',
            'specifications' => 'nullable|array',
            'unit_of_measure' => 'nullable|string|max:50',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $item->update($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Item updated successfully',
            'data' => $item->load(['category', 'creator'])
        ]);
    }

    /**
     * Delete an item.
     */
    public function destroy($id)
    {
        $item = Item::findOrFail($id);
        $item->delete();

        return response()->json([
            'success' => true,
            'message' => 'Item deleted successfully'
        ]);
    }

    /**
     * Get all categories.
     */
    public function categories()
    {
        $categories = Category::active()->with('children')->get();

        return response()->json([
            'success' => true,
            'data' => $categories
        ]);
    }

    /**
     * Get all item templates.
     */
    public function templates()
    {
        $templates = ItemTemplate::active()->get();

        return response()->json([
            'success' => true,
            'data' => $templates
        ]);
    }

    /**
     * Get field types.
     */
    public function fieldTypes()
    {
        $fieldTypes = collect(FieldType::cases())->map(function ($type) {
            return [
                'value' => $type->value,
                'label' => $type->getLabel(),
                'requires_options' => $type->requiresOptions(),
                'supports_validation' => $type->supportsValidation(),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $fieldTypes
        ]);
    }

    /**
     * Bulk import items from CSV/Excel.
     */
    public function bulkImport(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|mimes:csv,xlsx,xls|max:10240', // 10MB max
            'template_id' => 'nullable|exists:item_templates,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $file = $request->file('file');
            $templateId = $request->template_id;
            
            // This would integrate with Laravel Excel package
            // For now, return a placeholder response
            return response()->json([
                'success' => true,
                'message' => 'Bulk import functionality will be implemented with Laravel Excel package',
                'data' => [
                    'imported_count' => 0,
                    'failed_count' => 0,
                    'errors' => []
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to import items',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk export items to CSV/Excel.
     */
    public function bulkExport(Request $request)
    {
        try {
            $items = Item::with(['category', 'template', 'customFields'])
                ->when($request->category_id, function ($query, $categoryId) {
                    $query->where('category_id', $categoryId);
                })
                ->when($request->template_id, function ($query, $templateId) {
                    $query->where('template_id', $templateId);
                })
                ->when($request->has('is_active'), function ($query) use ($request) {
                    $query->where('is_active', $request->boolean('is_active'));
                })
                ->get();

            // This would integrate with Laravel Excel package
            // For now, return a placeholder response
            return response()->json([
                'success' => true,
                'message' => 'Bulk export functionality will be implemented with Laravel Excel package',
                'data' => [
                    'exported_count' => $items->count(),
                    'download_url' => null
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to export items',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
