<?php

namespace App\Http\Controllers;

use App\Models\Approvals;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Purchase;
use App\Services\Purchases\PurchaseDeletionService;


class ApprovalsController extends Controller
{

    public function index(Request $request){
        $itemsPerPage = request('itemsPerPage') ? request('itemsPerPage') : 10;
        $data = Approvals::query();
        if ($request->has('type')) {
            $data = $data->where('type' , $request->type);
        }
        if ($request->has('status')) {
            $data = $data->where('status' , $request->status);
        }
        if ($request->has('table_name')) {
            $data = $data->where('table_name' , $request->table_name);
        }
        if ($request->has('date')) {
            $data = $data->whereDate('created_at', 'like', $request->date . '%');
        }
        $data = $data->with('user')->orderBy('id' , 'desc')->paginate($itemsPerPage);
        return response()->json($data);
    }

    public function store(Request $request)
    {
        $request->validate([
            "id" => "required|exists:approvals,id",
            'status' => 'required|in:approved,rejected',
        ]);

        DB::beginTransaction();

        try {
            $data = Approvals::find($request->id);
            if($data->status !== 'pending'){
                return response()->json(['message' => 'Approval is not pending'], 422);
            }
            $data->status = $request->status;
            $data->save();

            if ($request->status === 'approved' && isset($data->column_values['id'])) {

                if($data->table_name == 'purchases' && $data->type == 'delete'){

                    $main = Purchase::query()
                        ->where('invoice_number', $data->column_values['invoice_number'])
                        ->whereNull('ref')
                        ->first();

                    if (! $main) {
                        $main = Purchase::query()
                            ->where('invoice_number', $data->column_values['invoice_number'])
                            ->orderBy('id')
                            ->first();
                    }

                    if (! $main) {
                        throw new \RuntimeException('تعذر العثور على فاتورة المشتريات.');
                    }

                    app(PurchaseDeletionService::class)->delete((int) $main->id, (int) auth()->id());

                } else {
                    $columnValues = $data->column_values;
                    $id = $columnValues['id'];
                    unset($columnValues['id']);

                    DB::table($data->table_name)->where('id', $id)->update($columnValues);
                }
            }

            DB::commit();
            return response()->json(['message' => 'success'], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to process the request', 'error' => $e->getMessage()], 500);
        }
    }

}
