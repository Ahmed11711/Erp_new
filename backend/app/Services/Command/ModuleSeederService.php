<?php

namespace App\Services;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Artisan;

class ModelSeederService
{
    public static function make(string $model)
    {
        // 🧱 تحديد اسم الجدول بناءً على اسم الموديل
        $table = Str::snake(Str::pluralStudly($model));

        if (!Schema::hasTable($table)) {
            return "❌ Table '{$table}' does not exist in database!";
        }

        // 🧾 تحديد المسار داخل هيكل MVC العادي
        $seederPath = database_path("seeders/{$model}Seeder.php");

        if (File::exists($seederPath)) {
            return "⚠️ {$model}Seeder already exists!";
        }

        // 📊 الحصول على الأعمدة
        $columns = Schema::getColumnListing($table);

        // 🧩 توليد محتوى الـ Seeder
        $seederStub = self::generateStub($model, $columns, $table);

        // 📝 إنشاء الملف
        File::put($seederPath, $seederStub);

        // 🔁 تحديث الـ DatabaseSeeder الأساسي
        self::updateMainSeeder($model);

        // 🚀 تشغيل Seeder مباشرة
        $seederClass = "Database\\Seeders\\{$model}Seeder";
        Artisan::call('db:seed', [
            '--class' => $seederClass,
            '--force' => true
        ]);

        return "✅ {$model}Seeder created and executed successfully.";
    }

    private static function generateStub($model, $columns, $table)
    {
        $rows = "        \${$table} = [\n";

        for ($i = 1; $i <= 5; $i++) {
            $dataString = "            [\n";

            foreach ($columns as $col) {
                if (in_array($col, ['id', 'created_at', 'updated_at'])) continue;

                // 🔗 الأعمدة المنتهية بـ _id (علاقات)
                if (Str::endsWith($col, '_id')) {
                    $value = rand(1, 3);
                    $dataString .= "                '{$col}' => {$value},\n";
                    continue;
                }

                // 🧠 نوع العمود
                $columnType = self::getColumnType($table, $col);

                // 📦 JSON columns
                if ($columnType === 'json') {
                    $dataString .= "                '{$col}' => [\n";
                    $dataString .= "                    'en' => 'Sample {$col} {$i}',\n";
                    $dataString .= "                    'ar' => 'عينة {$col} {$i}'\n";
                    $dataString .= "                ],\n";
                    continue;
                }

                // 🎯 ENUM columns
                if ($columnType === 'enum') {
                    $enumValues = self::getEnumValues($table, $col);
                    $value = $enumValues[0] ?? 'default';
                    $dataString .= "                '{$col}' => '{$value}',\n";
                    continue;
                }

                // 🔢 أرقام
                if (in_array($columnType, ['integer', 'int', 'bigint', 'smallint', 'tinyint'])) {
                    $value = rand(1, 1000);
                    $dataString .= "                '{$col}' => {$value},\n";
                }
                // 💰 أرقام عشرية
                elseif (in_array($columnType, ['float', 'double', 'decimal'])) {
                    $value = number_format(rand(100, 10000) / 100, 2, '.', '');
                    $dataString .= "                '{$col}' => {$value},\n";
                }
                // 📅 تاريخ فقط
                elseif ($columnType === 'date') {
                    $value = now()->subYears(rand(1, 10))->format('Y-m-d');
                    $dataString .= "                '{$col}' => '{$value}',\n";
                }
                // ⏰ تاريخ مع وقت
                elseif (in_array($columnType, ['datetime', 'timestamp'])) {
                    $value = now()->subDays(rand(1, 500))->format('Y-m-d H:i:s');
                    $dataString .= "                '{$col}' => '{$value}',\n";
                }
                // 🕒 وقت فقط
                elseif ($columnType === 'time') {
                    $value = now()->subMinutes(rand(1, 600))->format('H:i:s');
                    $dataString .= "                '{$col}' => '{$value}',\n";
                }
                // 🔤 نصوص
                else {
                    $value = "Sample {$col} {$i}";
                    $dataString .= "                '{$col}' => '{$value}',\n";
                }
            }

            $dataString .= "            ],\n";
            $rows .= $dataString;
        }

        $rows .= "        ];\n\n";
        $rows .= "        foreach (\${$table} as \$data) {\n";
        $rows .= "            {$model}::firstOrCreate(\$data);\n";
        $rows .= "        }\n";

        return "<?php

namespace Database\\Seeders;

use Illuminate\\Database\\Seeder;
use App\\Models\\{$model};

class {$model}Seeder extends Seeder
{
    public function run(): void
    {
{$rows}    }
}
";
    }

    private static function getEnumValues($table, $column)
    {
        $type = DB::selectOne("SHOW COLUMNS FROM {$table} WHERE Field = '{$column}'")->Type ?? '';
        preg_match("/^enum\('(.*)'\)$/", $type, $matches);
        return isset($matches[1]) ? explode("','", $matches[1]) : [];
    }

    private static function updateMainSeeder($model)
    {
        $mainSeederPath = database_path("seeders/DatabaseSeeder.php");

        if (!File::exists($mainSeederPath)) {
            File::put($mainSeederPath, "<?php

namespace Database\\Seeders;

use Illuminate\\Database\\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Seeders will be added here
    }
}
");
        }

        $content = File::get($mainSeederPath);
        $seederClass = "{$model}Seeder::class";

        if (!str_contains($content, $seederClass)) {
            if (preg_match('/public function run\(\): void\s*\{/', $content, $matches, PREG_OFFSET_CAPTURE)) {
                $pos = $matches[0][1] + strlen($matches[0][0]);
                $content = substr_replace($content, "\n        \$this->call({$seederClass});", $pos, 0);
                File::put($mainSeederPath, $content);
            }
        }
    }

    private static function getColumnType($table, $column)
    {
        return DB::getSchemaBuilder()->getColumnType($table, $column);
    }
}
