@extends('layouts.app')
@section('title', 'QR Önizleme · ORVIONART')
@section('content')
<main class="wrap qr-preview-page">
    <div class="crumb"><a href="{{ route('admin.qr-links.index') }}">← QR/NFC yönetimine dön</a></div>
    <section class="hero"><div><div class="eyebrow">QR / NFC · Yazdırma önizlemesi</div><h1>{{ $link->label }}</h1><p class="muted">QR kodunu gerçek telefonla tarayarak baskıdan önce doğrulayın. NFC etiketi için aşağıdaki aynı kalıcı adresi kullanın.</p></div></section>
    <section class="panel preview-card">
        <div class="print-label" id="print-label">
            <div class="label-brand">ORVIONART</div>
            <h2 class="label-title">{{ $link->experience?->title ?? 'Deneyim' }}</h2>
            <p class="label-instruction">Bu eserin hikâyesini ve ayrıntılarını görmek için QR kodu okutun.</p>
            <div class="qr-paper"><img src="{{ route('admin.qr-links.preview-image', $link) }}" alt="{{ $link->label }} için QR kodu" width="420" height="420"></div>
            <p class="label-footer">ORVIONART · Seramik &amp; Çini</p>
        </div>
        <p class="muted">{{ $link->targetAvailabilityLabel() }}</p>
        <label for="nfc-url">QR/NFC kalıcı adresi</label>
        <div class="copy-row"><input id="nfc-url" readonly value="{{ $link->redirectUrl() }}"><button class="btn secondary" type="button" id="copy-nfc-url">Adresi kopyala</button></div>
        <p class="copy-status" aria-live="polite"></p>
        <div class="actions"><a class="btn secondary" href="{{ $link->qrImageUrl() }}">SVG dosyasını indir</a><button class="btn" type="button" id="print-qr">QR kodunu yazdır</button></div>
        <div class="notice print-note"><strong>Baskı kontrolü:</strong> QR kodunun etrafındaki beyaz boşluğu kesmeyin. İlk baskıdan önce telefon kamerasıyla tarayıp doğru deneyimin açıldığını doğrulayın. Bu ekran NFC etiketine fiziksel yazma yapmaz.</div>
    </section>
</main>
<style>
.qr-preview-page{max-width:860px}.crumb{margin-bottom:24px}.crumb a{text-decoration:none;color:var(--muted)}.preview-card{max-width:650px;margin:24px auto;text-align:center}.print-label{background:#fff;color:#0f172a;width:min(100%,460px);margin:0 auto 22px;padding:22px;border:1px solid #d7dee7;border-radius:16px;text-align:center}.label-brand{font-size:1rem;font-weight:900;letter-spacing:.2em;color:#0f172a}.label-title{font-size:1.5rem;line-height:1.2;margin:12px 0;color:#0f172a;overflow-wrap:anywhere}.label-instruction{font-size:.95rem;line-height:1.45;color:#334155;margin:0 auto 14px;max-width:34ch}.qr-paper{background:#fff;padding:8px;width:min(100%,340px);margin:0 auto;border-radius:8px}.qr-paper img{display:block;width:100%;height:auto;aspect-ratio:1;object-fit:contain}.label-footer{font-size:.75rem;font-weight:700;letter-spacing:.08em;color:#475569;margin:12px 0 0}.preview-card label{display:block;text-align:left;font-weight:700;margin:20px 0 8px}.copy-row{display:flex;gap:8px}.copy-row input{min-width:0;flex:1;padding:12px;border:1px solid #405367;border-radius:9px;background:#0b131d;color:white;font:inherit}.actions{display:flex;justify-content:center;gap:10px;flex-wrap:wrap;margin-top:16px}.copy-status{min-height:1.3em;color:var(--muted)}.print-note{text-align:left;margin-top:20px}@media(max-width:560px){.preview-card{padding:16px}.print-label{padding:16px}.copy-row{flex-direction:column}.copy-row .btn{width:100%}}
@page{margin:8mm}
@media print{body{background:#fff!important;color:#000!important}.wrap,.preview-card{width:100%!important;max-width:none!important;margin:0!important;padding:0!important}.crumb,.hero,.preview-card>.muted,.preview-card label,.copy-row,.copy-status,.actions,.print-note{display:none!important}.panel{border:0!important;box-shadow:none!important;background:#fff!important;padding:0!important;margin:0 auto!important}.print-label{display:block!important;width:90mm!important;max-width:90mm!important;margin:0 auto!important;padding:5mm!important;border:0!important;border-radius:0!important;background:#fff!important;color:#000!important;break-inside:avoid}.label-brand{font-size:12pt!important;color:#000!important}.label-title{font-size:17pt!important;color:#000!important;margin:4mm 0!important}.label-instruction{font-size:10pt!important;color:#222!important;margin:0 auto 3mm!important}.qr-paper{width:70mm!important;max-width:70mm!important;padding:0!important;margin:0 auto!important;border:0!important}.qr-paper img{width:70mm!important;height:70mm!important}.label-footer{font-size:8pt!important;color:#222!important;margin-top:3mm!important}}
</style>
<script>
(() => {
 const input = document.getElementById('nfc-url');
 const message = document.querySelector('.copy-status');
 document.getElementById('copy-nfc-url')?.addEventListener('click', async () => {
   try {
     if (navigator.clipboard && window.isSecureContext) await navigator.clipboard.writeText(input.value);
     else { input.focus(); input.select(); if (!document.execCommand('copy')) throw new Error('copy unavailable'); }
     message.textContent = 'Kalıcı adres kopyalandı. NFC yazma uygulamasında kullanabilirsiniz.';
   } catch (_) { input.focus(); input.select(); message.textContent = 'Adres seçildi. Kopyalamak için telefonunuzun Kopyala komutunu kullanın.'; }
 });
 document.getElementById('print-qr')?.addEventListener('click', () => window.print());
})();
</script>
@endsection
