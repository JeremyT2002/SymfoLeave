<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Absence;
use App\Entity\AbsenceStatus;
use App\Entity\Team;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Absence> */
class AbsenceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Absence::class);
    }

    public function countOverlapping(User $user, \DateTimeImmutable $start, \DateTimeImmutable $end, ?int $excludeId): int
    {
        $qb = $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->where('a.user = :user')
            ->andWhere('a.status IN (:active)')
            ->andWhere('a.startDate <= :end AND a.endDate >= :start')
            ->setParameter('user', $user)
            ->setParameter('active', AbsenceStatus::ACTIVE)
            ->setParameter('start', $start, 'date_immutable')
            ->setParameter('end', $end, 'date_immutable');
        if ($excludeId !== null) {
            $qb->andWhere('a.id != :id')->setParameter('id', $excludeId);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /** @return list<Absence> active absences of a user touching the given year */
    public function findActiveForUserInYear(User $user, int $year): array
    {
        return $this->createQueryBuilder('a')
            ->join('a.type', 't')->addSelect('t')
            ->where('a.user = :user')
            ->andWhere('a.status IN (:active)')
            ->andWhere('a.startDate <= :end AND a.endDate >= :start')
            ->setParameter('user', $user)
            ->setParameter('active', AbsenceStatus::ACTIVE)
            ->setParameter('start', new \DateTimeImmutable("$year-01-01"), 'date_immutable')
            ->setParameter('end', new \DateTimeImmutable("$year-12-31"), 'date_immutable')
            ->getQuery()->getResult();
    }

    /** @return list<Absence> */
    public function findForUser(User $user): array
    {
        return $this->createQueryBuilder('a')
            ->join('a.type', 't')->addSelect('t')
            ->where('a.user = :user')
            ->setParameter('user', $user)
            ->orderBy('a.startDate', 'DESC')
            ->getQuery()->getResult();
    }

    /** @return list<Absence> */
    public function findUpcomingForUser(User $user, \DateTimeImmutable $today, int $limit = 5): array
    {
        return $this->createQueryBuilder('a')
            ->join('a.type', 't')->addSelect('t')
            ->where('a.user = :user')
            ->andWhere('a.status IN (:active)')
            ->andWhere('a.endDate >= :today')
            ->setParameter('user', $user)
            ->setParameter('active', AbsenceStatus::ACTIVE)
            ->setParameter('today', $today, 'date_immutable')
            ->orderBy('a.startDate', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()->getResult();
    }

    /**
     * Pending requests a decider may act on. Null team = all teams (admin).
     *
     * @return list<Absence>
     */
    public function findPending(?Team $team): array
    {
        $qb = $this->createQueryBuilder('a')
            ->join('a.user', 'u')->addSelect('u')
            ->join('a.type', 't')->addSelect('t')
            ->where('a.status = :s')
            ->setParameter('s', AbsenceStatus::REQUESTED)
            ->orderBy('a.startDate', 'ASC');
        if ($team !== null) {
            $qb->andWhere('u.team = :team')->setParameter('team', $team);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Active absences in a date range, optionally limited to some users.
     *
     * @param list<User>|null $users
     *
     * @return list<Absence>
     */
    public function findActiveInRange(\DateTimeImmutable $start, \DateTimeImmutable $end, ?array $users = null): array
    {
        $qb = $this->createQueryBuilder('a')
            ->join('a.user', 'u')->addSelect('u')
            ->join('a.type', 't')->addSelect('t')
            ->where('a.status IN (:active)')
            ->andWhere('a.startDate <= :end AND a.endDate >= :start')
            ->setParameter('active', AbsenceStatus::ACTIVE)
            ->setParameter('start', $start, 'date_immutable')
            ->setParameter('end', $end, 'date_immutable')
            ->orderBy('u.name', 'ASC');
        if ($users !== null) {
            $qb->andWhere('a.user IN (:users)')->setParameter('users', $users);
        }

        return $qb->getQuery()->getResult();
    }
}
