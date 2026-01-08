# Page View Tracking - Universal für GA4, Matomo & Jitsu

## Problem

Bisher wurden nur **E-Commerce Events** (add_to_cart, view_item, purchase, etc.) an Jitsu gesendet, aber **keine Page Views**. Das führte zu:

- ❌ **Unvollständige User Journey** in Jitsu (nur vereinzelte Events, keine kontinuierliche Session)
- ❌ **Keine Session-Metriken** (Bounce Rate, Pages per Session, etc.)
- ❌ **Fehlende Basis-Daten** für Funnel-Analysen
- ❌ **Inkonsistenz** zwischen GA4/Matomo (haben Page Views) und Jitsu (hatte keine)

## Lösung

Ein **zentrales `page_view` Event** das:
- ✅ Auf **JEDER Seite** automatisch getrackt wird
- ✅ Von **GA4, Matomo UND Jitsu** gelesen wird
- ✅ **User-Daten** enthält (wenn eingeloggt)
- ✅ **Page Type** erkennt (product, category, checkout, etc.)
- ✅ **Konsistent** mit GA4 Enhanced Ecommerce

## Implementierung

### 1. Template: `page_view.html.twig`

**Datei:** `src/Resources/views/storefront/wscTagManager/DataLayer/page_view.html.twig`

**Was es macht:**
1. Wartet 100ms auf Backend-Events (um User-Daten zu extrahieren)
2. Ermittelt Page Type aus URL oder vorherigen Events
3. Pusht `page_view` Event in dataLayer und mtmLayer
4. Verhindert doppelte Page Views auf derselben Seite

**Event-Struktur:**
```javascript
{
  event: 'page_view',
  page_title: 'Produktname - Shop',
  page_location: 'https://shop.com/product/123',
  page_path: '/product/123',
  page_type: 'product',  // oder: category, checkout, home, etc.
  user: {
    user_email: 'customer@example.com',
    user_country: 'Germany',
    user_city: 'Berlin'
  }
}
```

### 2. Einbindung in `DataLayer.html.twig`

**Zeile 1-5:**
```twig
{# Page View Event - Universal for GA4, Matomo & Jitsu #}
{# Load on ALL pages to ensure complete user journey tracking #}
{% if config('WscSwCookieDataLayer.config.wscTagManagerDataLayerGoogle') or config('WscSwCookieDataLayer.config.wscTagManagerDataLayerMatomo') %}
    {% sw_include '@WscSwCookieDataLayer/storefront/wscTagManager/DataLayer/page_view.html.twig' %}
{% endif %}
```

**Resultat:** Page View wird auf **allen Seiten** geladen, sobald DataLayer aktiviert ist!

### 3. Jitsu Bridge erweitert

**Datei:** `src/Resources/views/storefront/wscTagManager/Jitsu/JitsuDataLayerBridge.html.twig`

**Zeile 35:**
```javascript
const TRACKED_EVENTS = [
    'page_view',  // ✅ NEU! Jetzt wird page_view an Jitsu gesendet
    'add_to_cart',
    'remove_from_cart',
    // ... alle anderen Events
];
```

**Resultat:** Jitsu empfängt jetzt auch Page View Events!

## Page Type Detection

Das Script erkennt automatisch den Seiten-Typ:

| URL Pattern | Page Type | Beschreibung |
|-------------|-----------|--------------|
| `/` | `home` | Startseite |
| `/product/*`, `/detail/*` | `product` | Produktdetailseite |
| `/navigation/*`, `/category/*` | `category` | Kategorie/Listing |
| `/checkout/confirm` | `checkout` | Checkout-Seite |
| `/checkout/finish` | `purchase_confirmation` | Bestellbestätigung |
| `/search` | `search` | Suchergebnisse |
| Alle anderen | `other` | Sonstige Seiten |

**Zusätzlich:** Fallback über vorherige dataLayer Events:
- `view_item` → `product`
- `view_item_list` → `category`
- `begin_checkout` → `checkout`
- `purchase` → `purchase_confirmation`

## Event-Flow

```
User lädt Seite (z.B. Produktseite)
        ↓
Backend pusht view_item Event MIT User-Daten
        ↓
100ms Delay
        ↓
page_view.html.twig lädt
        ↓
getUserData() sucht User-Daten im dataLayer
        ↓
✅ Findet user-Objekt vom view_item Event
        ↓
getPageType() erkennt 'product' (aus view_item Event oder URL)
        ↓
Pusht page_view Event:
{
  event: 'page_view',
  page_title: 'Produktname',
  page_location: 'https://shop.com/product/123',
  page_path: '/product/123',
  page_type: 'product',
  user: { user_email: '...', ... }
}
        ↓
    ┌───┴───┬─────┐
    ↓       ↓     ↓
  GTM     Matomo  Jitsu
```

## Vorteile

### 1. **Vollständige User Journey in Jitsu** ✅
Jitsu sieht jetzt:
```
Session Start
  ↓
page_view (home)
  ↓
page_view (category)
  ↓
select_item
  ↓
page_view (product)
  ↓
view_item
  ↓
add_to_cart
  ↓
page_view (checkout)
  ↓
begin_checkout
  ↓
purchase
Session End
```

