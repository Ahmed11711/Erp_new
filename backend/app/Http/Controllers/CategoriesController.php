<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Validator;
use Carbon\Carbon;
use App\Models\OrderProduct;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use App\Models\CategoryMonthlyInventory;
use App\Http\Resources\V2\Category\CategoryResource;
use App\Http\Requests\V2\Category\GetCategoryByStock;

class CategoriesController extends Controller
{
 /**
  * Display a listing of the resource.
  *
  * @return \Illuminate\Http\Response
  */
 public function index()
 {
  $itemsPerPage = request('itemsPerPage') ? request('itemsPerPage') : 10;
  $category = Category::with('production', 'measurement', 'stock:id,name')->paginate($itemsPerPage);
  return response()->json($category, 200);
 }


 public function allCategories()
 {
  $cateogry = Category::all();
  return response()->json($cateogry, 200);
 }

 public function getCategoryById($id)
 {
  $cateogry = Category::find($id);
  return response()->json($cateogry, 200);
 }

 public function getCategoryByStockId(Request $request)
 {
  $request->validate([
   'stock_id' => 'required|integer|exists:stocks,id',
  ]);

  $stock_id = $request->query('stock_id');
  $itemsPerPage = $request->query('itemsPerPage', 15);

  $categories = Category::with(['production', 'measurement', 'stock:id,name'])
   ->where('stock_id', $stock_id)
   ->paginate($itemsPerPage);

  return response()->json($categories, 200);
 }


 public function deleteCategory($id)
 {
  $category = Category::find($id);

  if (!$category) {
   return response()->json(['error' => 'Category not found'], 404);
  }
  $category->delete();

  return response()->json(['message' => 'Category deleted successfully'], 200);
 }


 /**
  * Store a newly created resource in storage.
  *
  * @param  \Illuminate\Http\Request  $request
  * @return \Illuminate\Http\Response
  */
 public function store(Request $request)
 {
  // return $request->category_image;
  $request->validate([
   'category_name' => 'required|string',
   'category_price' => 'required|numeric|min:0',
   'category_code' => 'required|string|max:255|unique:categories,category_code',
   'initial_balance' => 'required|numeric|min:0',
   'minimum_quantity' => 'required|numeric|min:0',
   'warehouse' => 'required|string',
   'production_id' => 'required|numeric|exists:productions,id',
   'measurement_id' => 'required|numeric|exists:measurements,id',
   'category_image' => 'image|mimes:jpeg,png,jpg,gif,svg|max:500',
   'stock_id' => 'required|integer|exists:stocks,id', // add this line for stock_id validation
  ]);
  $img_name = '';
  if ($request->hasFile('category_image')) {
   $img = $request->file('category_image');
   $img_name = time() . '.' . $img->extension();
   $img->move(public_path('images'), $img_name);
  }

  $exist = Category::where('warehouse', $request->warehouse)
   ->whereRaw('TRIM(category_name) = ?', trim($request->category_name))
   ->first();

  if ($exist) {
   return response()->json(['message' => 'هذا الصنف موجود بالفعل'], 422);
  }

  $category = Category::create([
   'category_name' => request('category_name'),
   'category_price' => request('category_price'),
   'category_code' => request('category_code'),
   'initial_balance' => request('initial_balance'),
   'minimum_quantity' => request('minimum_quantity'),
   'warehouse' => request('warehouse'),
   'production_id' => request('production_id'),
   'measurement_id' => request('measurement_id'),
   'category_image' => $img_name,
   'stock_id' => request('stock_id'),
  ]);


  return response()->json($category, 201);
 }

 public function updateCode(Request $request, $id)
 {
  $request->validate([
   'category_code' => 'required|unique:categories,category_code,' . $id,
  ]);

  $category = Category::findOrFail($id);
  $category->category_code = $request->category_code;
  $category->save();

  return response()->json(['success' => true, 'category_code' => $category->category_code]);
 }


