const express = require('express');
const mysql = require('mysql2/promise');
const bcrypt = require('bcryptjs');
const jwt = require('jsonwebtoken');

const app = express();
app.use(express.json()); 
const PORT = 3001;
const JWT_SECRET = 'fiyw37dnjdnakieuat297s';
const REFRESH_SECRET = 'jayhw87wja10smkua71';

// Konfigurasi Database ke Docker
const dbConfig = {
    host: 'localhost',
    user: 'root',
    password: 'root',
    database: 'auth_db'
};

let pool;

// Fungsi inisialisasi database dan pembuatan tabel otomatis
async function initDB() {
    try {
        pool = mysql.createPool(dbConfig);
        await pool.query(`
            CREATE TABLE IF NOT EXISTS users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                email VARCHAR(255) UNIQUE NOT NULL,
                password VARCHAR(255),
                picture VARCHAR(500),
                oauth_provider VARCHAR(50),
                refresh_token TEXT
            )
        `);
        console.log('Database auth_db terhubung dan tabel users siap.');
    } catch (error) {
        console.error('Gagal koneksi database:', error);
    }
}
initDB();

// --- ENDPOINT: REGISTER ---
app.post('/register', async (req, res) => {
    const { name, email, password } = req.body;
    
    // Validasi input
    if (!name || !email || !password) {
        return res.status(400).json({ error: 'Nama, email, dan password wajib diisi' });
    }

    try {
        // Enkripsi password sebelum disimpan ke database
        const hashedPassword = await bcrypt.hash(password, 10);
        
        await pool.query(
            'INSERT INTO users (name, email, password) VALUES (?, ?, ?)',
            [name, email, hashedPassword]
        );
        res.status(201).json({ message: 'Registrasi berhasil, silakan login.' });
    } catch (error) {
        // Tangkap error jika email sudah ada di database
        if (error.code === 'ER_DUP_ENTRY') {
            return res.status(409).json({ error: 'Email sudah terdaftar' });
        }
        res.status(500).json({ error: 'Terjadi kesalahan server internal' });
    }
});

app.post('/login', async (req, res) => {
    const { email, password } = req.body;

    try {
        // 1. Cari user berdasarkan email
        const [rows] = await pool.query('SELECT * FROM users WHERE email = ?', [email]);
        if (rows.length === 0) return res.status(401).json({ error: 'Email atau password salah' });

        const user = rows[0];

        // 2. Cek kecocokan password dengan bcrypt
        const validPassword = await bcrypt.compare(password, user.password);
        if (!validPassword) return res.status(401).json({ error: 'Email atau password salah' });

        // 3. Buat JWT Access Token (Masa berlaku 15 menit) & Refresh Token (7 hari)
        const accessToken = jwt.sign({ id: user.id }, JWT_SECRET, { expiresIn: '15m' });
        const refreshToken = jwt.sign({ id: user.id }, REFRESH_SECRET, { expiresIn: '7d' });

        // 4. Simpan Refresh Token ke Database
        await pool.query('UPDATE users SET refresh_token = ? WHERE id = ?', [refreshToken, user.id]);

        res.json({ access_token: accessToken, refresh_token: refreshToken });
    } catch (error) {
        res.status(500).json({ error: 'Terjadi kesalahan server internal' });
    }
});

// --- ENDPOINT: REFRESH TOKEN ---
app.post('/refresh', async (req, res) => {
    const { refresh_token } = req.body;
    if (!refresh_token) return res.status(400).json({ error: 'Refresh token wajib dikirim' });

    try {
        // 1. Cek apakah refresh token valid ada di database
        const [rows] = await pool.query('SELECT * FROM users WHERE refresh_token = ?', [refresh_token]);
        if (rows.length === 0) return res.status(401).json({ error: 'Refresh token tidak valid atau Anda sudah logout' });

        const user = rows[0];

        // 2. Verifikasi masa berlaku refresh token
        jwt.verify(refresh_token, REFRESH_SECRET, (err, decoded) => {
            if (err) return res.status(403).json({ error: 'Refresh token kedaluwarsa, silakan login ulang' });

            // 3. Buat Access Token baru
            const newAccessToken = jwt.sign({ id: user.id }, JWT_SECRET, { expiresIn: '15m' });
            res.json({ access_token: newAccessToken });
        });
    } catch (error) {
        res.status(500).json({ error: 'Terjadi kesalahan server internal' });
    }
});

// --- ENDPOINT: LOGOUT ---
app.post('/logout', async (req, res) => {
    const { refresh_token } = req.body;
    if (!refresh_token) return res.status(400).json({ error: 'Refresh token wajib dikirim' });

    try {
        // Hapus refresh token dari database (Mekanisme Token Invalidation / Blacklist)
        await pool.query('UPDATE users SET refresh_token = NULL WHERE refresh_token = ?', [refresh_token]);
        res.status(200).json({ message: 'Logout berhasil' });
    } catch (error) {
        res.status(500).json({ error: 'Terjadi kesalahan server internal' });
    }
});

app.listen(PORT, () => {
    console.log(`Auth Service berjalan di http://localhost:${PORT}`);
});