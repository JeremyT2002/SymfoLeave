import { Controller } from '@hotwired/stimulus';

/*
 * Live preview of the working days for an absence request.
 * The calculation itself happens on the server (holidays via Yasumi).
 */
export default class extends Controller {
    static targets = ['start', 'end', 'startHalf', 'endHalf', 'output', 'detail'];
    static values = { url: String };

    connect() {
        this.update();
    }

    async update() {
        const start = this.startTarget.value;
        const end = this.endTarget.value;
        if (!start || !end) {
            this.render('–', '');
            return;
        }
        if (end < start) {
            this.render('–', 'Das Ende liegt vor dem Start.');
            return;
        }

        const params = new URLSearchParams({
            start,
            end,
            startHalf: this.startHalfTarget.checked ? '1' : '0',
            endHalf: this.endHalfTarget.checked ? '1' : '0',
        });

        try {
            const response = await fetch(`${this.urlValue}?${params}`, { headers: { Accept: 'application/json' } });
            const data = await response.json();
            if (data.days === null) {
                this.render('–', '');
                return;
            }
            const years = Object.entries(data.perYear || {});
            const detail = years.length > 1
                ? years.map(([year, days]) => `${year}: ${this.format(days)}`).join(' · ')
                : '';
            this.render(this.format(data.days), detail);
        } catch (e) {
            this.render('–', '');
        }
    }

    format(days) {
        return days.toLocaleString('de-DE', { maximumFractionDigits: 1 });
    }

    render(text, detail) {
        this.outputTarget.textContent = text;
        this.detailTarget.textContent = detail;
    }
}
