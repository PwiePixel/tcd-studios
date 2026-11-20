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
   Zum Nachweis lädt er ein **Foto seines Ausweises** (Vorder- und Rückseite) über ein verschlüsseltes Formular hoch
   (siehe „Ausweisprüfung und Löschung“). Das ist vor der ersten Veröffentlichung und bei jeder Änderung von Name oder
   Anschrift nötig.
4. **Kunde reicht ein** → das Team bekommt eine Mail an `imprint@tcd-studio.de`.
5. **Admin prüft** (`/admin/reviews.php`): sieht private Daten, die Änderungen gegenüber der Live-Version und eine
   Vorschau; das Ausweisfoto wird dafür im Browser des Admins entschlüsselt. Freigabe nur mit Häkchen „Identität und
   Anschrift geprüft“. **Ablehnen** nur mit Begründung.
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
- **Aktuelle Fassung:** `2026-11-16` (neu: Ausweisprüfung per Foto § 4 Abs. 1, Datenverarbeitung und Speicherzweck § 5 Abs. 4–7,
  Beenden des Dienstes und Löschung § 7 Abs. 2, 6, 7). Bestandskunden müssen dieser Fassung im Portal zustimmen; die
  Änderungsmitteilung per E-Mail mindestens vier Wochen vorher (§ 10 Abs. 1) ist Sache des Betreibers.
- **Neue Fassung:** Inhaltliche Änderung der AGB → `terms_version` in `app/config.php` erhöhen (z. B. `2027-03-01`). Bis
  Kunden der neuen Fassung im Portal zustimmen, können sie keine neuen Profile anlegen und nichts mehr einreichen (die
  bisher freigegebene Impressumsseite bleibt online).
- **Vorab klären / Entscheidungen** (im Text so festgelegt, bitte bestätigen oder ändern lassen): Dienst **unentgeltlich,
  solange keine Preisinformation angezeigt wird** (§ 6) · Mindestalter 18 (§ 3) · Kündigung durch uns mit 14 Tagen Frist
  (§ 7) · Gerichtsstand Graz für Unternehmer (§ 11) · Rücktrittsrecht für Verbraucher inkl. Musterformular (§ 8) ·
  Weitergabe von Identitätsdaten an Behörden/Gerichte (§ 5) · Aufbewahrung des Ausweisfotos bis zum Dienstende (§ 5 Abs. 4–6) ·
  Legal Hold (§ 5 Abs. 7) · Beenden des Dienstes per Antrag + Bestätigung im Portal (§ 7 Abs. 2, 6).
  **Die AGB sollten vor dem Livegang anwaltlich geprüft werden.**

## Shops (nur auf Anfrage) und Stripe

- Imprint Protection für **Shops** gehört nicht zum Standardangebot für Creator, sondern wird **auf Anfrage und begrenzt**
  individuell vereinbart.
- **Shop-Zahlungen sind für die Zukunft über die Stripe-API geplant** (Einbehalt eines vereinbarten Anteils,
  Steuerabrechnung, Auszahlung nach Steuerrecht). Es gibt in dieser Software **noch keine Zahlungsabwicklung** und keine
  Stripe-Anbindung; sie verwaltet nur **Anfragen** (`/shops/`, Admin → Requests, Mail an `contact@tcd-studio.de`).
- Für eine spätere Umsetzung: Stripe-Schlüssel gehören nie ins Repository, sondern in `data/config.local.php`
  (dort ist noch nichts vorgesehen oder ausgelesen). Ob ein Modell mit Einbehalt und Auszahlung (z. B. Stripe Connect)
  zahlungsdiensterechtlich zulässig ist, muss vorher rechtlich/steuerlich geklärt werden.

## Installation mit installer.sh (empfohlen)

**Code per Git auf den Server holen** (privates Repository → am besten mit einem *Deploy key*, nur Lesen):

```bash
ssh-keygen -t ed25519 -f ~/.ssh/tcd_deploy -N ""      # öffentlichen Teil (~/.ssh/tcd_deploy.pub) bei GitHub eintragen:
                                                        # Repository → Settings → Deploy keys → Add deploy key (ohne Schreibrecht)
GIT_SSH_COMMAND="ssh -i ~/.ssh/tcd_deploy" git clone git@github.com:PwiePixel/tcd-studios.git /var/www/tcd-studio.de
cd /var/www/tcd-studio.de && git config core.sshCommand "ssh -i ~/.ssh/tcd_deploy"
bash installer.sh
```

Updates: `cd /var/www/tcd-studio.de && git pull` (der Installer muss danach nicht erneut laufen). `sitemap.xml`, die Seiten in `legal/`,
`app/data-dir.local`, `.htaccess` und `data/` sind nicht im Git bzw. werden ignoriert – `git pull` kollidiert deshalb nicht mit den
Dateien, die die Website selbst schreibt. Der Ordner `.git/` liegt im Webroot: die `.htaccess`-Regel des Installers sperrt ihn (Apache),
bei nginx die angezeigte Regel `location ^~ /.git` eintragen; `bash installer.sh --check` prüft das.

