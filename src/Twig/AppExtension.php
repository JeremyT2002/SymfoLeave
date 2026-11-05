<?php

declare(strict_types=1);

namespace App\Twig;

use App\Calendar\AbsenceVisibility;
use App\Entity\Absence;
use Twig\Attribute\AsTwigFilter;
use Twig\Attribute\AsTwigFunction;

final class AppExtension
{
    public function __construct(private readonly AbsenceVisibility $visibility)
    {
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

    /** 2.0 → "2", 2.5 → "2,5" */
    #[AsTwigFilter('days')]
    public function formatDays(float|int|string|null $value): string
    {
        $formatted = number_format((float) $value, 1, ',', '.');

        return str_ends_with($formatted, ',0') ? substr($formatted, 0, -2) : $formatted;
    }
}