 public function editCategory($id, Request $request)
 {
  $request->validate([
   'category_name' => 'required|string',
   'category_price' => 'required|numeric',
   'initial_balance' => 'required|numeric',
   'minimum_quantity' => 'required|numeric',
   'warehouse' => 'required|string',
   'production_id' => 'required|numeric|exists:productions,id',
   'measurement_id' => 'required|numeric|exists:measurements,id',
   'category_image' => 'image|mimes:jpeg,png,jpg,gif,svg|max:500',
   'stock_id' => 'required|integer|exists:stocks,id',

  ]);

  $category = Category::find($id);

  if (!$category) {
   return response()->json(['error' => 'Category not found'], 404);
  }

  $img_name = '';
  if ($request->hasFile('category_image')) {
   $img = $request->file('category_image');
   $img_name = time() . '.' . $img->extension();
   $img->move(public_path('images'), $img_name);
   $oldImgPath = public_path('images') . '/' . $category->category_image;
   if (file_exists($oldImgPath) && is_file($oldImgPath)) {
    unlink($oldImgPath);
   }
  }

  $category->update([
   'category_name' => $request->input('category_name'),
   'category_price' => $request->input('category_price'),
   'initial_balance' => $request->input('initial_balance'),
   'minimum_quantity' => $request->input('minimum_quantity'),
   'warehouse' => $request->input('warehouse'),
   'production_id' => $request->input('production_id'),
   'measurement_id' => $request->input('measurement_id'),
   'category_image' => $img_name,
   'stock_id' => $request->input('stock_id'),

  ]);


  return response()->json($category, 200);
 }



 public function search(Request $request)
 {

  $itemsPerPage = request('itemsPerPage') ? request('itemsPerPage') : 10;
  $search = Category::query();

  $userDepartment = auth()->user()->department;

  $roleCategoryStatuses = [
   'Customer Service' => ["مخزن منتج تام"],
   'Data Entry' => ["مخزن منتج تام"],
  ];
  $CategoryStatusArray = $roleCategoryStatuses[$userDepartment] ?? [];

  $search = $search->whereNot('warehouse', 'مخزن صيانة');
  if (!empty($CategoryStatusArray)) {
   $search->whereIn('warehouse', $CategoryStatusArray);
  }
  if ($request->has('category_name')) {
   $search->where('category_name', 'like', '%' . $request->category_name . '%');
  }
  if ($request->has('production_id')) {
   $search->where('production_id', $request->production_id);
  }
  if ($request->has('warehouse')) {
   $search->where('warehouse', 'like', '%' . $request->warehouse . '%');
  }
  $search->orderBy('category_name', 'asc');

  $search = $search->with('production', 'measurement')->paginate($itemsPerPage);
  return response()->json($search, 200);
 }


 public function catName()
 {
  $catName = Category::with('measurement:id,unit')->select('category_name', 'category_price', 'measurement_id')->get();

  return response()->json($catName, 200);
 }

 public function changeCategoryQuantityss(Request $request)
 {
  $id = $request->id;           // الرقم التعريفي للصنف
  $status = $request->status;   // عادة "update"
  $quantity = $request->quantity; // الرقم الجديد

  // مثال تحديث الصنف
  $category = Category::find($id);
  if (!$category) {
   return response()->json(['success' => false, 'message' => 'الصنف غير موجود']);
  }

  $category->quantity = $quantity;
  $category->save();

  return response()->json([
   'success' => true,
   'quantity' => $category->quantity
  ]);
 }



