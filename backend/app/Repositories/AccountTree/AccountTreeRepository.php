<?php

namespace App\Repositories\AccountTree;

use App\Models\AccountEntry;
use App\Repositories\BaseRepository\BaseRepository;
use App\Repositories\TreeAccount\TreeAccountRepositoryInterface;
 use Illuminate\Http\Request;

class AccountTreeRepository extends BaseRepository implements AccountTreeRepositoryInterface
{
    public function __construct(AccountEntry $model)
    {
        parent::__construct($model);
    }

   
}
