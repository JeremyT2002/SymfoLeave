# Entscheidungen

- **Symfony 7.4 LTS** – aktuelle LTS; Symfony 8 bräuchte PHP 8.4, die VPS-Version ist unbekannt.
- **`config.platform.php` = 8.2.0** – Mindestversion von Symfony 7.4; damit läuft `composer install` auf jedem VPS mit PHP ≥ 8.2.
- **VPS-Zugriff übersprungen** – SSH (Port 22) ist aus der Cloud-Umgebung gesperrt; auf Wunsch von Jeremy wird nur gebaut und auf GitHub gepusht. Deployment über `deploy/server-setup.sh`.
- **Start-Check Container** – PHP 8.3.6, Composer 2.8, alle benötigten Erweiterungen vorhanden.
- **Halbe Tage als zwei Booleans** – `startHalfDay` = erster Tag ab Mittag, `endHalfDay` = letzter Tag bis Mittag; deckt Vormittag/Nachmittag ab, ohne ein Enum.
- **Nur offizielle Feiertage (Yasumi `TYPE_OFFICIAL`)** – Heiligabend/Silvester zählen als Arbeitstage, wie gesetzlich vorgesehen.
- **„Genommen“ = genehmigte Tage bis einschließlich heute, „geplant“ = genehmigte zukünftige Tage + offene Anträge** – Abwesenheiten über heute werden tagesgenau aufgeteilt.
- **Teamleitung darf eigene Anträge nicht genehmigen** – das übernimmt ein Admin (Vier-Augen-Prinzip).
- **Admins entscheiden über alle Teams** – Rollenhierarchie Admin ⊃ Teamleitung.
- **Team-Kalender zeigt standardmäßig das eigene Team, umschaltbar auf andere/alle** – Datenschutz über Typ-Sichtbarkeit, nicht über Ausblenden von Personen.
- **Arbeitstage werden beim Antrag gespeichert** – Anzeige schnell; Urlaubskonto rechnet pro Jahr neu, damit Jahreswechsel anteilig stimmen.
- **Live-Arbeitstage per Stimulus + JSON-Endpunkt** – Feiertagslogik bleibt nur auf dem Server.
- **CRUD und Voter von Hand statt `make:*`** – MakerBundle hätte nur weiteren Download über den langsamen Proxy bedeutet; Code folgt denselben Konventionen.
- **`app:demo:reset` = Schema droppen + Migrationen + DemoDataLoader** – funktioniert ohne Dev-Pakete; Fixtures nutzen denselben Loader.
- **Demo-Passwort `demo` für alle Demo-Personen** – zusätzlich zum Ein-Klick-Login nutzbar, fiktive Daten.
- **SQLite-Fremdschlüssel per DBAL-Middleware aktiviert** – sonst greifen `ON DELETE CASCADE/SET NULL` nicht.
- **`TRUSTED_PROXIES` über Umgebungsvariable** – leer bei direktem nginx/Apache + PHP-FPM, setzbar hinter einem Proxy.

## Live-Betrieb (VPS, 05.11.2026)
- **PHP 8.3 statt 8.4** – auf dem Server ist nur 8.3 installiert; Symfony 7.4 läuft damit, die Plattform-Version bleibt 8.2.
- **Apache + mod_php statt PHP-FPM** – so laufen alle bestehenden vHosts auf diesem Server; Vorlage `deploy/apache-mod_php.conf` (FPM-Variante bleibt in `deploy/apache.conf`).
- **Eigene vHost-Datei** `/etc/apache2/sites-available/symfoleave.jeremythelen.de.conf` mit `open_basedir` wie bei den Nachbarprojekten; bestehende vHosts unverändert.
- **Nur HTTP, noch kein SSL** – für `symfoleave.jeremythelen.de` gibt es noch keinen DNS-Eintrag (zuständig: Namecheap), certbot kann ohne DNS nicht prüfen.
- **Rechte über Gruppe `www-data`** – Projekt `root:www-data` lesbar, `var/` `2770` für den Web-User statt 777.
- **Demo-Reset per Crontab von `www-data`** täglich 3:30 Uhr.
- **Demo-Logins per curl geprüft** – Session-Cookie ist in `prod` `secure`, daher bis SSL im Browser erst über HTTPS nutzbar.
- **Live MySQL statt SQLite** – wie SymfoShop auf demselben Server; lokal und in den Tests bleibt SQLite, die Migration hat dafür einen MySQL- und einen SQLite-Zweig.
- **Fremdschlüssel-PRAGMA nur bei SQLite** – eigene Middleware statt der DBAL-Middleware, die das PRAGMA auch an MySQL schicken würde.
- **Datenbank anlegen per `deploy/switch-to-mysql.sh`** – braucht das MySQL-Root-Passwort, das nur Jeremy hat; das Zufallspasswort des App-Benutzers landet nur in `.env.local`.
- **SSL per certbot** für `symfoleave.jeremythelen.de`, nachdem der DNS-Eintrag angelegt war; HTTP leitet auf HTTPS um.
