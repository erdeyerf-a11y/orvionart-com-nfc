# ORVIONART V1.5 Ana Talimat Gap İncelemesi — 0.10.74

**İnceleme tarihi:** 2026-10-16  
**Esas belge:** `docs/ORVIONART_ANA_TALIMAT_V1_5.txt`  
**Kaynak sürümü:** `0.10.74`  
**Git commit:** `12af3be`

## 1. Genel sonuç

Kaynak kodu, GitHub deposu, Composer lock dosyası, kontrollü ZIP paketi ve ZIP preflight kontrolü hazırdır. Ancak V1.5 ana talimatına göre proje henüz **gerçek sunucuda kurulmuş, staging URL'si açılmış veya üretim kabulünden geçmiş değildir**.

En önemli durum:

- **Kaynak teslimatı:** Hazır.
- **ZIP güvenlik/preflight:** PASS.
- **GitHub aktarımı:** PASS.
- **Site Builder hedef testleri:** 46 test geçti.
- **Son tam test koşusu:** 204 test ve 837 assertion başarılı; tam Laravel test paketi PASS.
- **Gerçek Plesk/MariaDB/staging:** NOT RUN / erişim yok.
- **Gerçek URL:** Yok.
- **Canlıya dağıtım:** Yapılmadı.

## 2. Kritik — canlıya/staging'e geçmeden önce zorunlu eksikler

### 2.1 Aşama 0 — gerçek sunucu keşfi tamamlanmamış

Ana talimatın 2.2 ve Aşama 0 maddeleri henüz gerçek kanıtla doldurulmadı:

- Plesk veya sunucu paneli sürümü.
- Gerçek PHP sürümü ve aktif eklentiler.
- Composer CLI durumu.
- MySQL/MariaDB sürümü.
- Document root ve uygulama kökü.
- HTTPS/SSL durumu.
- Ayrı staging alan adı/dizini.
- Ayrı staging veritabanı ve medya alanı.
- Cron ve queue worker imkânı.
- Disk, upload, post ve video işleme sınırları.
- Yedekleme ve geri yükleme yolu.
- Mevcut canlı dosya/veritabanı envanteri.

**Durum:** BLOCKED — gerçek sunucu erişimi ve bilgileri yok.

### 2.2 Aşama 1 — güvenli staging ortamı yok

Ana talimat staging için HTTPS, parola/erişim kısıtlaması, noindex, ayrı DB ve ayrı medya alanı istiyor. Şu anda:

- Gerçek staging URL'si yok.
- Gerçek staging DB'si yok.
- Plesk'e kurulum yapılmadı.
- HTTPS ve erişim kısıtlaması gerçek ortamda doğrulanmadı.
- Canlı DB'ye yanlışlıkla yazmayı engelleyen gerçek ortam doğrulaması yapılmadı.

**Durum:** NOT RUN.

### 2.3 Gerçek kabul testleri yapılmamış

V1.5 bölüm 20'ye göre aşağıdakiler gerçek staging/cihaz üzerinde yapılmalı:

- Süper Admin giriş, çıkış, throttling, oturum yenileme ve yetki testi.
- Gerçek MariaDB migration/rollback testi.
- Fotoğraf ve video upload testi.
- Büyük video, kesintili mobil ağ ve queue/worker testi.
- FFmpeg/FFprobe gerçek worker testi.
- QR'ın gerçek telefon kamerasıyla okutulması.
- NFC URL'sinin fiziksel etikete yazılması ve okutulması.
- Gerçek yazıcı/etiket boyutunda QR baskı testi.
- Masaüstü/tablet/mobil responsive kabulü.
- Site Builder yayın, önizleme, geri alma ve bakım modu kabulü.
- Backup/restore staging tatbikatı.
- Canlı alanın değişmediğinin kontrolü.

**Durum:** NOT RUN.

### 2.4 Güncelleme/rollback yönetim modülü tamamlanmamış

