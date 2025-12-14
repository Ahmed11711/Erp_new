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
}
