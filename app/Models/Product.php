<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'sub_category_id',
        'name',
        'description',
        'thumbnail',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function subCategory()
    {
        return $this->belongsTo(SubCategory::class);
    }
    public function variants()
    {
        return $this->hasMany(ProductVariant::class);
    }
}
