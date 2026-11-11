# Stok Akış Diyagramı

Bu diyagram stok girişi, satış ve çıkış işlemlerinin stok üzerindeki etkisini göstermektedir.

```mermaid
graph TD
    A["Güncel Stok Hesapla"] 
    
    A --> B["Stok Girişleri<br/>+ Adet"]
    A --> C["Satışlar<br/>- Adet"]
    A --> D["Stok Çıkışları<br/>- Adet"]
    A --> E["Satış İadeleri<br/>+ Adet"]
    A --> F["Stok Çıkış İadeleri<br/>+ Adet"]
    A --> G["Tedarikçi İadeleri<br/>- Adet"]
    
    B --> H["Formül"]
    C --> H
    D --> H
    E --> H
    F --> H
    G --> H
    
    H --> I["Güncel Stok =<br/>Girişler + Satış İadeleri + Stok Çıkış İadeleri<br/>- Satışlar - Stok Çıkışları - Tedarikçi İadeleri"]
    
    I --> J{"Yetersiz Stok?"}
    J -->|Evet| K["❌ İşlem Reddedilir"]
    J -->|Hayır| L["✅ İşlem Onaylanır"]
    
    style I fill:#e1f5ff
    style K fill:#ffcdd2
    style L fill:#c8e6c9
    
    subgraph Girişler["📥 STOKa Gelen İşlemler"]
        B
        E
        F
    end
    
    subgraph Çıkışlar["📤 STOKtan Çıkan İşlemler"]
        C
        D
        G
    end
```

## Stok Hareketi Detayları

### Stoktan Çıkan İşlemler (-)

| İşlem | Açıklama | Bağlantı |
|-------|----------|---------|
| **Satış** | Satış kaydı yapıldığında stok anında düşer | Satış Kayıt |
| **Stok Çıkışı** | Stok hareket, kırılmış ürün, atık vs. | Stok → Çıkış |
| **Tedarikçi İadesi** | Tedarikçiye dönen ürünler | Finans → Tedarikçi İadeleri |

### Stoka Gelen İşlemler (+)

| İşlem | Açıklama | Bağlantı |
|-------|----------|---------|
| **Stok Girişi** | Yeni tedarikçi satınalması | Stok → Giriş |
| **Satış İadesi** | Müşteri tarafından iade edilen ürünler | Satış → İade |
| **Stok Çıkış İadesi** | Hatalı stok çıkışının düzeltilmesi | Stok → Çıkış İadesi |

## Önemli Kurallar

- **Yetersiz Stok**: Stok bitmişse satış ve çıkış işlemleri reddedilir
- **Teslimat Kaydı**: Satış teslim edilirken stok ikinci kez düşülmez (sadece satış kaydında düşer)
- **Kısmi Teslimat**: Satış bölme ve kısmi iade desteklenir; her işlem stoku günceller
- **FIFO Yöntemi**: Tahsilat işleminde eski alacaklar önce mahsup edilir

## Finansal Etki

- **Kâr/Zarar Hesabı**: Net Satış - Satış Anındaki Kitap Maliyeti - Gider - Bağış
- **Alacak Takibi**: Müşteri bakiyesi satış ile artar, tahsilat ve iade ile azalır
- **Tedarikçi Borcu**: Stok girişiyle artış, ödeme ve iade ile azalış
