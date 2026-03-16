<?php

namespace App\Controller\Api;

use App\Entity\Event;
use App\Entity\Reservation;
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
    public function list(EventRepository $eventRepository, SerializerInterface $serializer): JsonResponse
    {
        $events = $eventRepository->findAllOrderedByDate();
        $data = $serializer->serialize($events, 'json', ['groups' => 'event:read']);
        
        return new JsonResponse($data, Response::HTTP_OK, [], true);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(Event $event, SerializerInterface $serializer): JsonResponse
    {
        $data = $serializer->serialize($event, 'json', ['groups' => 'event:read']);
        return new JsonResponse($data, Response::HTTP_OK, [], true);
    }

}