**Vorher:** Nur die E-Commerce Events (keine Kontext!)
**Nachher:** Komplette User Journey mit Seitenaufrufen!

### 2. **Session-Metriken** ✅
Jitsu kann jetzt berechnen:
- **Pages per Session** (wieviele Seiten pro Besuch)
- **Bounce Rate** (Besuche mit nur 1 Page View)
- **Average Session Duration** (Zeit zwischen erstem und letztem Page View)
- **Entry Pages** (erste Page View der Session)
- **Exit Pages** (letzte Page View der Session)

### 3. **Funnel-Analysen** ✅
Beispiel Checkout-Funnel:
```
100% - page_view (category)
 80% - page_view (product)
 60% - add_to_cart
 40% - page_view (checkout)
 30% - begin_checkout
 20% - add_payment_info
 15% - purchase
```

### 4. **User Identification** ✅
Page Views enthalten User-Daten:
- **Eingeloggte User:** `userId`, `traits` (Email, Land, Stadt)
- **Gäste:** Nur `anonymousId`

### 5. **Konsistenz** ✅
Alle Tracking-Systeme nutzen **dasselbe Event-Format**:
- GA4: Liest `page_view` aus dataLayer
- Matomo: Liest `page_view` aus mtmLayer (zusätzlich zu nativem `_paq`)
- Jitsu: Liest `page_view` aus dataLayer (via Bridge)

## Vergleich: Vorher vs. Nachher

### Vorher ❌

**GA4:**
```
page_view (via GTM/gtag.js)
view_item
add_to_cart
```

**Matomo:**
```
trackPageView (via _paq)
view_item
add_to_cart
```

**Jitsu:**
```
view_item           ← Keine Page View!
add_to_cart         ← Keine Page View!
```

❌ **Inkonsistent!** Jitsu hat unvollständige Daten.

### Nachher ✅

**Alle (GA4, Matomo, Jitsu):**
```
page_view           ← Überall!
view_item
add_to_cart
page_view           ← Überall!
begin_checkout
purchase
```

✅ **Konsistent!** Alle Systeme sehen dieselben Page Views.

## Testing

### Schritt 1: Cache leeren
```bash
bin/console cache:clear
```

### Schritt 2: Debug-Modus aktivieren
```
Shopware Admin → WSC Cookie DataLayer → wscTagManagerDataLayerDebug = true
```

### Schritt 3: Seite laden
1. Öffne Shop im Browser
2. Öffne Console (F12)
3. Lade eine beliebige Seite

**Erwartete Logs:**
```
🔧 WSC Page View Tracking: Loading...
🔧 WSC Page View: DOM already loaded
🔧 WSC Page View: Pushing page_view event...
🔧 WSC Page View: Getting user data from dataLayer...
🔧 WSC Page View: Found user data at index 0 {
    user_email: "customer@example.com",
    user_country: "Germany",
    user_city: "Berlin"
}
📄 WSC Page View Event pushing: {
    event: "page_view",
    page_title: "Produktname - Shop",
    page_location: "https://shop.com/product/123",
    page_path: "/product/123",
    page_type: "product",
    user: {...}
}
✅ Pushed page_view to window.dataLayer
✅ Pushed page_view to window._mtm
✅ WSC Page View Event pushed successfully!
   Page Type: product
   User Data: Present

🔍 Jitsu: Processing dataLayer event { event: 'page_view', ... }
🔧 Jitsu: Added user data to payload {
    email: "customer@example.com",
    country: "Germany",
    city: "Berlin"
}
📤 Jitsu: Sending event {
    event: "page_view",
    userId: "customer@example.com",
    traits: {...}
}
✅ Jitsu: Event sent successfully
```

### Schritt 4: DataLayer prüfen
```javascript
// Console:
console.log(window.dataLayer.filter(e => e.event === 'page_view'));
```

**Erwartetes Ergebnis:**
```javascript
[
  {
    event: 'page_view',
    page_title: 'Produktname - Shop',
    page_location: 'https://shop.com/product/123',
    page_path: '/product/123',
    page_type: 'product',
    user: {
      user_email: 'customer@example.com',
      user_country: 'Germany',
      user_city: 'Berlin'
    }
  }
]
```

### Schritt 5: Jitsu Dashboard prüfen
1. Öffne **Jitsu Dashboard** → **Events**
2. Filtere nach `page_view` Events
3. Prüfe Payload:

```json
{
  "event": "page_view",
  "userId": "customer@example.com",
  "anonymousId": "anon_xyz123",
  "traits": {
    "email": "customer@example.com",
    "country": "Germany",
    "city": "Berlin"
  },
  "properties": {
    "page_title": "Produktname - Shop",
    "page_location": "https://shop.com/product/123",
    "page_path": "/product/123",
    "page_type": "product"
  },
  "context": {
    "page": {...},
    "userAgent": "...",
    "screen": {...}
  }
}
```

