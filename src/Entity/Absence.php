<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\AbsenceRepository;
use App\Validator\NoOverlappingAbsence;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity(repositoryClass: AbsenceRepository::class)]
#[ORM\Index(columns: ['start_date', 'end_date'])]
#[ORM\HasLifecycleCallbacks]
#[NoOverlappingAbsence]
class Absence
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: 'absence.type_required')]
    private ?AbsenceType $type = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    #[Assert\NotNull(message: 'absence.start_required')]
    private ?\DateTimeImmutable $startDate = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    #[Assert\NotNull(message: 'absence.end_required')]
    #[Assert\GreaterThanOrEqual(propertyPath: 'startDate', message: 'absence.end_before_start')]
    private ?\DateTimeImmutable $endDate = null;

    /** First day starts at noon (only the afternoon is taken). */
    #[ORM\Column]
    private bool $startHalfDay = false;

    /** Last day ends at noon (only the morning is taken). */
    #[ORM\Column]
    private bool $endHalfDay = false;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(max: 1000)]
    private ?string $comment = null;

    #[ORM\Column(length: 20)]
    private string $status = AbsenceStatus::REQUESTED;

    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 1)]
    private string $workingDays = '0.0';

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?User $decidedBy = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(max: 1000)]
    private ?string $decisionComment = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $decidedAt = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct(User $user)
    {
        $this->user = $user;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = $this->createdAt;
    }

    #[ORM\PreUpdate]
    public function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    #[Assert\Callback]
    public function validateHalfDays(ExecutionContextInterface $context): void
    {
        if ($this->startDate && $this->endDate && $this->startDate == $this->endDate
            && $this->startHalfDay && $this->endHalfDay) {
            $context->buildViolation('absence.single_day_half')
                ->atPath('endHalfDay')->addViolation();
        }
    }

    public function getId(): ?int { return $this->id; }
    public function getUser(): User { return $this->user; }

    public function getType(): ?AbsenceType { return $this->type; }
    public function setType(?AbsenceType $type): static { $this->type = $type; return $this; }

    public function getStartDate(): ?\DateTimeImmutable { return $this->startDate; }
    public function setStartDate(?\DateTimeImmutable $d): static { $this->startDate = $d?->setTime(0, 0); return $this; }

    public function getEndDate(): ?\DateTimeImmutable { return $this->endDate; }
    public function setEndDate(?\DateTimeImmutable $d): static { $this->endDate = $d?->setTime(0, 0); return $this; }

    public function isStartHalfDay(): bool { return $this->startHalfDay; }
    public function setStartHalfDay(bool $v): static { $this->startHalfDay = $v; return $this; }

    public function isEndHalfDay(): bool { return $this->endHalfDay; }
    public function setEndHalfDay(bool $v): static { $this->endHalfDay = $v; return $this; }

    public function getComment(): ?string { return $this->comment; }
    public function setComment(?string $c): static { $this->comment = $c !== null && trim($c) !== '' ? trim($c) : null; return $this; }

    /** Used by the workflow marking store. */
    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): static { $this->status = $status; return $this; }
    public function getStatusLabel(): string { return AbsenceStatus::label($this->status); }
    public function isActive(): bool { return in_array($this->status, AbsenceStatus::ACTIVE, true); }

    public function getWorkingDays(): float { return (float) $this->workingDays; }
    public function setWorkingDays(float $days): static { $this->workingDays = number_format($days, 1, '.', ''); return $this; }

    public function getDecidedBy(): ?User { return $this->decidedBy; }
    public function getDecisionComment(): ?string { return $this->decisionComment; }
    public function getDecidedAt(): ?\DateTimeImmutable { return $this->decidedAt; }

    public function recordDecision(?User $by, ?string $comment): static
    {
        $this->decidedBy = $by;
        $this->decisionComment = $comment !== null && trim($comment) !== '' ? trim($comment) : null;
        $this->decidedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function setCreatedAt(\DateTimeImmutable $at): static { $this->createdAt = $at; return $this; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }

    public function covers(\DateTimeImmutable $day): bool
    {
        $day = $day->setTime(0, 0);
        return $this->startDate <= $day && $this->endDate >= $day;
    }

    /** Is only half of the given day taken? */
    public function isHalfOn(\DateTimeImmutable $day): bool
    {
        $day = $day->setTime(0, 0);
        return ($this->startHalfDay && $day == $this->startDate) || ($this->endHalfDay && $day == $this->endDate);
    }
}
