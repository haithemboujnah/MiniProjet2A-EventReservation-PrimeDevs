<?php

namespace App\Controller\Admin;

use App\Entity\Event;
use App\Entity\Reservation;
use App\Form\Admin\EventType;
use App\Repository\EventRepository;
use App\Repository\ReservationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\HttpFoundation\JsonResponse;

#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
class AdminController extends AbstractController
{
    
    #[Route('/', name: 'admin_dashboard')]
    public function dashboard(EventRepository $eventRepository, ReservationRepository $reservationRepository): Response
    {
        // Get all events
        $allEvents = $eventRepository->findAll();
        
        // Get upcoming and past events using repository methods
        $upcomingEvents = $eventRepository->findUpcomingEvents();
        $pastEvents = $eventRepository->findPastEvents();
        
        // Get recent reservations
        $recentReservations = $reservationRepository->findBy([], ['createdAt' => 'DESC'], 5);
        
        // Get recent events
        $recentEvents = $eventRepository->findBy([], ['date' => 'DESC'], 5);
        
        $stats = [
            'total_events' => count($allEvents),
            'upcoming_events' => count($upcomingEvents),
            'past_events' => count($pastEvents),
            'total_reservations' => $reservationRepository->count([]),
            'recent_reservations' => $recentReservations,
            'recent_events' => $recentEvents,
        ];

        return $this->render('admin/dashboard.html.twig', [
            'stats' => $stats
        ]);
    }

    #[Route('/events', name: 'admin_events_index')]
    public function eventsIndex(EventRepository $eventRepository): Response
    {
        $events = $eventRepository->findAllOrderedByDate();
        
        return $this->render('admin/events/index.html.twig', [
            'events' => $events
        ]);
    }

    #[Route('/events/new', name: 'admin_events_new')]
    public function eventsNew(Request $request, EntityManagerInterface $entityManager): Response
    {
        $event = new Event();
        $form = $this->createForm(EventType::class, $event);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($event);
            $entityManager->flush();

            $this->addFlash('success', 'Event created successfully!');
            return $this->redirectToRoute('admin_events_index');
        }

        return $this->render('admin/events/new.html.twig', [
            'form' => $form->createView(),
            'event' => $event
        ]);
    }

    #[Route('/events/{id}/edit', name: 'admin_events_edit')]
    public function eventsEdit(Request $request, Event $event, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(EventType::class, $event);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'Event updated successfully!');
            return $this->redirectToRoute('admin_events_index');
        }

        return $this->render('admin/events/edit.html.twig', [
            'form' => $form->createView(),
            'event' => $event
        ]);
    }

    #[Route('/events/{id}', name: 'admin_events_show')]
    public function eventsShow(Event $event, ReservationRepository $reservationRepository): Response
    {
        $reservations = $reservationRepository->findByEvent($event->getId());
        
        return $this->render('admin/events/show.html.twig', [
            'event' => $event,
            'reservations' => $reservations,
            'total_reservations' => count($reservations)
        ]);
    }

    #[Route('/events/{id}/delete', name: 'admin_events_delete', methods: ['POST'])]
    public function eventsDelete(Request $request, Event $event, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete' . $event->getId(), $request->request->get('_token'))) {
            // Check if event has reservations
            if (count($event->getReservations()) > 0) {
                $this->addFlash('error', 'Cannot delete event with existing reservations.');
                return $this->redirectToRoute('admin_events_show', ['id' => $event->getId()]);
            }

            $entityManager->remove($event);
            $entityManager->flush();
            $this->addFlash('success', 'Event deleted successfully!');
        }

        return $this->redirectToRoute('admin_events_index');
    }

    #[Route('/events/{id}/reservations', name: 'admin_event_reservations')]
    public function eventReservations(Event $event, ReservationRepository $reservationRepository): Response
    {
        $reservations = $reservationRepository->findByEvent($event->getId());
        
        return $this->render('admin/events/reservations.html.twig', [
            'event' => $event,
            'reservations' => $reservations
        ]);
    }

    #[Route('/reservations/{id}/delete', name: 'admin_reservation_delete', methods: ['POST'])]
    public function deleteReservation(Request $request, Reservation $reservation, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete' . $reservation->getId(), $request->request->get('_token'))) {
            $eventId = $reservation->getEvent()->getId();
            $entityManager->remove($reservation);
            $entityManager->flush();
            $this->addFlash('success', 'Reservation deleted successfully!');
        }

        return $this->redirectToRoute('admin_event_reservations', ['id' => $eventId]);
    }


    #[Route('/notifications', name: 'admin_notifications', methods: ['GET'])]
    public function getNotifications(ReservationRepository $reservationRepository): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        
        // Get recent reservations (last 24 hours)
        $since = (new \DateTime())->modify('-24 hours');
        $recentReservations = $reservationRepository->createQueryBuilder('r')
            ->where('r.createdAt >= :since')
            ->setParameter('since', $since)
            ->orderBy('r.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
        
        // Get total unread (you can implement an "is_read" field if needed)
        // For now, we'll just return all recent reservations
        $totalNew = count($recentReservations);
        
        $notifications = array_map(function($reservation) {
            return [
                'id' => $reservation->getId(),
                'name' => $reservation->getName(),
                'email' => $reservation->getEmail(),
                'event' => $reservation->getEvent()->getTitle(),
                'created_at' => $reservation->getCreatedAt()->format('Y-m-d H:i:s'),
                'time_ago' => $this->getTimeAgo($reservation->getCreatedAt()),
            ];
        }, $recentReservations);
        
        return $this->json([
            'total' => $totalNew,
            'notifications' => $notifications
        ]);
    }

    #[Route('/notifications/mark-read', name: 'admin_notifications_mark_read', methods: ['POST'])]
    public function markNotificationsRead(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        
        $data = json_decode($request->getContent(), true);
        $ids = $data['ids'] ?? [];
        
        // Here you would mark notifications as read in your database
        // For now, we'll just return success
        
        return $this->json(['success' => true]);
    }

    private function getTimeAgo(\DateTimeInterface $dateTime): string
    {
        $now = new \DateTime();
        $diff = $now->diff($dateTime);
        
        if ($diff->y > 0) {
            return $diff->y . ' year' . ($diff->y > 1 ? 's' : '') . ' ago';
        }
        if ($diff->m > 0) {
            return $diff->m . ' month' . ($diff->m > 1 ? 's' : '') . ' ago';
        }
        if ($diff->d > 0) {
            return $diff->d . ' day' . ($diff->d > 1 ? 's' : '') . ' ago';
        }
        if ($diff->h > 0) {
            return $diff->h . ' hour' . ($diff->h > 1 ? 's' : '') . ' ago';
        }
        if ($diff->i > 0) {
            return $diff->i . ' minute' . ($diff->i > 1 ? 's' : '') . ' ago';
        }
        return 'Just now';
    }
}