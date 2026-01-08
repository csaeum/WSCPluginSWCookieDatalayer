# Jitsu Frontend Tracking - DataLayer Bridge

## Übersicht

Die **Jitsu DataLayer Bridge** leitet automatisch alle relevanten `dataLayer` Events an Jitsu weiter. Dies ermöglicht Client-seitiges Tracking zusätzlich zum bereits implementierten Server-seitigen Tracking.

## Funktionsweise

### Architektur

```
User-Aktion (z.B. "In den Warenkorb")
         |
         v
JavaScript Event Handler (add_to_cart.html.twig)
         |
         v
window.dataLayer.push({ event: 'add_to_cart', ... })
         |
         +----> Google Tag Manager / GA4
         |
         +----> Jitsu DataLayer Bridge
                    |
                    v
                 Jitsu API (client-side fetch)
                    |
                    v
                 Jitsu Server
```

### Implementierung

#### 1. DataLayer Interceptor
Die Bridge installiert einen Interceptor für `window.dataLayer.push()`, der:
- Alle Events analysiert
- Relevante GA4-Events (siehe Liste unten) filtert
- Diese an Jitsu weiterleitet

#### 2. Getrackte Events
```javascript
const TRACKED_EVENTS = [
    'add_to_cart',           // ✅ Produkt in Warenkorb
    'remove_from_cart',      // ✅ Produkt aus Warenkorb
    'add_to_wishlist',       // ✅ Produkt zu Wunschliste
    'view_item',             // ✅ Produktdetailseite
    'view_item_list',        // ✅ Produktlistingseite
    'select_item',           // ✅ Produkt angeklickt
    'begin_checkout',        // ✅ Checkout gestartet
    'add_payment_info',      // ✅ Zahlungsinformationen hinzugefügt
    'add_shipping_info',     // ✅ Versandinformationen hinzugefügt
    'purchase',              // ✅ Kauf abgeschlossen
    'refund',                // ✅ Rückerstattung
    'search',                // ✅ Suche
    'login',                 // ✅ Login
    'sign_up',               // ✅ Registrierung
    'select_promotion',      // ✅ Promotion ausgewählt
    'view_promotion',        // ✅ Promotion angesehen
    'generate_lead',         // ✅ Lead generiert
    'add_to_compare'         // ✅ Produkt zum Vergleich hinzugefügt
];
```

#### 3. Consent Management
- **Consent Mode aktiviert**: Prüft Cookie Consent vor jedem Event
- **Consent Mode deaktiviert**: Alle Events werden getrackt
- Consent-Status wird in `context.consent` mitgesendet

#### 4. Payload-Struktur
```json
{
  "event": "add_to_cart",
  "properties": {
    "currency": "EUR",
    "value": 29.99,
    "items": [
      {
        "item_id": "SW10001",
        "item_name": "Product Name",
        "quantity": 1,
        "price": 29.99
      }
    ]
  },
  "anonymousId": "anon_xyz123",
  "timestamp": "2026-01-08T10:30:00.000Z",
  "context": {
    "page": {
      "url": "https://example.com/product/123",
      "referrer": "https://example.com/",
      "title": "Product Name"
    },
    "userAgent": "Mozilla/5.0...",
    "screen": {
      "width": 1920,
      "height": 1080
    },
    "consent": {
      "analytics": true,
      "marketing": false,
      "functional": true
    }
  }
}
```

## Konfiguration

### Voraussetzungen
In der Shopware-Administration müssen folgende Einstellungen konfiguriert sein:

1. **Jitsu aktivieren**
   - `WscSwCookieDataLayer.config.wscTagManagerJitsu` = `true`

2. **Jitsu Server URL**
   - `WscSwCookieDataLayer.config.wscTagManagerJitsuUrl`
   - Beispiel: `https://jitsu.example.com`

3. **Jitsu Write Key**
   - `WscSwCookieDataLayer.config.wscTagManagerJitsuWriteKey`
   - Beispiel: `js.abc123xyz...`

4. **Optional: Debug-Modus**
   - `WscSwCookieDataLayer.config.wscTagManagerJitsuDebug` = `true`
   - Aktiviert ausführliche Console-Logs

5. **Optional: Consent Mode**
   - `WscSwCookieDataLayer.config.wscTagManagerJitsuConsentMode` = `true`
   - Respektiert Cookie Consent

### Debug-Modus

Mit aktiviertem Debug-Modus siehst du in der Browser-Console:

