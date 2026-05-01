const express = require('express');
const jwt = require('jsonwebtoken');
const mysql = require('mysql2');
const bcrypt = require('bcrypt');
require('dotenv').config();
const axios = require('axios');

const app = express();
app.use(express.json());

const PORT = 4000;
const SECRET_KEY = "key34527siak278dbya72d";
const REFRESH_SECRET_KEY = "refresh_key_99887766"; 

// Koneksi ke MySQL Docker
const db = mysql.createConnection({
    host: '127.0.0.1',
    port: 3307,
    user: 'root',
    password: 'root', 
    database: 'auth_db'
});

db.connect(err => {
    if (err) console.error('Gagal konek auth_db:', err);
    else console.log('✅ Auth Service terhubung ke MySQL');
});

// --- HELPER: GENERATE TOKENS ---
const generateTokens = (user) => {
    const accessToken = jwt.sign(
        { id: user.id, email: user.email }, 
        SECRET_KEY, 
        { expiresIn: '15m' } 
    );
    const refreshToken = jwt.sign(
        { id: user.id, email: user.email }, 
        REFRESH_SECRET_KEY, 
        { expiresIn: '7d' } 
    );
    return { accessToken, refreshToken };
};

// --- RUTE DENGAN PREFIX LENGKAP (Agar sinkron dengan Gateway) ---

app.post('/api/auth/register', async (req, res) => {
    const { name, email, password } = req.body;
    try {
        const hashedPassword = await bcrypt.hash(password, 10); 
        const query = "INSERT INTO users (name, email, password, oauth_provider) VALUES (?, ?, ?, ?)";
        db.query(query, [name, email, hashedPassword, 'manual'], (err, result) => {
            if (err) return res.status(500).json({ error: err.message });
            res.json({ status: "success", message: "User berhasil didaftarkan!" });
        });
    } catch (e) {
        res.status(500).json({ error: "Gagal registrasi" });
    }
});

app.post('/api/auth/login', (req, res) => {
    const { email, password } = req.body;
    const query = "SELECT * FROM users WHERE email = ?";

    db.query(query, [email], async (err, results) => {
        if (err || results.length === 0) return res.status(401).json({ message: "User tidak ditemukan" });

        const user = results[0];
        const match = await bcrypt.compare(password, user.password); 

        if (match) {
            const tokens = generateTokens(user);
            res.json({ status: "success", ...tokens });
        } else {
            res.status(401).json({ message: "Password salah!" });
        }
    });
});

app.post('/api/auth/refresh', (req, res) => {
    const { refreshToken } = req.body;
    if (!refreshToken) return res.status(401).json({ message: "Refresh token diperlukan" });

    try {
        const decoded = jwt.verify(refreshToken, REFRESH_SECRET_KEY);
        const newAccessToken = jwt.sign(
            { id: decoded.id, email: decoded.email }, 
            SECRET_KEY, 
            { expiresIn: '15m' }
        );
        res.json({ accessToken: newAccessToken });
    } catch (error) {
        res.status(403).json({ message: "Refresh token tidak valid" });
    }
});

app.post('/api/auth/logout', (req, res) => {
    res.json({ 
        status: "success", 
        message: "Logout berhasil." 
    });
});

// --- GOOGLE OAUTH DENGAN PREFIX ---
app.get('/api/auth/google', (req, res) => {
    // Pastikan REDIRECT_URI di .env atau variabel ini mengarah ke PORT 3000
    // Contoh: http://localhost:3000/api/auth/google/callback
    const googleAuthUrl = `https://accounts.google.com/o/oauth2/v2/auth?client_id=${process.env.GOOGLE_CLIENT_ID}&redirect_uri=${encodeURIComponent(process.env.GOOGLE_REDIRECT_URI)}&response_type=code&scope=profile%20email`;
    res.redirect(googleAuthUrl);
});

app.get('/api/auth/google/callback', async (req, res) => {
    const { code } = req.query;
    if (!code) return res.status(400).json({ error: 'Akses ditolak' });

    try {
        const tokenResponse = await axios.post('https://oauth2.googleapis.com/token', {
            client_id: process.env.GOOGLE_CLIENT_ID,
            client_secret: process.env.GOOGLE_CLIENT_SECRET,
            code,
            redirect_uri: process.env.GOOGLE_REDIRECT_URI,
            grant_type: 'authorization_code'
        });

        const profileResponse = await axios.get('https://www.googleapis.com/oauth2/v2/userinfo', {
            headers: { Authorization: `Bearer ${tokenResponse.data.access_token}` }
        });

        const profile = profileResponse.data;
        db.query('SELECT * FROM users WHERE email = ?', [profile.email], (err, results) => {
            if (err) return res.status(500).json({ error: 'Database error' });

            let user = results[0];
            if (!user) {
                const newUser = { 
                    name: profile.name, email: profile.email, password: '',
                    avatar: profile.picture, oauth_provider: 'google' 
                };
                db.query('INSERT INTO users SET ?', newUser, (insertErr, result) => {
                    if (insertErr) return res.status(500).json({ error: 'Gagal simpan user' });
                    const tokens = generateTokens({ id: result.insertId, email: profile.email });
                    res.json({ message: 'Login Google Berhasil!', ...tokens });
                });
            } else {
                const tokens = generateTokens(user);
                res.json({ message: 'Login Google Berhasil!', ...tokens });
            }
        });
    } catch (error) {
        res.status(500).json({ error: 'Gagal otentikasi Google' });
    }
});

app.listen(PORT, () => console.log(`🚀 Auth Service jalan di port ${PORT}`));