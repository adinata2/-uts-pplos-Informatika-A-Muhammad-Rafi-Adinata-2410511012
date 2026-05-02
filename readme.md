# UTS Microservices E-Commerce

Sistem E-Commerce berbasis Arsitektur Microservices yang memisahkan fungsionalitas ke dalam layanan independen (Gateway, Auth, Product, dan Order). Proyek ini dilengkapi dengan pengamanan sesi (JWT & Google OAuth), Rate Limiting, dan sinkronisasi data antar-database menggunakan Database Transaction.

---

## Identitas

- **Nama:** Muhammad Rafi Adinata
- **NIM:** 2410511012
- **Kelas:** Informatika A
- **Mata Kuliah:** PEMBANGUNAN PERANGKAT LUNAK BERORIENTASI SERVICE

---

## Cara Menjalankan

Proyek ini menggunakan **Docker** untuk mempermudah instalasi *database* dan membutuhkan **Node.js** serta **PHP/Composer** untuk menjalankan servisnya. *(Catatan: Seluruh file `.env` sudah disertakan di dalam repository ini untuk kemudahan pengujian).*

### 1. Menjalankan Database (MySQL via Docker)
Buka terminal pada *root folder* proyek, lalu jalankan perintah berikut untuk menyalakan MySQL dan mengeksekusi file `init.sql` (Otomatis membuat `auth_db`, `product_db`, dan `order_db`):

docker-compose up -d

Menjalankan API Gateway (Node.js)
cd gateway
npm install
node index.js
# Berjalan di Port 3000

Menjalankan Auth Service (Node.js)
cd services/auth-service
npm install
node index.js
# Berjalan di Port 4000

Menjalankan Product Service (Laravel)
cd services/product-service
composer install
php -S 127.0.0.1:8080 -t public
# Berjalan di Port 8080

Menjalankan Order Service (Laravel)
cd services/order-service
composer install
php -S 127.0.0.1:8081 -t public
# Berjalan di Port 8081

Peta Endpoint
Auth Service
GET /api/auth/google
Fungsi: Login via Google OAuth (Akses langsung via Browser).
Autorisasi: Tidak perlu.

POST /api/auth/refresh
Fungsi: Memperbarui Access Token yang sudah kedaluwarsa.
Autorisasi: Tidak perlu.

POST /api/auth/logout
Fungsi: Menghapus token dan mengakhiri sesi pengguna.
Autorisasi: Tidak perlu.

Product Service
GET /api/product
Fungsi: Menampilkan seluruh katalog produk beserta stoknya.
Autorisasi: Butuh Bearer Token.

POST /api/products
Fungsi: Menambahkan data produk baru ke dalam database.
Autorisasi: Tidak perlu.

Order Service
GET /api/orders
Fungsi: Melihat riwayat pesanan (menampilkan detail rincian barang dan status pembayaran).
Autorisasi: Butuh Bearer Token.

POST /api/orders
Fungsi: Melakukan checkout banyak barang sekaligus (multi-item) dengan sinkronisasi otomatis ke 4 tabel.
Autorisasi: Butuh Bearer Token.

DELETE /api/orders/{id}
Fungsi: Membatalkan pesanan berdasarkan ID, menghapus tagihan, dan mengembalikan stok barang (rollback otomatis).
Autorisasi: Butuh Bearer Token.


Link Youtube
https://youtu.be/FNv1txZm8n0
