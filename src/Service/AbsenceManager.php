<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Absence;
use App\Entity\AbsenceStatus;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * Application service for the absence lifecycle (submit, approve, reject, cancel).
 */
class AbsenceManager
{
    public const TRANSITION_APPROVE = 'approve';
    public const TRANSITION_REJECT = 'reject';
    public const TRANSITION_CANCEL = 'cancel';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly WorkingDayCalculator $workingDays,
        #[Target('absence')]
        private readonly WorkflowInterface $workflow,
    ) {
    }

    public function recalculate(Absence $absence): void
    {
        if ($absence->getStartDate() && $absence->getEndDate()) {
            $absence->setWorkingDays($this->workingDays->count(
                $absence->getStartDate(),
                $absence->getEndDate(),
                $absence->isStartHalfDay(),
                $absence->isEndHalfDay(),
            ));
        }
    }

    /** Persist a new request. Types without approval are approved immediately. */
    public function submit(Absence $absence, bool $flush = true): void
    {
        $this->recalculate($absence);
        $absence->setStatus(AbsenceStatus::REQUESTED);
        if ($absence->getType()?->requiresApproval() === false) {
            $this->workflow->apply($absence, self::TRANSITION_APPROVE);
            $absence->recordDecision(null, 'Automatisch genehmigt (keine Genehmigung erforderlich)');
        }
        $this->em->persist($absence);
        if ($flush) {
            $this->em->flush();
        }
    }

    public function can(Absence $absence, string $transition): bool
    {
        return $this->workflow->can($absence, $transition);
    }

    public function apply(Absence $absence, string $transition, User $by, ?string $comment = null): void
    {
        $this->workflow->apply($absence, $transition);
        if ($transition !== self::TRANSITION_CANCEL) {
            $absence->recordDecision($by, $comment);
        }
        $this->em->flush();
    }
}