Per SSH im Webroot (als der Benutzer, unter dem PHP läuft, oder als root):

```bash
bash installer.sh            # geführt, mit Rückfragen
bash installer.sh --check    # nur prüfen, nichts verändern (auch später: Zugriffsschutz, System checks)
bash installer.sh --help     # alle Optionen, auch für den unbeaufsichtigten Betrieb (--yes)
```

Der Installer erledigt die Schritte 1, 3 und 5 unten (und Teile von 4) und kann **gefahrlos erneut gestartet** werden: er ergänzt nur,
was fehlt, ersetzt nie den Datenschlüssel, ein bestehendes Admin-Konto, einen gespeicherten Tresor-Schlüssel oder ein Archiv, in dem
schon Ausweisdateien liegen. Bevor er etwas ändert, zeigt er eine Zusammenfassung; bei „n“ bleibt alles unverändert.

- **Server (als root auf Debian/Ubuntu):** installiert fehlende Pakete per apt (Apache, PHP mit allen Erweiterungen, certbot), legt die
  Apache-Seite `/etc/apache2/sites-available/<domain>.conf` an (DocumentRoot = Webroot, `AllowOverride All`, Upload-Grenzen), aktiviert
  sie samt `rewrite`/`headers` und holt das HTTPS-Zertifikat von Let's Encrypt, sobald die Domain per DNS auf den Server zeigt
  (sonst einfach später erneut starten). Abschalten: `--no-packages`, `--no-webserver`, `--no-https`.
- **Prüft** PHP-Version und Erweiterungen (pdo_sqlite, gd mit JPEG, mbstring, openssl, sodium …), `mail()` und den Webserver.
  Hinweis: geprüft wird das PHP der Kommandozeile; das des Webservers kann abweichen (danach Admin → Settings → System checks).
- **Als root** läuft jeder PHP-Aufruf als Webserver-Benutzer (z. B. `www-data`, automatisch erkannt oder `--web-user`), nie als root.
  Datenverzeichnis, Archiv, `legal/`, die `legal-notice-*`-Ordner und `sitemap.xml` gehören danach diesem Benutzer – das Webroot-Verzeichnis
  selbst und die übrigen Dateien der Website **nicht** (PHP kann `index.html`, `.htaccess` usw. also nicht verändern).
  Als normaler Benutzer (Shared-Hosting) wird angenommen, dass PHP unter demselben Benutzer läuft; sonst `--web-group GRUPPE`.
- **Datenverzeichnis:** schlägt einen Ordner **außerhalb des Webroots** vor (z. B. `../tcd-studio-data`; nur neue oder leere Ordner werden
  angenommen) und schreibt den Pfad in `app/data-dir.local`. So finden Webserver, Cronjob und Kommandozeile die Daten ohne weitere
  Serverkonfiguration (die Umgebungsvariable `TCD_DATA_DIR` hat weiterhin Vorrang). In `data/` bleibt die Markierung `data/.tcd-moved`.
  **Sicherheitsnetz:** Fehlt das Verzeichnis aus `app/data-dir.local` (Speichermedium nicht eingehängt) oder fehlt der Zeiger, obwohl
  `data/.tcd-moved` existiert (z. B. bei einem Update gelöscht), verweigert die Anwendung den Dienst (Fehler 500), statt still eine leere
  Installation mit neuem Schlüssel anzulegen. Abhilfe: Speichermedium einhängen bzw. den Pfad wieder in `app/data-dir.local` schreiben.
  Wer Updates per `rsync --delete` o. Ä. einspielt, muss `app/data-dir.local` ausnehmen.
- **Umzug:** Liegt eine bestehende Installation in `data/` im Webroot, bietet der Installer an, sie nach außen zu verschieben (unbeaufsichtigt:
  `--data-dir PFAD`). Ablauf: altes Verzeichnis schreibschützen (die Website ist kurz nur lesbar), alles kopieren und vergleichen, Zeiger
  umstellen, alte Kopie löschen. Bricht der Umzug ab, bleibt das Original unverändert; ein erneuter Start setzt ihn fort. In den Webroot
  zurück wird nie verschoben.
- **Anwendung:** Datenbank, Datenschlüssel, `site_url`, Postfach-Adressen, Proxy-Einstellung (`--behind-proxy` / `--no-proxy`).
- **Admin-Konto** (Passwort verdeckt eingeben, aus Datei oder Umgebungsvariable, oder zufällig erzeugen – dann wird es direkt nach dem
  Anlegen einmal angezeigt; es wird nie protokolliert). Geheimnisse aus Umgebungsvariablen werden sofort aus der Umgebung entfernt.
- **Archiv:** Verzeichnis (außerhalb des Webroots, neu oder leer; Hinweis, wenn es auf demselben Dateisystem wie die Website liegt) oder S3
  (nur `https://`; Zugangsdaten verdeckt oder über `TCD_S3_ACCESS_KEY`/`TCD_S3_SECRET_KEY`). Danach läuft automatisch der Archiv-Test.
  Liegen im bisherigen Archiv schon Ausweisdateien, verweigert der Installer einen Wechsel (sonst wären sie unauffindbar).
