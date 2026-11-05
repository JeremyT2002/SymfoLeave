<?php

declare(strict_types=1);

namespace App\Validator;

use App\Entity\Absence;
use App\Repository\AbsenceRepository;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class NoOverlappingAbsenceValidator extends ConstraintValidator
{
    public function __construct(private readonly AbsenceRepository $absences)
    {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof NoOverlappingAbsence) {
            throw new UnexpectedTypeException($constraint, NoOverlappingAbsence::class);
        }
        if (!$value instanceof Absence || !$value->isActive()) {
            return;
        }
        $start = $value->getStartDate();
        $end = $value->getEndDate();
        if ($start === null || $end === null || $end < $start) {
            return;
        }
        if ($this->absences->countOverlapping($value->getUser(), $start, $end, $value->getId()) > 0) {
            $this->context->buildViolation($constraint->message)->atPath('startDate')->addViolation();
        }
    }
}
