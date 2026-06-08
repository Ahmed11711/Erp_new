<?php

namespace App\Services\Shipping;

use RuntimeException;

/**
 * تُرمى عند محاولة شحن طلب بينما إحدى جهات التحصيل/الشحن غير مرتبطة
 * بحساب ذمم في شجرة الحسابات — مما يمنع تسجيل المديونيات بشكل صحيح.
 */
class UnlinkedReceivableAccountException extends RuntimeException
{
}
