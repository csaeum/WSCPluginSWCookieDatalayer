# Fix: User-Daten in ALLEN Events (Frontend + Jitsu)

## Problem

Mehrere Frontend-Events wurden **ohne User-Daten** gesendet, obwohl der User eingeloggt war. In Jitsu erschienen diese Events nur mit `anonymousId` statt mit `userId` und `traits`.

**Betroffene Events:**
- `select_item` ❌ (nur anonymousId)
- `add_to_cart` ❌
- `remove_from_cart` ❌
- `add_to_wishlist` ❌
- `view_search_results` ❌
- `view_promotion` ❌
- `select_promotion` ❌
- `generate_lead` ❌
- `add_to_compare` ❌
- `add_payment_info` ❌ (behoben in vorherigem Fix)
- `add_shipping_info` ❌ (behoben in vorherigem Fix)

## Ursache

Frontend-Events (JavaScript) hatten keinen direkten Zugriff auf den Shopware-Session-Context. Sie pushten Events ohne `user`-Objekt, was dazu führte, dass Jitsu nur `anonymousId` setzte.

### Vorher (FALSCH):
```javascript
// add_to_cart.html.twig
function pushEvent(eventName, ecommerceData) {
    const eventData = {
        event: eventName,
        ecommerce: ecommerceData
        // ❌ KEIN user-Objekt!
    };
    dataLayer.push(eventData);
}
```

**Resultat in Jitsu:**
```json
{
  "event": "select_item",
  "anonymousId": "anon_xyz123",
  // ❌ KEIN userId!
  // ❌ KEINE traits!
}
```

## Lösung

Alle Frontend-Events extrahieren jetzt User-Daten aus vorherigen dataLayer-Einträgen (z.B. von `view_item`, `begin_checkout` etc., die vom Backend mit User-Daten gepusht wurden).

### Implementierung

**1. Zentrale `getUserData()` Funktion** (in jedem Template):
```javascript
/**
 * Get user data from existing dataLayer entries
 */
function getUserData() {
    if (window.__wscDebugMode) console.log('🔧 Getting user data from dataLayer...');

    for (let i = dataLayer.length - 1; i >= 0; i--) {
        const entry = dataLayer[i];
        if (entry && entry.user && Object.keys(entry.user).length > 0) {
            if (window.__wscDebugMode) {
                console.log('🔧 Found user data at index', i, entry.user);
            }
            return entry.user;
        }
    }

    if (window.__wscDebugMode) console.log('🔧 No user data found (guest user)');
    return {}; // Empty object for guest users
}
```

**2. Erweiterte `pushEvent()` Funktionen**:
```javascript
// Beispiel: add_to_cart.html.twig
function pushEvent(eventName, ecommerceData) {
    const userData = getUserData(); // ✅ User-Daten holen!

    const eventData = {
        event: eventName,
        ecommerce: ecommerceData,
        user: userData // ✅ User-Daten hinzufügen!
    };

    if (window.__wscDebugMode) {
        console.log('📤 Pushing event:', eventName, eventData);
    }

    dataLayer.push({ ecommerce: null });
    dataLayer.push(eventData);
}
```

**3. Jitsu Bridge nutzt User-Daten** (bereits in vorherigem Fix implementiert):
```javascript
// JitsuDataLayerBridge.html.twig
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
}
```

## Geänderte Dateien

Alle folgenden Templates wurden erweitert mit `getUserData()` und User-Daten im `pushEvent()`:

### 1. ✅ `add_to_cart.html.twig`
**Events:** `add_to_cart`, `remove_from_cart`, `add_to_wishlist`, `select_item`
- Zeile 92-110: `getUserData()` hinzugefügt
- Zeile 115-138: `pushEvent()` erweitert mit `user: userData`

### 2. ✅ `search_tracking.html.twig`
**Events:** `view_search_results`
- Zeile 23-41: `getUserData()` hinzugefügt
- Zeile 47-73: `pushSearchEvent()` erweitert mit `user: userData`

### 3. ✅ `promotion_tracking.html.twig`
**Events:** `view_promotion`, `select_promotion`
- Zeile 21-39: `getUserData()` hinzugefügt
- Zeile 175-216: `pushPromotionEvent()` erweitert mit `user: userData`

### 4. ✅ `generate_lead.html.twig`
**Events:** `generate_lead`
- Zeile 23-41: `getUserData()` hinzugefügt
- Zeile 46-73: `pushLeadEvent()` erweitert mit `user: userData`

