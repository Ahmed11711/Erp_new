<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Manufacture;
use App\Models\ManufactureProduct;
use App\Models\RecipeIngredient;
use App\Models\Stock;
use App\Models\TreeAccount;
use Validator;
use Carbon\Carbon;
use App\Models\OrderProduct;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use App\Models\CategoryMonthlyInventory;
use App\Http\Resources\V2\Category\CategoryResource;
use App\Http\Requests\V2\Category\GetCategoryByStock;
use App\Models\Item;
use App\Services\Accounting\InventoryGlPostingService;
use App\Services\Accounting\InventoryAccountsTrueUpService;
use App\Services\Accounting\ProductPerformanceReportService;
use App\Services\CategoryInventoryCostService;
use App\Services\Items\CategoryMergeService;
use App\Services\Items\CategoryForceDeletionService;
use App\Services\Items\ItemCodeService;
use App\Enums\InventoryMovementType;
use App\Services\Inventory\InventoryMovementLedgerService;

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
  $category = Category::with('production', 'measurement', 'itemClassification', 'stock:id,name')->paginate($itemsPerPage);
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

  $categories = Category::with(['production', 'measurement', 'itemClassification', 'stock:id,name'])
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

  $blockReasons = [];
  if (ManufactureProduct::query()->where('product_id', $id)->exists()) {
   $blockReasons[] = 'الصنف مستخدم كمكوّن (مادة خام) ضمن تفاصيل أوامر التصنيع.';
  }
  if (Manufacture::query()->where('product_id', $id)->exists()) {
   $blockReasons[] = 'الصنف معرّف كمنتج نهائي في أمر تصنيع.';
  }
  if (OrderProduct::query()->where('category_id', $id)->exists()) {
   $blockReasons[] = 'الصنف مستخدم في طلبات مبيعات.';
  }
  if (RecipeIngredient::query()->where('item_id', $id)->exists()) {
   $blockReasons[] = 'الصنف مستخدم كمكوّن في وصفة تصنيع.';
  }

  if ($blockReasons !== []) {
   return response()->json([
    'message' => 'لا يمكن حذف هذا الصنف لوجود ارتباطات في النظام.',
    'details' => $blockReasons,
   ], 422);
  }

  try {
   $category->delete();
  } catch (QueryException $e) {
   $sqlState = $e->errorInfo[0] ?? '';
   $isFkBlock = $sqlState === '23000'
    || str_contains($e->getMessage(), 'Integrity constraint violation')
    || str_contains($e->getMessage(), '1451');
   if ($isFkBlock) {
    Log::warning('category_delete_blocked_by_fk', [
     'category_id' => $id,
     'exception' => $e->getMessage(),
    ]);

    return response()->json([
     'message' => 'لا يمكن حذف هذا الصنف لوجود سجلات مرتبطة في قاعدة البيانات. احذف أو عدّل تلك السجلات ثم أعد المحاولة.',
     'details' => config('app.debug') ? [$e->getMessage()] : [],
    ], 422);
   }

   throw $e;
  }

  return response()->json(['message' => 'Category deleted successfully'], 200);
 }

 public function categoryLinks($id)
 {
  $category = Category::find($id);
  if (! $category) {
   return response()->json(['error' => 'Category not found'], 404);
  }

  $mergeService = app(CategoryMergeService::class);

  return response()->json([
   'category' => [
    'id' => (int) $category->id,
    'category_name' => $category->category_name,
    'item_code' => $category->item_code,
    'warehouse' => $category->warehouse,
    'stock_id' => $category->stock_id,
    'quantity' => (float) ($category->quantity ?? 0),
   ],
   'links' => $mergeService->countLinks((int) $id),
  ], 200);
 }

 public function mergeCategoryPreview(Request $request)
 {
  $request->validate([
   'source_id' => 'required|integer|exists:categories,id',
   'target_id' => 'required|integer|exists:categories,id',
  ]);

  $preview = app(CategoryMergeService::class)->preview(
   (int) $request->input('source_id'),
   (int) $request->input('target_id'),
  );

  $status = $preview['valid'] ? 200 : 422;

  return response()->json($preview, $status);
 }

 public function mergeCategory(Request $request)
 {
  $request->validate([
   'source_id' => 'required|integer|exists:categories,id',
   'target_id' => 'required|integer|exists:categories,id',
  ]);

  try {
   $result = app(CategoryMergeService::class)->merge(
    (int) $request->input('source_id'),
    (int) $request->input('target_id'),
   );
  } catch (\InvalidArgumentException $e) {
   return response()->json(['message' => $e->getMessage()], 422);
  }

  return response()->json([
   'message' => 'تم دمج الصنف بنجاح.',
   'result' => $result,
  ], 200);
 }

 public function duplicateCategoryGroups(Request $request)
 {
  $stockId = $request->query('stock_id');
  $groups = app(CategoryMergeService::class)->duplicateGroups(
   $stockId !== null ? (int) $stockId : null
  );

  return response()->json([
   'groups' => $groups,
   'count' => count($groups),
  ], 200);
 }

 public function mergeCategoriesBulk(Request $request)
 {
  $request->validate([
   'target_id' => 'required|integer|exists:categories,id',
   'source_ids' => 'required|array|min:1',
   'source_ids.*' => 'integer|exists:categories,id',
  ]);

  try {
   $result = app(CategoryMergeService::class)->mergeMany(
    (int) $request->input('target_id'),
    (array) $request->input('source_ids'),
   );
  } catch (\InvalidArgumentException $e) {
   return response()->json(['message' => $e->getMessage()], 422);
  }

  return response()->json([
   'message' => 'تم دمج الأصناف بنجاح.',
   'result' => $result,
  ], 200);
 }

 public function forceDeleteCategoriesPreview(Request $request)
 {
  $this->ensureAdmin();

  $request->validate([
   'category_ids' => 'nullable|array',
   'category_ids.*' => 'integer|exists:categories,id',
   'warehouse' => 'nullable|string|max:255',
  ]);

  $svc = app(CategoryForceDeletionService::class);
  $categoryIds = array_map('intval', (array) $request->input('category_ids', []));

  if ($request->filled('warehouse')) {
   $categoryIds = $svc->categoryIdsForWarehouse((string) $request->input('warehouse'));
  }

  $preview = $svc->preview($categoryIds);

  return response()->json($preview, 200);
 }

 public function forceDeleteCategoriesBulk(Request $request)
 {
  $this->ensureAdmin();

  $request->validate([
   'category_ids' => 'nullable|array',
   'category_ids.*' => 'integer|exists:categories,id',
   'warehouse' => 'nullable|string|max:255',
   'confirm_phrase' => 'required|string',
  ]);

  $warehouse = trim((string) $request->input('warehouse', ''));
  $expectedPhrase = $warehouse !== '' ? $warehouse : 'حذف نهائي';

  if (trim((string) $request->input('confirm_phrase')) !== $expectedPhrase) {
   return response()->json([
    'message' => $warehouse !== ''
     ? 'اكتب اسم المخزن بالضبط للتأكيد.'
     : 'اكتب «حذف نهائي» للتأكيد.',
   ], 422);
  }

  $svc = app(CategoryForceDeletionService::class);
  $categoryIds = array_map('intval', (array) $request->input('category_ids', []));

  if ($warehouse !== '') {
   $categoryIds = $svc->categoryIdsForWarehouse($warehouse);
  }

  try {
   $result = $svc->deleteMany($categoryIds);
  } catch (\InvalidArgumentException $e) {
   return response()->json(['message' => $e->getMessage()], 422);
  } catch (\Throwable $e) {
   Log::error('category_force_delete_failed', [
    'category_ids' => $categoryIds,
    'warehouse' => $warehouse,
    'exception' => $e->getMessage(),
   ]);

   return response()->json([
    'message' => 'تعذر تنفيذ الحذف: '.$e->getMessage(),
   ], 500);
  }

  return response()->json([
   'message' => 'تم حذف الأصناف وجميع ارتباطاتها.',
   'result' => $result,
  ], 200);
 }

 private function ensureAdmin(): void
 {
  if ((auth()->user()->department ?? '') !== 'Admin') {
   abort(403, 'متاح للمسؤول فقط.');
  }
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
   'initial_balance' => 'required|numeric|min:0',
   'minimum_quantity' => 'required|numeric|min:0',
   'warehouse' => 'required|string',
   'production_id' => 'required|numeric|exists:productions,id',
   'measurement_id' => 'required|numeric|exists:measurements,id',
   'category_image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:500',
   'item_code' => 'nullable|string|max:64|unique:categories,item_code',
   'color' => 'nullable|string|max:128',
   'item_classification_id' => 'nullable|integer|exists:item_classifications,id',
   'recipe_id' => 'nullable|integer|exists:recipes,id',
   'product_type' => 'nullable|string|in:raw_material,semi_finished,finished',
   'shipping_size_tier' => 'nullable|string|in:small,medium,large',
   'allow_wip_sale' => 'nullable|boolean',
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

  $stockId = $this->resolveStockIdForCategory($request);
  if (!$stockId) {
   return response()->json(['message' => 'تعذر تحديد المخزن.'], 422);
  }

  $cost = (float) request('category_price');
  $openQty = (float) request('initial_balance');
  $warehouse = request('warehouse');

  $attrs = [
   'category_name' => request('category_name'),
   'category_price' => request('category_price'),
   'unit_price' => $cost,
   'initial_balance' => request('initial_balance'),
   'minimum_quantity' => request('minimum_quantity'),
   'warehouse' => $warehouse,
   'production_id' => request('production_id'),
   'measurement_id' => request('measurement_id'),
   'category_image' => $img_name,
   'stock_id' => $stockId,
   'color' => $request->input('color'),
   'item_classification_id' => $request->filled('item_classification_id')
    ? (int) $request->input('item_classification_id')
    : null,
   'item_code' => $request->filled('item_code') ? trim((string) $request->input('item_code')) : null,
  ];

  if ($request->filled('recipe_id')) {
   $attrs['recipe_id'] = (int) $request->input('recipe_id');
  }

  if ($request->filled('product_type')) {
   $attrs['product_type'] = (string) $request->input('product_type');
  }
  if ($request->filled('shipping_size_tier')) {
   $attrs['shipping_size_tier'] = (string) $request->input('shipping_size_tier');
  }
  if ($request->has('allow_wip_sale')) {
   $attrs['allow_wip_sale'] = $request->boolean('allow_wip_sale');
  }

  $category = Category::create($attrs);

  if ($category->item_code === null || $category->item_code === '') {
   app(ItemCodeService::class)->ensureCode(Item::query()->findOrFail($category->id));
   $category->refresh();
  }

  if ($openQty > 0.0000001) {
   /** @var InventoryMovementLedgerService $ledger */
   $ledger = app(InventoryMovementLedgerService::class);
   $ledger->recordInbound(
    $category->fresh(),
    InventoryMovementType::OpeningBalance,
    $openQty,
    $cost,
    $openQty * $cost,
    true,
    'category_opening',
    (int) $category->id,
    'رصيد افتتاحي — إنشاء صنف',
    null,
    auth()->check() ? auth()->user()->name : 'النظام'
   );

   DB::table('categories_balance')->insert([
    'invoice_number' => 'OB',
    'category_id' => $category->id,
    'type' => 'رصيد افتتاحي',
    'quantity' => $openQty,
    'balance_before' => 0,
    'balance_after' => $openQty,
    'price' => $cost,
    'total_price' => $cost * $openQty,
    'unit_cost' => $cost,
    'cost_total' => $cost * $openQty,
    'by' => auth()->check() ? auth()->user()->name : 'النظام',
    'created_at' => now(),
   ]);
   if ($warehouse !== 'مخزن منتج تام') {
    CategoryInventoryCostService::syncUnitPriceFromWeightedAverage((int) $category->id);
   }
   $openingValue = $openQty * $cost;
   if ($openingValue > 0.00001) {
    $invAcc = TreeAccount::resolveInventoryAccountForCategoryId((int) $category->id);
    app(InventoryGlPostingService::class)->postOpeningInventory(
     $openingValue,
     'رصيد افتتاحي — ' . $category->category_name . ' (صنف #' . $category->id . ')',
     auth()->id(),
     $invAcc
    );
   }
  }

  return response()->json($category, 201);
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
   'category_image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:500',
   'item_code' => 'nullable|string|max:64|unique:categories,item_code,'.$id,
   'color' => 'nullable|string|max:128',
   'item_classification_id' => 'nullable|integer|exists:item_classifications,id',
   'recipe_id' => 'nullable|integer|exists:recipes,id',
   'product_type' => 'nullable|string|in:raw_material,semi_finished,finished',
   'shipping_size_tier' => 'nullable|string|in:small,medium,large',
   'allow_wip_sale' => 'nullable|boolean',

  ]);

  $stockId = $this->resolveStockIdForCategory($request);
  if (!$stockId) {
   return response()->json(['message' => 'تعذر تحديد المخزن.'], 422);
  }

  $category = Category::find($id);

  if (!$category) {
   return response()->json(['error' => 'Category not found'], 404);
  }

  $incomingName = trim((string) $request->category_name);
  $incomingWarehouse = trim((string) $request->warehouse);
  $nameOrWarehouseChanged = $incomingName !== trim((string) $category->category_name)
   || $incomingWarehouse !== trim((string) $category->warehouse);

  if ($nameOrWarehouseChanged) {
   $dupQuery = Category::query()
    ->whereRaw('TRIM(category_name) = ?', [$incomingName])
    ->where('id', '!=', (int) $id);

   if ($stockId) {
    $dupQuery->where('stock_id', $stockId);
   } else {
    $dupQuery->where('warehouse', $incomingWarehouse);
   }

   if ($dupQuery->first()) {
    return response()->json(['message' => 'هذا الصنف موجود بالفعل'], 422);
   }
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

  $update = [
   'category_name' => $request->input('category_name'),
   'category_price' => $request->input('category_price'),
   'initial_balance' => $request->input('initial_balance'),
   'minimum_quantity' => $request->input('minimum_quantity'),
   'warehouse' => $request->input('warehouse'),
   'production_id' => $request->input('production_id'),
   'measurement_id' => $request->input('measurement_id'),
   'stock_id' => $stockId,
   'color' => $request->input('color'),
  ];

  if ($request->has('item_classification_id')) {
   $raw = $request->input('item_classification_id');
   $update['item_classification_id'] = ($raw !== null && $raw !== '')
    ? (int) $raw
    : null;
  }

  if ($img_name !== '') {
   $update['category_image'] = $img_name;
  }

  if ($request->has('item_code')) {
   $update['item_code'] = $request->filled('item_code') ? trim((string) $request->input('item_code')) : null;
  }

  if ($request->has('recipe_id')) {
   $update['recipe_id'] = $request->filled('recipe_id') ? (int) $request->input('recipe_id') : null;
  }

  if ($request->has('product_type')) {
   $update['product_type'] = $request->filled('product_type') ? (string) $request->input('product_type') : null;
  }
  if ($request->has('shipping_size_tier')) {
   $update['shipping_size_tier'] = $request->filled('shipping_size_tier') ? (string) $request->input('shipping_size_tier') : null;
  }
  if ($request->has('allow_wip_sale')) {
   $update['allow_wip_sale'] = $request->boolean('allow_wip_sale');
  }

  $category->update($update);

  if (($category->item_code === null || $category->item_code === '') && $request->has('item_code')) {
   app(ItemCodeService::class)->ensureCode(Item::query()->findOrFail($category->id));
   $category->refresh();
  }


  return response()->json($category, 200);
 }

 /**
  * ترقية صنف من تحت التشغيل (WIP) إلى منتج تام مع حركة مخزون وقيد محاسبي.
  */
 public function promoteToFinished(int $id)
 {
  try {
   $result = app(\App\Services\Manufacturing\WipToFinishedPromotionService::class)
    ->promote($id, auth()->id());

   $cat = $result['category'];

   return response()->json([
    'success' => true,
    'message' => 'تم ترقية الصنف إلى منتج تام بنجاح',
    'category' => $cat,
    'daily_entry_id' => $result['daily_entry_id'],
   ]);
  } catch (\RuntimeException $e) {
   return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
  } catch (\Throwable $e) {
   Log::error('promoteToFinished failed', ['id' => $id, 'error' => $e->getMessage()]);
   return response()->json(['success' => false, 'message' => 'حدث خطأ أثناء ترقية الصنف.'], 500);
  }
 }

 /**
  * يعتمد على stock_id المرسل إن وُجد، أو اسم المخزن، أو ينشئ صفاً في stocks للمخازن الخمسة المعتمدة.
  */
 private function resolveStockIdForCategory(Request $request): ?int
 {
  $inputId = $request->input('stock_id');
  if ($inputId !== null && $inputId !== '' && $inputId !== '0') {
   $id = (int) $inputId;
   if (Stock::where('id', $id)->exists()) {
    return $id;
   }
  }
  $warehouse = trim((string) $request->input('warehouse', ''));
  if ($warehouse === '') {
   return null;
  }
  $existing = Stock::where('name', $warehouse)->first();
  if ($existing) {
   return (int) $existing->id;
  }

  return $this->ensureStockRowForStandardWarehouse($warehouse);
 }

 /**
  * @return int|null رقم السجل أو null إن لم يكن اسماً معتمداً
  */
 private function ensureStockRowForStandardWarehouse(string $warehouse): ?int
 {
  $allowed = [
   'مخزن مواد خام',
   'مخزن منتج تحت التشغيل',
   'مخزن منتج تام',
   'مخزن صيانة',
   'مخزن تالف',
  ];
  if (!in_array($warehouse, $allowed, true)) {
   return null;
  }
  $assetId = Stock::query()->value('asset_id');
  if ($assetId === null) {
   $assetId = TreeAccount::query()->min('id');
  }
  if ($assetId === null) {
   $assetId = 1;
  }
  $row = Stock::firstOrCreate(
   ['name' => $warehouse],
   [
    'balance' => 0,
    'asset_id' => (int) $assetId,
    'active' => true,
   ]
  );

  return (int) $row->id;
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
  if ($request->has('category_name') && $request->category_name !== '') {
   $term = trim((string) $request->category_name);
   $likeTerm = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term).'%';
   $search->where(function ($q) use ($likeTerm) {
    $q->where('category_name', 'like', $likeTerm)
     ->orWhere('item_code', 'like', $likeTerm);
   });
  }
  if ($request->has('production_id')) {
   $search->where('production_id', $request->production_id);
  }
  if ($request->has('item_classification_id')) {
   $search->where('item_classification_id', $request->item_classification_id);
  }
  if ($request->has('warehouse')) {
   $search->where('warehouse', 'like', '%' . $request->warehouse . '%');
  }
  $search->orderBy('category_name', 'asc');

  $search = $search->with('production', 'measurement', 'itemClassification')->paginate($itemsPerPage);
  return response()->json($search, 200);
 }


 public function catName()
 {
  $catName = Category::with('measurement:id,unit')->select('category_name', 'category_price', 'measurement_id')->get();

  return response()->json($catName, 200);
 }

