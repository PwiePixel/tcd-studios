# tcd-studio.de

Website von **TCD-Studio/Agency** unter **https://tcd-studio.de**.

> **tcd-studio.de ist die Zweitstelle von TCD-Studio/Agency in Österreich (AT).**
> Der Hinweis steht auf der Startseite (Abschnitt „About us“), im Footer der Seiten mit Footer, im Impressum
> (`/legal-notice/`) sowie in den Meta-Daten (Description, JSON-LD). Die Datenschutzerklärung hat keinen Footer
> und enthält den Hinweis nur in den Meta-Daten.

Die Seite ist ein statischer Webauftritt (HTML/CSS/JS) mit einem kleinen PHP-Backend für das Kontaktformular.
Sie wurde aus einem IONOS-Sitejet-Export übernommen und für tcd-studio.de neu aufgesetzt. Das Design ist
unverändert (per Screenshot-Vergleich gegen das Original geprüft: 8 Seiten × 3 Bildschirmgrößen; abweichend
sind nur die neuen Hinweistexte und die geänderten Domains/E-Mail-Adressen). Bewusst entfernt wurde der
abgelaufene Ankündigungsbanner oben auf der Startseite.

## Struktur

```
index.html                       Startseite
privacy/                         Datenschutzerklärung
legal-notice/                    Impressum (§ 5 ECG, Österreich)
legal-notice-pixelcst/           Impressum Streamer PixelCST
legal-notice-creepyx-470/        Impressum Streamer CreepyX_470
legal-notice-tth-projects/       Impressum Streamer TTH Projects
newcomer/                        TCD x Quantix – Gratis-Programmierkurse (DE)
tcd-programmer/                  TCD-Programmer Developer Group

css/theme.css                    Theme-Styles (Design) – hier wird das Aussehen angepasst
css/pages/*.css                  seitenspezifische Styles (früher Inline-<style> je Seite)
js/site.js                       Menü (Mobile-Toggle, Smooth-Scroll, Sticky, aktiver Link), neues Captcha nach Fehlversuch
fonts/                           Poppins (selbst gehostet, keine Verbindung zu Google)
images/                          nur noch tatsächlich verwendete Bilder

api.php                          Einstiegspunkt der Formular-API (/api.php/form_container/captcha | submit)
backend/                         Konfiguration, Captcha, Versand der Kontaktanfragen
                                 (Direktzugriff gesperrt, siehe backend/.htaccess)

webcard/                         Laufzeit des Sitejet-Baukastens (Drittanbieter-Code, minifiziert)
                                 -> nicht bearbeiten; liefert Lazy-Loading, Animationen, Parallax, Formular-Logik
.well-known/                     für Let's-Encrypt-Zertifikate (ACME)
sitemap.xml, robots.txt          Suchmaschinen
```

## Betrieb

Voraussetzungen des Hostings: Apache (oder ähnlich) mit **PHP ≥ 7.4**, Erweiterung **GD**, **Sessions** und
funktionierendem **`mail()`**. Die Dateien einfach in das Webroot von `tcd-studio.de` hochladen.

Lokal ausprobieren:

```bash
php -S 127.0.0.1:8080
```

> Das Formular im Browser spricht die API immer per `https://` an (so ist es in der Laufzeit fest
> eingebaut). Lokal über `http://` lässt sich die Seite ansehen, das Absenden des Formulars aber nur
> hinter HTTPS testen.

## Kontaktformular

Das Formular benötigt keinen externen Dienst mehr (vorher: `api.sitehub.io`). Empfänger, Absender,
Betreff, Texte und Spam-Schutz-Werte stehen gesammelt in **`backend/config.php`**.

**Wichtig:** Das Postfach `kontakt@tcd-studio.de` muss existieren (oder in `backend/config.php` ändern).

## Hinweise / offene Punkte

- **E-Mail-Adressen:** Überall wurden `…@tcd-studio.com` / `…@tcd-studio.agency` auf `…@tcd-studio.de` umgestellt
  (`kontakt@` sowie `creepyx_470@` im Impressum). Bitte sicherstellen, dass diese Postfächer existieren.
- **Rechtstexte:** Impressum und Datenschutz wurden nur in Domain/Adresse/Zweitstellen-Hinweis angepasst, nicht
  inhaltlich geprüft. Bitte vor dem Livegang rechtlich gegenlesen.
- **Formatierung:** Die Laufzeit in `webcard/` sucht das Ziel von `#!next`-Links (Hero-Button „Learn more“, Icon im
  Footer) über `nextSibling`. `js/site.js` (`fixNextLinkTargets`) entfernt dafür die Leerraum-Textknoten, damit die
  Seiten weiterhin lesbar eingerückt bleiben dürfen.
- **Instagram-Link** im Footer ist wie im Original ein Platzhalter (`/coming-soon`, Seite existiert nicht).
- **Aufgeräumt:** ungenutzte Länderflaggen-Bibliothek (516 SVGs), alte Favicon-Varianten, nicht eingebundene Bilder
  (`aerostudios/`, `synaptox/`, `dummy.png`) und nicht referenzierte Bildgrößen wurden entfernt (liegen weiterhin im
  ursprünglichen ZIP). Defekte Footer-Links (`/2136746#…`), falsche Canonical-URLs und der Google-Maps-Key wurden
  korrigiert bzw. entfernt.
