<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
#[UniqueEntity(fields: ['email'], message: 'user.email_taken')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    public const ROLE_EMPLOYEE = 'ROLE_EMPLOYEE';
    public const ROLE_TEAM_LEAD = 'ROLE_TEAM_LEAD';
    public const ROLE_ADMIN = 'ROLE_ADMIN';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 120)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 120)]
    private string $name = '';

    #[ORM\Column(length: 180, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Email]
    private string $email = '';

    /** @var list<string> */
    #[ORM\Column]
    private array $roles = [];

    #[ORM\Column(nullable: true)]
    private ?string $password = null;

    #[ORM\ManyToOne(inversedBy: 'members')]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Team $team = null;

    #[ORM\Column]
    #[Assert\Range(min: 0, max: 60)]
    private int $annualVacationDays = 30;

    public function __construct(string $name = '', string $email = '')
    {
        $this->name = $name;
        $this->email = $email;
    }

    public function getId(): ?int { return $this->id; }

    public function getName(): string { return $this->name; }
    public function setName(string $name): static { $this->name = $name; return $this; }

    public function getEmail(): string { return $this->email; }
    public function setEmail(string $email): static { $this->email = mb_strtolower($email); return $this; }

    public function getUserIdentifier(): string
    {
        return $this->email !== '' ? $this->email : 'anonymous';
    }

    /** @return list<string> */
    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = self::ROLE_EMPLOYEE;

        return array_values(array_unique($roles));
    }

    /** @return list<string> stored roles only */
    public function getStoredRoles(): array { return $this->roles; }

    /** @param list<string> $roles */
    public function setRoles(array $roles): static
    {
        $this->roles = array_values(array_unique($roles));
        return $this;
    }

    public function hasRole(string $role): bool
    {
        return in_array($role, $this->getRoles(), true);
    }

    public function getPassword(): ?string { return $this->password; }
    public function setPassword(?string $password): static { $this->password = $password; return $this; }

    public function eraseCredentials(): void {}

    public function getTeam(): ?Team { return $this->team; }
    public function setTeam(?Team $team): static { $this->team = $team; return $this; }

    public function getAnnualVacationDays(): int { return $this->annualVacationDays; }
    public function setAnnualVacationDays(int $days): static { $this->annualVacationDays = $days; return $this; }

    public function isLeadOf(?Team $team): bool
    {
        return $team !== null && $team->getLead() === $this;
    }

    public function getInitials(): string
    {
        $parts = preg_split('/\s+/', trim($this->name)) ?: [];
        $initials = '';
        foreach (array_slice($parts, 0, 2) as $p) {
            $initials .= mb_strtoupper(mb_substr($p, 0, 1));
        }
        return $initials;
    }

    public function __toString(): string { return $this->name; }
}
