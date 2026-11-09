# Kitap Dağıtım ve Cari Takip

PHP ve MySQL/MariaDB tabanlı, Türkçe kitap dağıtımı, stok, satış ve cari takip uygulaması.

## Özellikler

- Kitap kartları, stok girişleri, koli hesabı, otomatik satış stok düşümü ve kritik stok uyarıları
- Okul ve kırtasiye müşteri kartları; il, ilçe ve iletişim bilgileri
- Vadeli satış, satış kalemleri, tahsilat ve müşteri ekstresi
- FIFO tahsilat mahsuplaştırmasıyla gecikmiş alacak takibi
- Yönetici paneli, grafikler ve filtrelenebilir kitap, müşteri, stok, cari ve vade raporları
- Yazdırılabilir ekstre ve çok sekmeli Excel (`.xlsx`) dışa aktarımı
- Google Sheets'e elle veya cron ile veri aktarımı

## Gereksinimler

- PHP 8.1 veya üzeri; `pdo_mysql`, `mbstring`, `zip`, `curl` ve OpenSSL eklentileri
- MySQL 8+ veya MariaDB 10.4+
- Google Sheets entegrasyonu için Google Cloud servis hesabı ve Sheets API erişimi (isteğe bağlı)

## Kurulum

1. Yetkili bir MySQL/MariaDB hesabıyla veritabanı ve uygulama kullanıcısı oluşturun. Uygulama kullanıcısına yalnızca bu veritabanı üzerinde yetki verin:

   ```sql
   CREATE DATABASE kitapcari CHARACTER SET utf8mb4 COLLATE utf8mb4_turkish_ci;
   CREATE USER 'kitapcari_app'@'localhost' IDENTIFIED BY 'uzun-ve-benzersiz-bir-sifre';
   GRANT ALL PRIVILEGES ON kitapcari.* TO 'kitapcari_app'@'localhost';
   ```

2. Bağlantıyı `config.php` içinden veya `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS` ortam değişkenleriyle ayarlayın. Örneğin `DB_NAME=kitapcari`, `DB_USER=kitapcari_app` ve `DB_PASS` veritabanı kullanıcısının parolası olmalıdır.
3. Proje dizininde şemayı kurup yönetici hesabı oluşturun:

   ```sh
   php install.php admin 'uzun-ve-benzersiz-bir-sifre'
   ```

   İsteğe bağlı örnek veriler için:

   ```sh
   php install.php admin 'uzun-ve-benzersiz-bir-sifre' --demo
   ```

4. Geliştirme amacıyla yerel sunucuyu başlatın:

   ```sh
   php -S 127.0.0.1:8000
   ```

   Tarayıcıda `http://127.0.0.1:8000` adresini açın. Üretim ortamında PHP destekli Apache veya başka bir web sunucusu kullanın; uygulamayı HTTPS üzerinden sunun ve proje içindeki `sql/`, `config.php`, `lib.php`, `install.php`, `cron_sync.php` ile servis hesabı anahtar dosyasına web üzerinden erişimi engelleyin. Kurulumdan sonra `install.php` dosyasını web erişimine açık bırakmayın.

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

## Veri ve hesaplama notları

- Güncel stok, toplam stok girişinden satış adetleri çıkarılarak hesaplanır; stok girişleri pozitif adet olarak kaydedilir.
- Satışta fiyat boş bırakılırsa kitap kartındaki satış fiyatı kullanılır. Koli miktarı girilirse adet, kitap kartındaki koli adedinden hesaplanır.
- Tahsilatlar, müşteri bazında en eski satışlardan başlayarak FIFO yöntemiyle mahsup edilir. Bu nedenle vadesi geçmiş raporu tahsilatı belirli bir faturaya elle bağlamaz.
- Tutar girişlerinde `1234,50` veya `1234.50` kullanılabilir.

## Lisans

Bu proje Apache License 2.0 ile lisanslanmıştır. Ayrıntılar için [LICENSE](LICENSE) dosyasına bakın.
