<?php

declare(strict_types=1);

namespace App\Calendar;

use App\Entity\Absence;
use App\Security\Voter\AbsenceVoter;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Privacy rule: colleagues only see "absent" for types that are not visible to them
 * (e.g. sickness) – no type, no comment. Labels are translated into the current language.
 */
class AbsenceVisibility
{
    public const HIDDEN_COLOR = '#64748b';

    public function __construct(
        private readonly Security $security,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function canSeeDetails(Absence $absence): bool
    {
        return $this->security->isGranted(AbsenceVoter::VIEW_DETAILS, $absence);
    }

    public function canSeeType(Absence $absence): bool
    {
        return (bool) $absence->getType()?->isVisibleToColleagues() || $this->canSeeDetails($absence);
    }

    public function label(Absence $absence): string
    {
        return $this->canSeeType($absence)
            ? $this->translator->trans((string) $absence->getType()?->getName(), domain: 'absence_types')
            : $this->translator->trans('calendar.absent');
    }

    public function color(Absence $absence): string
    {
        return $this->canSeeType($absence) ? (string) $absence->getType()?->getColor() : self::HIDDEN_COLOR;
    }

    public function comment(Absence $absence): ?string
    {
        return $this->canSeeDetails($absence) ? $absence->getComment() : null;
    }
}
