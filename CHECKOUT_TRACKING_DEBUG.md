# Checkout Tracking Debug Guide - add_payment_info & add_shipping_info

## Problem
Die Events `add_payment_info` und `add_shipping_info` werden nicht zu Matomo, Google Analytics und Jitsu gesendet.

## Lösung
Ich habe die Checkout-Tracking-Implementierung mit ausführlichen Debug-Logs versehen und mehrere Probleme behoben:

### Behobene Probleme

1. **Verbesserter Path-Check** ✅
   - **Vorher**: Nur `/checkout/` erkannt
   - **Nachher**: Unterstützt auch mehrsprachige URLs (`/de/checkout/`, `/en/checkout/`, etc.)

2. **Debug-Modus hinzugefügt** ✅
   - Ausführliche Console-Logs für alle Schritte
   - Zeigt an, welche Input-Felder gefunden werden
   - Warnt, wenn keine Cart-Daten vorhanden sind

3. **Doppelte Initialisierung verhindert** ✅
   - Prüft ob bereits initialisiert via `window.__wscCheckoutTrackingInitialized`
   - Verhindert Konflikte mit dem JS-Plugin

4. **Bessere Error-Meldungen** ✅
   - Zeigt an, wenn Selektoren nicht gefunden werden
   - Zeigt an, wenn Cart-Daten fehlen

## Debug-Schritte

### Schritt 1: Debug-Modus aktivieren

1. Gehe zu **Shopware Admin** → **Erweiterungen** → **Meine Erweiterungen**
2. Finde **WSC Cookie DataLayer**
3. Klicke auf **Konfigurieren**
4. Aktiviere **Debug-Modus** (`wscTagManagerDataLayerDebug`)
5. Speichern

### Schritt 2: Checkout-Seite öffnen

1. Öffne deinen Shop im Browser
2. Lege ein Produkt in den Warenkorb
3. Gehe zum Checkout (`/checkout/confirm`)
4. Öffne die **Browser Console** (F12 → Console)

### Schritt 3: Debug-Logs prüfen

Du solltest folgende Logs sehen:

```
🔧 WSC Checkout Tracking: Initializing on checkout page...
🔧 WSC Checkout Tracking: DOM already loaded, registering listeners immediately
🔧 WSC Checkout Tracking: Registering event listeners...
🔧 WSC Checkout Tracking: Change listeners registered
🔧 WSC Checkout Tracking: Found 3 payment inputs
🔧 WSC Checkout Tracking: Found 2 shipping inputs
✅ WSC Checkout Tracking: Initialization complete!
```

### Schritt 4: Pre-Selected Methods prüfen

Nach 500ms solltest du sehen:

```
🔧 WSC Checkout Tracking: Checking for pre-selected methods...
🔧 WSC Checkout Tracking: On confirm page, tracking pre-selected methods
🔧 WSC Checkout Tracking: Found pre-selected payment, tracking...
🔧 WSC Checkout Tracking: trackPaymentMethodChange() called
🔧 WSC Checkout Tracking: Found selected payment: <input...>
🔧 WSC Checkout Tracking: Payment method changed to: Invoice (ID: abc123)
🔧 WSC Checkout Tracking: Getting cart items from dataLayer...
🔧 WSC Checkout Tracking: Found cart data at index 2 {...}
📤 WSC Checkout Tracking: Pushing event: add_payment_info {...}
✅ Pushed to window.dataLayer
✅ Pushed to window._mtm
```

### Schritt 5: Zahlungsart ändern

1. Wähle eine **andere Zahlungsart** im Checkout
2. Du solltest sehen:

```
🔧 WSC Checkout Tracking: Payment method change event detected
🔧 WSC Checkout Tracking: trackPaymentMethodChange() called
🔧 WSC Checkout Tracking: Found selected payment: <input...>
🔧 WSC Checkout Tracking: Payment method changed to: Credit Card (ID: xyz789)
📤 WSC Checkout Tracking: Pushing event: add_payment_info {...}
✅ Pushed to window.dataLayer
✅ Pushed to window._mtm
```

### Schritt 6: Jitsu-Weiterleitung prüfen

Die Jitsu DataLayer Bridge sollte das Event automatisch abfangen:

```
🔍 Jitsu: Processing dataLayer event { event: 'add_payment_info', ... }
📤 Jitsu: Sending event { event: 'add_payment_info', endpoint: '...', payload: {...} }
✅ Jitsu: Event sent successfully { event: 'add_payment_info', status: 200 }
```

## Häufige Probleme & Lösungen

### Problem 1: "Not on checkout page, skipping"

**Ursache**: Der Path-Check schlägt fehl
**Lösung**: Prüfe die URL in der Console:

```javascript
console.log(window.location.pathname);
```

Wenn die URL nicht `/checkout/` enthält, passe den Path-Check an.

### Problem 2: "No payment method selected"

**Ursache**: Die Selektoren finden keine Input-Felder
**Debug**:

```javascript
// Console:
document.querySelectorAll('input[name="paymentMethodId"]');
// Sollte NodeList mit Input-Elementen zurückgeben
```

**Mögliche Selektoren in Shopware 6**:
- `input[name="paymentMethodId"]` ✅ (Standard)
- `input[type="radio"][name="paymentMethodId"]`
- `input.payment-method-input`

**Lösung**: Wenn ein anderer Selektor benötigt wird, passe Zeile 81 in `checkout_tracking.html.twig` an.

### Problem 3: "No cart items found in dataLayer!"

**Ursache**: Der dataLayer enthält keine ecommerce.items
**Debug**:

```javascript
// Console:
console.log(window.dataLayer);
// Suche nach Einträgen mit ecommerce.items
```

**Lösung**: Stelle sicher, dass ein Event mit ecommerce.items vorher gepusht wurde (z.B. `begin_checkout` oder `view_item_list`).

### Problem 4: Events werden nicht zu Jitsu gesendet

**Ursache 1**: Jitsu Bridge nicht initialisiert
**Check**:
```javascript
console.log(window.__wscJitsuBridgeInitialized);  // sollte 'true' sein
```

**Ursache 2**: Consent fehlt
**Check**:
```javascript
localStorage.getItem('cc_preferences');  // Prüfe analytics: true
```

**Ursache 3**: Jitsu-Konfiguration fehlt
**Check**: Stelle sicher, dass Jitsu URL und Write Key konfiguriert sind.

### Problem 5: Doppelte Events

**Ursache**: Beide Implementierungen (checkout_tracking.html.twig + wsc-checkout-datalayer.plugin.js) laufen parallel

**Lösung**: Die neue Implementierung verhindert dies automatisch via `window.__wscCheckoutTrackingInitialized`.

## Manuelle Tests

### Test 1: DataLayer-Push simulieren

```javascript
// Console:
window.dataLayer.push({
  event: 'add_payment_info',
  ecommerce: {
    currency: 'EUR',
    value: 99.99,
    payment_type: 'Test Payment',
    items: [{
      item_id: 'TEST123',
      item_name: 'Test Product',
      quantity: 1,
      price: 99.99
    }]
  }
});
```

### Test 2: Prüfe dataLayer-Inhalt

```javascript
// Console:
window.dataLayer.filter(e => e.event === 'add_payment_info');
// Sollte alle add_payment_info Events anzeigen
```

### Test 3: Prüfe Jitsu-Requests

1. Öffne **DevTools** (F12)
2. Gehe zu **Network** Tab
3. Filtere nach `track`
4. Führe Checkout-Aktion aus
5. Prüfe Request zu `https://your-jitsu-server.com/api/s/s2s/track`
6. Klicke auf Request → **Payload** → Prüfe Event-Daten

## Event-Struktur

### add_payment_info

```json
{
  "event": "add_payment_info",
  "ecommerce": {
    "currency": "EUR",
    "value": 99.99,
    "payment_type": "Invoice",
    "items": [
      {
        "item_id": "SW10001",
        "item_name": "Product Name",
        "quantity": 1,
        "price": 99.99,
        "item_brand": "Brand",
        "item_category": "Category"
      }
    ]
  }
}
```

### add_shipping_info

```json
{
  "event": "add_shipping_info",
  "ecommerce": {
    "currency": "EUR",
    "value": 99.99,
    "shipping_tier": "Standard Shipping",
    "items": [
      {
        "item_id": "SW10001",
        "item_name": "Product Name",
        "quantity": 1,
        "price": 99.99
      }
    ]
  }
}
```

## Erweiterte Debugging-Optionen

### Option 1: Breakpoints setzen

1. Öffne DevTools → **Sources**
2. Suche nach `checkout_tracking.html.twig` (im `<script>` Tag)
3. Setze Breakpoint bei Zeile 77 (`function trackPaymentMethodChange()`)
4. Wähle Zahlungsart → Debugger stoppt
5. Prüfe Variablen: `selectedPayment`, `paymentMethodName`, `cartData`

### Option 2: Network-Monitor

```javascript
// Console: Überwache alle dataLayer.push Aufrufe
const originalPush = window.dataLayer.push;
window.dataLayer.push = function(...args) {
  console.log('📊 dataLayer.push:', args);
  return originalPush.apply(this, args);
};
```

### Option 3: Event-Listener testen

```javascript
// Console: Trigger event manuell
const paymentInput = document.querySelector('input[name="paymentMethodId"]:checked');
if (paymentInput) {
  paymentInput.dispatchEvent(new Event('change', { bubbles: true }));
}
```

## Checkliste

- [ ] Debug-Modus aktiviert
- [ ] Browser Console offen (F12)
- [ ] Auf Checkout-Seite (`/checkout/confirm`)
- [ ] "WSC Checkout Tracking: Initialization complete!" in Console
- [ ] Payment/Shipping inputs gefunden (Anzahl > 0)
- [ ] Cart-Daten im dataLayer vorhanden
- [ ] Event `add_payment_info` wird gepusht (bei Auswahl)
- [ ] Event `add_shipping_info` wird gepusht (bei Auswahl)
- [ ] Jitsu Bridge fängt Events ab (wenn aktiviert)
- [ ] Requests an Jitsu API sichtbar im Network Tab

## Support

Wenn du immer noch Probleme hast:

1. **Kopiere alle Console-Logs** aus dem Checkout-Prozess
2. **Mache einen Screenshot** der Network-Tab-Requests
3. **Exportiere den dataLayer**:
   ```javascript
   copy(JSON.stringify(window.dataLayer, null, 2));
   ```
4. Erstelle ein GitHub Issue mit diesen Informationen

## Weiterführende Dokumentation

- [JITSU_FRONTEND_TRACKING.md](./JITSU_FRONTEND_TRACKING.md) - Jitsu DataLayer Bridge
- [EVENTS_DOCUMENTATION.md](./EVENTS_DOCUMENTATION.md) - Alle Events
- [JITSU_EVENTS_MAPPING.md](./JITSU_EVENTS_MAPPING.md) - Jitsu Event-Mapping
