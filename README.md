# tcd-studio.de

Website von **TCD-Studio/Agency** unter **https://tcd-studio.de**.

> **tcd-studio.de ist die Zweitstelle von TCD-Studio/Agency in Österreich (AT).**
> Der Hinweis steht auf der Startseite (Abschnitt „About us“), im Footer der Seiten mit Footer, im Impressum
> (`/legal-notice/`) sowie in den Meta-Daten (Description, JSON-LD). Die Datenschutzerklärung hat keinen Footer
> und enthält den Hinweis nur in den Meta-Daten.

Die Seite ist ein statischer Webauftritt (HTML/CSS/JS) mit einem kleinen PHP-Backend für das Kontaktformular – und
dem **Impressumsschutz-System** (Kundenbereich + Admin-Panel), siehe unten und **[docs/BETRIEB.md](docs/BETRIEB.md)**.
Impressumsschutz gibt es **für alle Creator (und nur für Creator)**, mit AGB (`/agb/` Deutsch, `/terms/` Englisch); für **Shops nur
auf Anfrage**, künftige Shop-Zahlungen sind **über die Stripe-API geplant** (noch nicht umgesetzt).
Kunden weisen ihre Identität per **verschlüsselt hochgeladenem Ausweisfoto** nach; beim Beenden des Dienstes werden alle Daten
automatisch gelöscht. Einrichtung (Schlüsselpaar, sicheres Archiv, Sicherung) und rechtliche Hinweise für den Betreiber:
[docs/BETRIEB.md](docs/BETRIEB.md), Abschnitt „Ausweisprüfung und Löschung“.
Die Seite wurde aus einem IONOS-Sitejet-Export übernommen und für tcd-studio.de neu aufgesetzt. Das Design ist
unverändert (per Screenshot-Vergleich gegen das Original geprüft: 8 Seiten × 3 Bildschirmgrößen; abweichend
sind nur die neuen Hinweistexte und die geänderten Domains/E-Mail-Adressen). Bewusst entfernt wurde der
abgelaufene Ankündigungsbanner oben auf der Startseite.

## Struktur

```
index.html                       Startseite
privacy/                         Datenschutzerklärung
legal-notice/                    Impressum (§ 5 ECG, Österreich)
legal-notice-<name>/             Weiterleitungen auf die neuen Creator-Impressen (/legal/<name>/)
newcomer/                        TCD x Quantix – Gratis-Programmierkurse (DE)
tcd-programmer/                  TCD-Programmer Developer Group

css/theme.css                    Theme-Styles (Design) – hier wird das Aussehen angepasst
css/pages/*.css                  seitenspezifische Styles (früher Inline-<style> je Seite)
js/site.js                       Menü (Mobile-Toggle, Smooth-Scroll, Sticky, aktiver Link), neues Captcha nach Fehlversuch
fonts/                           Poppins (selbst gehostet, keine Verbindung zu Google)
images/                          nur noch tatsächlich verwendete Bilder

legal/<name>/                    Impressen der Creator (ERZEUGT nach Admin-Freigabe, statisches HTML)
portal/                          Kundenbereich (Registrierung, Impressum-Profile, Anfragen)
admin/                           Admin-Panel (Freigaben, Profile, Kunden, Anfragen, Services, Audit-Log, Settings)
services/, imprint-protection/,  öffentliche Seiten des Systems (Service-Katalog, Impressumsschutz, Shops auf Anfrage,
shops/, coming-soon/             wiederverwendbare Coming-Soon-Seite: /coming-soon/?topic=…)
app/                             PHP-Code des Systems (gesperrt per .htaccess); Architektur: docs/ARCHITECTURE.md
data/                            Laufzeitdaten (SQLite-DB, Einstellungen, Setup-Token) – nie committen, nicht ausliefern
                                 (liegt nach dem Installer meist außerhalb des Webroots; Zeiger: app/data-dir.local)
installer.sh                     Einrichtung auf dem Server (bash installer.sh), Helfer: tools/install-helper.php

api.php                          Einstiegspunkt der Formular-API (/api.php/form_container/captcha | submit)
backend/                         Konfiguration, Captcha, Versand der Kontaktanfragen
                                 (Direktzugriff gesperrt, siehe backend/.htaccess)

webcard/                         Laufzeit des Sitejet-Baukastens (Drittanbieter-Code, minifiziert)
                                 -> nicht bearbeiten; liefert Lazy-Loading, Animationen, Parallax, Formular-Logik
.well-known/                     für Let's-Encrypt-Zertifikate (ACME)
sitemap.xml, robots.txt          Suchmaschinen
```

