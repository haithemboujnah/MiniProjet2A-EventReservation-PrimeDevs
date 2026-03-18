<?php

namespace App\Controller\Api;

use App\Entity\User;
use App\Repository\ReservationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/api/reservations', name: 'api_reservations_')]
class ReservationController extends AbstractController
{
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(ReservationRepository $reservationRepository, SerializerInterface $serializer): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['error' => 'Authentication required'], Response::HTTP_UNAUTHORIZED);
        }

        if (!$user instanceof User) {
            return $this->json(['error' => 'Invalid user type'], Response::HTTP_UNAUTHORIZED);
        }

        $reservations = $reservationRepository->findByEmail($user->getEmail());
        
        $data = $serializer->serialize($reservations, 'json', ['groups' => 'reservation:read']);
        return new JsonResponse($data, Response::HTTP_OK, [], true);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(int $id, ReservationRepository $reservationRepository, SerializerInterface $serializer): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['error' => 'Authentication required'], Response::HTTP_UNAUTHORIZED);
        }

        if (!$user instanceof User) {
            return $this->json(['error' => 'Invalid user type'], Response::HTTP_UNAUTHORIZED);
        }

        $reservation = $reservationRepository->find($id);
        
        if (!$reservation) {
            return $this->json(['error' => 'Reservation not found'], Response::HTTP_NOT_FOUND);
        }

        if ($reservation->getEmail() !== $user->getEmail()) {
            return $this->json(['error' => 'Access denied'], Response::HTTP_FORBIDDEN);
        }

        $data = $serializer->serialize($reservation, 'json', ['groups' => 'reservation:read']);
        return new JsonResponse($data, Response::HTTP_OK, [], true);
    }

    #[Route('/upcoming', name: 'upcoming', methods: ['GET'])]
    public function upcoming(ReservationRepository $reservationRepository, SerializerInterface $serializer): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['error' => 'Authentication required'], Response::HTTP_UNAUTHORIZED);
        }

        if (!$user instanceof User) {
            return $this->json(['error' => 'Invalid user type'], Response::HTTP_UNAUTHORIZED);
        }

        $allReservations = $reservationRepository->findByEmail($user->getEmail());
        $now = new \DateTime();
        
        $upcoming = array_filter($allReservations, function($reservation) use ($now) {
            return $reservation->getEvent()->getDate() > $now;
        });

        $data = $serializer->serialize(array_values($upcoming), 'json', ['groups' => 'reservation:read']);
        return new JsonResponse($data, Response::HTTP_OK, [], true);
    }

    #[Route('/past', name: 'past', methods: ['GET'])]
    public function past(ReservationRepository $reservationRepository, SerializerInterface $serializer): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['error' => 'Authentication required'], Response::HTTP_UNAUTHORIZED);
        }

        if (!$user instanceof User) {
            return $this->json(['error' => 'Invalid user type'], Response::HTTP_UNAUTHORIZED);
        }

        $allReservations = $reservationRepository->findByEmail($user->getEmail());
        $now = new \DateTime();
        
        $past = array_filter($allReservations, function($reservation) use ($now) {
            return $reservation->getEvent()->getDate() <= $now;
        });

        $data = $serializer->serialize(array_values($past), 'json', ['groups' => 'reservation:read']);
        return new JsonResponse($data, Response::HTTP_OK, [], true);
    }
}