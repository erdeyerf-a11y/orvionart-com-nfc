@extends('layouts.app')
@section('title', $pageTitle.' · ORVIONART')
@section('content')
<main class="wrap">
    <div class="crumb"><a href="{{ route('admin.experiences.index') }}">← Deneyim listesine dön</a></div>
    <section class="hero"><div><div class="eyebrow">Deneyim Yönetimi</div><h1>{{ $pageTitle }}</h1><p class="muted">Buraya metin girin; kaydedin, yayımlayın ve aynı ekrandan QR/NFC bağlantısı oluşturun. Ziyaretçi telefonuyla okuttuğunda bu içerik açılır.</p></div></section>
    @if($errors->any())<div class="errors" role="alert"><strong>Kaydetmeden önce şu alanları düzeltin:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    @if(session('success'))<div class="success" role="status">{{ session('success') }}</div>@endif
    @if($experience->exists)
        <section class="panel experience-media-shortcut" aria-labelledby="experience-media-title">
            <div><div class="eyebrow">Fotoğraf ve video</div><h2 id="experience-media-title">Bu içeriğe görsel veya video ekle</h2><p class="muted">Yükleme ekranı bu deneyim seçili açılır. Yüklenen dosyalar ziyaretçiye yalnızca deneyim erişilebilir durumdayken gösterilir.</p></div>
            <a class="btn secondary" href="{{ route('admin.media.index', ['experience_id' => $experience->id]) }}">Bu deneyime medya ekle →</a>
        </section>
    @endif
    @if($experience->exists && $experience->publicUrl())
        <div class="public-link panel"><strong>Bu deneyim ziyaretçilere açık</strong><p><a href="{{ $experience->publicUrl() }}" target="_blank" rel="noopener noreferrer">Ziyaretçi sayfasını aç ↗</a></p><small>Bu bağlantıdaki uzun kimlik kalıcıdır; kısa ad değişikliği QR/NFC adresini ileride bozmayacak şekilde tasarlanmıştır.</small></div>
    @endif
    @if($experience->exists)
        <section class="panel qr-workflow" aria-labelledby="qr-workflow-title">
            <div class="eyebrow">Bir sonraki adım</div>
            <h2 id="qr-workflow-title">QR / NFC bağlantısı</h2>
            <p class="muted">Bu deneyim için bir kez bağlantı oluşturun. Aynı adres hem QR kodda hem NFC etiketinde kullanılır. İçerik güncellendiğinde etiketi yeniden üretmeniz gerekmez.</p>
            @if(in_array($experience->status, ['published', 'scheduled'], true))
                <form method="post" action="{{ route('admin.qr-links.store') }}" class="qr-create-form">
                    @csrf
                    <input type="hidden" name="experience_id" value="{{ $experience->id }}">
                    <div class="field"><label for="qr-label">Etiket adı</label><input id="qr-label" name="label" maxlength="160" required value="{{ old('label', $experience->title . ' etiketi') }}" placeholder="Örn. Çini tabağın etiketi"></div>
                    <button class="btn" type="submit">Bu deneyim için QR/NFC oluştur</button>
                </form>
                <small class="muted">QR/NFC bağlantısı oluşturmak için deneyimin yayınlanmış veya zamanlanmış olması gerekir. Zamanı gelmemiş içerik henüz ziyaretçilere açılmaz.</small>
            @else
                <div class="notice">Önce yukarıdaki durum alanından bu içeriği <strong>Yayınlandı</strong> olarak kaydedin. Sonra burada QR/NFC bağlantısı oluşturabilirsiniz.</div>
            @endif
            @if(isset($qrLinks) && $qrLinks->isNotEmpty())
                <h3>Bu içeriğin bağlantıları</h3>
                <div class="qr-link-list">
                    @foreach($qrLinks as $qrLink)
                        <div class="qr-link-card">
                            <div><strong>{{ $qrLink->label }}</strong><div class="muted small">{{ $qrLink->is_active ? 'Etkin' : 'Devre dışı' }} · {{ number_format($qrLink->scan_count, 0, ',', '.') }} okutma</div></div>
                            <label class="sr-only" for="qr-url-{{ $qrLink->id }}">NFC etiketine yazılacak kalıcı adres</label>
                            <input class="qr-url" id="qr-url-{{ $qrLink->id }}" readonly value="{{ $qrLink->redirectUrl() }}" @if(!$qrLink->is_active) aria-describedby="qr-url-state-{{ $qrLink->id }}" @endif>
                            <div class="qr-actions"><button class="btn secondary" type="button" data-copy-target="qr-url-{{ $qrLink->id }}">Adresi kopyala (NFC için)</button>@if($qrLink->is_active && in_array($qrLink->targetAvailability(), ['available', 'scheduled'], true))<a class="btn secondary" href="{{ route('admin.qr-links.preview', $qrLink) }}">QR önizle</a><a class="btn secondary" href="{{ $qrLink->qrImageUrl() }}">QR görselini indir</a>@else<small class="muted">QR önizlemesi için bağlantı etkin ve hedef erişilebilir olmalı.</small>@endif</div>
                            @if(!$qrLink->is_active)<small id="qr-url-state-{{ $qrLink->id }}" class="muted">Bu bağlantı kapalı olduğu için ziyaretçiye içerik açmaz.</small>@endif
                        </div>
                    @endforeach
                </div>
            @endif
            <p class="copy-status" aria-live="polite"></p>
        </section>
    @endif
    <form class="panel formpanel" method="post" action="{{ $formAction }}">
        @csrf @if($formMethod !== 'POST') @method($formMethod) @endif
        <div class="field"><label for="title">Deneyim başlığı <span class="req">*</span></label><input id="title" name="title" maxlength="160" required value="{{ old('title', $experience->title) }}" placeholder="Örn. Seramik Atölyesi Deneyimi"><small>Kullanıcıların göreceği ana başlık. En fazla 160 karakter.</small></div>
        <div class="field"><label for="slug">Bağlantı kısa adı</label><input id="slug" name="slug" maxlength="180" value="{{ old('slug', $experience->slug) }}" placeholder="seramik-atolyesi"><small>Boş bırakırsanız başlıktan üretilir. QR/NFC için kullanılacak kalıcı kimlik bu kısa addan ayrıdır; kısa ad değişse de kalıcı bağlantı kimliği değişmez.</small></div>
        <div class="field"><label for="summary">Kısa açıklama</label><textarea id="summary" name="summary" rows="3" maxlength="500" placeholder="Deneyimi bir veya iki cümleyle anlatın…">{{ old('summary', $experience->summary) }}</textarea><small>En fazla 500 karakter.</small></div>
        <div class="field"><label for="body">İçerik / açıklama</label><textarea id="body" name="body" rows="9" maxlength="50000" placeholder="Deneyimin ayrıntılı içeriği…">{{ old('body', $experience->body) }}</textarea><small>En fazla 50.000 karakter. Güvenlik amacıyla bu alan şu aşamada düz metin olarak saklanır.</small></div>
        <div class="field"><label for="status">Kayıt durumu</label><select id="status" name="status" required>@foreach($statuses as $key => $label)<option value="{{ $key }}" @selected(old('status', $experience->status) === $key)>{{ $label }}</option>@endforeach</select><small>Yayınlandı seçildiğinde ziyaretçi sayfası açılır. Taslak ve arşiv içerikleri dışarıya gösterilmez; zamanı gelmemiş planlı içerikler de erişime kapalıdır.</small></div>
        <div class="field" id="schedule-field"><label for="scheduled_at">Yayınlama için planlanan tarih ve saat</label><input id="scheduled_at" name="scheduled_at" type="datetime-local" value="{{ old('scheduled_at', $experience->scheduled_at?->format('Y-m-d\TH:i')) }}"><small>Yalnızca “Zamanlandı” seçildiğinde zorunludur. Zamanı geldiğinde ziyaretçi sayfası sunucu tarafında erişilebilir olur; ayrıca arka plan görevi gerekmez. Yönetim durum etiketi yine “Zamanlandı” olarak kalır.</small></div>
        <div class="field"><label for="ends_at">İçeriğin erişim bitiş zamanı (isteğe bağlı)</label><input id="ends_at" name="ends_at" type="datetime-local" value="{{ old('ends_at', $experience->ends_at?->format('Y-m-d\TH:i')) }}"><small>Boş bırakırsanız bitiş tarihi olmaz. Bu tarih geldiğinde aynı QR/NFC bağlantısı korunur ancak içerik ziyaretçilere otomatik olarak kapanır. Zamanlanmış içerikte bitiş zamanı yayın zamanından sonra olmalıdır.</small></div>
        <div class="formactions"><a class="btn secondary" href="{{ route('admin.experiences.index') }}">Vazgeç</a><button class="btn" type="submit">{{ $experience->exists ? 'Değişiklikleri kaydet' : 'Deneyimi kaydet' }}</button></div>
    </form>
