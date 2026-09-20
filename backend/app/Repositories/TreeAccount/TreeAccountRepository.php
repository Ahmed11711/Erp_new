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
        if ($request->boolean('flat')) {
            return $this->model->newQuery()
                ->orderBy('code')
                ->get(['id', 'code', 'name']);
        }

        $query = $this->model->newQuery();

        if ($request->has('parent')) {
            return $query->whereNull('parent_id')->orderBy('code')->get();
        }

        if ($request->has('children')) {
            $parentIds = $this->model->whereNull('parent_id')->pluck('id');
            return $query->whereIn('parent_id', $parentIds)->orderBy('code')->get();
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

    /**
     * شجرة متداخلة من استعلام واحد — بدون eager-load متعدد المستويات.
     */
    public function getNestedTree()
    {
        $accounts = $this->model->newQuery()->orderBy('code')->get();

        foreach ($accounts as $account) {
            $account->setRelation('children', collect());
        }

        $byId = $accounts->keyBy('id');
        $roots = collect();

        foreach ($accounts as $account) {
            $parentId = $account->parent_id;
            if ($parentId && $byId->has($parentId)) {
                $byId->get($parentId)->children->push($account);
                continue;
            }
            $roots->push($account);
        }

        return $roots;
    }
}
