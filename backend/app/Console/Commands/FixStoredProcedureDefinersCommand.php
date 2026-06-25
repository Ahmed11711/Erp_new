<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * إعادة إنشاء الإجراءات المخزنة بعد استيراد قاعدة من استضافة أخرى.
 * الخطأ 1449: The user specified as a definer (...) does not exist
 */
class FixStoredProcedureDefinersCommand extends Command
{
    protected $signature = 'db:fix-procedure-definers';

    protected $description = 'Recreate stored procedures with the current DB user as definer (fixes MySQL error 1449 after DB import)';

    public function handle(): int
    {
        $this->recreateShippingCompanyProcedure();
        $this->recreateUpdateCustomerCompanyBalanceProcedure();
        $this->recreateUpdateBankBalanceProcedure();
        $this->recreateInsertNoteProcedure();
        $this->recreateInsertTrackingProcedure();
        $this->recreateNotificationSenderProcedure();
        $this->recreateCategoryProcedure();

        $this->info('Stored procedures recreated with current definer.');

        return self::SUCCESS;
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
        $this->line('  shipping_company_procedure');
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
        $this->line('  update_customer_company_balance');
    }

    private function recreateUpdateBankBalanceProcedure(): void
    {
        $procedure = <<<'SQL'
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

    SELECT balance INTO current_balance
    FROM banks
    WHERE id = bank_id;

    UPDATE banks
    SET balance = balance + amount
    WHERE id = bank_id;

    INSERT INTO bank_details (
        bank_id, details, ref, type, amount, balance_before, balance_after, date, created_at, user_id
    ) VALUES (
        bank_id, details, ref, type, amount, current_balance, current_balance + amount, CURDATE(), p_created_at, user_id
    );
END
SQL;

        DB::unprepared('DROP PROCEDURE IF EXISTS update_bank_balance');
        DB::unprepared($procedure);
        $this->line('  update_bank_balance');
    }

    private function recreateInsertNoteProcedure(): void
    {
        $procedure = <<<'SQL'
CREATE PROCEDURE insert_note(
    IN p_order_id INT,
    IN p_user_id INT,
    IN p_note TEXT,
    IN p_added_from VARCHAR(255),
    IN p_created_at TIMESTAMP
)
BEGIN
    INSERT INTO notes (order_id, user_id, note, added_from, created_at, updated_at)
    VALUES (p_order_id, p_user_id, p_note, p_added_from, p_created_at, p_created_at);
END
SQL;

        DB::unprepared('DROP PROCEDURE IF EXISTS insert_note');
        DB::unprepared($procedure);
        $this->line('  insert_note');
    }

    private function recreateInsertTrackingProcedure(): void
    {
        $procedure = <<<'SQL'
CREATE PROCEDURE insert_tracking(
    IN p_order_id INT,
    IN p_action VARCHAR(255),
    IN p_user_id INT,
    IN p_created_at TIMESTAMP
)
BEGIN
    INSERT INTO trackings (order_id, date, action, user_id, created_at, updated_at)
    VALUES (p_order_id, CURDATE(), p_action, p_user_id, p_created_at, p_created_at);
END
SQL;

        DB::unprepared('DROP PROCEDURE IF EXISTS insert_tracking');
        DB::unprepared($procedure);
        $this->line('  insert_tracking');
    }

    private function recreateNotificationSenderProcedure(): void
    {
        $procedure = <<<'SQL'
CREATE PROCEDURE notification_sender(
    IN p_send_from INT,
    IN p_send_to INT,
    IN p_type VARCHAR(255),
    IN p_ref VARCHAR(255),
    IN p_order_id INT,
    IN p_note VARCHAR(255),
    IN p_created_at TIMESTAMP
)
BEGIN
    DECLARE new_id INT;

    INSERT INTO notifications (send_from, send_to, type, ref, order_id, note, created_at, updated_at)
    VALUES (p_send_from, p_send_to, p_type, p_ref, p_order_id, p_note, p_created_at, p_created_at);

    SET new_id = LAST_INSERT_ID();

    UPDATE notifications
    SET notification_number = CONCAT('NF', new_id)
    WHERE id = new_id;
END
SQL;

        DB::unprepared('DROP PROCEDURE IF EXISTS notification_sender');
        DB::unprepared($procedure);
        $this->line('  notification_sender');
    }

    private function recreateCategoryProcedure(): void
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
        $this->line('  category_procedure');
    }
}