- **Tresor-Schlüssel:** empfohlen „später im Browser“ (Vault-Seite). Alternativ öffentlichen Schlüssel aus Datei übernehmen oder hier erzeugen:
  dann liegt der private Schlüssel kurz in `~/tcd-vault-key/` (nur für root bzw. den aufrufenden Benutzer lesbar) – herunterladen,
  „GESICHERT“ eintippen, der Installer löscht ihn. Unbeaufsichtigt bleibt er liegen und der Installer meldet das als offenes Problem.
- **Cronjob** für `tools/maintenance.php` (täglich; als root in der crontab des Webserver-Benutzers; je Website ein eigener Eintrag).
  Ohne `crontab` wird die Zeile zum Eintragen im Hosting-Panel angezeigt.
- **Schutz:** Regel in der `.htaccess` des Webroots, die `installer.sh` sperrt; bei nginx/Caddy werden die nötigen Sperr-Regeln angezeigt.
  Abschlusstest über HTTP: Startseite, Portal, Admin – und dass `app/`, `tools/`, `backend/`, `docs/`, ggf. `data/` und `installer.sh`
  **nicht** abrufbar sind (Testdateien werden sofort wieder gelöscht).
- **Abschlussbericht** mit offenen Punkten (Postfächer, Zwei-Faktor, Backups, privater Schlüssel …). Protokoll ohne Passwörter:
  `~/tcd-installer.log`. Exit-Code 0 = alles erledigt, 3 = fertig mit offenen Problemen, 1 = Abbruch (nichts Unfertiges), 130 = abgebrochen.

Was der Installer **nicht** kann: Postfächer und DNS/HTTPS einrichten, Zwei-Faktor-Anmeldung aktivieren, den privaten Schlüssel für dich
aufbewahren, die Rechtstexte prüfen. **Sicherung:** das Datenverzeichnis (Pfad steht in `app/data-dir.local`) vollständig sichern –
Datenbank und `config.local.php` immer zusammen. Ohne SSH-Zugang gilt der manuelle Weg:

## Erste Inbetriebnahme (manuell)

1. Alle Dateien hochladen (Webroot von tcd-studio.de). Voraussetzungen: PHP ≥ 7.4 mit **pdo_sqlite, GD, mbstring,
   openssl**, funktionierendes `mail()`, Schreibrechte für `data/` und `legal/` (und `sitemap.xml`).
2. **Postfächer anlegen** (alle `@tcd-studio.de`): `contact`, `info`, `imprint`, `privacy-policy`, `no-reply`.
   Wofür: `contact` = Kontaktformular + Anfragen · `imprint` = Freigabe-Benachrichtigungen + Kontakt in den
   Kunden-Impressen · `privacy-policy` = Datenschutz-Kontakt · `info` = allgemein · `no-reply` = Absender aller
   System-Mails (sollte beim selben Mail-Host liegen, wegen SPF/DKIM). Andere Adressen: `data/config.local.php`.
3. **Admin-Konto anlegen**: `https://tcd-studio.de/admin/setup.php` aufrufen. Der Setup-Token steht in der Datei
   `data/setup-token-<zufall>.txt` (der Name beginnt mit `setup-token-`, danach folgen zwölf zufällige Zeichen; per FTP/SSH
   lesen; die Datei wird nach der Einrichtung gelöscht). Alternativ per SSH: `php tools/create-admin.php`.
4. Als Admin anmelden → **Account → Zwei-Faktor-Anmeldung aktivieren** (empfohlen; für alle Admins, die Ausweisfotos sehen
   dürfen, dringend) → **Settings → System checks** prüfen (alles grün?).
5. **Ausweisprüfung einrichten** (Schlüsselpaar, sicheres Archiv, Sicherung): siehe den nächsten Abschnitt. Ohne gespeicherten
   öffentlichen Schlüssel können Kunden keine Ausweisfotos hochladen.
6. Die drei bisherigen Creator-Impressen (PixelCST, CreepyX_470, TTH Projects) sind bereits importiert und live; die alten
   URLs `/legal-notice-<name>/` leiten auf die neuen Adressen weiter.

**Sicherheit:** `data/` enthält die Datenbank (mit privaten Kundendaten!). Idealerweise außerhalb des Webroots
ablegen (`installer.sh` erledigt das; sonst Umgebungsvariable `TCD_DATA_DIR` oder eine Zeile mit dem absoluten Pfad in
`app/data-dir.local`). Mindestens muss `data/.htaccess`
greifen (Apache) – die Datenbankdatei hat einen zufälligen Namen. Regelmäßig sichern: das Datenverzeichnis (`data/` oder der Pfad aus `app/data-dir.local`) **einschließlich
`config.local.php`** (Datenbank und Schlüssel gehören zusammen, siehe unten) und `legal/`.

## Ausweisprüfung und Löschung

