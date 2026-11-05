<?php

declare(strict_types=1);

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

#[\Attribute(\Attribute::TARGET_CLASS)]
final class NoOverlappingAbsence extends Constraint
{
    public string $message = 'absence.overlap';

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
