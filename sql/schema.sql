CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  kullanici VARCHAR(50) NOT NULL UNIQUE,
  sifre_hash VARCHAR(255) NOT NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS books (
  id INT AUTO_INCREMENT PRIMARY KEY,
  kod VARCHAR(40) NOT NULL UNIQUE,
  ad VARCHAR(200) NOT NULL,
  alis_fiyati DECIMAL(12,2) NOT NULL DEFAULT 0,
  satis_fiyati DECIMAL(12,2) NOT NULL DEFAULT 0,
  kritik_stok INT NOT NULL DEFAULT 0,
  KEY (ad)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS people (
  id INT AUTO_INCREMENT PRIMARY KEY,
  ad VARCHAR(120) NOT NULL UNIQUE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS stock_in (
  id INT AUTO_INCREMENT PRIMARY KEY,
  book_id INT NOT NULL,
  tarih DATE NOT NULL,
  adet INT NOT NULL,
  teslim_alan VARCHAR(120) NOT NULL,
  aciklama VARCHAR(255) NOT NULL DEFAULT '',
  FOREIGN KEY (book_id) REFERENCES books(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS customers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  tip ENUM('Okul','Kırtasiye') NOT NULL DEFAULT 'Okul',
  ad VARCHAR(200) NOT NULL,
  il VARCHAR(60) NOT NULL DEFAULT '',
  ilce VARCHAR(60) NOT NULL DEFAULT '',
  yetkili VARCHAR(120) NOT NULL DEFAULT '',
  telefon VARCHAR(40) NOT NULL DEFAULT '',
  eposta VARCHAR(120) NOT NULL DEFAULT '',
  adres VARCHAR(255) NOT NULL DEFAULT '',
  KEY (ad), KEY (il, ilce)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS stock_out (
  id INT AUTO_INCREMENT PRIMARY KEY,
  book_id INT NOT NULL,
  customer_id INT NOT NULL,
  tarih DATE NOT NULL,
  adet INT NOT NULL,
  goturen_kisi VARCHAR(120) NOT NULL,
  aciklama VARCHAR(255) NOT NULL DEFAULT '',
  KEY (tarih),
  FOREIGN KEY (book_id) REFERENCES books(id),
  FOREIGN KEY (customer_id) REFERENCES customers(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS stock_out_returns (
  id INT AUTO_INCREMENT PRIMARY KEY,
  stock_out_id INT NOT NULL,
  tarih DATE NOT NULL,
  adet INT NOT NULL,
  aciklama VARCHAR(255) NOT NULL DEFAULT '',
  KEY (stock_out_id),
  FOREIGN KEY (stock_out_id) REFERENCES stock_out(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS sales (
  id INT AUTO_INCREMENT PRIMARY KEY,
  customer_id INT NOT NULL,
  tarih DATE NOT NULL,
  vade_tarihi DATE NOT NULL,
  aciklama VARCHAR(255) NOT NULL DEFAULT '',
  toplam DECIMAL(14,2) NOT NULL DEFAULT 0,
  KEY (tarih), KEY (vade_tarihi),
  FOREIGN KEY (customer_id) REFERENCES customers(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS sale_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  sale_id INT NOT NULL,
  book_id INT NOT NULL,
  adet INT NOT NULL,
  birim_fiyat DECIMAL(12,2) NOT NULL,
  maliyet_birim DECIMAL(12,2) NOT NULL DEFAULT 0,
  toplam DECIMAL(14,2) NOT NULL,
  FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE CASCADE,
  FOREIGN KEY (book_id) REFERENCES books(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS customer_returns (
  id INT AUTO_INCREMENT PRIMARY KEY,
  sale_item_id INT NOT NULL,
  tarih DATE NOT NULL,
  adet INT NOT NULL,
  birim_fiyat DECIMAL(12,2) NOT NULL,
  maliyet_birim DECIMAL(12,2) NOT NULL DEFAULT 0,
  toplam DECIMAL(14,2) NOT NULL,
  aciklama VARCHAR(255) NOT NULL DEFAULT '',
  KEY (tarih),
  FOREIGN KEY (sale_item_id) REFERENCES sale_items(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS supplier_returns (
  id INT AUTO_INCREMENT PRIMARY KEY,
  book_id INT NOT NULL,
  tarih DATE NOT NULL,
  tedarikci VARCHAR(200) NOT NULL,
  adet INT NOT NULL,
  birim_fiyat DECIMAL(12,2) NOT NULL,
  toplam DECIMAL(14,2) NOT NULL,
  aciklama VARCHAR(255) NOT NULL DEFAULT '',
  KEY (tarih),
  FOREIGN KEY (book_id) REFERENCES books(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS payments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  customer_id INT NOT NULL,
  tarih DATE NOT NULL,
  tutar DECIMAL(14,2) NOT NULL,
  yontem VARCHAR(40) NOT NULL DEFAULT 'Nakit',
  aciklama VARCHAR(255) NOT NULL DEFAULT '',
  KEY (tarih),
  FOREIGN KEY (customer_id) REFERENCES customers(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS expenses (
  id INT AUTO_INCREMENT PRIMARY KEY,
  tarih DATE NOT NULL,
  kategori VARCHAR(100) NOT NULL,
  tutar DECIMAL(14,2) NOT NULL,
  yontem VARCHAR(40) NOT NULL DEFAULT 'Nakit',
  aciklama VARCHAR(255) NOT NULL DEFAULT '',
  KEY (tarih)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS donations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  tarih DATE NOT NULL,
  bagisci VARCHAR(200) NOT NULL DEFAULT '',
  tutar DECIMAL(14,2) NOT NULL,
  yontem VARCHAR(40) NOT NULL DEFAULT 'Nakit',
  aciklama VARCHAR(255) NOT NULL DEFAULT '',
  KEY (tarih)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS loans (
  id INT AUTO_INCREMENT PRIMARY KEY,
  tarih DATE NOT NULL,
  alacakli VARCHAR(200) NOT NULL,
  tutar DECIMAL(14,2) NOT NULL,
  aciklama VARCHAR(255) NOT NULL DEFAULT '',
  KEY (tarih)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS loan_payments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  loan_id INT NOT NULL,
  tarih DATE NOT NULL,
  tutar DECIMAL(14,2) NOT NULL,
  yontem VARCHAR(40) NOT NULL DEFAULT 'Nakit',
  aciklama VARCHAR(255) NOT NULL DEFAULT '',
  KEY (tarih),
  FOREIGN KEY (loan_id) REFERENCES loans(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS notification_log (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  customer_id INT NOT NULL,
  hafta_baslangici DATE NOT NULL,
  kanal ENUM('E-posta','SMS') NOT NULL,
  durum ENUM('gönderildi','başarısız','atlandı') NOT NULL,
  hedef VARCHAR(255) NOT NULL DEFAULT '',
  hata TEXT NULL,
  guncellendi TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY haftalik_bildirim (customer_id, hafta_baslangici, kanal),
  KEY bildirim_tarih (hafta_baslangici),
  FOREIGN KEY (customer_id) REFERENCES customers(id)
) ENGINE=InnoDB;

-- Existing installations may have the old stock and book columns.
SET @add_stock_receiver = (SELECT COUNT(*) = 0 FROM information_schema.columns
  WHERE table_schema = DATABASE() AND table_name = 'stock_in' AND column_name = 'teslim_alan');
SET @migration_sql = IF(@add_stock_receiver,
  'ALTER TABLE stock_in ADD COLUMN teslim_alan VARCHAR(120) NOT NULL DEFAULT ''''', 'SET @migration_noop = 1');
PREPARE migration_stmt FROM @migration_sql;
EXECUTE migration_stmt;
DEALLOCATE PREPARE migration_stmt;

SET @add_stock_carrier = (SELECT COUNT(*) = 0 FROM information_schema.columns
  WHERE table_schema = DATABASE() AND table_name = 'stock_out' AND column_name = 'goturen_kisi');
SET @migration_sql = IF(@add_stock_carrier,
  'ALTER TABLE stock_out ADD COLUMN goturen_kisi VARCHAR(120) NOT NULL DEFAULT ''''', 'SET @migration_noop = 1');
PREPARE migration_stmt FROM @migration_sql;
EXECUTE migration_stmt;
DEALLOCATE PREPARE migration_stmt;

CREATE TABLE IF NOT EXISTS schema_migrations (
  name VARCHAR(100) PRIMARY KEY
) ENGINE=InnoDB;

INSERT IGNORE INTO people (ad)
  SELECT TRIM(teslim_alan) FROM stock_in
  WHERE TRIM(teslim_alan) <> '' AND NOT EXISTS (SELECT 1 FROM schema_migrations WHERE name = 'people_from_stock');
INSERT IGNORE INTO people (ad)
  SELECT TRIM(goturen_kisi) FROM stock_out
  WHERE TRIM(goturen_kisi) <> '' AND NOT EXISTS (SELECT 1 FROM schema_migrations WHERE name = 'people_from_stock');
INSERT IGNORE INTO schema_migrations (name) VALUES ('people_from_stock');

SET @migration_sql = IF((SELECT COUNT(*) FROM information_schema.columns
  WHERE table_schema = DATABASE() AND table_name = 'books' AND column_name = 'koli_adedi') > 0,
  'ALTER TABLE books DROP COLUMN koli_adedi', 'SET @migration_noop = 1');
PREPARE migration_stmt FROM @migration_sql;
EXECUTE migration_stmt;
DEALLOCATE PREPARE migration_stmt;

SET @migration_sql = IF((SELECT COUNT(*) FROM information_schema.columns
  WHERE table_schema = DATABASE() AND table_name = 'stock_out' AND column_name = 'koli') > 0,
  'ALTER TABLE stock_out DROP COLUMN koli', 'SET @migration_noop = 1');
PREPARE migration_stmt FROM @migration_sql;
EXECUTE migration_stmt;
DEALLOCATE PREPARE migration_stmt;

SET @migration_sql = IF((SELECT COUNT(*) FROM information_schema.columns
  WHERE table_schema = DATABASE() AND table_name = 'sale_items' AND column_name = 'koli') > 0,
  'ALTER TABLE sale_items DROP COLUMN koli', 'SET @migration_noop = 1');
PREPARE migration_stmt FROM @migration_sql;
EXECUTE migration_stmt;
DEALLOCATE PREPARE migration_stmt;

SET @migration_sql = IF((SELECT COUNT(*) FROM information_schema.columns
  WHERE table_schema = DATABASE() AND table_name = 'stock_out' AND column_name = 'alan_kisi') > 0,
  'ALTER TABLE stock_out DROP COLUMN alan_kisi', 'SET @migration_noop = 1');
PREPARE migration_stmt FROM @migration_sql;
EXECUTE migration_stmt;
DEALLOCATE PREPARE migration_stmt;

SET @migration_sql = IF((SELECT COUNT(*) FROM information_schema.columns
  WHERE table_schema = DATABASE() AND table_name = 'stock_out' AND column_name = 'teslim_yeri') > 0,
  'ALTER TABLE stock_out DROP COLUMN teslim_yeri', 'SET @migration_noop = 1');
PREPARE migration_stmt FROM @migration_sql;
EXECUTE migration_stmt;
DEALLOCATE PREPARE migration_stmt;

SET @migration_sql = IF((SELECT COUNT(*) FROM information_schema.columns
  WHERE table_schema = DATABASE() AND table_name = 'books' AND column_name = 'yayinevi') > 0,
  'ALTER TABLE books DROP COLUMN yayinevi', 'SET @migration_noop = 1');
PREPARE migration_stmt FROM @migration_sql;
EXECUTE migration_stmt;
DEALLOCATE PREPARE migration_stmt;

SET @migration_sql = IF((SELECT COUNT(*) FROM information_schema.columns
  WHERE table_schema = DATABASE() AND table_name = 'books' AND column_name = 'sinif') > 0,
  'ALTER TABLE books DROP COLUMN sinif', 'SET @migration_noop = 1');
PREPARE migration_stmt FROM @migration_sql;
EXECUTE migration_stmt;
DEALLOCATE PREPARE migration_stmt;

SET @add_sale_cost = (SELECT COUNT(*) = 0 FROM information_schema.columns
  WHERE table_schema = DATABASE() AND table_name = 'sale_items' AND column_name = 'maliyet_birim');
SET @migration_sql = IF(@add_sale_cost,
  'ALTER TABLE sale_items ADD COLUMN maliyet_birim DECIMAL(12,2) NOT NULL DEFAULT 0', 'SET @migration_noop = 1');
PREPARE migration_stmt FROM @migration_sql;
EXECUTE migration_stmt;
DEALLOCATE PREPARE migration_stmt;

SET @migration_sql = IF(@add_sale_cost,
  'UPDATE sale_items SET maliyet_birim = COALESCE((SELECT alis_fiyati FROM books WHERE books.id = sale_items.book_id), 0)',
  'SET @migration_noop = 1');
PREPARE migration_stmt FROM @migration_sql;
EXECUTE migration_stmt;
DEALLOCATE PREPARE migration_stmt;
