<?php

declare(strict_types=1);

namespace App\Entity;

final class AbsenceStatus
{
    public const REQUESTED = 'requested';
    public const APPROVED = 'approved';
    public const REJECTED = 'rejected';
    public const CANCELLED = 'cancelled';

    /** Statuses that block the time slot (overlap check, vacation account). */
    public const ACTIVE = [self::REQUESTED, self::APPROVED];

    public const LABELS = [
        self::REQUESTED => 'Beantragt',
        self::APPROVED => 'Genehmigt',
        self::REJECTED => 'Abgelehnt',
        self::CANCELLED => 'Storniert',
    ];

    public static function label(string $status): string
    {
        return self::LABELS[$status] ?? $status;
    }
}
