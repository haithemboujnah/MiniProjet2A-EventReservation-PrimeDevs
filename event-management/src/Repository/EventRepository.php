<?php

namespace App\Repository;

use App\Entity\Event;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Event>
 */
class EventRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Event::class);
    }

    /**
     * @return Event[] Returns an array of Event objects ordered by date
     */
    public function findAllOrderedByDate(): array
    {
        return $this->createQueryBuilder('e')
            ->orderBy('e.date', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Event[] Returns upcoming events
     */
    public function findUpcomingEvents(): array
    {
        return $this->createQueryBuilder('e')
            ->where('e.date > :now')
            ->setParameter('now', new \DateTime())
            ->orderBy('e.date', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Event[] Returns past events
     */
    public function findPastEvents(): array
    {
        return $this->createQueryBuilder('e')
            ->where('e.date <= :now')
            ->setParameter('now', new \DateTime())
            ->orderBy('e.date', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return string[] Returns all unique locations
     */
    public function findAllLocations(): array
    {
        return $this->createQueryBuilder('e')
            ->select('DISTINCT e.location')
            ->orderBy('e.location', 'ASC')
            ->getQuery()
            ->getSingleColumnResult();
    }

    /**
     * @return Event[] Search events by criteria
     */
    public function searchEvents(string $searchTerm, ?string $filter = 'upcoming', ?string $location = null): array
    {
        $qb = $this->createQueryBuilder('e');

        // Apply filter
        if ($filter === 'upcoming') {
            $qb->where('e.date > :now')
               ->setParameter('now', new \DateTime());
        } elseif ($filter === 'past') {
            $qb->where('e.date <= :now')
               ->setParameter('now', new \DateTime());
        }

        // Apply search
        if (!empty($searchTerm)) {
            $qb->andWhere('e.title LIKE :search OR e.description LIKE :search OR e.location LIKE :search')
               ->setParameter('search', '%' . $searchTerm . '%');
        }

        // Apply location filter
        if (!empty($location)) {
            $qb->andWhere('e.location = :location')
               ->setParameter('location', $location);
        }

        // Order by date
        if ($filter === 'past') {
            $qb->orderBy('e.date', 'DESC');
        } else {
            $qb->orderBy('e.date', 'ASC');
        }

        return $qb->getQuery()->getResult();
    }
}