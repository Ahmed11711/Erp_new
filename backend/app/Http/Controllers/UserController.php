<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\Cache;

class UserController extends Controller
{

    public function index()
    {
        $users = User::with(['permissions:name'])->get();
        return response()->json($users,200);
    }

    public function usersForNotification()
    {
        $data = User::whereNotIn('id', [auth()->id()])->whereNot('department' , 'Employee')->whereNot('department' , 'test')->get();
        return response()->json($data, 200);
    }


    public function user_permission($id){
        $user = User::find($id);
        $permissions = $user->getAllPermissions()->pluck('name');
        return response()->json($permissions,200);
    }
    public function create_permssion(Request $request){
        $request->validate([
            'name'=>'required'
        ]);
        $permssion = Permission::create(['name' => $request->name]);
        return response()->json($permssion,200);
    }
    public function give_permission(Request $request,$id){
        $user = User::find($id);
       $per =  $user->givePermissionTo($request->permission);
        return response()->json($per,200);
    }

    public function get_all_permssions(){
        $permssions = Permission::select('name')->get();
        return response()->json($permssions,200);
    }

    public function revoke_permssion(Request $request,$id){
        $user = User::find($id);
        $user->revokePermissionTo($request->permission);
        return response()->json('done',200);
    }
}
