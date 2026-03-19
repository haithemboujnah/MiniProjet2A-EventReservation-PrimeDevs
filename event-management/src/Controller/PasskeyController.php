<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\WebAuthnService;
use App\Repository\WebauthnCredentialRepository;
use App\Service\PasskeyAuthService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;

#[Route('/passkey')]
class PasskeyController extends AbstractController
{
    public function __construct(
        private WebAuthnService $webAuthnService,
        private WebauthnCredentialRepository $credentialRepository,
        private EntityManagerInterface $entityManager
    ) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    #[Route('/manage', name: 'app_passkey_manage')]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function manage(): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $credentials = $this->credentialRepository->findCredentialsForUser($user);

        return $this->render('passkey/manage.html.twig', [
            'passkeys' => $credentials,
            'user' => $user
        ]);
    }

    #[Route('/delete/{id}', name: 'app_passkey_delete', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function delete(Request $request, int $id): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $credential = $this->credentialRepository->find($id);
        
        if (!$credential || $credential->getUser() !== $user) {
            return $this->json(['error' => 'Credential not found'], Response::HTTP_NOT_FOUND);
        }

        if (!$this->isCsrfTokenValid('delete-passkey', $request->request->get('_token'))) {
            return $this->json(['error' => 'Invalid CSRF token'], Response::HTTP_BAD_REQUEST);
        }

        $this->entityManager->remove($credential);
        $this->entityManager->flush();

        return $this->json(['success' => true]);
    }

    #[Route('/login', name: 'app_passkey_login')]
    public function login(): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_home');
        }

        return $this->render('passkey/login.html.twig');
    }

    #[Route('/register/options', name: 'app_passkey_register_options', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function registerOptions(): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        try {
            $options = $this->webAuthnService->getRegistrationOptions($user);
            
            return $this->json([
                'publicKey' => $options
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/register', name: 'app_passkey_register', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function register(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $data = json_decode($request->getContent(), true);
        
        if (!isset($data['clientDataJSON']) || !isset($data['attestationObject'])) {
            return $this->json(['error' => 'Invalid registration data'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $result = $this->webAuthnService->processRegistration(
                $user,
                $data['clientDataJSON'],
                $data['attestationObject']
            );

            return $this->json([
                'success' => true,
                'credential' => [
                    'id' => $result['credential']->getId(),
                    'name' => $result['credential']->getName()
                ]
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/login/options', name: 'app_passkey_login_options', methods: ['POST'])]
    public function loginOptions(): JsonResponse
    {
        try {
            $options = $this->webAuthnService->getAuthenticationOptions();
            
            return $this->json([
                'publicKey' => $options
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/authenticate', name: 'app_passkey_authenticate', methods: ['POST'])]
    public function authenticate(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        if (!isset($data['id']) || !isset($data['rawId']) || !isset($data['response'])) {
            return $this->json(['error' => 'Invalid authentication data'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $user = $this->webAuthnService->processAuthentication(
                $data['rawId'],
                $data['response']['clientDataJSON'],
                $data['response']['authenticatorData'],
                $data['response']['signature']
            );

            $token = new UsernamePasswordToken($user, 'main', $user->getRoles());
            $this->container->get('security.token_storage')->setToken($token);
            
            $event = new InteractiveLoginEvent($request, $token);
            $this->container->get('event_dispatcher')->dispatch($event);

            $request->getSession()->set('_security_main', serialize($token));

            return $this->json([
                'success' => true,
                'redirect' => $this->generateUrl('app_home')
            ]);

        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_UNAUTHORIZED);
        }
    }
}