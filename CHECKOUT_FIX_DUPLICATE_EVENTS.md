# Fix: Doppelte add_payment_info und add_shipping_info Events

## Problem

Bei `begin_checkout` wurden automatisch auch `add_payment_info` und `add_shipping_info` Events mitgesendet, was zu folgenden Problemen führte:

1. **`add_shipping_info` wurde 2x gesendet**:
   - Einmal mit User-Daten
   - Einmal anonym

2. **`add_payment_info` wurde nur anonym gesendet**:
   - User-Daten fehlten komplett

## Ursache

Das Problem lag in der `checkout_tracking.html.twig` Implementierung:

### Vorher (FALSCH):
```javascript
// Auto-Tracking nach 500ms beim Seitenladen
setTimeout(function() {
    if (window.location.pathname.includes('/checkout/confirm')) {
        const selectedPayment = document.querySelector('input[name="paymentMethodId"]:checked');
        const selectedShipping = document.querySelector('input[name="shippingMethodId"]:checked');

        if (selectedPayment) {
            trackPaymentMethodChange(); // ❌ Feuert automatisch!
        }
        if (selectedShipping) {
            trackShippingMethodChange(); // ❌ Feuert automatisch!
        }
    }
}, 500);
```

**Warum das problematisch war:**
1. User lädt `/checkout/confirm` → `begin_checkout` Event wird gefeuert
2. Nach 500ms sucht das Script nach vorausgewählten Methoden
3. Es findet sie und feuert `add_payment_info` und `add_shipping_info` automatisch
4. → **Doppelte Events** direkt beim Seitenladen!

## Lösung

### 1. Auto-Tracking deaktiviert

```javascript
// IMPORTANT: DO NOT auto-track on page load!
// This would cause duplicate events when begin_checkout fires.
// Events should only be tracked when the user MANUALLY changes the selection.

if (window.__wscCheckoutDebug) {
    console.log('⚠️ WSC Checkout Tracking: Auto-tracking on page load is DISABLED');
    console.log('   Reason: Prevents duplicate events with begin_checkout');
    console.log('   Events will only fire when user manually changes payment/shipping method');
}
```

**Ergebnis:** Events werden nur noch bei **manuellen Änderungen** gefeuert! ✅

### 2. User-Daten aus dataLayer extrahiert

```javascript
// Vorher: getCartItems() - nur Cart-Daten
function getCartItems() {
    for (let i = dataLayer.length - 1; i >= 0; i--) {
        const entry = dataLayer[i];
        if (entry && entry.ecommerce && entry.ecommerce.items) {
            return {
                items: entry.ecommerce.items,
                value: entry.ecommerce.value || 0,
                currency: entry.ecommerce.currency || 'EUR'
            };
        }
    }
    return null;
}

// Nachher: getCartData() - Cart + User-Daten
function getCartData() {
    for (let i = dataLayer.length - 1; i >= 0; i--) {
        const entry = dataLayer[i];
        if (entry && entry.ecommerce && entry.ecommerce.items) {
            return {
                items: entry.ecommerce.items,
                value: entry.ecommerce.value || 0,
                currency: entry.ecommerce.currency || 'EUR',
                user: entry.user || {} // ✅ User-Daten hinzugefügt!
            };
        }
    }
    return null;
}
```

**Ergebnis:** Events enthalten jetzt User-Daten vom vorherigen `begin_checkout` Event! ✅

### 3. pushEvent() mit User-Daten

```javascript
// Vorher: pushEvent(eventName, payload)
function pushEvent(eventName, payload) {
    const eventPayload = Object.assign({ event: eventName }, payload);
    dataLayer.push({ ecommerce: null });
    dataLayer.push(eventPayload);
}

// Nachher: pushEvent(eventName, ecommerceData, userData)
function pushEvent(eventName, ecommerceData, userData) {
    const eventPayload = {
        event: eventName,
        ecommerce: ecommerceData,
        user: userData || {} // ✅ User-Daten separat!
    };
    dataLayer.push({ ecommerce: null });
    dataLayer.push(eventPayload);
}
```