0.10.74 gap matrisiyle uyumlu olarak:

- ZIP preflight mevcut.
- SHA-256, manifest, path traversal, symlink ve sır dosyası kontrolleri mevcut.
- Ancak kontrollü staging dağıtımı,
- yedek alma/geri alma akışı,
- dağıtım geçmişi ekranı,
- migration sonucu kaydı,
- yetkili rollback arayüzü

tamamlanmış bir uygulama modülü olarak mevcut değil.

**Durum:** Kaynak preflight temeli PASS; operasyonel deployment/rollback modülü NOT COMPLETE.

## 3. Yüksek öncelik — test ve belge tutarsızlıkları

### 3.1 `DEVELOPMENT_STATUS.md` güncel değil

Dosya hâlâ **son kaynak sürüm 0.10.70** diyor ve Composer/Laravel runtime'ın bulunmadığını belirtiyor. Oysa:

- Kaynak sürümü 0.10.74.
- `composer.lock` oluşturuldu.
- Sandbox'ta Composer, PHP ve vendor kurulumu yapıldı.
- Site Builder testleri çalıştırıldı.
- Tam test paketinin son koşusunda 181 PASS ve 23 FAIL görüldü.

Bu belge yeni durumla güncellenmeli; aksi halde teslimat raporu gerçeği yansıtmıyor.

### 3.2 `V1_5_GAP_MATRIX_0_10_74.md` test durumunu güncellemeli

Matrisin 8. maddesi Feature testlerinin çalıştırılmadığını söylüyor. Bu ifade paket oluşturma anında doğru olsa da mevcut çalışma durumunda artık güncel değil.

Güncellenmesi gerekenler:

- Hangi testlerin çalıştığı.
- Son tam test sonucu.
- Kalan 23 hata başlığı.
- Sonraki küçük düzeltmelerden sonra tam testin henüz yeniden koşturulmadığı.

### 3.3 `INSTALL_STAGING.md` sürüm etiketi eski

Dosya başlığında `0.10.57` yazıyor. Ayrıca Composer lock dosyasının teslimatta bulunmayabileceğini varsayıyor. Güncel paket 0.10.74 ve `composer.lock` içeriyor.

### 3.4 Son test raporu ve PASS/FAIL/BLOCKED/NOT RUN listesi eksik

Ana talimatın 23. maddesi ve ilerleme raporu şablonu aşağıdakileri ayrı kayıt olarak istiyor:

- PASS listesi.
- FAIL listesi.
- BLOCKED listesi.
- NOT RUN listesi.
- Bilinen sorunlar ve riskler.
- Bir sonraki aşama.
- Kullanıcıdan gereken kararlar.

Mevcut belgelerde bu bilgiler dağınık ve eski sürümler arasında bulunuyor; 0.10.74 için tek güncel son test raporu yok.

## 4. Orta öncelik — ürün kapsamı ve kabul edilmesi gereken alanlar

### 4.1 Site Builder V1.5 kapsamı gerçek tarayıcıda kabul edilmedi

Kaynakta Site Builder bölümleri, tema ayarları, SEO, sosyal görsel, iletişim, müşteri görüşleri ve istatistik yapıları bulunuyor. Ancak ana talimatın istediği şu kabul noktaları gerçek tarayıcıda doğrulanmadı:

- Hero slider'ın mobil davranışı.
- Masaüstü/tablet/mobil önizleme.
- Otomatik hareketi durdurma ve erişilebilirlik.
- Footer ve hukuki sayfa bağlantıları.
- QR, NFC ve QR+NFC pazarlama bölümlerinin doğru CTA'ları.
- Deneyim listesinin pasif/taslak/süresi dolmuş içerikleri gizlemesi.
- Menüde ölü bağlantı tespiti.
- Gerçek cihazda yatay taşma ve dokunma hedefleri.

### 4.2 Ödeme modülü bilinçli olarak pasif

