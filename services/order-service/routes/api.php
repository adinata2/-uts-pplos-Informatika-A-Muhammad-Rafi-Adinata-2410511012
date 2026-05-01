<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

// --- 1. GET: Melihat Semua Pesanan ---
Route::get('/orders', function (Request $request) {
    $userEmail = $request->header('x-user-email');
    $allOrders = DB::table('orders')->get();

    return response()->json([
        'status' => 'success',
        'pembeli_saat_ini' => $userEmail ?? 'Guest',
        'data' => $allOrders
    ]);
});

// --- 2. POST: Membuat Pesanan & Potong Stok ---
Route::post('/orders', function (Request $request) {
    $userEmail = $request->header('x-user-email') ?? 'adinata@upnvj.ac.id';

    $data = $request->validate([
        'product_id' => 'required|integer',
        'quantity' => 'required|integer',
        'total_price' => 'required|numeric',
    ]);

    // Lapor ke Product Service untuk kurangi stok
    $response = Http::post("http://127.0.0.1:8080/api/products/{$data['product_id']}/reduce-stock", [
        'quantity' => $data['quantity']
    ]);

    if ($response->failed()) {
        return response()->json([
            'status' => 'error',
            'message' => 'Gagal potong stok. Stok mungkin habis!'
        ], 400);
    }

    // Simpan ke database jika stok aman
    $orderId = DB::table('orders')->insertGetId([
        'user_email' => $userEmail,
        'product_id' => $data['product_id'],
        'quantity' => $data['quantity'],
        'total_price' => $data['total_price'],
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return response()->json([
        'status' => 'success',
        'message' => 'Pesanan berhasil dan stok terpotong',
        'detail' => [
            'order_id' => $orderId,
            'pembeli' => $userEmail,
            'jumlah' => $data['quantity']
        ]
    ]);
});

// --- 3. DELETE: Membatalkan Pesanan & Kembalikan Stok ---
Route::delete('/orders/{id}', function ($id) {
    // PENTING: Ambil data sebelum dihapus
    $order = DB::table('orders')->where('id', $id)->first();

    if (!$order) {
        return response()->json(['status' => 'error', 'message' => 'Order tidak ditemukan'], 404);
    }

    // Kirim perintah balikkan stok ke Product Service
    $response = Http::post("http://127.0.0.1:8080/api/products/{$order->product_id}/add-stock", [
        'quantity' => $order->quantity
    ]);

    if ($response->successful()) {
        // Hapus permanen dari database orders HANYA JIKA stok berhasil balik
        DB::table('orders')->where('id', $id)->delete();

        return response()->json([
            'status' => 'success',
            'message' => "Order #$id dihapus, stok dikembalikan ke Produk ID $order->product_id",
            'pembeli' => $order->user_email,
            'jumlah_dikembalikan' => $order->quantity
        ]);
    }

    return response()->json(['status' => 'error', 'message' => 'Gagal koneksi ke Product Service'], 500);
});