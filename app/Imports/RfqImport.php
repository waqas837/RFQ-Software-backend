<?php

namespace App\Imports;

use App\Models\Rfq;
use App\Models\Category;
use App\Models\Item;
use App\Models\RfqItem;
use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\IOFactory;

class RfqImport
{
    protected $user;
    protected $company;
    protected $importedCount = 0;
    protected $errors = [];
    protected $sequenceNumber = 0;

    public function __construct($user, $company)
    {
        $this->user = $user;
        $this->company = $company;
    }

    public function import($file)
    {
        $extension = $file->getClientOriginalExtension();
        $importedCount = 0;
        $errors = [];

        try {
            if ($extension === 'csv') {
                $data = $this->parseCsv($file);
            } elseif (in_array($extension, ['xlsx', 'xls'])) {
                $data = $this->parseExcel($file);
            } else {
                throw new \Exception('Unsupported file format. Please use CSV, XLSX, or XLS files.');
            }

            foreach ($data as $rowIndex => $row) {
                try {
                    $this->createRfq($row);
                    $importedCount++;
                } catch (\Exception $e) {
                    $errors[] = "Row " . ($rowIndex + 2) . ": " . $e->getMessage();
                }
            }

            return [
                'success' => true,
                'imported_count' => $importedCount,
                'errors' => $errors
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'errors' => $errors
            ];
        }
    }

