<?php

namespace App\Http\Controllers\V2\Transaction;

use App\Http\Controllers\Controller;
use App\Models\AccountEntry;
use App\Models\Order;
use App\Models\Transaction;
use App\Services\Accounting\AccountLinkingService;
use Illuminate\Http\Request;

class TransactionController extends Controller
{

  public function allTransaction(Request $request)
  {

  }

    /**
     * تفاصيل حركة عميل حسب الموبايل.
     *
     * عند وجود حساب في شجرة الحسابات: يُرجع مجموع القيود على حساب العميل لكل طلب
     * (من account_entries) — متوافق مع بطاقة الحساب في الشجرة بعد التحصيل.
     *
     * إن لم يُوجد حساب شجرة: احتياطي من جدول الطلبات (net_total / prepaid) للبيانات القديمة.
     */
    public function index(Request $request)
    {
        $phone = trim((string) $request->query('customer', ''));
        $itemsPerPage = max(1, min(200, (int) $request->query('itemsPerPage', 15)));
        $page = max(1, (int) $request->query('page', 1));

        if ($phone === '') {
            return response()->json([
                'data' => [],
                'total' => 0,
                'per_page' => $itemsPerPage,
                'current_page' => 1,
            ], 200);
        }

        $variants = $this->phoneSearchVariants($phone);

        $sampleOrder = Order::query()
            ->whereIn('customer_phone_1', $variants)
            ->orderByDesc('id')
            ->first();

        if (! $sampleOrder) {
            return response()->json([
                'data' => [],
                'total' => 0,
                'per_page' => $itemsPerPage,
                'current_page' => 1,
            ], 200);
        }

        $linking = app(AccountLinkingService::class);
        $tree = $linking->findExistingCustomerTreeAccount(
            (string) ($sampleOrder->customer_type ?? 'فرد'),
            (string) ($sampleOrder->customer_name ?? ''),
            $sampleOrder->customer_phone_1,
            $sampleOrder->company_id ? (int) $sampleOrder->company_id : null
        );

        if ($tree) {
            $paginator = AccountEntry::query()
                ->where('tree_account_id', $tree->id)
                ->whereNotNull('order_id')
                ->selectRaw('order_id')
                ->selectRaw('SUM(debit) as total_debit')
                ->selectRaw('SUM(credit) as total_credit')
                ->selectRaw('MIN(created_at) as created_at')
                ->groupBy('order_id')
                ->orderByDesc('order_id')
                ->paginate($itemsPerPage, ['*'], 'page', $page);

            return response()->json($paginator, 200);
        }

        $query = Order::query()
            ->whereIn('customer_phone_1', $variants)
            ->select(
                'id as order_id',
                'created_at',
                'prepaid_amount',
                'net_total',
            )
            ->orderBy('id', 'desc');

        $legacy = $query->paginate($itemsPerPage, ['*'], 'page', $page);
        $legacy->getCollection()->transform(function ($row) {
            return (object) [
                'order_id' => $row->order_id,
                'created_at' => $row->created_at,
                'total_debit' => (float) ($row->net_total ?? 0),
                'total_credit' => (float) ($row->prepaid_amount ?? 0),
            ];
        });

        return response()->json($legacy, 200);
    }

    /**
     * @return string[]
     */
    private function phoneSearchVariants(string $phone): array
    {
        $phone = trim($phone);
        $variants = [$phone];
        if (strlen($phone) === 11 && str_starts_with($phone, '0')) {
            $variants[] = substr($phone, 1);
        }
        if (strlen($phone) === 10 && ctype_digit($phone)) {
            $variants[] = '0' . $phone;
        }
        $digits = preg_replace('/\D/', '', $phone);
        if ($digits !== '' && strlen($digits) >= 8) {
            $variants[] = $digits;
            if (str_starts_with($digits, '20') && strlen($digits) > 2) {
                $variants[] = '0' . substr($digits, 2);
            }
        }

        return array_values(array_unique(array_filter($variants, fn ($v) => $v !== '')));
    }

    public function store($data)
    {
      $transaction=Transaction::create($data);
    }
}
