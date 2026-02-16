<?php

namespace App\Http\Controllers\V2\TreeAccount;

use App\Models\TreeAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Repositories\TreeAccount\TreeAccountRepositoryInterface;

class AddAssetController extends Controller
{
   public function __construct(public TreeAccountRepositoryInterface $repository)
  {}

  public function Addcustomer($name, $type, $parentAccountId = null)
  {

    $parent = null;
    if ($parentAccountId) {
        $parent = TreeAccount::find($parentAccountId);
    }
    
    // Fallback if parentId provided but not found, OR if parentId not provided
    if (!$parent) {
        if ($type == "شركة") {
            $parent = TreeAccount::where('code', 104)->where('level',3)->first();
        } else {
            $parent = TreeAccount::where('code', 100025)->first();
        }
    }

            $lastChildCode = TreeAccount::where('parent_id', $parent->id)->max('code');

        if ($lastChildCode) {
            $newCode = $lastChildCode + 1;
        } else {
             $newCode = $parent->code * 10 + 1;
        }
                $data = [
            'name'      => $name,
            'parent_id' => $parent->id,
            'code'      => $newCode,
            'level'     => $parent->level + 1,
            'type'      => $parent->type,
            'balance'   => 0
        ];

     return    $account = $this->repository->create($data);



 
  }

  public function createNewTreeAccount()
  {
        
  }

}
