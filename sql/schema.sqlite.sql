PRAGMA foreign_keys = ON;

CREATE TABLE IF NOT EXISTS uy_users (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  kullanici TEXT NOT NULL UNIQUE,
  sifre_hash TEXT NOT NULL,
  person_id INTEGER REFERENCES uy_people(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS uy_books (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  kod TEXT NOT NULL UNIQUE,
  ad TEXT NOT NULL,
  alis_fiyati NUMERIC NOT NULL DEFAULT 0,
  satis_fiyati NUMERIC NOT NULL DEFAULT 0,
  kritik_stok INTEGER NOT NULL DEFAULT 0
);
CREATE INDEX IF NOT EXISTS books_ad ON uy_books(ad);

CREATE TABLE IF NOT EXISTS uy_people (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  ad TEXT NOT NULL UNIQUE
);


CREATE TABLE IF NOT EXISTS uy_stock_in (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  book_id INTEGER NOT NULL REFERENCES uy_books(id),
  tarih TEXT NOT NULL,
  adet INTEGER NOT NULL,
  teslim_alan TEXT NOT NULL,
  aciklama TEXT NOT NULL DEFAULT ''
);

CREATE TABLE IF NOT EXISTS uy_supplier_purchases (
  stock_in_id INTEGER PRIMARY KEY REFERENCES uy_stock_in(id),
  total_cents INTEGER NOT NULL,
  due_date TEXT NOT NULL
);
CREATE TABLE IF NOT EXISTS uy_supplier_payments (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  stock_in_id INTEGER NOT NULL REFERENCES uy_supplier_purchases(stock_in_id),
  tarih TEXT NOT NULL,
  amount_cents INTEGER NOT NULL,
  yontem TEXT NOT NULL,
  aciklama TEXT NOT NULL DEFAULT ''
);
CREATE INDEX IF NOT EXISTS supplier_payments_in ON uy_supplier_payments(stock_in_id);

CREATE TABLE IF NOT EXISTS uy_customers (
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
CREATE INDEX IF NOT EXISTS customers_ad ON uy_customers(ad);
CREATE INDEX IF NOT EXISTS customers_il_ilce ON uy_customers(il, ilce);

CREATE TABLE IF NOT EXISTS uy_stock_out (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  book_id INTEGER NOT NULL REFERENCES uy_books(id),
  customer_id INTEGER NOT NULL REFERENCES uy_customers(id),
  tarih TEXT NOT NULL,
  adet INTEGER NOT NULL,
  goturen_kisi TEXT NOT NULL,
  aciklama TEXT NOT NULL DEFAULT ''
);
CREATE INDEX IF NOT EXISTS stock_out_tarih ON uy_stock_out(tarih);

CREATE TABLE IF NOT EXISTS uy_stock_out_returns (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  stock_out_id INTEGER NOT NULL REFERENCES uy_stock_out(id),
  tarih TEXT NOT NULL,
  adet INTEGER NOT NULL,
  aciklama TEXT NOT NULL DEFAULT ''
);
CREATE INDEX IF NOT EXISTS stock_out_returns_out ON uy_stock_out_returns(stock_out_id);

CREATE TABLE IF NOT EXISTS uy_sales (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  customer_id INTEGER NOT NULL REFERENCES uy_customers(id),
  tarih TEXT NOT NULL,
  vade_tarihi TEXT NOT NULL,
  aciklama TEXT NOT NULL DEFAULT '',
  toplam NUMERIC NOT NULL DEFAULT 0
);
CREATE INDEX IF NOT EXISTS sales_tarih ON uy_sales(tarih);
CREATE INDEX IF NOT EXISTS sales_vade_tarihi ON uy_sales(vade_tarihi);

CREATE TABLE IF NOT EXISTS uy_sale_items (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  sale_id INTEGER NOT NULL REFERENCES uy_sales(id) ON DELETE CASCADE,
  book_id INTEGER NOT NULL REFERENCES uy_books(id),
  adet INTEGER NOT NULL,
  birim_fiyat NUMERIC NOT NULL,
  maliyet_birim NUMERIC NOT NULL DEFAULT 0,
  toplam NUMERIC NOT NULL
);

CREATE TABLE IF NOT EXISTS uy_sale_deliveries (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  sale_item_id INTEGER NOT NULL REFERENCES uy_sale_items(id),
  tarih TEXT NOT NULL,
  adet INTEGER NOT NULL,
  goturen_kisi TEXT NOT NULL,
  teslim_edilen TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS sale_deliveries_item ON uy_sale_deliveries(sale_item_id);
CREATE TABLE IF NOT EXISTS uy_legacy_sale_returns (
  sale_item_id INTEGER PRIMARY KEY REFERENCES uy_sale_items(id),
  adet INTEGER NOT NULL
);

CREATE TABLE IF NOT EXISTS uy_customer_returns (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  sale_item_id INTEGER NOT NULL REFERENCES uy_sale_items(id),
  tarih TEXT NOT NULL,
  adet INTEGER NOT NULL,
  birim_fiyat NUMERIC NOT NULL,
  maliyet_birim NUMERIC NOT NULL DEFAULT 0,
  toplam NUMERIC NOT NULL,
  aciklama TEXT NOT NULL DEFAULT ''
);
CREATE INDEX IF NOT EXISTS customer_returns_tarih ON uy_customer_returns(tarih);

CREATE TABLE IF NOT EXISTS uy_supplier_returns (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  book_id INTEGER NOT NULL REFERENCES uy_books(id),
  tarih TEXT NOT NULL,
  tedarikci TEXT NOT NULL,
  adet INTEGER NOT NULL,
  birim_fiyat NUMERIC NOT NULL,
  toplam NUMERIC NOT NULL,
  aciklama TEXT NOT NULL DEFAULT ''
);
CREATE INDEX IF NOT EXISTS supplier_returns_tarih ON uy_supplier_returns(tarih);

CREATE TABLE IF NOT EXISTS uy_supplier_return_links (
  return_id INTEGER PRIMARY KEY REFERENCES uy_supplier_returns(id),
  stock_in_id INTEGER NOT NULL REFERENCES uy_stock_in(id)
);
CREATE INDEX IF NOT EXISTS supplier_return_links_in ON uy_supplier_return_links(stock_in_id);

CREATE TABLE IF NOT EXISTS uy_payments (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  customer_id INTEGER NOT NULL REFERENCES uy_customers(id),
  tarih TEXT NOT NULL,
  tutar NUMERIC NOT NULL,
  yontem TEXT NOT NULL DEFAULT 'Nakit',
  aciklama TEXT NOT NULL DEFAULT ''
);
CREATE INDEX IF NOT EXISTS payments_tarih ON uy_payments(tarih);

CREATE TABLE IF NOT EXISTS uy_expenses (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  tarih TEXT NOT NULL,
  kategori TEXT NOT NULL,
  tutar NUMERIC NOT NULL,
  yontem TEXT NOT NULL DEFAULT 'Nakit',
  aciklama TEXT NOT NULL DEFAULT ''
);
CREATE INDEX IF NOT EXISTS expenses_tarih ON uy_expenses(tarih);

CREATE TABLE IF NOT EXISTS uy_donations (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  tarih TEXT NOT NULL,
  bagisci TEXT NOT NULL DEFAULT '',
  tutar NUMERIC NOT NULL,
  yontem TEXT NOT NULL DEFAULT 'Nakit',
  aciklama TEXT NOT NULL DEFAULT ''
);
CREATE INDEX IF NOT EXISTS donations_tarih ON uy_donations(tarih);

CREATE TABLE IF NOT EXISTS uy_loans (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  tarih TEXT NOT NULL,
  alacakli TEXT NOT NULL,
  tutar NUMERIC NOT NULL,
  aciklama TEXT NOT NULL DEFAULT ''
);
CREATE INDEX IF NOT EXISTS loans_tarih ON uy_loans(tarih);

CREATE TABLE IF NOT EXISTS uy_loan_payments (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  loan_id INTEGER NOT NULL REFERENCES uy_loans(id) ON DELETE CASCADE,
  tarih TEXT NOT NULL,
  tutar NUMERIC NOT NULL,
  yontem TEXT NOT NULL DEFAULT 'Nakit',
  aciklama TEXT NOT NULL DEFAULT ''
);
CREATE INDEX IF NOT EXISTS loan_payments_tarih ON uy_loan_payments(tarih);

CREATE TABLE IF NOT EXISTS uy_notification_log (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  customer_id INTEGER NOT NULL REFERENCES uy_customers(id),
  hafta_baslangici TEXT NOT NULL,
  kanal TEXT NOT NULL CHECK(kanal IN ('E-posta','SMS')),
  durum TEXT NOT NULL CHECK(durum IN ('gönderildi','başarısız','atlandı')),
  hedef TEXT NOT NULL DEFAULT '',
  hata TEXT,
  guncellendi TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE(customer_id, hafta_baslangici, kanal)
);
CREATE INDEX IF NOT EXISTS notification_log_week ON uy_notification_log(hafta_baslangici);
