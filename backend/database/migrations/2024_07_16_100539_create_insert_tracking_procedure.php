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
        ";
        DB::unprepared('DROP PROCEDURE IF EXISTS insert_tracking');
        DB::unprepared($procedure);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::unprepared('DROP PROCEDURE IF EXISTS insert_tracking');
    }
};
