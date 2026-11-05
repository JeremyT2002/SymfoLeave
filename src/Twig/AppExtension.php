<?php

declare(strict_types=1);

namespace App\Twig;

use App\Calendar\AbsenceVisibility;
use App\Entity\Absence;
use App\Entity\AbsenceType;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Attribute\AsTwigFilter;
use Twig\Attribute\AsTwigFunction;

final class AppExtension
{
    public function __construct(
        private readonly AbsenceVisibility $visibility,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[AsTwigFunction('absence_label')]
    public function absenceLabel(Absence $absence): string
    {
        return $this->visibility->label($absence);
    }

    #[AsTwigFunction('absence_color')]
    public function absenceColor(Absence $absence): string
    {
        return $this->visibility->color($absence);
    }

    /** Name einer Abwesenheitsart in der aktuellen Sprache (gespeichert ist der deutsche Name). */
    #[AsTwigFilter('type_name')]
    public function typeName(?AbsenceType $type): string
    {
        return null === $type ? '' : $this->translator->trans($type->getName(), domain: 'absence_types');
    }

    /** 2.0 → "2", 2.5 → "2,5" (de/fr) bzw. "2.5" (en) */
    #[AsTwigFilter('days')]
    public function formatDays(float|int|string|null $value): string
    {
        $decimal = 'en' === $this->translator->getLocale() ? '.' : ',';
        $formatted = number_format((float) $value, 1, $decimal, '');

        return str_ends_with($formatted, $decimal.'0') ? substr($formatted, 0, -2) : $formatted;
    }

    /** Datum im Format der aktuellen Sprache; $patternKey verweist auf format.* in den Übersetzungen. */
    #[AsTwigFilter('ldate')]
    public function localizedDate(\DateTimeInterface $date, string $patternKey = 'format.date'): string
    {
        $formatter = new \IntlDateFormatter(
            $this->translator->getLocale(),
            \IntlDateFormatter::NONE,
            \IntlDateFormatter::NONE,
            $date->getTimezone(),
            null,
            $this->translator->trans($patternKey),
        );

        return (string) $formatter->format($date);
    }
}
