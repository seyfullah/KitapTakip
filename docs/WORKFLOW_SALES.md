# Satış Akış Diyagramı

Bu diyagram satış kayıt, teslimat ve tamamlanma sürecini göstermektedir.

```mermaid
stateDiagram-v2
    [*] --> Yeni: Satış Kayıt
    
    Yeni --> TeslimBekliyor: Teslimat Hazırlığı
    TeslimBekliyor --> TeslimEdildi: Tesimat Kaydı<br/>(Tüm Kalemleri Teslim)
    
    TeslimBekliyor --> IadeEdildi: İade İşlemi
    TeslimEdildi --> IadeEdildi: Satış İadesi
    
    Yeni --> IptalEdildi: İptal
    TeslimBekliyor --> IptalEdildi: İptal
    
    TeslimEdildi --> [*]: Kapalı
    IadeEdildi --> [*]: Kapalı
    IptalEdildi --> [*]: Kapalı
    
    note right of Yeni
        Satış kaydı oluşturulur
        Stok ayırılır
        Durum: "Yeni"
    end note
    
    note right of TeslimBekliyor
        Teslimat beklemede
        Kalemlerin teslim
        tarihi kaydedilir
    end note
    
    note right of TeslimEdildi
        Tüm kalemleri teslim
        Stok ikinci kez çıkarılmaz
        Satış iadeleri mümkün
    end note
    
    note right of IadeEdildi
        Satış kısmi/tam iade
        Stok geri eklenir
        Müşteri bakiyesi güncellenir
    end note
    
    note right of IptalEdildi
        Satış iptal edildi
        Ayırılan stok serbest
        Müşteri bakiyesi sıfırlanır
    end note
```

## Durum Açıklamaları

| Durum | Açıklama | İşlem |
|-------|----------|-------|
| **Yeni** | Yeni satış kaydı oluşturuldu | Satış Kayıt formundan giriş |
| **Teslimat Bekliyor** | Teslimatın hazırlanması beklenmekte | Satış İncele → Teslimat Hazırla |
| **Teslim Edildi** | Tüm kalemleri teslim edildi | Satış İncele → Teslimatı Kaydet |
| **İade Edildi** | Satış kısmi veya tamamen iade edildi | Satış İade işlemi |
| **İptal Edildi** | Satış iptal edildi | Satış İptal işlemi |

## Önemli Notlar

- **Otomatik Geçiş**: Teslimat sırasında tüm kalemleri teslim ederseniz durum otomatik olarak "Teslim Edildi"ye değişir
- **Kısmi Teslimat**: Teslimat formunda kitap ve adet seçimi olmaz; tüm kalemlerin teslim tarihi kaydedilir
- **Stok Yönetimi**: 
  - Satış kaydı oluşturulduğunda → Stok ilk kez düşer
  - Teslimat kaydı oluşturulduğunda → Stok ikinci kez düşülmez
  - İade işleminde → Stok geri eklenir
- **Müşteri Bakiyesi**: Satış oluşturulduğunda müşteriye borç eklenir, iade/tahsilat sonrası güncellenir
