<?php

namespace App\Http\Controllers;

use App\Models\CollectionCompany;
use App\Models\TreeAccount;
use App\Services\Accounting\AccountLinkingService;
use Illuminate\Http\Request;

class CollectionCompanyController extends Controller
{
    public function __construct(public AccountLinkingService $accountLinkingService)
    {
    }

    public function index(Request $request)
    {
        $q = CollectionCompany::query()->with([
            'linkedShippingCompany:id,name',
            'receivableTreeAccount:id,code,name',
        ]);

        if ($request->filled('status')) {
            $q->where('status', $request->status);
        }
        if ($request->filled('search')) {
            $s = '%' . $request->search . '%';
            $q->where('name', 'like', $s);
        }

        return response()->json($q->orderBy('name')->get());
    }

    public function select()
    {
        return response()->json(
            CollectionCompany::active()->select('id', 'name', 'linked_shipping_company_id')->orderBy('name')->get()
        );
    }

    public function show(CollectionCompany $collectionCompany)
    {
        $collectionCompany->load(
            'linkedShippingCompany:id,name,type',
            'receivableTreeAccount:id,code,name'
        );

        return response()->json($collectionCompany);
    }

    /**
     * عدد شركات التحصيل غير المربوطين بحسابات الشجرة.
     */
    public function unlinkedSummary()
    {
        $unlinkedCount = CollectionCompany::query()->whereNull('receivable_tree_account_id')->count();
        $parent = $this->accountLinkingService->getCollectionCompaniesParent();

        return response()->json([
            'unlinked_count' => $unlinkedCount,
            'parent_account' => $parent ? [
                'id' => $parent->id,
                'name' => $parent->name,
                'code' => (string) $parent->code,
            ] : null,
        ]);
    }

    /**
     * ربط جميع شركات التحصيل غير المربوطين دفعة واحدة.
     */
    public function linkUnlinked()
    {
        $result = $this->accountLinkingService->linkAllUnlinkedCollectionCompanies();

        if (!$result['parent']) {
            return response()->json([
                'message' => $result['message'],
            ], 422);
        }

        $status = $result['failed'] > 0 ? 207 : 200;

        return response()->json($result, $status);
    }

    /**
     * ربط شركة تحصيل واحدة بحساب في شجرة الحسابات.
     */
    public function linkAccount(CollectionCompany $collectionCompany)
    {
        if ($collectionCompany->receivable_tree_account_id) {
            $account = TreeAccount::find($collectionCompany->receivable_tree_account_id);

            return response()->json([
                'message' => 'الشركة مربوطة بالفعل بحساب في الشجرة.',
                'receivable_tree_account_id' => $collectionCompany->receivable_tree_account_id,
                'receivable_tree_account' => $account ? [
                    'id' => $account->id,
                    'name' => $account->name,
                    'code' => (string) $account->code,
                ] : null,
            ]);
        }

        $account = $this->accountLinkingService->ensureCollectionCompanyAccount($collectionCompany);
        if (!$account) {
            return response()->json([
                'message' => 'تعذر إنشاء حساب للشركة. راجع حساب «شركات التحصيل» في شجرة الحسابات.',
            ], 422);
        }

        return response()->json([
            'message' => 'success',
            'receivable_tree_account_id' => $account->id,
            'receivable_tree_account' => [
                'id' => $account->id,
                'name' => $account->name,
                'code' => (string) $account->code,
            ],
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:64',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string',
            'status' => 'nullable|in:active,inactive',
            'notes' => 'nullable|string',
            'linked_shipping_company_id' => 'nullable|exists:shipping_companies,id',
            'receivable_tree_account_id' => 'nullable|exists:tree_accounts,id',
        ]);

        $company = CollectionCompany::create($data);

        if (!$company->receivable_tree_account_id) {
            $account = $this->accountLinkingService->ensureCollectionCompanyAccount($company);
            if (!$account) {
                return response()->json([
                    'message' => 'تم إنشاء الشركة لكن تعذر إنشاء حسابها في شجرة الحسابات. راجع حساب «شركات التحصيل» أو إعدادات الربط المحاسبي.',
                ], 422);
            }
        } else {
            $account = TreeAccount::find($company->receivable_tree_account_id);
        }

        $company->load('receivableTreeAccount:id,code,name');

        return response()->json($company, 201);
    }

    public function update(Request $request, CollectionCompany $collectionCompany)
    {
        $data = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'phone' => 'nullable|string|max:64',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string',
            'status' => 'nullable|in:active,inactive',
            'notes' => 'nullable|string',
            'linked_shipping_company_id' => 'nullable|exists:shipping_companies,id',
            'receivable_tree_account_id' => 'nullable|exists:tree_accounts,id',
        ]);

        $collectionCompany->update($data);

        if (!$collectionCompany->receivable_tree_account_id) {
            $account = $this->accountLinkingService->ensureCollectionCompanyAccount($collectionCompany);
            if (!$account) {
                return response()->json([
                    'message' => 'تم حفظ بيانات الشركة لكن تعذر ربطها بحساب في شجرة الحسابات.',
                ], 422);
            }
        } else {
            $this->accountLinkingService->syncCollectionCompanyTreeAccountName($collectionCompany);
        }

        $collectionCompany->load('receivableTreeAccount:id,code,name');

        return response()->json($collectionCompany);
    }

    public function destroy(CollectionCompany $collectionCompany)
    {
        $collectionCompany->delete();

        return response()->json(['message' => 'deleted']);
    }
}
