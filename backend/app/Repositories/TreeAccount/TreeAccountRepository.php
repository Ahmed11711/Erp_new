<?php

namespace App\Repositories\TreeAccount;

use App\Repositories\TreeAccount\TreeAccountRepositoryInterface;
use App\Repositories\BaseRepository\BaseRepository;
use App\Models\TreeAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TreeAccountRepository extends BaseRepository implements TreeAccountRepositoryInterface
{
    public function __construct(TreeAccount $model)
    {
        parent::__construct($model);
    }

    public function getAccounts($request)
    {
        $query = $this->model->newQuery()->with('children');
 
        if ($request->has('parent')) {
            return $query->whereNull('parent_id')->get();
        }

        if ($request->has('children')) {
            $parentIds = $this->model->whereNull('parent_id')->pluck('id');
            return $query->whereIn('parent_id', $parentIds)->get();
        }

        return $query->get();
    }
}
