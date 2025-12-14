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
        ";
        DB::unprepared('DROP PROCEDURE IF EXISTS notification_sender');
        DB::unprepared($procedure);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::unprepared('DROP PROCEDURE IF EXISTS notification_sender');
    }
};