### 5. ✅ `add_to_compare.html.twig`
**Events:** `add_to_compare`
- Zeile 26-44: `getUserData()` hinzugefügt
- Zeile 96-122: `pushEvent()` erweitert mit `user: userData`

### 6. ✅ `checkout_tracking.html.twig` (bereits in vorherigem Fix)
**Events:** `add_payment_info`, `add_shipping_info`
- Zeile 32-52: `getCartData()` mit User-Daten
- Zeile 55-77: `pushEvent()` mit User-Parameter

### 7. ✅ `JitsuDataLayerBridge.html.twig` (bereits in vorherigem Fix)
**Alle Events → Jitsu**
- Zeile 180-196: User-Daten → `userId` und `traits`

## Event-Flow nach dem Fix

```
User lädt Seite (z.B. Produktdetailseite)
        ↓
Backend pusht view_item Event MIT User-Daten:
{
  event: 'view_item',
  ecommerce: {...},
  user: {
    user_email: 'customer@example.com',
    user_country: 'Germany',
    user_city: 'Berlin'
  }
}
        ↓
User klickt Produkt in Listing (select_item)
        ↓
Frontend: getUserData() sucht User-Daten im dataLayer
        ↓
✅ Findet user-Objekt vom view_item Event!
        ↓
select_item Event MIT User-Daten gepusht:
{
  event: 'select_item',
  ecommerce: {...},
  user: {
    user_email: 'customer@example.com',
    user_country: 'Germany',
    user_city: 'Berlin'
  }
}
        ↓
Jitsu Bridge fängt Event ab
        ↓
Extrahiert User-Daten
        ↓
Sendet an Jitsu MIT userId:
{
  event: 'select_item',
  userId: 'customer@example.com',
  traits: {
    email: 'customer@example.com',
    country: 'Germany',
    city: 'Berlin'
  },
  anonymousId: 'anon_xyz123',
  properties: {...}
}
```

## Vorher vs. Nachher

### Vorher ❌

**dataLayer:**
```javascript
{
  event: 'select_item',
  ecommerce: {
    items: [...]
  }
  // KEIN user-Objekt!
}
```

**Jitsu:**
```json
{
  "event": "select_item",
  "anonymousId": "anon_xyz123"
  // KEIN userId!
  // KEINE traits!
}
```

### Nachher ✅

**dataLayer:**
```javascript
{
  event: 'select_item',
  ecommerce: {
    items: [...]
  },
  user: {  // ✅ User-Daten vorhanden!
    user_email: 'customer@example.com',
    user_country: 'Germany',
    user_city: 'Berlin'
  }
}
```

**Jitsu:**
```json
{
  "event": "select_item",
  "userId": "customer@example.com",  // ✅ User identifiziert!
  "anonymousId": "anon_xyz123",
  "traits": {                          // ✅ User-Informationen!
    "email": "customer@example.com",
    "country": "Germany",
    "city": "Berlin"
  },
  "properties": {...}
}
```

## Testing

### Schritt 1: Debug-Modus aktivieren
```
Shopware Admin → WSC Cookie DataLayer → wscTagManagerDataLayerDebug = true
```

### Schritt 2: Als eingeloggter User testen

1. **Logge dich ein** im Shop
2. **Öffne Browser Console** (F12)
3. **Navigiere zu einem Produkt** (oder Listing)
4. **Führe eine Aktion aus** (z.B. Produkt klicken)

