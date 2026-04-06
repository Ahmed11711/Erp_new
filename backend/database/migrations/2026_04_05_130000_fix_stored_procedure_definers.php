<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * إعادة إنشاء الإجراءات بعد استيراد قاعدة من استضافة أخرى.
 * الخطأ 1449: The user specified as a definer (...) does not exist
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->recreateShippingCompanyProcedure();
        $this->recreateUpdateCustomerCompanyBalanceProcedure();
    }

    public function down(): void
    {
        // لا نعيد definer السابق
    }

    private function recreateShippingCompanyProcedure(): void
    {
        $procedure = <<<'SQL'
CREATE PROCEDURE shipping_company_procedure(
    IN shipping_company_id INT,
    IN order_id INT,
    IN shipping_date DATE,
    IN status VARCHAR(255),
    IN p_amount DOUBLE,
    IN `by` VARCHAR(255),
    IN p_created_at TIMESTAMP
)
BEGIN
    DECLARE current_balance DOUBLE;
    DECLARE new_id INT;

    SELECT balance INTO current_balance
    FROM shipping_companies
    WHERE id = shipping_company_id;

    UPDATE shipping_companies
    SET balance = current_balance + p_amount
    WHERE id = shipping_company_id;

    INSERT INTO shipping_company_details (
        order_id, shipping_date, status, amount, shipping_company_id, created_at, updated_at, `by`
    ) VALUES (
        order_id, shipping_date, status, p_amount, shipping_company_id, p_created_at, p_created_at, `by`
    );

    SET new_id = LAST_INSERT_ID();

    UPDATE shipping_company_details
    SET ref = CONCAT('R', new_id)
    WHERE id = new_id;

    IF status = 'تم التحصيل' OR status = 'رفض استلام' THEN
        UPDATE shipping_company_details
        SET collect_date = DATE(NOW())
        WHERE id = new_id;
    END IF;
END
SQL;

        DB::unprepared('DROP PROCEDURE IF EXISTS shipping_company_procedure');
        DB::unprepared($procedure);
    }

    private function recreateUpdateCustomerCompanyBalanceProcedure(): void
    {
        $procedure = <<<'SQL'
CREATE PROCEDURE update_customer_company_balance(
    IN p_company_id INT,
    IN p_amount DOUBLE,
    IN p_bank_id INT,
    IN p_ref INT,
    IN p_details VARCHAR(255),
    IN p_type VARCHAR(255),
    IN p_user_id INT,
    IN p_created_at TIMESTAMP
)
BEGIN
    DECLARE current_balance DOUBLE;

    SELECT balance INTO current_balance
    FROM customer_companies
    WHERE id = p_company_id;

    UPDATE customer_companies
    SET balance = balance + p_amount
    WHERE id = p_company_id;

    INSERT INTO customer_company_details (
        bank_id, customer_company_id, ref, details, type, amount, balance_before, balance_after, date, created_at, user_id
    ) VALUES (
        p_bank_id, p_company_id, p_ref, p_details, p_type, p_amount, current_balance, current_balance + p_amount, CURDATE(), p_created_at, p_user_id
    );
END
SQL;

        DB::unprepared('DROP PROCEDURE IF EXISTS update_customer_company_balance');
        DB::unprepared($procedure);
    }
};
