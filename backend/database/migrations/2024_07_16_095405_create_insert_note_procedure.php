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
        ";
        DB::unprepared('DROP PROCEDURE IF EXISTS insert_note');
        DB::unprepared($procedure);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::unprepared('DROP PROCEDURE IF EXISTS insert_note');
    }
};
