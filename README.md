# Kitap Dağıtım ve Cari Takip

PHP tabanlı, Türkçe kitap dağıtımı, stok, satış ve cari takip uygulaması. Varsayılan olarak sunucu gerektirmeyen SQLite dosyası kullanır; MySQL/MariaDB isteğe bağlıdır.

## Özellikler

- Kitap kartları, ayrı stok giriş/çıkış/hareket/güncel stok sayfaları, koli hesabı, otomatik satış stok düşümü ve kritik stok uyarıları
- Okul ve kırtasiye müşteri kartları; il, ilçe ve iletişim bilgileri
- Vadeli satış, satış kalemleri, tahsilat ve müşteri ekstresi
- FIFO tahsilat mahsuplaştırmasıyla gecikmiş alacak takibi
- Yönetici paneli, grafikler ve filtrelenebilir kitap, müşteri, stok, cari ve vade raporları
- Yazdırılabilir ekstre ve çok sekmeli Excel (`.xlsx`) dışa aktarımı
- Google Sheets'e elle veya cron ile veri aktarımı
- Mobil uyumlu modern arayüz; tüm listelerde sütun başlığına tıklayarak sıralama
- Stok girişinde teslim alan, stok çıkışında götüren kişi kaydı
- Müşteri ve tedarikçi iadeleri; stok ve müşteri bakiyesi güncellemesi
- Gider, bağış ve alınan borç/borç ödemesi kayıtları ile kâr-zarar özeti
- Panelde "en çok satış yapılan" ve "en yüksek alacaklı" müşteri listeleri
- Her Pazartesi 08.00'de alacaklı müşterilere e-posta ve SMS hatırlatması

## Hızlı deneme (MySQL ve e-posta olmadan)

Varsayılan dosya modu verileri tek bir SQLite dosyasında tutar; MySQL/MariaDB sunucusu veya e-posta ayarı gerekmez. PHP'de `pdo_sqlite` etkin olmalıdır. Mevcut SQLite dosyaları uygulama açıldığında otomatik ve işlem içinde güncellenir; eski kayıtlar korunur.

Güncellemeden önce veri dosyanızı yedekleyin. Eski satışların kitap maliyeti, yükseltme sırasında mevcut kitap alış fiyatıyla yaklaşık olarak doldurulur. Eski teslim alan, teslim yeri, yayınevi ve sınıf seviyesi verileri dosyada korunur, ancak yeni arayüzde kullanılmaz. Kâr/zarar; net satış, satış anındaki kitap maliyeti, gider ve bağışlardan hesaplanır. Alınan borç ve anapara geri ödemeleri kâr/zarardan ayrı izlenir.

1. Örnek veriler ve `admin / admin123` hesabıyla yerel dosyayı hazırlayın:

   ```sh
   php install.php --sqlite --demo
   ```

   Varsayılan dosya proje klasörünün dışında `../kitaptakip-data/kitaptakip.sqlite` konumuna yazılır. Yerini değiştirmek için `APP_DATA_FILE=/tam/yol/kitaptakip.sqlite` ayarlayın (kurulumda ve sunucuyu başlatırken aynı ayarı kullanın). Dosya izinleri nedeniyle kurulum ve web sunucusu aynı kullanıcıyla çalışmalıdır.

2. Yerel web sunucusunu başlatın:

   ```sh
   php -S 127.0.0.1:8000
   ```

3. `http://127.0.0.1:8000` adresinden `admin / admin123` ile giriş yapın. Kitap, stok girişi/teslimatı, müşteri, satış ve tahsilat kayıtları dosyada saklanır ve sunucu yeniden başlatıldığında korunur. Demo verisini yeniden oluşturmak veya sıfırlamak için SQLite dosyasını silip `php install.php --sqlite --demo` komutunu tekrar çalıştırın. Bu varsayılan hesabı yalnızca yerel testte kullanın; sunucuyu `127.0.0.1` adresine bağlayın ve deneme modunu internete açmayın.

Bu modda gerçek e-posta/SMS gönderimi özellikle devre dışıdır; `cron_notify.php` gönderim yapmadan açıklayıcı hata verir. Google Sheets aktarımı da servis hesabı ayarlanmadığı sürece kapalıdır.

## Gereksinimler

- PHP 8.1 veya üzeri; MySQL modu için `pdo_mysql`, yerel dosya modu için `pdo_sqlite`; dışa aktarım ve entegrasyonlara göre `mbstring`, `zip`, `curl` ve OpenSSL eklentileri
- Üretim MySQL modu için MySQL 8+ veya MariaDB 10.4+ (yerel deneme modunda MySQL gerekmez)
- Google Sheets entegrasyonu için Google Cloud servis hesabı ve Sheets API erişimi (isteğe bağlı)

## MySQL/MariaDB kurulumu (isteğe bağlı)

1. Yetkili bir MySQL/MariaDB hesabıyla veritabanı ve uygulama kullanıcısı oluşturun. Uygulama kullanıcısına yalnızca bu veritabanı üzerinde yetki verin:

   ```sql
   CREATE DATABASE kitapcari CHARACTER SET utf8mb4 COLLATE utf8mb4_turkish_ci;
   CREATE USER 'kitapcari_app'@'localhost' IDENTIFIED BY 'uzun-ve-benzersiz-bir-sifre';
   GRANT ALL PRIVILEGES ON kitapcari.* TO 'kitapcari_app'@'localhost';
   ```

