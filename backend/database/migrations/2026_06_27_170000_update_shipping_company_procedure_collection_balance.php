<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * تحسين shipping_company_procedure:
 * - IFNULL على balance
 * - is_done=1 لسطور التحصيل/الرفض/سند القبض
 */
return new class extends Migration
{
    public function up(): void
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

    SELECT IFNULL(balance, 0) INTO current_balance
    FROM shipping_companies
    WHERE id = shipping_company_id;

    UPDATE shipping_companies
    SET balance = IFNULL(current_balance, 0) + p_amount
    WHERE id = shipping_company_id;

    INSERT INTO shipping_company_details (
        order_id, shipping_date, status, amount, shipping_company_id, is_done, created_at, updated_at, `by`
    ) VALUES (
        order_id, shipping_date, status, p_amount, shipping_company_id,
        IF(status IN ('تم التحصيل', 'رفض استلام', 'سند قبض'), 1, 0),
        p_created_at, p_created_at, `by`
    );

    SET new_id = LAST_INSERT_ID();

    UPDATE shipping_company_details
    SET ref = CONCAT('R', new_id)
    WHERE id = new_id;

    IF status = 'تم التحصيل' OR status = 'رفض استلام' OR status = 'سند قبض' THEN
        UPDATE shipping_company_details
        SET collect_date = DATE(NOW()), is_done = 1
        WHERE id = new_id;
    END IF;
END
SQL;

        DB::unprepared('DROP PROCEDURE IF EXISTS shipping_company_procedure');
        DB::unprepared($procedure);
    }

    public function down(): void
    {
        DB::unprepared('DROP PROCEDURE IF EXISTS shipping_company_procedure');
    }
};
