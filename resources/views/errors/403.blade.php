<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Akses ditolak</title>
    <style>
        :root { --navy: #123047; --cyan: #00BCD4; --bg: #F3F6F8; }
        @media (prefers-color-scheme: dark) { :root { --navy: #E6F7FA; --bg: #0B1620; } }
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; background: var(--bg); color: var(--navy); font-family: "Segoe UI", Arial, sans-serif; }
        main { max-width: 28rem; padding: 2rem 1.5rem; text-align: center; }
        .kode { font-size: 3.5rem; font-weight: 700; color: var(--cyan); margin: 0; }
        h1 { font-size: 1.25rem; margin: .25rem 0 .75rem; }
        p { line-height: 1.6; margin: 0 0 1.25rem; }
        a { display: inline-block; padding: .6rem 1.1rem; border-radius: .5rem; background: var(--cyan); color: #fff; text-decoration: none; font-weight: 600; }
    </style>
</head>
<body>
<main>
    <p class="kode">403</p>
    <h1>Akses ditolak</h1>
    <p>Akun Anda tidak memiliki izin untuk membuka halaman ini. Jika Anda merasa ini keliru, hubungi admin sekolah Anda.</p>
    <a href="{{ url('/') }}">Kembali ke beranda</a>
</main>
</body>
</html>
