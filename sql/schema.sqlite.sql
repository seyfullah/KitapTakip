PRAGMA foreign_keys = ON;

CREATE TABLE IF NOT EXISTS users (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  kullanici TEXT NOT NULL UNIQUE,
  sifre_hash TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS books (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  kod TEXT NOT NULL UNIQUE,
  ad TEXT NOT NULL,
  yayinevi TEXT NOT NULL DEFAULT '',
  sinif TEXT NOT NULL DEFAULT '',
  alis_fiyati NUMERIC NOT NULL DEFAULT 0,
  satis_fiyati NUMERIC NOT NULL DEFAULT 0,
  koli_adedi INTEGER NOT NULL DEFAULT 1,
  kritik_stok INTEGER NOT NULL DEFAULT 0
);
CREATE INDEX IF NOT EXISTS books_ad ON books(ad);
CREATE INDEX IF NOT EXISTS books_yayinevi ON books(yayinevi);

CREATE TABLE IF NOT EXISTS stock_in (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  book_id INTEGER NOT NULL REFERENCES books(id),
  tarih TEXT NOT NULL,
  adet INTEGER NOT NULL,
  aciklama TEXT NOT NULL DEFAULT ''
);

CREATE TABLE IF NOT EXISTS customers (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  tip TEXT NOT NULL DEFAULT 'Okul' CHECK(tip IN ('Okul','Kırtasiye')),
  ad TEXT NOT NULL,
  il TEXT NOT NULL DEFAULT '',
  ilce TEXT NOT NULL DEFAULT '',
  yetkili TEXT NOT NULL DEFAULT '',
  telefon TEXT NOT NULL DEFAULT '',
  eposta TEXT NOT NULL DEFAULT '',
  adres TEXT NOT NULL DEFAULT ''
);
CREATE INDEX IF NOT EXISTS customers_ad ON customers(ad);
CREATE INDEX IF NOT EXISTS customers_il_ilce ON customers(il, ilce);

CREATE TABLE IF NOT EXISTS stock_out (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  book_id INTEGER NOT NULL REFERENCES books(id),
  customer_id INTEGER NOT NULL REFERENCES customers(id),
  tarih TEXT NOT NULL,
  adet INTEGER NOT NULL,
  koli NUMERIC NOT NULL DEFAULT 0,
  alan_kisi TEXT NOT NULL,
  teslim_yeri TEXT NOT NULL,
  aciklama TEXT NOT NULL DEFAULT ''
);
CREATE INDEX IF NOT EXISTS stock_out_tarih ON stock_out(tarih);

CREATE TABLE IF NOT EXISTS sales (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  customer_id INTEGER NOT NULL REFERENCES customers(id),
  tarih TEXT NOT NULL,
  vade_tarihi TEXT NOT NULL,
  aciklama TEXT NOT NULL DEFAULT '',
  toplam NUMERIC NOT NULL DEFAULT 0
);
CREATE INDEX IF NOT EXISTS sales_tarih ON sales(tarih);
CREATE INDEX IF NOT EXISTS sales_vade_tarihi ON sales(vade_tarihi);

CREATE TABLE IF NOT EXISTS sale_items (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  sale_id INTEGER NOT NULL REFERENCES sales(id) ON DELETE CASCADE,
  book_id INTEGER NOT NULL REFERENCES books(id),
  adet INTEGER NOT NULL,
  koli NUMERIC NOT NULL DEFAULT 0,
  birim_fiyat NUMERIC NOT NULL,
  toplam NUMERIC NOT NULL
);

CREATE TABLE IF NOT EXISTS payments (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  customer_id INTEGER NOT NULL REFERENCES customers(id),
  tarih TEXT NOT NULL,
  tutar NUMERIC NOT NULL,
  yontem TEXT NOT NULL DEFAULT 'Nakit',
  aciklama TEXT NOT NULL DEFAULT ''
);
CREATE INDEX IF NOT EXISTS payments_tarih ON payments(tarih);

CREATE TABLE IF NOT EXISTS notification_log (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  customer_id INTEGER NOT NULL REFERENCES customers(id),
  hafta_baslangici TEXT NOT NULL,
  kanal TEXT NOT NULL CHECK(kanal IN ('E-posta','SMS')),
  durum TEXT NOT NULL CHECK(durum IN ('gönderildi','başarısız','atlandı')),
  hedef TEXT NOT NULL DEFAULT '',
  hata TEXT,
  guncellendi TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE(customer_id, hafta_baslangici, kanal)
);
CREATE INDEX IF NOT EXISTS notification_log_week ON notification_log(hafta_baslangici);
