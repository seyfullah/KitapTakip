# Finansal Akış Diyagramı

Bu diyagram müşteri alacağı, tahsilat ve finansal raporlama sürecini göstermektedir.

```mermaid
graph LR
    A["Satış Kaydı"] -->|Müşteri Borç Ekle| B["Müşteri Bakiyesi"]
    
    C["Tahsilat"] -->|Bakiye Azalt| B
    D["Satış İadesi"] -->|Bakiye Azalt| B
    E["Stok Çıkış İadesi"] -->|Etkilemez| B
    
    B --> F{"Bakiye Analizi"}
    
    F -->|Pozitif| G["Alacak Kaydı"]
    F -->|Sıfır| H["Borç Yok"]
    F -->|Negatif| I["Müşteriye Borç"]
    
    G --> J["Pazartesi 08.00'de<br/>E-posta & SMS Bildirim"]
    
    K["Stok Girişi<br/>+ Alış Tutarı"] --> L["Tedarikçi Borcu"]
    M["Tedarikçi Ödeme"] -->|Borcu Azalt| L
    N["Tedarikçi İadesi<br/>Orantılı Hesap"] -->|Borcu Azalt| L
    
    O["Gider/Bağış Kaydı"] --> P["Kâr/Zarar Özeti"]
    Q["Satış Gelirleri"] --> P
    R["Satış Maliyeti<br/>Anındaki Kitap Fiyatı"] --> P
    L -.->|Etkilemez| P
    C -.->|Etkilemez| P
    
    J --> S["Bildirim Sayfası<br/>Gönderme Sonuçları"]
    
    style B fill:#fff3e0
    style G fill:#c8e6c9
    style H fill:#f5f5f5
    style I fill:#ffcdd2
    style P fill:#e1f5ff
    
    subgraph Müşteri["👥 MÜŞTERİ CARİ YÖNETİMİ"]
        A
        C
        D
        B
        F
        G
        H
        I
        J
        S
    end
    
    subgraph Tedarikçi["📦 TEDARİKÇİ BÖRCü YÖNETİMİ"]
        K
        M
        N
        L
    end
    
    subgraph Finansal["📊 TAŞLAR"]
        O
        Q
        R
        P
    end
```

## Müşteri Cari Yönetimi

### Bakiye Değişimleri

| İşlem | Etki | Açıklama |
|-------|------|---------|
| **Satış** | ↑ Artış | Müşteri borç ekle |
| **Tahsilat** | ↓ Azalış | Nakit/çek tahsilat |
| **Satış İadesi** | ↓ Azalış | Ürün iade, borç azal |
| **Stok Çıkış İadesi** | — | Müşteri hisselendirmeyen işlem |

### Alacak Takibi

- **Haftalık Bildirim**: Pazartesi 08:00'de bakiyesi pozitif olan müşterilere
- **İletişim Kanalları**: E-posta (PHP mail) + SMS (Twilio)
- **Gönderim Durumu**: Bildirimler sayfasında listelenir
- **Yeniden Deneme**: Başarısız gönderimler sonraki çalıştırmada tekrar denenir

## Tedarikçi Borcu Yönetimi

### Ödeme Takibi

1. **Stok Girişi**: Alış tutarı ve vade tarihi kaydedilir
2. **Tedarikçi Ödemesi**: Ödenmiş tutar kaydedilir
3. **Tedarikçi İadesi**: Orantılı hesap; formül = İade Tutarı × (İade Miktarı / Toplam Miktarı)
4. **Alacak Durumu**: Ödenmiş tutarı aşan iade, tedarikçiden alacak olarak gösterilir

## Kâr/Zarar Hesabı

```
Kâr/Zarar = Net Satış - Satış Maliyeti - Gider - Bağış

Burada:
  - Net Satış: Tüm satış tutarları toplamı
  - Satış Maliyeti: Satış yapılan anındaki kitap birim fiyatı × Satılan Adet
  - Gider: Kaydedilen tüm giderler
  - Bağış: Kaydedilen tüm bağışlar

ETKİSİZ İŞLEMLER:
  - Tahsilat: Bakiyeyi değiştirir, kâr/zararı etkilemez
  - Tedarikçi Borcu: Ödeme zamanı değiştirmez
  - Alınan Borç: Anapara ödemesi kâr/zararı etkilemez
```

## Raporlama

### Müşteri Ekstresi
- Tarih aralığına göre satış, tahsilat ve iade işlemlerini gösterir
- İşlem detayı, tarih, tutar bilgilerini içerir

### Vade Raporu
- Vadesi geçmiş satışlar listelenmiş
- Müşteri bakiyesi ile eşleşmeyebilir (tahsilat FIFO yöntemiyle yapılır)

### Stok Raporu
- Güncel stok, kritikal stok seviyeleri
- Satış potansiyeli yüksek ürünler

### Cari Raporu
- Müşteri bakiyeleri özeti
- Alacak-borç dengesi
