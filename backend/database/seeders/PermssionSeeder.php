<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class PermssionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Permission::create(['name' => 'read warehouse']);
        Permission::create(['name' => 'read category']);
        Permission::create(['name' => 'read supplier']);
        Permission::create(['name' => 'read purchase']);
        Permission::create(['name' => 'read manufacturing']);
        Permission::create(['name' => 'read shipping']);
        Permission::create(['name' => 'read report']);
        Permission::create(['name' => 'read receipt']);
        Permission::create(['name' => 'read system']);
        Permission::create(['name' => 'read Hr']);
        Permission::create(['name' => 'read Accounting']);
    }
}
