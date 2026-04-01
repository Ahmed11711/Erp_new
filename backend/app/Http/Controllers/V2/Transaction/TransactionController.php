<?php

namespace App\Http\Controllers\V2\Transaction;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Transaction;
use Illuminate\Http\Request;

class TransactionController extends Controller
{

  public function allTransaction(Request $request)
  {

  }

    /**
     * تفاصيل أوامر عميل (حسب رقم الموبايل) — من جدول الطلبات مباشرةً
     * لأن جدول transactions قد لا يحتوي على كل الطلبات القديمة.
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

        $query = Order::query()
            ->whereIn('customer_phone_1', $variants)
            ->select('id as order_id', 'created_at', 'prepaid_amount', 'net_total')
            ->orderBy('id', 'desc');

        return response()->json($query->paginate($itemsPerPage, ['*'], 'page', $page), 200);
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
