<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        $this->call([
            TreeAccountSeeder::class,
            // StockSeeder::class
        ]);

        // User::create([
        //     'name' => 'Hossam',
        //     'email' => 'hossam@magalis.com',
        //     'department' => 'Admin',
        //     'password' => \Hash::make('hossam506050'),
        // ]);
        // $this->call(PermssionSeeder::class);
    }
}
