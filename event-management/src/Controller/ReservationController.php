<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\ReservationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/reservations')]
class ReservationController extends AbstractController
{
    #[Route('/', name: 'app_my_reservations')]
    public function index(ReservationRepository $reservationRepository): Response
    {
        $user = $this->getUser();
        
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('Invalid user type');
        }

        $reservations = $reservationRepository->findByEmail($user->getEmail());

        $now = new \DateTime();
        $upcomingReservations = [];
        $pastReservations = [];

        foreach ($reservations as $reservation) {
            if ($reservation->getEvent()->getDate() > $now) {
                $upcomingReservations[] = $reservation;
            } else {
                $pastReservations[] = $reservation;
            }
        }

        return $this->render('reservation/index.html.twig', [
            'upcomingReservations' => $upcomingReservations,
            'pastReservations' => $pastReservations
        ]);
    }

    #[Route('/{id}', name: 'app_reservation_show', methods: ['GET'])]
    public function show(int $id, ReservationRepository $reservationRepository): Response
    {
        $reservation = $reservationRepository->find($id);
        
        if (!$reservation) {
            throw $this->createNotFoundException('Reservation not found');
        }

        $user = $this->getUser();
        
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('Invalid user type');
        }

        if ($reservation->getEmail() !== $user->getEmail()) {
            throw $this->createAccessDeniedException('Access denied');
        }

        return $this->render('reservation/show.html.twig', [
            'reservation' => $reservation
        ]);
    }

    #[Route('/{id}/cancel', name: 'app_reservation_cancel', methods: ['POST'])]
    public function cancel(int $id, ReservationRepository $reservationRepository, EntityManagerInterface $entityManager): Response
    {
        $reservation = $reservationRepository->find($id);
        
        if (!$reservation) {
            throw $this->createNotFoundException('Reservation not found');
        }

        $user = $this->getUser();
        
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('Invalid user type');
        }

        if ($reservation->getEmail() !== $user->getEmail()) {
            throw $this->createAccessDeniedException('Access denied');
        }

        if ($reservation->getEvent()->getDate() <= new \DateTime()) {
            $this->addFlash('error', 'Cannot cancel past events');
            return $this->redirectToRoute('app_my_reservations');
        }

        $entityManager->remove($reservation);
        $entityManager->flush();

        $this->addFlash('success', 'Reservation cancelled successfully');
        return $this->redirectToRoute('app_my_reservations');
    }
}