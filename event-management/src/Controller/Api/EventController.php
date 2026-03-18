<?php

namespace App\Controller\Api;

use App\Entity\Event;
use App\Entity\Reservation;
use App\Entity\User;
use App\Repository\EventRepository;
use App\Repository\ReservationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/events', name: 'api_events_')]
class EventController extends AbstractController
{
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(EventRepository $eventRepository, SerializerInterface $serializer, Request $request): JsonResponse
    {
        $filter = $request->query->get('filter', 'all');
        $search = $request->query->get('search', '');
        $page = max(1, $request->query->getInt('page', 1));
        $limit = 10;

        // Get events based on filter
        switch ($filter) {
            case 'upcoming':
                $allEvents = $eventRepository->findUpcomingEvents();
                break;
            case 'past':
                $allEvents = $eventRepository->findPastEvents();
                break;
            default:
                $allEvents = $eventRepository->findAllOrderedByDate();
        }

        // Filter by search term if provided
        if (!empty($search)) {
            $allEvents = array_filter($allEvents, function($event) use ($search) {
                return stripos($event->getTitle(), $search) !== false || 
                       stripos($event->getDescription(), $search) !== false ||
                       stripos($event->getLocation(), $search) !== false;
            });
        }

        // Calculate pagination
        $totalEvents = count($allEvents);
        $totalPages = ceil($totalEvents / $limit);
        $offset = ($page - 1) * $limit;
        $events = array_slice($allEvents, $offset, $limit);

        $data = $serializer->serialize(array_values($events), 'json', ['groups' => 'event:read']);
        
        return new JsonResponse([
            'data' => json_decode($data),
            'pagination' => [
                'current_page' => $page,
                'total_pages' => $totalPages,
                'total_events' => $totalEvents,
                'per_page' => $limit
            ]
        ], Response::HTTP_OK);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(Event $event, SerializerInterface $serializer): JsonResponse
    {
        $data = $serializer->serialize($event, 'json', ['groups' => 'event:read']);
        return new JsonResponse($data, Response::HTTP_OK, [], true);
    }

    #[Route('/{id}/reserve', name: 'reserve', methods: ['POST'])]
    public function reserve(
        Event $event,
        Request $request,
        EntityManagerInterface $entityManager,
        SerializerInterface $serializer,
        ValidatorInterface $validator
    ): JsonResponse {
        // Check authentication
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['error' => 'Authentication required'], Response::HTTP_UNAUTHORIZED);
        }

        if (!$user instanceof User) {
            return $this->json(['error' => 'Invalid user type'], Response::HTTP_UNAUTHORIZED);
        }

        if ($event->getAvailableSeats() <= 0) {
            return $this->json(['error' => 'No available seats'], Response::HTTP_BAD_REQUEST);
        }

        $data = json_decode($request->getContent(), true);

        // Check if user already has a reservation for this event
        $existingReservation = $entityManager->getRepository(Reservation::class)
            ->findOneBy([
                'event' => $event,
                'email' => $user->getEmail()
            ]);

        if ($existingReservation) {
            return $this->json(['error' => 'You already have a reservation for this event'], Response::HTTP_CONFLICT);
        }

        $reservation = new Reservation();
        $reservation->setEvent($event);
        $reservation->setName($data['name'] ?? $user->getFullName());
        $reservation->setEmail($user->getEmail());
        $reservation->setPhone($data['phone'] ?? '');

        $errors = $validator->validate($reservation);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getMessage();
            }
            return $this->json(['errors' => $errorMessages], Response::HTTP_BAD_REQUEST);
        }

        $entityManager->persist($reservation);
        $entityManager->flush();

        $data = $serializer->serialize($reservation, 'json', ['groups' => 'reservation:read']);
        return new JsonResponse($data, Response::HTTP_CREATED, [], true);
    }

    #[Route('/{id}/cancel', name: 'cancel', methods: ['POST'])]
    public function cancel(
        Event $event,
        ReservationRepository $reservationRepository,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['error' => 'Authentication required'], Response::HTTP_UNAUTHORIZED);
        }

        if (!$user instanceof User) {
            return $this->json(['error' => 'Invalid user type'], Response::HTTP_UNAUTHORIZED);
        }

        $reservation = $reservationRepository->findOneBy([
            'event' => $event,
            'email' => $user->getEmail()
        ]);

        if (!$reservation) {
            return $this->json(['error' => 'Reservation not found'], Response::HTTP_NOT_FOUND);
        }

        if ($event->getDate() <= new \DateTime()) {
            return $this->json(['error' => 'Cannot cancel past events'], Response::HTTP_BAD_REQUEST);
        }

        $entityManager->remove($reservation);
        $entityManager->flush();

        return $this->json(['message' => 'Reservation cancelled successfully']);
    }

    #[Route('/{id}/check-availability', name: 'check_availability', methods: ['GET'])]
    public function checkAvailability(Event $event): JsonResponse
    {
        return $this->json([
            'event_id' => $event->getId(),
            'total_seats' => $event->getSeats(),
            'available_seats' => $event->getAvailableSeats(),
            'is_available' => $event->getAvailableSeats() > 0
        ]);
    }
}