Kunden müssen Identität und Anschrift nachweisen: Sie geben Name und Anschrift im Kundenportal ein und laden ein **Foto ihres
Ausweises** (Vorder- und Rückseite; mindestens ein Bild ist Pflicht, bei Ausweisen ohne Anschrift auf der Vorderseite wird die Rückseite
oder ein Anschriftsnachweis gebraucht) über ein verschlüsseltes Formular hoch. Das Foto ist so geschützt, dass der Webserver es
nach dem Hochladen nicht mehr lesen kann. Warum wir die Daten speichern: Wir sind nur die Management-Adresse (Postfach) und der
Schutz vor der Veröffentlichung der Privatadresse; bei Missbrauch (rechtswidrige Inhalte, Ansprüche, Behördenanfragen) müssen wir
die verantwortliche Person benennen können (AGB § 5, Datenschutzerklärung).
Technik im Detail: `app/lib/crypt.php`, `vault.php`, `erase.php`, [ARCHITECTURE.md](ARCHITECTURE.md).

### So funktioniert es

1. **Upload:** Das Bild wird geprüft und neu kodiert (JPEG ohne Metadaten, längste Seite höchstens 3000 px) und sofort im
   Arbeitsspeicher **hybrid verschlüsselt** (AES-256-GCM; der Schlüssel wird mit dem öffentlichen RSA-Schlüssel verpackt).
   Nur der Chiffretext landet im Eingang `data/vault-inbox` des Webservers. Der **private Schlüssel liegt auf keinem Server.**
   Uploads werden nur über HTTPS angenommen (`vault.require_https`, bitte nicht abschalten). Während des Uploads selbst liegt
   das Bild kurz unverschlüsselt im Arbeitsspeicher und in PHPs temporärer Upload-Datei (wird von PHP am Ende der Anfrage gelöscht).
2. **Prüfung:** Ein Admin entschlüsselt das Foto **im eigenen Browser** mit der privaten Schlüsseldatei (WebCrypto, `js/vault.js`);
   der Schlüssel verlässt den Browser nicht. Er vergleicht Foto und Angaben und gibt frei.
3. **Archivierung:** Bei der Freigabe wird der Chiffretext ins **sichere Archiv** verschoben (Rücklesen + SHA-256-Prüfung); erst
   danach wird die Kopie auf dem Webserver überschrieben und gelöscht. Im Archiv bleibt die Datei, **bis der Kunde den Dienst beendet.**
4. **Dienstende:** automatische, unwiderrufliche Löschung (siehe unten).

Weitere Maßnahmen: Adressdaten liegen verschlüsselt in der Datenbank (libsodium, Schlüssel `data_key`); Zugriff nur für Admins
(Zwei-Faktor-Anmeldung möglich); SQLite `secure_delete` ist an (gelöschte Zeilen werden überschrieben).
Außerdem: Die **Freigabe ist an den geprüften Stand gebunden** (Prüfsummen über Antrag und Ausweisdateien: ändert der Kunde zwischen Öffnen
und Freigeben etwas, wird die Freigabe verweigert). Während eine Einreichung geprüft wird, kann der Kunde die Ausweisbilder nicht mehr
austauschen. Ein **Austausch des öffentlichen Vault-Schlüssels** verlangt zusätzlich das Admin-Passwort. Der Eingang des Webservers wird
täglich aufgeräumt: **nie geprüfte Uploads werden nach 14 Tagen gelöscht** (`tools/maintenance.php`, siehe „Betriebsaufgaben“).

### Einmalige Einrichtung

1. **Schlüsselpaar erzeugen:** Admin → Settings → **Identity vault** (`/admin/vault.php`), Schritt 1: Das Schlüsselpaar wird
   **im Browser** erzeugt (RSA 4096). Den **privaten Schlüssel** (`id-vault-private-key.pem`) sofort herunterladen und **offline**
   aufbewahren (z. B. verschlüsselter USB-Stick im Tresor) und **mindestens eine Sicherung** davon anlegen. Nie per E-Mail oder Chat
   verschicken, nie auf einen Server legen, nie zusammen mit den Datenbank-Backups sichern. **Geht der private Schlüssel verloren,
   sind alle gespeicherten Ausweisfotos für immer unlesbar** – niemand kann sie wiederherstellen. Schritt 2: den **öffentlichen**
   Schlüssel speichern. Wird er später ersetzt, brauchen ältere Dateien weiterhin den **alten** privaten Schlüssel (beide aufbewahren).
   Ohne JavaScript/HTTPS lässt sich das Paar auch mit `openssl` auf einem vertrauenswürdigen Rechner erzeugen (Befehle stehen auf der Seite).
