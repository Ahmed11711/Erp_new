<?php

use App\Models\Employee;

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$allEmps = Employee::select('id', 'name', 'code', 'acc_no')->get();
echo "Total Employees: " . $allEmps->count() . "\n";
foreach ($allEmps as $emp) {
    echo "ID: {$emp->id}, Name: {$emp->name}, Code: {$emp->code}, Acc No: '{$emp->acc_no}'\n";
}
