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
  alis_fiyati NUMERIC NOT NULL DEFAULT 0,
  satis_fiyati NUMERIC NOT NULL DEFAULT 0,
  kritik_stok INTEGER NOT NULL DEFAULT 0
);
CREATE INDEX IF NOT EXISTS books_ad ON books(ad);

CREATE TABLE IF NOT EXISTS people (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  ad TEXT NOT NULL UNIQUE
);

CREATE TABLE IF NOT EXISTS stock_in (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  book_id INTEGER NOT NULL REFERENCES books(id),
  tarih TEXT NOT NULL,
  adet INTEGER NOT NULL,
  teslim_alan TEXT NOT NULL,
  aciklama TEXT NOT NULL DEFAULT ''
);

CREATE TABLE IF NOT EXISTS supplier_purchases (
  stock_in_id INTEGER PRIMARY KEY REFERENCES stock_in(id),
  total_cents INTEGER NOT NULL,
  due_date TEXT NOT NULL
);
CREATE TABLE IF NOT EXISTS supplier_payments (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  stock_in_id INTEGER NOT NULL REFERENCES supplier_purchases(stock_in_id),
  tarih TEXT NOT NULL,
  amount_cents INTEGER NOT NULL,
  yontem TEXT NOT NULL,
  aciklama TEXT NOT NULL DEFAULT ''
);
CREATE INDEX IF NOT EXISTS supplier_payments_in ON supplier_payments(stock_in_id);

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
  goturen_kisi TEXT NOT NULL,
  aciklama TEXT NOT NULL DEFAULT ''
);
CREATE INDEX IF NOT EXISTS stock_out_tarih ON stock_out(tarih);

CREATE TABLE IF NOT EXISTS stock_out_returns (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  stock_out_id INTEGER NOT NULL REFERENCES stock_out(id),
  tarih TEXT NOT NULL,
  adet INTEGER NOT NULL,
  aciklama TEXT NOT NULL DEFAULT ''
);
CREATE INDEX IF NOT EXISTS stock_out_returns_out ON stock_out_returns(stock_out_id);

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
  birim_fiyat NUMERIC NOT NULL,
  maliyet_birim NUMERIC NOT NULL DEFAULT 0,
  toplam NUMERIC NOT NULL
);

CREATE TABLE IF NOT EXISTS sale_deliveries (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  sale_item_id INTEGER NOT NULL REFERENCES sale_items(id),
  tarih TEXT NOT NULL,
  adet INTEGER NOT NULL,
  goturen_kisi TEXT NOT NULL,
  teslim_edilen TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS sale_deliveries_item ON sale_deliveries(sale_item_id);
CREATE TABLE IF NOT EXISTS legacy_sale_returns (
  sale_item_id INTEGER PRIMARY KEY REFERENCES sale_items(id),
  adet INTEGER NOT NULL
);

CREATE TABLE IF NOT EXISTS customer_returns (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  sale_item_id INTEGER NOT NULL REFERENCES sale_items(id),
  tarih TEXT NOT NULL,
  adet INTEGER NOT NULL,
  birim_fiyat NUMERIC NOT NULL,
  maliyet_birim NUMERIC NOT NULL DEFAULT 0,
  toplam NUMERIC NOT NULL,
  aciklama TEXT NOT NULL DEFAULT ''
);
CREATE INDEX IF NOT EXISTS customer_returns_tarih ON customer_returns(tarih);

CREATE TABLE IF NOT EXISTS supplier_returns (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  book_id INTEGER NOT NULL REFERENCES books(id),
  tarih TEXT NOT NULL,
  tedarikci TEXT NOT NULL,
  adet INTEGER NOT NULL,
  birim_fiyat NUMERIC NOT NULL,
  toplam NUMERIC NOT NULL,
  aciklama TEXT NOT NULL DEFAULT ''
);
CREATE INDEX IF NOT EXISTS supplier_returns_tarih ON supplier_returns(tarih);

CREATE TABLE IF NOT EXISTS supplier_return_links (
  return_id INTEGER PRIMARY KEY REFERENCES supplier_returns(id),
  stock_in_id INTEGER NOT NULL REFERENCES stock_in(id)
);
CREATE INDEX IF NOT EXISTS supplier_return_links_in ON supplier_return_links(stock_in_id);

CREATE TABLE IF NOT EXISTS payments (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  customer_id INTEGER NOT NULL REFERENCES customers(id),
  tarih TEXT NOT NULL,
  tutar NUMERIC NOT NULL,
  yontem TEXT NOT NULL DEFAULT 'Nakit',
  aciklama TEXT NOT NULL DEFAULT ''
);
CREATE INDEX IF NOT EXISTS payments_tarih ON payments(tarih);

CREATE TABLE IF NOT EXISTS expenses (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  tarih TEXT NOT NULL,
  kategori TEXT NOT NULL,
  tutar NUMERIC NOT NULL,
  yontem TEXT NOT NULL DEFAULT 'Nakit',
  aciklama TEXT NOT NULL DEFAULT ''
);
CREATE INDEX IF NOT EXISTS expenses_tarih ON expenses(tarih);

CREATE TABLE IF NOT EXISTS donations (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  tarih TEXT NOT NULL,
  bagisci TEXT NOT NULL DEFAULT '',
  tutar NUMERIC NOT NULL,
  yontem TEXT NOT NULL DEFAULT 'Nakit',
  aciklama TEXT NOT NULL DEFAULT ''
);
CREATE INDEX IF NOT EXISTS donations_tarih ON donations(tarih);

CREATE TABLE IF NOT EXISTS loans (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  tarih TEXT NOT NULL,
  alacakli TEXT NOT NULL,
  tutar NUMERIC NOT NULL,
  aciklama TEXT NOT NULL DEFAULT ''
);
CREATE INDEX IF NOT EXISTS loans_tarih ON loans(tarih);

CREATE TABLE IF NOT EXISTS loan_payments (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  loan_id INTEGER NOT NULL REFERENCES loans(id) ON DELETE CASCADE,
  tarih TEXT NOT NULL,
  tutar NUMERIC NOT NULL,
  yontem TEXT NOT NULL DEFAULT 'Nakit',
  aciklama TEXT NOT NULL DEFAULT ''
);
CREATE INDEX IF NOT EXISTS loan_payments_tarih ON loan_payments(tarih);

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
