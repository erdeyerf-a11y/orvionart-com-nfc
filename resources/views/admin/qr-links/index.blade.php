@extends('layouts.app')
@section('title', 'QR / NFC Yönetimi · ORVIONART')
@section('content')
<main class="wrap">
    <div class="crumb"><a href="{{ route('admin.dashboard') }}">← Yönetim merkezi</a></div>
    <section class="hero"><div><div class="eyebrow">QR / NFC · Modül 03</div><h1>QR / NFC bağlantıları</h1><p class="muted">Her bağlantı kalıcı bir yönlendirme adresi kullanır. QR görseli SVG olarak üretilebilir; aynı bağlantı NFC etiketine yazılabilir. Ziyaretçi IP adresi veya tarayıcı bilgisi saklanmaz.</p></div></section>
    @if(session('success'))<div class="success" role="status">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="errors" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <section class="panel create-panel"><h2 style="margin-top:0">Yeni QR/NFC hedefi oluştur</h2><form method="post" action="{{ route('admin.qr-links.store') }}">@csrf
        <div class="field"><label for="label">Etiket adı <span class="req">*</span></label><input id="label" name="label" maxlength="160" required value="{{ old('label') }}" placeholder="Örn. Mavi vazo ürün etiketi"></div>
        <div class="field"><label for="experience_id">Bağlanacak deneyim <span class="req">*</span></label><select id="experience_id" name="experience_id" required><option value="">Deneyim seçin</option>@foreach($experiences as $experience)<option value="{{ $experience->id }}" @selected((string)old('experience_id') === (string)$experience->id)>{{ $experience->title }} · {{ $experience->status }}</option>@endforeach</select><small>Yayın zamanı gelene kadar bağlantı ziyaretçilere içerik göstermez.</small></div>
        <button class="btn" type="submit">Bağlantı oluştur</button>
    </form></section>
    <section class="panel" style="margin-top:20px"><h2 style="margin-top:0">Bağlantı kayıtları</h2>
    <form class="qr-filters" method="get" action="{{ route('admin.qr-links.index') }}" role="search" aria-label="QR/NFC bağlantılarını filtrele">
        <div class="field"><label for="qr-search">Etiket veya deneyim ara</label><input id="qr-search" name="q" type="search" maxlength="120" value="{{ $filters['q'] }}" placeholder="Örn. mavi vazo"></div>
        <div class="field"><label for="qr-status">Bağlantı durumu</label><select id="qr-status" name="status"><option value="all" @selected($filters['status'] === 'all')>Tümü</option><option value="active" @selected($filters['status'] === 'active')>Etkin</option><option value="inactive" @selected($filters['status'] === 'inactive')>Devre dışı</option></select></div>
        <div class="filter-actions"><button class="btn" type="submit">Filtrele</button><a class="btn secondary" href="{{ route('admin.qr-links.index') }}">Temizle</a><a class="btn secondary" href="{{ route('admin.qr-links.export', array_filter(['q' => $filters['q'], 'status' => $filters['status'] !== 'all' ? $filters['status'] : null], fn ($value) => $value !== null && $value !== '')) }}">CSV dışa aktar</a></div>
    </form>
    @if($links->isEmpty())<div class="empty"><h3>Henüz QR/NFC bağlantısı yok</h3><p class="muted">İlk bağlantıyı oluşturduğunuzda QR SVG indirme seçeneği burada görünecek.</p></div>@else
    <div class="table-wrap"><table><thead><tr><th>Etiket / Hedef</th><th>Durum</th><th>Tarama sayısı</th><th>Son erişim</th><th>İşlemler</th></tr></thead><tbody>@foreach($links as $link)<tr><td><strong>{{ $link->label }}</strong><div class="muted small">Deneyim: {{ $link->experience?->title ?? 'Silinmiş deneyim' }}</div><div class="url"><a href="{{ $link->redirectUrl() }}" target="_blank" rel="noopener noreferrer">{{ $link->redirectUrl() }}</a></div></td><td><span class="badge {{ $link->is_active ? 'active' : 'inactive' }}">{{ $link->is_active ? 'Etkin' : 'Devre dışı' }}</span><div class="muted small target-state">{{ $link->targetAvailabilityLabel() }}</div>@if($link->experience?->status === 'scheduled' && $link->experience?->scheduled_at)<div class="muted small">Planlanan: {{ $link->experience->scheduled_at->format('d.m.Y H:i') }}</div>@endif</td><td>{{ number_format($link->scan_count, 0, ',', '.') }}</td><td>{{ $link->last_scanned_at?->format('d.m.Y H:i') ?? 'Henüz yok' }}</td><td><div class="actions">@if($link->is_active && in_array($link->targetAvailability(), ['available', 'scheduled'], true))<a class="btn secondary" href="{{ route('admin.qr-links.preview', $link) }}">QR önizle</a><a class="btn secondary" href="{{ $link->qrImageUrl() }}">QR SVG indir</a>@else<span class="muted small">Önizleme için bağlantı etkin ve hedef erişilebilir olmalı.</span>@endif<button class="btn secondary" type="button" data-copy-url="{{ $link->redirectUrl() }}">NFC adresini kopyala</button><form method="post" action="{{ route('admin.qr-links.toggle', $link) }}">@csrf @method('PATCH')<button class="btn {{ $link->is_active ? 'danger' : '' }}" type="submit">{{ $link->is_active ? 'Devre dışı bırak' : 'Etkinleştir' }}</button></form></div></td></tr>@endforeach</tbody></table></div><div class="pagination">{{ $links->links() }}</div>@endif
    </section>
    <p class="copy-status" aria-live="polite"></p><div class="notice" style="margin-top:20px"><strong>Üretim notu:</strong> SVG QR dosyasını indirdikten sonra baskıdan önce gerçek telefonda okutun. NFC etikete, bu tabloda görünen kalıcı yönlendirme bağlantısını yazın. Gerçek sunucu alan adı ve HTTPS doğrulanmadan basılı materyal hazırlamayın.</div>
