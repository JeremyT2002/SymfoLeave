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
