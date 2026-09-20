<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $permTable = config('permission.table_names.permissions');
        $roleTable = config('permission.table_names.roles');

        Schema::table($permTable, function (Blueprint $table) use ($permTable) {
            $table->string('module')->nullable()->after('guard_name');
            $table->string('slug', 191)->nullable()->after('module');
            $table->text('description')->nullable()->after('slug');
            $table->unique(['slug', 'guard_name']);
        });

        Schema::table($roleTable, function (Blueprint $table) use ($roleTable) {
            $table->string('slug', 191)->nullable()->after('guard_name');
            $table->text('description')->nullable()->after('slug');
            $table->unique(['slug', 'guard_name']);
        });

        $rows = DB::table($permTable)->select('id', 'name')->get();
        $usedPermSlugs = [];
        foreach ($rows as $row) {
            $base = Str::slug(str_replace(['.', '_'], ' ', $row->name), '.');
            if ($base === '') {
                $base = 'permission';
            }
            $slug = $base;
            $n = 1;
            while (isset($usedPermSlugs[$slug])) {
                $slug = $base.'.'.$n++;
            }
            $usedPermSlugs[$slug] = true;
            $module = explode('.', $slug)[0] ?? 'general';
            DB::table($permTable)->where('id', $row->id)->update([
                'slug' => $slug,
                'module' => $module,
            ]);
        }

        $roles = DB::table($roleTable)->select('id', 'name')->get();
        $usedRoleSlugs = [];
        foreach ($roles as $role) {
            $base = Str::slug($role->name, '-');
            if ($base === '') {
                $base = 'role';
            }
            $slug = $base;
            $n = 1;
            while (isset($usedRoleSlugs[$slug])) {
                $slug = $base.'-'.$n++;
            }
            $usedRoleSlugs[$slug] = true;
            DB::table($roleTable)->where('id', $role->id)->update(['slug' => $slug]);
        }
    }

    public function down(): void
    {
        $permTable = config('permission.table_names.permissions');
        $roleTable = config('permission.table_names.roles');

        Schema::table($permTable, function (Blueprint $table) {
            $table->dropUnique(['slug', 'guard_name']);
            $table->dropColumn(['module', 'slug', 'description']);
        });

        Schema::table($roleTable, function (Blueprint $table) {
            $table->dropUnique(['slug', 'guard_name']);
            $table->dropColumn(['slug', 'description']);
        });
    }
};
