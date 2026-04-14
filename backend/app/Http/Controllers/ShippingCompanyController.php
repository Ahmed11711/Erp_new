<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderDetails;
use App\Models\ShippingCompany;
use App\Models\shippingCompanyDetails;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;


class ShippingCompanyController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $shippingCompanies = ShippingCompany::all();
        return response()->json($shippingCompanies, 200);
    }

    public function shippingcompanySelect()
    {
        $data = ShippingCompany::select('id', 'name')->get();
        return response()->json($data, 200);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required',
            'type' => 'required|in:مندوب,شركة',
        ]);
        ShippingCompany::create([
            'name' => $request->name,
            'type' => $request->type,
        ]);

        return response()->json("created", 201);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $itemsPerPage = request('itemsPerPage') ?: 10;

        $orderDetails = OrderDetails::where('shipping_company_id', $id)
            ->with(['order:id,customer_name,customer_phone_1,order_date,order_status,net_total','shipping_company:id,name'])
            ->orderBy('id', 'desc')
            ->paginate($itemsPerPage);

            $totalNet = Order::whereHas('order_details', function ($query) use ($id) {
                $query->where('shipping_company_id', $id);
            })->sum('net_total');


            // $orderDetails['totalNet'] = $totalNet;

            $response = [
                'orderDetails' => $orderDetails,
                'totalNet' => $totalNet,
            ];

        return response()->json($response, 200);
    }

    public function search(Request $request)
    {
        $id = $request->id;
        $itemsPerPage = $request->itemsPerPage ?? 15;

        $search = shippingCompanyDetails::query();

        if ($request->has('id')) {
            $search->where('shipping_company_id', $id);
        }

        if ($request->has('shippingDate')) {
            $search->where(function ($query) use ($request) {
                $query->where('shipping_date', $request->shippingDate);
            });
        }

        if ($request->has('order_status')) {
            $search->where(function ($query) use ($request) {
                $query->where('status', $request->order_status)
                    ->where(function ($subquery) {
                        $subquery->where('is_done', '=', 0);
                    });
            });
        }


        if ($request->has('collectDate')) {
            $search->where(function ($query) use ($request) {
                $query->where('collect_date', $request->collectDate);
            });
        }

        if ($request->has('reviewed')) {
            $search->whereHas('order.order_details', function ($query) use ($request) {
                $query->where('reviewed', $request->reviewed);
            });
        }

        $search->with('order')->with([
            'order.order_details',
            'order' => function ($query) {
                $query->withCount([
                    'notifications as review_notifications_count' => function ($query) {
                        $query->where('type', 'مراجعة')
                            ->where('send_from', auth()->id());
                    }
                ]);
            }
        ]);
        $search->orderBy('id', 'desc');

        $allData= $search->get();

        $totalNet = $allData->map(function ($item) {
            return $item->amount;
        })->sum();

        $search = $search->paginate($itemsPerPage);


        $name = ShippingCompany::find($id);

        $response = [
            'orderDetails' => $search,
            'totalNet' => $totalNet,
            'name' => $name,
        ];



        return response()->json($response, 200);
    }


    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $companyToUpdate = ShippingCompany::find($id);
        if(!$companyToUpdate){
            return response()->json("not found", 404);
        }

        $request->validate([
            'name' => 'required',
            'type' => 'required|in:مندوب,شركة',
        ]);

        $companyToUpdate->update([
            'name' => $request->name,
            'type' => $request->type,
        ]);
        return response()->json("updated", 200);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $companyToDelete = ShippingCompany::find($id);
        if(!$companyToDelete){
            return response()->json("not found", 404);
        }
        $companyToDelete->delete();

        return response()->json("deleted", 200);
    }

    /**
     * تقرير أداء شركات الشحن حسب فترة (من سجلات shipping_company_details).
     */
    public function shippingCompaniesReport(Request $request)
    {
        $validated = $request->validate([
            'date_from' => 'required|date',
            'date_to' => 'required|date|after_or_equal:date_from',
            'q' => 'nullable|string|max:255',
        ]);

        $from = $validated['date_from'];
        $to = $validated['date_to'];
        $q = isset($validated['q']) ? trim($validated['q']) : '';

        $bindings = [$from, $to, $from, $to, $from, $to];
        $where = '';
        if ($q !== '') {
            $where = 'WHERE sc.name LIKE ?';
            $bindings[] = '%'.$q.'%';
        }

        $sql = "
            SELECT sc.id,
                sc.name,
                sc.type,
                COUNT(DISTINCT CASE
                    WHEN scd.status = 'تم شحن'
                    AND DATE(COALESCE(NULLIF(scd.shipping_date, '0000-00-00'), scd.created_at)) BETWEEN ? AND ?
                    THEN scd.order_id END) AS shipped_count,
                COUNT(DISTINCT CASE
                    WHEN scd.status = 'تم التحصيل'
                    AND DATE(COALESCE(NULLIF(scd.collect_date, '0000-00-00'), scd.created_at)) BETWEEN ? AND ?
                    THEN scd.order_id END) AS collected_count,
                COUNT(DISTINCT CASE
                    WHEN scd.status = 'رفض استلام'
                    AND DATE(COALESCE(NULLIF(scd.collect_date, '0000-00-00'), scd.created_at)) BETWEEN ? AND ?
                    THEN scd.order_id END) AS refused_count
            FROM shipping_companies sc
            LEFT JOIN shipping_company_details scd ON scd.shipping_company_id = sc.id
            {$where}
            GROUP BY sc.id, sc.name, sc.type
            ORDER BY sc.name
        ";

        $rows = DB::select($sql, $bindings);

        $reasonWhere = '';
        $reasonParams = [$from, $to];
        if ($q !== '') {
            $reasonWhere = 'AND sc.name LIKE ?';
            $reasonParams[] = '%'.$q.'%';
        }

        $reasonSql = "
            SELECT scd.shipping_company_id AS id,
                GROUP_CONCAT(DISTINCT NULLIF(TRIM(n.note), '') SEPARATOR ' | ') AS refusal_reasons
            FROM shipping_company_details scd
            INNER JOIN shipping_companies sc ON sc.id = scd.shipping_company_id
            INNER JOIN notes n ON n.order_id = scd.order_id AND n.added_from = 'رفض استلام'
            WHERE scd.status = 'رفض استلام'
            AND DATE(COALESCE(NULLIF(scd.collect_date, '0000-00-00'), scd.created_at)) BETWEEN ? AND ?
            {$reasonWhere}
            GROUP BY scd.shipping_company_id
        ";

        $reasonRows = DB::select($reasonSql, $reasonParams);

        $reasonByCompany = [];
        foreach ($reasonRows as $r) {
            $reasonByCompany[(int) $r->id] = $r->refusal_reasons ?? '';
        }

        $data = [];
        foreach ($rows as $r) {
            $shipped = (int) $r->shipped_count;
            $collected = (int) $r->collected_count;
            $refused = (int) $r->refused_count;
            $pct = $shipped > 0
                ? round(100.0 * $collected / $shipped, 2)
                : 0.0;

            $data[] = [
                'id' => (int) $r->id,
                'name' => $r->name,
                'type' => $r->type,
                'orders_count' => $shipped,
                'collected_count' => $collected,
                'refused_count' => $refused,
                'collection_percentage' => $pct,
                'refusal_reasons' => $reasonByCompany[(int) $r->id] ?? '',
                'period_from' => $from,
                'period_to' => $to,
            ];
        }

        return response()->json([
            'data' => $data,
            'date_from' => $from,
            'date_to' => $to,
        ], 200);
    }
}
