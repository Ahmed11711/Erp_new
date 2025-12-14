<?php
namespace App\Filters;

use Illuminate\Http\Request;
use Carbon\Carbon;

class OrderFilters
{
    protected $request;
    protected $query;

    // Mapping عشان camelCase يشتغل مع method names
    protected $map = [
        'prepaidAmount' => 'prepaidAmount',
        'collectType' => 'collectType',
        'shippment_number' => 'shippment_number',
    ];

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public function apply($query)
    {
        $this->query = $query;

        foreach ($this->filters() as $key => $value) {
            if ($value === null || $value === '') continue;

            $method = $this->map[$key] ?? $key;

            if (method_exists($this, $method)) {
                $this->$method($value);
            }
        }

        return $this->query;
    }

    protected function filters()
    {
        // ناخد كل المفاتيح من الريكوست
        return $this->request->all();
    }

    protected function private_order($value)
    {
        if ($value === 'null') {
            $this->query->whereNull('private_order');
        } elseif ($value == '1') {
            $this->query->where('private_order', 1);
        }
    }

    protected function prepaidAmount($value)
    {
        $this->query->where('prepaid_amount', '>', 0)
                    ->where('net_total', '>', 0);
    }

    protected function paid($value)
    {
        $this->query->where('net_total', '=', 0);
    }

    protected function collectType($value)
    {
        if ($value === 'تحصيل متغير') {
            $this->query->where(function ($q) {
                $q->whereNotNull('collect_note')
                  ->orWhereNotNull('reference_number')
                  ->orWhereNotNull('reference_image');
            });
        } elseif ($value === 'تحصيل الكتروني') {
            $this->query->where(function ($q) {
                $q->whereNotNull('reference_number')
                  ->orWhereNotNull('reference_image');
            });
        }
    }

    protected function customer_type($value)
    {
        $this->query->where('customer_type', $value);
    }

    protected function order_date($value)
    {
        $this->query->where('order_date', $value);
    }

    protected function delivery_date($value)
    {
        $this->query->where('delivery_date', '<=', $value);
    }

    protected function order_type($value)
    {
        $this->query->where('order_type', $value);
    }

    protected function shipping_company_id($value)
    {
        $this->query->whereHas('order_details', fn($q) => $q->where('shipping_company_id', $value));
    }

    protected function category_id($value)
    {
        $this->query->whereHas('order_products', fn($q) => $q->where('category_id', $value));
    }

    protected function need_by_date($value)
    {
        $this->query->whereHas('order_details', fn($q) => $q->where('need_by_date', $value));
    }

    protected function status_date($value)
    {
        $this->query->whereHas('order_details', fn($q) => $q->where('status_date', $value));
    }

    protected function vip($value)
    {
        $this->query->whereHas('order_details', fn($q) => $q->where('vip', $value));
    }

    protected function reviewed($value)
    {
        $this->query->whereHas('order_details', function($q) use ($value) {
            if ($value == "2") {
                $q->where('reviewed', 1)
                  ->whereNotNull('reviewed_note');
            } else {
                $q->where('reviewed', $value);
            }
        });
    }

    protected function shortage($value)
    {
        $this->query->whereHas('order_details', fn($q) => $q->where('shortage', $value));
    }

    protected function shippment_number($value)
    {
        $this->query->whereHas('order_shipment_number', fn($q) => $q->where('shipment_number', 'like', "%$value%"));
    }

    protected function governorate($value)
    {
        $this->query->where('governorate', 'like', "%$value%");
    }

    protected function city($value)
    {
        $this->query->where('city', 'like', "%$value%");
    }

    protected function customer_name($value)
    {
        $this->query->where('customer_name', 'like', "%$value%");
    }

    protected function customer_phone($value)
    {
        $this->query->where('customer_phone_1', 'like', "%$value%");
    }

    protected function order_number($value)
    {
        $this->query->where('id', 'like', "%$value%");
    }

    protected function order_status($value)
    {
        $this->query->where('order_status', $value);
    }

    protected function order_source_id($value)
    {
        $this->query->where('order_source_id', $value);
    }

    protected function shipping_method_id($value)
    {
        $this->query->where('shipping_method_id', $value);
    }

    protected function shipping_line_id($value)
    {
        $this->query->whereHas('order_details', fn($q) => $q->where('shipping_line_id', $value));
    }
}
