<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\RefreshToken;
use App\Form\RegistrationFormType;
use App\Factory\RefreshTokenFactory;
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
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use App\Repository\ReservationRepository;

class UserController extends AbstractController
{
    private JWTTokenManagerInterface $jwtManager;
    private RefreshTokenManagerInterface $refreshTokenManager;
    private JWTEncoderInterface $jwtEncoder;
    private RefreshTokenFactory $refreshTokenFactory;

    public function __construct(
        JWTTokenManagerInterface $jwtManager,
        RefreshTokenManagerInterface $refreshTokenManager,
        JWTEncoderInterface $jwtEncoder,
        RefreshTokenFactory $refreshTokenFactory
    ) {
        $this->jwtManager = $jwtManager;
        $this->refreshTokenManager = $refreshTokenManager;
        $this->jwtEncoder = $jwtEncoder;
        $this->refreshTokenFactory = $refreshTokenFactory;
    }

    #[Route('/register', name: 'app_register')]
    public function register(
        Request $request, 
        UserPasswordHasherInterface $userPasswordHasher, 
        EntityManagerInterface $entityManager
    ): Response {
        // Redirect if already logged in
        if ($this->getUser()) {
            return $this->redirectToRoute('app_home');
        }

        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Check if email already exists
            $existingUser = $entityManager->getRepository(User::class)->findOneBy(['email' => $user->getEmail()]);
            if ($existingUser) {
                $this->addFlash('error', 'This email is already registered.');
                return $this->redirectToRoute('app_register');
            }

            // Encode the plain password
            $user->setPassword(
                $userPasswordHasher->hashPassword(
                    $user,
                    $form->get('plainPassword')->getData()
                )
            );

            $entityManager->persist($user);
            $entityManager->flush();

            // Generate JWT token
            $token = $this->jwtManager->create($user);
            
            // Create refresh token
            $refreshToken = $this->refreshTokenFactory->create($user->getUserIdentifier(), 30 * 24 * 3600);
            $this->refreshTokenManager->save($refreshToken);

            // Store tokens in session for web use
            $request->getSession()->set('jwt_token', $token);
            $request->getSession()->set('refresh_token', $refreshToken->getRefreshToken());

            $this->addFlash('success', 'Registration successful! You are now logged in.');
            
            // Redirect to home with tokens in session
            return $this->redirectToRoute('app_home');
        }

