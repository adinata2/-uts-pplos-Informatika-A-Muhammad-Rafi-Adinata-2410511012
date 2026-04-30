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

    // 3. Fungsi untuk menambah produk baru
    public function store(Request $request)
    {
        $product = Product::create($request->all());
        
        return response()->json([
            'status' => 'success',
            'message' => 'Produk berhasil ditambahkan',
            'data' => $product
        ], 201);
    }

    // 4. Fungsi untuk mengubah data produk
    public function update(Request $request, $id)
    {
        $product = Product::find($id);

        if (!$product) {
            return response()->json([
                'status' => 'error',
                'message' => 'Produk tidak ditemukan'
            ], 404);
        }

        $product->update($request->all());

        return response()->json([
            'status' => 'success',
            'message' => 'Produk berhasil diperbarui',
            'data' => $product
        ]);
    }

    // 5. Fungsi untuk menghapus produk
    public function destroy($id)
    {
        $product = Product::find($id);

        if (!$product) {
            return response()->json([
                'status' => 'error',
                'message' => 'Produk tidak ditemukan'
            ], 404);
        }

        $product->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Produk berhasil dihapus'
        ]);
    }
}