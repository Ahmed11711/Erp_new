<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds media-related columns and performance indexes to the messages table
 * so the WhatsApp-Web chat UI can load conversations with cursor pagination,
 * search by text, and render inline media (image/video/audio/document).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            if (! Schema::hasColumn('messages', 'type')) {
                $table->string('type', 32)->default('text')->after('content');
            }
            if (! Schema::hasColumn('messages', 'media_id')) {
                $table->string('media_id', 191)->nullable()->after('type');
            }
            if (! Schema::hasColumn('messages', 'media_mime_type')) {
                $table->string('media_mime_type', 128)->nullable()->after('media_id');
            }
            if (! Schema::hasColumn('messages', 'media_filename')) {
                $table->string('media_filename', 255)->nullable()->after('media_mime_type');
            }
            if (! Schema::hasColumn('messages', 'media_caption')) {
                $table->text('media_caption')->nullable()->after('media_filename');
            }
            if (! Schema::hasColumn('messages', 'phone_number_id')) {
                $table->string('phone_number_id', 64)->nullable()->after('twilio_message_sid');
            }
        });

        // Indexes (wrapped in try/catch to stay idempotent on re-runs).
        $this->safeIndex('messages', ['customer_id', 'created_at'], 'messages_customer_created_idx');
        $this->safeIndex('messages', ['customer_id', 'id'], 'messages_customer_id_idx');
        $this->safeIndex('messages', ['type'], 'messages_type_idx');
        $this->safeIndex('messages', ['media_id'], 'messages_media_id_idx');
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            foreach (['messages_customer_created_idx', 'messages_customer_id_idx', 'messages_type_idx', 'messages_media_id_idx'] as $idx) {
                try {
                    $table->dropIndex($idx);
                } catch (\Throwable $e) {
                    // index may not exist
                }
            }

            foreach (['type', 'media_id', 'media_mime_type', 'media_filename', 'media_caption', 'phone_number_id'] as $col) {
                if (Schema::hasColumn('messages', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }

    private function safeIndex(string $table, array $columns, string $name): void
    {
        try {
            Schema::table($table, function (Blueprint $t) use ($columns, $name) {
                $t->index($columns, $name);
            });
        } catch (\Throwable $e) {
            // Index likely already exists — ignore on re-runs.
        }
    }
};