**Erwartete Logs:**
```
🔧 WSC Cart: Getting user data from dataLayer...
🔧 WSC Cart: Found user data at index 2 {
    user_email: "customer@example.com",
    user_country: "Germany",
    user_city: "Berlin"
}
📤 WSC Cart: Pushing event: select_item {
    event: "select_item",
    ecommerce: {...},
    user: {
        user_email: "customer@example.com",
        user_country: "Germany",
        user_city: "Berlin"
    }
}
✅ Pushed to window.dataLayer

🔍 Jitsu: Processing dataLayer event { event: 'select_item', ... }
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

### Schritt 3: Jitsu Dashboard prüfen

1. Öffne **Jitsu Dashboard** → **Events** oder **Debug**
2. Suche nach dem Event (z.B. `select_item`)
3. Prüfe Payload:

```json
{
  "event": "select_item",
  "userId": "customer@example.com",  // ✅ Sollte gefüllt sein!
  "traits": {                         // ✅ Sollte gefüllt sein!
    "email": "customer@example.com",
    "country": "Germany",
    "city": "Berlin"
  },
  "anonymousId": "anon_xyz123"
}
```

### Schritt 4: Als Gast testen

1. **Logge dich aus**
2. **Wiederhole die Aktion**

**Erwartete Logs:**
```
🔧 WSC Cart: Getting user data from dataLayer...
🔧 WSC Cart: No user data found in dataLayer (guest user or not logged in)
📤 WSC Cart: Pushing event: select_item {
    event: "select_item",
    ecommerce: {...},
    user: {}  // ✅ Leeres Objekt für Gäste
}
```

**Jitsu:**
```json
{
  "event": "select_item",
  "anonymousId": "anon_xyz123",  // ✅ Nur anonymousId für Gäste
  // KEIN userId (korrekt!)
  // KEINE traits (korrekt!)
}
```

## Alle Events mit User-Daten

### Frontend-Events (JavaScript)
✅ `select_item` (add_to_cart.html.twig)
✅ `add_to_cart` (add_to_cart.html.twig)
✅ `remove_from_cart` (add_to_cart.html.twig)
✅ `add_to_wishlist` (add_to_cart.html.twig)
✅ `view_search_results` (search_tracking.html.twig)
✅ `view_promotion` (promotion_tracking.html.twig)
✅ `select_promotion` (promotion_tracking.html.twig)
✅ `generate_lead` (generate_lead.html.twig)
✅ `add_to_compare` (add_to_compare.html.twig)
✅ `add_payment_info` (checkout_tracking.html.twig)
✅ `add_shipping_info` (checkout_tracking.html.twig)

### Backend-Events (PHP - bereits mit User-Daten)
✅ `view_item` (DataLayerSubscriber.php)
✅ `view_item_list` (DataLayerSubscriber.php)
✅ `begin_checkout` (DataLayerSubscriber.php)
✅ `purchase` (DataLayerSubscriber.php)
✅ `login` (DataLayerSubscriber.php)
✅ `sign_up` (DataLayerSubscriber.php)

## Wichtige Hinweise

### 1. User-Daten stammen aus vorherigen Events
Frontend-Events können User-Daten nur aus **vorherigen dataLayer-Einträgen** extrahieren. Das bedeutet:
- ✅ Wenn der User eine Seite lädt, pusht das Backend ein Event mit User-Daten (z.B. `view_item`)
- ✅ Alle nachfolgenden Frontend-Events nutzen diese User-Daten
- ⚠️ Wenn KEIN Backend-Event geladen wurde, hat das Frontend KEINE User-Daten

**Lösung:** Stelle sicher, dass auf jeder Seite ein Backend-Event gepusht wird (view_item, view_item_list, begin_checkout, etc.)

### 2. Gäste vs. Eingeloggte User
- **Eingeloggt:** `user` enthält Email, Land, Stadt
- **Gast:** `user` ist ein leeres Objekt `{}`
- **Jitsu:** Nutzt `anonymousId` für Gäste, `userId` für eingeloggte User

### 3. GDPR & Datenschutz
User-Daten (Email, Land, Stadt) werden an Jitsu gesendet. Stelle sicher:
- ✅ User haben Consent gegeben (Cookie Consent)
- ✅ Jitsu Consent Mode ist aktiviert
- ✅ Datenschutzerklärung erwähnt Jitsu-Tracking

## Checkliste

- [x] `getUserData()` in allen Frontend-Event-Templates hinzugefügt
- [x] Alle `pushEvent()` Funktionen erweitert mit `user: userData`
- [x] Jitsu Bridge nutzt User-Daten für `userId` und `traits`
- [x] Debug-Logs für User-Daten-Extraktion
- [x] Fallback für Gäste (leeres `user`-Objekt)
- [x] Testing mit eingeloggtem User
- [x] Testing mit Gast
- [x] Dokumentation erstellt

## Support

Wenn Events immer noch keine User-Daten haben:

1. **Prüfe ob Backend-Events geladen werden:**
   ```javascript
   console.log(window.dataLayer.filter(e => e.user));
   ```
   Sollte mindestens ein Event mit `user`-Objekt zeigen.

2. **Prüfe Reihenfolge:**
   Backend-Events (mit User-Daten) müssen VOR Frontend-Events laden.

3. **Prüfe Jitsu Network Request:**
   DevTools → Network → Filter `track` → Payload prüfen

4. **Aktiviere Debug-Modus** für detaillierte Logs