 public function changeCategoryQuantity(Request $request)
 {
  $quantity = $request->quantity;
  $category = Category::find($request->id);
  $categorPrice = $category->category_price;
  $ref = null;
  if ($request->status == 'add' && $quantity > 0) {
   $ref = 'CH+';
  }
  if ($request->status == 'add' && $quantity < 0) {
   $ref = 'CH-';
  }
  if ($request->status == 'add' && $quantity !== 0) {
   $cat_details =  DB::table('categories_balance')->where('category_id', $request->id)->latest()->first();
   if ($cat_details) {
    $categorPrice = $cat_details->price;
   }
   DB::table('categories_balance')->insert([
    'invoice_number' => $ref,
    'category_id' => $request->id,
    'type' => 'تعديل الصنف',
    'quantity' => $quantity,
    'balance_before' => $category->quantity,
    'balance_after' => $category->quantity + $quantity,
    'price' => $categorPrice,
    'total_price' => $categorPrice * $quantity,
    'by' => auth()->user()->name,
    'created_at' => now()
   ]);
   $category->quantity = $category->quantity + $quantity;
   if ($category->warehouse == 'مخزن منتج تام') {
    $category->sell_total_price = $category->sell_total_price + ($quantity * $categorPrice);
   } else {
    $category->total_price = $category->total_price + ($quantity * $categorPrice);
   }
   $category->save();
   return response()->json('success', 200);
  }

  if ($request->status == 'edit' && $quantity > 0) {
   $cat_details =  DB::table('categories_balance')->where('category_id', $request->id)->latest()->first();
   if ($cat_details) {
    $categorPrice = $cat_details->price;
   }
   $ref = 'CHQ';
   $totalPrice = $categorPrice * ($quantity - $category->quantity);
   DB::table('categories_balance')->insert([
    'invoice_number' => $ref,
    'category_id' => $request->id,
    'type' => 'تعديل الصنف',
    'quantity' => $quantity - $category->quantity,
    'balance_before' => $category->quantity,
    'balance_after' => $quantity,
    'price' => $categorPrice,
    'total_price' => $categorPrice * ($quantity - $category->quantity),
    'by' => auth()->user()->name,
    'created_at' => now()
   ]);
   $category->quantity = $quantity;
   if ($category->warehouse == 'مخزن منتج تام') {
    $category->sell_total_price = $category->sell_total_price + $totalPrice;
   } else {
    $category->total_price = $category->total_price + $totalPrice;
   }
   $category->save();
   return response()->json('success', 200);
  }
 }

 public function categoryByWarehouse(Request $request)
 {
  $category = Category::where('warehouse', $request->warehouse)->with('production', 'measurement')->get();
  return response()->json($category, 200);
 }

 public function categoryDetailsByWherehouse(Request $request)
 {
  $itemsPerPage = $request->input('itemsPerPage', 15);

  $category = Category::where('warehouse', $request->warehouse);

  if ($request->has('name')) {
   $category->where('category_name', 'like', '%' . $request->name . '%');
  }
  if ($request->has('sort')) {
   if ($request->warehouse == 'مخزن منتج تام') {
    $category->orderBy('sell_total_price', 'desc');
   } else {
    $category->orderBy('total_price', 'desc');
   }
  }
  $category = $category->with('production', 'measurement')->paginate($itemsPerPage);


  return response()->json($category, 200);
 }

 public function monthlyInventoryDetailsByWherehouse(Request $request)
 {
  $itemsPerPage = $request->input('itemsPerPage', 15);

  $category = CategoryMonthlyInventory::where('month', $request->month)
   ->whereHas('category', function ($query) use ($request) {
    $query->where('warehouse', $request->warehouse);
   });

  if ($request->has('name')) {
   $category->whereHas('category', function ($query) use ($request) {
    $query->where('category_name', 'like', '%' . $request->name . '%');
   });
  }

  if ($request->has('sort')) {
   if ($request->warehouse == 'مخزن منتج تام') {
    $category->orderBy('sell_total_price', 'desc');
   } else {
    $category->orderBy('total_price', 'desc');
   }
  }

  $category = $category->with('category', 'category.production', 'category.measurement')->paginate($itemsPerPage);

  return response()->json($category, 200);
 }


 public function warehouseDetails(Request $request)
 {
  $itemsPerPage = $request->input('itemsPerPage', 15);

  $warehouse = DB::table('categories_balance')
   ->join('categories', 'categories_balance.category_id', '=', 'categories.id')
   ->where('categories.warehouse', $request->warehouse)
   ->when($request->has('date'), function ($query) use ($request) {
    return $query->whereDate('categories_balance.created_at', $request->date);
   })
   ->select('categories_balance.*', 'categories.category_name')
   ->orderBy('categories_balance.id', 'desc')
   ->paginate($itemsPerPage);

  return response()->json($warehouse, 200);
 }

 public function categories_details($id, Request $request)
 {
  $itemsPerPage = $request->input('itemsPerPage', 15);
  $name = Category::findOrFail($id)->category_name;

  $cat_details =  DB::table('categories_balance')->where('category_id', $id);
  if ($request->has('ref')) {
   $cat_details->where('ref', 'like', '%' . $request->ref . '%');
  }
  $cat_details = $cat_details->orderBy('created_at', 'desc')->paginate($itemsPerPage);

  return response()->json(['name' => $name, 'details' => $cat_details], 200);
 }

