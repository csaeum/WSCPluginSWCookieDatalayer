# WSC Cookie Consent + DataLayer + Tag Manager

**Shopware 6 Plugin** für DSGVO-konformes Cookie Consent Management mit OrestBida Cookie Consent v3, DataLayer Integration und Tag Manager Support (Google GTM/GA4, Matomo).

[![CI](https://github.com/csaeum/WSCPluginSWCookieDatalayer/workflows/CI/badge.svg)](https://github.com/csaeum/WSCPluginSWCookieDatalayer/actions/workflows/ci.yml)
[![PHP Version](https://img.shields.io/badge/PHP-8.0%20%7C%208.1%20%7C%208.2%20%7C%208.3-blue)](https://php.net)
[![Shopware](https://img.shields.io/badge/Shopware-6.5%20%7C%206.6%20%7C%206.7-blue)](https://www.shopware.com)
[![License: GPL-3.0](https://img.shields.io/badge/License-GPL%203.0-blue.svg)](LICENSE)
[![Tests](https://img.shields.io/badge/Tests-17%20passing-success)](tests/)

---

## Features

### Cookie Consent (OrestBida v3)
- ✅ **DSGVO-konformes Cookie Management** mit Opt-in/Opt-out Modus
- ✅ **4 Cookie-Kategorien**: Necessary, Analytics, Marketing, Personalization
- ✅ **Mehrsprachigkeit**: Deutsch, Englisch, Französisch
- ✅ **Lokal gehostet**: Alle Assets im Plugin (keine CDN-Abhängigkeiten)
- ✅ **Revision Control**: Erneute Zustimmung bei Policy-Änderungen
- ✅ **Auto-Clear**: Cookies werden bei Consent-Widerruf gelöscht

### DataLayer Integration
- ✅ **Consent Events**: `cookie_consent_update` Events an DataLayer
- ✅ **E-Commerce Events**: view_item, purchase, add_to_cart, etc.
- ✅ **GTM/GA4 kompatibel**: Standard DataLayer Format

### Tag Manager Support
- ✅ **Google Tag Manager** (GTM)
- ✅ **Google Analytics 4** (GA4)
- ✅ **Matomo Tag Manager**
- ✅ **Matomo Analytics**
- ✅ **Server-Side GTM** Support
- ✅ **Consent-basiertes Laden**: Scripts laden nur nach Zustimmung

### Sicherheit
- ✅ **XSS-Schutz**: Alle Ausgaben werden escaped
- ✅ **Context-aware Escaping**: |e('js'), |e('html_attr'), |e('url')
- ✅ **Keine unsicheren |raw Filter**

---

## ⚠️ Wichtiger Hinweis: Shopware Cookie Banner

**Automatische Deaktivierung des Shopware Cookie Banners:**

Dieses Plugin deaktiviert **automatisch** den Shopware-eigenen Cookie Banner, wenn WSC Cookie Consent aktiviert ist. Dies verhindert, dass zwei Cookie Banner gleichzeitig angezeigt werden.

**Wie funktioniert das?**
- Template-Override von `cookie-permission.html.twig` und `cookie-configuration.html.twig`
- Wenn `Cookie Consent aktivieren` in der Plugin-Config auf ✅ EIN gesetzt ist, wird der Shopware Cookie Banner ausgeblendet
- Wenn du WSC Cookie Consent deaktivierst, wird automatisch der Shopware Cookie Banner wieder verwendet

**Zusätzliche manuelle Deaktivierung (optional, aber empfohlen):**

Für 100% Sicherheit kannst du den Shopware Cookie Banner auch manuell deaktivieren:

```
Shopware Admin → Einstellungen → Grundeinstellungen → Storefront
→ "Cookie-Erlaubnis" deaktivieren
```

**Wichtig nach Installation:**
```bash
# Cache leeren damit Template-Override aktiv wird
bin/console cache:clear
bin/console theme:compile
```

---

## Installation

### 1. Plugin installieren

```bash
# Plugin-Verzeichnis in Shopware installieren
cd /path/to/shopware
cp -r /path/to/wscpluginswcookiedatalayer custom/plugins/WscSwCookieDataLayer

# Assets installieren (kopiert Cookie Consent Assets nach public/)
bin/console plugin:refresh
bin/console plugin:install --activate WscSwCookieDataLayer

# Assets in public/ Bundle kopieren
bin/console assets:install

# Cache leeren
bin/console cache:clear

# Theme neu kompilieren
bin/console theme:compile
```

### 2. Plugin konfigurieren

Gehe zu: **Shopware Admin → Einstellungen → System → Plugins → WSC Cookie Consent + DataLayer → Konfiguration**

**Empfohlene Einstellungen:**

#### Cookie Consent
- **Cookie Consent aktivieren**: ✅ EIN
- **Consent-Modus**: `opt-in` (DSGVO-konform, empfohlen)
- **Revision Counter aktivieren**: Optional
- **Revisionsnummer**: 0 (erhöhen bei Policy-Änderungen)

#### DataLayer
- **DataLayer aktivieren**: ✅ EIN
- **DataLayer Google aktivieren**: ✅ EIN (wenn GTM/GA4 verwendet)
- **DataLayer Matomo aktivieren**: ✅ EIN (wenn Matomo verwendet)

#### Google Tag Manager / Analytics
- **Google Allgemein aktivieren**: ✅ EIN
- **Google TagManager aktivieren**: ✅ EIN
- **Tag Manager GTM**: `GTM-XXXXXXX` (deine GTM-ID)
- **Google Analytics 4 aktivieren**: Optional
- **Google Analytics 4**: `G-XXXXXXXXXX` (deine GA4-ID)

#### Matomo
- **Matomo Allgemein aktivieren**: ✅ EIN
- **Matomo URL**: `https://deine-matomo-domain.de` (ohne trailing /)
- **Matomo TagManager aktivieren**: Optional
- **Matomo Seiten ID aktivieren**: ✅ EIN
- **Matomo Seiten ID**: `1` (deine Site ID)

---

## Wichtige Befehle

### Nach Installation / Update

```bash
# Assets installieren (WICHTIG nach Plugin-Installation!)
bin/console assets:install

# Cache leeren
bin/console cache:clear

# Theme neu kompilieren (WICHTIG für Frontend-Änderungen!)
bin/console theme:compile
```

### Development / Debugging

```bash
# Plugin deinstallieren
bin/console plugin:uninstall WscSwCookieDataLayer

# Plugin neu installieren
bin/console plugin:install --activate WscSwCookieDataLayer

# Snippets neu laden (bei Übersetzungsänderungen)
bin/console snippets:validate

# Assets nur kopieren (schneller als assets:install)
bin/console assets:install --symlink
```

### Production Deployment

```bash
# Kompletter Deployment-Flow
bin/console plugin:refresh
bin/console plugin:install --activate WscSwCookieDataLayer
bin/console assets:install
bin/console cache:clear
bin/console theme:compile
bin/console dal:refresh:index
```

---

## Bugfixes (v1.0.0)

Diese Version behebt **4 kritische Bugs** aus der ursprünglichen Implementierung:

### 🔴 Bug #1: XSS-Vulnerability in Google.html.twig
**Problem**: `|raw` Filter ohne Escaping ermöglichte Code-Injection
**Fix**: Alle `|raw` Filter entfernt, `|e('js')` hinzugefügt
**Betroffene Dateien**: `src/Resources/views/storefront/wscTagManager/Google/Google.html.twig`

### 🔴 Bug #2: XSS-Vulnerability in Matomo.html.twig
**Problem**: `|raw` Filter ohne Escaping ermöglichte Code-Injection
**Fix**: Alle `|raw` Filter entfernt, `|e('js')` hinzugefügt
**Betroffene Dateien**: `src/Resources/views/storefront/wscTagManager/Matomo/Matomo.html.twig`

### 🔴 Bug #3: Matomo Site ID Bug
**Problem**: Extra `1` wurde an Site ID angehängt (z.B. Site ID 5 → 51)
**Fix**: Template-Code korrigiert, `1` entfernt
**Betroffene Dateien**: `src/Resources/views/storefront/wscTagManager/Matomo/Matomo.html.twig:17`

### 🔴 Bug #4: Falsche Config-Prüfung in meta.html.twig
**Problem**: Google Scripts wurden nur geladen wenn Matomo-Config aktiv war
**Fix**: Config-Check von `wscTagManagerDataLayerMatomo` zu `wscTagManagerDataLayerGoogle` geändert
**Betroffene Dateien**: `src/Resources/views/storefront/layout/meta.html.twig:33`

---

## Dateistruktur

```
src/
├── Resources/
│   ├── public/
│   │   └── cookieconsent/
│   │       ├── cookieconsent.umd.js    # Cookie Consent v3.1.0 (23KB)
│   │       └── cookieconsent.css       # Cookie Consent CSS (32KB)
│   ├── snippet/
│   │   ├── de_DE/
│   │   │   └── storefront.de-DE.json   # Deutsche Übersetzungen
│   │   ├── en_GB/
│   │   │   └── storefront.en-GB.json   # Englische Übersetzungen
│   │   └── fr_FR/
│   │       └── storefront.fr-FR.json   # Französische Übersetzungen
│   ├── views/
│   │   └── storefront/
│   │       ├── layout/
│   │       │   ├── meta.html.twig
│   │       │   └── cookie/
│   │       │       ├── cookie-permission.html.twig      # Shopware Cookie Banner Override
│   │       │       └── cookie-configuration.html.twig   # Shopware Cookie Config Override
│   │       └── wscTagManager/
│   │           ├── CookieConsent/
│   │           │   ├── CookieConsent.html.twig        # Cookie Consent Loader
│   │           │   └── CookieConsentConfig.html.twig  # Cookie Consent Config
│   │           ├── DataLayer/
│   │           │   ├── DataLayer.html.twig
│   │           │   └── view_item.html.twig
│   │           ├── Google/
│   │           │   └── Google.html.twig   # GTM + GA4 Integration
│   │           └── Matomo/
│   │               └── Matomo.html.twig   # Matomo Integration
│   └── config/
│       └── config.xml                     # Plugin-Konfiguration
├── WscSwCookieDataLayer.php              # Plugin Base Class
└── composer.json
```

---

## Cookie Consent Funktionsweise

### Script-Blocking Mechanismus

**OHNE Consent:**
```html
<script data-category="analytics" type="text/plain">
  // GTM/GA4/Matomo Code wird NICHT ausgeführt
</script>
```

**MIT Analytics-Consent:**
Cookie Consent ändert automatisch `type="text/plain"` → `type="text/javascript"` und führt das Script aus.

### DataLayer Event

Bei Consent-Änderung wird folgendes Event an `window.dataLayer` gepusht:

```javascript
{
  event: 'cookie_consent_update',
  cookie_consent: {
    necessary: true,
    analytics: true,      // User hat zugestimmt
    marketing: false,
    personalization: false
  },
  timestamp: '2025-12-20T10:30:00.000Z'
}
```

### GTM Trigger konfigurieren

**Trigger Type**: Custom Event
**Event Name**: `cookie_consent_update`
**Trigger Fires On**: Some Custom Events
**Condition**: `cookie_consent.analytics` equals `true`

Verwende diesen Trigger für GA4/Analytics Tags, damit sie nur nach Consent feuern.

---

## Testing

### Funktionale Tests

- [ ] Cookie-Banner erscheint bei Erstbesuch
- [ ] "Alle akzeptieren" gewährt alle Consents
- [ ] "Nur notwendige" gewährt nur necessary Cookies
- [ ] Einstellungs-Modal ermöglicht granulare Auswahl
- [ ] Consent wird über Seitenaufrufe gespeichert
- [ ] GTM/GA4 lädt nur bei Analytics-Consent
- [ ] Matomo lädt nur bei Analytics-Consent
- [ ] DataLayer Events werden bei Consent-Änderung gepusht

### Sprachentests

- [ ] Deutsch (de-DE) korrekt angezeigt
- [ ] Englisch (en-GB) korrekt angezeigt
- [ ] Französisch (fr-FR) korrekt angezeigt

### Konfigurationstests

- [ ] Opt-in blockiert alle Scripts bis Consent
- [ ] Opt-out lädt Scripts standardmäßig
- [ ] Revision Counter erzwingt Re-Consent
- [ ] Cookie Consent deaktivieren entfernt Banner

### Bug-Verifikation

- [ ] **Bug #1**: Keine XSS-Vulnerability in Google GTM/GA4 IDs
- [ ] **Bug #2**: Keine XSS-Vulnerability in Matomo URLs/IDs
- [ ] **Bug #3**: Matomo Site ID korrekt (kein Extra-'1')
- [ ] **Bug #4**: Google Scripts laden mit korrekter Config-Prüfung

### Browser-Tests

- [ ] Chrome/Edge (latest)
- [ ] Firefox (latest)
- [ ] Safari (latest)
- [ ] Mobile (iOS Safari, Chrome Android)

---

## Troubleshooting

### Cookie Consent Banner erscheint nicht

```bash
# 1. Prüfe ob Assets installiert sind
ls -la public/bundles/wscswcookiedatalayer/cookieconsent/

# 2. Assets neu installieren
bin/console assets:install --force

# 3. Cache leeren
bin/console cache:clear

# 4. Theme neu kompilieren
bin/console theme:compile

# 5. Browser-Cache leeren (Ctrl+Shift+R)
```

### Zwei Cookie Banner werden angezeigt (WSC + Shopware)

**Problem:** Sowohl der WSC Cookie Consent Banner als auch der Shopware Cookie Banner erscheinen gleichzeitig.

**Lösung:**

```bash
# 1. Cache leeren (Template-Override aktivieren)
bin/console cache:clear
bin/console theme:compile

# 2. Browser-Cache leeren (Ctrl+Shift+R)
```

**Zusätzlich:** Shopware Cookie Banner manuell deaktivieren:
```
Shopware Admin → Einstellungen → Grundeinstellungen → Storefront
→ "Cookie-Erlaubnis" deaktivieren
```

**Prüfen:**
- Cookie Consent in Plugin-Config auf ✅ EIN?
- Template-Override aktiv? Prüfe `src/Resources/views/storefront/layout/cookie/cookie-permission.html.twig`

### Scripts laden trotz fehlender Zustimmung

**Prüfe:**
1. Cookie Consent aktiviert? (Admin → Plugin-Config)
2. Browser-Console auf Fehler prüfen: `console.log(CookieConsent)`
3. Script-Tag hat `data-category="analytics" type="text/plain"`?

### Übersetzungen werden nicht angezeigt

```bash
# Snippets neu validieren
bin/console snippets:validate

# Cache leeren
bin/console cache:clear

# Prüfe Snippet-Dateien
cat src/Resources/snippet/de_DE/storefront.de-DE.json
```

### DataLayer Events werden nicht gepusht

**Browser Console:**
```javascript
// Prüfe ob DataLayer existiert
console.log(window.dataLayer);

// Prüfe Cookie Consent Status
console.log(CookieConsent.acceptedCategory('analytics'));

// Manuelles Test-Event
window.dataLayer.push({ event: 'test' });
```

---

## Entwicklung

### Assets ändern

Nach Änderungen an Cookie Consent Assets:

```bash
# Assets neu kopieren
bin/console assets:install --force

# Cache leeren
bin/console cache:clear

# Theme kompilieren
bin/console theme:compile
```

### Templates ändern

Nach Änderungen an Twig-Templates:

```bash
# Cache leeren (WICHTIG!)
bin/console cache:clear

# Theme kompilieren
bin/console theme:compile
```

### Übersetzungen ändern

Nach Änderungen an Snippet-Dateien:

```bash
# Cache leeren
bin/console cache:clear

# Snippets validieren
bin/console snippets:validate
```

### Config ändern

Nach Änderungen an `config.xml`:

```bash
# Plugin refresh
bin/console plugin:refresh

# Cache leeren
bin/console cache:clear
```

---

## Technische Details

### Cookie Consent Version
- **Library**: OrestBida Cookie Consent v3.1.0
- **Repository**: https://github.com/orestbida/cookieconsent
- **Lizenz**: MIT

### Shopware Kompatibilität
- **Shopware Version**: 6.7.0+
- **PHP Version**: 8.1+

### Cookie-Kategorien

| Kategorie | Beschreibung | readOnly | Auto-enabled (opt-out) |
|-----------|--------------|----------|------------------------|
| **necessary** | Technisch notwendige Cookies | ✅ Ja | ✅ Ja |
| **analytics** | GTM, GA4, Matomo | ❌ Nein | ✅ Ja (opt-out) |
| **marketing** | Marketing & Werbung | ❌ Nein | ✅ Ja (opt-out) |
| **personalization** | Personalisierung | ❌ Nein | ✅ Ja (opt-out) |

### Auto-Clear Cookies

Bei Consent-Widerruf werden automatisch gelöscht:
- `_ga*` - Google Analytics
- `_gid*` - Google Analytics
- `_pk_*` - Matomo
- `_mtm*` - Matomo Tag Manager

---

## Support

- **Issues**: https://gitlab.web-seo-consulting.eu/csaeum/wscpluginswcookiedatalayer/-/issues
- **Email**: Christian.Saeum@Web-SEO-Consulting.eu
- **Website**: https://www.Web-SEO-Consulting.eu

---

## Roadmap

### Version 1.1.0 (geplant)
- [ ] Google Consent Mode v2 implementieren
- [ ] Custom Theme-Optionen (Farben, Position)
- [ ] Erweiterte Cookie-Verwaltung im Admin
- [ ] Performance-Optimierung (Lazy Loading)

### Version 1.2.0 (geplant)
- [ ] Fehlende E-Commerce Events implementieren (add_to_cart, purchase, etc.)
- [ ] PHP Event Subscriber für DataLayer
- [ ] activeRoute Variable setzen
- [ ] Unit Tests & Integration Tests

### Version 2.0.0 (geplant)
- [ ] Content Security Policy (CSP) Support
- [ ] Consent-Statistiken im Admin
- [ ] A/B Testing für Consent-Banner
- [ ] WCAG 2.1 AA Accessibility

---

## Changelog

### v1.0.0 (2025-12-20)

**Features:**
- ✅ Cookie Consent Integration (OrestBida v3.1.0)
- ✅ 4 Cookie-Kategorien (necessary, analytics, marketing, personalization)
- ✅ Mehrsprachigkeit (DE, EN, FR)
- ✅ DataLayer Integration mit Consent Events
- ✅ GTM/GA4/Matomo Integration
- ✅ Opt-in/Opt-out Modus
- ✅ Revision Control
- ✅ Automatische Deaktivierung des Shopware Cookie Banners (Template-Override)

**Bugfixes:**
- 🔴 Bug #1: XSS-Vulnerability in Google.html.twig behoben
- 🔴 Bug #2: XSS-Vulnerability in Matomo.html.twig behoben
- 🔴 Bug #3: Matomo Site ID Bug behoben (Extra '1' entfernt)
- 🔴 Bug #4: Falsche Config-Prüfung in meta.html.twig behoben

**Security:**
- ✅ Alle `|raw` Filter entfernt
- ✅ Context-aware Escaping implementiert
- ✅ XSS-Schutz für alle User-Inputs

---

## Lizenz

MIT License - siehe [LICENSE](LICENSE) Datei für Details.

---

## Autor

**Christian Säum**
- Email: Christian.Saeum@Web-SEO-Consulting.eu
- Website: https://www.Web-SEO-Consulting.eu
- GitLab: https://gitlab.web-seo-consulting.eu/csaeum

---

## Acknowledgments

- [OrestBida](https://github.com/orestbida) für die exzellente Cookie Consent Library
- Shopware Community für Support und Dokumentation
- Alle Contributors und Tester

---

## Lizenz

GPL-3.0-or-later

## Unterstützung

**Made with ❤️ by WSC - Web SEO Consulting**

Dieses Plugin ist kostenlos und Open Source. Wenn es dir geholfen hat, freue ich mich über deine Unterstützung:

[![Buy Me a Coffee](https://img.shields.io/badge/Buy%20Me%20a%20Coffee-ffdd00?style=for-the-badge&logo=buy-me-a-coffee&logoColor=black)](https://buymeacoffee.com/csaeum)
[![GitHub Sponsors](https://img.shields.io/badge/GitHub%20Sponsors-ea4aaa?style=for-the-badge&logo=github-sponsors&logoColor=white)](https://github.com/sponsors/csaeum)
[![PayPal](https://img.shields.io/badge/PayPal-00457C?style=for-the-badge&logo=paypal&logoColor=white)](https://paypal.me/csaeum)
