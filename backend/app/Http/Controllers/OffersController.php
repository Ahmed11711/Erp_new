<?php

namespace App\Http\Controllers;

use App\Models\CorporateSalesLead;
use App\Models\CorporateSalesTracking;
use App\Models\Offers;
use App\Models\OffersCategory;
use App\Models\User;
use App\Models\customerCompany;
use App\Services\Accounting\AccountLinkingService;
use App\Services\Accounting\OfferDebtAccountingService;
use App\Services\Offers\OfferConvertToOrderService;
use App\Services\Offers\OfferProductMatchService;
use App\Services\Offers\OfferSyncConvertedOrderService;
use App\Support\RbacLegacyAccess;
use App\Support\VatCalculator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class OffersController extends Controller
{
    /** صلاحية عرض كل عروض الأسعار بغض النظر عن من أنشأها */
    public const VIEW_ALL_PERMISSION = 'offers.view_all';

    /** صلاحية تعديل العرض وأصنافه (إضافة / حذف / تغيير) */
    public const EDIT_PERMISSION = 'offers.edit';

    /** صلاحية حذف عرض السعر بالكامل */
    public const DELETE_PERMISSION = 'offers.delete';

    private const OFFER_ATTRIBUTES = [
        'offer',
        'quote',
        'contact_person',
        'client_phone',
        'corporate_sales_lead_id',
        'dateFrom',
        'dateTo',
        'subtotal',
        'vat',
        'total',
        'phone_number',
        'email',
        'title',
        'note',
        'transportation',
    ];

    private function canViewAllOffers(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        // استخدم المستخدم الممرّر (وليس فقط auth() الحالي) حتى يبقى الفحص متسقاً مع canAccessOffer
        $resolver = app(\App\Services\Rbac\PermissionResolutionService::class);

        return $resolver->hasPermission($user, self::VIEW_ALL_PERMISSION)
            || $resolver->hasPermission($user, 'system.rbac');
    }

    private function canAccessCorporateLeads(?User $user): bool
    {
        return RbacLegacyAccess::passes(
            $user,
            ['Admin', 'Shipping Management', 'Corparates'],
            ['nav.corporate', 'system.rbac']
        );
    }

    private function canAccessOffer(Offers $offer, ?User $user): bool
    {
        if ($user === null) {
            return false;
        }
        if ($this->canViewAllOffers($user)) {
            return true;
        }
        if ($offer->corporate_sales_lead_id && $this->canAccessCorporateLeads($user)) {
            return true;
        }

        return (int) $offer->user_id === (int) $user->id;
    }

    private function canEditOffers(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        return has_permission(self::EDIT_PERMISSION) || has_permission('system.rbac');
    }

    private function canDeleteOffers(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        return has_permission(self::DELETE_PERMISSION) || has_permission('system.rbac');
    }

    public function index()
    {
        $user = Auth::user();
        $itemsPerPage = (int) request('itemsPerPage', 15);
        if ($itemsPerPage < 1) {
            $itemsPerPage = 15;
        }
        if ($itemsPerPage > 100) {
            $itemsPerPage = 100;
        }

        $query = Offers::query()
            ->with([
                'creator:id,name',
                'customerCompany:id,name,phone1,balance',
                'lead:id,company_name',
            ])
            ->orderBy('id', 'desc');

        $leadId = request()->filled('corporate_sales_lead_id')
            ? (int) request('corporate_sales_lead_id')
            : 0;

        // الافتراضي: عروض المستخدم فقط — إلا مع صلاحية offers.view_all
        // أو فلتر Lead لمستخدم يملك صلاحية الـ leads
        if (! $this->canViewAllOffers($user)
            && ! ($leadId > 0 && $this->canAccessCorporateLeads($user))
        ) {
            $query->where('user_id', $user->id);
        }

        if ($leadId > 0) {
            $query->where('corporate_sales_lead_id', $leadId);
        }

        $offerId = trim((string) request('id', ''));
        if ($offerId !== '' && ctype_digit($offerId)) {
            $query->where('id', (int) $offerId);
        }

        $quote = trim((string) request('quote', ''));
        if ($quote !== '') {
            $query->where('quote', 'like', '%' . $quote . '%');
        }

        $companyName = trim((string) request('company_name', ''));
        if ($companyName !== '') {
            $query->whereHas('customerCompany', function ($q) use ($companyName) {
                $q->where('name', 'like', '%' . $companyName . '%');
            });
        }

        if (request()->filled('customer_company_id')) {
            $query->where('customer_company_id', (int) request('customer_company_id'));
        }

        $page = $query->paginate($itemsPerPage);
        $page->getCollection()->each(static fn (Offers $offer) => VatCalculator::applyToOffer($offer));

        return response()->json($page);
    }

    public function show($id)
    {
        $user = Auth::user();
        $offer = Offers::query()
            ->where('id', $id)
            ->with([
                'category',
                'creator:id,name',
                'customerCompany:id,name,phone1,phone2,balance',
                'lead:id,company_name',
            ])
            ->first();

        if ($offer === null) {
            return response()->json(['message' => 'Not found'], 404);
        }

        if (! $this->canAccessOffer($offer, $user)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        return response()->json(VatCalculator::applyToOffer($offer), 200);
    }

    /**
     * ربط عرض السعر بعميل شركة وترحيل إجمالي العرض كمديونية على رصيده + قيد الشجرة.
     */
    public function linkCustomerCompany(
        Request $request,
        $id,
        AccountLinkingService $accountLinking,
        OfferDebtAccountingService $offerDebtAccounting
    ) {
        $user = Auth::user();

        $request->validate([
            'customer_company_id' => ['required', 'integer', 'exists:customer_companies,id'],
        ]);

        $offer = Offers::query()->where('id', $id)->first();
        if ($offer === null) {
            return response()->json(['message' => 'عرض السعر غير موجود'], 404);
        }

        if (! $this->canAccessOffer($offer, $user)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        // عروض مربوطة سابقاً بدون قيد شجرة: أكمل القيد فقط
        if ($offer->debt_posted_at !== null) {
            return $this->completeMissingGl($offer, $user, $offerDebtAccounting);
        }

        $company = customerCompany::findOrFail((int) $request->customer_company_id);

        try {
            $this->attachCompanyAndPostDebt($offer, $company, $user, $accountLinking, $offerDebtAccounting);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'فشل ربط العميل أو ترحيل المديونية: ' . $e->getMessage(),
            ], 500);
        }

        $offer->load(['customerCompany:id,name,phone1,phone2,balance,tree_account_id', 'category', 'creator:id,name']);

        return response()->json([
            'message' => 'تم ربط العرض بعميل الشركة وترحيل المديونية على الرصيد وحساب الشجرة بنجاح',
            'offer' => $offer,
        ], 200);
    }

    /**
     * إنشاء عميل شركة من بيانات عرض السعر ثم الربط وترحيل المديونية.
     */
    public function createAndLinkCustomerCompany(
        Request $request,
        $id,
        AccountLinkingService $accountLinking,
        OfferDebtAccountingService $offerDebtAccounting
    ) {
        $user = Auth::user();

        $offer = Offers::query()->where('id', $id)->first();
        if ($offer === null) {
            return response()->json(['message' => 'عرض السعر غير موجود'], 404);
        }

        if (! $this->canAccessOffer($offer, $user)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        if ($offer->debt_posted_at !== null) {
            return response()->json([
                'message' => 'تم ربط هذا العرض وترحيل المديونية مسبقاً ولا يمكن تكرار العملية',
            ], 422);
        }

        $defaultName = trim((string) ($request->input('name') ?: $offer->quote));
        $defaultPhone = $this->normalizeEgyptMobile(
            (string) ($request->input('phone1') ?: $offer->client_phone ?: '')
        );
        $defaultAddress = trim((string) ($request->input('address') ?: ''));
        if ($defaultAddress === '' && trim((string) ($offer->contact_person ?? '')) !== '') {
            $defaultAddress = 'مسؤول التواصل: ' . trim((string) $offer->contact_person);
        }

        $request->merge([
            'name' => $defaultName,
            'phone1' => $defaultPhone,
            'address' => $defaultAddress,
        ]);

        $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:customer_companies,name'],
            'phone1' => ['required', 'string', 'max:20'],
            'phone2' => ['nullable', 'string', 'max:20'],
            'governorate' => ['required', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'address' => ['required', 'string', 'max:1000'],
            'tel' => ['nullable', 'string', 'max:50'],
        ]);

        try {
            $company = DB::transaction(function () use ($request, $offer, $user, $accountLinking, $offerDebtAccounting) {
                $company = customerCompany::create([
                    'name' => trim((string) $request->name),
                    'phone1' => trim((string) $request->phone1),
                    'phone2' => $request->filled('phone2') ? trim((string) $request->phone2) : null,
                    'tel' => $request->filled('tel') ? trim((string) $request->tel) : null,
                    'governorate' => trim((string) $request->governorate),
                    'city' => $request->filled('city') && $request->city !== 'المدينة'
                        ? trim((string) $request->city)
                        : null,
                    'address' => trim((string) $request->address),
                ]);

                $account = $accountLinking->ensureCustomerCompanyAccount($company);
                if (! $account) {
                    throw new \RuntimeException(
                        'تم إنشاء الشركة لكن تعذر إنشاء حسابها في شجرة الحسابات. راجع حساب «عملاء شركات».'
                    );
                }

                $company->refresh();
                $this->attachCompanyAndPostDebt($offer, $company, $user, $accountLinking, $offerDebtAccounting);

                return $company;
            });
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'فشل إنشاء العميل أو ترحيل المديونية: ' . $e->getMessage(),
            ], 500);
        }

        $offer->load(['customerCompany:id,name,phone1,phone2,balance,tree_account_id', 'category', 'creator:id,name']);

        return response()->json([
            'message' => 'تم إنشاء عميل الشركة وربط العرض وترحيل المديونية بنجاح',
            'offer' => $offer,
            'company' => $company,
        ], 201);
    }

    private function completeMissingGl(
        Offers $offer,
        User $user,
        OfferDebtAccountingService $offerDebtAccounting
    ) {
        if ($offerDebtAccounting->hasGlPosted((int) $offer->id)) {
            return response()->json([
                'message' => 'تم ربط هذا العرض وترحيل المديونية مسبقاً ولا يمكن تكرار العملية',
            ], 422);
        }

        $company = customerCompany::find($offer->customer_company_id);
        if (! $company) {
            return response()->json(['message' => 'عميل الشركة المرتبط غير موجود'], 422);
        }

        try {
            $amount = round((float) ($offer->debt_amount ?: $offer->total), 2);
            $offerDebtAccounting->postOfferDebt($offer, $company, $amount, (int) $user->id);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'فشل ترحيل قيد الشجرة: ' . $e->getMessage(),
            ], 500);
        }

        $offer->load(['customerCompany:id,name,phone1,phone2,balance,tree_account_id', 'category', 'creator:id,name']);

        return response()->json([
            'message' => 'تم استكمال قيد المديونية على حساب الشجرة بنجاح',
            'offer' => $offer,
        ], 200);
    }

    private function attachCompanyAndPostDebt(
        Offers $offer,
        customerCompany $company,
        User $user,
        AccountLinkingService $accountLinking,
        OfferDebtAccountingService $offerDebtAccounting
    ): void {
        VatCalculator::applyToOffer($offer);
        $amount = round((float) $offer->total, 3);
        if ($amount <= 0) {
            throw new \InvalidArgumentException('لا يمكن ترحيل مديونية لأن إجمالي العرض صفر أو سالب');
        }

        if ($offer->debt_posted_at !== null) {
            throw new \InvalidArgumentException('تم ربط هذا العرض وترحيل المديونية مسبقاً');
        }

        DB::transaction(function () use ($offer, $company, $amount, $user, $accountLinking, $offerDebtAccounting) {
            $accountLinking->ensureCustomerCompanyAccount($company);
            $company->refresh();

            $offer->customer_company_id = $company->id;
            if (trim((string) $offer->quote) === '') {
                $offer->quote = $company->name;
            }
            if (trim((string) ($offer->client_phone ?? '')) === '' && ! empty($company->phone1)) {
                $offer->client_phone = $company->phone1;
            }

            $details = 'مديونية من عرض سعر رقم ' . $offer->id;
            DB::statement('CALL update_customer_company_balance(?, ?, ?, ?, ?, ?, ?, ?)', [
                $company->id,
                $amount,
                null,
                (int) $offer->id,
                $details,
                'عروض أسعار',
                (int) $user->id,
                now(),
            ]);

            $offerDebtAccounting->postOfferDebt($offer, $company, (float) $amount, (int) $user->id);

            $offer->debt_amount = $amount;
            $offer->debt_posted_at = now();
            $offer->save();
        });
    }

    private function normalizeEgyptMobile(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if (str_starts_with($digits, '20') && strlen($digits) >= 12) {
            $digits = substr($digits, 2);
        }
        if ($digits !== '' && ! str_starts_with($digits, '0') && strlen($digits) === 10) {
            $digits = '0' . $digits;
        }

        return $digits;
    }

    /**
     * تحليل بنود العرض: هل الأصناف موجودة؟ هل لها وصفات؟ مع مطابقة تتجاهل فرق المسافات.
     */
    public function productGaps($id, OfferProductMatchService $matcher)
    {
        $user = Auth::user();
        $offer = Offers::query()->where('id', $id)->with('category')->first();

        if ($offer === null) {
            return response()->json(['message' => 'عرض السعر غير موجود'], 404);
        }

        if (! $this->canAccessOffer($offer, $user)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        return response()->json($matcher->analyze($offer), 200);
    }

    /**
     * إنشاء الأصناف الناقصة من بنود عرض السعر (مع منع تكرار الأسماء المتشابهة مسافاتياً).
     */
    public function createMissingCategories(Request $request, $id, OfferProductMatchService $matcher)
    {
        $user = Auth::user();
        $offer = Offers::query()->where('id', $id)->with('category')->first();

        if ($offer === null) {
            return response()->json(['message' => 'عرض السعر غير موجود'], 404);
        }

        if (! $this->canAccessOffer($offer, $user)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $request->validate([
            'offer_line_ids' => ['nullable', 'array'],
            'offer_line_ids.*' => ['integer'],
        ]);

        try {
            $result = $matcher->createMissingCategories(
                $offer,
                $request->filled('offer_line_ids')
                    ? array_map('intval', $request->input('offer_line_ids'))
                    : null
            );
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'فشل إنشاء الأصناف: ' . $e->getMessage(),
            ], 500);
        }

        $analysis = $matcher->analyze($offer->fresh(['category']));

        return response()->json([
            'message' => 'تم تنفيذ إنشاء الأصناف الناقصة',
            'created' => $result['created'],
            'skipped' => $result['skipped'],
            'analysis' => $analysis,
        ], 200);
    }

    /**
     * ربط بند عرض يدوياً بصنف موجود (اسم مختلف في النظام).
     */
    public function linkMatchedCategory(Request $request, $id, OfferProductMatchService $matcher)
    {
        $user = Auth::user();
        $offer = Offers::query()->where('id', $id)->with('category')->first();

        if ($offer === null) {
            return response()->json(['message' => 'عرض السعر غير موجود'], 404);
        }

        if (! $this->canAccessOffer($offer, $user)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $data = $request->validate([
            'offer_line_id' => ['required', 'integer'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
        ]);

        try {
            $result = $matcher->linkMatchedCategory(
                $offer,
                (int) $data['offer_line_id'],
                (int) $data['category_id']
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'فشل ربط الصنف: ' . $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'message' => 'تم ربط البند بالصنف المختار',
            'line' => $result['line'],
            'analysis' => $result['analysis'],
        ], 200);
    }

    /**
     * إلغاء الربط اليدوي لبند عرض.
     */
    public function clearMatchedCategory(Request $request, $id, OfferProductMatchService $matcher)
    {
        $user = Auth::user();
        $offer = Offers::query()->where('id', $id)->with('category')->first();

        if ($offer === null) {
            return response()->json(['message' => 'عرض السعر غير موجود'], 404);
        }

        if (! $this->canAccessOffer($offer, $user)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $data = $request->validate([
            'offer_line_id' => ['required', 'integer'],
        ]);

        try {
            $result = $matcher->clearMatchedCategory($offer, (int) $data['offer_line_id']);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'فشل إلغاء الربط: ' . $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'message' => 'تم إلغاء الربط اليدوي',
            'line' => $result['line'],
            'analysis' => $result['analysis'],
        ], 200);
    }

    /**
     * ربط عرض السعر بـ Lead من المبيعات الشركات.
     */
    public function linkLead(Request $request, $id)
    {
        $user = Auth::user();
        $request->validate([
            'corporate_sales_lead_id' => ['required', 'integer', 'exists:corporate_sales_leads,id'],
        ]);

        $offer = Offers::query()->where('id', $id)->first();
        if ($offer === null) {
            return response()->json(['message' => 'عرض السعر غير موجود'], 404);
        }

        if (! $this->canAccessOffer($offer, $user) && ! $this->canAccessCorporateLeads($user)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $leadId = (int) $request->corporate_sales_lead_id;
        $previousLeadId = $offer->corporate_sales_lead_id ? (int) $offer->corporate_sales_lead_id : null;

        $offer->corporate_sales_lead_id = $leadId;
        $offer->save();

        if ($previousLeadId !== $leadId) {
            $this->recordLeadQuotationTracking($leadId, $offer, $user, $previousLeadId);
        }

        $offer->load([
            'category',
            'creator:id,name',
            'customerCompany:id,name,phone1,phone2,balance',
            'lead:id,company_name',
        ]);

        return response()->json([
            'message' => 'تم ربط عرض السعر بالـ Lead',
            'offer' => $offer,
        ], 200);
    }

    /**
     * تحويل عرض السعر إلى طلب شركة جديد (مع ربط العميل وترحيل المديونية مرة واحدة).
     */
    public function convertToOrder(Request $request, $id, OfferConvertToOrderService $converter)
    {
        $user = Auth::user();
        $offer = Offers::query()->where('id', $id)->with(['category', 'customerCompany'])->first();

        if ($offer === null) {
            return response()->json(['message' => 'عرض السعر غير موجود'], 404);
        }

        if (! $this->canAccessOffer($offer, $user)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $request->validate([
            'customer_company_id' => ['nullable', 'integer', 'exists:customer_companies,id'],
            'shipping_method_id' => ['nullable', 'integer', 'exists:shipping_methods,id'],
            'order_source_id' => ['nullable', 'integer', 'exists:order_sources,id'],
            'order_date' => ['nullable', 'date'],
            'delivery_date' => ['nullable', 'date'],
            'shipping_cost' => ['nullable', 'numeric', 'min:0'],
            'prepaid_amount' => ['nullable', 'numeric', 'min:0'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'vat' => ['nullable', 'numeric', 'min:0'],
            'total_invoice' => ['nullable', 'numeric', 'min:0'],
            'net_total' => ['nullable', 'numeric'],
            'delivery_mode' => ['nullable', 'string', 'in:later,self_pickup,client_rep,system'],
            'shipping_note' => ['nullable', 'string', 'max:2000'],
            'order_notes' => ['nullable', 'string', 'max:2000'],
            'governorate' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
            'customer_name' => ['nullable', 'string', 'max:255'],
            'customer_phone_1' => ['nullable', 'string', 'max:30'],
            'customer_phone_2' => ['nullable', 'string', 'max:30'],
            'order_details' => ['required', 'array', 'min:1'],
            'order_details.*.category_id' => ['required', 'integer', 'exists:categories,id'],
            'order_details.*.quantity' => ['required', 'numeric', 'min:0.001'],
            'order_details.*.price' => ['required', 'numeric', 'min:0'],
            'order_details.*.total' => ['required', 'numeric', 'min:0'],
            'order_details.*.special_details' => ['nullable', 'string'],
        ]);

        try {
            $result = $converter->convert($offer, $request->all());
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'فشل تحويل العرض إلى طلب: ' . $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'message' => 'تم تحويل عرض السعر إلى طلب بنجاح'
                . ($result['debt_posted_now'] ? ' مع ترحيل المديونية' : ' (المديونية كانت مرحّلة مسبقاً)'),
            'order_id' => $result['order']->id,
            'offer' => $result['offer'],
            'debt_posted_now' => $result['debt_posted_now'],
            'delivery_modes' => OfferConvertToOrderService::DELIVERY_MODES,
        ], 201);
    }

    /**
     * استكمال قيد الشجرة لعروض رُحّلت مديونيتها التشغيلية سابقاً بدون قيد.
     */
    public function syncDebtGl($id, OfferDebtAccountingService $offerDebtAccounting)
    {
        $user = Auth::user();
        $offer = Offers::query()->where('id', $id)->first();

        if ($offer === null) {
            return response()->json(['message' => 'عرض السعر غير موجود'], 404);
        }

        if (! $this->canAccessOffer($offer, $user)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        if ($offer->debt_posted_at === null || ! $offer->customer_company_id) {
            return response()->json(['message' => 'العرض غير مربوط بعميل شركة بعد'], 422);
        }

        if ($offerDebtAccounting->hasGlPosted((int) $offer->id)) {
            return response()->json(['message' => 'قيد الشجرة موجود مسبقاً'], 200);
        }

        $company = customerCompany::find($offer->customer_company_id);
        if (! $company) {
            return response()->json(['message' => 'عميل الشركة غير موجود'], 422);
        }

        try {
            $amount = round((float) ($offer->debt_amount ?: $offer->total), 2);
            $offerDebtAccounting->postOfferDebt($offer, $company, $amount, (int) $user->id);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'فشل ترحيل قيد الشجرة: ' . $e->getMessage(),
            ], 500);
        }

        $offer->load(['customerCompany:id,name,phone1,phone2,balance,tree_account_id', 'category', 'creator:id,name']);

        return response()->json([
            'message' => 'تم ترحيل قيد المديونية على حساب الشجرة بنجاح',
            'offer' => $offer,
        ], 200);
    }

    public function store(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'categories' => ['required', 'array', 'min:1'],
            'quote' => ['required', 'string', 'min:1'],
            'contact_person' => ['required', 'string', 'min:1'],
            'client_phone' => ['nullable', 'string', 'max:30'],
            'id' => ['nullable', 'integer', 'exists:offers,id'],
            'corporate_sales_lead_id' => ['nullable', 'integer', 'exists:corporate_sales_leads,id'],
            'update_converted_order' => ['nullable', 'boolean'],
        ], [
            'quote.required' => 'اسم العميل مطلوب في عرض السعر',
            'contact_person.required' => 'اسم مسؤول التواصل مطلوب في عرض السعر',
        ]);

        $payload = $request->only(self::OFFER_ATTRIBUTES);

        foreach (['quote', 'contact_person', 'client_phone'] as $field) {
            if (array_key_exists($field, $payload)) {
                $payload[$field] = trim((string) $payload[$field]);
            }
        }

        if (($payload['client_phone'] ?? '') === '') {
            $payload['client_phone'] = null;
        }

        if (array_key_exists('corporate_sales_lead_id', $payload)) {
            $leadId = (int) ($payload['corporate_sales_lead_id'] ?? 0);
            $payload['corporate_sales_lead_id'] = $leadId > 0 ? $leadId : null;
        }

        $subtotal = 0.0;
        $normalizedCategories = [];
        foreach ($request->input('categories', []) as $category) {
            if (! is_array($category)) {
                continue;
            }
            $lineTotal = VatCalculator::lineTotal(
                (float) ($category['category_quantity'] ?? 0),
                (float) ($category['new_category_price'] ?? 0)
            );
            $category['total_price'] = $lineTotal;
            $normalizedCategories[] = $category;
            $subtotal += $lineTotal;
        }

        $totals = VatCalculator::offerTotals(
            $subtotal,
            (float) ($payload['transportation'] ?? 0),
            (float) ($payload['vat'] ?? 0)
        );
        $payload = array_merge($payload, $totals);

        $offerId = $request->filled('id') ? (int) $request->input('id') : null;

        if ($offerId) {
            if (! $this->canEditOffers($user)) {
                return response()->json([
                    'message' => 'ليس لديك صلاحية تعديل عرض السعر أو أصنافه',
                ], 403);
            }

            $existing = Offers::findOrFail($offerId);
            if (! $this->canAccessOffer($existing, $user)) {
                return response()->json(['message' => 'Forbidden'], 403);
            }

            // العرض المحوّل قابل للتعديل؛ المديونية المرحّلة تُقفل فقط قبل التحويل
            if (! $existing->converted_order_id && $existing->debt_posted_at !== null) {
                return response()->json([
                    'message' => 'لا يمكن تعديل عرض تم ترحيل مديونيته — راجع المحاسبة أولاً',
                ], 422);
            }
        }

        $updateConvertedOrder = $request->boolean('update_converted_order');
        $existingLines = $offerId
            ? OffersCategory::where('offer_id', $offerId)->get()
            : collect();
        $existingById = $existingLines->keyBy('id');
        $existingByName = $existingLines->keyBy(
            static fn ($line) => trim((string) ($line->category_name ?? ''))
        );

        $data = DB::transaction(function () use (
            $request,
            $user,
            $payload,
            $offerId,
            $existingById,
            $existingByName,
            $normalizedCategories
        ) {
            if ($offerId) {
                $offer = Offers::findOrFail($offerId);
                $offer->update($payload);
                OffersCategory::where('offer_id', $offerId)->delete();
            } else {
                $offer = Offers::create(array_merge($payload, [
                    'user_id' => $user->id,
                ]));
            }

            foreach ($normalizedCategories as $index => $category) {
                $img_name = '';

                if ($request->hasFile("categories.$index.image")) {
                    $img = $request->file("categories.$index.image");
                    $img_name = time() . "_category_{$index}." . $img->extension();
                    $img->move(public_path('images'), $img_name);
                }

                if (isset($category['original_image'])) {
                    $img_name = $category['original_image'];
                }

                OffersCategory::create([
                    'offer_id' => $offer->id,
                    'category_name' => $category['category_name'] ?? '',
                    'category_quantity' => $category['category_quantity'] ?? 0,
                    'old_category_price' => $category['old_category_price'] ?? 0,
                    'new_category_price' => $category['new_category_price'] ?? 0,
                    'total_price' => $category['total_price'] ?? 0,
                    'description' => $category['description'] ?? '',
                    'category_image' => $img_name,
                    'matched_category_id' => $this->resolvePreservedMatchedCategoryId(
                        $category,
                        $existingById,
                        $existingByName
                    ),
                ]);
            }

            return $offer;
        });

        if (! $offerId && ! empty($data->corporate_sales_lead_id)) {
            $this->recordLeadQuotationTracking((int) $data->corporate_sales_lead_id, $data, $user, null);
        }

        $orderUpdated = false;
        $orderUpdateMessage = null;
        $unmatchedProducts = [];
        $needsProductLink = false;
        if ($offerId && $updateConvertedOrder && $data->converted_order_id) {
            try {
                $sync = app(OfferSyncConvertedOrderService::class)->sync(
                    $data->fresh(['category', 'customerCompany'])
                );
                $orderUpdated = (bool) ($sync['updated'] ?? false);
                $orderUpdateMessage = $sync['message'] ?? null;
                $unmatchedProducts = $sync['unmatched_products'] ?? [];
                $needsProductLink = (bool) ($sync['needs_product_link'] ?? false);
            } catch (\InvalidArgumentException $e) {
                $orderUpdateMessage = $e->getMessage();
            } catch (\Throwable $e) {
                $orderUpdateMessage = 'تم حفظ العرض لكن فشل تحديث الطلب: ' . $e->getMessage();
            }
        }

        $message = 'success';
        if ($offerId) {
            $message = $orderUpdated
                ? ($orderUpdateMessage ?: 'تم تعديل العرض وتحديث الطلب المحوّل')
                : 'تم تعديل عرض السعر';
        }

        return response()->json([
            'message' => $message,
            'id' => $data->id,
            'updated' => (bool) $offerId,
            'order_updated' => $orderUpdated,
            'order_update_message' => $orderUpdateMessage,
            'converted_order_id' => $data->converted_order_id,
            'unmatched_products' => $unmatchedProducts,
            'needs_product_link' => $needsProductLink,
        ], $offerId ? 200 : 201);
    }

    /**
     * مزامنة الطلب المحوّل من العرض، مع خيار إنشاء الأصناف الناقصة أولاً.
     */
    public function syncConvertedOrder(Request $request, $id, OfferSyncConvertedOrderService $syncer)
    {
        $user = Auth::user();
        $offer = Offers::query()->where('id', $id)->with(['category', 'customerCompany'])->first();

        if ($offer === null) {
            return response()->json(['message' => 'عرض السعر غير موجود'], 404);
        }

        if (! $this->canAccessOffer($offer, $user)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        if (! $this->canEditOffers($user)) {
            return response()->json([
                'message' => 'ليس لديك صلاحية تعديل عرض السعر أو أصنافه',
            ], 403);
        }

        if (! $offer->converted_order_id) {
            return response()->json(['message' => 'العرض غير محوّل إلى طلب'], 422);
        }

        $request->validate([
            'create_missing_categories' => ['nullable', 'boolean'],
        ]);

        try {
            $result = $syncer->sync($offer, [
                'create_missing' => $request->boolean('create_missing_categories'),
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'فشل تحديث الطلب المحوّل: ' . $e->getMessage(),
            ], 500);
        }

        $status = ! empty($result['updated']) ? 200 : 422;

        return response()->json([
            'message' => $result['message'] ?? 'تم التنفيذ',
            'order_updated' => (bool) ($result['updated'] ?? false),
            'order_id' => $result['order_id'] ?? $offer->converted_order_id,
            'unmatched_products' => $result['unmatched_products'] ?? [],
            'needs_product_link' => (bool) ($result['needs_product_link'] ?? false),
            'debt_adjusted' => (bool) ($result['debt_adjusted'] ?? false),
        ], ! empty($result['needs_product_link']) ? 200 : $status);
    }

    /**
     * الإبقاء على الربط اليدوي للصنف عند إعادة حفظ بنود العرض.
     *
     * @param  array<string,mixed>  $category
     * @param  \Illuminate\Support\Collection<int, OffersCategory>  $existingById
     * @param  \Illuminate\Support\Collection<string, OffersCategory>  $existingByName
     */
    private function resolvePreservedMatchedCategoryId(
        array $category,
        $existingById,
        $existingByName
    ): ?int {
        $incoming = (int) ($category['matched_category_id'] ?? 0);
        if ($incoming > 0) {
            return $incoming;
        }

        $lineId = (int) ($category['id'] ?? 0);
        if ($lineId > 0 && $existingById->has($lineId)) {
            $preserved = (int) ($existingById->get($lineId)->matched_category_id ?? 0);
            if ($preserved > 0) {
                return $preserved;
            }
        }

        $nameKey = trim((string) ($category['category_name'] ?? ''));
        if ($nameKey !== '' && $existingByName->has($nameKey)) {
            $preserved = (int) ($existingByName->get($nameKey)->matched_category_id ?? 0);
            if ($preserved > 0) {
                return $preserved;
            }
        }

        return null;
    }

    /**
     * حذف عرض سعر بالكامل مع أصنافه.
     * ممنوع إذا حُوّل لطلب أو رُحّلت مديونيته.
     */
    public function destroy($id)
    {
        $user = Auth::user();

        if (! $this->canDeleteOffers($user)) {
            return response()->json([
                'message' => 'ليس لديك صلاحية حذف عروض الأسعار',
            ], 403);
        }

        $offer = Offers::query()->where('id', $id)->first();
        if ($offer === null) {
            return response()->json(['message' => 'عرض السعر غير موجود'], 404);
        }

        if (! $this->canAccessOffer($offer, $user)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        if ($offer->converted_order_id) {
            return response()->json([
                'message' => 'لا يمكن حذف عرض محوّل إلى طلب رقم ' . $offer->converted_order_id,
            ], 422);
        }

        if ($offer->debt_posted_at !== null) {
            return response()->json([
                'message' => 'لا يمكن حذف عرض تم ترحيل مديونيته على عميل الشركة',
            ], 422);
        }

        DB::transaction(function () use ($offer) {
            OffersCategory::where('offer_id', $offer->id)->delete();
            $offer->delete();
        });

        return response()->json(['message' => 'تم حذف عرض السعر بنجاح'], 200);
    }

    private function recordLeadQuotationTracking(
        int $leadId,
        Offers $offer,
        ?User $user,
        ?int $previousLeadId
    ): void {
        if (! CorporateSalesLead::query()->where('id', $leadId)->exists()) {
            return;
        }

        $details = $previousLeadId ? 'link Quotation' : 'add Quotation';
        $newValue = 'Offer #' . $offer->id;
        if ($previousLeadId) {
            $newValue .= ' (from Lead #' . $previousLeadId . ')';
        }

        CorporateSalesTracking::create([
            'type' => $previousLeadId ? 'edit' : 'add',
            'details' => $details,
            'old_value' => $previousLeadId ? (string) $previousLeadId : null,
            'new_value' => $newValue,
            'corporate_sales_lead_id' => $leadId,
            'user_id' => $user?->id,
        ]);
    }
}
