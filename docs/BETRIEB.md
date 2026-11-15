# Impressumsschutz-System – Betriebsanleitung

tcd-studio.de bietet **Impressumsschutz für alle Creator** (Streamer, Video-Creator, Künstler …) – und **nur für Creator**.
Für **Shops** gibt es Impressumsschutz **nur auf Anfrage** und begrenzt; künftige **Shop-Zahlungen** sollen über die
**Stripe-API** laufen (noch nicht gebaut). Für den normalen Impressumsschutz gelten die **AGB** (siehe unten).

Jeder Kunde bekommt ein eigenes Impressum unter
`https://tcd-studio.de/legal/<name>/`, in dem die **Management-Adresse von TCD-Studio/Agency** steht statt seiner
Privatadresse. Die echten Daten des Kunden bleiben intern (nur der Kunde selbst und das Admin-Team sehen sie).

## Die Bereiche

| Bereich | URL | Wer |
|---|---|---|
| Kundenbereich | `/portal/` | Creator (Registrierung mit E-Mail-Bestätigung und Zustimmung zu den AGB) |
| Admin-Panel | `/admin/` | Team (eigene Admin-Konten, optional 2-Faktor per Authenticator-App) |
| Impressen (öffentlich) | `/legal/<name>/` | jeder – statische Seiten, werden nach Freigabe erzeugt |
| Dienste | `/services/`, `/imprint-protection/`, `/shops/` | öffentlich |
| AGB | `/agb/` (Deutsch, **verbindlich**), `/terms/` (Englisch, nur zur Information) | öffentlich |
| Coming Soon (wiederverwendbar) | `/coming-soon/?topic=<thema>` | öffentlich; Themen: instagram, shops, creator-protection, payments |

## Ablauf Impressumsschutz

1. **Kunde registriert sich** (`/portal/register.php`) und bestätigt seine E-Mail-Adresse.
2. **Kunde legt ein Impressum-Profil an**: öffentlicher Name, Typ, Seitenname (`/legal/<name>/`).
3. **Kunde pflegt seine Angaben**: Geschäftszweck-Text, **eigene Links hinzufügen/entfernen**, ob eine direkte
   Kontakt-E-Mail im Impressum stehen soll – und seine **privaten Daten** (Name, Anschrift; werden nie veröffentlicht).
4. **Kunde reicht ein** → das Team bekommt eine Mail an `imprint@tcd-studio.de`.
5. **Admin prüft** (`/admin/reviews.php`): sieht private Daten, die Änderungen gegenüber der Live-Version und eine
   Vorschau. Freigabe nur mit Häkchen „Identität und Anschrift geprüft“. **Ablehnen** nur mit Begründung.
6. **Freigabe** → die statische Seite `legal/<name>/index.html` wird erzeugt, der Kunde bekommt eine Mail.
   **Jede spätere Änderung** (z. B. neue Links) läuft wieder über Einreichen → Freigabe; bis dahin bleibt die alte
   Version online.
7. Admin kann jederzeit **sperren** (Seite verschwindet), **wieder aktivieren**, den Seitennamen ändern oder löschen.
   Der Kunde kann sein Impressum selbst **offline nehmen**.

## AGB (Allgemeine Geschäftsbedingungen)

- Texte: `app/views/site/agb.php` (Deutsch, verbindlich) und `app/views/site/terms.php` (Englisch, Übersetzung).
  Die Anbieterdaten (Name, Anschrift, Telefon, Postfächer) kommen aus den Einstellungen bzw. `app/config.php`.
- **Zustimmung:** Bei der Registrierung ist das Häkchen „Ich bin Creator und akzeptiere die AGB“ Pflicht. Zeitpunkt und
  **Version** werden gespeichert (Admin sieht sie bei der Freigabe und in der Kundenansicht).
- **Neue Fassung:** Inhaltliche Änderung der AGB → `terms_version` in `app/config.php` erhöhen (z. B. `2027-03-01`). Bis
  Kunden der neuen Fassung im Portal zustimmen, können sie keine neuen Profile anlegen und nichts mehr einreichen (die
  bisher freigegebene Impressumsseite bleibt online).
- **Vorab klären / Entscheidungen** (im Text so festgelegt, bitte bestätigen oder ändern lassen): Dienst **unentgeltlich,
  solange keine Preisinformation angezeigt wird** (§ 6) · Mindestalter 18 (§ 3) · Kündigung durch uns mit 14 Tagen Frist
  (§ 7) · Gerichtsstand Graz für Unternehmer (§ 11) · Rücktrittsrecht für Verbraucher inkl. Musterformular (§ 8) ·
  Weitergabe von Identitätsdaten an Behörden/Gerichte (§ 5). **Die AGB sollten vor dem Livegang anwaltlich geprüft werden.**

