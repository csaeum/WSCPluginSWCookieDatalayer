# DataLayer Preis-Felder Dokumentation

## Übersicht

Dieses Plugin fügt zusätzliche Preis-Felder zum DataLayer hinzu, die **nicht Teil des Google Analytics 4 (GA4) Standards** sind, aber für erweiterte Tag Manager Konfigurationen nützlich sein können.

---

## Preis-Felder

### Standard GA4-Felder

| Feld | Beschreibung | GA4 Standard | Wert |
|------|--------------|--------------|------|
| `price` | Produktpreis pro Einheit | ✅ Ja | Brutto (inkl. MwSt.) |
| `quantity` | Anzahl | ✅ Ja | Stückzahl |

### Erweiterte Felder (Nicht GA4-Standard)

| Feld | Beschreibung | GA4 Standard | Wert | Zweck |
|------|--------------|--------------|------|-------|
| `price_net` | Netto-Preis pro Einheit | ❌ Nein | Netto (ohne MwSt.) | GTM-Berechnungen |
| `price_gross` | Brutto-Preis pro Einheit | ❌ Nein | Brutto (inkl. MwSt.) | GTM-Berechnungen |
| `tax` | Steuerbetrag pro Einheit | ❌ Nein | MwSt-Betrag | GTM-Berechnungen |

---

## Warum zusätzliche Felder?

### Problem ohne extra Felder

Wenn nur der GA4-Standard `price` (Brutto) vorhanden ist, müssen Sie im Google Tag Manager:

1. **Manuell Netto-Preise berechnen:**
   ```javascript
   // Im GTM Custom JavaScript Variable
   var bruttoPrice = {{DLV - price}};
   var taxRate = 0.19; // 19% MwSt. - hartcodiert!
   var nettoPrice = bruttoPrice / (1 + taxRate);
   ```

2. **Probleme:**
   - Steuersatz muss im GTM hartcodiert werden
   - Bei verschiedenen Steuersätzen (7%, 19%, etc.) wird es komplex
   - Fehleranfällig bei Steueränderungen
   - Keine Möglichkeit, exakte Steuern zu tracken

### Lösung mit extra Feldern

Mit `price_net`, `price_gross` und `tax` können Sie:

```javascript
// Im GTM - direkt verfügbar:
{{DLV - price}}       // Brutto für GA4
{{DLV - price_net}}   // Netto für Custom Reports
{{DLV - price_gross}} // Brutto (gleich wie price)
{{DLV - tax}}         // Exakter Steuerbetrag
```

**Vorteile:**
- ✅ Keine Berechnungen im GTM nötig
- ✅ Exakte Werte direkt aus Shopware
- ✅ Funktioniert mit allen Steuersätzen (7%, 19%, 0%)
- ✅ Flexibel für Custom Tracking (z.B. Facebook Conversions API)

---

## Beispiel DataLayer Events

### view_item (Produktseite)

```javascript
{
  "event": "view_item",
  "ecommerce": {
    "items": [
      {
        "item_id": "SWDEMO10001",
        "item_name": "Hauptartikel",
        "price": 19.99,         // GA4 Standard (Brutto)
        "price_net": 16.80,     // Extra: Netto
        "price_gross": 19.99,   // Extra: Brutto
        "tax": 3.19,            // Extra: MwSt. (19%)
        "quantity": 1
      }
    ]
  }
}
```

### purchase (Bestellabschluss)

```javascript
{
  "event": "purchase",
  "ecommerce": {
    "transaction_id": "10004",
    "value": 991.90,           // Gesamt-Brutto
    "tax": 158.37,             // Gesamt-MwSt.
    "shipping": 0,
    "items": [
      {
        "item_id": "SWDEMO10001",
        "price": 495.95,       // Brutto pro Stück
        "price_net": 416.76,   // Netto pro Stück
        "price_gross": 495.95, // Brutto pro Stück
        "tax": 79.19,          // MwSt. pro Stück
        "quantity": 2
      }
    ]
  }
}
```

---

## Use Cases

### 1. Facebook Conversions API

Facebook benötigt oft Netto-Preise für B2B:

```javascript
// GTM Custom HTML Tag
var items = {{DLV - ecommerce.items}};
var fbItems = items.map(function(item) {
  return {
    id: item.item_id,
    quantity: item.quantity,
    item_price: item.price_net  // Netto für FB!
  };
});
```

### 2. Custom Google Analytics Report

Tracken Sie Steuerbeträge separat:

```javascript
// Event Parameter in GA4
'tax_amount': {{DLV - tax}},
'net_revenue': {{DLV - price_net}} * {{DLV - quantity}}
```

### 3. Affiliate Tracking

Manche Affiliate-Netzwerke tracken Netto:

```javascript
// Affiliate Pixel
<img src="https://affiliate.com/track?amount={{DLV - price_net}}&tax={{DLV - tax}}">
```

---

## Berechnung

Die Felder werden in `src/Service/DataLayerBuilder.php` berechnet:

```php
// Brutto-Preis pro Einheit
$bruttoPrice = $calculatedPrice->getUnitPrice();

// Netto-Preis berechnen
$taxPerUnit = $calculatedPrice->getCalculatedTaxes()->getAmount() / $quantity;
$nettoPrice = $bruttoPrice - $taxPerUnit;

// DataLayer Item
$item = [
    'price' => $bruttoPrice,           // GA4 Standard
    'price_net' => round($nettoPrice, 2),   // Extra
    'price_gross' => $bruttoPrice,          // Extra
    'tax' => round($taxPerUnit, 2)          // Extra
];
```

---

## GA4 Kompatibilität

**Wichtig:** Die zusätzlichen Felder (`price_net`, `price_gross`, `tax`) beeinflussen **NICHT** die GA4-Standardfunktionalität:

- ✅ GA4 ignoriert unbekannte Felder automatisch
- ✅ Standard E-Commerce Berichte funktionieren normal
- ✅ `price` ist GA4-konform (Brutto)
- ✅ Keine Auswirkung auf GA4 Attribution

**Getestet mit:**
- Google Analytics 4
- Google Tag Manager
- Enhanced E-Commerce
- Server-Side Tagging

---

## Deaktivierung

Wenn Sie die extra Felder **nicht** benötigen, können Sie sie im GTM ignorieren:

```javascript
// Nur GA4-Standard Felder verwenden
dataLayer.push({
  event: 'purchase',
  ecommerce: {
    value: {{DLV - value}},
    items: {{DLV - items}}.map(function(item) {
      return {
        item_id: item.item_id,
        item_name: item.item_name,
        price: item.price,       // Nur price verwenden
        quantity: item.quantity
        // price_net, price_gross, tax werden ignoriert
      };
    })
  }
});
```

---

## Support

Bei Fragen zu den Preis-Feldern:
- Siehe Code: `src/Service/DataLayerBuilder.php`
- Issue #2: Preis-Berechnung Fix

**Changelog:**
- v1.1.0: Preis-Felder korrigiert (Issue #2)
- v1.0.0: Initiale Version mit price_net/price_gross
