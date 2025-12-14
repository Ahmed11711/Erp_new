<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;


return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $procedure = "
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

                -- Get the current balance
                SELECT balance INTO current_balance
                FROM customer_companies
                WHERE id = p_company_id;

                -- Update the company balance
                UPDATE customer_companies
                SET balance = balance + p_amount
                WHERE id = p_company_id;

                -- Insert the transaction details
                INSERT INTO customer_company_details (
                    bank_id, customer_company_id, ref, details, type, amount, balance_before, balance_after, date, created_at, user_id
                ) VALUES (
                    p_bank_id, p_company_id, p_ref, p_details, p_type, p_amount, current_balance, current_balance + p_amount, CURDATE(), p_created_at, p_user_id
                );
            END
        ";
        DB::unprepared('DROP PROCEDURE IF EXISTS update_customer_company_balance');
        DB::unprepared($procedure);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::unprepared('DROP PROCEDURE IF EXISTS update_customer_company_balance');
    }
};
