// Proksi HTTPS sederhana untuk uji lokal: HP hanya mau membuka kamera lewat HTTPS, sedangkan "php artisan serve" hanya HTTP.
// Pemakaian: node tools/proxy-https.cjs <port-https> <port-http> <berkas.pfx> <sandi-pfx>
// Hanya untuk jaringan tepercaya dan data uji. Tanpa dependensi tambahan.
const https = require('https');
const http = require('http');
const fs = require('fs');

const [, , portHttps, portHttp, pfx, sandi] = process.argv;
if (!portHttps || !portHttp || !pfx) { console.error('Pemakaian: node proxy-https.cjs <port-https> <port-http> <berkas.pfx> <sandi>'); process.exit(1); }

https.createServer({ pfx: fs.readFileSync(pfx), passphrase: sandi || '' }, (req, res) => {
    const headers = { ...req.headers, 'x-forwarded-proto': 'https', 'x-forwarded-host': req.headers.host, 'x-forwarded-for': req.socket.remoteAddress };
    const up = http.request({ host: '127.0.0.1', port: Number(portHttp), path: req.url, method: req.method, headers }, (r) => {
        res.writeHead(r.statusCode, r.headers);
        r.pipe(res);
    });
    up.on('error', () => { res.writeHead(502); res.end('Server Laravel tidak menjawab.'); });
    req.pipe(up);
}).listen(Number(portHttps), '0.0.0.0', () => console.log(`HTTPS aktif di port ${portHttps} -> http://127.0.0.1:${portHttp}`));