2. **Sicheres Archiv festlegen:** ausschließlich in `data/config.local.php` – **nie** in `app/config.php`, nie ins Repository.
   Diese Datei wird beim ersten Start automatisch angelegt und enthält bereits `db_file` und `data_key`: die Einträge **ergänzen,
   nichts löschen oder die Datei ersetzen** (ohne `data_key` sind die verschlüsselten Adressdaten verloren).
   ```php
   // Variante A: Verzeichnis AUSSERHALB des Webroots, am besten auf einem getrennten Volume/Speichersystem
   'vault' => ['driver' => 'directory', 'dir' => '/pfad/zum/vault-archiv'],

   // Variante B: S3-kompatibler Speicher (nur https-Endpunkt)
   'vault' => [
       'driver' => 's3',
       's3' => ['endpoint' => 'https://…', 'region' => '…', 'bucket' => '…',
                'access_key' => '…', 'secret_key' => '…', 'path_style' => true, 'prefix' => 'tcd-id/'],
   ],
   ```
   Das Verzeichnis muss für PHP beschreibbar sein (Rechte, ggf. `open_basedir`); alternativ die Umgebungsvariable `TCD_VAULT_DIR`.
   Ohne Angabe nutzt das System `data/vault-archive` – das ist **nicht** „getrennt“ und widerspricht dem, was wir in der
   Datenschutzerklärung zusagen. Beim S3-Bucket: nicht öffentlich, Zugangsschlüssel nur mit PUT/GET/DELETE auf diesen Bucket,
   und **keine Versionierung/Object-Lock-Aufbewahrung**, die gelöschte Objekte weiter vorhält (sonst ist die Löschung nicht unwiderruflich).
   Danach auf der Vault-Seite **„Test archive“** ausführen (schreibt, liest und löscht ein Probeobjekt ohne Personendaten) und
   Settings → System checks prüfen. Die PHP-Grenzen `upload_max_filesize`/`post_max_size` müssen zu `vault.max_upload_bytes`
   (8 MB je Bild) passen.
3. **Sicherung:** **`data/config.local.php` immer zusammen mit der Datenbank sichern** – sie enthält den Datenschlüssel `data_key`;
   ohne ihn sind die verschlüsselten Adressdaten nie wieder lesbar. Umgekehrt macht ein Backup mit Datenbank **und** Schlüssel die
   Adressdaten lesbar: Backups verschlüsseln und nur wenigen Personen zugänglich machen. Den **privaten Vault-Schlüssel nicht** in
   dieses Backup legen. Das Archiv (Verzeichnis/Bucket) separat sichern, falls Sie es aufbewahren wollen – und die
   Aufbewahrungsdauer der Backups festlegen (siehe „Rechtliche Hinweise“). Nach einem **Restore** müssen bereits abgeschlossene
   Löschungen erneut durchgeführt werden (Requests → Deletions, Status „completed“, mit der Referenznummer des Kunden), sonst sind gelöschte Daten wieder da.

### Tägliche Routine (Ausweise prüfen)

1. Neue Einreichungen stehen unter **Reviews**. Das Dashboard und die Seite **Identity vault** zeigen, wie viele Ausweisdateien noch im
   Eingang des Webservers liegen (Warnung bei Dateien, die länger als 7 Tage nicht archiviert wurden).
2. Eintrag öffnen → Bereich **Identity documents** → **„Open ID documents and compare“**. Auf der Seite „Identity documents“ stehen die vom
   Kunden eingegebenen Angaben; unter **„Decrypt and show (in your browser)“** die private Schlüsseldatei `id-vault-private-key.pem`
   auswählen → **Entschlüsseln** → Name und Anschrift auf dem Ausweis mit den Angaben vergleichen. Schlüssel und Bild bleiben im Browser
   (Tab schließen bzw. „Forget key and hide images“, wenn Sie fertig sind). **Jeder Aufruf einer Ausweisdatei wird im Audit-Log protokolliert**
   (`identity.file_viewed`).
3. Stimmt alles: zurück zum Review, Häkchen „I verified the identity and address“ + **Freigabe**. Die Dateien werden dabei **automatisch
   archiviert** (Kopie auf dem Webserver wird gelöscht). Scheitert die Archivierung (Archiv nicht erreichbar), bleibt die Freigabe gültig,
   die Datei bleibt verschlüsselt im Eingang, und die Identity-Seite warnt; beheben (Vault-Seite → „Test archive“) und **„Archive now“** drücken.
4. Stimmt etwas nicht: mit Begründung **ablehnen**; der Kunde lädt neu hoch. Ohne Ausweisfoto verweigert das System die Freigabe.
5. **Ausnahme:** Im Entscheidungsformular lässt sich „Identity verified by other proof“ mit einer Pflichtnotiz festhalten (nur den Nachweis
   beschreiben, keine Personendaten kopieren). Das sollte die Ausnahme bleiben und nachvollziehbar sein.
6. Hygiene: Schlüsseldatei nur auf vertrauenswürdigen Geräten verwenden und danach wieder offline legen; entschlüsselte Bilder weder
   herunterladen noch speichern oder weiterleiten; Bildschirm sperren. Nur namentlich benannte Admins mit Zwei-Faktor-Anmeldung.

### Legal Hold (Löschsperre)

