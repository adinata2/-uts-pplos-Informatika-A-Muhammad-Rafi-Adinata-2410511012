const express = require('express');
const { createProxyMiddleware } = require('http-proxy-middleware');
const jwt = require('jsonwebtoken');
const rateLimit = require('express-rate-limit');

const app = express();
const PORT = 3000;
const SECRET_KEY = "key34527siak278dbya72d"; 

app.use(express.json()); 

// --- 1. RATE LIMITING ---
const limiter = rateLimit({
    windowMs: 1 * 60 * 1000, 
    max: 60,
    message: { message: "Sabar Bos, jangan dipencet terusss!" }
});
app.use(limiter);

// --- 2. MIDDLEWARE PENGECEK TOKEN ---
const authenticateJWT = (req, res, next) => {
    const authHeader = req.headers.authorization;
    if (!authHeader) {
        return res.status(401).json({ message: "Berhenti! Mana Token kamu?" });
    }
    const token = authHeader.split(' ')[1];
    jwt.verify(token, SECRET_KEY, (err, user) => {
        if (err) return res.status(403).json({ message: "Token palsu atau sudah basi!" });
        req.user = user;
        next();
    });
};

// --- 3. PROXY KE AUTH SERVICE (Port 4000) ---
app.use('/api/auth', (req, res, next) => {
    req.url = req.originalUrl; 
    createProxyMiddleware({ 
        target: 'http://localhost:4000', 
        changeOrigin: true,
        on: {
            proxyReq: (proxyReq, req) => {
                // untuk Ambil body yang sudah dibaca Gateway dan tulis ulang ke Auth Service
                if (req.method !== 'GET' && req.body && Object.keys(req.body).length > 0) {
                    const bodyData = JSON.stringify(req.body);
                    proxyReq.setHeader('Content-Type', 'application/json');
                    proxyReq.setHeader('Content-Length', Buffer.byteLength(bodyData));
                    proxyReq.write(bodyData);
                }
            }
        }
    })(req, res, next);
});

// --- 4. PROXY KE PRODUCT SERVICE (Port 8080) ---
app.use('/api/products', authenticateJWT, (req, res, next) => {
    req.url = req.originalUrl; 
    createProxyMiddleware({
        target: 'http://127.0.0.1:8080',
        changeOrigin: true,
        on: {
            proxyReq: (proxyReq, req) => {
                proxyReq.setHeader('Accept', 'application/json');
                
                // Meneruskan email dari token ke Laravel
                if (req.user?.email) {
                    proxyReq.setHeader('x-user-email', req.user.email);
                }

            
                if (req.method !== 'GET' && req.body && Object.keys(req.body).length > 0) {
                    const bodyData = JSON.stringify(req.body);
                    proxyReq.setHeader('Content-Type', 'application/json');
                    proxyReq.setHeader('Content-Length', Buffer.byteLength(bodyData));
                    proxyReq.write(bodyData);
                }
            }
        }
    })(req, res, next);
});

// --- 5. PROXY KE ORDER SERVICE (Port 8081) ---
app.use('/api/orders', authenticateJWT, (req, res, next) => {
    req.url = req.originalUrl; 
    createProxyMiddleware({
        target: 'http://127.0.0.1:8081',
        changeOrigin: true,
        on: {
            proxyReq: (proxyReq, req) => {
                proxyReq.setHeader('Accept', 'application/json');
                
                // Kirim email user ke Laravel
                if (req.user?.email) {
                    proxyReq.setHeader('x-user-email', req.user.email);
                }
                
                
                if (req.method !== 'GET' && req.body && Object.keys(req.body).length > 0) {
                    const bodyData = JSON.stringify(req.body);
                    proxyReq.setHeader('Content-Type', 'application/json');
                    proxyReq.setHeader('Content-Length', Buffer.byteLength(bodyData));
                    proxyReq.write(bodyData);
                }
            }
        }
    })(req, res, next);
});

app.listen(PORT, () => {
    console.log(`API Gateway Siap Berjaga di http://127.0.0.1:${PORT}`);
});