<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    // Membuka gembok agar kolom ini bisa diisi dari Controller
    protected $fillable = [
        'name',
        'description',
        'price',
        'stock'
    ];
}