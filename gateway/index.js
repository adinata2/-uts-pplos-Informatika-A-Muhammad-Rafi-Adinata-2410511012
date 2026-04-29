const express = require('express');
const { createProxyMiddleware } = require('http-proxy-middleware');
const rateLimit = require('express-rate-limit');
const jwt = require('jsonwebtoken');
const cors = require('cors');

const app = express();
const PORT = 3000;
const JWT_SECRET = 'fiyw37dnjdnakieuat297s'; 

app.use(cors());

// 1. Basic Rate Limiting (60 req / menit)
const limiter = rateLimit({
    windowMs: 1 * 60 * 1000, 
    max: 60, 
    message: { error: 'Terlalu banyak request dari IP ini, silakan coba lagi setelah 1 menit.' }
});
app.use(limiter);

// 2. MIDDLEWARE: Verifikasi JWT
const verifyToken = (req, res, next) => {
    // Ambil token dari header Authorization (Format: Bearer <token>)
    const authHeader = req.headers['authorization'];
    const token = authHeader && authHeader.split(' ')[1];

    if (!token) return res.status(401).json({ error: 'Akses ditolak. Token tidak ditemukan.' });

    jwt.verify(token, JWT_SECRET, (err, decoded) => {
        if (err) return res.status(403).json({ error: 'Token tidak valid atau kedaluwarsa.' });
        // Sisipkan ID user ke header agar bisa dibaca oleh service di belakangnya
        req.headers['x-user-id'] = decoded.id;
        next();
    });
};

// 3. Peta Routing (Routing per Service)
// Khusus Profile WAJIB dicek tokennya
app.use('/auth/profile', verifyToken);

// 2. Semua rute /auth (baik itu profile yang sudah lolos satpam, login, atau register) diteruskan ke sini
app.use('/auth', createProxyMiddleware({ target: 'http://localhost:3001', changeOrigin: true }));

// Produk: Kalau GET (lihat produk) boleh publik. Kalau POST/PUT/DELETE wajib ada token.
app.use('/products', (req, res, next) => {
    if (req.method === 'GET') next();
    else verifyToken(req, res, next);
}, createProxyMiddleware({ target: 'http://localhost:8000', changeOrigin: true }));

// Orders: Seluruh transaksi pesanan WAJIB ada token
app.use('/orders', verifyToken, createProxyMiddleware({ target: 'http://localhost:3002', changeOrigin: true }));

app.get('/', (req, res) => res.json({ message: 'API Gateway Mini E-Commerce berjalan.' }));

app.listen(PORT, () => console.log(`API Gateway berjalan di http://localhost:${PORT}`));