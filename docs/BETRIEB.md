# Impressumsschutz-System – Betriebsanleitung

tcd-studio.de bietet **Impressumsschutz für Creator/Streamer**: Jeder Kunde bekommt ein eigenes Impressum unter
`https://tcd-studio.de/legal/<name>/`, in dem die **Management-Adresse von TCD-Studio/Agency** steht statt seiner
Privatadresse. Die echten Daten des Kunden bleiben intern (nur der Kunde selbst und das Admin-Team sehen sie).

## Die Bereiche

| Bereich | URL | Wer |
|---|---|---|
| Kundenbereich | `/portal/` | Creator / Shop-Betreiber (Registrierung mit E-Mail-Bestätigung) |
| Admin-Panel | `/admin/` | Team (eigene Admin-Konten, optional 2-Faktor per Authenticator-App) |
| Impressen (öffentlich) | `/legal/<name>/` | jeder – statische Seiten, werden nach Freigabe erzeugt |
| Dienste | `/services/`, `/imprint-protection/`, `/shops/` | öffentlich |
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

## Shops (nur auf Anfrage)

`/shops/` beschreibt das Konzept (Anbindung an unser Zahlungssystem, Einbehalt eines vereinbarten Anteils,
Steuerabrechnung, Auszahlung nach Steuerrecht) und nimmt **Anfragen** entgegen. In der Anlaufphase ist das **nur auf
Anfrage und begrenzt**. Anfragen landen im Admin-Panel unter „Requests“ und per Mail bei `contact@tcd-studio.de`.
Es gibt **noch keine Zahlungsabwicklung** in dieser Software – das System verwaltet nur Anfragen.

## Erste Inbetriebnahme

1. Alle Dateien hochladen (Webroot von tcd-studio.de). Voraussetzungen: PHP ≥ 7.4 mit **pdo_sqlite, GD, mbstring,
   openssl**, funktionierendes `mail()`, Schreibrechte für `data/` und `legal/` (und `sitemap.xml`).
2. **Postfächer anlegen** (alle `@tcd-studio.de`): `contact`, `info`, `imprint`, `privacy-policy`, `no-reply`.
   Wofür: `contact` = Kontaktformular + Anfragen · `imprint` = Freigabe-Benachrichtigungen + Kontakt in den
   Kunden-Impressen · `privacy-policy` = Datenschutz-Kontakt · `info` = allgemein · `no-reply` = Absender aller
   System-Mails (sollte beim selben Mail-Host liegen, wegen SPF/DKIM). Andere Adressen: `data/config.local.php`.
3. **Admin-Konto anlegen**: `https://tcd-studio.de/admin/setup.php` aufrufen. Der Setup-Token steht in der Datei
   `data/setup-token.txt` (per FTP/SSH lesen). Alternativ per SSH: `php tools/create-admin.php`.
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
- **AGB/Preise** für den Impressumsschutz gibt es noch nicht; auf den Seiten steht bewusst „auf Anfrage“.
