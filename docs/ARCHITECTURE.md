# Admin- und Kundensystem (Impressumsschutz) – Architektur

Gebaut für Apache + PHP ≥ 7.4 (PDO SQLite, GD, mail()). Kein Framework, keine externen Abhängigkeiten.
**PHP 7.4-kompatibel**: kein `match`, keine Union-/`mixed`-Typen, kein `str_contains`, keine named args, kein `never`.
Jede Datei: `declare(strict_types=1);`.

## Ziel
tcd-studio.de bietet **Impressumsschutz für Creator** (Impressum unter `/legal/<name>/` mit der Management-Adresse
von TCD-Studio/Agency statt der Privatadresse). Kunden registrieren sich, legen ein Impressum-Profil an, pflegen
Links/Angaben und reichen es ein. Ein **Admin muss jede Änderung freigeben** (Freigabe → statische Seite wird erzeugt).
Weitere Dienste (Creator-Schutz allgemein: „coming soon“; **Shops: nur auf Anfrage**) hängen am Service-Katalog.

## Verzeichnisse
```
app/                  Code (per .htaccess gesperrt). bootstrap.php lädt app/lib/*.php
  lib/*.php           Bibliotheken (Funktionen, Präfixlos, Namespaces: keine)
  views/portal|admin  Templates der Bereiche (Layout: views/layout-portal.php)
  views/site/         Sitejet-Rahmen (Header/Footer) für öffentliche Seiten + Impressum-Seiten
  data/               Seeds (legacy-profiles.php)
portal/*.php          Kunden-Bereich – ein Einstiegsskript je Seite (kein URL-Rewriting nötig)
admin/*.php           Admin-Bereich – dito
legal/<slug>/index.html   ERZEUGTE, freigegebene Impressen (statisch, Design wie bisherige legal-notice-*-Seiten)
data/                 Laufzeitdaten (SQLite, config.local.php, setup-token) – nie committen, per .htaccess gesperrt
css/portal.css        Styles Portal/Admin (Klassen unten)
```
Öffentliche Seiten: `services/`, `imprint-protection/`, `shops/`, `coming-soon/` (jeweils `index.php`, Sitejet-Rahmen).

## Konventionen (verbindlich)
- Einstiegsskript: `require dirname(__DIR__) . '/app/bootstrap.php';` → dann `session_boot('portal'|'admin');`
  → `$user = auth_require('customer'|'admin');` (für Login-/Registrierungsseiten stattdessen `auth_current_user()`).
- **Alle POST-Formulare**: `<?= csrf_field() ?>` und im Skript `csrf_verify();`. State-ändernde Aktionen nur per POST.
- **Ausgabe immer** `h()`; **SQL nur Prepared Statements** (`db_one/db_all/db_exec/db_insert`).
- **Kein Inline-JS/Inline-CSS/Inline-Handler** in Views (CSP: nur `'self'`). Keine `style="…"`-Attribute. JS nur falls zwingend in `js/portal.js`.
  Alles muss **ohne JavaScript** funktionieren.
- Nutzer-Texte in Englisch über `__('Text')`. Keine Übersetzungsdateien (noch).
- Seiten rendern: `render_view('portal/dashboard', ['title'=>__('Dashboard'), ...]);` (Layout wählt `$area` automatisch aus der View-Dir).
- Umleiten nach POST: `redirect('/portal/dashboard.php', ['ok'=>__('Saved.')]);` (Flash `ok` / `error`).
- Jede sicherheits-/inhaltsrelevante Aktion: `audit('action.name', 'profile', $id, ['k'=>'v'])`.
- Fehler an Nutzer: freundliche Meldung, keine Exceptions/Pfade ausgeben. Logging via `error_log`.
- Mails: `send_mail($to, $subject, $plainText)`; Absender/Postfächer nur aus `config('mail.*')`.
  Postfächer: `contact@` (Kontaktformular, Anfragen), `info@`, `imprint@` (Imprint-Reviews/Admin-Benachrichtigungen, Kontakt in Impressen),
  `privacy-policy@` (Datenschutz), `no-reply@` (Absender).