Bu bir hata değildir. V1.5 talimatına göre ödeme başlangıçta pasif olmalıdır. Ancak gerçek staging kabulünde ayrıca kanıtlanmalıdır:

- `ORVIONART_PAYMENTS_ENABLED=false`.
- Ödeme endpoint'lerinin sunucu tarafında engelli olması.
- Site Builder yayınlamasının ödeme sistemini açamaması.
- Gerçek sağlayıcıya yanlışlıkla istek gitmemesi.

### 4.3 Hukuki metin ve gerçek şirket bilgileri eksik olabilir

Ana talimat uydurma şirket/iletişim/hukuki metin yayınlanmasını yasaklıyor. Gerçek şirket bilgileri kullanıcı tarafından verilmeden şu sayfalar tamamlanmış üretim içeriği olarak yayınlanmamalı:

- Gizlilik/KVKK.
- Kullanım koşulları.
- İletişim ve şirket bilgileri.
- Çerez bilgilendirmesi gerekiyorsa ilgili metin.

## 5. Teknik borç / son testte düzeltilmesi gerekenler

Son tam test koşusunda kalan hata grupları:

- Demo Engine lifecycle ve media denetim komutlarının beklenen çıktı uyumsuzlukları.
- Admin media preview ve media reference/storage inspection çıktıları.
- Platform integrity inspection çıktısı.
- Bazı public experience/demo akışları.
- Cache-Control header biçimi/sırası beklentileri.
- QR/NFC export ve bazı medya upload kabul senaryoları.

Bu hatalar canlıya çıkmadan önce yeniden sınıflandırılmalı:

1. Gerçek uygulama hatası mı?
2. Test beklentisi eski mi?
3. Header normalizasyonu nedeniyle yalnızca test uyumsuzluğu mu?
4. Gerçek MariaDB/filesystem davranışı mı bekleniyor?

## 6. Hazır olanlar

- GitHub ana dalı güncel ve temiz.
- `composer.lock` mevcut.
- `RELEASE_MANIFEST.json` 0.10.74 ile yenilenmiş.
- Sunucu ZIP'i oluşturuldu.
- ZIP SHA-256: `8a8c152d4b11b90b37158237c538f6c33a5878770bd2f63e7e05a9e42b1e8843`.
- ZIP preflight sonucu: `PREFLIGHT_PASS`.
- ZIP içinde `.env`, gerçek sır, vendor/runtime verisi bulunmuyor.
- Site Builder hedef paketi: 46 test geçti.
- GitHub commitleri:
  - `d528a49 fix: stabilize tests and deployment preflight`
  - `12af3be chore: refresh release manifest`

## 7. Önerilen sonraki sıra

1. Kullanıcıdan gerçek Plesk/sunucu erişimi veya sunucu envanteri alın.
2. Ayrı staging hostname, dizin, DB ve medya alanını belirleyin.
3. HTTPS + parola koruması + noindex kurun.
4. ZIP'i staging'e kontrollü yükleyin; Composer kurun.
5. `scripts/preflight.php --check-db` ve migration kontrolünü çalıştırın.
6. İlk Süper Admin hesabını etkileşimli oluşturun.
7. Staging HTTP smoke ve gerçek tarayıcı kabul testlerini yürütün.
8. Gerçek telefonda QR/NFC, mobil ve baskı testlerini yapın.
9. Backup/restore tatbikatını tamamlayın.
10. Tam test paketini düzeltmelerden sonra yeniden çalıştırın.
11. 0.10.74 için tek güncel son test ve ilerleme raporu oluşturun.
12. Ancak bundan sonra kontrollü canlıya geçiş kararı verin.

## Son karar

**0.10.74 kaynak teslimatı hazır; gerçek sunucuya kurulum ve üretim kabulü hazır olarak ilan edilemez.** En büyük eksik, koddan ziyade gerçek staging/operasyon doğrulamasının ve güncel tekil teslimat raporunun bulunmamasıdır.
