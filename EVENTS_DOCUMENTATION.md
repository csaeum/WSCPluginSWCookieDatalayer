# GA4 DataLayer Events - Dokumentation

Dieses Plugin trackt alle wichtigen GA4 E-Commerce Events automatisch.

## 📊 Implementierte Events (16 Events)

### E-Commerce Events (14 Events)

#### 1. **view_item_list** - Produktliste angezeigt
- **Trigger:** Kategorieseite, Suchseite, verwandte Produkte
- **Implementierung:** Backend (PHP - DataLayerBuilder)
- **Felder:** `item_list_id`, `item_list_name`, `items[]`

#### 2. **select_item** ⭐ NEU
- **Trigger:** User klickt auf Produkt im Listing
- **Implementierung:** Frontend (JavaScript - add_to_cart.html.twig)
- **Felder:** `item_list_id`, `item_list_name`, `items[]`
- **CSS-Selektoren:** `.product-name`, `.product-image-link`, `a.product-link`, `[data-product-link]`

#### 3. **view_item** - Produktdetailseite angezeigt
- **Trigger:** Produktdetailseite aufgerufen
- **Implementierung:** Backend (PHP - DataLayerBuilder)
- **Felder:** `currency`, `value`, `items[]`

#### 4. **add_to_cart** - Produkt in Warenkorb gelegt
- **Trigger:** Klick auf "In den Warenkorb" Button
- **Implementierung:** Frontend (JavaScript - add_to_cart.html.twig)
- **Besonderheit:** Quantity wird aus AJAX-Request extrahiert
- **Felder:** `currency`, `value`, `items[]` (inkl. korrekter `quantity`)

#### 5. **remove_from_cart** - Produkt aus Warenkorb entfernt
- **Trigger:** Produkt aus Warenkorb entfernen
- **Implementierung:** Frontend (JavaScript - add_to_cart.html.twig)
- **Felder:** `currency`, `value`, `items[]`

#### 6. **view_cart** - Warenkorb-Seite angezeigt
- **Trigger:** Warenkorb-Seite aufgerufen
- **Implementierung:** Backend (PHP - DataLayerBuilder)
- **Felder:** `currency`, `value`, `items[]`

#### 7. **begin_checkout** - Checkout gestartet
- **Trigger:** Checkout-Prozess gestartet
- **Implementierung:** Backend (PHP - DataLayerBuilder)
- **Felder:** `currency`, `value`, `items[]`

#### 8. **add_shipping_info** - Versandart ausgewählt
- **Trigger:** User wählt Versandmethode im Checkout
- **Implementierung:** Frontend (JavaScript - checkout_tracking.html.twig)
- **Felder:** `currency`, `value`, `shipping_tier`, `items[]`

#### 9. **add_payment_info** - Zahlungsart ausgewählt
- **Trigger:** User wählt Zahlungsmethode im Checkout
- **Implementierung:** Frontend (JavaScript - checkout_tracking.html.twig)
- **Felder:** `currency`, `value`, `payment_type`, `items[]`

#### 10. **purchase** - Bestellung abgeschlossen
- **Trigger:** Bestellung erfolgreich abgeschlossen
- **Implementierung:** Backend (PHP - DataLayerBuilder)
- **Felder:** `transaction_id`, `affiliation`, `currency`, `value`, `tax`, `shipping`, `coupon`, `items[]`

#### 11. **add_to_wishlist** - Zur Wunschliste hinzugefügt
- **Trigger:** Klick auf Wunschlisten-Button
- **Implementierung:** Frontend (JavaScript - add_to_cart.html.twig)
- **Felder:** `currency`, `value`, `items[]`

#### 12. **search** ⭐ NEU
- **Trigger:** User führt eine Suche durch
- **Implementierung:** Frontend (JavaScript - search_tracking.html.twig)
- **Felder:** `search_term`
- **Form-Erkennung:** `.search-form`, `.header-search-form`, `form[action*="/search"]`

#### 13. **view_promotion** ⭐ NEU
- **Trigger:** Banner/Promotion wird sichtbar (50% im Viewport)
- **Implementierung:** Frontend (JavaScript - promotion_tracking.html.twig)
- **Technologie:** Intersection Observer API
- **Felder:** `items[]` mit `promotion_id`, `promotion_name`, `creative_name`, `creative_slot`
- **CSS-Selektoren:** `[data-promotion-tracking]`, `[data-banner-tracking]`, `.promotion-banner`

#### 14. **select_promotion** ⭐ NEU
- **Trigger:** User klickt auf Banner/Promotion
- **Implementierung:** Frontend (JavaScript - promotion_tracking.html.twig)
- **Felder:** `items[]` mit `promotion_id`, `promotion_name`, `creative_name`, `creative_slot`

### User Events (2 Events)

#### 15. **login** - User Login
- **Trigger:** User meldet sich an
- **Implementierung:** Backend (Twig - login.html.twig)
- **Felder:** `method`, `user` (email, country, city)

#### 16. **sign_up** - User Registrierung
- **Trigger:** User registriert sich
- **Implementierung:** Backend (Twig - sign_up.html.twig)
- **Felder:** `method`, `user` (email, country, city)

---

## 🎯 GA4-konforme Produktfelder

Alle Events enthalten folgende GA4-Felder:

**Pflichtfelder:**
- ✅ `item_id` - Produktnummer
- ✅ `item_name` - Produktname
- ✅ `currency` - Währung (EUR, USD, etc.)
- ✅ `price` - Preis (Brutto pro Einheit)

