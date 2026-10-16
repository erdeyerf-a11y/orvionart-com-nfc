<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="referrer" content="strict-origin-when-cross-origin">
    <meta name="robots" content="{{ app()->environment('production') ? 'index,follow' : 'noindex,nofollow,noarchive' }}">
    <link rel="canonical" href="{{ $canonicalUrl }}">
    <title>{{ $experience->title }} · ORVIONART</title>
    @if($experience->summary)<meta name="description" content="{{ $experience->summary }}">@endif
    <style>
        :root{color-scheme:light;--ink:#17252d;--muted:#62717a;--line:#dce5e8;--sea:#0b6f78;--paper:#f5f8f7;--white:#fff}*{box-sizing:border-box}html{scroll-behavior:smooth}body{margin:0;background:var(--paper);color:var(--ink);font:17px/1.75 system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}.shell{width:min(820px,calc(100% - 36px));margin:0 auto}.top{background:#102b35;color:#fff;border-bottom:4px solid #7cc9c3}.top-inner{min-height:76px;display:flex;align-items:center;justify-content:space-between;gap:16px}.brand{font-size:1.05rem;font-weight:850;letter-spacing:.16em}.brand span{color:#8ce0d8}.tag{font-size:.75rem;letter-spacing:.12em;text-transform:uppercase;color:#c4dbde}main{padding:clamp(34px,8vw,76px) 0 70px}.eyebrow{font-size:.76rem;font-weight:800;letter-spacing:.18em;text-transform:uppercase;color:var(--sea)}h1{font-size:clamp(2.2rem,7vw,4.5rem);line-height:1.08;letter-spacing:-.045em;margin:12px 0 22px;overflow-wrap:anywhere}.summary{font-size:clamp(1.1rem,2.7vw,1.35rem);color:#465b63;line-height:1.65}.rule{height:1px;background:var(--line);margin:34px 0}.content{white-space:normal;overflow-wrap:anywhere}.content p{margin:0 0 1.25em}.empty-content{color:var(--muted);font-style:italic}.foot{border-top:1px solid var(--line);padding:22px 0 30px;color:var(--muted);font-size:.82rem}.foot strong{color:var(--ink);letter-spacing:.08em}.sr-only{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0}@media(max-width:520px){.top-inner{min-height:64px}.tag{max-width:110px;text-align:right;font-size:.65rem}.shell{width:min(100% - 28px,820px)}body{font-size:16px}}
    .media-gallery{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,260px),1fr));gap:16px;margin:26px 0}.media-item{margin:0;min-width:0}.media-item img,.media-item video{display:block;width:100%;height:auto;max-height:560px;object-fit:contain;background:#101923;border-radius:12px}.media-item figcaption{font-size:.9rem;color:var(--muted);padding:7px 2px}.body{white-space:normal}
</style>
</head>
<body>
<header class="top"><div class="shell top-inner"><div class="brand">ORVION<span>ART</span></div><div class="tag">Dijital deneyim</div></div></header>
<main class="shell">
    <div class="eyebrow">ORVIONART · Deneyim</div>
    <h1>{{ $experience->title }}</h1>
    @if($experience->summary)<p class="summary">{{ $experience->summary }}</p>@endif
    <div class="rule" role="presentation"></div>
    @if($experience->mediaAssets->isNotEmpty())
        <section class="media-gallery" aria-label="Deneyim fotoğraf ve videoları">
            @foreach($experience->mediaAssets as $asset)
                <figure class="media-item">
                    @if($asset->isVideo())
                        <video controls playsinline preload="metadata" @if($asset->thumbnail_path && $asset->processing_status === 'ready') poster="{{ route('media.thumbnail', $asset) }}" @endif aria-label="{{ $asset->title ?: 'Deneyim videosu' }}"><source src="{{ $asset->publicUrl() }}" type="{{ $asset->processed_path ? 'video/mp4' : $asset->mime_type }}">Tarayıcınız video oynatmayı desteklemiyor.</video>
                    @else
                        <img src="{{ $asset->publicUrl() }}" alt="{{ $asset->alt_text ?: ($asset->title ?: $experience->title) }}" loading="lazy" decoding="async">
                    @endif
                    @if($asset->title)<figcaption>{{ $asset->title }}</figcaption>@endif
                </figure>
            @endforeach
        </section>
    @endif
    <article class="content" aria-label="Deneyim içeriği">
        @if($experience->body !== null && trim($experience->body) !== '')
            {!! nl2br(e($experience->body)) !!}
        @else
            <p class="empty-content">Bu deneyimin ayrıntılı içeriği henüz eklenmedi.</p>
        @endif
    </article>
</main>
<footer class="foot"><div class="shell"><strong>ORVIONART</strong><br>Seramik, sanat ve dijital hikâyeler.</div></footer>
</body>
</html>
