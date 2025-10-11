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
use Illuminate\Support\Facades\Auth;

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

            // Transform items to include custom fields
            $transformedItems = $items->getCollection()->map(function ($item) {
                $itemData = $item->toArray();
                $itemData['custom_fields'] = $item->getCustomFieldsArray();
                return $itemData;
            });

            $items->setCollection($transformedItems);

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
        
        // Transform item to include custom fields
        $itemData = $item->toArray();
        $itemData['custom_fields'] = $item->getCustomFieldsArray();

        return response()->json([
            'success' => true,
            'data' => $itemData
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
            $importedCount = 0;
            $failedCount = 0;
            $errors = [];

            // Read the file using PhpSpreadsheet
            $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($file->getPathname());
            $spreadsheet = $reader->load($file->getPathname());
            $worksheet = $spreadsheet->getActiveSheet();
            $rows = $worksheet->toArray();

            // Skip header row
            $headerRow = array_shift($rows);
            
            // Expected columns: name, description, sku, category_id, unit_of_measure, specifications, is_active
            $expectedColumns = ['name', 'description', 'sku', 'category_id', 'unit_of_measure', 'specifications', 'is_active'];
            
            foreach ($rows as $rowIndex => $row) {
                try {
                    // Skip empty rows
                    if (empty(array_filter($row))) {
                        continue;
                    }

                    $user = Auth::user();
                    /** @var \App\Models\User $user */
                    $userCompany = $user->companies()->first();
                    
                    if (!$userCompany) {
                        throw new \Exception('User must be associated with a company');
                    }

                    $itemData = [
                        'name' => $row[0] ?? '',
                        'description' => $row[1] ?? '',
                        'sku' => $row[2] ?? '',
                        'category_id' => $row[3] ?? null,
                        'unit_of_measure' => $row[4] ?? 'pcs',
                        'specifications' => $row[5] ? json_decode($row[5], true) : [],
                        'is_active' => filter_var($row[6] ?? true, FILTER_VALIDATE_BOOLEAN),
                        'template_id' => $templateId,
                        'created_by' => $user->id,
                        'company_id' => $userCompany->id,
                    ];

                    // Validate required fields
                    if (empty($itemData['name'])) {
                        throw new \Exception('Name is required');
                    }

                    // Check if SKU already exists
                    if (!empty($itemData['sku'])) {
                        $existingItem = Item::where('sku', $itemData['sku'])->first();
                        if ($existingItem) {
                            throw new \Exception('SKU already exists: ' . $itemData['sku']);
                        }
                    }

                    // Create the item
                    Item::create($itemData);
                    $importedCount++;

                } catch (\Exception $e) {
                    $failedCount++;
                    $errors[] = "Row " . ($rowIndex + 2) . ": " . $e->getMessage();
                }
            }

            return response()->json([
                'success' => true,
                'message' => "Import completed. {$importedCount} items imported, {$failedCount} failed.",
                'data' => [
                    'imported_count' => $importedCount,
                    'failed_count' => $failedCount,
                    'errors' => $errors
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

            // Create a new spreadsheet
            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $worksheet = $spreadsheet->getActiveSheet();
            
            // Set headers
            $headers = [
                'Name', 'Description', 'SKU', 'Category ID', 'Unit of Measure', 
                'Specifications', 'Is Active', 'Created At', 'Updated At'
            ];
            
            $col = 1;
            foreach ($headers as $header) {
                $worksheet->setCellValue([$col, 1], $header);
                $col++;
            }
            
            // Style the header row
            $worksheet->getStyle('A1:I1')->getFont()->setBold(true);
            $worksheet->getStyle('A1:I1')->getFill()
                ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setRGB('E5E7EB');
            
            // Add data rows
            $row = 2;
            foreach ($items as $item) {
                $worksheet->setCellValue([1, $row], $item->name);
                $worksheet->setCellValue([2, $row], $item->description);
                $worksheet->setCellValue([3, $row], $item->sku);
                $worksheet->setCellValue([4, $row], $item->category_id);
                $worksheet->setCellValue([5, $row], $item->unit_of_measure);
                $worksheet->setCellValue([6, $row], json_encode($item->specifications));
                $worksheet->setCellValue([7, $row], $item->is_active ? 'Yes' : 'No');
                $worksheet->setCellValue([8, $row], $item->created_at->format('Y-m-d H:i:s'));
                $worksheet->setCellValue([9, $row], $item->updated_at->format('Y-m-d H:i:s'));
                $row++;
            }
            
            // Auto-size columns
            foreach (range('A', 'I') as $column) {
                $worksheet->getColumnDimension($column)->setAutoSize(true);
            }
            
            // Generate filename
            $filename = 'items_export_' . date('Y-m-d_H-i-s') . '.xlsx';
            $filepath = storage_path('app/exports/' . $filename);
            
            // Ensure directory exists
            if (!file_exists(dirname($filepath))) {
                mkdir(dirname($filepath), 0755, true);
            }
            
            // Save the file
            $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
            $writer->save($filepath);
            
            return response()->json([
                'success' => true,
                'message' => 'Items exported successfully',
                'data' => [
                    'exported_count' => $items->count(),
                    'download_url' => url('api/downloads/' . $filename),
                    'filename' => $filename
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
