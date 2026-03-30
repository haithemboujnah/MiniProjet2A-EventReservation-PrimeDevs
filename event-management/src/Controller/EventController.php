<?php

namespace App\Controller;

use App\Entity\Event;
use App\Entity\Reservation;
use App\Entity\User;
use App\Form\ReservationType;
use App\Repository\EventRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Service\EmailService;

class EventController extends AbstractController
{
    public function __construct(private EmailService $emailService) {}

    #[Route('/', name: 'app_home')]
    public function index(EventRepository $eventRepository): Response
    {
        $upcomingEvents = $eventRepository->findUpcomingEvents();
        
        $upcomingEvents = array_slice($upcomingEvents, 0, 6);

        return $this->render('event/index.html.twig', [
            'events' => $upcomingEvents,
        ]);
    }

    #[Route('/events', name: 'app_events_list')]
    public function list(Request $request, EventRepository $eventRepository): Response
    {
        $filter = $request->query->get('filter', 'all');
        $search = $request->query->get('search', '');
        $page = max(1, $request->query->getInt('page', 1));
        $limit = 4; 

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

        if (!empty($search)) {
            $allEvents = array_filter($allEvents, function($event) use ($search) {
                return stripos($event->getTitle(), $search) !== false || 
                       stripos($event->getDescription(), $search) !== false ||
                       stripos($event->getLocation(), $search) !== false;
            });
        }

        $totalEvents = count($allEvents);
        $totalPages = ceil($totalEvents / $limit);
        
        $offset = ($page - 1) * $limit;
        $events = array_slice($allEvents, $offset, $limit);

        return $this->render('event/list.html.twig', [
            'events' => $events,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'totalEvents' => $totalEvents,
            'limit' => $limit
        ]);
    }

    #[Route('/event/{id}', name: 'app_event_show')]
    public function show(Event $event): Response
    {
        return $this->render('event/show.html.twig', [
            'event' => $event,
        ]);
    }

    #[Route('/event/{id}/reserve', name: 'app_event_reserve')]
    public function reserve(Request $request, Event $event, EntityManagerInterface $entityManager): Response
    {
        if (!$this->getUser()) {
            $request->getSession()->set('redirect_after_login', $request->getUri());
            $this->addFlash('warning', 'Please login to make a reservation');
            return $this->redirectToRoute('app_login');
        }

        if ($event->getAvailableSeats() <= 0) {
            $this->addFlash('error', 'No available seats for this event.');
            return $this->redirectToRoute('app_event_show', ['id' => $event->getId()]);
        }

        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('Invalid user type');
        }

        $reservation = new Reservation();
        $reservation->setEvent($event);
        $reservation->setName($user->getFullName());
        $reservation->setEmail($user->getEmail());

        $form = $this->createForm(ReservationType::class, $reservation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($reservation);
            $entityManager->flush();

            $this->emailService->sendReservationConfirmation(
                $reservation->getEmail(),
                $reservation->getName(),
                $event->getTitle(),
                $event->getDate(),
                $reservation->getId(),
                $event->getLocation()
            );

            $this->addFlash('success', 'Your reservation has been confirmed! A confirmation email has been sent to ' . $reservation->getEmail());
            return $this->redirectToRoute('app_reservation_confirmation', ['id' => $reservation->getId()]);
        }

        return $this->render('event/reserve.html.twig', [
            'event' => $event,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/reservation/{id}/confirmation', name: 'app_reservation_confirmation')]
    public function confirmation(Reservation $reservation): Response
    {
        return $this->render('event/confirmation.html.twig', [
            'reservation' => $reservation,
        ]);
    }
}