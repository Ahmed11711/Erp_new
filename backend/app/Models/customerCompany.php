<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class customerCompany extends Model
{
    use HasFactory;

    protected $fillable=[
        "name",
        "phone1",
        "phone2",
        "phone3",
        "phone4",
        "tel",
        "governorate",
        "city",
        "address",
        "tree_account_id"
    ];
}
