<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class CreateUpdateBankBalanceProcedure extends Migration
{
    public function up()
    {
        $procedure = "
            CREATE PROCEDURE update_bank_balance(
                IN bank_id INT,
                IN amount DOUBLE,
                IN order_id INT,
                IN user_id INT,
                IN details VARCHAR(255),
                IN ref VARCHAR(255),
                IN type VARCHAR(255),
                IN p_created_at TIMESTAMP
            )
            BEGIN
                DECLARE current_balance DOUBLE;

                -- Get the current balance
                SELECT balance INTO current_balance
                FROM banks
                WHERE id = bank_id;

                -- Update the bank balance
                UPDATE banks
                SET balance = balance + amount
                WHERE id = bank_id;

                -- Insert the transaction details
                INSERT INTO bank_details (
                    bank_id, details, ref, type, amount, balance_before, balance_after, date, created_at, user_id
                ) VALUES (
                    bank_id, details, ref, type, amount, current_balance, current_balance + amount, CURDATE(), p_created_at, user_id
                );
            END
        ";
        DB::unprepared('DROP PROCEDURE IF EXISTS update_bank_balance');
        DB::unprepared($procedure);
    }

    public function down()
    {
        DB::unprepared('DROP PROCEDURE IF EXISTS update_bank_balance');
    }
}

