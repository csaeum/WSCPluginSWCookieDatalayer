# Cookie Consent Themes - Dokumentation

## Verfügbare Themes

### Standard Themes
1. **Default (Light)** - Standard helles Theme ohne extra CSS
2. **Dark** - Dunkles Theme mit inline CSS-Variablen

### Orest Bida Custom Themes (feste Farben)
3. **Dark Turquoise** - Dunkles türkises Theme
4. **Light Funky** - Helles, verspieltes Theme mit abgerundeten Ecken
5. **Elegant Black** - Elegantes schwarzes Theme

### Shopware Custom Themes (adaptive Farben)
6. **Custom Theme 1: Shopware Primary Color**
   - Verwendet die Shopware Theme-Farben (z.B. `--sw-color-brand-primary`)
   - Passt sich automatisch an Ihr aktives Shopware Theme an
   - Helles, professionelles Design

7. **Custom Theme 2: Shopware Accent Colors**
   - Verwendet Success & Info Farben aus Ihrem Shopware Theme
   - Kontrastreiches, modernes Design mit abgerundeten Buttons
   - Bunte, freundliche Optik

---

## Shopware Theme-Variablen anpassen

Wenn Sie ein **eigenes Shopware Theme** verwenden und die Custom Themes 1 & 2 anpassen möchten, können Sie folgende CSS-Variablen in Ihrem Theme definieren:

### theme.json (empfohlen)

Bearbeiten Sie die `theme.json` Ihres Shopware Themes:

```json
{
  "name": "MeinTheme",
  "author": "...",
  "style": {
    "theme-variables": {
      "@sw-color-brand-primary": "#ff6b35",
      "@sw-color-success": "#28a745",
      "@sw-color-info": "#17a2b8",
      "@sw-text-color": "#333333",
      "@sw-background-color": "#ffffff",
      "@sw-border-color": "#dee2e6"
    }
  }
}
```

### CSS-Variablen (Alternative)

Oder fügen Sie CSS-Variablen in Ihrer `base.scss` / `app.css` hinzu:

```css
:root {
    --sw-color-brand-primary: #ff6b35;
    --sw-color-brand-primary-darker: #e55a2b;
    --sw-color-success: #28a745;
    --sw-color-success-darker: #218838;
    --sw-color-info: #17a2b8;
    --sw-color-info-darker: #138496;
    --sw-text-color: #333333;
    --sw-background-color: #ffffff;
    --sw-border-color: #dee2e6;
}
```

---

## Welche Farben werden verwendet?

### Custom Theme 1 (Primary Color)
- **Primary Button:** `--sw-color-brand-primary` (Ihre Hauptfarbe)
- **Hintergrund:** `--sw-background-color` (Hintergrundfarbe)
- **Text:** `--sw-text-color` (Textfarbe)
- **Toggles:** `--sw-color-brand-primary` wenn aktiv

### Custom Theme 2 (Accent Colors)
- **Primary Button:** `--sw-color-success` (grün)
- **Secondary Button:** `--sw-color-info` (blau)
- **Toggles:** `--sw-color-brand-primary`

---

## Eigene Farben ohne Shopware Theme-Variablen

Wenn Ihr Theme keine Shopware CSS-Variablen definiert, verwenden die Custom Themes **Fallback-Werte**:

- `--sw-color-brand-primary` → `#008490` (türkis)
- `--sw-color-success` → `#10b981` (grün)
- `--sw-color-info` → `#3b82f6` (blau)
- `--sw-text-color` → `#4a5568` (dunkelgrau)

---

## Eigene Custom Themes erstellen

Sie können die CSS-Dateien auch direkt bearbeiten:

1. **theme-custom-1.css** bearbeiten
2. **theme-custom-2.css** bearbeiten

Nach Änderungen:
```bash
bin/console assets:install
php bin/console cache:clear
```

---

## Support

Bei Fragen zu den Themes schauen Sie in die CSS-Dateien - dort sind alle Variablen dokumentiert:

- `theme-custom-1.css` - Shopware Primary Color Theme
- `theme-custom-2.css` - Shopware Accent Colors Theme
