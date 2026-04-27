<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * تحديث category_procedure: خصم/إضافة total_price حسب متوسط التكلفة الحالي (وليس سعر البيع).
     * سجلات categories_balance تبقى بسعر البيع للتحليل والمبيعات؛ قيمة المخزون في categories تصبح متسقة مع التقارير.
     */
    public function up(): void
    {
        $procedure = <<<'SQL'
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

    INSERT INTO categories_balance (
        invoice_number, category_id, type, quantity, balance_before, balance_after, price, total_price, created_at, `by`
    ) VALUES (
        invoice_number, category_id, type, ABS(p_quantity), current_quantity, current_quantity + p_quantity, price, price * ABS(p_quantity), p_created_at, `by`
    );
END
SQL;

        DB::unprepared('DROP PROCEDURE IF EXISTS category_procedure');
        DB::unprepared($procedure);
    }

    public function down(): void
    {
        $procedure = <<<'SQL'
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

    SELECT quantity INTO current_quantity
    FROM categories
    WHERE id = category_id;

    UPDATE categories
    SET quantity = current_quantity + p_quantity
    WHERE id = category_id;

    INSERT INTO categories_balance (
        invoice_number, category_id, type, quantity, balance_before, balance_after, price, total_price, created_at, `by`
    ) VALUES (
        invoice_number, category_id, type, ABS(p_quantity), current_quantity, current_quantity + p_quantity, price, price * ABS(p_quantity), p_created_at, `by`
    );
END
SQL;

        DB::unprepared('DROP PROCEDURE IF EXISTS category_procedure');
        DB::unprepared($procedure);
    }
};
