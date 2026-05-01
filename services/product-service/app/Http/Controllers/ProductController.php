<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;
use Illuminate\Support\Facades\Validator; 

class ProductController extends Controller
{
    // 1. Fungsi index: SEKARANG DENGAN PAGING & FILTERING
    public function index(Request $request)
    {
        $query = Product::query();

        // Fitur Filtering: Mencari berdasarkan nama produk jika ada parameter 'search'
        if ($request->has('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        // Fitur Paging: Mengambil per_page dari request, default 10
        $perPage = $request->input('per_page', 10);
        $products = $query->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'data' => $products
        ]);
    }

    // 2. Fungsi show: Melihat detail produk
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

    // 3. Fungsi store DENGAN VALIDASI 
    public function store(Request $request)
    {
        // Validasi input di layer controller
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'stock' => 'required|integer|min:0',
            'description' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        $product = Product::create($request->all());
        
        return response()->json([
            'status' => 'success',
            'message' => 'Produk berhasil ditambahkan',
            'data' => $product
        ], 201);
    }

    // 4. Fungsi update DENGAN VALIDASI
    public function update(Request $request, $id)
    {
        $product = Product::find($id);

        if (!$product) {
            return response()->json([
                'status' => 'error',
                'message' => 'Produk tidak ditemukan'
            ], 404);
        }

        // Validasi input saat update
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'price' => 'sometimes|required|numeric|min:0',
            'stock' => 'sometimes|required|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        $product->update($request->all());

        return response()->json([
            'status' => 'success',
            'message' => 'Produk berhasil diperbarui',
            'data' => $product
        ]);
    }

    // 5. Fungsi destroy: Menghapus produk
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