<?php

declare(strict_types=1);

namespace App\Calendar;

use App\Entity\Absence;
use App\Security\Voter\AbsenceVoter;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Privacy rule: colleagues only see "abwesend" for types that are not visible to them
 * (e.g. sickness) – no type, no comment.
 */
class AbsenceVisibility
{
    public const HIDDEN_LABEL = 'abwesend';
    public const HIDDEN_COLOR = '#64748b';

    public function __construct(private readonly Security $security)
    {
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
        return $this->canSeeType($absence) ? (string) $absence->getType()?->getName() : self::HIDDEN_LABEL;
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