**Empfohlene Felder:**
- ✅ `affiliation` - Shop-Name
- ✅ `item_brand` - Marke/Hersteller
- ✅ `item_category` - Hauptkategorie
- ✅ `item_category2-5` - Unterkategorien
- ✅ `item_variant` - Produktvariante (z.B. "Größe:M|Farbe:Blau")
- ✅ `item_list_id` - Listen-ID (z.B. "category_listing")
- ✅ `item_list_name` - Listen-Name (z.B. "Category Listing")
- ✅ `index` - Position in der Liste (0-basiert)
- ✅ `quantity` - Anzahl
- ✅ `discount` - Rabatt in % (wenn vorhanden)
- ✅ `google_business_vertical` - Immer "retail"

**Zusätzliche Felder (für GTM/Custom Tracking):**
- ✅ `price_net` - Nettopreis
- ✅ `price_gross` - Bruttopreis
- ✅ `tax` - Steuer pro Einheit

---

## 🛠️ Setup für Promotions

### HTML-Markup für Promotions/Banner:

```html
<!-- Einfaches Banner -->
<div data-promotion-tracking
     data-promotion-id="summer_sale_2025"
     data-promotion-name="Summer Sale 2025"
     data-creative-name="Banner Hero"
     data-creative-slot="home_hero">
    <a href="/sale">
        <img src="banner.jpg" alt="Summer Sale">
    </a>
</div>

<!-- Banner mit allen Optionen -->
<a href="/promo"
   data-promotion-tracking
   data-promotion-id="black_friday"
   data-promotion-name="Black Friday Deal"
   data-creative-name="Sidebar Banner"
   data-creative-slot="sidebar_top"
   class="promotion-banner">
    <img src="black-friday.jpg" alt="Black Friday">
</a>
```

**Erkannte CSS-Selektoren:**
- `[data-promotion-tracking]` (empfohlen!)
- `[data-banner-tracking]`
- `.promotion-banner`
- `.cms-element-image-slider[data-promotion="true"]`
- `.banner-slider[data-promotion="true"]`

**Verfügbare Data-Attribute:**
- `data-promotion-id` / `data-banner-id`
- `data-promotion-name` / `data-banner-name`
- `data-creative-name` / `data-banner-creative`
- `data-creative-slot` / `data-banner-slot`

---

## 🔍 Debug-Modus

### Aktivierung:
Admin → Einstellungen → Plugin-Konfiguration → "DataLayer Debug-Modus aktivieren"

### Debug-Ausgaben:

**Browser Console:**
```javascript
🔧 WSC Add-to-Cart Script: Loading...
🔧 Registering select_item listener
🔧 Product link clicked: <a>
🔧 Pushing select_item event with data: {...}
✅ select_item event pushed successfully!

🔍 WSC Search Event pushed: {event: "search", search_term: "..."}

🎨 WSC Promotion Event pushed: view_promotion {...}
```

**DataLayer prüfen:**
```javascript
// Im Browser
console.table(window.dataLayer);

// Nur bestimmte Events
window.dataLayer.filter(e => e.event === 'select_item');

// Letztes Event
window.dataLayer[window.dataLayer.length - 1];
```

---

## 📝 Testing-Checkliste

### E-Commerce Events:
- [ ] Kategorieseite öffnen → `view_item_list`
- [ ] Produkt im Listing klicken → `select_item`
- [ ] Produktdetailseite → `view_item`
- [ ] In Warenkorb (Quantity 1) → `add_to_cart` (quantity: 1, value: 19.99)
- [ ] In Warenkorb (Quantity 3) → `add_to_cart` (quantity: 3, value: 59.97)
- [ ] Produkt entfernen → `remove_from_cart`
- [ ] Warenkorb öffnen → `view_cart`
- [ ] Checkout starten → `begin_checkout`
- [ ] Versandart wählen → `add_shipping_info`
- [ ] Zahlungsart wählen → `add_payment_info`
- [ ] Bestellung abschließen → `purchase`
- [ ] Zur Wunschliste → `add_to_wishlist`

### Neue Events:
- [ ] Suche durchführen → `search` (search_term: "...")
- [ ] Banner sichtbar (50%) → `view_promotion`
- [ ] Banner klicken → `select_promotion`

### User Events:
- [ ] Login → `login`
- [ ] Registrierung → `sign_up`

---

## 🚀 Changelog

### Version: Issue #10 (2025-12-30)
- ✨ **NEU:** `select_item` Event (Produkt-Klicks im Listing)
- ✨ **NEU:** `search` Event (Suchfunktion-Tracking)
- ✨ **NEU:** `view_promotion` Event (Banner-Impressions mit Intersection Observer)
- ✨ **NEU:** `select_promotion` Event (Banner-Klicks)
- 🎯 Alle Events GA4-konform
- 🐛 Debug-Modus für alle neuen Events

### Version: Issue #2 + Issue #3 (2025-12-29)
- ✨ GA4-konforme Felder hinzugefügt (affiliation, item_list_id, index, etc.)
- ✨ Quantity-Extraktion aus AJAX-Requests
- ✨ Debug-Modus zentral steuerbar
- 🐛 Buy-Widget Template-Block korrigiert
- 🐛 Preis-Berechnungen korrigiert (Brutto/Netto/Tax)

---

## 📚 Weiterführende Links

- [GA4 E-Commerce Events Documentation](https://developers.google.com/analytics/devguides/collection/ga4/ecommerce)
- [GA4 Promotion Events](https://developers.google.com/analytics/devguides/collection/ga4/promotions)
- [Intersection Observer API](https://developer.mozilla.org/en-US/docs/Web/API/Intersection_Observer_API)
