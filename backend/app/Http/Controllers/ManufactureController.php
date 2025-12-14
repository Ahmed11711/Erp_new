<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\ConfirmedManfucture;
use App\Models\Manufacture;
use App\Models\ManufactureProduct;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ManufactureController extends Controller
{
    //
    public function index(){
        $manufactures = Manufacture::with('product')->get();
        return response()->json($manufactures,200);
    }
    public function store(Request $request){

        $request->validate([
            'product_id'=>'required',
            'total'=>'required'
        ]);
        $manfuture = Manufacture::create([
            'product_id'=> $request->product_id,
            'total'=> $request->total
            ]);
        foreach($request->products as $product){
            ManufactureProduct::create([
                'manufacture_id'=>$manfuture->id,
                'product_id'=>$product['id'],
                'quantity'=>$product['quantity'],
                'total_price'=>$product['total_price']
            ]);
        }
        return response()->json('success',201);
    }

    public function manfucture_by_warhouse(Request $request){

        $request->validate([
            'warehouse'=>'required'
        ]);
        $manufactures = Manufacture::with('product')->get();
        $p = [];
        foreach($manufactures as $manufacture){
            if($manufacture->product->warehouse == $request->warehouse){
                $data = (object) [
                    'id' => $manufacture->product->id,
                    'category_name' => $manufacture->product->category_name,
                    'cost' => $manufacture->total,
                ];

                array_push($p,$data);
            }
        }
        return response()->json($p,200);

    }

    public function confirm(Request $request){
        $request->validate([
                'quantity'=>'required',
                'status'=>'required',
                'total'=>'required',
                'date'=>'required',
                'product_id'=>'required'
            ]);
            $confirmed = ConfirmedManfucture::create([
                'quantity'=>$request->quantity,
                'status'=>$request->status,
                'total'=>$request->total,
                'date'=>$request->date,
                'user_id'=>auth()->user()->id,
                'product_id'=>$request->product_id
            ]);
            $manfucture = Manufacture::where('product_id',$confirmed->product_id)->first();

            $manproducts = ManufactureProduct::where('manufacture_id',$manfucture->id)->get();
            //  return response()->json($manproducts,201);
                foreach($manproducts as $manproduct){
                    $category = Category::find($manproduct->product_id);

                    // rating
                    $neededQuantity = $manproduct->quantity*$request->quantity;
                    $warehouseRatings = DB::table('warehouse_ratings')->where('category_id' , $manproduct->product_id)->get();
                    $availableQuantity = 0;
                    $total_price=$category->total_price;
                    foreach ($warehouseRatings as $product) {
                        if ($product->quantity == 0) {
                            continue;
                        }
                        $availableQuantity = $product->quantity-$neededQuantity;
                        if($availableQuantity<=0){
                            $neededQuantity=$neededQuantity-$product->quantity;
                            // DB::table('warehouse_ratings')->where('id', $product->id)->delete();
                            DB::table('warehouse_ratings')->where('id', $product->id)->update(['quantity' => 0]);
                            $total_price -= $product->quantity*$product->price;
                        } else {
                            $total_price -= $neededQuantity*$product->price;
                            DB::table('warehouse_ratings')->where('id', $product->id)->increment('quantity', -$neededQuantity);
                            $category->total_price = $total_price;
                            break;
                        }
                    }
                    //end rating

                    DB::table('categories_balance')->insert([
                        'invoice_number' => $confirmed->id,
                        'category_id' => $category->id,
                        'type' => 'تصنيع',
                        'quantity' => $manproduct['quantity']*$confirmed['quantity'],
                        'balance_before' => $category->quantity,
                        'balance_after' => $category->quantity- ($manproduct['quantity']*$confirmed['quantity']),
                        'price' => $manproduct['total_price']/$manproduct['quantity'],
                        'total_price' => $manproduct['total_price'],
                        'by' => auth()->user()->name,
                        'created_at' =>now()
                    ]
                    );
                    // $category->initial_balance = $category->initial_balance - $manproduct->quantity;
                    $category->quantity = $category->quantity - ($manproduct->quantity*$request->quantity);
                    $category->save();
                }
            if($confirmed->status == 'تم الانتهاء'){
                $category = Category::find($request->product_id);
                DB::table('categories_balance')->insert([
                    'invoice_number' => $confirmed->id,
                    'category_id' => $category->id,
                    'type' => 'تصنيع',
                    'quantity' => $confirmed['quantity'],
                    'balance_before' => $category->quantity,
                    'balance_after' => $category->quantity + $confirmed->quantity,
                    'price' => $confirmed['total']/$confirmed['quantity'],
                    'total_price' => $confirmed['total'],
                    'by' => auth()->user()->name,
                    'created_at' =>now()
                ]
                );
                // $category->initial_balance = $category->initial_balance + $confirmed->quantity;
                $category->quantity = $category->quantity + $confirmed->quantity;
                $category->total_price = $category->total_price + $confirmed->total;
                $category->unit_price = $confirmed->total/$confirmed->quantity;
                $category->sell_total_price = $category->sell_total_price + ($category->category_price*$request->quantity);
                $category->save();
            }
            return response()->json($confirmed,201);

    }

    public function confirmed(){
        $confirmed = ConfirmedManfucture::with(['user' => function ($query) {
            $query->select('id','name');
        },
        'product'=>function($query){
            $query->select('id','category_name');
        }
        ])->get();
            return response()->json($confirmed,200);
    }

    public function done($id){
        $confirmed = ConfirmedManfucture::find($id);
        $confirmed->status = 'تم الانتهاء';
        $confirmed->save();

        $category = Category::find($confirmed->product_id);
        // return response()->json([$confirmed,$category],200);


        DB::table('categories_balance')->insert([
            'invoice_number' => $confirmed->id,
            'category_id' => $category->id,
            'type' => 'تصنيع',
            'quantity' => $confirmed['quantity'],
            'balance_before' => $category->quantity,
            'balance_after' => $category->quantity + $confirmed->quantity,
            'price' => $confirmed['total']/$confirmed['quantity'],
            'total_price' => $confirmed['total'],
            'by' => auth()->user()->name,
            'created_at' =>now()
        ]
        );
        // $category->initial_balance = $category->initial_balance + $confirmed->quantity;
        $category->quantity = $category->quantity + $confirmed->quantity;
        $category->total_price = $category->total_price + $confirmed->total;
        $category->unit_price = $confirmed->total/$confirmed->quantity;
        $category->sell_total_price = $category->sell_total_price + ($category->category_price*$confirmed->quantity);
        $category->save();

        // $manfucture = Manufacture::where('product_id',$confirmed->product_id)->first();
        //  $manproducts = ManufactureProduct::where('manufacture_id',$manfucture->id)->get();
        //     foreach($manproducts as $manproduct){
        //         $category = Category::find($manproduct->product_id);
        //         $category->initial_balance = $category->initial_balance - $manproduct->quantity;
        //         $category->save();
        //     }
        return response()->json('success',200);
    }
}
