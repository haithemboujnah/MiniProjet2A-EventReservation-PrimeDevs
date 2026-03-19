<?php

namespace App\Controller\Api;

use App\Entity\User;
use App\Entity\RefreshToken;
use App\Factory\RefreshTokenFactory;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use Gesdinet\JWTRefreshTokenBundle\Entity\RefreshToken as BaseRefreshToken;
use Lexik\Bundle\JWTAuthenticationBundle\Encoder\JWTEncoderInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api', name: 'api_')]
class AuthController extends AbstractController
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

    #[Route('/register', name: 'register', methods: ['POST'])]
    public function register(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager,
        ValidatorInterface $validator
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        $existingUser = $entityManager->getRepository(User::class)->findOneBy(['email' => $data['email'] ?? '']);
        if ($existingUser) {
            return $this->json(['error' => 'User already exists'], Response::HTTP_CONFLICT);
        }

        $user = new User();
        $user->setEmail($data['email'] ?? '');
        $user->setFullName($data['fullName'] ?? '');
        $user->setPassword($passwordHasher->hashPassword($user, $data['password'] ?? ''));

        $errors = $validator->validate($user);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getMessage();
            }
            return $this->json(['errors' => $errorMessages], Response::HTTP_BAD_REQUEST);
        }

        $entityManager->persist($user);
        $entityManager->flush();

        $token = $this->jwtManager->create($user);
        
        $refreshToken = $this->refreshTokenFactory->create($user->getUserIdentifier(), 30 * 24 * 3600);
        $this->refreshTokenManager->save($refreshToken);

        return $this->json([
            'message' => 'User registered successfully',
            'user' => [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'fullName' => $user->getFullName()
            ],
            'token' => $token,
            'refresh_token' => $refreshToken->getRefreshToken()
        ], Response::HTTP_CREATED);
    }

    #[Route('/login', name: 'login', methods: ['POST'])]
    public function login(): JsonResponse
    {
        return $this->json(['message' => 'Login successful']);
    }

    #[Route('/logout', name: 'logout', methods: ['POST'])]
    public function logout(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $token = str_replace('Bearer ', '', $request->headers->get('Authorization', ''));
        
        if ($token) {
            try {
                $data = $this->jwtEncoder->decode($token);
                $email = $data['email'] ?? null;
                
                if ($email) {
                    $refreshTokens = $entityManager->getRepository(RefreshToken::class)
                        ->findBy(['username' => $email]);
                    
                    foreach ($refreshTokens as $refreshToken) {
                        $entityManager->remove($refreshToken);
                    }
                    $entityManager->flush();
                }
            } catch (\Exception $e) {
                
            }
        }

        return $this->json(['message' => 'Logged out successfully']);
    }

    #[Route('/token/refresh', name: 'refresh_token', methods: ['POST'])]
    public function refreshToken(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $refreshToken = $data['refresh_token'] ?? null;

        if (!$refreshToken) {
            return $this->json(['error' => 'Refresh token required'], Response::HTTP_BAD_REQUEST);
        }

        $tokenEntity = $this->refreshTokenManager->get($refreshToken);
        
        if (!$tokenEntity || !$tokenEntity->isValid()) {
            return $this->json(['error' => 'Invalid refresh token'], Response::HTTP_UNAUTHORIZED);
        }

        $user = $entityManager->getRepository(User::class)
            ->findOneBy(['email' => $tokenEntity->getUsername()]);

        if (!$user) {
            return $this->json(['error' => 'User not found'], Response::HTTP_UNAUTHORIZED);
        }

        if (!$user instanceof User) {
            return $this->json(['error' => 'Invalid user type'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $newToken = $this->jwtManager->create($user);

        $newRefreshToken = $this->refreshTokenFactory->create($user->getUserIdentifier(), 30 * 24 * 3600);
        
        $this->refreshTokenManager->save($newRefreshToken);

        $this->refreshTokenManager->delete($tokenEntity);

        return $this->json([
            'token' => $newToken,
            'refresh_token' => $newRefreshToken->getRefreshToken()
        ]);
    }

    #[Route('/me', name: 'current_user', methods: ['GET'])]
    public function currentUser(): JsonResponse
    {
        $user = $this->getUser();
        
        if (!$user) {
            return $this->json(['error' => 'Not authenticated'], Response::HTTP_UNAUTHORIZED);
        }

        if (!$user instanceof User) {
            return $this->json(['error' => 'Invalid user type'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return $this->json([
            'user' => [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'fullName' => $user->getFullName(),
                'roles' => $user->getRoles()
            ]
        ]);
    }
}