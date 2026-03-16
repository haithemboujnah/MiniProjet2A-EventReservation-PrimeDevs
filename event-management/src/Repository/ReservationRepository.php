<?php

namespace App\Repository;

use App\Entity\Reservation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Reservation>
 */
class ReservationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Reservation::class);
    }

    /**
     * Find reservations by email
     * 
     * @return Reservation[] Returns an array of Reservation objects
     */
    public function findByEmail(string $email): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.email = :email')
            ->setParameter('email', $email)
            ->orderBy('r.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find reservations by event
     * 
     * @return Reservation[] Returns an array of Reservation objects
     */
    public function findByEvent(int $eventId): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.event = :eventId')
            ->setParameter('eventId', $eventId)
            ->orderBy('r.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Count reservations by event
     */
    public function countByEvent(int $eventId): int
    {
        return $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->andWhere('r.event = :eventId')
            ->setParameter('eventId', $eventId)
            ->getQuery()
            ->getSingleScalarResult();
    }
}