## Shops (nur auf Anfrage) und Stripe

- Imprint Protection für **Shops** gehört nicht zum Standardangebot für Creator, sondern wird **auf Anfrage und begrenzt**
  individuell vereinbart.
- **Shop-Zahlungen sind für die Zukunft über die Stripe-API geplant** (Einbehalt eines vereinbarten Anteils,
  Steuerabrechnung, Auszahlung nach Steuerrecht). Es gibt in dieser Software **noch keine Zahlungsabwicklung** und keine
  Stripe-Anbindung; sie verwaltet nur **Anfragen** (`/shops/`, Admin → Requests, Mail an `contact@tcd-studio.de`).
- Für eine spätere Umsetzung: Stripe-Schlüssel gehören nie ins Repository, sondern in `data/config.local.php`
  (dort ist noch nichts vorgesehen oder ausgelesen). Ob ein Modell mit Einbehalt und Auszahlung (z. B. Stripe Connect)
  zahlungsdiensterechtlich zulässig ist, muss vorher rechtlich/steuerlich geklärt werden.

## Erste Inbetriebnahme

1. Alle Dateien hochladen (Webroot von tcd-studio.de). Voraussetzungen: PHP ≥ 7.4 mit **pdo_sqlite, GD, mbstring,
   openssl**, funktionierendes `mail()`, Schreibrechte für `data/` und `legal/` (und `sitemap.xml`).
2. **Postfächer anlegen** (alle `@tcd-studio.de`): `contact`, `info`, `imprint`, `privacy-policy`, `no-reply`.
   Wofür: `contact` = Kontaktformular + Anfragen · `imprint` = Freigabe-Benachrichtigungen + Kontakt in den
   Kunden-Impressen · `privacy-policy` = Datenschutz-Kontakt · `info` = allgemein · `no-reply` = Absender aller
   System-Mails (sollte beim selben Mail-Host liegen, wegen SPF/DKIM). Andere Adressen: `data/config.local.php`.
3. **Admin-Konto anlegen**: `https://tcd-studio.de/admin/setup.php` aufrufen. Der Setup-Token steht in der Datei
   `data/setup-token-….txt` (eine Datei, deren Name mit `setup-token-` beginnt; per FTP/SSH lesen). Alternativ per SSH: `php tools/create-admin.php`.
4. Als Admin anmelden → **Account → Zwei-Faktor-Anmeldung aktivieren** (empfohlen) → **Settings → System checks**
   prüfen (alles grün?).
5. Die drei bisherigen Creator-Impressen (PixelCST, CreepyX_470, TTH Projects) sind bereits importiert und live; die alten
   URLs `/legal-notice-<name>/` leiten auf die neuen Adressen weiter.

**Sicherheit:** `data/` enthält die Datenbank (mit privaten Kundendaten!). Idealerweise außerhalb des Webroots
ablegen (Umgebungsvariable `TCD_DATA_DIR` oder `data_dir` in der Hosting-Konfiguration). Mindestens muss `data/.htaccess`
greifen (Apache) – die Datenbankdatei hat einen zufälligen Namen. Regelmäßig sichern (nur `data/` + `legal/`).

## Zu klären (rechtlich/organisatorisch – bitte nicht auf die Software abwälzen)

- **Impressumsschutz rechtlich:** Wer als Impressums-Verantwortlicher auftreten darf und ob die Management-Adresse für
  jeden Creator zulässig ist, hängt von Medien-/E-Commerce-Recht (ECG/MedienG, DDG) ab. Bitte rechtlich prüfen
  lassen und die Texte im Admin-Panel/den Vorlagen entsprechend anpassen.
- **Datenschutzerklärung** muss um Kundenkonten, Impressums-Profile (private Daten werden gespeichert), Mail-Versand
  und Aufbewahrung ergänzt werden. Es gibt Kunden-Funktionen für Datenexport und Löschwunsch; die Löschung selbst
  erledigt ein Admin manuell.
- **Shops/Zahlungen:** Fremdgeld einziehen, Steuerabrechnung erstellen und auszahlen kann erlaubnispflichtig sein
  (Zahlungsdiensterecht) und braucht einen Zahlungsdienstleister. Deshalb bewusst nur „auf Anfrage“.
- **Preise** für den Impressumsschutz sind noch nicht festgelegt (laut AGB bis dahin unentgeltlich). Sobald es Preise
  gibt: Preisinformation im Portal/auf der Seite anzeigen und AGB § 6 prüfen.
- **Streitbeilegung/ODR-Hinweis** in den Impressen: Die EU-Online-Streitbeilegungsplattform (ec.europa.eu/consumers/odr)
  wurde nach meinem Kenntnisstand eingestellt – den Absatz bitte prüfen lassen.