## Betrieb

Voraussetzungen des Hostings: Apache (oder ähnlich) mit **PHP ≥ 7.4**, Erweiterungen **GD**, **pdo_sqlite**,
**mbstring**, **openssl**, **Sessions** und funktionierendem **`mail()`**; Schreibrechte für `data/`, `legal/` und
`sitemap.xml`. Die Dateien in das Webroot von `tcd-studio.de` hochladen und dann per SSH im Webroot den **Installer** starten:

```bash
bash installer.sh
```

Er prüft den Server, legt das Datenverzeichnis (am besten außerhalb des Webroots), Datenbank, Datenschlüssel, das erste
Admin-Konto, den Ausweis-Tresor (Schlüssel + Archiv mit Test) und den Wartungs-Cronjob an und gibt am Ende eine Checkliste aus.
`bash installer.sh --check` prüft nur; `bash installer.sh --help` zeigt alle Optionen (auch für den unbeaufsichtigten Betrieb).
Ohne SSH: `/admin/setup.php` aufrufen (Details in [docs/BETRIEB.md](docs/BETRIEB.md)).

Lokal ausprobieren:

```bash
php -S 127.0.0.1:8080
```

> Das Absenden des Formulars nutzt das Protokoll der Seite, die Captcha-Abfrage der Sitejet-Laufzeit
> dagegen immer `https://` (so ist es in `webcard/` fest eingebaut). Lokal über `http://` lässt sich die Seite
> ansehen; das Formular (Captcha) funktioniert vollständig nur hinter HTTPS.

## Kontaktformular

Das Formular benötigt keinen externen Dienst mehr (vorher: `api.sitehub.io`). Empfänger, Absender,
Betreff, Texte und Spam-Schutz-Werte stehen gesammelt in **`backend/config.php`**.

**Wichtig:** Das Postfach `contact@tcd-studio.de` muss existieren (oder in `backend/config.php` ändern).

Der Schutz von `backend/` per `backend/.htaccess` gilt für Apache; bei anderen Servern verhindert zusätzlich ein
Guard in jeder Datei den Direktaufruf.

## Hinweise / offene Punkte

- **Postfächer (alle `@tcd-studio.de`, bitte anlegen):** `contact` (Kontaktformular, Anfragen), `info`, `imprint`
  (Freigabe-Benachrichtigungen, Kontakt in den Creator-Impressen), `privacy-policy` (Datenschutz-Kontakt),
  `no-reply` (Absender der System-Mails). Die Adresse `creepyx_470@` gibt es nicht mehr.
- **Rechtstexte:** Im Impressum wurden nur Domain, E-Mail-Adresse, „Website Owner“ (jetzt `TCD-Studio/Agency`) und der
  Zweitstellen-Hinweis geändert; die Datenschutzerklärung nur in Titel/Meta-Daten und um den Abschnitt „Imprint Protection (customer area)“ / „Impressumsschutz
  (Kundenbereich)“. Inhaltlich geprüft wurde nichts,
  bitte vor dem Livegang rechtlich gegenlesen. Die Datenschutzerklärung bindet wie im Original ein
  Matomo-Opt-out-Fenster von `page-stats.de` ein (die Seite selbst nutzt kein Tracking); ob das bleiben soll, ist eine
  inhaltliche Entscheidung.
- **Formatierung:** Die Laufzeit in `webcard/` sucht das Ziel von `#!next`-Links (Hero-Button „Learn more“, Icon im
  Footer) über `nextSibling`. `js/site.js` (`fixNextLinkTargets`) entfernt dafür die Leerraum-Textknoten, damit die
  Seiten weiterhin lesbar eingerückt bleiben dürfen.
- **Instagram-Link** im Footer führt auf `/coming-soon/?topic=instagram` (bis das Profil existiert).
- **Aufgeräumt:** ungenutzte Länderflaggen-Bibliothek (516 SVGs), alte Favicon-Varianten, nicht eingebundene Bilder
  (`aerostudios/`, `synaptox/`, `dummy.png`) und nicht referenzierte Bildgrößen wurden entfernt (liegen weiterhin im
  ursprünglichen ZIP). Defekte Footer-Links (`/2136746#…`), falsche Canonical-URLs wurden korrigiert, der Google-Maps-Key
  wurde aus dem Inline-Skript der Seiten entfernt (es gibt keine Karte; die Laufzeit in `webcard/` bringt unverändert
  ihren eigenen Platzhalter mit).
