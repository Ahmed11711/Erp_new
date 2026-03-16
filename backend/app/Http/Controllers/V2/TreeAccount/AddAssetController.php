<?php

namespace App\Http\Controllers\V2\TreeAccount;

use App\Models\TreeAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Repositories\TreeAccount\TreeAccountRepositoryInterface;
use App\Services\Accounting\AccountLinkingService;

class AddAssetController extends Controller
{
   public function __construct(
       public TreeAccountRepositoryInterface $repository,
       public AccountLinkingService $accountLinkingService
   ) {}

  public function Addcustomer($name, $type, $parentAccountId = null)
  {
      return $this->accountLinkingService->createCustomerAccount($name, $type ?? 'شركة', $parentAccountId);
  }

  public function createNewTreeAccount()
  {
        
  }

}
