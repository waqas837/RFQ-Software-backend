<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use OpenApi\Annotations as OA;
use App\Models\Item;
use App\Models\Category;
use App\Models\ItemTemplate;
use App\Models\ItemCustomField;
use App\Models\ItemAttachment;
use App\Enums\FieldType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * @OA\Info(
 *     title="RFQ Software API",
 *     version="1.0.0",
 *     description="API for RFQ (Request for Quotation) Software",
 *     @OA\Contact(
 *         email="support@rfqsoftware.com"
 *     )
 * )
 * @OA\Server(
 *     url="http://localhost:8000/api",
 *     description="Development Server"
 * )
 * @OA\SecurityScheme(
 *     securityScheme="sanctum",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="JWT"
 * )
 */
class ItemController extends Controller
{
    /**
     * @OA\Get(
     *     path="/items",
     *     summary="Get all items",
     *     description="Retrieve a paginated list of items with optional filtering",
     *     tags={"Items"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Search term for name, description, or SKU",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="category_id",
     *         in="query",
     *         description="Filter by category ID",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Number of items per page",
     *         required=false,
     *         @OA\Schema(type="integer", default=15)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Item")),
     *                 @OA\Property(property="current_page", type="integer"),
     *                 @OA\Property(property="per_page", type="integer"),
     *                 @OA\Property(property="total", type="integer")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized"
     *     )
     * )
     */
    public function index(Request $request)
    {
        try {
            $relationships = ['category', 'creator', 'template', 'customFields'];
            
            // Include attachments if requested
            if ($request->has('include') && str_contains($request->include, 'attachments')) {
                $relationships[] = 'attachments';
            }
            
            $items = Item::with($relationships)
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

    /**
     * @OA\Post(
     *     path="/items/{id}/attachments",
     *     summary="Upload file attachment for an item",
     *     description="Upload a file (image or document) to an item",
     *     tags={"Items"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Item ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 @OA\Property(
     *                     property="file",
     *                     type="string",
     *                     format="binary",
     *                     description="File to upload (max 10MB)"
     *                 ),
     *                 @OA\Property(
     *                     property="file_type",
     *                     type="string",
     *                     enum={"image", "document"},
     *                     description="Type of file (optional, auto-detected if not provided)"
     *                 ),
     *                 @OA\Property(
     *                     property="is_primary",
     *                     type="boolean",
     *                     description="Set as primary file (optional)"
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="File uploaded successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="File uploaded successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/ItemAttachment")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Validation error or file limit reached",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Validation failed"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - not authorized to upload files for this item"
     *     )
     * )
     */
    public function uploadAttachment(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|max:10240|mimes:jpg,jpeg,png,gif,pdf,doc,docx,xls,xlsx,txt,csv', // 10MB max with specific file types
            'file_type' => 'nullable|in:image,document',
            'is_primary' => 'nullable|boolean',
        ], [
            'file.required' => 'Please select a file to upload.',
            'file.file' => 'The uploaded file is not valid.',
            'file.max' => 'The file size must not exceed 10MB.',
            'file.mimes' => 'The file must be one of the following types: jpg, jpeg, png, gif, pdf, doc, docx, xls, xlsx, txt, csv.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $item = Item::findOrFail($id);
            $user = $request->user();

            // Check if user has permission to upload files for this item
            if ($item->created_by !== $user->id && !$user->hasRole(['admin', 'buyer'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not authorized to upload files for this item'
                ], 403);
            }
            
            // Check file count limit per item (max 20 files per item)
            $existingFileCount = ItemAttachment::where('item_id', $item->id)->count();
            if ($existingFileCount >= 20) {
                return response()->json([
                    'success' => false,
                    'message' => 'Maximum file limit reached for this item (20 files). Please delete some files before uploading new ones.'
                ], 400);
            }

            $file = $request->file('file');
            
            // Additional security checks
            $originalName = $file->getClientOriginalName();
            $mimeType = $file->getMimeType();
            $fileSize = $file->getSize();
            
            // Check for suspicious file names
            if (preg_match('/[<>:"|?*]/', $originalName)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid file name. Please rename the file and try again.'
                ], 400);
            }
            
            // Check for executable files
            $executableExtensions = ['exe', 'bat', 'cmd', 'com', 'pif', 'scr', 'vbs', 'js', 'jar'];
            $extension = strtolower($file->getClientOriginalExtension());
            if (in_array($extension, $executableExtensions)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Executable files are not allowed for security reasons.'
                ], 400);
            }
            
            // Generate secure filename
            $filename = time() . '_' . Str::random(10) . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs('item-attachments', $filename, 'public');

            // Determine file type
            $fileType = $request->file_type;
            if (!$fileType) {
                $fileType = str_starts_with($file->getMimeType(), 'image/') ? 'image' : 'document';
            }

            // If this is set as primary, unset other primary files of the same type
            if ($request->boolean('is_primary')) {
                ItemAttachment::where('item_id', $item->id)
                    ->where('file_type', $fileType)
                    ->update(['is_primary' => false]);
            }

            $attachment = ItemAttachment::create([
                'item_id' => $item->id,
                'uploaded_by' => $user->id,
                'filename' => $filename,
                'original_name' => $file->getClientOriginalName(),
                'file_path' => $path,
                'mime_type' => $file->getMimeType(),
                'file_size' => $file->getSize(),
                'file_type' => $fileType,
                'is_primary' => $request->boolean('is_primary'),
                'metadata' => [
                    'uploaded_at' => now()->toISOString(),
                    'user_agent' => $request->userAgent(),
                ],
            ]);

            return response()->json([
                'success' => true,
                'message' => 'File uploaded successfully',
                'data' => $attachment->load('uploader')
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload file',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get attachments for an item.
     */
    public function getAttachments($id)
    {
        try {
            $item = Item::findOrFail($id);
            $attachments = $item->attachments()->with('uploader')->get();

            return response()->json([
                'success' => true,
                'data' => $attachments
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch attachments',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete an attachment.
     */
    public function deleteAttachment($itemId, $attachmentId)
    {
        try {
            $item = Item::findOrFail($itemId);
            $attachment = ItemAttachment::where('item_id', $item->id)
                ->where('id', $attachmentId)
                ->firstOrFail();

            $user = request()->user();

            // Check if user has permission to delete this attachment
            if ($attachment->uploaded_by !== $user->id && !$user->hasRole(['admin', 'buyer'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not authorized to delete this attachment'
                ], 403);
            }

            // Delete the physical file
            if (file_exists($attachment->full_path)) {
                unlink($attachment->full_path);
            }

            // Delete the database record
            $attachment->delete();

            return response()->json([
                'success' => true,
                'message' => 'Attachment deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete attachment',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Set primary attachment.
     */
    public function setPrimaryAttachment($itemId, $attachmentId)
    {
        try {
            $item = Item::findOrFail($itemId);
            $attachment = ItemAttachment::where('item_id', $item->id)
                ->where('id', $attachmentId)
                ->firstOrFail();

            $user = request()->user();

            // Check if user has permission to modify this attachment
            if ($attachment->uploaded_by !== $user->id && !$user->hasRole(['admin', 'buyer'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not authorized to modify this attachment'
                ], 403);
            }

            // Unset other primary files of the same type
            ItemAttachment::where('item_id', $item->id)
                ->where('file_type', $attachment->file_type)
                ->where('id', '!=', $attachment->id)
                ->update(['is_primary' => false]);

            // Set this attachment as primary
            $attachment->update(['is_primary' => true]);

            return response()->json([
                'success' => true,
                'message' => 'Primary attachment updated successfully',
                'data' => $attachment
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to set primary attachment',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}

/**
 * @OA\Schema(
 *     schema="Item",
 *     type="object",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="name", type="string", example="Laptop Computer"),
 *     @OA\Property(property="sku", type="string", example="LAP-001"),
 *     @OA\Property(property="description", type="string", example="High-performance laptop"),
 *     @OA\Property(property="category_id", type="integer", example=1),
 *     @OA\Property(property="unit_of_measure", type="string", example="Piece"),
 *     @OA\Property(property="is_active", type="boolean", example=true),
 *     @OA\Property(property="specifications", type="object"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time"),
 *     @OA\Property(property="category", ref="#/components/schemas/Category"),
 *     @OA\Property(property="attachments", type="array", @OA\Items(ref="#/components/schemas/ItemAttachment"))
 * )
 * 
 * @OA\Schema(
 *     schema="ItemAttachment",
 *     type="object",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="item_id", type="integer", example=1),
 *     @OA\Property(property="filename", type="string", example="1640995200_abc123.jpg"),
 *     @OA\Property(property="original_name", type="string", example="product-image.jpg"),
 *     @OA\Property(property="file_path", type="string", example="item-attachments/1640995200_abc123.jpg"),
 *     @OA\Property(property="mime_type", type="string", example="image/jpeg"),
 *     @OA\Property(property="file_size", type="integer", example=1024000),
 *     @OA\Property(property="file_type", type="string", example="image"),
 *     @OA\Property(property="is_primary", type="boolean", example=true),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 * 
 * @OA\Schema(
 *     schema="Category",
 *     type="object",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="name", type="string", example="Electronics"),
 *     @OA\Property(property="description", type="string", example="Electronic devices and components")
 * )
 */