```
🚀 Jitsu DataLayer Bridge: Initializing...
✅ Jitsu DataLayer Bridge: Interceptor installed successfully
✅ Jitsu DataLayer Bridge: Initialized successfully

🔍 Jitsu: Processing dataLayer event { event: 'add_to_cart', ... }
📤 Jitsu: Sending event { event: 'add_to_cart', endpoint: '...', payload: {...} }
✅ Jitsu: Event sent successfully { event: 'add_to_cart', status: 200 }
```

## Testing

### 1. Console-Test
```javascript
// Öffne Browser Console (F12)

// Test 1: add_to_cart Event
dataLayer.push({
  event: 'add_to_cart',
  ecommerce: {
    currency: 'EUR',
    value: 29.99,
    items: [{
      item_id: 'TEST123',
      item_name: 'Test Product',
      quantity: 1,
      price: 29.99
    }]
  }
});

// Test 2: Prüfe ob Event gesendet wurde
// Schaue in der Console nach: "✅ Jitsu: Event sent successfully"
```

### 2. Network-Tab
1. Öffne Browser DevTools (F12)
2. Gehe zum **Network** Tab
3. Filter nach `track`
4. Führe eine Aktion aus (z.B. Produkt in Warenkorb legen)
5. Prüfe ob Request an `https://jitsu.example.com/api/s/s2s/track` gesendet wurde

### 3. Jitsu Dashboard
1. Öffne dein Jitsu Dashboard
2. Gehe zu **Events** oder **Debug**
3. Prüfe ob Events ankommen

## Unterschied: Frontend vs. Backend Tracking

### Frontend (Jitsu DataLayer Bridge) ✨ NEU
- **Quelle**: JavaScript im Browser
- **Zeitpunkt**: Unmittelbar bei User-Interaktion
- **Vorteile**:
  - Echtzeit-Tracking
  - Erfasst auch abgebrochene Aktionen
  - Mehr Kontext (Browser, Screen, etc.)
- **Nachteile**:
  - Kann durch Ad-Blocker blockiert werden
  - Benötigt Client-seitige Consent-Prüfung

### Backend (JitsuTrackingSubscriber) ✅ BEREITS VORHANDEN
- **Quelle**: PHP Server
- **Zeitpunkt**: Nach erfolgreicher Server-Aktion
- **Vorteile**:
  - Zuverlässig (keine Ad-Blocker)
  - Transaktionale Daten (tatsächlicher Warenkorb)
- **Nachteile**:
  - Verzögert (nach Server-Response)
  - Erfasst nur erfolgreiche Aktionen

### Empfehlung: Beide nutzen! 🎯
- **Frontend**: Für Engagement-Tracking (Clicks, Views, Interactions)
- **Backend**: Für transaktionale Daten (tatsächliche Käufe, Warenkorb)

## Troubleshooting

### Problem: Events kommen nicht in Jitsu an

**1. Prüfe Konfiguration**
```javascript
// Console:
console.log(window.__wscJitsuDebug);  // sollte 'true' oder 'false' sein
console.log(window.dataLayer);         // sollte ein Array sein
```

**2. Prüfe Consent**
```javascript
// Console:
localStorage.getItem('cc_preferences');  // Consent-Status
```

**3. Prüfe Network-Fehler**
- Öffne Network-Tab
- Filtere nach `track`
- Prüfe Status-Code (sollte 200 sein)
- Prüfe CORS-Fehler

**4. Prüfe Jitsu Write Key**
- Ist der Write Key korrekt?
- Ist die Jitsu URL erreichbar?

### Problem: Doppelte Events

Wenn du jetzt **sowohl Frontend als auch Backend** Tracking hast, könnten Events doppelt getrackt werden:

**Lösung 1: Deaktiviere Backend-Tracking für bestimmte Events**
- Editiere `JitsuTrackingSubscriber.php`
- Entferne `add_to_cart` aus den getSubscribedEvents

**Lösung 2: Deduplizierung in Jitsu**
- Nutze Jitsu's Deduplication-Features
- Filtere Server-Side-Events in Jitsu-Transformationen

## Weitere Ressourcen

- [Jitsu Documentation](https://jitsu.com/docs)
- [Google Analytics 4 Event Reference](https://developers.google.com/analytics/devguides/collection/ga4/reference/events)
- [Cookie Consent Integration](./EVENTS_DOCUMENTATION.md)
