<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * إعادة إنشاء category_procedure بعد استيراد DB من استضافة أخرى.
 * الخطأ 1449: definer 'u660470583_Magalis'@'127.0.0.1' does not exist
 */
return new class extends Migration
{
    public function up(): void
    {
        $insertWithCostCols = Schema::hasColumn('categories_balance', 'unit_cost')
            && Schema::hasColumn('categories_balance', 'cost_total');

        $insertBlock = $insertWithCostCols
            ? <<<'SQL'
    INSERT INTO categories_balance (
        invoice_number, category_id, type, quantity, balance_before, balance_after, price, total_price, unit_cost, cost_total, created_at, `by`
    ) VALUES (
        invoice_number, category_id, type, ABS(p_quantity), current_quantity, current_quantity + p_quantity, price, price * ABS(p_quantity), avg_cost, ABS(p_quantity) * avg_cost, p_created_at, `by`
    );
SQL
            : <<<'SQL'
    INSERT INTO categories_balance (
        invoice_number, category_id, type, quantity, balance_before, balance_after, price, total_price, created_at, `by`
    ) VALUES (
        invoice_number, category_id, type, ABS(p_quantity), current_quantity, current_quantity + p_quantity, price, price * ABS(p_quantity), p_created_at, `by`
    );
SQL;

        $procedure = <<<SQL
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
    DECLARE current_quantity DOUBLE DEFAULT 0;
    DECLARE current_total_price DOUBLE DEFAULT 0;
    DECLARE current_unit_price DOUBLE DEFAULT 0;
    DECLARE avg_cost DOUBLE DEFAULT 0;

    SELECT quantity, total_price, IFNULL(unit_price, 0)
    INTO current_quantity, current_total_price, current_unit_price
    FROM categories
    WHERE id = category_id;

    IF current_quantity IS NULL THEN
        SET current_quantity = 0;
    END IF;
    IF current_total_price IS NULL THEN
        SET current_total_price = 0;
    END IF;

    IF current_quantity > 0.0000001 THEN
        SET avg_cost = current_total_price / current_quantity;
    ELSEIF current_unit_price > 0.0000001 THEN
        SET avg_cost = current_unit_price;
    ELSE
        SET avg_cost = 0;
    END IF;

    UPDATE categories
    SET
        quantity = current_quantity + p_quantity,
        total_price = current_total_price + (p_quantity * avg_cost)
    WHERE id = category_id;

    UPDATE categories
    SET total_price = 0
    WHERE id = category_id AND quantity <= 0;

    UPDATE categories
    SET unit_price = CASE
        WHEN quantity > 0.0000001 THEN total_price / quantity
        ELSE unit_price
    END
    WHERE id = category_id;

{$insertBlock}
END
SQL;

        DB::unprepared('DROP PROCEDURE IF EXISTS category_procedure');
        DB::unprepared($procedure);
    }

    public function down(): void
    {
        DB::unprepared('DROP PROCEDURE IF EXISTS category_procedure');
    }
};
