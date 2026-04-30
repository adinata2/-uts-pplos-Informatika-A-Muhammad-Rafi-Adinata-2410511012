<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProductController; // Memanggil Controller kita

// Jalur (Route) untuk melihat barang
Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/{id}', [ProductController::class, 'show']);

// Jalur (Route) untuk menambah, mengubah, dan menghapus barang
Route::post('/products', [ProductController::class, 'store']);
Route::put('/products/{id}', [ProductController::class, 'update']);
Route::delete('/products/{id}', [ProductController::class, 'destroy']);