**Ergebnis:** User-Daten werden korrekt in das Event-Objekt eingefügt! ✅

### 4. Jitsu Bridge mit User-Daten

Die Jitsu Bridge extrahiert jetzt User-Daten aus den dataLayer Events:

```javascript
// Add user data from dataLayer event if available
if (eventData.user && Object.keys(eventData.user).length > 0) {
    payload.traits = {
        email: eventData.user.user_email || '',
        country: eventData.user.user_country || '',
        city: eventData.user.user_city || ''
    };

    // If user email exists, use it as userId
    if (eventData.user.user_email) {
        payload.userId = eventData.user.user_email;
    }

    if (window.__wscJitsuDebug) {
        console.log('🔧 Jitsu: Added user data to payload', payload.traits);
    }
}
```

**Ergebnis:** Jitsu erhält jetzt User-Daten (userId, traits) statt nur anonymousId! ✅

## Verhalten nach dem Fix

### Beim begin_checkout
```
User öffnet /checkout/confirm
        ↓
begin_checkout Event wird gefeuert (vom Backend)
        ↓
dataLayer enthält jetzt: { event: 'begin_checkout', ecommerce: {...}, user: {...} }
        ↓
checkout_tracking.html.twig Script lädt
        ↓
Registriert Change-Listener
        ↓
⚠️ KEIN Auto-Tracking! Keine add_payment_info/add_shipping_info Events!
```

### Beim Ändern der Zahlungsart
```
User wählt andere Zahlungsart
        ↓
Change-Event auf input[name="paymentMethodId"]
        ↓
trackPaymentMethodChange() wird aufgerufen
        ↓
getCartData() holt Cart + User-Daten aus dataLayer
        ↓
pushEvent('add_payment_info', { currency, value, payment_type, items }, userData)
        ↓
Event mit User-Daten wird gepusht! ✅
        ↓
    ┌───┴───┬─────┐
    ↓       ↓     ↓
  GTM/GA4  Matomo  Jitsu (mit userId!)
```

## Event-Struktur nach dem Fix

### add_payment_info mit User-Daten

```json
{
  "event": "add_payment_info",
  "ecommerce": {
    "currency": "EUR",
    "value": 99.99,
    "payment_type": "Invoice",
    "items": [...]
  },
  "user": {
    "user_email": "customer@example.com",
    "user_country": "Germany",
    "user_city": "Berlin"
  }
}
```

### Jitsu Payload mit User-Daten

```json
{
  "event": "add_payment_info",
  "properties": {
    "currency": "EUR",
    "value": 99.99,
    "payment_type": "Invoice",
    "items": [...]
  },
  "userId": "customer@example.com",
  "anonymousId": "anon_xyz123",
  "traits": {
    "email": "customer@example.com",
    "country": "Germany",
    "city": "Berlin"
  },
  "context": {...}
}
```

## Testing

### Test 1: begin_checkout - KEINE automatischen Events

1. Öffne `/checkout/confirm`
2. Öffne Browser Console (F12)
3. Prüfe Console-Logs:

```
✅ Erwartete Logs:
🔧 WSC Checkout Tracking: Initializing on checkout page...
🔧 WSC Checkout Tracking: Registering event listeners...
⚠️ WSC Checkout Tracking: Auto-tracking on page load is DISABLED
   Reason: Prevents duplicate events with begin_checkout

❌ NICHT erwartet:
📤 WSC Checkout Tracking: Pushing event: add_payment_info
📤 WSC Checkout Tracking: Pushing event: add_shipping_info
```

### Test 2: Manuelle Änderung - Event mit User-Daten

1. Bleibe auf `/checkout/confirm`
2. **Wähle eine andere Zahlungsart**
3. Prüfe Console:

```
✅ Erwartete Logs:
🔧 WSC Checkout Tracking: Payment method change event detected
🔧 WSC Checkout Tracking: trackPaymentMethodChange() called
🔧 WSC Checkout Tracking: Found selected payment: <input...>
🔧 WSC Checkout Tracking: Payment method changed to: Credit Card
🔧 WSC Checkout Tracking: Getting cart data from dataLayer...
🔧 WSC Checkout Tracking: Found cart data at index 2 {
    items: [...],
    value: 99.99,
    currency: "EUR",
    user: {
        user_email: "customer@example.com",
        user_country: "Germany",
        user_city: "Berlin"
    }
}
📤 WSC Checkout Tracking: Pushing event: add_payment_info {
    event: "add_payment_info",
    ecommerce: {...},
    user: {
        user_email: "customer@example.com",
        user_country: "Germany",
        user_city: "Berlin"
    }
}
✅ Pushed to window.dataLayer
✅ Pushed to window._mtm

🔍 Jitsu: Processing dataLayer event { event: 'add_payment_info', ... }
🔧 Jitsu: Added user data to payload {
    email: "customer@example.com",
    country: "Germany",
    city: "Berlin"
}
📤 Jitsu: Sending event {
    userId: "customer@example.com",
    traits: {...}
}
✅ Jitsu: Event sent successfully
```

### Test 3: Jitsu Payload prüfen

1. Öffne DevTools → Network Tab
2. Filtere nach `track`
3. Wähle Zahlungsart
4. Klicke auf Request zu `https://jitsu-server.com/api/s/s2s/track`
5. Prüfe **Payload**:

```json
{
  "event": "add_payment_info",
  "userId": "customer@example.com",  // ✅ User-ID vorhanden!
  "anonymousId": "anon_xyz123",
  "traits": {                         // ✅ User-Traits vorhanden!
    "email": "customer@example.com",
    "country": "Germany",
    "city": "Berlin"
  },
  "properties": {
    "currency": "EUR",
    "value": 99.99,
    "payment_type": "Credit Card",
    "items": [...]
  }
}
```

## Betroffene Dateien

1. **`checkout_tracking.html.twig`**
   - ✅ Auto-Tracking deaktiviert
   - ✅ `getCartData()` extrahiert User-Daten
   - ✅ `pushEvent()` inkludiert User-Daten

2. **`JitsuDataLayerBridge.html.twig`**
   - ✅ User-Daten werden in `userId` und `traits` übertragen

## Checkliste

- [x] Auto-Tracking auf `/checkout/confirm` deaktiviert
- [x] User-Daten aus dataLayer extrahiert
- [x] `pushEvent()` überträgt User-Daten
- [x] Jitsu Bridge nutzt User-Daten für `userId` und `traits`
- [x] Events werden nur bei manuellen Änderungen gefeuert
- [x] Keine doppelten Events mehr bei `begin_checkout`

## Migration

Keine Breaking Changes! Die Änderungen sind abwärtskompatibel:

- ✅ Alte Events ohne User-Daten funktionieren weiterhin
- ✅ Neue Events mit User-Daten werden automatisch erkannt
- ✅ Jitsu sendet `anonymousId` als Fallback, wenn keine User-Daten vorhanden

## Bekannte Edge Cases

### Gast-Checkout (keine User-Daten)

```javascript
// Wenn user leer ist:
user: {}

// Jitsu Payload:
{
  "anonymousId": "anon_xyz123",  // ✅ Fallback funktioniert
  "userId": undefined,            // OK - wird nicht gesendet
  "traits": {}                    // OK - leer
}
```

### User-Daten nur teilweise vorhanden

```javascript
// Wenn nur Email vorhanden:
user: {
  user_email: "customer@example.com"
}

// Jitsu Payload:
{
  "userId": "customer@example.com",
  "traits": {
    "email": "customer@example.com",
    "country": "",   // Leer, aber OK
    "city": ""       // Leer, aber OK
  }
}
```

## Support

Wenn du Probleme hast:

1. **Aktiviere Debug-Modus** (`wscTagManagerDataLayerDebug` = true)
2. **Prüfe Console-Logs** - sollten zeigen ob User-Daten gefunden wurden
3. **Prüfe dataLayer**:
   ```javascript
   console.log(window.dataLayer.filter(e => e.event === 'begin_checkout'));
   ```
4. **Prüfe Jitsu Network Requests** - sollten `userId` und `traits` enthalten