</main>
<style>
.experience-media-shortcut{max-width:850px;margin-top:20px;display:flex;align-items:center;justify-content:space-between;gap:18px;flex-wrap:wrap}.experience-media-shortcut h2{margin:6px 0}.experience-media-shortcut p{margin:0}.public-link{max-width:850px;margin-top:20px;border-color:#376f59}.public-link p{margin:6px 0}.public-link small{color:var(--muted)}.crumb{margin-bottom:24px}.crumb a{text-decoration:none;color:var(--muted)}.formpanel{max-width:850px;margin-top:24px}.field{margin:0 0 23px}.field label{font-weight:700}.field input,.field select,.field textarea{width:100%;padding:13px 14px;border-radius:10px;border:1px solid #405367;background:#0b131d;color:white;font:inherit}.field textarea{resize:vertical;min-height:90px}.field small{display:block;color:var(--muted);font-size:.82rem;margin-top:6px}.req{color:#ffb2b8}.formactions{display:flex;justify-content:flex-end;gap:10px;border-top:1px solid var(--line);padding-top:20px}.errors ul{margin-bottom:0}.success{margin-top:20px;background:#17372d;border:1px solid #376f59;padding:14px 16px;border-radius:10px}@media(max-width:600px){.formactions{flex-direction:column-reverse}.formactions .btn{width:100%}}
.qr-workflow{max-width:850px;margin-top:20px}.qr-workflow h2{margin:8px 0}.qr-create-form{display:flex;align-items:end;gap:12px;flex-wrap:wrap;margin:18px 0}.qr-create-form .field{flex:1;min-width:220px;margin:0}.qr-link-list{display:grid;gap:12px;margin-top:12px}.qr-link-card{border:1px solid var(--line);border-radius:12px;padding:14px;display:grid;gap:10px}.qr-url{width:100%;min-width:0;padding:11px;border:1px solid #405367;border-radius:8px;background:#0b131d;color:white;font:inherit}.qr-actions{display:flex;gap:8px;flex-wrap:wrap}.copy-status{min-height:1.2em;color:var(--muted)}@media(max-width:600px){.qr-create-form{align-items:stretch;flex-direction:column}.qr-actions .btn{flex:1;text-align:center}}
</style>
<script>
(() => {
 const status = document.getElementById('status'); const schedule = document.getElementById('scheduled_at');
 if (status && schedule) { const sync = () => { const required = status.value === 'scheduled'; schedule.required = required; schedule.setAttribute('aria-required', required ? 'true' : 'false'); }; status.addEventListener('change', sync); sync(); }
 document.querySelectorAll('[data-copy-target]').forEach(button => button.addEventListener('click', async () => {
   const input = document.getElementById(button.dataset.copyTarget); const message = document.querySelector('.copy-status'); if (!input) return;
   input.focus(); input.select();
   try { if (navigator.clipboard && window.isSecureContext) await navigator.clipboard.writeText(input.value); else if (!document.execCommand('copy')) throw new Error('copy unavailable');
     if (message) message.textContent = 'Adres kopyalandı. NFC yazma uygulamasında bu adresi kullanın.';
   } catch (_) { if (message) message.textContent = 'Adres seçildi. Kopyalamak için telefonunuzun Kopyala komutunu kullanın.'; }
 }));
})();
</script>
@endsection
