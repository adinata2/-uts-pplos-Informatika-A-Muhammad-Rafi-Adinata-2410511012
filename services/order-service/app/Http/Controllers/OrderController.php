<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    // Fungsi untuk melihat semua pesanan
    public function index()
    {
        $orders = Order::all();
        return response()->json([
            'status' => 'success', 
            'data' => $orders
        ]);
    }

    // Fungsi untuk menambah pesanan baru
    public function store(Request $request)
    {
        $order = Order::create([
            'product_id' => $request->product_id,
            'quantity' => $request->quantity,
            'total_price' => $request->total_price,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Pesanan berhasil dibuat!',
            'data' => $order
        ], 201);
    }
}