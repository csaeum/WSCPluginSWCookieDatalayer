# GitHub Issues - WSC Cookie Consent + DataLayer Plugin

Vollständige Issue-Liste priorisiert für Release 1.0

---

## 🔴 KRITISCH - Sofort beheben (P0)

### Issue #1: DataLayerSubscriber testen und debuggen

**Labels:** `bug`, `critical`, `subscriber`, `testing`

**Beschreibung:**

Der DataLayerSubscriber ist implementiert, wurde aber noch nicht ausreichend getestet und funktioniert möglicherweise nicht korrekt in allen Szenarien.

**Probleme:**
- Events werden möglicherweise nicht immer gefeuert
- `wscDataLayerEvent` könnte in manchen Templates fehlen
- Keine Fehlerbehandlung bei fehlenden Daten
- Kein Logging für Debugging

**Aufgaben:**
- [ ] Debug-Logging im Subscriber hinzufügen
- [ ] Prüfen ob alle Events korrekt subscribed sind
- [ ] Testen ob `wscDataLayerEvent` in allen relevanten Templates ankommt
- [ ] Null-Checks in DataLayerBuilder verbessern
- [ ] Error-Handling für fehlende Produkt-Daten
- [ ] Unit Tests für DataLayerSubscriber schreiben
- [ ] Integration Tests für alle Events (view_item, view_cart, purchase, etc.)
- [ ] Browser-Tests: Prüfen ob DataLayer korrekt gefüllt wird

**Akzeptanzkriterien:**
- Alle E-Commerce Events werden korrekt in `window.dataLayer` gepusht
- Browser Console zeigt Debug-Logs für alle Events
- Keine JavaScript-Fehler in der Console
- GTM Preview Mode zeigt alle Events

**Dateien:**
- `src/Subscriber/DataLayerSubscriber.php:20`
- `src/Service/DataLayerBuilder.php`
- `src/Resources/views/storefront/wscTagManager/DataLayer.html.twig`

**Priorität:** P0 - KRITISCH
**Geschätzter Aufwand:** 1-2 Tage

---

### Issue #2: add_payment_info und add_shipping_info Events implementieren

**Labels:** `enhancement`, `critical`, `tracking`, `e-commerce`

**Beschreibung:**

Die Templates für `add_payment_info` und `add_shipping_info` existieren, aber die Events werden nicht im Subscriber gefeuert.

**Fehlende Funktionalität:**
- Kein Event wenn User Zahlungsart auswählt
- Kein Event wenn User Versandart auswählt
- Templates sind vorhanden aber nicht eingebunden

**Aufgaben:**
- [ ] Event Subscriber für Zahlungsart-Änderung hinzufügen
- [ ] Event Subscriber für Versandart-Änderung hinzufügen
- [ ] DataLayerBuilder Methoden implementieren:
  - `buildAddPaymentInfoData()`
  - `buildAddShippingInfoData()`
- [ ] Templates in Checkout-Flow einbinden
- [ ] JavaScript Event Listener für AJAX-Updates
- [ ] Testen in verschiedenen Checkout-Szenarien

**Shopware Events zu subscriben:**
- `CheckoutConfirmPageLoadedEvent` (Zahlungsart)
- Oder: Custom JavaScript Event Listener im Checkout

**Dateien:**
- `src/Subscriber/DataLayerSubscriber.php` (erweitern)
- `src/Service/DataLayerBuilder.php` (neue Methoden)
- `src/Resources/views/storefront/wscTagManager/DataLayer/add_payment_info.html.twig`
- `src/Resources/views/storefront/wscTagManager/DataLayer/add_shipping_info.html.twig`

**Priorität:** P0 - KRITISCH
**Geschätzter Aufwand:** 0.5-1 Tag

---

## 🟠 HOCH - Vor Release 1.0 (P1)

### Issue #3: Cookie Consent GUI-Optionen erweitern

**Labels:** `enhancement`, `high-priority`, `cookie-consent`, `ux`

**Beschreibung:**

Der OrestBida Cookie Consent v3 bietet viele GUI-Anpassungsoptionen, die aktuell hardcodiert sind. Diese sollten über die Plugin-Config konfigurierbar sein.