Besteht ein Grund, Daten aufzubewahren (offener Rechtsanspruch, Behördenanfrage, gesetzliche Aufbewahrungspflicht), setzt ein Admin unter
**Customers → Kunde → „Legal hold“** das Häkchen und trägt den **Grund** ein (Pflichtfeld, keine Personendaten hineinkopieren; die Notiz wird
mit dem Konto gelöscht). Solange der Legal Hold gilt, wird **keine** Löschung ausgeführt – auch nicht, wenn der Kunde den Dienst beendet:
der Löschantrag bekommt den Status „on hold“, der Kunde sieht den Hinweis, dass wir einige Daten vorerst aufbewahren müssen, und
„Erase now“ ist gesperrt. Fällt der Grund weg, den Legal Hold wieder entfernen. **Wartet eine vom Kunden bereits bestätigte Löschung,
wird sie beim Entfernen des Legal Hold sofort automatisch ausgeführt** (der Kunde hatte ja schon zugestimmt; die Aktion steht im Audit-Log
als `erase.after_hold_release`). Scheitert sie technisch, bleibt der Antrag offen und lässt sich mit **„Erase now“** wiederholen. Nur für den
dokumentierten Grund aufbewahren und den Fall regelmäßig prüfen. Das Dashboard zählt offene Löschanträge, Löschanträge „on hold“ und Kunden
unter Legal Hold (Liste: Requests → Deletions).

### Dienst beenden und Löschen (Ablauf für Kunden)

1. Der Kunde beantragt im Portal das **Ende des Dienstes**. Dabei entsteht automatisch ein **Löschantrag** im System, das Team
   wird per Mail informiert (`mail.admin_notify`), der Kunde bekommt per E-Mail einen **Bestätigungslink** (gültig `termination_token_hours`,
   Standard 24 Stunden). Bis zur Bestätigung kann er den Antrag im Portal wieder zurückziehen.
2. Der Kunde öffnet den Link (angemeldet), setzt das Häkchen, gibt sein Passwort ein und **bestätigt** damit, dass der Dienst endet, alles
   unwiderruflich gelöscht wird und er den Dienst nicht mehr nutzen darf. Ohne Bestätigung passiert nichts.
3. Danach läuft **automatisch und unwiderruflich** die Löschung; der Kunde erhält eine letzte Bestätigungs-E-Mail. Scheitert dabei
   ein Speichersystem (z. B. Archiv nicht erreichbar), bricht der Vorgang ab, **bevor** Datenbank-Daten gelöscht werden: Seiten können
   dann schon offline und einzelne Ausweisdateien schon gelöscht sein, Konto und Adressdaten bleiben bestehen. Der Kunde sieht eine
   ehrliche Fehlermeldung („Teile können bereits gelöscht sein – bitte erneut versuchen“), der Vorgang ist **gefahrlos wiederholbar**
   (bereits gelöschte Dateien werden übersprungen); im Server-Log (`error_log`) steht der Grund. Mit Legal Hold siehe oben.
4. Sonderfall: Eine Kündigung per E-Mail oder durch uns (AGB § 7 Abs. 2 letzter Teil, 3, 4, 7) löst **keine** automatische Löschung aus; die
   Daten sind nach Vertragsende zu löschen, sobald sie nicht mehr gebraucht werden (nicht mehr für gesetzliche Pflichten oder Rechtsansprüche).
   Das ist dann eine **manuelle Aufgabe** des Betreibers: Customers → Kunde → **„Erase now – irrevocable“** (E-Mail-Adresse zur Bestätigung
   eintippen; derselbe Ablauf wie oben, auch mit Prüfung des Legal Hold).

**Gelöscht wird:** die Impressumsseite (offline, Datei in `legal/<name>/` entfernt), die Ausweisdateien im Eingang **und** im Archiv
(Dateien im Verzeichnis-Archiv werden vor dem Löschen überschrieben, soweit das Speichermedium es zulässt; bei S3 per DELETE), alle Impressum-Profile mit allen Versionen und den verschlüsselten
Adressdaten, Anfragen, Tokens und das Konto.

**Bleibt (nur ohne Namen/E-Mail):** Einträge des Audit-Logs, pseudonymisiert als `erased-user#<Nummer>` – **die IP-Adresse und ggf.
der Seitenname (Slug) in den Einträgen bleiben jedoch erhalten**; ein Löschnachweis (Datum, Anzahlen, Referenznummer, keine Personendaten).
**Nicht vom System erfasst:** Backups (werden im Sicherungszyklus überschrieben), gesendete Mails (Mailserver, Postfach `imprint@`),
Server-Logfiles des Hosters, Kopien Dritter von bereits veröffentlichten Impressumsseiten (Suchmaschinen, Archive).

### Betriebsaufgaben und Server-Einstellungen

- **Cronjob (täglich):** `php /pfad/zur/seite/tools/maintenance.php` – löscht nie geprüfte Ausweis-Uploads (älter als 14 Tage) aus dem
  Eingang des Webservers und räumt alte Zähler des Missbrauchsschutzes auf. Das Skript läuft nur auf der Kommandozeile.
  Ohne Cronjob läuft die Aufräumroutine nur gelegentlich nebenbei bei Seitenaufrufen.
