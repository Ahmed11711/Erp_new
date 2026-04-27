<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Recreates shipping_company_procedure when it is missing (e.g. DB restored without routines).
 */
return new class extends Migration
{
    public function up(): void
    {
        $procedure = "
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
        ";
        DB::unprepared('DROP PROCEDURE IF EXISTS shipping_company_procedure');
        DB::unprepared($procedure);
    }

    public function down(): void
    {
        DB::unprepared('DROP PROCEDURE IF EXISTS shipping_company_procedure');
    }
};