        return $this->render('user/register.html.twig', [
            'registrationForm' => $form->createView(),
        ]);
    }

    #[Route('/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils, Request $request): Response
    {
        // Redirect if already logged in
        if ($this->getUser()) {
            return $this->redirectToRoute('app_home');
        }

        // Get the login error if there is one
        $error = $authenticationUtils->getLastAuthenticationError();
        
        // Last username entered by the user
        $lastUsername = $authenticationUtils->getLastUsername();

        // If login is successful, the user will be in the session
        // We need to check if there's a user in the token storage
        if ($error === null && $request->isMethod('POST')) {
            // The login was successful, but we need to get the user
            // This will be handled after redirect
        }

        return $this->render('user/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
        ]);
    }

    // Add this new route to handle post-login token generation
    #[Route('/login-success', name: 'app_login_success')]
    public function loginSuccess(Request $request): Response
    {
        $user = $this->getUser();
        
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        // Generate JWT token if not exists
        if (!$request->getSession()->has('jwt_token')) {
            $token = $this->jwtManager->create($user);
            $refreshToken = $this->refreshTokenFactory->create($user->getUserIdentifier(), 30 * 24 * 3600);
            $this->refreshTokenManager->save($refreshToken);
            
            $request->getSession()->set('jwt_token', $token);
            $request->getSession()->set('refresh_token', $refreshToken->getRefreshToken());
        }

        $this->addFlash('success', 'Login successful!');
        return $this->redirectToRoute('app_home');
    }

    #[Route('/logout', name: 'app_logout')]
    public function logout(Request $request, EntityManagerInterface $entityManager): void
    {
        // Invalidate refresh tokens
        $token = $request->getSession()->get('jwt_token');
        
        if ($token) {
            try {
                // Decode token to get user email
                $data = $this->jwtEncoder->decode($token);
                $email = $data['email'] ?? null;
                
                if ($email) {
                    // Remove refresh tokens for this user
                    $refreshTokens = $entityManager->getRepository(RefreshToken::class)
                        ->findBy(['username' => $email]);
                    
                    foreach ($refreshTokens as $refreshToken) {
                        $entityManager->remove($refreshToken);
                    }
                    $entityManager->flush();
                }
            } catch (\Exception $e) {
                // Token invalid, just proceed with logout
            }
        }

        // Clear session tokens
        $request->getSession()->remove('jwt_token');
        $request->getSession()->remove('refresh_token');

        // This method can be blank - it will be intercepted by the logout key on your firewall
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }

    #[Route('/profile', name: 'app_profile')]
    public function profile(Request $request, ReservationRepository $reservationRepository): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        
        $user = $this->getUser();
        
        // Type check to ensure $user is your User entity
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('Invalid user type');
        }
        
        // Get JWT token from session
        $jwtToken = $request->getSession()->get('jwt_token');
        
        // If no token in session, generate one
        if (!$jwtToken) {
            $jwtToken = $this->jwtManager->create($user);
            $refreshToken = $this->refreshTokenFactory->create($user->getUserIdentifier(), 30 * 24 * 3600);
            $this->refreshTokenManager->save($refreshToken);
            
            $request->getSession()->set('jwt_token', $jwtToken);
            $request->getSession()->set('refresh_token', $refreshToken->getRefreshToken());
        }
        
        // Get user statistics
        $reservations = $reservationRepository->findByEmail($user->getEmail());
        $now = new \DateTime();
        
        $upcoming = array_filter($reservations, fn($r) => $r->getEvent()->getDate() > $now);
        $past = array_filter($reservations, fn($r) => $r->getEvent()->getDate() <= $now);
        
        $stats = [
            'total_reservations' => count($reservations),
            'upcoming_events' => count($upcoming),
            'past_events' => count($past),
        ];
        
        return $this->render('user/profile.html.twig', [
            'user' => $user,
            'jwt_token' => $jwtToken,
            'stats' => $stats,
        ]);
    }

    #[Route('/api-token', name: 'app_api_token', methods: ['GET'])]
    public function getApiToken(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        
        $user = $this->getUser();
        
        // Type check
        if (!$user instanceof User) {
            return $this->json(['error' => 'Invalid user type'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
        
        $jwtToken = $request->getSession()->get('jwt_token');
        $refreshToken = $request->getSession()->get('refresh_token');
        
        if (!$jwtToken) {
            // Generate new token if not exists
            $jwtToken = $this->jwtManager->create($user);
            
            // Create refresh token
            $refreshTokenEntity = $this->refreshTokenFactory->create($user->getUserIdentifier(), 30 * 24 * 3600);
            $this->refreshTokenManager->save($refreshTokenEntity);
            $refreshToken = $refreshTokenEntity->getRefreshToken();
            
            // Store in session
            $request->getSession()->set('jwt_token', $jwtToken);
            $request->getSession()->set('refresh_token', $refreshToken);
        }
        
        return $this->json([
            'token' => $jwtToken,
            'refresh_token' => $refreshToken,
        ]);
    }

    #[Route('/refresh-api-token', name: 'app_refresh_api_token', methods: ['POST'])]
    public function refreshApiToken(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        
        $user = $this->getUser();
        
        // Type check
        if (!$user instanceof User) {
            return $this->json(['error' => 'Invalid user type'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
        
        $refreshToken = $request->getSession()->get('refresh_token');
        
        if (!$refreshToken) {
            return $this->json(['error' => 'No refresh token found'], Response::HTTP_BAD_REQUEST);
        }

        // Validate refresh token
        $tokenEntity = $this->refreshTokenManager->get($refreshToken);
        
        if (!$tokenEntity || !$tokenEntity->isValid()) {
            return $this->json(['error' => 'Invalid refresh token'], Response::HTTP_UNAUTHORIZED);
        }

        // Generate new JWT
        $newToken = $this->jwtManager->create($user);

        // Generate new refresh token
        $newRefreshTokenEntity = $this->refreshTokenFactory->create($user->getUserIdentifier(), 30 * 24 * 3600);
        $this->refreshTokenManager->save($newRefreshTokenEntity);
        $newRefreshToken = $newRefreshTokenEntity->getRefreshToken();

        // Delete old refresh token
        $this->refreshTokenManager->delete($tokenEntity);

        // Store in session
        $request->getSession()->set('jwt_token', $newToken);
        $request->getSession()->set('refresh_token', $newRefreshToken);

        return $this->json([
            'token' => $newToken,
            'refresh_token' => $newRefreshToken
        ]);
    }
}