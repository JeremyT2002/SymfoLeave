# SymfoLeave

Schlanke Abwesenheitsverwaltung mit Symfony: Mitarbeitende tragen Urlaub, Krankheit, Homeoffice & Co. ein, Teamleitungen genehmigen – inklusive Urlaubskonto, Team-Kalender und Feiertagen nach Bundesland.

**Live-Demo:** <https://symfoleave.jeremythelen.de> – ohne Registrierung, ein Klick auf „Als Mitarbeiter:in / Teamleitung / Admin testen“ genügt.

## Features

- **Anträge** mit Zeitraum, halben Tagen am Anfang/Ende und Kommentar; die benötigten Arbeitstage werden live angezeigt.
- **Arbeitstage-Berechnung**: Mo–Fr ohne gesetzliche Feiertage (Yasumi, Bundesland konfigurierbar, Standard NRW), halbe Tage = 0,5, Abwesenheiten über den Jahreswechsel werden anteilig dem jeweiligen Jahr zugerechnet.
- **Urlaubskonto pro Jahr**: Anspruch, genommen, geplant (genehmigt + offen), verbleibend; Warnung bei negativem Saldo.
- **Genehmigungs-Workflow** (Symfony Workflow): Beantragt → Genehmigt/Abgelehnt, Beantragt/Genehmigt → Storniert. Krankheit ist ohne Genehmigung sofort eingetragen.
- **Validierung**: Ende ≥ Start, keine Überschneidung mit eigenen aktiven Abwesenheiten.
- **Team-Kalender**: Monatsansicht Personen × Tage, farbcodiert, Wochenenden und Feiertage hervorgehoben, halbe Tage und offene Anträge erkennbar.
- **Dashboard**: eigenes Urlaubskonto, nächste Abwesenheiten, offene Anträge des Teams (mit Genehmigen/Ablehnen), wer heute fehlt.
- **Admin**: Mitarbeitende, Rollen, Teams, Teamleitungen und Urlaubsanspruch pflegen.
- **Datenschutz**: Bei nicht sichtbaren Arten (z. B. Krankheit) sehen Kolleg:innen nur „abwesend“ – ohne Art und Kommentar.
- **Demo-Modus** (`APP_DEMO=1`): Ein-Klick-Logins, fiktive Firma mit 11 Personen in 2 Teams, Daten relativ zum heutigen Datum, nächtlicher Reset.

## Tech-Stack

PHP 8.2+ · Symfony 7.4 LTS · Doctrine ORM + Migrations · SQLite · Twig · AssetMapper · Tailwind CSS (symfonycasts/tailwind-bundle, ohne Node) · Stimulus · Symfony Security (Voter), Forms, Validator, Workflow · azuyalabs/yasumi · PHPUnit

## Lokales Setup

```bash
git clone https://github.com/JeremyT2002/SymfoLeave.git && cd SymfoLeave
composer install
php bin/console doctrine:migrations:migrate -n
php bin/console app:demo:reset          # oder: php bin/console doctrine:fixtures:load -n
php bin/console tailwind:build --watch & # CSS bauen
symfony serve                            # oder: php -S localhost:8000 -t public
```

Login: einer der Demo-Buttons oder z. B. `max.mueller@nordlicht.example` / `demo`.

Konfiguration (in `.env.local` überschreibbar): `APP_DEMO` (0/1), `HOLIDAY_REGION` (Yasumi-Provider, z. B. `Germany/Bavaria`), `DATABASE_URL`, `TRUSTED_PROXIES`.

### Tests

```bash
php bin/phpunit
```

Unit-Tests für Arbeitstage (Wochenende, Feiertage, halbe Tage, Jahreswechsel) und Urlaubskonto; Functional-Tests für „beantragen → genehmigen“, „Teamleitung kann fremdes Team nicht genehmigen“, Überschneidungen, Datenschutz im Kalender und Demo-Logins.

## Deployment

Ziel: VPS mit nginx oder Apache, PHP-FPM ≥ 8.2 und SQLite – ohne Docker.

Einmalige Einrichtung (als normaler Benutzer mit sudo-Rechten):

```bash
curl -fsSL https://raw.githubusercontent.com/JeremyT2002/SymfoLeave/main/deploy/server-setup.sh | sudo bash
```

Das Skript ist idempotent und erkennt Webserver, PHP-FPM-Version und Web-User selbst. Es ergänzt fehlende PHP-Erweiterungen (ohne Upgrades), klont das Repo nach `/var/www/symfoleave.jeremythelen.de`, erzeugt `.env.local` mit zufälligem `APP_SECRET`, baut Assets, migriert, lädt die Demo-Daten, legt einen **eigenen** vHost an (Backup → Config-Test → Reload, sonst Rollback), nutzt ein vorhandenes Wildcard-Zertifikat oder certbot und richtet den nächtlichen Demo-Reset per Cron ein. Vorher und nachher werden die Statuscodes aller anderen Subdomains verglichen.

Updates: `cd /var/www/symfoleave.jeremythelen.de && bash deploy/deploy.sh` (sichert vorher die SQLite-Datei).

vHost-Vorlagen: [`deploy/nginx.conf`](deploy/nginx.conf), [`deploy/apache.conf`](deploy/apache.conf).

## Demo-Reset

```bash
php bin/console app:demo:reset   # Schema neu aufsetzen + Demo-Daten laden, funktioniert auch in prod ohne Dev-Pakete
```

Live läuft der Reset jede Nacht um 3:30 Uhr.

## Architektur

Controller sind dünn und delegieren an Services: `WorkingDayCalculator` (Arbeitstage pro Jahr, Feiertage über `HolidayProvider`), `VacationAccountCalculator` (Urlaubskonto), `AbsenceManager` (Antrag und Statuswechsel über die Workflow-Komponente) und `MonthCalendarBuilder` (Kalender-Matrix). Berechtigungen prüft ausschließlich der `AbsenceVoter` serverseitig; `AbsenceVisibility` setzt die Datenschutzregel für Kalender und Dashboard um. Demo-Daten erzeugt ein eigener `DemoDataLoader`, den sowohl die Fixtures als auch der Reset-Command nutzen.

## Technische Highlights

- **Fachlogik testbar gekapselt**: Arbeitstage und Urlaubskonto sind reine Services mit Unit-Tests für Grenzfälle (Feiertage je Bundesland, halbe Tage, Jahreswechsel, negatives Saldo).
- **Workflow-Komponente als State Machine** statt verstreuter `if`-Abfragen – erlaubte Statuswechsel sind deklarativ konfiguriert.
- **Rechte über Voter**: Teamleitungen entscheiden nur über ihr eigenes Team und nie über eigene Anträge; jeder POST ist CSRF-geschützt – abgesichert durch Functional-Tests.
- **Privacy by Design**: Sichtbarkeit pro Abwesenheitsart, Kolleg:innen sehen bei Krankheit nur „abwesend“.
- **Modernes Frontend ohne Node-Toolchain**: AssetMapper + Tailwind-Binary + ein kleiner Stimulus-Controller für die Live-Berechnung.
- **Reproduzierbares Deployment**: idempotentes Setup-Skript mit Config-Test, automatischem Rollback und Vorher/Nachher-Vergleich der übrigen Websites.

## Lizenz

MIT – siehe [LICENSE](LICENSE).
