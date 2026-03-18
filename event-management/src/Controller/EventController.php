<?php

namespace App\Controller;

use App\Entity\Event;
use App\Entity\Reservation;
use App\Entity\User; // Add this import
use App\Form\ReservationType;
use App\Repository\EventRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class EventController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(EventRepository $eventRepository): Response
    {
        $upcomingEvents = $eventRepository->findUpcomingEvents();
        
        // Get only the next 6 events for the homepage
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
        $limit = 4; // 4 events per page

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
        
        // Slice array for current page
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
        // Check if user is logged in
        if (!$this->getUser()) {
            // Store the intended reservation URL in session
            $request->getSession()->set('redirect_after_login', $request->getUri());
            
            // Add a flash message
            $this->addFlash('warning', 'Please login to make a reservation');
            
            // Redirect to login page
            return $this->redirectToRoute('app_login');
        }

        // Check if event has available seats
        if ($event->getAvailableSeats() <= 0) {
            $this->addFlash('error', 'No available seats for this event.');
            return $this->redirectToRoute('app_event_show', ['id' => $event->getId()]);
        }

        // Get the authenticated user and type-hint it as your User entity
        $user = $this->getUser();
        
        // Ensure the user is an instance of your User entity
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('Invalid user type');
        }

        // Pre-fill form with user data
        $reservation = new Reservation();
        $reservation->setEvent($event);
        $reservation->setName($user->getFullName());
        $reservation->setEmail($user->getEmail());
        // Phone is left empty as it might not be in User entity

        $form = $this->createForm(ReservationType::class, $reservation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($reservation);
            $entityManager->flush();

            $this->addFlash('success', 'Your reservation has been confirmed!');
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