- **Upload-Grenzen:** Pro Bild sind 8 MB erlaubt (`vault.max_upload_bytes`). Der Server (`upload_max_filesize`, `post_max_size`,
  ggf. `portal/.user.ini`) muss Anfragen von mindestens 17 MB zulassen, sonst sieht der Kunde nur die Meldung „Der Upload war zu groß“.
  Erfolgreiche Uploads sind auf 12 pro Stunde und Kunde begrenzt.
- **Reverse-Proxy/Load-Balancer:** `trust_forwarded_proto` in `data/config.local.php` nur auf `true` setzen, wenn **alle** Anfragen
  über einen vertrauenswürdigen Proxy kommen, der `X-Forwarded-Proto` selbst setzt. Sonst könnte ein Client „https“ vortäuschen
  und die HTTPS-Pflicht für Ausweis-Uploads umgehen. Ohne Proxy auf `false` lassen (Standard).
- **`data/` schützen:** Der Ordner muss vom Webserver **nicht ausgeliefert** werden (`.htaccess` genügt nur bei Apache). Besser ist
  ein Verzeichnis außerhalb des Webroots (Umgebungsvariable `TCD_DATA_DIR`). Der Chiffretext der Ausweise bindet keine Zusatzdaten
  (AAD) an die Datenbankzeile; das ist bekannt und unkritisch, solange die Datenbank nicht manipuliert werden kann.
- **PHP-OPcache:** Wird `data/config.local.php` von Hand geändert, danach PHP-FPM/Apache neu laden bzw. warten, bis OPcache neu liest.
  Das System erkennt fehlende oder unlesbare Konfiguration und **erzeugt dann keinen neuen Datenschlüssel** (es bricht mit Fehler ab,
  statt vorhandene verschlüsselte Daten unlesbar zu machen).

### Was getestet wurde – und was nicht

*Geprüft* (lokal, Entwicklungsumgebung, mit erfundenen Testdaten und dem Archiv-Treiber `directory`): Schlüsselpaar → Upload zweier
Bilder → Entschlüsselung mit dem privaten Schlüssel → Archivierung (Eingang danach leer, Archivdatei enthält nur Chiffretext) →
Löschantrag → Bestätigung → Konto, Profile, Ausweisdateien (Eingang und Archiv) gelöscht, Audit-Log pseudonymisiert, Löschnachweis
vorhanden → Legal Hold blockiert die Löschung und gibt sie beim Aufheben frei. Außerdem: die Rechtstexte rendern ohne Fehler; ein
S3-Archiv gegen einen **lokalen S3-Nachbau** (Signatur, PUT/GET/DELETE); automatisierte Tests von Kundenportal (511 Prüfungen),
Admin-Bereich (394), Vault/Löschung (82) und ein kompletter Browser-Durchlauf Registrierung → Upload → Freigabe → Dienstende (62).
Eine unabhängige Sicherheitsprüfung hat zwölf Befunde ergeben; sie wurden behoben (u. a. gebundene Freigabe, Schutz vor Verlust des
Datenschlüssels, Passwort bei Schlüsseltausch, Upload-Grenzen, Löschung ohne Reste).

*Nicht geprüft:* ein **echter S3-Anbieter** (Endpunkt-/Signatur-Eigenheiten, Versionierung); **echter Mailversand** (`mail()`,
SPF/DKIM, Zustellung der Bestätigungs-Mail); der Betrieb in der **echten Hosting-Umgebung** (HTTPS, PHP-Limits, `open_basedir`,
Dateirechte, getrenntes Volume); die **Entschlüsselung im Browser** mit echten Ausweisfotos auf verschiedenen Geräten/Browsern;
Wiederherstellung aus einem Backup; Ausfall des Archivs im Echtbetrieb. **Vor dem Livegang** bitte eine Generalprobe mit einem
Testkonto und einem erfundenen Ausweisbild durchspielen (Upload → Prüfung → Archiv → Löschung) und danach prüfen, dass Eingang und Archiv leer sind.

### Rechtliche Hinweise an den Betreiber

> **Bitte vor dem Livegang lesen.** Das sind Hinweise, keine Rechtsberatung. Die AGB- und Datenschutztexte wurden von einem
> KI-Assistenten entworfen und **müssen anwaltlich geprüft werden**.

- **(a) Ausweiskopien über die gesamte Dauer speichern ist eine erhebliche Datenschutzentscheidung.** Datenminimierung und
  Speicherbegrenzung (Art. 5 Abs. 1 lit. c und e DSGVO) sprechen eher für eine kürzere Aufbewahrung. In Deutschland beschränkt
  **§ 20 PAuswG** das Kopieren und Speichern von Personalausweisen und erlaubt es nur unter strengen Voraussetzungen – das ist
  für Kunden aus Deutschland **anwaltlich zu klären** (ebenso, was für Österreich und andere Länder gilt). Eine mildere
  Alternative wäre, das Foto **unmittelbar nach der Prüfung zu löschen** und nur einen **Prüfvermerk** aufzubewahren (Datum, Prüfer,
  geprüfte Angaben, Art des Dokuments); dafür müsste das System geändert werden (derzeit nicht umgesetzt: es archiviert bis zum
  Dienstende). Nicht jeder Ausweis enthält eine Anschrift – ggf. ist zusätzlich ein Anschriftsnachweis nötig. Die Kunden werden
  gebeten, nicht benötigte Angaben (Foto, Dokumentnummer, Zugangsnummer) abzudecken.
