<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product; // Memanggil model Product

class ProductController extends Controller
{
    // 1. Fungsi untuk melihat semua produk
    public function index()
    {
        $products = Product::all();
        return response()->json([
            'status' => 'success',
            'data' => $products
        ]);
    }

    // 2. Fungsi untuk melihat detail satu produk berdasarkan ID
    public function show($id)
    {
        $product = Product::find($id);

        if (!$product) {
            return response()->json([
                'status' => 'error',
                'message' => 'Produk tidak ditemukan'
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $product
        ]);
    }
}