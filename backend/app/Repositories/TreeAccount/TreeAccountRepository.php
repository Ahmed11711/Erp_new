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
        $query = $this->model->newQuery()->with(['children', 'parent']);

        if ($request->has('parent')) {
            return $query->whereNull('parent_id')->get();
        }

        if ($request->has('children')) {
            $parentIds = $this->model->whereNull('parent_id')->pluck('id');
            return $query->whereIn('parent_id', $parentIds)->get();
        }

        // Search by name (AR/EN) or code for account tree
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('name_en', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%");
            });
        }

        if ($request->filled('level')) {
            $query->where('level', $request->level);
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        $query->orderBy('code');

        return $query->get();
    }
}
