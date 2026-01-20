<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StockApiController extends Controller
{
    public function index(Request $request)
    {
        try {
            $location_id = $request->get('location_id', auth()->user()->pharm_location_id);
            $page = $request->get('page', 1);
            $perPage = 50;
            $offset = ($page - 1) * $perPage;

            $filters = [
                'chrgdesc' => $request->get('chrgdesc', ''),
                'drug_concat' => $request->get('drug_concat', ''),
                'dmselprice' => $request->get('dmselprice', ''),
                'stock_bal' => $request->get('stock_bal', ''),
                'exp_date_from' => $request->get('exp_date_from', ''),
                'exp_date_to' => $request->get('exp_date_to', ''),
                'lot_no' => $request->get('lot_no', ''),
            ];

            $query = "
                SELECT
                    s.dmdcomb,
                    s.dmdctr,
                    s.drug_concat,
                    c.chrgdesc,
                    s.chrgcode,
                    p.dmselprice,
                    p.dmduprice,
                    s.loc_code,
                    s.dmdprdte,
                    s.updated_at,
                    s.exp_date,
                    s.stock_bal,
                    s.id,
                    p.has_compounding,
                    p.compounding_fee,
                    l.description,
                    s.lot_no
                FROM pharm_drug_stocks s WITH (NOLOCK)
                INNER JOIN hcharge c WITH (NOLOCK) ON c.chrgcode = s.chrgcode
                INNER JOIN hdmhdrprice p WITH (NOLOCK) ON p.dmdprdte = s.dmdprdte
                INNER JOIN pharm_locations l WITH (NOLOCK) ON l.id = s.loc_code
                WHERE s.loc_code = ?
                AND s.stock_bal > 0
            ";

            $params = [$location_id];

            if (!empty($filters['chrgdesc'])) {
                $query .= " AND c.chrgdesc LIKE ?";
                $params[] = '%' . $filters['chrgdesc'] . '%';
            }

            if (!empty($filters['drug_concat'])) {
                $query .= " AND s.drug_concat LIKE ?";
                $params[] = '%' . $filters['drug_concat'] . '%';
            }

            if (!empty($filters['dmselprice'])) {
                $query .= " AND CAST(p.dmselprice AS VARCHAR) LIKE ?";
                $params[] = '%' . $filters['dmselprice'] . '%';
            }

            if (!empty($filters['stock_bal'])) {
                $query .= " AND CAST(s.stock_bal AS VARCHAR) LIKE ?";
                $params[] = '%' . $filters['stock_bal'] . '%';
            }

            if (!empty($filters['exp_date_from']) && !empty($filters['exp_date_to'])) {
                $query .= " AND s.exp_date BETWEEN ? AND ?";
                $params[] = $filters['exp_date_from'];
                $params[] = $filters['exp_date_to'];
            } elseif (!empty($filters['exp_date_from'])) {
                $query .= " AND s.exp_date >= ?";
                $params[] = $filters['exp_date_from'];
            } elseif (!empty($filters['exp_date_to'])) {
                $query .= " AND s.exp_date <= ?";
                $params[] = $filters['exp_date_to'];
            }

            if (!empty($filters['lot_no'])) {
                $query .= " AND s.lot_no LIKE ?";
                $params[] = '%' . $filters['lot_no'] . '%';
            }

            $query .= "
                ORDER BY s.drug_concat ASC
                OFFSET ? ROWS
                FETCH NEXT ? ROWS ONLY
            ";

            $params[] = $offset;
            $params[] = $perPage;

            $stocks = DB::connection('sqlsrv')->select($query, $params);

            return response()->json([
                'success' => true,
                'data' => $stocks,
                'current_page' => (int)$page,
                'per_page' => $perPage,
                'has_more' => count($stocks) === $perPage,
                'filters' => $filters
            ]);
        } catch (\Exception $e) {
            \Log::error('Stock API Error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error loading stocks',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