**Aktuell hardcodiert in `CookieConsentConfig.html.twig:42-55`:**
```javascript
guiOptions: {
    consentModal: {
        layout: 'box inline',           // hardcodiert
        position: 'bottom center',      // hardcodiert
        equalWeightButtons: true,       // hardcodiert
        flipButtons: false              // hardcodiert
    },
    preferencesModal: {
        layout: 'box',                  // hardcodiert
        position: 'right',              // hardcodiert
        equalWeightButtons: true,       // hardcodiert
        flipButtons: false              // hardcodiert
    }
}
```

**Fehlende Config-Optionen (laut https://playground.cookieconsent.orestbida.com/):**

#### Consent Modal
- **Layout:** box, cloud, bar, wide, box inline (aktuell: box inline)
- **Position:** top left, top center, top right, middle left, middle center, middle right, bottom left, bottom center, bottom right (aktuell: bottom center)
- **Optionen:**
  - Equal weight buttons (aktuell: true)
  - Flip buttons (aktuell: false)

#### Preferences Modal
- **Layout:** box, bar, wide (aktuell: box)
- **Position:** left, right (aktuell: right)
- **Optionen:**
  - Equal weight buttons (aktuell: true)
  - Flip buttons (aktuell: false)

#### Allgemeine Optionen
- **Dark Mode:** aktivieren/deaktivieren
- **Disable page interaction:** Seite während Modal blockieren
- **Disable transitions:** CSS-Übergänge deaktivieren
- **Hide from bots:** Von Bots verstecken (bereits implementiert)

#### Theme/Farben (Custom CSS)
- Primary Color
- Secondary Color
- Button Background
- Button Text Color
- Modal Background
- Text Color

**Aufgaben:**
- [ ] Neue Config-Felder in `config.xml` hinzufügen:
  - `wscCookieConsentModalLayout` (single-select)
  - `wscCookieConsentModalPosition` (single-select)
  - `wscCookieConsentModalEqualButtons` (bool)
  - `wscCookieConsentModalFlipButtons` (bool)
  - `wscCookieConsentPrefsLayout` (single-select)
  - `wscCookieConsentPrefsPosition` (single-select)
  - `wscCookieConsentPrefsEqualButtons` (bool)
  - `wscCookieConsentPrefsFlipButtons` (bool)
  - `wscCookieConsentDarkMode` (bool)
  - `wscCookieConsentDisablePageInteraction` (bool)
  - `wscCookieConsentDisableTransitions` (bool)
  - `wscCookieConsentPrimaryColor` (colorpicker - optional)
  - `wscCookieConsentSecondaryColor` (colorpicker - optional)
- [ ] `CookieConsentConfig.html.twig` anpassen: Twig-Variablen statt hardcodierte Werte
- [ ] Dokumentation in README.md aktualisieren
- [ ] Screenshots für verschiedene Layout-Optionen

**Dateien:**
- `src/Resources/config/config.xml:6-68` (erweitern)
- `src/Resources/views/storefront/wscTagManager/CookieConsent/CookieConsentConfig.html.twig:42-55`

**Priorität:** P1 - HOCH
**Geschätzter Aufwand:** 1-2 Tage

---

### Issue #4: DataLayer Validierung und umfassendes Error-Handling

**Labels:** `enhancement`, `quality`, `stability`, `error-handling`

**Beschreibung:**

Aktuell fehlt robuste Fehlerbehandlung im DataLayerBuilder. Bei fehlenden oder falschen Daten können Exceptions auftreten.

**Probleme:**
- Keine Validierung von Input-Daten
- Fehlende Null-Checks
- Keine Fehler-Logs bei ungültigen Daten
- Try-Catch fehlt in kritischen Bereichen

**Aufgaben:**
- [ ] Input-Validierung in allen DataLayerBuilder-Methoden
- [ ] Null-Checks für alle Optional-Felder
- [ ] Try-Catch Blocks mit Logging
- [ ] Fallback-Werte bei fehlenden Daten
- [ ] PSR-3 Logger Integration
- [ ] Admin-Benachrichtigung bei kritischen Fehlern
- [ ] Schema-Validierung für DataLayer-Events (JSON Schema)

**Beispiel Error-Handling:**
```php
public function buildViewItemData(...): array
{
    try {
        // Validate input
        if (!$product->getProductNumber()) {
            $this->logger->warning('Product has no product number', [
                'productId' => $product->getId()
            ]);
            return $this->buildEmptyEvent('view_item');
        }

        // Build data with null checks
        $items = [[
            'item_id' => $product->getProductNumber(),
            'item_name' => $product->getTranslated()['name'] ?? 'Unknown Product',
            // ...
        ]];

        return ['event' => 'view_item', 'ecommerce' => ['items' => $items]];

    } catch (\Exception $e) {
        $this->logger->error('Failed to build view_item data', [
            'error' => $e->getMessage(),
            'productId' => $product->getId()
        ]);
        return $this->buildEmptyEvent('view_item');
    }
}
```

**Dateien:**
- `src/Service/DataLayerBuilder.php` (alle Methoden)
- `src/Subscriber/DataLayerSubscriber.php`

**Priorität:** P1 - HOCH
**Geschätzter Aufwand:** 1 Tag

---

### Issue #5: Unit Tests und Integration Tests implementieren

**Labels:** `testing`, `quality`, `ci-cd`

**Beschreibung:**

Keine Tests vorhanden. Für einen stabilen Release 1.0 sind Tests essentiell.

**Test-Kategorien:**

#### Unit Tests
- [ ] DataLayerBuilder Tests
  - `buildViewItemData()` mit gültigen Daten
  - `buildViewItemData()` mit fehlenden Daten
  - `buildViewCartData()` mit leerem Cart
  - `buildPurchaseData()` mit kompletter Order
  - Alle anderen Builder-Methoden
- [ ] DataLayerSubscriber Tests (Mocking)
  - Events werden korrekt subscribed
  - Event Handler rufen Builder auf
  - Page-Variables werden korrekt gesetzt

#### Integration Tests
- [ ] Subscriber Integration Tests
  - ProductPageLoaded Event feuert korrekt
  - CheckoutFinishPageLoaded Event feuert korrekt
  - DataLayer wird in Template verfügbar gemacht
- [ ] E2E Browser Tests (optional, aber empfohlen)
  - Cookie Consent Banner erscheint
  - DataLayer wird gefüllt
  - GTM empfängt Events

**Technologie:**
- PHPUnit (bereits in Shopware integriert)
- Shopware TestCase
- Optional: Cypress/Playwright für E2E

**Aufgaben:**
- [ ] PHPUnit einrichten
- [ ] Test-Fixtures erstellen (Mock-Produkte, Orders, etc.)
- [ ] Unit Tests schreiben
- [ ] Integration Tests schreiben
- [ ] Code Coverage Report (min. 80%)
- [ ] Tests in CI/CD Pipeline integrieren

**Dateien:**
- `tests/Unit/Service/DataLayerBuilderTest.php` (neu)
- `tests/Unit/Subscriber/DataLayerSubscriberTest.php` (neu)
- `tests/Integration/DataLayerIntegrationTest.php` (neu)
- `phpunit.xml.dist` (neu)

**Priorität:** P1 - HOCH
**Geschätzter Aufwand:** 2-3 Tage

---

### Issue #6: Frontend Assets Build-Prozess einrichten

**Labels:** `frontend`, `build`, `webpack`, `optimization`

**Beschreibung:**

JavaScript und CSS werden aktuell nicht kompiliert/minifiziert. Für Production sollte ein Build-Prozess vorhanden sein.

**Aktueller Stand:**
- JavaScript-Plugins direkt als ES6
- Keine Minifizierung
- Keine Bundling
- Keine Code-Splitting

**Aufgaben:**
- [ ] Webpack/Vite konfigurieren
- [ ] Build-Script in `package.json`
- [ ] JavaScript kompilieren und minifizieren
- [ ] CSS kompilieren (SCSS optional)
- [ ] Source Maps für Development
- [ ] Watch-Mode für Development
- [ ] Production-Build optimieren
- [ ] Asset-Versionierung (Cache-Busting)

**Dateien:**
- `package.json` (neu)
- `webpack.config.js` (neu)
- `src/Resources/app/storefront/build/` (neu)
- `src/Resources/app/storefront/src/` (bestehende Plugins)

**Priorität:** P1 - HOCH
**Geschätzter Aufwand:** 1 Tag

---

## 🟡 MITTEL - Nice-to-have für 1.0 (P2)

### Issue #7: GitHub Actions CI/CD Pipeline

**Labels:** `ci-cd`, `automation`, `quality`, `github-actions`

**Beschreibung:**

Automatisierte Pipeline für Tests, Code-Style Checks und Deployment.

**Pipeline-Stages:**

#### Stage 1: Code Quality
- [ ] PHP-CS-Fixer (PSR-12 Compliance)
- [ ] PHPStan (Static Analysis)
- [ ] ESLint (JavaScript)
- [ ] Prettier (Code Formatting)

#### Stage 2: Tests
- [ ] PHPUnit Unit Tests
- [ ] PHPUnit Integration Tests
- [ ] Code Coverage Report
- [ ] Minimum Coverage: 80%

#### Stage 3: Build
- [ ] Frontend Assets Build
- [ ] Validierung: composer.json, config.xml
- [ ] Plugin-Struktur Check

#### Stage 4: Release (optional)
- [ ] Automatische Versionierung
- [ ] GitHub Release erstellen
- [ ] ZIP-Datei für Shopware Store

**Dateien:**
- `.github/workflows/ci.yml` (neu)
- `.github/workflows/release.yml` (neu)
- `.php-cs-fixer.php` (neu)
- `phpstan.neon` (neu)
- `.eslintrc.js` (neu)

**Priorität:** P2 - MITTEL
**Geschätzter Aufwand:** 1-2 Tage

---

### Issue #8: Performance-Optimierung - DataLayer Conditional Loading

**Labels:** `performance`, `optimization`

**Beschreibung:**

DataLayer wird aktuell auf jeder Seite geladen, auch wenn kein Tracking aktiv ist.

**Probleme:**
- `meta.html.twig` lädt JavaScript auf allen Seiten
- DataLayer-Code wird immer ausgeführt
- Subscriber feuern immer, auch wenn DataLayer deaktiviert

**Optimierungen:**
- [ ] Lazy Loading für DataLayer-Scripts
- [ ] Conditional Subscriber-Registration (nur wenn DataLayer aktiv)
- [ ] DataLayer nur auf relevanten Seiten laden
- [ ] Cache für DataLayer-Events (SSR)
- [ ] Debouncing für häufige Events

**Performance-Gewinn:**
- ~10-20KB weniger JavaScript auf Seiten ohne Tracking
- Schnellere Seitenladezeit
- Reduzierte CPU-Last

**Dateien:**
- `src/Subscriber/DataLayerSubscriber.php` (Conditional Registration)
- `src/Resources/views/storefront/layout/meta.html.twig`
- `src/WscSwCookieDataLayer.php` (Service Registration)

**Priorität:** P2 - MITTEL
**Geschätzter Aufwand:** 0.5-1 Tag

---

### Issue #9: Admin UI/UX Verbesserungen

**Labels:** `admin`, `ux`, `enhancement`

**Beschreibung:**

Plugin-Config ist funktional, aber UX könnte verbessert werden.

**Verbesserungen:**
- [ ] Gruppierung der Config-Felder verbessern
- [ ] Hilfe-Texte überarbeiten (klarer formulieren)
- [ ] Inline-Hilfe mit Beispielen
- [ ] Preview-Funktion für Cookie Banner
- [ ] Live-Validation für GTM/GA4 IDs
- [ ] Link zu GTM/Matomo Dashboard
- [ ] Setup-Wizard für First-Time Setup
- [ ] Video-Tutorials einbinden

**Mockup:**
```
┌─────────────────────────────────────┐
│ Cookie Consent Einstellungen        │
├─────────────────────────────────────┤
│ ☑ Aktivieren                        │
│ Modus: [Opt-in ▼]                   │
│                                     │
│ [👁️ Vorschau anzeigen]              │
│                                     │
│ Erweiterte Optionen ▼               │
│   Layout: [Box Inline ▼]            │
│   Position: [Bottom Center ▼]       │
│   ...                               │
└─────────────────────────────────────┘
```

**Dateien:**
- `src/Resources/config/config.xml`
- `src/Resources/views/administration/` (neu - Custom Admin Component)

**Priorität:** P2 - MITTEL
**Geschätzter Aufwand:** 2-3 Tage

---

### Issue #10: Erweiterte DataLayer-Events für User-Interaktionen

**Labels:** `enhancement`, `tracking`, `feature`

**Beschreibung:**

Zusätzliche Events für besseres User-Verhalten-Tracking.

**Neue Events:**
- [ ] `view_search_results` - Suchergebnisse angezeigt
- [ ] `select_item` - Produkt aus Liste angeklickt
- [ ] `view_promotion` - Promotion/Banner angezeigt
- [ ] `select_promotion` - Promotion angeklickt
- [ ] `login` - User Login (bereits Template vorhanden)
- [ ] `sign_up` - User Registrierung (bereits Template vorhanden)
- [ ] `generate_lead` - Newsletter Signup
- [ ] `add_to_compare` - Produkt zu Vergleich hinzugefügt

**Implementierung:**
- [ ] Subscriber-Methoden hinzufügen
- [ ] DataLayerBuilder-Methoden
- [ ] JavaScript Event Listener
- [ ] Templates einbinden

**Dateien:**
- `src/Subscriber/DataLayerSubscriber.php`
- `src/Service/DataLayerBuilder.php`
- `src/Resources/app/storefront/src/plugin/wsc-interaction-datalayer.plugin.js` (neu)

**Priorität:** P2 - MITTEL
**Geschätzter Aufwand:** 1-2 Tage

---

## 🟢 NIEDRIG - Post-Release / v1.1+ (P3)

### Issue #11: Google Consent Mode v2 implementieren

**Labels:** `compliance`, `enhancement`, `google`, `v1.1`

**Beschreibung:**

Google Consent Mode v2 für erweiterte Consent-Signale an Google.

**Features:**
- `ad_storage` - Werbe-Cookies
- `analytics_storage` - Analytics-Cookies
- `ad_user_data` - User-Daten für Werbung
- `ad_personalization` - Personalisierte Werbung
- Region-spezifische Defaults (EU vs. Non-EU)

**Implementierung:**
```javascript
gtag('consent', 'default', {
  'ad_storage': 'denied',
  'analytics_storage': 'denied',
  'ad_user_data': 'denied',
  'ad_personalization': 'denied',
  'wait_for_update': 500
});

// After consent
gtag('consent', 'update', {
  'analytics_storage': 'granted'
});
```

**Priorität:** P3 - NIEDRIG
**Geschätzter Aufwand:** 1 Tag

---

### Issue #12: Content Security Policy (CSP) Support

**Labels:** `security`, `csp`, `v1.1`

**Beschreibung:**

Plugin soll mit strikten Content Security Policies kompatibel sein.

**Aufgaben:**
- [ ] Inline-Scripts durch externe Scripts ersetzen
- [ ] Nonce-Support für dynamische Scripts
- [ ] CSP Meta-Tag Konfiguration
- [ ] Dokumentation für CSP-Setup

**Priorität:** P3 - NIEDRIG
**Geschätzter Aufwand:** 1-2 Tage

---

### Issue #13: Matomo Tag Manager - Erweiterte Integration

**Labels:** `matomo`, `enhancement`, `v1.1`

**Beschreibung:**

Matomo Tag Manager volle E-Commerce Integration.

**Features:**
- [ ] Matomo E-Commerce Events (analog zu GA4)
- [ ] Product Impressions
- [ ] Cart Updates
- [ ] Order Tracking

**Priorität:** P3 - NIEDRIG
**Geschätzter Aufwand:** 1 Tag

---

### Issue #14: Lazy Loading für externe Scripts (GTM, Analytics)

**Labels:** `performance`, `lazy-loading`, `v1.1`

**Beschreibung:**

GTM/GA4/Matomo Scripts erst laden wenn User scrollt oder interagiert.

**Performance-Gewinn:**
- Faster First Contentful Paint (FCP)
- Faster Time to Interactive (TTI)
- Besserer Lighthouse Score

**Priorität:** P3 - NIEDRIG
**Geschätzter Aufwand:** 0.5 Tag

---

### Issue #15: A/B Testing für Cookie Banner

**Labels:** `experiment`, `ux`, `v2.0`

**Beschreibung:**

A/B Testing welche Banner-Konfiguration höchste Consent-Rate hat.

**Features:**
- Variante A: Opt-in, Bottom Center
- Variante B: Opt-out, Top Center
- Analytics: Consent-Rate messen

**Priorität:** P3 - NIEDRIG
**Geschätzter Aufwand:** 2 Tage

---

### Issue #16: Consent-Statistiken im Admin-Bereich

**Labels:** `admin`, `analytics`, `v2.0`

**Beschreibung:**

Dashboard im Admin: Wie viele User akzeptieren welche Cookie-Kategorien?

**Features:**
- Consent-Rate Dashboard
- Kategorie-Breakdown (Analytics: 80%, Marketing: 20%)
- Zeitverlauf-Charts
- Export als CSV

**Priorität:** P3 - NIEDRIG
**Geschätzter Aufwand:** 3-5 Tage

---

## 📋 Zusammenfassung

### Prioritäts-Verteilung

| Priorität | Anzahl Issues | Aufwand | Release |
|-----------|--------------|---------|---------|
| **P0 - KRITISCH** | 2 | 2-3 Tage | **Release 1.0 BLOCKER** |
| **P1 - HOCH** | 5 | 6-10 Tage | **Release 1.0** |
| **P2 - MITTEL** | 4 | 5-8 Tage | Release 1.0 (optional) |
| **P3 - NIEDRIG** | 6 | 10-15 Tage | v1.1 - v2.0 |
| **GESAMT** | **17 Issues** | **23-36 Tage** | |

### Roadmap

#### Sprint 1: KRITISCHE Bugs (1 Woche)
1. Issue #1 - Subscriber testen & debuggen
2. Issue #2 - Payment/Shipping Events

**Ziel:** Plugin funktioniert korrekt, alle Events werden gefeuert

#### Sprint 2: Release 1.0 Vorbereitung (2 Wochen)
3. Issue #3 - Cookie Consent GUI-Optionen
4. Issue #4 - Error-Handling & Validierung
5. Issue #5 - Tests implementieren
6. Issue #6 - Frontend Build-Prozess

**Ziel:** Stabiler, getesteter Release 1.0

#### Sprint 3: Quality & Automation (1 Woche, optional)
7. Issue #7 - CI/CD Pipeline
8. Issue #8 - Performance-Optimierung
9. Issue #9 - Admin UX
10. Issue #10 - Erweiterte Events

**Ziel:** Production-ready, hohe Code-Qualität

#### Post-Release: v1.1 - v2.0 (nach Release)
11. Issue #11 - Google Consent Mode v2
12. Issue #12 - CSP Support
13. Issue #13 - Matomo Erweiterungen
14. Issue #14 - Lazy Loading
15. Issue #15 - A/B Testing
16. Issue #16 - Consent-Statistiken

---

## 🚀 Nächste Schritte

1. **Issues auf GitHub anlegen** (manuell, da `gh` CLI nicht installiert)
2. **Sprint 1 starten:** Issue #1 + #2 (KRITISCH)
3. **Testing-Setup:** PHPUnit + Fixtures
4. **Branch-Strategie:** `main` → `develop` → Feature-Branches

---

## 📝 Notizen

- Alle Issues sind GitHub-ready formatiert
- Labels sind standardisiert
- Geschätzter Aufwand ist realistisch
- Prioritäten folgen Release 1.0 Anforderungen
- Code-Beispiele sind eingefügt wo sinnvoll