    private function parseCsv($file)
    {
        $data = [];
        $handle = fopen($file->getPathname(), 'r');
        
        if (!$handle) {
            throw new \Exception('Could not read the CSV file.');
        }

        // Read header row
        $headers = fgetcsv($handle);
        if (!$headers) {
            throw new \Exception('CSV file appears to be empty.');
        }

        // Convert headers to lowercase and replace spaces with underscores
        $headers = array_map(function($header) {
            return str_replace(' ', '_', strtolower(trim($header)));
        }, $headers);

        // Read data rows
        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) === count($headers)) {
                // Trim all values
                $row = array_map('trim', $row);
                $data[] = array_combine($headers, $row);
            }
        }

        fclose($handle);
        return $data;
    }

    private function parseExcel($file)
    {
        try {
            // Load the Excel file using PhpSpreadsheet
            $spreadsheet = IOFactory::load($file->getPathname());
            $worksheet = $spreadsheet->getActiveSheet();
            $data = [];
            
            // Get all rows
            $rows = $worksheet->toArray();
            
            if (empty($rows)) {
                throw new \Exception('Excel file appears to be empty');
            }
            
            // Get headers from first row and normalize them
            $headers = array_map(function($header) {
                return str_replace(' ', '_', strtolower(trim($header)));
            }, $rows[0]);
            
            // Convert remaining rows to associative arrays
            for ($i = 1; $i < count($rows); $i++) {
                $rowData = [];
                for ($j = 0; $j < count($headers); $j++) {
                    $value = isset($rows[$i][$j]) ? $rows[$i][$j] : '';
                    // Convert to string and trim
                    $rowData[$headers[$j]] = trim((string) $value);
                }
                $data[] = $rowData;
            }
            
            return $data;
            
        } catch (\Exception $e) {
            throw new \Exception('Could not read Excel file: ' . $e->getMessage());
        }
    }


    private function createRfq($row)
    {
        // Validate required fields
        $validator = Validator::make($row, [
            'title' => 'required|string|max:255',
            'category' => 'nullable|string|max:255',
            'currency' => 'nullable|string|size:3|in:USD,EUR,GBP,JPY,CAD,AUD,CHF,CNY,INR,AED,SAR,QAR,KWD,BHD,OMR',
            'budget_min' => 'nullable|numeric|min:0',
            'budget_max' => 'nullable|numeric|min:0|gte:budget_min',
            'delivery_date' => 'nullable|date',
            'bid_deadline' => 'nullable|date',
        ], $this->getValidationMessages());

        if ($validator->fails()) {
            throw new \Exception(implode(', ', $validator->errors()->all()));
        }

        // Resolve category - support both name and id based columns
        $categoryId = null;
        // Case 1: category name provided
        if (!empty($row['category'])) {
            $category = Category::firstOrCreate(
                ['name' => $row['category']],
                ['description' => 'Imported category', 'is_active' => true]
            );
            $categoryId = $category->id;
        }
        // Case 2: category id provided (either `category_id` or `category id`)
        if (!$categoryId && !empty($row['category_id'])) {
            $existing = Category::find((int)$row['category_id']);
            if ($existing) {
                $categoryId = $existing->id;
            }
        }
        if (!$categoryId && !empty($row['category id'])) {
            $existing = Category::find((int)$row['category id']);
            if ($existing) {
                $categoryId = $existing->id;
            }
        }
        // Fallback: ensure a default category exists to satisfy NOT NULL constraint
        if (!$categoryId) {
            $defaultCategory = Category::firstOrCreate(
                ['name' => 'Uncategorized'],
                ['description' => 'Default category for imports', 'is_active' => true]
            );
            $categoryId = $defaultCategory->id;
        }

        // Generate unique reference number
        $year = date('Y');
        
        // Initialize sequence number only once for the entire import batch
        if ($this->sequenceNumber === 0) {
            $lastRfq = Rfq::whereYear('created_at', $year)
                ->orderBy('created_at', 'desc')
                ->first();
            
            $this->sequenceNumber = $lastRfq ? 
                (int)substr($lastRfq->reference_number, -4) + 1 : 1;
        }
        
        // Ensure reference number is unique
        do {
            $referenceNumber = 'RFQ-' . $year . '-' . str_pad($this->sequenceNumber, 4, '0', STR_PAD_LEFT);
            $exists = Rfq::where('reference_number', $referenceNumber)->exists();
            if ($exists) {
                $this->sequenceNumber++;
            }
        } while ($exists);
        
        // Increment for next RFQ in the batch
        $this->sequenceNumber++;

        // Create RFQ
        $rfq = Rfq::create([
            'title' => $row['title'],
            'description' => $row['description'] ?? '',
            'category_id' => $categoryId,
            'company_id' => $this->company->id,
            'created_by' => $this->user->id,
            'currency' => $row['currency'] ?? 'USD',
            'budget_min' => $row['budget_min'] ?? 0,
            'budget_max' => $row['budget_max'] ?? 0,
            // Use null-coalescing to avoid undefined index notices when optional columns are missing
            'delivery_date' => $this->parseDate($row['delivery_date'] ?? null),
            'bid_deadline' => $this->parseDate($row['bid_deadline'] ?? null),
            'terms_conditions' => $row['terms_conditions'] ?? '',
            'status' => 'draft',
            'reference_number' => $referenceNumber,
            'attachments' => [],
        ]);

        // Create RFQ items
        $this->createRfqItems($rfq, $row);

        return $rfq;
    }

    private function createRfqItems($rfq, $row)
    {
        $hasItems = false;
        
        // Debug: Log the row data to see what we're receiving
        \Log::info('Creating RFQ items', [
            'row_keys' => array_keys($row),
            'item_name' => $row['item_name'] ?? 'NOT SET',
            'row_data' => $row
        ]);

        // Check for single item format (item_name, item_description, etc.)
        if (isset($row['item_name']) && !empty($row['item_name'])) {
            $hasItems = true;
            
            $itemData = [
                'rfq_id' => $rfq->id,
                'item_name' => $row['item_name'],
                'item_description' => $row['item_description'] ?? '',
                'quantity' => (int)($row['quantity'] ?? 1),
                'unit_of_measure' => $row['unit_of_measure'] ?? 'pcs',
                'specifications' => $row['specifications'] ?? '',
                'notes' => $row['notes'] ?? '',
                'currency' => $rfq->currency,
            ];

            RfqItem::create($itemData);
        }

        // Check for multiple items format (item_1_name, item_2_name, etc.)
        $itemCount = 1;
        while (isset($row["item_{$itemCount}_name"]) && !empty($row["item_{$itemCount}_name"])) {
            $hasItems = true;
            
            $itemData = [
                'rfq_id' => $rfq->id,
                'item_name' => $row["item_{$itemCount}_name"],
                'item_description' => $row["item_{$itemCount}_description"] ?? '',
                'quantity' => (int)($row["item_{$itemCount}_quantity"] ?? 1),
                'unit_of_measure' => $row["item_{$itemCount}_unit"] ?? 'pcs',
                'specifications' => $row["item_{$itemCount}_specifications"] ?? '',
                'notes' => $row["item_{$itemCount}_notes"] ?? '',
                'currency' => $rfq->currency,
            ];

            RfqItem::create($itemData);
            $itemCount++;
        }

        if (!$hasItems) {
            throw new \Exception('At least one item is required for each RFQ');
        }
    }

    private function parseDate($dateString)
    {
        if (empty($dateString)) {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $dateString);
        } catch (\Exception $e) {
            return null;
        }
    }

    private function getValidationMessages()
    {
        return [
            'title.required' => 'RFQ title is required',
            'title.max' => 'RFQ title cannot exceed 255 characters',
            'currency.size' => 'Currency must be exactly 3 characters (e.g., USD, EUR)',
            'currency.in' => 'Invalid currency code. Supported: USD, EUR, GBP, JPY, CAD, AUD, CHF, CNY, INR, AED, SAR, QAR, KWD, BHD, OMR',
            'budget_min.numeric' => 'Minimum budget must be a number',
            'budget_min.min' => 'Minimum budget must be greater than 0',
            'budget_max.numeric' => 'Maximum budget must be a number',
            'budget_max.min' => 'Maximum budget must be greater than 0',
            'budget_max.gte' => 'Maximum budget must be greater than or equal to minimum budget',
            'delivery_date.date' => 'Delivery date must be a valid date (YYYY-MM-DD format)',
            'delivery_date.after' => 'Delivery date must be in the future',
            'bid_deadline.date' => 'Bid deadline must be a valid date (YYYY-MM-DD format)',
            'bid_deadline.before' => 'Bid deadline must be before delivery date',
        ];
    }
}