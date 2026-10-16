@extends('layouts.app')
@section('title', 'Süper Admin Girişi · ORVIONART')
@section('content')
<main class="wrap"><section class="panel login"><div class="eyebrow">Merkezi yönetim</div><h1>Süper Admin</h1><p class="muted">ORVIONART platform yönetimine erişmek için hesabınızla giriş yapın.</p>
@if ($errors->any())<div class="errors" role="alert"><ul style="margin:0;padding-left:20px">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<form method="post" action="{{ route('admin.login.store') }}" autocomplete="on">@csrf<div class="field"><label for="email">E-posta adresi</label><input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="username" maxlength="254" required autofocus></div><div class="field"><label for="password">Parola</label><input id="password" name="password" type="password" autocomplete="current-password" maxlength="1024" required></div><button class="btn" type="submit" style="width:100%">Güvenli giriş</button></form><p class="muted" style="font-size:.85rem;margin-top:22px">Herkese açık kayıt kapalıdır. İlk yönetici hesabı sunucuda, komut satırından oluşturulur.</p></section><div class="footer" style="text-align:center">ORVIONART · Geliştirme altyapısı · {{ date('Y') }}</div></main>
@endsection
