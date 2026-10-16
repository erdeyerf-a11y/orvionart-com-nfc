@extends('layouts.app')
@section('title', 'Medya Kütüphanesi · ORVIONART')
@section('content')
<main class="wrap">
    <div class="crumb"><a href="{{ route('admin.dashboard') }}">← Yönetim merkezi</a></div>
    <section class="hero"><div><div class="eyebrow">Medya ve video · Modül 02</div><h1>Merkezi medya kütüphanesi</h1><p class="muted">Tüm görsel ve videoları tek yerde arayın, türüne ve kullanım alanına göre filtreleyin. Önizlemeler yalnızca oturum açmış yöneticilere açıktır; dosyalar doğrudan herkese açık klasöre konmaz.</p></div></section>
    @if(session('success'))<div class="success" role="status">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="errors" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <section class="stats" aria-label="Medya istatistikleri"><div><strong>{{ $assets->total() }}</strong><span>Filtredeki kayıt</span></div><div><strong>{{ $imageCount }}</strong><span>Görsel</span></div><div><strong>{{ $videoCount }}</strong><span>Video</span></div><div><strong>{{ number_format($totalBytes / 1048576, 2, ',', '.') }} MB</strong><span>Toplam depolama</span></div></section>
    <section class="panel upload-panel"><h2 style="margin-top:0">Yeni medya yükle</h2><form method="post" action="{{ route('admin.media.store') }}" enctype="multipart/form-data">@csrf
        <div class="field"><label for="file">Fotoğraf veya video <span class="req">*</span></label><input id="file" name="file" type="file" required accept="image/jpeg,image/png,image/webp,image/gif,video/mp4,video/webm,video/ogg"><small>Tek dosya, en fazla 30 MB. SVG, HTML, script ve çalıştırılabilir dosyalar kabul edilmez.</small></div>
        <div class="field"><label for="title">Medya başlığı</label><input id="title" name="title" maxlength="160" value="{{ old('title') }}" placeholder="Örn. Atölyeden üretim aşaması"></div>
        <div class="field"><label for="alt_text">Fotoğraf açıklaması (erişilebilirlik)</label><input id="alt_text" name="alt_text" maxlength="250" value="{{ old('alt_text') }}" placeholder="Fotoğrafta ne görülüyor?"><small>Video için bu alan altyazı yerine geçmez.</small></div>
        <div class="field"><label for="experience_id">Bağlanacak deneyim</label><select id="experience_id" name="experience_id"><option value="">Merkezi kütüphanede tut</option>@foreach($experiences as $experience)<option value="{{ $experience->id }}" @selected((string)old('experience_id', $selectedExperienceId) === (string)$experience->id)>{{ $experience->title }} · {{ $experience->status }}</option>@endforeach</select><small>Deneyime bağlanan dosyalar yalnızca deneyim herkese açıkken sunulur.</small></div>
        <button class="btn" type="submit">Güvenli şekilde yükle</button>
    </form></section>
    <section class="panel upload-panel" style="margin-top:20px" id="chunk-video-upload"><h2 style="margin-top:0">Büyük videoyu parçalı yükle</h2>
        <p class="muted">4 MB parçalarla yükler. Bağlantı kesilirse aynı dosyayı yeniden seçerek bu tarayıcıda devam edebilirsiniz. Üst sınır 512 MB; yalnızca MP4, WebM ve OGG video biçimleri kabul edilir.</p>
        <form id="chunk-video-form">
            <div class="field"><label for="chunk_video_file">Video dosyası</label><input id="chunk_video_file" type="file" accept="video/mp4,video/webm,video/ogg,.mp4,.webm,.ogv,.ogg" required></div>
            <div class="field"><label for="chunk_video_title">Video başlığı</label><input id="chunk_video_title" maxlength="160" placeholder="Örn. Çini üretim süreci"></div>
            <div class="field"><label for="chunk_video_alt">Video açıklaması</label><input id="chunk_video_alt" maxlength="250" placeholder="Videonun kısa açıklaması"></div>
            <div class="field"><label for="chunk_video_experience">Bağlanacak deneyim</label><select id="chunk_video_experience"><option value="">Merkezi kütüphanede tut</option>@foreach($experiences as $experience)<option value="{{ $experience->id }}" @selected((string)$selectedExperienceId === (string)$experience->id)>{{ $experience->title }} · {{ $experience->status }}</option>@endforeach</select></div>
            <button class="btn" id="chunk_video_start" type="submit">Parçalı yüklemeyi başlat / devam et</button>
            <div class="chunk-progress" aria-live="polite"><progress id="chunk_video_progress" max="100" value="0" style="width:100%;display:none"></progress><p id="chunk_video_status" class="muted" role="status">Henüz yükleme başlatılmadı.</p></div>
        </form>
    </section>
    <section class="panel" style="margin-top:20px"><div class="library-head"><div><h2 style="margin:0">Kayıtlı medya</h2><p class="muted" style="margin:5px 0 0">Önizleme ve kullanım yeri bilgisi her dosyanın yanında gösterilir.</p></div>
    <form method="get" action="{{ route('admin.media.index') }}" class="search-form"><input type="search" name="q" value="{{ request('q') }}" maxlength="120" placeholder="Başlık, dosya adı veya alt metin ara"><select name="kind" aria-label="Medya türü"><option value="">Tüm türler</option><option value="image" @selected(request('kind')==='image')>Görsel</option><option value="video" @selected(request('kind')==='video')>Video</option></select><select name="location" aria-label="Kullanım yeri"><option value="all" @selected(request('location','all')==='all')>Tüm alanlar</option><option value="library" @selected(request('location')==='library')>Kütüphane</option><option value="experience" @selected(request('location')==='experience')>Deneyimler</option><option value="site" @selected(request('location')==='site')>Site Builder</option></select><button class="btn secondary" type="submit">Filtrele</button></form></div>
    @if($assets->isEmpty())<div class="empty"><h3>Bu filtrede medya yok</h3><p class="muted">Filtreleri temizleyin veya yeni fotoğraf/video yükleyin.</p></div>@else
    <div class="asset-grid">@foreach($assets as $asset)<article class="asset-card"><div class="preview">@if($asset->kind==='image')<img src="{{ route('admin.media.preview',$asset) }}" alt="{{ $asset->alt_text ?: $asset->title ?: 'Medya önizlemesi' }}" loading="lazy">@elseif($asset->processing_status==='ready')<video src="{{ route('admin.media.preview',$asset) }}" @if($asset->thumbnail_path) poster="{{ route('admin.media.thumbnail',$asset) }}" @endif controls preload="metadata" aria-label="{{ $asset->title ?: 'Video önizlemesi' }}"></video>@else<div class="processing-note" role="status">Video durumu: {{ $asset->processing_status }}</div>@endif</div><div class="asset-info"><strong>{{ $asset->title ?: $asset->original_name }}</strong><div class="muted small">{{ $asset->original_name }}</div>@if($asset->alt_text)<div class="muted small">Alt: {{ $asset->alt_text }}</div>@endif<div class="badges"><span>{{ $asset->kind==='video'?'Video':'Görsel' }}</span><span>{{ number_format($asset->file_size / 1048576, 2, ',', '.') }} MB</span>@if($asset->kind==='video')<span>İşleme: {{ $asset->processing_status }}</span>@endif</div>@if($asset->kind==='video' && $asset->processing_status==='failed')<div class="errors small" role="alert">{{ $asset->processing_error ?: 'Video doğrulanamadı.' }}</div><form method="post" action="{{ route('admin.media.video-processing.retry', $asset) }}">@csrf<button class="btn secondary" type="submit">Video doğrulamasını yeniden dene</button></form>@elseif($asset->kind==='video' && $asset->processing_status!=='ready')<div class="muted small">Video, doğrulama tamamlanana kadar kamuya açık sunulmaz.</div>@endif<div class="usage"><strong>Kullanım:</strong> @if($asset->sitePage)Site Builder · {{ $asset->sitePage->published_title ?: $asset->sitePage->title }}@elseif($asset->experience)Deneyim · {{ $asset->experience->title }}@else Merkezi kütüphane (henüz bağlı değil)@endif</div><div class="muted small">{{ $asset->mime_type }} · {{ $asset->created_at?->format('d.m.Y H:i') }}</div>@if($asset->experience_id !== null)