2. Tüm uygulama, kurulum ve cron işlemleri için `APP_STORAGE=mysql` ayarlayın. Bağlantıyı `config.php` içinden veya `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS` ortam değişkenleriyle ayarlayın. Örneğin `DB_NAME=kitapcari`, `DB_USER=kitapcari_app` ve `DB_PASS` veritabanı kullanıcısının parolası olmalıdır.
3. Proje dizininde şemayı kurup yönetici hesabı oluşturun:

   ```sh
   APP_STORAGE=mysql php install.php admin 'uzun-ve-benzersiz-bir-sifre'
   ```

   İsteğe bağlı örnek veriler için:

   ```sh
   APP_STORAGE=mysql php install.php admin 'uzun-ve-benzersiz-bir-sifre' --demo
   ```

4. Geliştirme amacıyla yerel sunucuyu başlatın:

   ```sh
   APP_STORAGE=mysql php -S 127.0.0.1:8000
   ```

   Tarayıcıda `http://127.0.0.1:8000` adresini açın. Üretim ortamında PHP destekli Apache veya başka bir web sunucusu kullanın; uygulamayı HTTPS üzerinden sunun ve proje içindeki `sql/`, `config.php`, `lib.php`, `install.php`, `cron_sync.php` ile servis hesabı anahtar dosyasına web üzerinden erişimi engelleyin. Kurulumdan sonra `install.php` dosyasını web erişimine açık bırakmayın.

   Mevcut MySQL/MariaDB kurulumlarını güncellerken önce yedek alın, sonra `mysql -u kitapcari_app -p kitapcari < sql/schema.sql` çalıştırın. Bu işlem eski teslim alan, teslim yeri, yayınevi ve sınıf seviyesi sütunlarını kaldırır.

## Google Sheets

1. Google Cloud projesinde Google Sheets API'yi etkinleştirin ve servis hesabı için JSON anahtarı oluşturun.
2. Anahtar dosyasını web kökü dışında güvenli bir konumda tutun; dosya yolunu `GSHEETS_KEY_FILE` değişkenine verin.
3. Hedef e-tabloyu servis hesabının `client_email` adresiyle **Düzenleyici** olarak paylaşın.
4. E-tablo kimliğini `GSHEETS_ID` değişkeniyle ayarlayın. Kimlik, e-tablo URL'sindeki `/d/` ile `/edit` arasındaki bölümdür.
5. Uygulamadaki **Excel / Sheets** sayfasından aktarımı elle başlatın veya aşağıdaki cron girdisiyle düzenli çalıştırın:

   ```cron
   */15 * * * * /usr/bin/php /uygulama/yolu/cron_sync.php
   ```

   Her aktarım, uygulamanın oluşturduğu sekmeleri güncel verilerle yeniler. Kayıt işlemlerinden sonra otomatik aktarım için `GSHEETS_AUTO=1` ayarlayın. Otomatik aktarım hatası, kayıt başarılı olduktan sonra sayfada bildirilir.

## Haftalık alacak bildirimleri (e-posta ve SMS)

Bakiyesi pozitif olan müşterilere her hafta bir kez bildirim gönderilir. E-posta PHP `mail()` ile (sunucuda çalışan bir MTA gerekir), SMS Twilio ile gönderilir. Ortam değişkenleri:

- `MAIL_FROM_EMAIL`, `MAIL_FROM_NAME`
- `SMS_TWILIO_ACCOUNT_SID`, `SMS_TWILIO_AUTH_TOKEN`, `SMS_TWILIO_FROM`
- `REMINDER_CONTACT` (mesajda görünecek iletişim bilgisi, isteğe bağlı)

Pazartesi 08.00 için crontab:

```cron
CRON_TZ=Europe/Istanbul
0 8 * * 1 /usr/bin/php /uygulama/yolu/cron_notify.php
```

Aynı hafta başarıyla gönderilen kanal tekrar gönderilmez; başarısız veya iletişim bilgisi eksik kayıtlar sonraki çalıştırmada yeniden denenir. Sonuçlar **Bildirimler** sayfasında listelenir. Müşteri telefonları Türkiye numarası (`05xx...`) veya `+` ile uluslararası biçimde girilmelidir.

Mevcut kurulumda yeni tabloları oluşturmak için `php install.php` komutunu mevcut kullanıcı adıyla yeniden çalıştırın (yönetici şifresi verdiğiniz değerle güncellenir).

## Veri ve hesaplama notları

- Güncel stok = stok girişleri − satışlar − stok çıkışları (teslimat). Yetersiz stokta çıkış ve satış reddedilir.
- Satışta fiyat boş bırakılırsa kitap kartındaki satış fiyatı kullanılır. Koli miktarı girilirse adet, kitap kartındaki koli adedinden hesaplanır.
- Tahsilatlar, müşteri bazında en eski satışlardan başlayarak FIFO yöntemiyle mahsup edilir. Bu nedenle vadesi geçmiş raporu tahsilatı belirli bir faturaya elle bağlamaz.
- Tutar girişlerinde `1234,50` veya `1234.50` kullanılabilir.

## Lisans

Bu proje Apache License 2.0 ile lisanslanmıştır. Ayrıntılar için [LICENSE](LICENSE) dosyasına bakın.