## Datenmodell (SQLite, Schema in app/lib/db.php → `db_schema_steps()`)
users(id,email UNIQUE,name,password_hash,role customer|admin,status pending|active|disabled,email_verified_at,totp_secret,totp_enabled,created_at,last_login_at)
tokens(id,user_id,type verify|reset,token_hash UNIQUE,expires_at,used_at,created_at)
profiles(id,owner_id NULL,slug UNIQUE,status draft|pending|live|suspended|withdrawn,live_revision_id NULL,identity_verified,created_at,updated_at,published_at)
revisions(id,profile_id,number,state draft|submitted|approved|rejected|superseded,public_json,private_json,submitted_at,reviewed_at,reviewed_by,review_note,created_at,updated_at)
services(id,slug UNIQUE,name,summary,details,availability active|on_request|coming_soon|hidden,requestable,sort_order)
service_requests(id,service_slug,user_id NULL,name,email,payload_json,status new|in_progress|accepted|declined|done,admin_note,created_at,updated_at)
audit_log(id,at,actor_id,actor_label,action,target_type,target_id,meta_json,ip)
rate_limits(k PK,hits,window_start)  settings(k PK,v)

**Revision-Logik**: pro Profil höchstens EINE offene Revision (`draft`|`submitted`). Live bleibt die `live_revision_id`,
bis ein Admin die nächste Revision freigibt (alte → `superseded`). Ablehnung: Kunde bearbeitet eine neue Entwurfs-Kopie.
**Öffentliche Daten** (`public_json`, werden im Impressum veröffentlicht):
`display_name`(2–60), `kind`(creator|streamer|shop|other), `purpose`(≤300, Klartext), `links`[{label≤40,url https ≤300}] (≤12),
`show_contact_email`(bool), `contact_email`(nur wenn show_contact_email).
**Private Daten** (`private_json`, NIE veröffentlicht, nur Admin + Eigentümer):
`legal_name`, `address_line1`, `postal_code`, `city`, `country`, `phone`(opt), `vat_id`(opt), `notes`(opt).
Admin-Freigabe verlangt ein Häkchen „Identität/Anschrift geprüft“ (setzt `profiles.identity_verified=1`).
Management-Block im Impressum (Name/Adresse/Telefon/E-Mail/…): `management_config()` (Defaults `config('management')`, DB-`settings` `management.<key>` überschreibt).

## Funktions-Verträge
Foundation (fertig, nicht ändern – Änderungswünsche melden): util/http/db/repo/session/csrf/ratelimit/audit/validate/mail/auth/view/imprint_model.
Siehe Funktionssignaturen direkt in `app/lib/*.php` (Docblocks).

Imprint-Pipeline (Agent „publish“, `app/lib/imprint_publish.php`):
`imprint_render_html(array $profile, array $public): string` (komplette Seite), `imprint_publish(int $profileId): void` (schreibt `legal/<slug>/index.html`,
wirft RuntimeException bei Fehler), `imprint_unpublish(int $profileId): void` (löscht die Datei), `imprint_publish_all(): int`,
`imprint_public_url(string $slug): string`, `imprint_render_fragment(array $profile, array $public): string` (nur der Inhalts-Block für die Admin-/Kunden-Vorschau, ohne Sitejet-Rahmen),
`imprint_write_legacy_redirects(): void` (statische Weiterleitungsseiten `legal-notice-<alt>/index.html` → `/legal/<slug>/`).

## CSS-Klassen (css/portal.css)
Layout: `.pf-container`, `.pf-grid`, `.pf-grid--2`, `.pf-grid--3`, `.pf-card`, `.pf-card h2`, `.pf-muted`, `.pf-stack`, `.pf-row`, `.pf-spacer`
Formulare: `.pf-form`, `.pf-field`, `.pf-field label`, `.pf-field input/select/textarea`, `.pf-help`, `.pf-error-text`, `.pf-check` (Checkbox-Zeile), `.pf-actions`
Buttons: `.pf-btn`, `.pf-btn--primary`, `.pf-btn--danger`, `.pf-btn--ghost`, `.pf-btn--small`
Tabellen: `.pf-table` (in `.pf-table-wrap`), Badges: `.pf-badge` + `--live|--pending|--draft|--rejected|--suspended|--new|--ok|--info`
Meldungen: `.pf-flash`, `.pf-flash--ok`, `.pf-flash--error`, `.pf-notice` (Hinweis-Kasten), `.pf-diff` (`.pf-diff-old`, `.pf-diff-new`)
Statistik: `.pf-stat`, `.pf-stat-number`
