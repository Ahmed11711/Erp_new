<?php

namespace App\Http\Controllers;

use App\Models\CollectionCompany;
use App\Models\TreeAccount;
use App\Services\Accounting\AccountLinkingService;
use App\Services\Accounting\ReceivableReconciliationService;
use App\Services\Accounting\ReceivableTreeAccountGuard;
use App\Services\Shipping\UnlinkedReceivableAccountException;
use Illuminate\Http\Request;

class CollectionCompanyController extends Controller
{
    public function __construct(
        public AccountLinkingService $accountLinkingService,
        public ReceivableTreeAccountGuard $receivableGuard,
        public ReceivableReconciliationService $reconciliationService,
    ) {
    }

    public function index(Request $request)
    {
        $q = CollectionCompany::query()->with([
            'linkedShippingCompany:id,name',
            'receivableTreeAccount:id,code,name,balance,debit_balance,credit_balance',
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
            CollectionCompany::active()
                ->with('receivableTreeAccount:id,balance')
                ->orderBy('name')
                ->get()
                ->map(fn (CollectionCompany $c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'linked_shipping_company_id' => $c->linked_shipping_company_id,
                    'balance' => (float) ($c->receivableTreeAccount->balance ?? 0),
                ])
        );
    }

    public function show(CollectionCompany $collectionCompany)
    {
        $collectionCompany->load(
            'linkedShippingCompany:id,name,type',
            'receivableTreeAccount:id,code,name,balance,debit_balance,credit_balance'
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
            if ($account && ! $this->receivableGuard->isPaymentSourceTreeAccount((int) $account->id)) {
                return response()->json([
                    'message' => 'الشركة مربوطة بالفعل بحساب في الشجرة.',
                    'receivable_tree_account_id' => $collectionCompany->receivable_tree_account_id,
                    'receivable_tree_account' => [
                        'id' => $account->id,
                        'name' => $account->name,
                        'code' => (string) $account->code,
                    ],
                ]);
            }

            $collectionCompany->receivable_tree_account_id = null;
            $collectionCompany->save();
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

        if (! empty($data['receivable_tree_account_id'])) {
            try {
                $this->receivableGuard->assertValidReceivableAssignment(
                    (int) $data['receivable_tree_account_id'],
                    'شركة التحصيل'
                );
            } catch (UnlinkedReceivableAccountException $e) {
                return response()->json(['message' => $e->getMessage()], 422);
            }
        }

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

        if (array_key_exists('receivable_tree_account_id', $data) && ! empty($data['receivable_tree_account_id'])) {
            try {
                $this->receivableGuard->assertValidReceivableAssignment(
                    (int) $data['receivable_tree_account_id'],
                    'شركة التحصيل'
                );
            } catch (UnlinkedReceivableAccountException $e) {
                return response()->json(['message' => $e->getMessage()], 422);
            }
        }

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

    /**
     * عدّ الطلبات المرتبطة بشركة تحصيل في فترة محددة.
     */
    public function reconcileCount(Request $request, CollectionCompany $collectionCompany)
    {
        $request->validate([
            'date_from' => 'required|date',
            'date_to' => 'required|date|after_or_equal:date_from',
        ]);

        $counts = $this->reconciliationService->countCollectionOrders(
            $collectionCompany->id,
            $request->date_from,
            $request->date_to
        );

        return response()->json($counts);
    }

    /**
     * جلب الطلبات المرتبطة بشركة تحصيل في فترة محددة.
     */
    public function reconcileOrders(Request $request, CollectionCompany $collectionCompany)
    {
        $request->validate([
            'date_from' => 'required|date',
            'date_to' => 'required|date|after_or_equal:date_from',
        ]);

        $orders = $this->reconciliationService->listCollectionOrders(
            $collectionCompany->id,
            $request->date_from,
            $request->date_to
        );

        return response()->json(['orders' => $orders]);
    }

    /**
     * إعادة حساب مديونيات شركة تحصيل.
     */
    public function reconcileReceivables(Request $request, CollectionCompany $collectionCompany)
    {
        $request->validate([
            'date_from' => 'required|date',
            'date_to' => 'required|date|after_or_equal:date_from',
            'order_ids' => 'nullable|array',
            'order_ids.*' => 'integer',
        ]);

        $result = $this->reconciliationService->reconcileCollectionCompany(
            $collectionCompany->id,
            $request->date_from,
            $request->date_to,
            $request->order_ids
        );

        $status = $result['success'] ? 200 : 422;

        return response()->json($result, $status);
    }
}