public function changeCategoryQuantityss(Request $request)
{
 $id = (int) $request->route('id', $request->id);
 $quantity = (float) $request->quantity;

 $category = Category::find($id);
 if (!$category) {
  return response()->json(['success' => false, 'message' => 'الصنف غير موجود']);
 }

 DB::beginTransaction();
 try {
  /** @var InventoryMovementLedgerService $ledger */
  $ledger = app(InventoryMovementLedgerService::class);
  $before = (float) $category->quantity;
  $delta = $quantity - $before;
  if (abs($delta) < 0.0000001) {
   DB::commit();

   return response()->json([
    'success' => true,
    'quantity' => $category->quantity,
    'total_price' => $category->total_price,
    'sell_total_price' => $category->sell_total_price,
    'unit_price' => $category->unit_price,
   ]);
  }

  $unitRef = CategoryInventoryCostService::averageValuationPerUnitForManualAdjustment($id);
  if ($unitRef < 0.0000001) {
   $unitRef = (float) $category->category_price;
   $det = DB::table('categories_balance')->where('category_id', $id)->latest()->first();
   if ($det && isset($det->price) && (float) $det->price > 0.0000001) {
    $unitRef = (float) $det->price;
   }
  }

  if ($delta > 0) {
   $ledger->recordInbound(
    $category,
    InventoryMovementType::ManualAdjustment,
    $delta,
    $unitRef,
    abs($delta) * $unitRef,
    true,
    'manual_quantity_set',
    (int) $category->id,
    'تعديل كمية (تعيين)',
    null,
    auth()->check() ? auth()->user()->name : null
   );
  } else {
   $ledger->recordOutbound(
    $category,
    InventoryMovementType::ManualAdjustment,
    abs($delta),
    $unitRef,
    abs($delta) * $unitRef,
    true,
    'manual_quantity_set',
    (int) $category->id,
    'تعديل كمية (تعيين)',
    null,
    auth()->check() ? auth()->user()->name : null
   );
  }

  $glAmount = abs($delta) * $unitRef;
  if ($glAmount > 0.00001) {
   $invAcc = TreeAccount::resolveInventoryAccountForCategoryId($id);
   if ($invAcc) {
    $gl = app(InventoryGlPostingService::class);
    $glLabel = 'تعديل كمية يدوي — ' . ($category->fresh()->category_name ?? ('صنف #' . $id));
    if ($delta > 0) {
     $gl->postPhysicalCountGain($glAmount, $invAcc, $glLabel, auth()->id());
    } else {
     $gl->postPhysicalCountLoss($glAmount, $invAcc, $glLabel, auth()->id());
    }
   }
  }

  DB::commit();

  $fresh = $category->fresh();

  return response()->json([
   'success' => true,
   'quantity' => $fresh->quantity,
   'total_price' => $fresh->total_price,
   'sell_total_price' => $fresh->sell_total_price,
   'unit_price' => $fresh->unit_price,
  ]);
 } catch (\Throwable $e) {
  DB::rollBack();

  return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
 }
}

 /**
  * تعيين متوسط تكلفة الوحدة يدوياً (بدون تغيير الكمية): تحديث قيمة المخزون بالتكلفة + قيد على حساب المخزن + حركة تقييم.
  */
 public function changeCategoryAverageUnitCost(Request $request)
 {
  $id = (int) $request->route('id');
  $request->validate([
   'average_unit_cost' => 'required|numeric|min:0',
  ]);

  $category = Category::find($id);
  if (! $category) {
   return response()->json(['success' => false, 'message' => 'الصنف غير موجود'], 404);
  }

  /** @var InventoryMovementLedgerService $ledger */
  $ledger = app(InventoryMovementLedgerService::class);
  $avgInput = (float) $request->input('average_unit_cost');

  DB::beginTransaction();
  try {
   $balanceBefore = (float) ($category->quantity ?? 0);

   $result = $ledger->applyPureCostRevaluation(
    $category,
    $avgInput,
    'manual_average_cost',
    $id,
    'تعديل متوسط تكلفة الوحدة',
    auth()->check() ? auth()->user()->name : null
   );

   $delta = (float) $result['value_delta'];
   /** @var Category $fresh */
   $fresh = $result['category'];

   if (abs($delta) > 0.00001 && $balanceBefore > 0.0000001) {
    DB::table('categories_balance')->insert([
     'invoice_number' => 'AVGC',
     'category_id' => $id,
     'type' => 'تعديل متوسط التكلفة',
     'quantity' => 0,
     'balance_before' => $balanceBefore,
     'balance_after' => (float) ($fresh->quantity ?? 0),
     'price' => $avgInput,
     'total_price' => $delta,
     'unit_cost' => $avgInput,
     'cost_total' => $delta,
     'by' => auth()->check() ? auth()->user()->name : 'system',
     'created_at' => now(),
    ]);
   }

   $glAmount = abs($delta);
   if ($glAmount > 0.00001) {
    $invAcc = TreeAccount::resolveInventoryAccountForCategoryId($id);
    if ($invAcc) {
     $gl = app(InventoryGlPostingService::class);
     $glLabel = 'تعديل متوسط تكلفة — ' . ($fresh->category_name ?? ('صنف #' . $id));
     if ($delta > 0) {
      $gl->postPhysicalCountGain($glAmount, $invAcc, $glLabel, auth()->id());
     } else {
      $gl->postPhysicalCountLoss($glAmount, $invAcc, $glLabel, auth()->id());
     }
    }
   }

   DB::commit();

   return response()->json([
    'success' => true,
    'quantity' => $fresh->quantity,
    'total_price' => $fresh->total_price,
    'sell_total_price' => $fresh->sell_total_price,
    'unit_price' => $fresh->unit_price,
    'value_delta' => $delta,
   ]);
  } catch (\Throwable $e) {
   DB::rollBack();

   return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
  }
 }


 /**
  * معاينة فروقات تسوية المخزون في الحسابات (بدون ترحيل).
  */
 public function previewInventoryGlSyncFromCategories()
 {
  /** @var InventoryAccountsTrueUpService $svc */
  $svc = app(InventoryAccountsTrueUpService::class);

  return response()->json($svc->preview(), 200);
 }

 /**
  * ترحيل قيد يومية لمطابقة أرصدة حسابات المخزون مع مجموع تكلفة الأصناف في المخازن.
  */
 public function syncInventoryAccountsFromActualCosts()
 {
  /** @var InventoryAccountsTrueUpService $svc */
  $svc = app(InventoryAccountsTrueUpService::class);

  try {
   return response()->json($svc->execute(auth()->id()), 200);
  } catch (\Throwable $e) {
   return response()->json([
    'success' => false,
    'posted' => false,
    'message' => $e->getMessage(),
   ], 422);
  }
 }


 public function changeCategoryQuantity(Request $request)
{
 $quantity = $request->quantity;
 $category = Category::find($request->id);
 if (! $category) {
  return response()->json(['message' => 'الصنف غير موجود'], 404);
 }

 /** @var InventoryMovementLedgerService $ledger */
 $ledger = app(InventoryMovementLedgerService::class);
 $categorPrice = $category->category_price;
 $ref = null;
 if ($request->status == 'add' && $quantity > 0) {
  $ref = 'CH+';
 }
 if ($request->status == 'add' && $quantity < 0) {
  $ref = 'CH-';
 }
 if ($request->status == 'add' && $quantity !== 0) {
  $cat_details = DB::table('categories_balance')->where('category_id', $request->id)->latest()->first();
  if ($cat_details) {
   $categorPrice = $cat_details->price;
  }
  $balanceBefore = (float) $category->quantity;
  $qd = (float) $quantity;

  $unitRef = CategoryInventoryCostService::averageValuationPerUnitForManualAdjustment((int) $request->id);
  if ($unitRef < 0.0000001) {
   $unitRef = (float) $categorPrice;
  }

  DB::beginTransaction();
  try {
   if ($qd > 0) {
    $ledger->recordInbound(
     $category,
     InventoryMovementType::ManualAdjustment,
     abs($qd),
     (float) $unitRef,
     abs($qd) * (float) $unitRef,
     true,
     'manual_adjustment',
     (int) $category->id,
     'تعديل الصنف — إضافة/خصم',
     null,
     auth()->user()->name
    );
   } elseif ($qd < 0) {
    $ledger->recordOutbound(
     $category,
     InventoryMovementType::ManualAdjustment,
     abs($qd),
     (float) $unitRef,
     abs($qd) * (float) $unitRef,
     true,
     'manual_adjustment',
     (int) $category->id,
     'تعديل الصنف — إضافة/خصم',
     null,
     auth()->user()->name
    );
   }

   $glAmount = abs($qd) * (float) $unitRef;
   if ($glAmount > 0.00001) {
    $invAcc = TreeAccount::resolveInventoryAccountForCategoryId((int) $request->id);
    if ($invAcc) {
     $gl = app(InventoryGlPostingService::class);
     $glLabel = 'تعديل كمية (+/-) — ' . ($category->fresh()->category_name ?? ('صنف #' . $request->id));
     if ($qd > 0) {
      $gl->postPhysicalCountGain($glAmount, $invAcc, $glLabel, auth()->id());
     } else {
      $gl->postPhysicalCountLoss($glAmount, $invAcc, $glLabel, auth()->id());
     }
    }
   }

   $category->refresh();
   DB::table('categories_balance')->insert([
    'invoice_number' => $ref,
    'category_id' => $request->id,
    'type' => 'تعديل الصنف',
    'quantity' => $quantity,
    'balance_before' => $balanceBefore,
    'balance_after' => (float) $category->quantity,
    'price' => $unitRef,
    'total_price' => $unitRef * $quantity,
    'unit_cost' => $unitRef,
    'cost_total' => $unitRef * $quantity,
    'by' => auth()->user()->name,
    'created_at' => now(),
   ]);
   if ($category->warehouse !== 'مخزن منتج تام') {
    CategoryInventoryCostService::syncUnitPriceFromWeightedAverage((int) $category->id);
   }
   DB::commit();
  } catch (\Throwable $e) {
   DB::rollBack();

   return response()->json(['message' => $e->getMessage()], 422);
  }

  return response()->json('success', 200);
 }

 if ($request->status == 'edit' && is_numeric($quantity) && (float) $quantity >= 0) {
  $cat_details = DB::table('categories_balance')->where('category_id', $request->id)->latest()->first();
  if ($cat_details) {
   $categorPrice = $cat_details->price;
  }
  $ref = 'CHQ';
  $balanceBefore = (float) $category->quantity;
  $targetQty = (float) $quantity;
  $delta = $targetQty - $balanceBefore;

  $unitRef = CategoryInventoryCostService::averageValuationPerUnitForManualAdjustment((int) $request->id);
  if ($unitRef < 0.0000001) {
   $unitRef = (float) $categorPrice;
  }

  DB::beginTransaction();
  try {
   if (abs($delta) > 0.0000001) {
    if ($delta > 0) {
     $ledger->recordInbound(
      $category,
      InventoryMovementType::ManualAdjustment,
      $delta,
      (float) $unitRef,
      abs($delta) * (float) $unitRef,
      true,
      'manual_adjustment_edit',
      (int) $category->id,
      'تعديل الصنف — تعيين كمية',
      null,
      auth()->user()->name
     );
    } else {
     $ledger->recordOutbound(
      $category,
      InventoryMovementType::ManualAdjustment,
      abs($delta),
      (float) $unitRef,
      abs($delta) * (float) $unitRef,
      true,
      'manual_adjustment_edit',
      (int) $category->id,
      'تعديل الصنف — تعيين كمية',
      null,
      auth()->user()->name
     );
    }

    $glAmount = abs($delta) * (float) $unitRef;
    if ($glAmount > 0.00001) {
     $invAcc = TreeAccount::resolveInventoryAccountForCategoryId((int) $request->id);
     if ($invAcc) {
      $gl = app(InventoryGlPostingService::class);
      $glLabel = 'تعيين كمية (مخازن) — ' . ($category->fresh()->category_name ?? ('صنف #' . $request->id));
      if ($delta > 0) {
       $gl->postPhysicalCountGain($glAmount, $invAcc, $glLabel, auth()->id());
      } else {
       $gl->postPhysicalCountLoss($glAmount, $invAcc, $glLabel, auth()->id());
      }
     }
    }
   }

   $category->refresh();
   DB::table('categories_balance')->insert([
    'invoice_number' => $ref,
    'category_id' => $request->id,
    'type' => 'تعديل الصنف',
    'quantity' => $targetQty - $balanceBefore,
    'balance_before' => $balanceBefore,
    'balance_after' => (float) $category->quantity,
    'price' => $unitRef,
    'total_price' => $unitRef * ($targetQty - $balanceBefore),
    'unit_cost' => $unitRef,
    'cost_total' => $unitRef * ($targetQty - $balanceBefore),
    'by' => auth()->user()->name,
    'created_at' => now(),
   ]);
   if ($category->warehouse !== 'مخزن منتج تام') {
    CategoryInventoryCostService::syncUnitPriceFromWeightedAverage((int) $category->id);
   }
   DB::commit();
  } catch (\Throwable $e) {
   DB::rollBack();

   return response()->json(['message' => $e->getMessage()], 422);
  }

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


 /**
  * تقرير مخزون مجمّع لكل صنف ضمن فترة (رصيد افتتاحي/ختامي + حركة وارد/صادر + قيمة).
  */
 public function warehouseInventoryReport(Request $request)
 {
  $request->validate([
   'warehouse' => 'required|string',
   'date_from' => 'nullable|date',
   'date_to' => 'nullable|date|after_or_equal:date_from',
   'sort' => 'nullable|string|in:quantity,total_value,sell_value,category_name,period_in_qty,period_out_qty,period_net_qty',
   'search' => 'nullable|string|max:200',
   'itemsPerPage' => 'nullable|integer|min:1|max:500',
  ]);

  $itemsPerPage = (int) $request->input('itemsPerPage', 15);
  $warehouse = (string) $request->warehouse;
  $isFinished = $warehouse === 'مخزن منتج تام';
  $dateFrom = $request->filled('date_from') ? $request->date_from.' 00:00:00' : null;
  $dateTo = $request->filled('date_to') ? $request->date_to.' 23:59:59' : null;
  $search = $request->filled('search') ? trim((string) $request->search) : null;

  $closingExpr = 'COALESCE(closing.closing_qty, c.quantity, 0)';
  $openingExpr = 'COALESCE(opening.opening_qty, 0)';
  $costValueExpr = "CASE WHEN c.quantity > 0.0000001 THEN ({$closingExpr} / c.quantity) * c.total_price ELSE ({$closingExpr}) * COALESCE(c.unit_price, 0) END";
  $sellValueExpr = "({$closingExpr}) * COALESCE(c.category_price, 0)";
  $unitCostExpr = "CASE WHEN c.quantity > 0.0000001 THEN c.total_price / c.quantity ELSE COALESCE(c.unit_price, 0) END";

  $baseQuery = $this->warehouseInventoryReportBaseQuery($warehouse, $dateFrom, $dateTo, $search);

  $totalsRow = (clone $baseQuery)->selectRaw("
    COUNT(*) as items_count,
    SUM({$closingExpr}) as total_quantity,
    SUM({$costValueExpr}) as total_value,
    SUM({$sellValueExpr}) as total_sell_value,
    SUM(COALESCE(period.period_in_qty, 0)) as period_in_total,
    SUM(COALESCE(period.period_out_qty, 0)) as period_out_total
  ")->first();

  $query = (clone $baseQuery)->select(
    'c.id as category_id',
    'c.category_name',
    'c.category_image',
    DB::raw("{$openingExpr} as opening_qty"),
    DB::raw("{$closingExpr} as quantity"),
    DB::raw('COALESCE(period.period_in_qty, 0) as period_in_qty'),
    DB::raw('COALESCE(period.period_out_qty, 0) as period_out_qty'),
    DB::raw('COALESCE(period.period_net_qty, 0) as period_net_qty'),
    DB::raw("ROUND({$unitCostExpr}, 4) as unit_cost"),
    DB::raw("ROUND({$costValueExpr}, 2) as total_value"),
    DB::raw("ROUND({$sellValueExpr}, 2) as sell_value"),
    DB::raw('COALESCE(m.unit, "-") as measurement_unit')
   );

  $sort = (string) $request->input('sort', 'total_value');
  $sortMap = [
   'quantity' => DB::raw($closingExpr),
   'total_value' => DB::raw($costValueExpr),
   'sell_value' => DB::raw($sellValueExpr),
   'category_name' => 'c.category_name',
   'period_in_qty' => DB::raw('COALESCE(period.period_in_qty, 0)'),
   'period_out_qty' => DB::raw('COALESCE(period.period_out_qty, 0)'),
   'period_net_qty' => DB::raw('COALESCE(period.period_net_qty, 0)'),
  ];
  $sortCol = $sortMap[$sort] ?? $sortMap['total_value'];
  $direction = $sort === 'category_name' ? 'asc' : 'desc';

  $paginated = $query->orderBy($sortCol, $direction)->paginate($itemsPerPage);

  $payload = $paginated->toArray();
  $payload['totals'] = [
   'items_count' => (int) ($totalsRow->items_count ?? 0),
   'total_quantity' => round((float) ($totalsRow->total_quantity ?? 0), 2),
   'total_value' => round((float) ($totalsRow->total_value ?? 0), 2),
   'total_sell_value' => round((float) ($totalsRow->total_sell_value ?? 0), 2),
   'period_in_total' => round((float) ($totalsRow->period_in_total ?? 0), 2),
   'period_out_total' => round((float) ($totalsRow->period_out_total ?? 0), 2),
   'is_finished_warehouse' => $isFinished,
   'warehouse' => $warehouse,
   'date_from' => $request->input('date_from'),
   'date_to' => $request->input('date_to'),
  ];

  return response()->json($payload, 200);
 }

 /**
  * @return \Illuminate\Database\Query\Builder
  */
 private function warehouseInventoryReportBaseQuery(string $warehouse, ?string $dateFrom, ?string $dateTo, ?string $search)
 {
  $closingSub = DB::table('categories_balance as cb_close')
   ->select('cb_close.category_id', 'cb_close.balance_after as closing_qty')
   ->joinSub(
    DB::table('categories_balance')
     ->select('category_id', DB::raw('MAX(id) as max_id'))
     ->when($dateTo, fn ($q) => $q->where('created_at', '<=', $dateTo))
     ->groupBy('category_id'),
    'latest_close',
    fn ($join) => $join->on('cb_close.id', '=', 'latest_close.max_id')
   );

  $openingSub = DB::table('categories_balance as cb_open')
   ->select('cb_open.category_id', 'cb_open.balance_after as opening_qty')
   ->joinSub(
    DB::table('categories_balance')
     ->select('category_id', DB::raw('MAX(id) as max_id'))
     ->when($dateFrom, fn ($q) => $q->where('created_at', '<', $dateFrom))
     ->groupBy('category_id'),
    'latest_open',
    fn ($join) => $join->on('cb_open.id', '=', 'latest_open.max_id')
   );

  $periodSub = DB::table('categories_balance')
   ->select(
    'category_id',
    DB::raw('SUM(CASE WHEN quantity > 0 THEN quantity ELSE 0 END) as period_in_qty'),
    DB::raw('SUM(CASE WHEN quantity < 0 THEN ABS(quantity) ELSE 0 END) as period_out_qty'),
    DB::raw('SUM(quantity) as period_net_qty')
   )
   ->when($dateFrom && $dateTo, fn ($q) => $q->whereBetween('created_at', [$dateFrom, $dateTo]))
   ->groupBy('category_id');

  $query = DB::table('categories as c')
   ->leftJoinSub($closingSub, 'closing', 'closing.category_id', '=', 'c.id')
   ->leftJoinSub($openingSub, 'opening', 'opening.category_id', '=', 'c.id')
   ->leftJoinSub($periodSub, 'period', 'period.category_id', '=', 'c.id')
   ->leftJoin('measurements as m', 'c.measurement_id', '=', 'm.id')
   ->where('c.warehouse', $warehouse);

  if ($search !== null && $search !== '') {
   $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search).'%';
   $query->where('c.category_name', 'like', $like);
  }

  return $query;
 }

 public function warehouseDetails(Request $request)
 {
  $itemsPerPage = $request->input('itemsPerPage', 15);

  $warehouse = $this->categoryBalanceMovementsQuery()
   ->join('categories', 'cb.category_id', '=', 'categories.id')
   ->leftJoin('measurements', 'categories.measurement_id', '=', 'measurements.id')
   ->where('categories.warehouse', $request->warehouse)
   ->when($request->has('date_from') && $request->has('date_to'), function ($query) use ($request) {
    return $query->whereBetween('cb.created_at', [$request->date_from . ' 00:00:00', $request->date_to . ' 23:59:59']);
   })
   ->when($request->has('date') && !$request->has('date_from'), function ($query) use ($request) {
    return $query->whereDate('cb.created_at', $request->date);
   })
   ->addSelect('categories.category_name', 'categories.warehouse', DB::raw('COALESCE(measurements.unit, "-") as measurement_unit'))
   ->orderBy('cb.id', 'desc')
   ->paginate($itemsPerPage);

  return response()->json($warehouse, 200);
 }

 public function categories_details($id, Request $request)
 {
  $itemsPerPage = $request->input('itemsPerPage', 15);
  $name = Category::findOrFail($id)->category_name;

  $cat_details = $this->categoryBalanceMovementsQuery()
   ->where('cb.category_id', $id);
  if ($request->has('ref')) {
   $cat_details->where('cb.ref', 'like', '%' . $request->ref . '%');
  }
  $cat_details = $cat_details->orderBy('cb.created_at', 'desc')->paginate($itemsPerPage);

  return response()->json(['name' => $name, 'details' => $cat_details], 200);
 }

 /**
  * Base query for categories_balance with movement date and counterparty (supplier / customer / return).
  */
 private function categoryBalanceMovementsQuery()
 {
  $partyNameSql = <<<'SQL'
CASE
 WHEN cb.type IN ('فواتير مشتريات', 'تعديل فواتير مشتريات', 'حذف فواتير مشتريات') THEN (
  SELECT s.supplier_name FROM purchases p
  INNER JOIN suppliers s ON s.id = p.supplier_id
  WHERE p.invoice_number = cb.invoice_number
  LIMIT 1
 )
 WHEN cb.type = 'رفض استلام طلب' THEN 'مرتجع'
 WHEN cb.type IN ('شحن طلب', 'تاجيل طلب') THEN (
  SELECT o.customer_name FROM orders o WHERE o.id = cb.invoice_number LIMIT 1
 )
 WHEN cb.type = 'صيانة' THEN (
  SELECT o.customer_name FROM orders o WHERE o.id = cb.ref LIMIT 1
 )
 WHEN cb.type = 'تصنيع' THEN 'تصنيع'
 ELSE NULL
END
SQL;

  return DB::table('categories_balance as cb')
   ->select('cb.*', DB::raw('cb.created_at as movement_date'), DB::raw("({$partyNameSql}) as party_name"));
 }

 public function warehouse_balance()
 {
  $warehouseMappings = [
   'مخزن مواد خام' => 'Raw',
   'مخزن منتج تحت التشغيل' => 'In_Process',
   'مخزن منتج تام' => 'Finished',
   'مخزن صيانة' => 'Maintenance',
   'مخزن تالف' => 'Defective',
  ];

  $warehouseBalances = [];
  $quantityTotals = [];

  foreach (array_keys($warehouseMappings) as $warehouse) {
   if ($warehouse == 'مخزن منتج تام') {
    $totalPrice = Category::where('warehouse', $warehouse)->sum('sell_total_price');
   } else {
    $totalPrice = Category::where('warehouse', $warehouse)->sum('total_price');
   }
   $qtySum = (float) Category::where('warehouse', $warehouse)->sum('quantity');
   $englishWarehouseName = $warehouseMappings[$warehouse];
   $warehouseBalances[$englishWarehouseName] = $totalPrice;
   $quantityTotals[$englishWarehouseName] = $qtySum;
  }

  return response()->json([
   ...$warehouseBalances,
   'quantity_totals' => $quantityTotals,
  ], 200);
 }

 public function categories_for_orders()
 {
  $data = Category::where('warehouse', 'مخزن منتج تام')
   ->select('id', 'category_name', 'category_price', 'category_image', 'item_code')
   ->orderBy('category_name')
   ->get();
  return response()->json($data, 200);
 }

 /**
  * إغلاق جرد شهري: لقطة من رصيد المخزون الفعلي (categories.quantity) وتقييم متسق مع حركات الإخراج/الشحن (متوسط التكلفة المرجح).
  * يدعم ?month=YYYY-MM (الشهر المُغلَق)، وإلا يُغلق الشهر السابق تقويمياً.
  * يستخدم updateOrCreate حتى يمكن إعادة تسجيل نفس الشهر بعد تصحيح الجرد دون خطأ تكرار.
  */
 public function monthlyInventory(Request $request)
 {
  $request->validate([
   'warehouse' => 'required|string',
   'month' => 'nullable|date_format:Y-m',
  ]);

  $month = $request->filled('month')
   ? Carbon::createFromFormat('Y-m', (string) $request->input('month'))->format('Y-m')
   : Carbon::now()->subMonth()->format('Y-m');

  $categories = Category::where('warehouse', $request->warehouse)->get();

  $closedBy = auth()->user()?->name ?? 'system';

  DB::transaction(function () use ($categories, $month, $closedBy) {
   foreach ($categories as $category) {
    $qty = max(0, (float) ($category->quantity ?? 0));
    $cid = (int) $category->id;

    // قيمة المخزون بالتكلفة: نفس منطق إخراج الصنف للشحن/COGS (متوسط مرجح من categories.total_price ÷ quantity)
    $avgCost = CategoryInventoryCostService::averageCostForCategoryIssue($cid);
    $inventoryAtCost = round($qty * $avgCost, 2);

    // مخزن منتج تام: إجمالي سعر البيع المرجّح = كمية × سعر البيع للوحدة
    $isFinished = $category->warehouse === 'مخزن منتج تام';
    $unitSell = (float) ($category->category_price ?? 0);
    $sellTotal = $isFinished
     ? round($qty * $unitSell, 2)
     : round((float) ($category->sell_total_price ?? 0), 2);

    CategoryMonthlyInventory::updateOrCreate(
     [
      'category_id' => $cid,
      'month' => $month,
     ],
     [
      'quantity' => $qty,
      'total_price' => $inventoryAtCost,
      'sell_total_price' => $sellTotal,
      'by' => $closedBy,
     ]
    );
   }
  });

  return response()->json([
   'success' => true,
   'month' => $month,
   'warehouse' => $request->warehouse,
   'lines' => $categories->count(),
  ], 200);
 }


 public function categoriesSellReports(Request $request, ProductPerformanceReportService $productPerformanceReportService)
 {
  $itemsPerPage = $request->input('itemsPerPage', 15);

  $matchingCategoryIds = null;
  $searchRaw = $request->input('search');
  if ($searchRaw !== null && $searchRaw !== '') {
   $searchTerm = trim((string) $searchRaw);
   if ($searchTerm !== '') {
    $likeTerm = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $searchTerm).'%';
    $matchingCategoryIds = DB::table('categories')
     ->where('category_name', 'like', $likeTerm)
     ->pluck('id')
     ->map(fn ($id) => (int) $id)
     ->all();
   }
  }

  $query = DB::table('order_products')
   ->select(
    'categories.id as category_id',
    'categories.category_name as category_name',
    'categories.category_image as category_image',
    'categories.quantity as warehouse_balance',
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

  if (is_array($matchingCategoryIds)) {
   $query->whereIn('categories.id', $matchingCategoryIds);
  }

  $categorySales = $query->groupBy('categories.id', 'categories.category_name', 'categories.category_image', 'categories.quantity');

  if ($request->has('sort')) {
   if ($request->sort == 'category_name' || $request->sort == 'total_quantity_return' || $request->sort == 'total_postpone') {
    $query->orderBy($request->sort);
   } else {
    $query->orderByDesc($request->sort);
   }
  }
  $categorySales = $query->paginate($itemsPerPage);

  $profitabilityTotals = null;
  $byCategoryId = [];
  if ($request->filled('date_from') && $request->filled('date_to')) {
   $period = $productPerformanceReportService->computeForPeriod($request->date_from, $request->date_to);
   $profitabilityTotals = $period['totals'];
   $byCategoryId = $period['by_category_id'];
   if (is_array($matchingCategoryIds)) {
    $byCategoryId = array_intersect_key($byCategoryId, array_flip($matchingCategoryIds));
    $profitabilityTotals = $this->aggregateProfitabilityTotalsFromRows(array_values($byCategoryId));
   }
  }

  foreach ($categorySales->items() as $item) {
   $pid = (int) $item->category_id;
   $p = $byCategoryId[$pid] ?? null;
   $item->net_sales = $p['net_sales'] ?? null;
   $item->avg_unit_cost = $p['avg_unit_cost'] ?? null;
   $item->ref_unit_cost = $p['ref_unit_cost'] ?? null;
   $item->cogs = $p['cogs'] ?? null;
   $item->gross_profit = $p['gross_profit'] ?? null;
   $item->gross_margin_percent = $p['gross_margin_percent'] ?? null;
  }

  $payload = $categorySales->toArray();
  $payload['profitability_totals'] = $profitabilityTotals;

  return response()->json($payload, 200);
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

 /**
  * @param  array<int, array<string, mixed>>  $productRows
  * @return array<string, float|int>
  */
 private function aggregateProfitabilityTotalsFromRows(array $productRows): array
 {
  if ($productRows === []) {
   return [
    'sales_qty' => 0,
    'sales_amount' => 0,
    'returns_qty' => 0,
    'returns_amount' => 0,
    'net_sales' => 0,
    'cogs' => 0,
    'avg_unit_cost' => 0,
    'gross_profit' => 0,
    'gross_margin_percent' => 0,
   ];
  }

  $sumNetQtyCost = 0.0;
  foreach ($productRows as $pr) {
   $nq = max(0, (float) ($pr['sales_qty'] ?? 0) - (float) ($pr['returns_qty'] ?? 0));
   $sumNetQtyCost += $nq;
  }

  $totals = [
   'sales_qty' => round(array_sum(array_column($productRows, 'sales_qty')), 3),
   'sales_amount' => round(array_sum(array_column($productRows, 'sales_amount')), 2),
   'returns_qty' => round(array_sum(array_column($productRows, 'returns_qty')), 3),
   'returns_amount' => round(array_sum(array_column($productRows, 'returns_amount')), 2),
   'net_sales' => round(array_sum(array_column($productRows, 'net_sales')), 2),
   'cogs' => round(array_sum(array_column($productRows, 'cogs')), 2),
   'avg_unit_cost' => $sumNetQtyCost > 0.000001
    ? round(array_sum(array_column($productRows, 'cogs')) / $sumNetQtyCost, 2)
    : 0,
   'gross_profit' => round(array_sum(array_column($productRows, 'gross_profit')), 2),
   'gross_margin_percent' => 0,
  ];
  $totals['gross_margin_percent'] = $totals['net_sales'] != 0
   ? round(($totals['gross_profit'] / $totals['net_sales']) * 100, 2)
   : 0;

  return $totals;
 }
}
