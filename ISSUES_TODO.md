# Plugin Issue-Übersicht & Roadmap

Komplette Übersicht aller Issues aus dem alten Projekt (wsc_swcookiedatalayer) und dem neuen Projekt (wscpluginswcookiedatalayer).

## Status: 19 Issues erfolgreich erstellt ✅

## Fehlende Issues (manuell anlegen)

Aufgrund von Netzwerkproblemen konnten diese Issues nicht automatisch erstellt werden:

## Issue: Cookie Consent Integration (OrestBida) implementieren

**Labels:** `enhancement`, `high-priority`

**Beschreibung:**

Integration des OrestBida Cookie Consent v3 (https://github.com/orestbida/cookieconsent) in das Plugin.

### Aufgaben
- [ ] Cookie Consent JavaScript Library einbinden (via CDN oder lokal)
- [ ] Cookie Consent CSS einbinden
- [ ] Konfigurationsoptionen in config.xml erstellen für:
  - [ ] Cookie Consent aktivieren/deaktivieren
  - [ ] Cookie Kategorien konfigurieren (notwendig, analytisch, marketing)
  - [ ] Position des Consent Banners
  - [ ] Theme/Farben anpassen
- [ ] Cookie Consent initialisieren und mit Tag Manager Skripten verknüpfen
- [ ] Consent-Events an DataLayer senden
- [ ] GTM/GA4 Skripte nur laden, wenn Consent erteilt wurde
- [ ] Matomo Skripte nur laden, wenn Consent erteilt wurde

### Sprachen
- Deutsch (de-DE)
- Englisch (en-GB)
- Französisch (fr-FR)

### Dokumentation
- Cookie Consent Konfiguration dokumentieren

---

## Issue: Mehrsprachigkeit komplett implementieren (DE, EN, FR)

**Labels:** `i18n`, `enhancement`

**Beschreibung:**

Alle Texte und Konfigurationen müssen in Deutsch, Englisch und Französisch verfügbar sein.

### Aufgaben
- [ ] Snippet-Dateien erstellen:
  - [ ] `src/Resources/snippet/de_DE/storefront.de-DE.json`
  - [ ] `src/Resources/snippet/en_GB/storefront.en-GB.json`
  - [ ] `src/Resources/snippet/fr_FR/storefront.fr-FR.json`
- [ ] Alle Texte in config.xml überprüfen und korrigieren (teilweise noch falsche/fehlende Übersetzungen)
- [ ] Cookie Consent Texte übersetzen:
  - [ ] Banner-Texte
  - [ ] Cookie-Kategorien Beschreibungen
  - [ ] Button-Texte (Akzeptieren, Ablehnen, Einstellungen)
- [ ] Hilfe-Texte in config.xml verbessern und korrekt übersetzen
- [ ] Französische Übersetzungen von Deutsch auf Französisch korrigieren (aktuell teilweise noch deutsch)

### Betroffene Dateien
- `src/Resources/config/config.xml`
- Neue Snippet-Dateien

---

# Zusammenfassung aller erstellten Issues

## 19 Issues erfolgreich erstellt ✅

### KRITISCHE Bugs (sofort beheben!)

1. **#14** 🔴 XSS-Sicherheitslücke durch |raw Filter (`bug`, `critical`, `security`)
2. **#15** 🔴 Matomo Site ID hat zusätzliche '1' angehängt (`bug`, `critical`, `matomo`)
3. **#16** 🔴 Variable 'activeRoute' wird nie gesetzt (`bug`, `critical`, `tracking`)
4. **#7** 🔴 Google DataLayer falsche Config-Prüfung (`bug`, `critical`)

### Hohe Priorität (vor Release 1.0)

5. **Cookie Consent Integration** - noch manuell anzulegen (`enhancement`, `high-priority`)
6. **Mehrsprachigkeit (DE/EN/FR)** - noch manuell anzulegen (`i18n`, `enhancement`)
7. **#1** Fehlende DataLayer Events implementieren (`enhancement`, `feature`)
8. **add_to_cart Event** - noch manuell anzulegen (`enhancement`, `high`, `tracking`)
9. **#3** PHP Services und Subscriber implementieren (`backend`, `enhancement`)

### Mittlere Priorität (für Release 1.0)

10. **#2** Frontend Assets (JavaScript/CSS) organisieren (`enhancement`, `frontend`)
11. **#17** 🔍 Fehlende Null-Checks in view_item.html.twig (`bug`, `medium`, `stability`)
12. **#18** ⚡ DataLayer wird auf jeder Seite geladen (`performance`, `optimization`)
13. **#19** ⚙️ services.xml erstellen (`architecture`, `dependency-injection`)
14. **Event Subscriber implementieren** - noch manuell anzulegen (`architecture`, `best-practice`)
15. **remove_from_cart Event** - noch manuell anzulegen (`enhancement`, `medium`)
16. **#4** Unit Tests und Integration Tests (`quality`, `testing`)
17. **#5** Plugin-Metadaten und Store-Assets (`documentation`, `marketing`)
18. **#6** DataLayer Validierung und Fehlerbehandlung (`bug`, `quality`, `security`)
19. **#8** GitLab CI/CD Pipeline konfigurieren (`ci-cd`, `quality`)
20. **#9** Erweiterte Cookie Consent Konfiguration (`enhancement`, `feature`)

### Niedrige Priorität (Post-Release / v1.1+)

21. **#10** Google Consent Mode v2 implementieren (`compliance`, `enhancement`)
22. **#11** Matomo Tag Manager Cookie Consent Integration (`enhancement`, `matomo`)
23. **#12** Performance-Optimierung: Lazy Loading (`optimization`, `performance`)
24. **#13** Admin Bereich UI/UX verbessern (`admin`, `enhancement`, `ux`)

---

## Empfohlene Reihenfolge (Roadmap)

### Sprint 1: Kritische Bugs (SOFORT) ⚡

**Diese Bugs verhindern aktuell die korrekte Funktionalität!**

1. **#14** - XSS-Sicherheitslücke: `|raw` Filter entfernen
2. **#15** - Matomo Site ID: `1` entfernen
3. **#16** - `activeRoute` Variable setzen
4. **#7** - Google DataLayer Config-Bedingung korrigieren
5. **#17** - Null-Checks in view_item.html.twig

**Zeitaufwand:** ~2-3 Stunden
**Impact:** KRITISCH - Plugin funktioniert aktuell nicht korrekt

### Sprint 2: Kernfunktionalität (Release 1.0 Blocker) 🎯

6. **Cookie Consent Integration** (manuell anlegen)
   - OrestBida CookieConsent v3 einbinden
   - Consent-Management implementieren
   - Skripte nur bei Consent laden

7. **Mehrsprachigkeit** (manuell anlegen)
   - Snippet-Dateien DE/EN/FR
   - config.xml Übersetzungen korrigieren

8. **#19** - services.xml erstellen

9. **Event Subscriber** (manuell anlegen)
   - ProductPageSubscriber
   - CheckoutSubscriber
   - CartSubscriber

10. **#1** - Fehlende DataLayer Events
    - Alle 7 GA4 E-Commerce Events

**Zeitaufwand:** ~3-5 Tage
**Impact:** HOCH - Kernfunktionalität des Plugins

### Sprint 3: Frontend & Interaktivität 🎨

11. **#2** - Frontend Assets (JS/CSS)
12. **add_to_cart Event** (manuell anlegen) - JavaScript
13. **remove_from_cart Event** (manuell anlegen) - JavaScript
14. **#18** - DataLayer Performance-Optimierung

**Zeitaufwand:** ~2-3 Tage
**Impact:** MITTEL - User Experience

### Sprint 4: Qualität & Testing ✅

15. **#6** - DataLayer Validierung
16. **#4** - Unit Tests & Integration Tests
17. **#8** - GitLab CI/CD Pipeline
18. **#9** - Erweiterte Cookie Consent Config

**Zeitaufwand:** ~2-3 Tage
**Impact:** MITTEL - Code-Qualität

### Sprint 5: Release-Vorbereitung 📦

19. **#5** - Plugin-Metadaten & Store-Assets
20. **README & Dokumentation** (aus altem Projekt #23)

**Zeitaufwand:** ~1-2 Tage
**Impact:** NIEDRIG - Marketing

### Post-Release (Version 1.1+) 🚀

21. **#10** - Google Consent Mode v2
22. **#11** - Matomo Cookie Consent Integration
23. **#12** - Performance-Optimierung (Lazy Loading)
24. **#13** - Admin UI/UX Verbesserungen
25. **PSR-12 Compliance** (aus altem Projekt #16)
26. **CSP Support** (aus altem Projekt #21)

---

## Wichtige Erkenntnisse aus dem alten Projekt

### Kritische Bugs gefunden

Das alte Projekt hatte **4 KRITISCHE Bugs**, die alle auch im neuen Projekt vorhanden sind:

1. **XSS-Sicherheitslücke** (#14) - `|raw` Filter in allen Templates
2. **Matomo Site ID Bug** (#15) - Zusätzliche '1' angehängt
3. **activeRoute nicht gesetzt** (#16) - Events greifen nie
4. **Falsche Config-Prüfung** (#7) - Google wird nur geladen wenn Matomo aktiv

**⚠️ DIESE MÜSSEN SOFORT BEHOBEN WERDEN!**

### Fehlende Features

- **6 von 7 E-Commerce-Events** nicht implementiert
- Nur `view_item` funktioniert teilweise
- `purchase`, `add_to_cart`, `remove_from_cart`, `begin_checkout`, `add_payment_info`, `add_shipping_info` fehlen komplett

### Architektur-Probleme

- Keine PHP-Services
- Keine Event Subscriber
- Alles nur in Twig-Templates
- Widerspricht Shopware Best Practices

### Qualität

- Keine Tests
- Keine CI/CD
- Fehlende Null-Checks
- Inkonsistente Code-Qualität

---

## Links

- **Neues Projekt:** https://gitlab.web-seo-consulting.eu/csaeum/wscpluginswcookiedatalayer/-/issues
- **Altes Projekt:** https://gitlab.web-seo-consulting.eu/csaeum/wsc_swcookiedatalayer/-/issues
- **OrestBida Cookie Consent:** https://github.com/orestbida/cookieconsent

---

## Zusammenfassung

**Gesamt: ~30 Issues identifiziert**
- ✅ 19 Issues im neuen Projekt erstellt
- ⏳ ~5-8 Issues noch manuell anzulegen (Netzwerkprobleme)
- 📋 Zusätzliche Ideen aus altem Projekt dokumentiert

**Kritische Blocker: 5**
- Diese MÜSSEN vor jedem Release behoben werden
- Aktuell funktioniert das Plugin NICHT korrekt

**Geschätzter Aufwand bis Release 1.0:**
- Sprint 1 (Bugs): ~2-3 Stunden
- Sprint 2 (Core): ~3-5 Tage
- Sprint 3 (Frontend): ~2-3 Tage
- Sprint 4 (Quality): ~2-3 Tage
- Sprint 5 (Release): ~1-2 Tage
- **GESAMT: ~2-3 Wochen** (Full-Time)

**Nächste Schritte:**
1. Kritische Bugs in Sprint 1 sofort beheben
2. Fehlende Issues manuell in GitLab anlegen
3. Sprint 2 starten (Cookie Consent + Mehrsprachigkeit)
4. Roadmap abarbeiten
