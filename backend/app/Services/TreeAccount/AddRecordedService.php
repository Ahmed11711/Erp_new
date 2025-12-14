<?php

namespace App\Services\TreeAccount;

use App\Models\Bank;
use App\Models\TreeAccount;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AddRecordedService
{
    protected int $parentId = 422;     // Parent Account ID
    protected int $baseCode = 1031001; // أول كود للـ Child

    /**
     * Add new bank under parent
     */
    public function addBank(string $name)
    {
        return DB::transaction(function () use ($name) {

            // 1- Get parent
            $parent = TreeAccount::find($this->parentId);

            if (!$parent) {
                throw new \Exception("Parent account not found (ID: {$this->parentId})");
            }

            // 2- Get last child under this parent (locked for safe increment)
            $lastChild = TreeAccount::where('parent_id', $parent->id)
                ->orderByDesc('code')
                ->lockForUpdate()
                ->first();

            // 3- Generate next unique code
            $code = $lastChild ? $lastChild->code + 1 : $this->baseCode;

            // 4- Create new child
            $treeAccount = TreeAccount::create([
                'name'      => $name,
                'code'      => $code,
                'parent_id' => $parent->id,
                'type'      => 'asset',
                'level'     => $parent->level + 1,
                'balance'   => 0,
            ]);

            return $treeAccount;
        });
    }


    /**
     * Check existing bank OR create new one
     */
    public function checkFoundBank(string $name)
    {
        $existing = TreeAccount::where('name', $name)
            ->where('parent_id', $this->parentId)
            ->first();

        if ($existing) {
            return $existing->id;
        }

        $newBank = $this->addBank($name);
        return $newBank->id;
    }

    public function getBankById($id)
    {
       $bank=Bank::find($id);
         return $bank->name;
    }

}
