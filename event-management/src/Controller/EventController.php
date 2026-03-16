<?php

namespace App\Controller;

use App\Entity\Event;
use App\Entity\Reservation;
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

}