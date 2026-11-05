<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\TeamRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: TeamRepository::class)]
class Team
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 120)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 120)]
    private string $name = '';

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?User $lead = null;

    /** @var Collection<int, User> */
    #[ORM\OneToMany(targetEntity: User::class, mappedBy: 'team')]
    #[ORM\OrderBy(['name' => 'ASC'])]
    private Collection $members;

    public function __construct(string $name = '')
    {
        $this->name = $name;
        $this->members = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }

    public function getName(): string { return $this->name; }
    public function setName(string $name): static { $this->name = $name; return $this; }

    public function getLead(): ?User { return $this->lead; }
    public function setLead(?User $lead): static { $this->lead = $lead; return $this; }

    /** @return Collection<int, User> */
    public function getMembers(): Collection { return $this->members; }

    public function __toString(): string { return $this->name; }
}
