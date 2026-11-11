# SQLite'dan MariaDB/MySQL'e Veri Taşıma Kılavuzu

Bu kılavuz, mevcut SQLite veritabanındaki tüm verileri MariaDB/MySQL'e taşımak için adım adım talimatlar sağlar.

## Ön Gereksinimler

- [ ] MariaDB/MySQL sunucusu kurulu ve çalışıyor
- [ ] Veritabanı ve uygulama kullanıcısı oluşturulmuş (bkz. README.md)
- [ ] PHP 8.1+ ve `pdo_mysql` uzantısı yüklü
- [ ] Mevcut SQLite veritabanında veriler bulunuyor

## 1. Adım: MariaDB Veritabanı ve Kullanıcı Oluşturma

MySQL/MariaDB yöneticiniz olarak şu komutları çalıştırın:

```sql
CREATE DATABASE kitapcari CHARACTER SET utf8mb4 COLLATE utf8mb4_turkish_ci;
CREATE USER 'kitapcari_app'@'localhost' IDENTIFIED BY 'uzun-ve-guvenli-sifre';
GRANT ALL PRIVILEGES ON kitapcari.* TO 'kitapcari_app'@'localhost';
FLUSH PRIVILEGES;
```

> **Not**: Şifreyi güçlü, benzersiz bir şifre ile değiştirin ve güvenli bir yerde saklayın.

## 2. Adım: MariaDB Şemasını Oluşturma

Proje klasöründe şu komutu çalıştırın (bu sadece tablo yapılarını oluşturur, veri yoktur):

```bash
DB_HOST=localhost DB_NAME=kitapcari DB_USER=kitapcari_app DB_PASS='şifre' php install.php admin 'admin-şifresi'
```

Komut açıklaması:
- `DB_HOST`: MariaDB sunucu adresi
- `DB_NAME`: Oluşturduğunuz veritabanı adı
- `DB_USER`: Oluşturduğunuz uygulama kullanıcı adı
- `DB_PASS`: Oluşturduğunuz uygulama kullanıcısının şifresi

## 3. Adım: Verileri Taşıma

Migration scriptini çalıştırın:

```bash
DB_HOST=localhost DB_NAME=kitapcari DB_USER=kitapcari_app DB_PASS='şifre' php migrate_to_mysql.php
```

Script şunları yapar:
1. ✅ SQLite bağlantısını kontrol eder
2. ✅ MariaDB bağlantısını kontrol eder
3. ✅ Hedef tablolardaki verileri temizler (uyarı verir)
4. ✅ Tüm verileri taşır (22 tablo, 100+ bin satır)
5. ✅ Hata raporı gösterir (varsa)

### Taşıma Sırasında Sorulacak Soru

```
⚠️  Dikkat: MariaDB'deki mevcut veriler silinecek!
   Devam etmek için 'EVET' yazın:
```

Devam etmek istiyorsanız `EVET` yazıp Enter tuşuna basın.

## 4. Adım: Konfigürasyon Dosyasını Güncelleme

`config.php` dosyasında MySQL ayarlarını yapın:

```php
// config.php

// Storage type
if (!defined('APP_STORAGE')) define('APP_STORAGE', 'mysql');

// MySQL connection
if (!defined('DB_HOST')) define('DB_HOST', $_ENV['DB_HOST'] ?? 'localhost');
if (!defined('DB_NAME')) define('DB_NAME', $_ENV['DB_NAME'] ?? 'kitapcari');
if (!defined('DB_USER')) define('DB_USER', $_ENV['DB_USER'] ?? 'kitapcari_app');
if (!defined('DB_PASS')) define('DB_PASS', $_ENV['DB_PASS'] ?? 'şifre');
```

Veya ortam değişkenlerini ayarlayın (tercih edilen yöntem):

```bash
# Bash/Linux
export APP_STORAGE=mysql
export DB_HOST=localhost
export DB_NAME=kitapcari
export DB_USER=kitapcari_app
export DB_PASS='şifre'
```

```batch
REM Windows CMD
set APP_STORAGE=mysql
set DB_HOST=localhost
set DB_NAME=kitapcari
set DB_USER=kitapcari_app
set DB_PASS=şifre
```