- **(b) Die Datenschutzerklärung muss wahr bleiben.** Sie sagt unter anderem: Ausweisfoto nach der Prüfung nur noch verschlüsselt auf
  einem **getrennten, zugriffsgeschützten Speichersystem**; **Zugriffe auf Ausweisfotos werden protokolliert**; Zugriff nur für
  berechtigte Personen; Backups werden im **regelmäßigen Sicherungszyklus überschrieben**; **Auftragsverarbeitungsverträge** mit den
  Dienstleistern für Hosting, E-Mail und Speicher. Bitte sicherstellen bzw. anpassen: tatsächlicher Speicherort und seine Trennung
  (siehe Einrichtung, Schritt 2); wer Zugriff hat (Admin-Konten, Zwei-Faktor); dass der Zugriff im Audit-Log erscheint (`identity.file_viewed`; vor dem
  Livegang kontrollieren); AV-Verträge mit Hoster, Mail- und Speicheranbieter (Art. 28 DSGVO) und ggf. Hinweise zu
  Drittlandübermittlungen; die **konkrete Aufbewahrungsdauer der Backups** (und dass Löschungen nach einem Restore wiederholt
  werden) – diese Frist sollte in der Erklärung genannt werden; eine **Aufbewahrungsfrist für das Audit-Log** (es enthält auch nach der
  Löschung IP-Adressen, siehe oben) und das Löschkonzept für Backups.
- **(c) Dokumentation:** Verarbeitungsverzeichnis (Art. 30 DSGVO) um den Impressumsschutz ergänzen (Konten, Adress- und Identitätsdaten,
  Ausweisfotos, Audit-Log, Empfänger, Fristen, technische und organisatorische Maßnahmen); prüfen, ob eine **Datenschutz-Folgenabschätzung**
  (Art. 35 DSGVO) nötig ist (Ausweisdaten zusammen mit Privatanschriften); einen Ablauf für Datenschutzverletzungen (Art. 33/34 DSGVO,
  z. B. Verlust oder Diebstahl von Backup oder Schlüssel) und für Auskunftsersuchen von Behörden und Dritten festlegen (wer entscheidet,
  wie wird dokumentiert, wird der Kunde informiert).
- **(d) Die AGB- und Datenschutztexte** (`/agb/`, `/terms/`, `/privacy/`) wurden von einem KI-Assistenten entworfen und sind **keine
  Rechtsberatung**. Bitte von einer Rechtsanwältin/einem Rechtsanwalt prüfen lassen, bevor Kunden den Dienst nutzen. Die deutsche Fassung ist verbindlich;
  bei Änderungen immer DE und EN gemeinsam anpassen (gleiche Gliederung, Nummerierung und Querverweise) und `terms_version` erhöhen.

## Zu klären (rechtlich/organisatorisch – bitte nicht auf die Software abwälzen)

- **Impressumsschutz rechtlich:** Wer als Impressums-Verantwortlicher auftreten darf und ob die Management-Adresse für
  jeden Creator zulässig ist, hängt von Medien-/E-Commerce-Recht (ECG/MedienG, DDG) ab. Bitte rechtlich prüfen
  lassen und die Texte im Admin-Panel/den Vorlagen entsprechend anpassen.
- **Datenschutzerklärung:** `/privacy/` enthält jetzt den Abschnitt „Imprint Protection (customer area)“ (Englisch) und direkt
  danach „Impressumsschutz (Kundenbereich)“ (Deutsch, für Österreich maßgeblich). Er muss zur tatsächlichen Praxis passen –
  siehe „Rechtliche Hinweise an den Betreiber“. Kunden können ihre Daten herunterladen und den Dienst beenden; die Löschung
  läuft nach der Bestätigung automatisch (siehe „Ausweisprüfung und Löschung“).
- **Shops/Zahlungen:** Fremdgeld einziehen, Steuerabrechnung erstellen und auszahlen kann erlaubnispflichtig sein
  (Zahlungsdiensterecht) und braucht einen Zahlungsdienstleister. Deshalb bewusst nur „auf Anfrage“.
- **Preise** für den Impressumsschutz sind noch nicht festgelegt (laut AGB bis dahin unentgeltlich). Sobald es Preise
  gibt: Preisinformation im Portal/auf der Seite anzeigen und AGB § 6 prüfen.
- **Streitbeilegung/ODR-Hinweis** in den Impressen: Die EU-Online-Streitbeilegungsplattform (ec.europa.eu/consumers/odr)
  wurde nach meinem Kenntnisstand eingestellt – den Absatz bitte prüfen lassen.
