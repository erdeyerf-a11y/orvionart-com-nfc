<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow,noarchive">
    <meta name="referrer" content="no-referrer">
    <title>{{ $expired ? 'İçerik süresi doldu' : 'İçerik şu anda kullanılamıyor' }} · ORVIONART</title>
    <style>
        :root{color-scheme:light;--ink:#17252d;--muted:#5d7078;--line:#dce5e8;--sea:#0b6f78;--paper:#f5f8f7;--white:#fff}
        *{box-sizing:border-box}body{margin:0;min-height:100vh;background:var(--paper);color:var(--ink);font:16px/1.7 system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;display:flex;flex-direction:column}
        header{background:#102b35;color:#fff;border-bottom:4px solid #7cc9c3;padding:20px 0}.shell{width:min(720px,calc(100% - 32px));margin:0 auto}.brand{font-size:1rem;font-weight:850;letter-spacing:.16em}.brand span{color:#8ce0d8}
        main{flex:1;display:grid;place-items:center;padding:48px 0}.card{width:100%;background:var(--white);border:1px solid var(--line);border-radius:18px;padding:clamp(24px,6vw,44px);box-shadow:0 12px 40px rgba(16,43,53,.06)}.icon{width:54px;height:54px;border-radius:50%;display:grid;place-items:center;background:#e5f3f1;color:var(--sea);font-size:1.6rem;font-weight:800}h1{font-size:clamp(1.7rem,5vw,2.5rem);line-height:1.2;letter-spacing:-.035em;margin:20px 0 12px}p{color:var(--muted);margin:0}.hint{border-top:1px solid var(--line);margin-top:24px;padding-top:18px;font-size:.9rem}.foot{padding:20px 0 28px;color:var(--muted);font-size:.82rem}.foot strong{color:var(--ink);letter-spacing:.08em}
    </style>
</head>
<body>
<header><div class="shell"><div class="brand">ORVION<span>ART</span></div></div></header>
<main class="shell">
    <section class="card" role="status" aria-live="polite">
        <div class="icon" aria-hidden="true">{{ $expired ? '⌛' : 'i' }}</div>
        <h1>{{ $expired ? 'Bu içeriğin erişim süresi sona erdi' : 'Bu içerik şu anda kullanılamıyor' }}</h1>
        <p>{{ $expired ? 'Bu QR/NFC bağlantısına bağlı içeriğin yayın süresi tamamlanmış.' : 'Bu QR/NFC bağlantısı devre dışı bırakılmış veya içerik şu anda yayında değil.' }}</p>
        <p class="hint">Bağlantı sahibiyle iletişime geçerek bilgi alabilirsiniz. Bu sayfayı daha sonra yeniden deneyebilirsiniz.</p>
    </section>
</main>
<footer class="foot"><div class="shell"><strong>ORVIONART</strong><br>Seramik, sanat ve dijital hikâyeler.</div></footer>
</body>
</html>