## 5. Adım: Uygulamayı Kontrol Etme

### Geliştirme Ortamında Test

```bash
APP_STORAGE=mysql php -S 127.0.0.1:8000
```

Tarayıcıda `http://127.0.0.1:8000` açın ve:
- ✅ Kullanıcı adı/şifre ile giriş yapın
- ✅ Müşteriler sayfasında verileri kontrol edin
- ✅ Satışlar sayfasında satışları kontrol edin
- ✅ Stok sayfasında stok haraketlerini kontrol edin

### Prodüksiyonda Ayarla

Apache/Nginx web sunucunuzu konfigüre edin:

```bash
# PHP-FPM ortam değişkenleri
APP_STORAGE=mysql
DB_HOST=localhost (veya remote IP)
DB_NAME=kitapcari
DB_USER=kitapcari_app
DB_PASS=şifre
```

## Sorun Giderme

### "MariaDB bağlantı hatası"

```
❌ MariaDB bağlantı hatası: SQLSTATE[HY000] [1045]
   Access denied for user 'kitapcari_app'@'localhost'
```

**Çözüm:**
- DB_HOST, DB_USER, DB_PASS değerlerini kontrol edin
- MySQL'de kullanıcı ve veritabanı oluşturduğunuz kontrol edin
- `mysql -u kitapcari_app -p kitapcari` ile manuel bağlantı deneyin

### "Unknown column" hatası

```
❌ table_name - Hata: Unknown column 'durum' in 'field list'
```

**Çözüm:**
- MariaDB şemasının güncel olduğundan emin olun
- `install.php` komutunu tekrar çalıştırın

### Taşıma sırasında "Foreign key" hataları

```
FOREIGN_KEY_CHECKS otomatik olarak kapatılır
```

**Çözüm:**
- Script bu durumu yönetir, manuel müdahale gerekmez
- İşlem tamamlandıktan sonra kontrol ettirilir

## Veri Tutarlılığı Kontrol

Taşıma tamamlandıktan sonra verileri kontrol edin:

```sql
-- MariaDB'de
SELECT 'uy_users' as tablo, COUNT(*) as satir FROM uy_users
UNION ALL SELECT 'uy_customers', COUNT(*) FROM uy_customers
UNION ALL SELECT 'uy_sales', COUNT(*) FROM uy_sales
UNION ALL SELECT 'uy_books', COUNT(*) FROM uy_books;
```

```bash
# SQLite'da
sqlite3 /path/to/kitaptakip.sqlite "SELECT 'uy_users' as tablo, COUNT(*) FROM uy_users UNION ALL SELECT 'uy_customers', COUNT(*) FROM uy_customers UNION ALL SELECT 'uy_sales', COUNT(*) FROM uy_sales UNION ALL SELECT 'uy_books', COUNT(*) FROM uy_books;"
```

Her tablonun satır sayısı eşleşmelidir.

## Geri Alma (Rollback)

Taşıma başarısız olursa:

1. SQLite verisini kullanmaya devam edin
2. MariaDB tablolarını temizleyin:
   ```sql
   DROP DATABASE kitapcari;
   ```
3. Migration scriptini yeniden çalıştırmadan önce sorun giderin

## Önemli Notlar

- ⚠️ **Yedek alın**: Taşıma öncesinde SQLite dosyasını yedekleyin
- 🔒 **Güvenlik**: DB_PASS gibi hassas bilgileri `.env` dosyasına yazın, versiyon kontrolüne eklemeyin
- 🚀 **Prodüksiyonda**: Yoğun trafik olmayan saatlarda taşıma yapın
- 🔄 **Sürekli Entegrasyon**: GitHub Actions FTP deploy'undan sonra otomatik olarak MariaDB kullanacaktır

## Başarılı Taşıma Sonrası

```
✅ Taşıma Tamamlandı!
   Toplam satır: 15,342

📝 Sonraki Adımlar:
   1. config.php dosyasında APP_STORAGE=mysql ayarlayın
   2. Uygulamayı yeniden başlatın
   3. MariaDB'den verileri kontrol edin
```

🎉 Tebrikler! SQLite verileriniz MariaDB'ye taşındı.
