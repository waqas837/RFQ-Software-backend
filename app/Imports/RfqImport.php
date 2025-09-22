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

        // Convert headers to lowercase for consistency
        $headers = array_map('strtolower', array_map('trim', $headers));

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
            
            // Get headers from first row
            $headers = array_map('strtolower', array_map('trim', $rows[0]));
            
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

        // Find or create category
        $category = null;
        if (!empty($row['category'])) {
            $category = Category::firstOrCreate(
                ['name' => $row['category']],
                ['description' => 'Imported category', 'is_active' => true]
            );
        }

        // Generate unique reference number
        $year = date('Y');
        $lastRfq = Rfq::whereYear('created_at', $year)
            ->orderBy('created_at', 'desc')
            ->first();
        
        $sequenceNumber = $lastRfq ? 
            (int)substr($lastRfq->reference_number, -4) + 1 : 1;
        
        $referenceNumber = 'RFQ-' . $year . '-' . str_pad($sequenceNumber, 4, '0', STR_PAD_LEFT);

        // Create RFQ
        $rfq = Rfq::create([
            'title' => $row['title'],
            'description' => $row['description'] ?? '',
            'category_id' => $category?->id,
            'company_id' => $this->company->id,
            'created_by' => $this->user->id,
            'currency' => $row['currency'] ?? 'USD',
            'budget_min' => $row['budget_min'] ?? 0,
            'budget_max' => $row['budget_max'] ?? 0,
            'delivery_date' => $this->parseDate($row['delivery_date']),
            'bid_deadline' => $this->parseDate($row['bid_deadline']),
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
        $itemCount = 1;
        $hasItems = false;

        while (isset($row["item_{$itemCount}_name"]) && !empty($row["item_{$itemCount}_name"])) {
            $hasItems = true;
            
            $itemData = [
                'rfq_id' => $rfq->id,
                'name' => $row["item_{$itemCount}_name"],
                'description' => $row["item_{$itemCount}_description"] ?? '',
                'quantity' => (int)($row["item_{$itemCount}_quantity"] ?? 1),
                'unit' => $row["item_{$itemCount}_unit"] ?? 'pcs',
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