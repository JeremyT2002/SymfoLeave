<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\AbsenceTypeRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AbsenceTypeRepository::class)]
class AbsenceType
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    public function __construct(
        #[ORM\Column(length: 80)]
        private string $name,
        /** Hex color, e.g. #2563eb */
        #[ORM\Column(length: 7)]
        private string $color,
        #[ORM\Column]
        private bool $requiresApproval = true,
        #[ORM\Column]
        private bool $countsAgainstVacation = false,
        #[ORM\Column]
        private bool $visibleToColleagues = true,
    ) {
    }

    public function getId(): ?int { return $this->id; }
    public function getName(): string { return $this->name; }
    public function getColor(): string { return $this->color; }
    public function requiresApproval(): bool { return $this->requiresApproval; }
    public function countsAgainstVacation(): bool { return $this->countsAgainstVacation; }
    public function isVisibleToColleagues(): bool { return $this->visibleToColleagues; }

    public function __toString(): string { return $this->name; }
}