### Schritt 6: Mehrere Seiten navigieren
1. Navigiere durch den Shop: Home → Kategorie → Produkt → Checkout
2. Prüfe Console für Page View bei jeder Navigation
3. Prüfe Jitsu Dashboard für alle Page Views

**Erwartete Page Views:**
```
page_view (page_type: 'home')
page_view (page_type: 'category')
page_view (page_type: 'product')
page_view (page_type: 'checkout')
```

## Wichtige Hinweise

### 1. **100ms Delay**
Das Script wartet 100ms bevor es page_view pusht. **Warum?**
- Backend-Events (view_item, begin_checkout, etc.) laden zuerst
- Diese enthalten User-Daten
- page_view kann dann User-Daten daraus extrahieren

**Edge Case:** Wenn Backend-Event länger als 100ms braucht:
- page_view wird ohne User-Daten gepusht
- Nachfolgende Events haben dann User-Daten

**Lösung:** Falls nötig, Delay auf 200ms erhöhen (Zeile 120 in page_view.html.twig).

### 2. **Duplicate Prevention**
`window.__wscPageViewTracked = true` verhindert doppelte Page Views auf derselben Seite.

**Wichtig:** Bei **SPA (Single Page Applications)** oder **AJAX-Navigation** wird die Seite NICHT neu geladen!
- page_view wird NUR einmal getrackt (beim ersten Laden)
- Nachfolgende AJAX-Navigation trackt KEIN neues page_view

**Lösung für SPAs:** Reset `window.__wscPageViewTracked = false` bei AJAX-Navigation.

### 3. **Kombination mit Matomo/GA4**

**Matomo:**
- Nutzt weiterhin `_paq.push(['trackPageView'])` (native Tracker)
- Zusätzlich: Kann `page_view` aus `window._mtm` lesen (für Konsistenz)

**GA4:**
- Kann `page_view` aus `window.dataLayer` lesen (via GTM Trigger)
- Oder nutzt weiterhin `gtag('event', 'page_view')` (native)

**Empfehlung:** Verwende GTM-Trigger für `page_view` Event im dataLayer - dann trackt GTM automatisch!

### 4. **GDPR & Consent**

Page Views enthalten **User-Daten** (Email, Land, Stadt).

**Wichtig:**
- ✅ Jitsu Consent Mode ist aktiviert
- ✅ Events werden nur mit Analytics-Consent gesendet
- ✅ Datenschutzerklärung erwähnt Page View Tracking

### 5. **Performance**

**Impact:** Minimal
- Script läuft nur 1x pro Seite
- 100ms Delay ist kaum merkbar
- Keine externen Requests (nur dataLayer push)

**Größe:** ~3 KB (unkomprimiert)

## Erweiterte Konfiguration

### Custom Page Types

Wenn du eigene Page Types brauchst, erweitere `getPageType()`:

```javascript
// In page_view.html.twig, Zeile 50-70
function getPageType() {
    const path = window.location.pathname;

    // Deine Custom Page Types
    if (path.includes('/blog/')) return 'blog';
    if (path.includes('/downloads/')) return 'downloads';
    if (path.includes('/support/')) return 'support';

    // Standard Page Types...
}
```

### Custom Properties

Füge zusätzliche Properties hinzu:

```javascript
// In page_view.html.twig, Zeile 80-90
const eventData = {
    event: 'page_view',
    page_title: document.title,
    page_location: window.location.href,
    page_path: window.location.pathname,
    page_type: pageType,
    user: userData,

    // Custom Properties
    page_language: document.documentElement.lang,
    page_referrer: document.referrer,
    viewport_width: window.innerWidth,
    viewport_height: window.innerHeight
};
```

## Checkliste

- [x] `page_view.html.twig` Template erstellt
- [x] In `DataLayer.html.twig` eingebunden (lädt auf allen Seiten)
- [x] `page_view` zu Jitsu TRACKED_EVENTS hinzugefügt
- [x] `view_search_results` zu Jitsu TRACKED_EVENTS hinzugefügt
- [x] getUserData() extrahiert User-Daten
- [x] getPageType() erkennt Seiten-Typ
- [x] Debug-Logs implementiert
- [x] Duplicate Prevention (nur 1 page_view pro Seite)
- [x] 100ms Delay für User-Daten-Extraktion
- [x] Dokumentation erstellt

## Zusammenfassung

✅ **Jitsu hat jetzt vollständige User Journey** (Page Views + E-Commerce Events)
✅ **Session-Metriken möglich** (Bounce Rate, Pages/Session, Duration)
✅ **Funnel-Analysen möglich** (Page Views zwischen Events)
✅ **Konsistent mit GA4/Matomo** (alle sehen dieselben Page Views)
✅ **User-Daten in allen Events** (eingeloggte User werden identifiziert)

🎯 **Ergebnis:** Jitsu ist jetzt ein **vollwertiges Analytics-Tool**, nicht nur für E-Commerce, sondern für die **gesamte User Journey**!
