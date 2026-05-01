<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\ProductController;

// --- Rute Standar (CRUD) ---
Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/{id}', [ProductController::class, 'show']);
Route::post('/products', [ProductController::class, 'store']);
Route::put('/products/{id}', [ProductController::class, 'update']);
Route::delete('/products/{id}', [ProductController::class, 'destroy']);

// --- Rute Tambahan untuk Sinkronisasi Stok (Dipanggil oleh Order Service) ---

// 1. Mengurangi Stok saat ada Pesanan Baru
Route::post('/products/{id}/reduce-stock', function (Request $request, $id) {
    $qty = $request->quantity;
    $product = DB::table('products')->where('id', $id)->first();

    if ($product && $product->stock >= $qty) {
        DB::table('products')->where('id', $id)->decrement('stock', $qty);
        return response()->json([
            'status' => 'success',
            'message' => 'Stok berhasil dikurangi',
            'sisa_stok' => $product->stock - $qty
        ]);
    }

    return response()->json([
        'status' => 'error',
        'message' => 'Stok tidak mencukupi atau produk tidak ditemukan'
    ], 400);
});

// 2. Menambah Stok kembali saat Pesanan Dibatalkan/Dihapus
Route::post('/products/{id}/add-stock', function (Request $request, $id) {
    $qty = $request->quantity;
    
    $exists = DB::table('products')->where('id', $id)->exists();
    
    if ($exists) {
        DB::table('products')->where('id', $id)->increment('stock', $qty);
        return response()->json([
            'status' => 'success',
            'message' => 'Stok berhasil dikembalikan'
        ]);
    }

    return response()->json([
        'status' => 'error',
        'message' => 'Produk tidak ditemukan'
    ], 404);
});