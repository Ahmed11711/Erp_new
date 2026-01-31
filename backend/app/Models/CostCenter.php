<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CostCenter extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'name_en',
        'code',
        'type',
        'parent_id',
        'responsible_person_id',
        'location',
        'phone',
        'email',
        'start_date',
        'end_date',
        'duration',
        'value',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'value' => 'decimal:2',
    ];

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function responsiblePerson()
    {
        return $this->belongsTo(Employee::class, 'responsible_person_id');
    }
}

