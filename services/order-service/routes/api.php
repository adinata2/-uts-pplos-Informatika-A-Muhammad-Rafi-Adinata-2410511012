<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

// --- 1. GET: Melihat Semua Pesanan ---
Route::get('/orders', function (Request $request) {
    // Ambil email dari header
    $userEmail = $request->header('x-user-email');

    // 1. Ambil data induk pesanan
    $query = DB::table('orders')->orderBy('created_at', 'desc');
    
    if ($userEmail) {
      $query->where('user_email', $userEmail);
     }
    
    $orders = $query->get();
    $formattedOrders = [];

    // 2. Loop setiap pesanan untuk menempelkan rincian barang dan tagihan
    foreach ($orders as $order) {
        
        // Ambil barang dari order_items
        $items = DB::table('order_items')
            ->where('order_id', $order->id)
            ->select('product_id', 'quantity', 'price')
            ->get();

        // Ambil status tagihan dari payments
        $payment = DB::table('payments')
            ->where('order_id', $order->id)
            ->select('payment_method', 'status')
            ->first();

        
        $formattedOrders[] = [
            'order_id' => $order->id,
            'user_email' => $order->user_email,
            'total_price' => $order->total_price,
            'payment_method' => $payment ? $payment->payment_method : 'Tidak diketahui',
            'payment_status' => $payment ? $payment->status : 'Tidak diketahui',
            'order_date' => $order->created_at,
            'items' => $items // Ini akan jadi array di dalam JSON
        ];
    }

    return response()->json([
        'status' => 'success',
        'pembeli_saat_ini' => $userEmail ?? 'Guest',
        'jumlah_pesanan' => count($formattedOrders),
        'data' => $formattedOrders
    ]);
});

// --- 2. POST: Membuat Pesanan, Potong Stok, & Buat Tagihan ---
Route::post('/orders', function (Request $request) {
    $userEmail = $request->header('x-user-email');

    if (!$userEmail) {
        return response()->json(['status' => 'error', 'message' => 'Unauthorized. Email tidak ditemukan di header.'], 401);
    }

    $request->validate([
        'items' => 'required|array|min:1',
        'items.*.product_id' => 'required|integer',
        'items.*.quantity' => 'required|integer|min:1',
        'items.*.price' => 'required|numeric',
        'payment_method' => 'nullable|string'
    ]);

    $items = $request->input('items');
    $paymentMethod = $request->input('payment_method', 'Transfer Bank');

    $totalPrice = 0;
    foreach ($items as $item) {
        $totalPrice += ($item['price'] * $item['quantity']);
    }

    DB::beginTransaction();

    try {
        // --- TABEL 1: Sinkronisasi ke tabel 'users' ---
        $user = DB::table('users')->where('email', $userEmail)->first();
        if (!$user) {
            DB::table('users')->insert([
                'email' => $userEmail,
                'name' => explode('@', $userEmail)[0],
                'password' => bcrypt('dummy_password'), 
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // --- TABEL 2: Buat Induk Pesanan di tabel 'orders' ---
        $orderId = DB::table('orders')->insertGetId([
            'user_email' => $userEmail,
            'total_price' => $totalPrice,
            'product_id' => 0, // Bypass error struktur lama
            'quantity' => 0,   // Bypass error struktur lama
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // --- TABEL 3: Loop setiap barang ke 'order_items' & Potong Stok ---
        foreach ($items as $item) {
            $response = Http::post("http://127.0.0.1:8080/api/products/{$item['product_id']}/reduce-stock", [
                'quantity' => $item['quantity']
            ]);

            if ($response->failed()) {
                DB::rollBack();
                return response()->json([
                    'status' => 'error',
                    'message' => "Gagal checkout. Stok Produk ID {$item['product_id']} tidak mencukupi."
                ], 400);
            }

            DB::table('order_items')->insert([
                'order_id' => $orderId,
                'product_id' => $item['product_id'],
                'quantity' => $item['quantity'],
                'price' => $item['price'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // --- TABEL 4: Terbitkan Tagihan di tabel 'payments' ---
        DB::table('payments')->insert([
            'order_id' => $orderId,
            'amount' => $totalPrice,
            'payment_method' => $paymentMethod,
            'status' => 'Pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::commit();

        return response()->json([
            'status' => 'success',
            'message' => 'Checkout berhasil! data telah ditambahkan ke table.',
            'detail' => [
                'order_id' => $orderId,
                'total_pembayaran' => $totalPrice,
                'jumlah_macam_barang' => count($items)
            ]
        ]);

    } catch (\Exception $e) {
        DB::rollBack();
        return response()->json([
            'status' => 'error',
            'message' => 'Terjadi kesalahan sistem internal: ' . $e->getMessage()
        ], 500);
    }
});

// --- 3. DELETE: Membatalkan Pesanan & Kembalikan Stok (Multi-Item) ---
Route::delete('/orders/{id}', function ($id) {
    // 1. Cek apakah pesanan ada di tabel orders
    $order = DB::table('orders')->where('id', $id)->first();

    if (!$order) {
        return response()->json(['status' => 'error', 'message' => 'Order tidak ditemukan'], 404);
    }

    // 2. Ambil semua rincian barang dari order_items
    $orderItems = DB::table('order_items')->where('order_id', $id)->get();

    // MULAI TRANSAKSI
    DB::beginTransaction();

    try {
        // 3. Loop untuk mengembalikan stok SETIAP barang ke Product Service
        foreach ($orderItems as $item) {
            $response = Http::post("http://127.0.0.1:8080/api/products/{$item->product_id}/add-stock", [
                'quantity' => $item->quantity
            ]);

            // Jika ada satu saja yang gagal dikembalikan, batalkan proses hapus
            if ($response->failed()) {
                DB::rollBack();
                return response()->json([
                    'status' => 'error',
                    'message' => "Gagal mengembalikan stok untuk Produk ID {$item->product_id}."
                ], 500);
            }
        }

        // 4. Hapus data dari tabel 'anak' dulu (payments & order_items)
        DB::table('payments')->where('order_id', $id)->delete();
        DB::table('order_items')->where('order_id', $id)->delete();
        
        // 5. Baru hapus 'induk'-nya (orders)
        DB::table('orders')->where('id', $id)->delete();

        DB::commit();

        return response()->json([
            'status' => 'success',
            'message' => "Order #$id berhasil dibatalkan. Tagihan dihapus dan stok semua barang telah dikembalikan.",
            'pembeli' => $order->user_email
        ]);

    } catch (\Exception $e) {
        DB::rollBack();
        return response()->json([
            'status' => 'error',
            'message' => 'Terjadi kesalahan sistem internal: ' . $e->getMessage()
        ], 500);
    }
});
