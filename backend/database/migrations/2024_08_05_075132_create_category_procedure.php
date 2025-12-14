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
            CREATE PROCEDURE category_procedure(
                IN category_id INT,
                IN invoice_number VARCHAR(255),
                IN type VARCHAR(255),
                IN p_quantity DOUBLE,
                IN price DOUBLE,
                IN `by` VARCHAR(255),
                IN p_created_at TIMESTAMP
            )
            BEGIN
                DECLARE current_quantity DOUBLE;

                -- Get the current quantity
                SELECT quantity INTO current_quantity
                FROM categories
                WHERE id = category_id;

                -- Update the category quantity
                UPDATE categories
                SET quantity = current_quantity + p_quantity
                WHERE id = category_id;

                -- Insert the transaction details
                INSERT INTO categories_balance (
                    invoice_number, category_id, type, quantity, balance_before, balance_after, price, total_price, created_at, `by`
                ) VALUES (
                    invoice_number, category_id, type, ABS(p_quantity), current_quantity, current_quantity + p_quantity, price, price * ABS(p_quantity), p_created_at, `by`
                );
            END
        ";
        DB::unprepared('DROP PROCEDURE IF EXISTS category_procedure');
        DB::unprepared($procedure);
    }

    public function down()
    {
        DB::unprepared('DROP PROCEDURE IF EXISTS category_procedure');
    }
};
