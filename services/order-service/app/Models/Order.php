<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Order extends Model
{
    protected $guarded = []; 

    // Relasi ke tabel users 
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Relasi ke tabel order_items 
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    // Relasi ke tabel payments 
    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class);
    }
}