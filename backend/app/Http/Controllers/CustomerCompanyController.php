<?php

namespace App\Http\Controllers;

use App\Models\Bank;
use Illuminate\Http\Request;
use App\Models\customerCompany;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\V2\TreeAccount\AddAssetController;


class CustomerCompanyController extends Controller
{
    
    public function __construct(public AddAssetController $addAsset)
    {
    }
    public function index()
    {
        $companies = customerCompany::all();
        return response()->json($companies);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
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
            'name' => 'required|unique:customer_companies,name',
            'phone1' => 'required|unique:customer_companies,phone1',
            'governorate' => 'required',
            'address' => 'required',
        ]);

 
        customerCompany::create([
            'name' => $request->name,
            'phone1' => $request->phone1,
            'phone2' => $request->phone2,
            'phone3' => $request->phone3,
            'phone4' => $request->phone4,
            'tel' => $request->tel,
            'governorate' => $request->governorate,
            'city' => $request->city,
            'address' => $request->address,
        ]);
        $this->addAsset->Addcustomer($request->name,'شركة');
        return response()->json(['message'=>'success'], 200);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\customerCompany  $customerCompany
     * @return \Illuminate\Http\Response
     */
    public function show(customerCompany $customerCompany)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\customerCompany  $customerCompany
     * @return \Illuminate\Http\Response
     */
    public function edit(customerCompany $customerCompany)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\customerCompany  $customerCompany
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, customerCompany $customerCompany)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\customerCompany  $customerCompany
     * @return \Illuminate\Http\Response
     */
    public function destroy(customerCompany $customerCompany)
    {
        //
    }

    public function search(Request $request){

        $itemsPerPage = request('itemsPerPage') ? request('itemsPerPage') : 10;
        $search = customerCompany::query();
        if($request->has('name')){
            $search->where('name', 'like' ,  '%'.$request->name.'%');
        }
        if($request->has('phone')){
            $search->where('phone1','like' , $request->phone.'%');
        }

        $search = $search->orderBy('id' , 'desc')->paginate($itemsPerPage);
        return response()->json($search, 200);
    }


    public function customerCompanyBalance($id , Request $request){
        $itemsPerPage = $request->input('itemsPerPage', 15);

        $name = customerCompany::where('id', $id)->value('name');

        $data = DB::table('customer_company_details')
        ->join('users', 'customer_company_details.user_id', '=', 'users.id')
        ->select('customer_company_details.*', 'users.name')
        ->where('customer_company_id', $id)
        // ->orderBy('customer_company_details.created_at', 'desc')
        ->orderBy('customer_company_details.id', 'desc')
        ->paginate($itemsPerPage);


        $result = [
            'data' => $data,
            $name,
        ];

        return response()->json($result, 200);
    }

    public function companyCollect($id , Request $request){

        $amount = request('amount');
        $bankId = request('bank');

        $request->validate(
            [
                'bank' => 'required|numeric|exists:banks,id',
                'amount' => 'required|numeric|min:0',
            ]);


        $company = CustomerCompany::find($id);
        $balance =(double) $company->balance;
        $company->balance = $company->balance - $amount ;
        $company->save();

        $lastIndex = DB::table('customer_company_details')->orderBy('id' , 'desc')->first();

        DB::table('customer_company_details')->insert([
            'bank_id' => $bankId,
            'customer_company_id' => $company->id,
            'ref' => 'C'.$lastIndex->id+1,
            'details' => ' تحصيل من حساب الشركة ',
            'type' => 'تحصيل',
            'amount' => (double)$amount,
            'balance_before' => $balance,
            'balance_after' => $company->balance,
            'date' => date('Y-m-d'),
            'created_at' => now(),
            'user_id'=> auth()->user()->id
        ]);


        $bank  = Bank::find($bankId);
        $balance =(double) $bank->balance;
        $bank->balance = $bank->balance + $amount;
        $bank->save();


        DB::table('bank_details')->insert([
            'bank_id' => $bankId,
            'details' => ' تحصيل من حساب شركة '.$company->name,
            'ref' => 'C'.$lastIndex->id+1,
            'type' =>'تحصيل عملاء شركات',
            'amount' => (double)$amount,
            'balance_before' => $balance,
            'balance_after' => $bank->balance,
            'date' => date('Y-m-d'),
            'created_at' => now(),
            'user_id'=> auth()->user()->id
        ]);



        return response()->json(['message' => 'success'], 200);
    }
}
