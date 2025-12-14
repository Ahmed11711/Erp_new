<?php

namespace App\Services\Command;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RequestGenerator
{
    public static function make(string $model)
    {
        try {
            // 🧠 تحديد الجدول
            $modelClass = "App\\Models\\{$model}";
            if (class_exists($modelClass)) {
                $table = (new $modelClass)->getTable();
            } else {
                $table = Str::snake(Str::pluralStudly($model));
            }

            // ✅ تحديد المسارات
            $baseFolder  = app_path("Http/Requests/V2");
            $modelFolder = "{$baseFolder}/{$model}";
            File::ensureDirectoryExists($modelFolder);

            $storeRequestPath  = "{$modelFolder}/{$model}StoreRequest.php";
            $updateRequestPath = "{$modelFolder}/{$model}UpdateRequest.php";

            // ❌ تحقق من وجود الجدول
            if (!Schema::hasTable($table)) {
                return "❌ Table '{$table}' does not exist in database!";
            }

            // 🧱 جلب الأعمدة
            $columns = Schema::getColumnListing($table);

            // ⚙️ إنشاء قواعد الفاليديشن
            $generateRules = function ($isUpdate = false) use ($columns, $table, $model) {
                $rules = [];
                $routeParam = Str::camel($model);
                $skip = ['id', 'created_at', 'updated_at', 'deleted_at'];

                foreach ($columns as $col) {
                    if (in_array($col, $skip)) continue;

                    $info = DB::selectOne("
                        SELECT COLUMN_TYPE, IS_NULLABLE
                        FROM INFORMATION_SCHEMA.COLUMNS
                        WHERE TABLE_NAME = ? AND COLUMN_NAME = ?
                          AND TABLE_SCHEMA = DATABASE()
                    ", [$table, $col]);

                    if (!$info) continue;

                    $type = $info->COLUMN_TYPE;
                    $nullable = $info->IS_NULLABLE === 'YES';
                    $rule = '';

                    if (preg_match('/^varchar\((\d+)\)$/i', $type, $m)) $rule = "string|max:{$m[1]}";
                    elseif (preg_match('/^enum\((.+)\)$/i', $type, $m))
                        $rule = 'in:' . implode(',', array_map(fn($v) => trim($v, " '\""), explode(',', $m[1])));
                    elseif (in_array($type, ['text', 'mediumtext', 'longtext'])) $rule = 'string';
                    elseif (preg_match('/int|bigint/i', $type)) $rule = 'integer';
                    elseif (preg_match('/tinyint\(1\)/i', $type)) $rule = 'boolean';
                    elseif (preg_match('/decimal|float|double/i', $type)) $rule = 'numeric';
                    elseif (preg_match('/date|datetime|timestamp/i', $type)) $rule = 'date';
                    elseif ($type === 'json') $rule = 'array';

                    if (Str::endsWith($col, '_id')) {
                        $related = Str::snake(Str::plural(Str::replaceLast('_id', '', $col)));
                        if (Schema::hasTable($related)) {
                            $rule .= ($rule ? '|' : '') . "exists:{$related},id";
                        }
                    }

                    if (preg_match('/(image|img|file|attachment|photo|picture)/i', $col)) {
                        $rule .= ($rule ? '|' : '') . 'file|max:2048';
                    }

                    $unique = DB::select("SHOW INDEX FROM {$table} WHERE Column_name='{$col}' AND Non_unique=0");
                    if (!empty($unique) && !Str::endsWith($col, '_id')) {
                        $rule .= $isUpdate
                            ? ($rule ? '|' : '') . "unique:{$table},{$col},'.\$this->route('{$routeParam}').',id"
                            : ($rule ? '|' : '') . "unique:{$table},{$col}";
                    }

                    $prefix = $isUpdate
                        ? ($nullable ? 'nullable|sometimes' : 'sometimes|required')
                        : ($nullable ? 'nullable' : 'required');

                    $rules[$col] = "{$prefix}" . ($rule ? "|{$rule}" : '');
                }

                return $rules;
            };

            // 🧾 إنشاء الملفات
            $storeRules = $generateRules(false);
            $updateRules = $generateRules(true);

            $storeStub = self::generateStub("{$model}StoreRequest", $storeRules, "V2\\{$model}");
            $updateStub = self::generateStub("{$model}UpdateRequest", $updateRules, "V2\\{$model}");

            File::put($storeRequestPath, $storeStub);
            File::put($updateRequestPath, $updateStub);

            return "✅ Requests for {$model} created successfully under V2 folder!";
        } catch (\Throwable $e) {
            return "❌ Error: " . $e->getMessage();
        }
    }

    private static function generateStub($className, $rules, $namespace)
    {
        $rulesString = "";
        foreach ($rules as $key => $value) {
            $rulesString .= "            '{$key}' => '{$value}',\n";
        }

        return "<?php

namespace App\\Http\\Requests\\{$namespace};
use App\Http\Requests\BaseRequest\BaseRequest;
class {$className} extends BaseRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
{$rulesString}        ];
    }
}
";
    }
}
