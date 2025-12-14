<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AccountEntry extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function assets()
    {
        return $this->belongsTo(TreeAccount::class,'tree_account_id','id');
    }
}
