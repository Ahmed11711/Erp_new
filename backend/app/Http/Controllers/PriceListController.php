<?php

namespace App\Http\Controllers;

use App\Models\PriceList;
use App\Models\PriceListItem;
use App\Models\ShopifyProduct;
use App\Services\Shopify\ShopifyProductImageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class PriceListController extends Controller
{
    public const NAV_PERMISSION = 'nav.price_list';

    public const EDIT_PERMISSION = 'price_list.edit';

    public const DELETE_PERMISSION = 'price_list.delete';

    /** قائمة كل الكتالوجات */
    public function index()
    {
        if (! $this->canView()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $lists = PriceList::query()
            ->withCount('items')
            ->orderBy('sort_order')
            ->orderByDesc('id')
            ->get()
            ->map(function (PriceList $list) {
                return [
                    'id' => $list->id,
                    'name' => $list->name,
                    'sort_order' => $list->sort_order,
                    'items_count' => (int) $list->items_count,
                    'created_at' => $list->created_at,
                    'updated_at' => $list->updated_at,
                ];
            });

        return response()->json(['lists' => $lists], 200);
    }

    /** إنشاء قائمة أسعار جديدة */
    public function storeList(Request $request)
    {
        if (! $this->canEdit()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $list = PriceList::query()->create([
            'name' => trim($validated['name']),
            'sort_order' => ((int) PriceList::query()->max('sort_order')) + 1,
        ]);

        return response()->json([
            'message' => 'Price list created',
            'list' => $list,
        ], 201);
    }

    /** عرض قائمة واحدة مع منتجاتها */
    public function show($listId)
    {
        if (! $this->canView()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $list = PriceList::query()->with('items')->find($listId);
        if (! $list) {
            return response()->json(['message' => 'Not found'], 404);
        }

        return response()->json([
            'id' => $list->id,
            'name' => $list->name,
            'title' => $list->name,
            'items' => $list->items,
        ], 200);
    }

    /** إعادة تسمية القائمة */
    public function updateList(Request $request, $listId)
    {
        if (! $this->canEdit()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $list = PriceList::query()->find($listId);
        if (! $list) {
            return response()->json(['message' => 'Not found'], 404);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $list->name = trim($validated['name']);
        $list->save();

        return response()->json([
            'message' => 'Price list updated',
            'list' => $list,
            'title' => $list->name,
            'name' => $list->name,
        ], 200);
    }

    /** حذف قائمة كاملة مع منتجاتها وصورها */
    public function destroyList($listId)
    {
        if (! $this->canDelete()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $list = PriceList::query()->with('items')->find($listId);
        if (! $list) {
            return response()->json(['message' => 'Not found'], 404);
        }

        DB::transaction(function () use ($list) {
            foreach ($list->items as $item) {
                $this->deletePhotoFile($item->photo1);
                $this->deletePhotoFile($item->photo2);
            }
            $list->items()->delete();
            $list->delete();
        });

        return response()->json(['message' => 'Price list deleted'], 200);
    }

    public function storeItem(Request $request, $listId)
    {
        if (! $this->canEdit()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $list = PriceList::query()->find($listId);
        if (! $list) {
            return response()->json(['message' => 'Not found'], 404);
        }

        $validated = $request->validate([
            'code' => 'required|string|max:64|unique:price_list_items,code,NULL,id,price_list_id,' . $list->id,
            'product_name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'currency' => 'required|string|max:16',
            'photo1' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:4096',
            'photo2' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:4096',
            'photo1_url' => 'nullable|string|max:2000',
            'photo2_url' => 'nullable|string|max:2000',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $sortOrder = $validated['sort_order']
            ?? ((int) PriceListItem::query()->where('price_list_id', $list->id)->max('sort_order') + 1);

        $imageService = app(ShopifyProductImageService::class);
        $photo1 = $this->storePhoto($request, 'photo1');
        if (! $photo1 && ! empty($validated['photo1_url'])) {
            $photo1 = $imageService->downloadToPublicImages($validated['photo1_url']);
        }
        $photo2 = $this->storePhoto($request, 'photo2');
        if (! $photo2 && ! empty($validated['photo2_url'])) {
            $photo2 = $imageService->downloadToPublicImages($validated['photo2_url']);
        }

        $item = PriceListItem::query()->create([
            'price_list_id' => $list->id,
            'code' => trim($validated['code']),
            'product_name' => trim($validated['product_name']),
            'price' => $validated['price'],
            'currency' => strtoupper(trim($validated['currency'])),
            'photo1' => $photo1,
            'photo2' => $photo2,
            'sort_order' => $sortOrder,
        ]);

        return response()->json([
            'message' => 'Item created',
            'item' => $item,
        ], 201);
    }

    public function updateItem(Request $request, $listId, $itemId)
    {
        if (! $this->canEdit()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $item = PriceListItem::query()
            ->where('price_list_id', $listId)
            ->where('id', $itemId)
            ->first();

        if (! $item) {
            return response()->json(['message' => 'Not found'], 404);
        }

        $validated = $request->validate([
            'code' => 'required|string|max:64|unique:price_list_items,code,' . $item->id . ',id,price_list_id,' . $listId,
            'product_name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'currency' => 'required|string|max:16',
            'photo1' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:4096',
            'photo2' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:4096',
            'photo1_url' => 'nullable|string|max:2000',
            'photo2_url' => 'nullable|string|max:2000',
            'sort_order' => 'nullable|integer|min:0',
            'remove_photo1' => 'nullable|boolean',
            'remove_photo2' => 'nullable|boolean',
        ]);

        $item->code = trim($validated['code']);
        $item->product_name = trim($validated['product_name']);
        $item->price = $validated['price'];
        $item->currency = strtoupper(trim($validated['currency']));
        if (array_key_exists('sort_order', $validated) && $validated['sort_order'] !== null) {
            $item->sort_order = (int) $validated['sort_order'];
        }

        $imageService = app(ShopifyProductImageService::class);

        if ($request->boolean('remove_photo1')) {
            $this->deletePhotoFile($item->photo1);
            $item->photo1 = null;
        } elseif ($request->hasFile('photo1')) {
            $this->deletePhotoFile($item->photo1);
            $item->photo1 = $this->storePhoto($request, 'photo1');
        } elseif (! empty($validated['photo1_url'])) {
            $downloaded = $imageService->downloadToPublicImages($validated['photo1_url']);
            if ($downloaded) {
                $this->deletePhotoFile($item->photo1);
                $item->photo1 = $downloaded;
            }
        }

        if ($request->boolean('remove_photo2')) {
            $this->deletePhotoFile($item->photo2);
            $item->photo2 = null;
        } elseif ($request->hasFile('photo2')) {
            $this->deletePhotoFile($item->photo2);
            $item->photo2 = $this->storePhoto($request, 'photo2');
        } elseif (! empty($validated['photo2_url'])) {
            $downloaded = $imageService->downloadToPublicImages($validated['photo2_url']);
            if ($downloaded) {
                $this->deletePhotoFile($item->photo2);
                $item->photo2 = $downloaded;
            }
        }

        $item->save();

        return response()->json([
            'message' => 'Item updated',
            'item' => $item->fresh(),
        ], 200);
    }

    public function destroyItem($listId, $itemId)
    {
        if (! $this->canDelete()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $item = PriceListItem::query()
            ->where('price_list_id', $listId)
            ->where('id', $itemId)
            ->first();

        if (! $item) {
            return response()->json(['message' => 'Not found'], 404);
        }

        $this->deletePhotoFile($item->photo1);
        $this->deletePhotoFile($item->photo2);
        $item->delete();

        return response()->json(['message' => 'Item deleted'], 200);
    }

    /**
     * تقديم صورة محلية مع CORS لاستخدامها في PDF/طباعة (عن نافذة about:blank و html2canvas).
     * الملفات أصلاً عامة تحت public/images — لا حاجة لمصادقة إضافية.
     */
    public function servePhoto(string $filename)
    {
        $safe = basename($filename);
        if ($safe === '' || $safe !== $filename || preg_match('/[\\\\\/]/', $filename)) {
            return response()->json(['message' => 'Invalid filename'], 422);
        }

        $path = public_path('images/'.$safe);
        if (! is_file($path)) {
            return response()->json(['message' => 'Not found'], 404);
        }

        $ext = strtolower(pathinfo($safe, PATHINFO_EXTENSION));
        $mime = match ($ext) {
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            'jpg', 'jpeg' => 'image/jpeg',
            'bmp' => 'image/bmp',
            default => (mime_content_type($path) ?: 'application/octet-stream'),
        };

        return response()->file($path, [
            'Content-Type' => $mime,
            'Cache-Control' => 'public, max-age=86400',
            'Access-Control-Allow-Origin' => '*',
            'Cross-Origin-Resource-Policy' => 'cross-origin',
        ]);
    }

    /** بحث منتجات Shopify لاستيرادها إلى قائمة الأسعار */
    public function searchShopifyProducts(Request $request)
    {
        if (! $this->canView()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $q = trim((string) $request->query('q', ''));
        $perPage = min(max((int) $request->query('per_page', 20), 1), 50);

        $query = ShopifyProduct::query()->orderByDesc('id');

        if ($q !== '') {
            $query->where(function ($builder) use ($q) {
                $builder->where('name', 'like', '%'.$q.'%')
                    ->orWhere('sku', 'like', '%'.$q.'%');
            });
        }

        $page = $query->paginate($perPage);

        $page->getCollection()->transform(function (ShopifyProduct $product) {
            return [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'price' => (float) $product->price,
                'image_url_1' => $product->image_url_1,
                'image_url_2' => $product->image_url_2,
                'shopify_product_id' => $product->shopify_product_id,
                'shopify_variant_id' => $product->shopify_variant_id,
            ];
        });

        return response()->json($page, 200);
    }

    /**
     * كتالوج صور منتجات Shopify (فريدة) لاختيار Photo 1 / Photo 2 داخل نموذج الإضافة/التعديل.
     */
    public function shopifyImageCatalog(Request $request)
    {
        if (! $this->canView()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $q = trim((string) $request->query('q', ''));
        $perPage = min(max((int) $request->query('per_page', 48), 1), 100);
        $page = max((int) $request->query('page', 1), 1);

        $rows = ShopifyProduct::query()
            ->when($q !== '', function ($builder) use ($q) {
                $builder->where(function ($inner) use ($q) {
                    $inner->where('name', 'like', '%'.$q.'%')
                        ->orWhere('sku', 'like', '%'.$q.'%');
                });
            })
            ->where(function ($builder) {
                $builder->whereNotNull('image_url_1')->where('image_url_1', '!=', '')
                    ->orWhere(function ($inner) {
                        $inner->whereNotNull('image_url_2')->where('image_url_2', '!=', '');
                    });
            })
            ->orderByDesc('id')
            ->limit(800)
            ->get(['id', 'name', 'sku', 'image_url_1', 'image_url_2']);

        $seen = [];
        $images = [];
        foreach ($rows as $row) {
            foreach ([$row->image_url_1, $row->image_url_2] as $url) {
                $url = is_string($url) ? trim($url) : '';
                if ($url === '' || isset($seen[$url])) {
                    continue;
                }
                $seen[$url] = true;
                $images[] = [
                    'url' => $url,
                    'name' => $row->name,
                    'sku' => $row->sku,
                    'shopify_product_row_id' => $row->id,
                ];
            }
        }

        $total = count($images);
        $offset = ($page - 1) * $perPage;
        $slice = array_slice($images, $offset, $perPage);
        $lastPage = max(1, (int) ceil($total / $perPage));

        return response()->json([
            'data' => $slice,
            'current_page' => $page,
            'last_page' => $lastPage,
            'per_page' => $perPage,
            'total' => $total,
        ], 200);
    }

    /** استيراد منتج Shopify (مع تنزيل الصور) إلى قائمة أسعار */
    public function importFromShopify(Request $request, $listId, ShopifyProductImageService $images)
    {
        if (! $this->canEdit()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $list = PriceList::query()->find($listId);
        if (! $list) {
            return response()->json(['message' => 'Not found'], 404);
        }

        $validated = $request->validate([
            'shopify_product_id' => 'required|integer|exists:shopify_products,id',
            'currency' => 'nullable|string|max:16',
        ]);

        $shopify = ShopifyProduct::query()->find($validated['shopify_product_id']);
        if (! $shopify) {
            return response()->json(['message' => 'Shopify product not found'], 404);
        }

        $imageUrl1 = $shopify->image_url_1;
        $imageUrl2 = $shopify->image_url_2;

        if ((! $imageUrl1 && ! $imageUrl2) && $shopify->shopify_product_id) {
            [$imageUrl1, $imageUrl2] = $images->fetchImageUrlsFromApi(
                (int) $shopify->shopify_product_id,
                $shopify->shopify_variant_id ? (int) $shopify->shopify_variant_id : null
            );
            if ($imageUrl1 || $imageUrl2) {
                $shopify->image_url_1 = $imageUrl1;
                $shopify->image_url_2 = $imageUrl2;
                $shopify->save();
            }
        }

        $photo1 = $imageUrl1 ? $images->downloadToPublicImages($imageUrl1) : null;
        $photo2 = $imageUrl2 ? $images->downloadToPublicImages($imageUrl2) : null;

        $code = trim((string) ($shopify->sku ?: ('SH-'.$shopify->id)));
        $baseCode = $code;
        $suffix = 1;
        while (
            PriceListItem::query()
                ->where('price_list_id', $list->id)
                ->where('code', $code)
                ->exists()
        ) {
            $code = $baseCode.'-'.$suffix;
            $suffix++;
        }

        $sortOrder = ((int) PriceListItem::query()->where('price_list_id', $list->id)->max('sort_order')) + 1;

        $item = PriceListItem::query()->create([
            'price_list_id' => $list->id,
            'code' => $code,
            'product_name' => trim((string) ($shopify->name ?: 'Shopify product')),
            'price' => (float) $shopify->price,
            'currency' => strtoupper(trim((string) ($validated['currency'] ?? 'EGP'))) ?: 'EGP',
            'photo1' => $photo1,
            'photo2' => $photo2,
            'sort_order' => $sortOrder,
        ]);

        return response()->json([
            'message' => 'Imported from Shopify',
            'item' => $item,
            'images_downloaded' => [
                'photo1' => (bool) $photo1,
                'photo2' => (bool) $photo2,
            ],
        ], 201);
    }

    private function canView(): bool
    {
        return has_permission(self::NAV_PERMISSION)
            || has_permission(self::EDIT_PERMISSION)
            || has_permission(self::DELETE_PERMISSION)
            || has_permission('system.rbac');
    }

    private function canEdit(): bool
    {
        return has_permission(self::EDIT_PERMISSION);
    }

    private function canDelete(): bool
    {
        return has_permission(self::DELETE_PERMISSION);
    }

    private function storePhoto(Request $request, string $field): ?string
    {
        if (! $request->hasFile($field)) {
            return null;
        }

        $img = $request->file($field);
        $name = time() . '_' . $field . '_' . uniqid() . '.' . $img->extension();
        $img->move(public_path('images'), $name);

        return $name;
    }

    private function deletePhotoFile(?string $filename): void
    {
        if (! $filename) {
            return;
        }

        $path = public_path('images/' . $filename);
        if (File::exists($path)) {
            File::delete($path);
        }
    }
}
