@extends('layouts.app')
@section('title', 'Denetim Kayıtları · ORVIONART')
@section('content')
<main class="wrap">
    <div class="crumb"><a href="{{ route('admin.dashboard') }}">← Yönetim merkezi</a></div>
    <section class="hero"><div><div class="eyebrow">Güvenlik ve izlenebilirlik</div><h1>Denetim kayıtları</h1><p class="muted">Yönetim işlemlerinin salt okunur olay geçmişi. Bu ekrandan kayıt değiştirilemez veya silinemez.</p></div></section>
    <div class="notice"><strong>Gizlilik notu:</strong> IP adresleri, tarayıcı bilgileri ve işlem metaverileri hassas operasyonel bilgi içerebilir. Erişim yalnızca yetkili Süper Admin hesaplarıyla sınırlandırılmıştır. Metaveride parola/token benzeri anahtarlar görünümde maskelenir.</div>
    <section class="stats"><div><strong>{{ number_format($totalCount) }}</strong><span>Toplam olay kaydı</span></div><div><strong>{{ $logs->total() }}</strong><span>Filtreye uyan kayıt</span></div></section>
    @if($errors->any())<div class="errors" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <section class="panel" style="margin-top:20px">
        <form class="filters" method="get" action="{{ route('admin.audit-logs.index') }}">
            <label><span class="sr-only">Olay veya yönetici ara</span><input type="search" name="q" maxlength="120" value="{{ $filters['q'] ?? '' }}" placeholder="Olay, yönetici veya kayıt ara"></label>
            <label><span class="sr-only">Olay türü</span><select name="action"><option value="">Tüm olay türleri</option>@foreach($actions as $action)<option value="{{ $action }}" @selected(($filters['action'] ?? '') === $action)>{{ $action }}</option>@endforeach</select></label>
            <label><span class="sr-only">Başlangıç tarihi</span><input type="date" name="from" value="{{ $filters['from'] ?? '' }}" aria-label="Başlangıç tarihi"></label>
            <label><span class="sr-only">Bitiş tarihi</span><input type="date" name="to" value="{{ $filters['to'] ?? '' }}" aria-label="Bitiş tarihi"></label>
            <button class="btn secondary" type="submit">Filtrele</button>
            @if(count($filters))<a href="{{ route('admin.audit-logs.index') }}">Temizle</a>@endif
        </form>
        @if($logs->isEmpty())
            <div class="empty"><h2>Kayıt bulunamadı</h2><p class="muted">Filtreleri değiştirerek yeniden deneyin.</p></div>
        @else
            <div class="table-wrap"><table><thead><tr><th>Zaman</th><th>Olay</th><th>Yönetici</th><th>İlgili kayıt</th><th>Bağlantı bilgisi</th><th>Metaveri</th></tr></thead><tbody>
            @foreach($logs as $log)
                <tr>
                    <td><strong>{{ $log->created_at?->format('d.m.Y H:i:s') ?? '—' }}</strong><div class="small">#{{ $log->id }}</div></td>
                    <td><code>{{ $log->action }}</code></td>
                    <td>{{ $log->actor?->name ?? 'Sistem / silinmiş kullanıcı' }}@if($log->actor?->email)<div class="small">{{ $log->actor->email }}</div>@endif</td>
                    <td>{{ $log->subject_type ?: '—' }}<div class="small">{{ $log->subject_id ?: '—' }}</div></td>
                    <td>{{ $log->ip_address ?: '—' }}@if($log->user_agent)<details><summary>Tarayıcı bilgisi</summary><p class="ua">{{ $log->user_agent }}</p></details>@endif</td>
                    <td>@if(!empty($log->safe_metadata))<details><summary>Metaveriyi görüntüle</summary><pre>{{ json_encode($log->safe_metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre></details>@else<span class="muted">Metaveri yok</span>@endif</td>
                </tr>
            @endforeach
            </tbody></table></div>
            <div class="pagination">{{ $logs->links() }}</div>
        @endif
    </section>
</main>
<style>
.crumb{margin-bottom:24px}.crumb a{text-decoration:none;color:var(--muted)}.notice{margin:18px 0}.stats{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;margin-top:20px}.stats>div{border:1px solid var(--line);border-radius:12px;padding:16px;background:#111d29}.stats strong{display:block;font-size:1.5rem}.stats span,.small{display:block;color:var(--muted);font-size:.82rem}.filters{display:flex;gap:9px;align-items:center;flex-wrap:wrap;margin-bottom:18px}.filters input,.filters select{max-width:100%;padding:10px 12px;border-radius:9px;border:1px solid #405367;background:#0b131d;color:white;font:inherit;min-height:43px}.filters label:first-child{flex:1;min-width:200px}.filters label:first-child input{width:100%}.table-wrap{overflow-x:auto}table{border-collapse:collapse;width:100%;min-width:1000px}th,td{text-align:left;vertical-align:top;padding:12px;border-bottom:1px solid var(--line);overflow-wrap:anywhere}th{color:var(--muted);font-size:.8rem}td code{overflow-wrap:anywhere}details{margin-top:6px}summary{cursor:pointer;color:#b8d4f5;font-size:.85rem}pre{white-space:pre-wrap;overflow-wrap:anywhere;max-width:360px;max-height:260px;overflow:auto;padding:10px;background:#0b131d;border-radius:8px;font-size:.78rem}.ua{max-width:260px;overflow-wrap:anywhere;font-size:.78rem}.pagination{margin-top:18px}.pagination nav{display:flex;justify-content:center;gap:8px}.pagination a,.pagination span{padding:6px 10px;border:1px solid var(--line);border-radius:8px;text-decoration:none}.empty{text-align:center;padding:32px}@media(max-width:760px){.filters>*{width:100%;max-width:none!important}.stats{grid-template-columns:1fr}.panel{overflow:hidden}}
</style>
@endsection