<form method="post" action="{{ route('admin.media.detach-experience', $asset) }}" onsubmit="return confirm('Bu medya deneyimden ayrılacak ve merkezi kütüphaneye taşınacak. Dosya silinmeyecek; yayındaki deneyimden hemen kaldırılabilir. Devam edilsin mi?');">@csrf @method('PATCH')<button class="btn secondary" type="submit">Kütüphaneye taşı</button></form>
@elseif($asset->isReferencedBySitePage())
<div class="muted small" role="status">Sayfa içeriğinde kullanılıyor; referans kaldırılmadan silinemez.</div>
@else
<form method="post" action="{{ route('admin.media.destroy', $asset) }}" onsubmit="return confirm('Bu medya dosyası kalıcı olarak silinsin mi? Bu işlem geri alınamaz.');">@csrf @method('DELETE')<button class="btn danger" type="submit">Kalıcı sil</button></form>
@endif</div></article>@endforeach</div><div class="pagination">{{ $assets->links() }}</div>@endif
    </section>
</main>
<style>
.crumb{margin-bottom:24px}.crumb a{text-decoration:none;color:var(--muted)}.upload-panel{max-width:850px;margin-top:24px}.field{display:grid;gap:7px;margin:18px 0}.field label{font-weight:700}.field input,.field select{width:100%;padding:13px 14px;border-radius:10px;border:1px solid #405367;background:#0b131d;color:white;font:inherit}.field input[type=file]{padding:10px}.field small{color:var(--muted);font-size:.82rem}.req{color:#ffb2b8}.library-head{display:flex;justify-content:space-between;align-items:center;gap:16px;flex-wrap:wrap}.search-form{display:flex;gap:8px;flex-wrap:wrap;min-width:min(100%,520px)}.search-form input,.search-form select{min-width:0;flex:1;padding:10px;border-radius:9px;border:1px solid var(--line);background:#0b131d;color:white}.asset-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:16px;margin-top:20px}.asset-card{border:1px solid var(--line);border-radius:14px;overflow:hidden;min-width:0;background:#0c1621}.preview{height:180px;background:#07101a;display:grid;place-items:center}.preview img,.preview video{width:100%;height:100%;object-fit:contain}.processing-note{padding:16px;text-align:center;color:var(--muted);overflow-wrap:anywhere}.asset-info{padding:14px;display:grid;gap:8px;min-width:0}.asset-info>strong,.small{overflow-wrap:anywhere}.small{font-size:.8rem}.badges{display:flex;gap:6px;flex-wrap:wrap}.badges span{font-size:.75rem;border:1px solid var(--line);border-radius:99px;padding:3px 8px;color:var(--muted)}.usage{font-size:.86rem;line-height:1.5;overflow-wrap:anywhere}.btn.danger{background:#542a32;color:#ffe3e6;padding:8px 12px}.success{margin:20px 0;background:#17372d;border:1px solid #376f59;padding:14px 16px;border-radius:10px}.errors{margin:20px 0;background:#47242b;padding:12px 16px;border-radius:10px}.pagination{margin-top:20px}.chunk-progress{margin-top:12px;min-width:0}#chunk_video_progress{accent-color:#79d4b4;height:12px}#chunk_video_status{overflow-wrap:anywhere;line-height:1.5}.empty{padding:30px 0;text-align:center}.stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin-top:20px}.stats>div{border:1px solid var(--line);border-radius:12px;padding:16px;display:grid;gap:5px}.stats strong{font-size:1.3rem}.stats span{color:var(--muted);font-size:.82rem}@media(max-width:950px){.asset-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:600px){.asset-grid,.stats{grid-template-columns:1fr 1fr}.preview{height:140px}.search-form{width:100%;min-width:0}.search-form>*{flex-basis:100%}.upload-panel{padding:17px}}@media(max-width:380px){.asset-grid,.stats{grid-template-columns:1fr}}
</style>
<script>
(() => {
    const form = document.getElementById('chunk-video-form');
    if (!form) return;
    const fileInput = document.getElementById('chunk_video_file');
    const button = document.getElementById('chunk_video_start');
    const progress = document.getElementById('chunk_video_progress');
    const status = document.getElementById('chunk_video_status');
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || @json(csrf_token());
    const createUrl = @json(route('admin.video-uploads.create'));
    const returnExperienceUrl = @json($selectedExperienceId ? route('admin.experiences.edit', $selectedExperienceId) : null);
    const storageKey = file => `orvionart-video-upload:${file.name}:${file.size}:${file.lastModified}`;
    const setStatus = (message, isError = false) => {
        status.textContent = message;
        status.style.color = isError ? '#ffb2b8' : '';
    };
    async function jsonFetch(url, options = {}) {
        const response = await fetch(url, {credentials: 'same-origin', ...options, headers: {'Accept': 'application/json', 'X-CSRF-TOKEN': csrf, ...(options.headers || {})}});
        let body = {};
        try { body = await response.json(); } catch (_) {}
        if (!response.ok) throw new Error(body.message || `İstek başarısız (${response.status}).`);
        return body;
    }
    async function sha256(blob) {
        if (!window.crypto?.subtle) throw new Error('Devam edebilen yükleme için HTTPS üzerinden güvenli tarayıcı gerekir.');
        const digest = await window.crypto.subtle.digest('SHA-256', await blob.arrayBuffer());
        return Array.from(new Uint8Array(digest), byte => byte.toString(16).padStart(2, '0')).join('');
    }
    function mimeFor(file) {
        if (['video/mp4','video/webm','video/ogg'].includes(file.type)) return file.type;
        const name = file.name.toLowerCase();
        if (name.endsWith('.mp4')) return 'video/mp4';
        if (name.endsWith('.webm')) return 'video/webm';
        if (name.endsWith('.ogv') || name.endsWith('.ogg')) return 'video/ogg';
        return '';
    }
    form.addEventListener('submit', async event => {
        event.preventDefault();
        const file = fileInput.files?.[0];
        if (!file) return setStatus('Önce bir video dosyası seçin.', true);
        if (file.size < 1 || file.size > 512 * 1024 * 1024) return setStatus('Video boyutu 1 bayt ile 512 MB arasında olmalıdır.', true);
        const mime = mimeFor(file);
        if (!mime) return setStatus('Yalnızca MP4, WebM veya OGG video dosyaları kabul edilir.', true);
        button.disabled = true;
        fileInput.disabled = true;
        progress.style.display = 'block';
        progress.value = 0;
        const key = storageKey(file);
        try {
            let session = null;
            const saved = localStorage.getItem(key);
            if (saved) {
                try {
                    const candidate = JSON.parse(saved);
                    const current = await jsonFetch(candidate.status_url);
                    if (current.status === 'uploading' && current.total_size === file.size && current.original_name === file.name && current.declared_mime === mime) {
                        let sameFile = true;
                        for (const index of (current.received_chunks || []).map(Number)) {
                            const start = index * candidate.chunk_size;
                            const part = file.slice(start, Math.min(file.size, start + candidate.chunk_size));
                            const expectedHash = (current.chunk_hashes || {})[String(index)];
                            if (!expectedHash || await sha256(part) !== expectedHash) { sameFile = false; break; }
                        }
                        if (sameFile) {
                            session = {...candidate, received_chunks: current.received_chunks || [], chunk_hashes: current.chunk_hashes || {}};
                            setStatus('Önceki oturum ve tamamlanmış parçaların SHA-256 değerleri doğrulandı; eksik parçalar sürdürülüyor.');
                        } else {
                            try { await jsonFetch(candidate.cancel_url, {method: 'DELETE'}); } catch (_) {}
                            localStorage.removeItem(key);
                            setStatus('Seçilen dosya önceki oturumla aynı değil; yeni oturum başlatılıyor.');
                        }
                    } else if (current.status === 'completed') {
                        // Completion may have succeeded on the server while its HTTP response was lost.
                        // Reuse the completed result instead of creating a duplicate MediaAsset.
                        localStorage.removeItem(key);
                        if (current.media_asset_id) {
                            setStatus('Bu video önceki denemede başarıyla kaydedilmiş. Medya kütüphanesine yönlendiriliyorsunuz.');
                            window.location.assign(current.redirect_url || returnExperienceUrl || @json(route('admin.media.index')));
                            return;
                        }
                        throw new Error('Sunucu yüklemeyi tamamlanmış gösteriyor ancak medya kaydı bulunamadı. Durumu yönetici kontrol etmelidir.');
                    }
                } catch (_) { localStorage.removeItem(key); }
            }
            if (!session) {
                const chunkSize = 4 * 1024 * 1024;
                const totalChunks = Math.ceil(file.size / chunkSize);
                session = await jsonFetch(createUrl, {method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({file_name: file.name, mime_type: mime, total_size: file.size, total_chunks: totalChunks, title: document.getElementById('chunk_video_title').value, alt_text: document.getElementById('chunk_video_alt').value, experience_id: document.getElementById('chunk_video_experience').value || null})});
                session.received_chunks = [];
                localStorage.setItem(key, JSON.stringify(session));
            }
            const received = new Set((session.received_chunks || []).map(Number));
            let doneBytes = 0;
            for (const index of received) {
                doneBytes += Math.min(session.chunk_size, file.size - index * session.chunk_size);
            }
            progress.value = Math.round((doneBytes / file.size) * 100);
            for (let index = 0; index < session.total_chunks; index++) {
                if (received.has(index)) continue;
                const start = index * session.chunk_size;
                const part = file.slice(start, Math.min(file.size, start + session.chunk_size));
                let uploaded = false;
                let lastError = null;
                for (let attempt = 1; attempt <= 3 && !uploaded; attempt++) {
                    try {
                        const data = new FormData();
                        data.append('chunk', part, `chunk-${index}.part`);
                        const partHash = await sha256(part);
                        const url = session.chunk_url_template.replace('__INDEX__', String(index));
                        await jsonFetch(url, {method: 'POST', body: data, headers: {'X-Chunk-SHA256': partHash}});
                        uploaded = true;
                    } catch (error) {
                        lastError = error;
                        if (attempt < 3) await new Promise(resolve => setTimeout(resolve, 700 * attempt));
                    }
                }
                if (!uploaded) throw lastError || new Error('Parça yüklenemedi.');
                doneBytes += part.size;
                progress.value = Math.round((doneBytes / file.size) * 100);
                setStatus(`Video parçaları yükleniyor: ${index + 1}/${session.total_chunks} · ${progress.value}%`);
            }
            setStatus('Bütün parçalar yüklendi; dosya birleştiriliyor ve gerçek video türü doğrulanıyor.');
            const result = await jsonFetch(session.complete_url, {method: 'POST'});
            localStorage.removeItem(key);
            progress.value = 100;
            setStatus('Video birleştirildi ve medya kütüphanesine kaydedildi; teknik doğrulama arka planda devam edecek. Listeye yönlendiriliyorsunuz.');
            window.location.assign(result.redirect_url || @json(route('admin.media.index')));
        } catch (error) {
            setStatus(`${error.message} Devam etmek için aynı dosyayı yeniden seçip tekrar deneyin.`, true);
        } finally {
            button.disabled = false;
            fileInput.disabled = false;
        }
    });
})();
</script>

@endsection
