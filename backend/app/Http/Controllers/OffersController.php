<?php

namespace App\Http\Controllers;

use App\Models\Offers;
use App\Models\OffersCategory;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OffersController extends Controller
{
    private const OFFER_ATTRIBUTES = [
        'offer',
        'quote',
        'dateFrom',
        'dateTo',
        'subtotal',
        'vat',
        'total',
        'phone_number',
        'email',
        'title',
        'note',
        'transportation',
    ];

    private function isAdminDepartment(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        return ($user->department ?? '') === 'Admin';
    }

    private function canAccessOffer(Offers $offer, ?User $user): bool
    {
        if ($user === null) {
            return false;
        }
        if ($this->isAdminDepartment($user)) {
            return true;
        }

        return (int) $offer->user_id === (int) $user->id;
    }

    public function index()
    {
        $user = Auth::user();
        $itemsPerPage = request('itemsPerPage') ? (int) request('itemsPerPage') : 10;

        $query = Offers::query()
            ->with(['creator:id,name'])
            ->orderBy('id', 'desc');

        if (! $this->isAdminDepartment($user)) {
            $query->where('user_id', $user->id);
        }

        return response()->json($query->paginate($itemsPerPage));
    }

    public function show($id)
    {
        $user = Auth::user();
        $offer = Offers::query()
            ->where('id', $id)
            ->with(['category', 'creator:id,name'])
            ->first();

        if ($offer === null) {
            return response()->json(['message' => 'Not found'], 404);
        }

        if (! $this->canAccessOffer($offer, $user)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        return response()->json($offer, 200);
    }

    public function store(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'categories' => ['required', 'array', 'min:1'],
        ]);

        $payload = $request->only(self::OFFER_ATTRIBUTES);

        if ($request->has('id')) {
            $data = Offers::findOrFail($request->id);
            if (! $this->canAccessOffer($data, $user)) {
                return response()->json(['message' => 'Forbidden'], 403);
            }
            $data->update($payload);
            OffersCategory::where('offer_id', $request->id)->delete();
        } else {
            $data = Offers::create(array_merge($payload, [
                'user_id' => $user->id,
            ]));
        }

        $categories = $request->categories;
        foreach ($categories as $index => $category) {
            $img_name = '';

            if ($request->hasFile("categories.$index.image")) {
                $img = $request->file("categories.$index.image");
                $img_name = time() . "_category_{$index}." . $img->extension();
                $img->move(public_path('images'), $img_name);
            }

            if (isset($category['original_image'])) {
                $img_name = $category['original_image'];
            }

            OffersCategory::create([
                'offer_id' => $data->id,
                'category_name' => $category['category_name'],
                'category_quantity' => $category['category_quantity'],
                'old_category_price' => $category['old_category_price'],
                'new_category_price' => $category['new_category_price'],
                'total_price' => $category['total_price'],
                'description' => $category['description'] ?? '',
                'category_image' => $img_name,
            ]);
        }

        return response()->json(['message' => 'success'], 201);
    }
}
