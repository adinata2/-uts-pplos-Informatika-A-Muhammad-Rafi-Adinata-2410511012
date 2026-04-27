const express = require('express');
const { createProxyMiddleware } = require('http-proxy-middleware');
const rateLimit = require('express-rate-limit');
const cors = require('cors');

const app = express();
const PORT = 3000;

app.use(cors());

// Basic Rate Limiting: Maksimal 60 request per menit dari 1 IP
const limiter = rateLimit({
    windowMs: 1 * 60 * 1000, 
    max: 60, 
    message: { error: 'Terlalu banyak request dari IP ini, silakan coba lagi setelah 1 menit.' }
});
app.use(limiter);

// Peta Routing (Akan diarahkan ke service masing-masing nantinya)
app.use('/auth', createProxyMiddleware({ target: 'http://localhost:3001', changeOrigin: true }));
app.use('/products', createProxyMiddleware({ target: 'http://localhost:8000', changeOrigin: true }));
app.use('/orders', createProxyMiddleware({ target: 'http://localhost:3002', changeOrigin: true }));

// Endpoint tes untuk Gateway
app.get('/', (req, res) => {
    res.json({ message: 'API Gateway Mini E-Commerce berjalan normal.' });
});

app.listen(PORT, () => {
    console.log(`API Gateway berjalan di http://localhost:${PORT}`);
});