</main>
<style>
.crumb{margin-bottom:24px}.crumb a{text-decoration:none;color:var(--muted)}.create-panel{max-width:850px;margin-top:24px}.qr-filters{display:grid;grid-template-columns:minmax(220px,2fr) minmax(180px,1fr) auto;gap:14px;align-items:end;margin:18px 0 8px}.qr-filters .field{margin:0}.filter-actions{display:flex;gap:8px;flex-wrap:wrap;padding-bottom:1px}.field{display:grid;gap:7px;margin:18px 0}.field label{font-weight:700}.field input,.field select{width:100%;padding:13px 14px;border-radius:10px;border:1px solid #405367;background:#0b131d;color:white;font:inherit}.field small{color:var(--muted);font-size:.82rem}.req{color:#ffb2b8}.table-wrap{overflow-x:auto;margin-top:20px}table{border-collapse:collapse;width:100%;min-width:900px}th,td{text-align:left;padding:13px 12px;border-bottom:1px solid var(--line);vertical-align:top}th{font-size:.78rem;color:var(--muted);text-transform:uppercase;letter-spacing:.07em}.small,.url{font-size:.8rem;overflow-wrap:anywhere}.url{margin-top:5px;max-width:360px}.actions{display:flex;gap:8px;flex-wrap:wrap}.actions .btn{font-size:.82rem;padding:8px 10px}.copy-status{min-height:1.2em;color:var(--muted)}.btn.danger{background:#542a32;color:#ffe3e6}.badge{display:inline-block;padding:4px 9px;border-radius:99px;font-size:.8rem}.badge.active{background:#17372d;color:#b8f4d8}.badge.inactive{background:#47242b;color:#ffe1e4}.target-state{margin-top:6px;max-width:190px}.success{margin:20px 0;background:#17372d;border:1px solid #376f59;padding:14px 16px;border-radius:10px}.errors{margin:20px 0;background:#47242b;padding:12px 16px;border-radius:10px}.pagination{margin-top:20px}.empty{padding:30px 0;text-align:center}@media(max-width:760px){.qr-filters{grid-template-columns:1fr}.filter-actions .btn{flex:1;text-align:center}}@media(max-width:600px){.create-panel{padding:17px}}
</style>
<script>
(() => {
  const status = document.querySelector('.copy-status');
  document.querySelectorAll('[data-copy-url]').forEach(button => button.addEventListener('click', async () => {
    const url = button.dataset.copyUrl || '';
    try {
      if (navigator.clipboard && window.isSecureContext) await navigator.clipboard.writeText(url);
      else {
        const input = document.createElement('textarea'); input.value = url; input.setAttribute('readonly', '');
        input.style.position = 'fixed'; input.style.opacity = '0'; document.body.appendChild(input); input.select();
        const copied = document.execCommand('copy'); input.remove(); if (!copied) throw new Error('copy unavailable');
      }
      if (status) status.textContent = 'Kalıcı NFC adresi kopyalandı.';
    } catch (_) {
      if (status) status.textContent = 'Adres: ' + url + ' — kopyalamak için adresi seçip kopyalayın.';
    }
  }));
})();
</script>
@endsection
