<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Rfq;
use App\Models\Bid;
use App\Models\Company;
use App\Models\Item;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ReportsController extends Controller
{
    /**
     * Get dashboard overview statistics.
     */
    public function dashboard(Request $request)
    {
        try {
            $user = $request->user();
            $period = $request->get('period', 30); // days
            $startDate = Carbon::now()->subDays($period);

            $stats = [];

            if ($user->isAdmin()) {
                // Admin dashboard stats
                $stats = [
                    'total_rfqs' => Rfq::count(),
                    'active_rfqs' => Rfq::whereIn('status', ['published', 'bidding_open'])->count(),
                    'total_bids' => Bid::count(),
                    'total_suppliers' => Company::where('type', 'supplier')->count(),
                    'total_buyers' => Company::where('type', 'buyer')->count(),
                    'recent_rfqs' => Rfq::with(['creator', 'bids'])->latest()->take(5)->get(),
                    'recent_bids' => Bid::with(['rfq', 'supplier'])->latest()->take(5)->get(),
                    'monthly_trends' => $this->getMonthlyTrends($startDate),
                    'rfq_status_distribution' => $this->getRfqStatusDistribution(),
                    'category_distribution' => $this->getCategoryDistribution(),
                    'top_suppliers' => $this->getTopSuppliers($startDate),
                ];
            } elseif ($user->isBuyer()) {
                // Buyer dashboard stats
                $stats = [
                    'my_rfqs' => Rfq::where('created_by', $user->id)->count(),
                    'active_rfqs' => Rfq::where('created_by', $user->id)
                        ->whereIn('status', ['published', 'bidding_open'])->count(),
                    'total_bids_received' => Bid::whereHas('rfq', function ($q) use ($user) {
                        $q->where('created_by', $user->id);
                    })->count(),
                    'awarded_rfqs' => Rfq::where('created_by', $user->id)
                        ->where('status', 'awarded')->count(),
                    'recent_rfqs' => Rfq::where('created_by', $user->id)->with('bids')->latest()->take(5)->get(),
                    'recent_bids' => Bid::whereHas('rfq', function ($q) use ($user) {
                        $q->where('created_by', $user->id);
                    })->with(['rfq', 'supplier'])->latest()->take(5)->get(),
                    'monthly_trends' => $this->getMonthlyTrends($startDate, $user->id),
                    'rfq_status_distribution' => $this->getRfqStatusDistribution($user->id),
                    'category_distribution' => $this->getCategoryDistribution($user->id),
                ];
            } elseif ($user->isSupplier()) {
                // Supplier dashboard stats
                $userCompany = $user->companies->first();
                $stats = [
                    'available_rfqs' => Rfq::whereHas('suppliers', function ($q) use ($userCompany) {
                        $q->where('supplier_company_id', $userCompany->id);
                    })->whereIn('status', ['published', 'bidding_open'])->count(),
                    'my_bids' => Bid::where('supplier_id', $user->id)->count(),
                    'awarded_bids' => Bid::where('supplier_id', $user->id)
                        ->where('status', 'awarded')->count(),
                    'success_rate' => $this->calculateSuccessRate($user->id),
                    'recent_rfqs' => Rfq::whereHas('suppliers', function ($q) use ($userCompany) {
                        $q->where('supplier_company_id', $userCompany->id);
                    })->with('bids')->latest()->take(5)->get(),
                    'recent_bids' => Bid::where('supplier_id', $user->id)
                        ->with(['rfq'])->latest()->take(5)->get(),
                    'monthly_trends' => $this->getSupplierMonthlyTrends($startDate, $user->id),
                    'rfq_status_distribution' => $this->getSupplierRfqStatusDistribution($user->id),
                    'category_distribution' => $this->getSupplierCategoryDistribution($user->id),
                ];
            }

            return response()->json([
                'success' => true,
                'data' => $stats,
                'message' => 'Dashboard statistics retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve dashboard statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    /**
     * Get RFQ analysis data.
     */
    public function rfqAnalysis(Request $request)
    {
        try {
            $user = $request->user();
            $period = $request->get('period', 30);
            $startDate = Carbon::now()->subDays($period);

            $analysis = [];

            if ($user->isAdmin()) {
                $analysis = [
                    'rfq_status_distribution' => $this->getRfqStatusDistribution(),
                    'monthly_rfq_trends' => $this->getMonthlyTrends($startDate),
                    'category_distribution' => $this->getCategoryDistribution(),
                    'average_bids_per_rfq' => $this->getAverageBidsPerRfq(),
                    'rfq_completion_time' => $this->getRfqCompletionTime(),
                ];
            } elseif ($user->isBuyer()) {
                $analysis = [
                    'my_rfq_status_distribution' => $this->getRfqStatusDistribution($user->id),
                    'my_monthly_trends' => $this->getMonthlyTrends($startDate, $user->id),
                    'my_category_distribution' => $this->getCategoryDistribution($user->id),
                    'my_average_bids_per_rfq' => $this->getAverageBidsPerRfq($user->id),
                ];
            }

            return response()->json([
                'success' => true,
                'data' => $analysis,
                'message' => 'RFQ analysis data retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve RFQ analysis data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get supplier performance data.
     */
    public function supplierPerformance(Request $request)
    {
        try {
            $user = $request->user();
            $period = $request->get('period', 30);
            $startDate = Carbon::now()->subDays($period);

            $performance = [];

            if ($user->isAdmin()) {
                $performance = [
                    'top_suppliers' => $this->getTopSuppliers($startDate),
                    'supplier_win_rates' => $this->getSupplierWinRates($startDate),
                    'supplier_response_times' => $this->getSupplierResponseTimes($startDate),
                    'supplier_quality_ratings' => $this->getSupplierQualityRatings($startDate),
                ];
            } elseif ($user->isSupplier()) {
                $userCompany = $user->companies->first();
                $performance = [
                    'my_performance' => $this->getMySupplierPerformance($userCompany->id, $startDate),
                    'my_win_rate' => $this->getMyWinRate($userCompany->id, $startDate),
                    'my_response_time' => $this->getMyResponseTime($userCompany->id, $startDate),
                ];
            }

            return response()->json([
                'success' => true,
                'data' => $performance,
                'message' => 'Supplier performance data retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve supplier performance data',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    /**
     * Get monthly trends data.
     */
    private function getMonthlyTrends($startDate, $userId = null)
    {
        $query = Rfq::where('created_at', '>=', $startDate);
            
        if ($userId) {
            $query->where('created_by', $userId);
        }
        
        $rfqs = $query->get()->groupBy(function ($item) {
            return $item->created_at->format('Y-m');
        })->map(function ($group) {
            return $group->count();
        });
        
        $bids = Bid::where('created_at', '>=', $startDate)
            ->when($userId, function ($q) use ($userId) {
                return $q->whereHas('rfq', function ($subQ) use ($userId) {
                    $subQ->where('created_by', $userId);
                });
            })
            ->get()->groupBy(function ($item) {
                return $item->created_at->format('Y-m');
            })->map(function ($group) {
                return $group->count();
            });
        
        $awards = Bid::where('created_at', '>=', $startDate)
            ->where('status', 'awarded')
            ->when($userId, function ($q) use ($userId) {
                return $q->whereHas('rfq', function ($subQ) use ($userId) {
                    $subQ->where('created_by', $userId);
                });
            })
            ->get()->groupBy(function ($item) {
                return $item->created_at->format('Y-m');
            })->map(function ($group) {
                return $group->count();
            });
        
        $allMonths = collect($rfqs->keys())
            ->merge($bids->keys())
            ->merge($awards->keys())
            ->unique()
            ->sort()
            ->values();
        
        return $allMonths->map(function ($month) use ($rfqs, $bids, $awards) {
            return [
                'month' => $month,
                'rfqs' => $rfqs->get($month, 0),
                'bids' => $bids->get($month, 0),
                'awards' => $awards->get($month, 0),
            ];
        });
    }

    /**
     * Get RFQ status distribution.
     */
    private function getRfqStatusDistribution($userId = null)
    {
        $query = Rfq::selectRaw('status, COUNT(*) as count')
            ->groupBy('status');
            
        if ($userId) {
            $query->where('created_by', $userId);
        }
        
        return $query->get();
    }

    /**
     * Get category distribution.
     */
    private function getCategoryDistribution($userId = null)
    {
        $query = DB::table('rfqs')
            ->join('rfq_items', 'rfqs.id', '=', 'rfq_items.rfq_id')
            ->join('items', 'rfq_items.item_id', '=', 'items.id')
            ->join('categories', 'items.category_id', '=', 'categories.id')
            ->selectRaw('categories.name, COUNT(*) as count')
            ->groupBy('categories.id', 'categories.name');
            
        if ($userId) {
            $query->where('rfqs.created_by', $userId);
        }
        
        return $query->get();
    }

    /**
     * Get average bids per RFQ.
     */
    private function getAverageBidsPerRfq($userId = null)
    {
        $subQuery = DB::table('rfqs')
            ->leftJoin('bids', 'rfqs.id', '=', 'bids.rfq_id')
            ->selectRaw('rfqs.id, COUNT(bids.id) as bid_count')
            ->groupBy('rfqs.id');
            
        if ($userId) {
            $subQuery->where('rfqs.created_by', $userId);
        }
        
        $result = DB::table(DB::raw("({$subQuery->toSql()}) as rfq_bid_counts"))
            ->mergeBindings($subQuery)
            ->selectRaw('AVG(bid_count) as average_bids')
            ->first();
            
        return round($result->average_bids ?? 0, 2);
    }

    /**
     * Get RFQ completion time.
     */
    private function getRfqCompletionTime()
    {
        return Rfq::selectRaw('AVG(DATEDIFF(updated_at, created_at)) as avg_days')
            ->where('status', 'awarded')
            ->first();
    }

    /**
     * Get top suppliers.
     */
    private function getTopSuppliers($startDate)
    {
        return DB::table('companies')
            ->join('bids', 'companies.id', '=', 'bids.supplier_company_id')
            ->join('rfqs', 'bids.rfq_id', '=', 'rfqs.id')
            ->selectRaw('companies.name, COUNT(bids.id) as total_bids, 
                        SUM(CASE WHEN bids.status = "awarded" THEN 1 ELSE 0 END) as awarded_bids,
                        AVG(bids.total_amount) as avg_bid_amount')
            ->where('companies.type', 'supplier')
            ->where('bids.created_at', '>=', $startDate)
            ->groupBy('companies.id', 'companies.name')
            ->orderBy('total_bids', 'desc')
            ->limit(10)
            ->get();
    }

    /**
     * Get supplier win rates.
     */
    private function getSupplierWinRates($startDate)
    {
        return DB::table('companies')
            ->join('bids', 'companies.id', '=', 'bids.supplier_company_id')
            ->selectRaw('companies.name, 
                        COUNT(bids.id) as total_bids,
                        SUM(CASE WHEN bids.status = "awarded" THEN 1 ELSE 0 END) as awarded_bids,
                        ROUND((SUM(CASE WHEN bids.status = "awarded" THEN 1 ELSE 0 END) / COUNT(bids.id)) * 100, 2) as win_rate')
            ->where('companies.type', 'supplier')
            ->where('bids.created_at', '>=', $startDate)
            ->groupBy('companies.id', 'companies.name')
            ->having('total_bids', '>', 0)
            ->orderBy('win_rate', 'desc')
            ->get();
    }

    /**
     * Get supplier response times.
     */
    private function getSupplierResponseTimes($startDate)
    {
        return DB::table('companies')
            ->join('bids', 'companies.id', '=', 'bids.supplier_company_id')
            ->join('rfqs', 'bids.rfq_id', '=', 'rfqs.id')
            ->selectRaw('companies.name, AVG(DATEDIFF(bids.created_at, rfqs.created_at)) as avg_response_days')
            ->where('companies.type', 'supplier')
            ->where('bids.created_at', '>=', $startDate)
            ->groupBy('companies.id', 'companies.name')
            ->orderBy('avg_response_days', 'asc')
            ->get();
    }

    /**
     * Get supplier quality ratings.
     */
    private function getSupplierQualityRatings($startDate)
    {
        return DB::table('companies')
            ->join('bids', 'companies.id', '=', 'bids.supplier_company_id')
            ->selectRaw('companies.name, AVG(bids.quality_score) as avg_quality_score')
            ->where('companies.type', 'supplier')
            ->where('bids.created_at', '>=', $startDate)
            ->whereNotNull('bids.quality_score')
            ->groupBy('companies.id', 'companies.name')
            ->orderBy('avg_quality_score', 'desc')
            ->get();
    }

    /**
     * Get my supplier performance.
     */
    private function getMySupplierPerformance($companyId, $startDate)
    {
        return DB::table('bids')
            ->join('rfqs', 'bids.rfq_id', '=', 'rfqs.id')
            ->selectRaw('COUNT(bids.id) as total_bids,
                        SUM(CASE WHEN bids.status = "awarded" THEN 1 ELSE 0 END) as awarded_bids,
                        AVG(bids.total_amount) as avg_bid_amount,
                        AVG(DATEDIFF(bids.created_at, rfqs.created_at)) as avg_response_days')
            ->where('bids.supplier_company_id', $companyId)
            ->where('bids.created_at', '>=', $startDate)
            ->first();
    }

    /**
     * Get my win rate.
     */
    private function getMyWinRate($companyId, $startDate)
    {
        $result = DB::table('bids')
            ->selectRaw('COUNT(*) as total_bids,
                        SUM(CASE WHEN status = "awarded" THEN 1 ELSE 0 END) as awarded_bids')
            ->where('supplier_company_id', $companyId)
            ->where('created_at', '>=', $startDate)
            ->first();
            
        if ($result->total_bids > 0) {
            return round(($result->awarded_bids / $result->total_bids) * 100, 2);
        }
        
        return 0;
    }

    /**
     * Get my response time.
     */
    private function getMyResponseTime($companyId, $startDate)
    {
        $result = DB::table('bids')
            ->join('rfqs', 'bids.rfq_id', '=', 'rfqs.id')
            ->selectRaw('AVG(DATEDIFF(bids.created_at, rfqs.created_at)) as avg_response_days')
            ->where('bids.supplier_company_id', $companyId)
            ->where('bids.created_at', '>=', $startDate)
            ->first();
            
        return round($result->avg_response_days ?? 0, 1);
    }





    /**
     * Calculate supplier success rate.
     */
    private function calculateSuccessRate($supplierId)
    {
        $totalBids = Bid::where('supplier_id', $supplierId)->count();
        $awardedBids = Bid::where('supplier_id', $supplierId)
            ->where('status', 'awarded')->count();

        return $totalBids > 0 ? round(($awardedBids / $totalBids) * 100, 2) : 0;
    }

    /**
     * Get monthly savings data.
     */
    private function getMonthlySavings($startDate)
    {
        return Bid::where('status', 'awarded')
            ->where('created_at', '>=', $startDate)
            ->get()
            ->groupBy(function ($item) {
                return $item->created_at->format('Y-m');
            })
            ->map(function ($group) {
                return [
                    'month' => $group->first()->created_at->format('Y-m'),
                    'savings' => $group->sum('total_amount'),
                ];
            })
            ->values()
            ->sortBy('month');
    }

    /**
     * Export reports to PDF or Excel.
     */
    public function export(Request $request)
    {
        try {
            $user = $request->user();
            
            // Check if user is authenticated
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication required',
                    'error' => 'User not authenticated'
                ], 401);
            }
            
            $type = $request->get('type', 'overview');
            $period = $request->get('period', 30);
            $format = $request->get('format', 'pdf');
            
            $startDate = Carbon::now()->subDays($period);
            $data = [];

            // Get data based on report type
            switch ($type) {
                case 'overview':
                    $data = $this->getDashboardData($user, $startDate);
                    break;
                case 'rfq':
                    $data = $this->getRfqAnalysisData($user, $startDate);
                    break;
                case 'supplier':
                    $data = $this->getSupplierPerformanceData($user, $startDate);
                    break;
                default:
                    $data = $this->getDashboardData($user, $startDate);
            }

            if ($format === 'pdf') {
                return $this->generatePdfReport($data, $type, $user);
            } else {
                return $this->generateExcelReport($data, $type, $user);
            }

        } catch (\Exception $e) {
            \Log::error('Export error: ' . $e->getMessage());
            \Log::error('Export stack trace: ' . $e->getTraceAsString());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to export report: ' . $e->getMessage(),
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate PDF report.
     */
    private function generatePdfReport($data, $type, $user)
    {
        try {
            $filename = "{$type}_report_" . now()->format('Y-m-d_H-i-s') . ".pdf";
            
            // Create HTML content for PDF
            $html = $this->generateReportHtml($data, $type, $user);
            
            // For now, return HTML content that can be printed to PDF
            // In production, use DomPDF or Snappy for actual PDF generation
            return response($html)
                ->header('Content-Type', 'text/html')
                ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
                
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate PDF report',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate Excel report.
     */
    private function generateExcelReport($data, $type, $user)
    {
        try {
            $filename = "{$type}_report_" . now()->format('Y-m-d_H-i-s') . ".csv";
            
            // Generate CSV content
            $csv = $this->generateReportCsv($data, $type, $user);
            
            return response($csv)
                ->header('Content-Type', 'text/csv')
                ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
                
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate Excel report',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get dashboard data for export.
     */
    private function getDashboardData($user, $startDate)
    {
        try {
            if ($user->isAdmin()) {
                return [
                    'total_rfqs' => Rfq::count(),
                    'active_rfqs' => Rfq::whereIn('status', ['published', 'bidding_open'])->count(),
                    'total_bids' => Bid::count(),
                    'total_suppliers' => Company::where('type', 'supplier')->count(),
                    'period' => $startDate->format('Y-m-d') . ' to ' . now()->format('Y-m-d')
                ];
            } elseif ($user->isBuyer()) {
                return [
                    'my_rfqs' => Rfq::where('created_by', $user->id)->count(),
                    'active_rfqs' => Rfq::where('created_by', $user->id)->whereIn('status', ['published', 'bidding_open'])->count(),
                    'total_bids_received' => Bid::whereHas('rfq', function ($q) use ($user) {
                        $q->where('created_by', $user->id);
                    })->count(),
                    'period' => $startDate->format('Y-m-d') . ' to ' . now()->format('Y-m-d')
                ];
            } else {
                return [
                    'my_bids' => Bid::where('supplier_id', $user->id)->count(),
                    'awarded_bids' => Bid::where('supplier_id', $user->id)->where('status', 'awarded')->count(),
                    'period' => $startDate->format('Y-m-d') . ' to ' . now()->format('Y-m-d')
                ];
            }
        } catch (\Exception $e) {
            \Log::error('getDashboardData error: ' . $e->getMessage());
            return [
                'error' => 'Failed to fetch dashboard data',
                'period' => $startDate->format('Y-m-d') . ' to ' . now()->format('Y-m-d')
            ];
        }
    }

    /**
     * Get RFQ analysis data for export.
     */
    private function getRfqAnalysisData($user, $startDate)
    {
        try {
            return [
                'rfq_status_distribution' => $this->getRfqStatusDistribution($user->isBuyer() ? $user->id : null),
                'category_distribution' => $this->getCategoryDistribution($user->isBuyer() ? $user->id : null),
                'average_bids_per_rfq' => $this->getAverageBidsPerRfq($user->isBuyer() ? $user->id : null),
                'period' => $startDate->format('Y-m-d') . ' to ' . now()->format('Y-m-d')
            ];
        } catch (\Exception $e) {
            \Log::error('getRfqAnalysisData error: ' . $e->getMessage());
            return [
                'error' => 'Failed to fetch RFQ analysis data',
                'period' => $startDate->format('Y-m-d') . ' to ' . now()->format('Y-m-d')
            ];
        }
    }

    /**
     * Get supplier performance data for export.
     */
    private function getSupplierPerformanceData($user, $startDate)
    {
        try {
            if ($user->isAdmin()) {
                return [
                    'top_suppliers' => $this->getTopSuppliers($startDate),
                    'supplier_win_rates' => $this->getSupplierWinRates($startDate),
                    'period' => $startDate->format('Y-m-d') . ' to ' . now()->format('Y-m-d')
                ];
            } else {
                $userCompany = $user->companies->first();
                if ($userCompany) {
                    return [
                        'my_performance' => $this->getMySupplierPerformance($userCompany->id, $startDate),
                        'my_win_rate' => $this->getMyWinRate($userCompany->id, $startDate),
                        'period' => $startDate->format('Y-m-d') . ' to ' . now()->format('Y-m-d')
                    ];
                } else {
                    return [
                        'error' => 'No company found for user',
                        'period' => $startDate->format('Y-m-d') . ' to ' . now()->format('Y-m-d')
                    ];
                }
            }
        } catch (\Exception $e) {
            \Log::error('getSupplierPerformanceData error: ' . $e->getMessage());
            return [
                'error' => 'Failed to fetch supplier performance data',
                'period' => $startDate->format('Y-m-d') . ' to ' . now()->format('Y-m-d')
            ];
        }
    }


    /**
     * Generate HTML content for PDF reports.
     */
    private function generateReportHtml($data, $type, $user)
    {
        $html = '<!DOCTYPE html>
        <html>
        <head>
            <title>' . ucfirst($type) . ' Report</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; }
                .header { text-align: center; margin-bottom: 30px; }
                .section { margin-bottom: 20px; }
                table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
                th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                th { background-color: #f2f2f2; }
                .metric { display: inline-block; margin: 10px; padding: 15px; border: 1px solid #ddd; }
            </style>
        </head>
        <body>
            <div class="header">
                <h1>' . ucfirst($type) . ' Report</h1>
                <p>Generated on: ' . now()->format('Y-m-d H:i:s') . '</p>
                <p>User: ' . $user->name . ' (' . $user->role . ')</p>
            </div>';

        // Add data based on report type
        switch ($type) {
            case 'overview':
                $html .= '<div class="section">
                    <h2>Overview Metrics</h2>';
                foreach ($data as $key => $value) {
                    if (is_array($value)) continue;
                    $html .= '<div class="metric"><strong>' . ucfirst(str_replace('_', ' ', $key)) . ':</strong> ' . $value . '</div>';
                }
                $html .= '</div>';
                break;
                
            case 'rfq':
                $html .= '<div class="section">
                    <h2>RFQ Analysis</h2>';
                foreach ($data as $key => $value) {
                    if (is_array($value)) continue;
                    $html .= '<div class="metric"><strong>' . ucfirst(str_replace('_', ' ', $key)) . ':</strong> ' . $value . '</div>';
                }
                $html .= '</div>';
                break;
                
            case 'supplier':
                $html .= '<div class="section">
                    <h2>Supplier Performance</h2>';
                foreach ($data as $key => $value) {
                    if (is_array($value)) continue;
                    $html .= '<div class="metric"><strong>' . ucfirst(str_replace('_', ' ', $key)) . ':</strong> ' . $value . '</div>';
                }
                $html .= '</div>';
                break;
                
        }

        $html .= '</body></html>';
        return $html;
    }

    /**
     * Generate CSV content for Excel reports.
     */
    private function generateReportCsv($data, $type, $user)
    {
        $csv = "Report Type,{$type}\n";
        $csv .= "Generated On," . now()->format('Y-m-d H:i:s') . "\n";
        $csv .= "User,{$user->name} ({$user->role})\n";
        $csv .= "\n";
        
        // Add data rows
        foreach ($data as $key => $value) {
            if (is_array($value)) continue;
            $csv .= ucfirst(str_replace('_', ' ', $key)) . "," . $value . "\n";
        }
        
        return $csv;
    }

    /**
     * Get supplier monthly trends.
     */
    private function getSupplierMonthlyTrends($startDate, $userId)
    {
        return Bid::where('supplier_id', $userId)
            ->where('created_at', '>=', $startDate)
            ->get()
            ->groupBy(function ($item) {
                return $item->created_at->format('Y-m');
            })
            ->map(function ($group) {
                return [
                    'month' => $group->first()->created_at->format('Y-m'),
                    'rfqs' => 0,
                    'bids' => $group->count(),
                    'awards' => $group->where('status', 'awarded')->count(),
                ];
            })
            ->values()
            ->sortBy('month');
    }

    /**
     * Get supplier RFQ status distribution.
     */
    private function getSupplierRfqStatusDistribution($userId)
    {
        return Bid::join('rfqs', 'bids.rfq_id', '=', 'rfqs.id')
            ->selectRaw('rfqs.status, COUNT(*) as count')
            ->where('bids.supplier_id', $userId)
            ->groupBy('rfqs.status')
            ->get();
    }

    /**
     * Get supplier category distribution.
     */
    private function getSupplierCategoryDistribution($userId)
    {
        return Bid::join('rfqs', 'bids.rfq_id', '=', 'rfqs.id')
            ->join('rfq_items', 'rfqs.id', '=', 'rfq_items.rfq_id')
            ->join('items', 'rfq_items.item_id', '=', 'items.id')
            ->join('categories', 'items.category_id', '=', 'categories.id')
            ->selectRaw('categories.name, COUNT(*) as count')
            ->where('bids.supplier_id', $userId)
            ->groupBy('categories.id', 'categories.name')
            ->get();
    }
}
