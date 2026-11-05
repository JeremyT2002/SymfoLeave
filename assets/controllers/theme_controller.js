import { Controller } from '@hotwired/stimulus';

/*
 * Hell/Dunkel umschalten. Die Wahl wird in localStorage gespeichert; ohne Wahl folgt die Seite dem System.
 * Das erste Setzen der Klasse passiert im <head> (base.html.twig), damit nichts aufblitzt.
 */
export default class extends Controller {
    toggle() {
        const dark = !document.documentElement.classList.contains('dark');
        document.documentElement.classList.toggle('dark', dark);
        try {
            localStorage.setItem('symfoleave-theme', dark ? 'dark' : 'light');
        } catch (e) {
            // privater Modus: nur für diese Seite
        }
    }
}
