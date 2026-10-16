<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Experience;
use App\Models\MediaAsset;
use App\Models\QrLink;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        return view('admin.dashboard', [
            'user' => request()->user(),
            'experienceCount' => Experience::count(),
            'draftCount' => Experience::where('status', 'draft')->count(),
            'publishedCount' => Experience::query()->publiclyAvailable()->count(),
            'mediaCount' => MediaAsset::count(),
            'activeQrCount' => QrLink::where('is_active', true)->count(),
            'modules' => [
                ['name' => 'Deneyim Yönetimi', 'status' => 'İlk sürüm hazır', 'description' => 'Deneyim kaydı oluşturma, düzenleme, taslak/zamanlı/yayınlandı durumu, arama ve işlem kayıtları.'],
                ['name' => 'Medya ve Video', 'status' => 'İlk sürüm hazır', 'description' => 'Özel depolama, izinli görsel/video türleri, deneyime bağlama ve yayın kontrollü sunum.'],
                ['name' => 'QR / NFC', 'status' => 'İlk sürüm hazır', 'description' => 'Kalıcı yönlendirme, SVG QR indirme, NFC bağlantı metni ve toplu olmayan gizlilik odaklı sayaç.'],
                ['name' => 'Demo Engine', 'status' => 'İlk sürüm hazır', 'description' => 'Bağımsız demo içerik havuzu, cihaz belirteci, günlük içerik ataması, 7 günlük varsayılan yolculuk ve ayrı istatistikler.'],
                ['name' => 'Site Builder', 'status' => 'İlk sürüm hazır', 'description' => 'Sayfalar, güvenli bölümler, tema ayarları, navigasyon, taslak/yayın ve sürüm geçmişi.'],
                ['name' => 'Kullanım Hakları', 'status' => 'Temel kayıt mevcut', 'description' => 'Gelişmiş ücretsiz kullanım ve süre kuralları son kabul aşamasında ayrıca doğrulanacak.'],
            ],
        ]);
    }
}
