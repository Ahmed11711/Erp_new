<?php

namespace App\Http\Controllers;

use App\Models\CorporateSalesIndustry;


class CorporateSalesIndustryController extends Controller
{


    public function index(){
        $data = CorporateSalesIndustry::get();
        return response()->json($data);
    }

    public function store(){

    }

}
