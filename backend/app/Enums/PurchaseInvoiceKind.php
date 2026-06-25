<?php

namespace App\Enums;

enum PurchaseInvoiceKind: string
{
    /** اضافة وارد جديد / تم الاستلام */
    case PurchaseReceipt = 'purchase_receipt';

    /** مرتجع مبيعات — وارد للمخزون بدون ذمة مورد */
    case SalesReturn = 'sales_return';

    /** امانات — وارد أمانات للمخزون بدون ذمة مورد */
    case Amanat = 'amanat';

    /** مرتجع مشتريات — صادر من المخزون ويُخفّض ذمة المورد */
    case PurchaseReturn = 'purchase_return';
}
