<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Color extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'hex',
    ];

    /** Finished / semi-finished products that list this shade as an available variant. */
    public function manufacturedOnItems(): BelongsToMany
    {
        return $this->belongsToMany(Item::class, 'category_color', 'color_id', 'category_id')->withTimestamps();
    }
}
