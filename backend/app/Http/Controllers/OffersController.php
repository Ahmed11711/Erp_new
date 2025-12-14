<?php

namespace App\Http\Controllers;

use App\Models\Offers;
use App\Models\OffersCategory;
use Illuminate\Http\Request;

class OffersController extends Controller
{
    public function index(){
        $itemsPerPage = request('itemsPerPage') ? request('itemsPerPage') : 10;
        $data = Offers::orderBy('id' , 'desc')->paginate($itemsPerPage);
        return response()->json($data);
    }

    public function show($id)
    {
        $offer = Offers::where('id',$id)->with(['category'])->first();

        return response()->json($offer, 200);
    }

    public function store(Request $request){


        if ($request->has('id')) {
            $data = Offers::findOrFail($request->id);
            $data->update($request->all());
            OffersCategory::where('offer_id', $request->id)->delete();
        } else {
            $data = Offers::create($request->all());
        }

        $categories = $request->categories;
        // $categories = json_decode($categories, true);
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


        return response()->json(['message' => 'success'],201);
    }
}
