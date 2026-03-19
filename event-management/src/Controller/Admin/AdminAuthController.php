<?php

namespace App\Controller\Admin;

use App\Entity\Admin;
use App\Entity\Event;
use App\Entity\Reservation;
use App\Entity\RefreshToken;
use App\Form\Admin\AdminLoginType;
use App\Form\Admin\EventType;
use App\Repository\EventRepository;
use App\Repository\ReservationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Encoder\JWTEncoderInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin')]
class AdminAuthController extends AbstractController
{
    private JWTTokenManagerInterface $jwtManager;
    private RefreshTokenManagerInterface $refreshTokenManager;
    private JWTEncoderInterface $jwtEncoder;

    public function __construct(
        JWTTokenManagerInterface $jwtManager,
        RefreshTokenManagerInterface $refreshTokenManager,
        JWTEncoderInterface $jwtEncoder
    ) {
        $this->jwtManager = $jwtManager;
        $this->refreshTokenManager = $refreshTokenManager;
        $this->jwtEncoder = $jwtEncoder;
    }

    #[Route('/login', name: 'admin_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        if ($this->isGranted('ROLE_ADMIN')) {
            return $this->redirectToRoute('admin_dashboard');
        }

        $error = $authenticationUtils->getLastAuthenticationError();
        $lastUsername = $authenticationUtils->getLastUsername();

        $form = $this->createForm(AdminLoginType::class);

        return $this->render('admin/login.html.twig', [
            'form' => $form->createView(),
            'last_username' => $lastUsername,
            'error' => $error,
        ]);
    }

    #[Route('/logout', name: 'admin_logout')]
    public function logout(): void
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }

    #[Route('/create-admin', name: 'admin_create')]
    public function createAdmin(Request $request, UserPasswordHasherInterface $passwordHasher, EntityManagerInterface $entityManager): Response
    {
        $adminRepo = $entityManager->getRepository(Admin::class);
        $adminCount = $adminRepo->count([]);

        if ($adminCount > 0 && $this->getParameter('kernel.environment') === 'prod') {
            throw $this->createNotFoundException('Admin creation is disabled');
        }

        if ($request->isMethod('POST')) {
            $username = $request->request->get('username');
            $password = $request->request->get('password');
            $email = $request->request->get('email');
            $fullName = $request->request->get('full_name');

            $existingAdmin = $adminRepo->findOneBy(['username' => $username]);
            if ($existingAdmin) {
                $this->addFlash('error', 'Username already exists');
                return $this->redirectToRoute('admin_create');
            }

            $admin = new Admin();
            $admin->setUsername($username);
            $admin->setEmail($email);
            $admin->setFullName($fullName);
            $admin->setPassword($passwordHasher->hashPassword($admin, $password));
            $admin->setRoles(['ROLE_ADMIN']);

            $entityManager->persist($admin);
            $entityManager->flush();

            $this->addFlash('success', 'Admin account created successfully!');
            return $this->redirectToRoute('admin_login');
        }

        return $this->render('admin/create_admin.html.twig');
    }

    #[Route('/profile', name: 'admin_profile')]
    #[IsGranted('ROLE_ADMIN')]
    public function profile(Request $request, EntityManagerInterface $entityManager): Response
    {
        $admin = $this->getUser();
        

        if (!$admin instanceof Admin) {
            throw $this->createAccessDeniedException('Invalid admin type');
        }

        $jwtToken = $this->generateAdminToken($admin, $request, $entityManager);
        
        $stats = $this->getAdminStats($entityManager);
        
        $recentActivity = $this->getRecentActivity($entityManager);
        
        return $this->render('admin/profile.html.twig', [
            'admin' => $admin,
            'jwt_token' => $jwtToken,
            'stats' => $stats,
            'recent_activity' => $recentActivity,
        ]);
    }

    #[Route('/api/token', name: 'admin_api_token', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function getApiToken(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $admin = $this->getUser();
        
        if (!$admin instanceof Admin) {
            return $this->json(['error' => 'Invalid admin type'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
        
        $jwtToken = $this->generateAdminToken($admin, $request, $entityManager);
        
        return $this->json([
            'token' => $jwtToken,
            'admin' => [
                'id' => $admin->getId(),
                'username' => $admin->getUsername(),
                'email' => $admin->getEmail(),
                'fullName' => $admin->getFullName(),
            ]
        ]);
    }

    #[Route('/api/token/refresh', name: 'admin_refresh_token', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function refreshApiToken(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $admin = $this->getUser();
        
        if (!$admin instanceof Admin) {
            return $this->json(['error' => 'Invalid admin type'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
        
        $jwtToken = $this->jwtManager->create($admin);
        
        return $this->json([
            'token' => $jwtToken,
        ]);
    }

    private function generateAdminToken(Admin $admin, Request $request, EntityManagerInterface $entityManager): ?string
    {
        $jwtToken = $request->getSession()->get('admin_jwt_token');
        
        if (!$jwtToken) {
            $jwtToken = $this->jwtManager->create($admin);
            
            $request->getSession()->set('admin_jwt_token', $jwtToken);
            
        }
        
        return $jwtToken;
    }

    private function getAdminStats(EntityManagerInterface $entityManager): array
    {
        $eventRepo = $entityManager->getRepository(Event::class);
        $reservationRepo = $entityManager->getRepository(Reservation::class);
        
        $now = new \DateTimeImmutable();
        
        return [
            'total_events' => $eventRepo->count([]),
            'upcoming_events' => count($eventRepo->findUpcomingEvents()),
            'past_events' => count($eventRepo->findPastEvents()),
            'total_reservations' => $reservationRepo->count([]),
            'today_reservations' => $reservationRepo->count(['createdAt' => $now]),
        ];
    }

    private function getRecentActivity(EntityManagerInterface $entityManager): array
    {
        $reservationRepo = $entityManager->getRepository(Reservation::class);
        $eventRepo = $entityManager->getRepository(Event::class);
        
        $recentReservations = $reservationRepo->findBy([], ['createdAt' => 'DESC'], 5);
        $recentEvents = $eventRepo->findBy([], ['createdAt' => 'DESC'], 5);
        
        return [
            'reservations' => $recentReservations,
            'events' => $recentEvents,
        ];
    }
}