 public function warehouse_balance()
 {
  Log::info("message", ['sss']);
  $warehouseMappings = [
   'مخزن مواد خام' => 'Raw',
   'مخزن منتج تحت التشغيل' => 'In_Process',
   'مخزن منتج تام' => 'Finished',
   'مخزن صيانة' => 'Maintenance',
   'مخزن تالف' => 'Defective',
  ];

  $warehouses = array_keys($warehouseMappings);

  $warehouseBalances = [];

  foreach ($warehouses as $warehouse) {
   if ($warehouse == 'مخزن منتج تام') {
    $totalPrice = Category::where('warehouse', $warehouse)->sum('sell_total_price');
   } else {
    $totalPrice = Category::where('warehouse', $warehouse)->sum('total_price');
   }
   $englishWarehouseName = $warehouseMappings[$warehouse];
   $warehouseBalances[$englishWarehouseName] = $totalPrice;
  }

  return response()->json($warehouseBalances, 200);
 }

 public function categories_for_orders()
 {
  $data = Category::where('warehouse', 'مخزن منتج تام')
   ->select('id', 'category_name', 'category_price', 'category_image')
   ->get();
  return response()->json($data, 200);
 }

 public function monthlyInventory(Request $request)
 {
  $categories = Category::where('warehouse', $request->warehouse)->get();
  $transformedCategories = $categories->map(function ($category) {
   $previousMonthDate = Carbon::now()->subMonth()->format('Y-m');
   return [
    'category_id' => $category->id,
    'quantity' => $category->quantity,
    'total_price' => $category->total_price,
    'sell_total_price' => $category->sell_total_price,
    'month' => $previousMonthDate,
    'by' => auth()->user()->name,
    'created_at' => now(),
   ];
  })->toArray();

  DB::table('category_monthly_inventories')->insert($transformedCategories);

  return response()->json('success', 200);
 }


 public function categoriesSellReports(Request $request)
 {
  $itemsPerPage = $request->input('itemsPerPage', 15);

  $query = DB::table('order_products')
   ->select(
    'categories.id as category_id',
    'categories.category_name as category_name',
    DB::raw('SUM(CASE WHEN orders.order_type = "جديد" THEN order_products.quantity ELSE 0 END) as total_quantity_new'),
    DB::raw('SUM(CASE WHEN orders.order_type = "طلب مرتجع" THEN order_products.quantity ELSE 0 END) as total_quantity_return'),
    DB::raw('COUNT(DISTINCT order_products.order_id) as total_orders'),
    DB::raw('SUM(order_products.total_price) as total_revenue'),
    DB::raw('SUM(CASE WHEN orders.order_type = "جديد" THEN order_products.total_price ELSE 0 END) as total_new'),
    DB::raw('SUM(CASE WHEN orders.order_type = "طلب مرتجع" THEN order_products.total_price ELSE 0 END) as total_postpone')
   )
   ->join('categories', 'order_products.category_id', '=', 'categories.id')
   ->join('orders', 'order_products.order_id', '=', 'orders.id')
   ->whereIn('orders.order_type', ['جديد', 'طلب مرتجع'])
   ->whereBetween('orders.order_date', [$request->date_from, $request->date_to]);

  if ($request->has('production_id')) {
   $query->where('categories.production_id', $request->production_id);
  }

  $categorySales = $query->groupBy('categories.id', 'categories.category_name');

  if ($request->has('sort')) {
   if ($request->sort == 'category_name' || $request->sort == 'total_quantity_return' || $request->sort == 'total_postpone') {
    $query->orderBy($request->sort);
   } else {
    $query->orderByDesc($request->sort);
   }
  }
  $categorySales = $query->paginate($itemsPerPage);

  return response()->json($categorySales, 200);
 }

















 /**
  * Display the specified resource.
  *
  * @param  int  $id
  * @return \Illuminate\Http\Response
  */
 public function show($id)
 {
  //
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
  //
 }

 /**
  * Remove the specified resource from storage.
  *
  * @param  int  $id
  * @return \Illuminate\Http\Response
  */
 public function destroy($id)
 {
  //
 }
}
