<?php

namespace App\Service;

use App\Entity\User;
use App\Entity\WebauthnCredential;
use App\Repository\WebauthnCredentialRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Serializer\SerializerInterface;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialRequestOptions;
use Webauthn\PublicKeyCredentialRpEntity;
use Webauthn\PublicKeyCredentialUserEntity;
use Webauthn\PublicKeyCredentialParameters;
use Webauthn\PublicKeyCredentialDescriptor;
use Webauthn\AuthenticatorSelectionCriteria;
use Webauthn\AuthenticatorAttestationResponse;
use Webauthn\AuthenticatorAssertionResponse;
use Webauthn\AuthenticatorAttestationResponseValidator;
use Webauthn\AuthenticatorAssertionResponseValidator;
use Webauthn\PublicKeyCredential;
use Webauthn\Exception\AuthenticatorResponseVerificationException;
use Webauthn\Denormalizer\WebauthnSerializerFactory;
use Webauthn\AttestationStatement\AttestationStatementSupportManager;

class WebAuthnService
{
    private RequestStack $requestStack;
    private WebauthnCredentialRepository $credentialRepository;
    private EntityManagerInterface $entityManager;
    private SerializerInterface $serializer;
    private AuthenticatorAttestationResponseValidator $attestationValidator;
    private AuthenticatorAssertionResponseValidator $assertionValidator;

    public function __construct(
        RequestStack $requestStack,
        WebauthnCredentialRepository $credentialRepository,
        EntityManagerInterface $entityManager,
        SerializerInterface $serializer,
        AuthenticatorAttestationResponseValidator $attestationValidator,
        AuthenticatorAssertionResponseValidator $assertionValidator
    ) {
        $this->requestStack = $requestStack;
        $this->credentialRepository = $credentialRepository;
        $this->entityManager = $entityManager;
        $this->serializer = $serializer;
        $this->attestationValidator = $attestationValidator;
        $this->assertionValidator = $assertionValidator;
    }

    private function getSession()
    {
        return $this->requestStack->getSession();
    }

    private function getRpEntity(): PublicKeyCredentialRpEntity
    {
        return new PublicKeyCredentialRpEntity(
            'EventBooking',
            'localhost',
            null
        );
    }

    public function getRegistrationOptions(User $user): array
    {
        $userEntity = new PublicKeyCredentialUserEntity(
            $user->getEmail(),
            (string) $user->getId(),
            $user->getFullName()
        );

        $existingCredentials = $this->credentialRepository->findCredentialsForUser($user);
        $excludeCredentials = [];
        foreach ($existingCredentials as $credential) {
            $excludeCredentials[] = new PublicKeyCredentialDescriptor(
                PublicKeyCredentialDescriptor::CREDENTIAL_TYPE_PUBLIC_KEY,
                $credential->getId(),
                []
            );
        }

        $options = new PublicKeyCredentialCreationOptions(
        $this->getRpEntity(),
        $userEntity,
        base64_encode(random_bytes(32)), // challenge
        [
            new PublicKeyCredentialParameters('public-key', -7),
            new PublicKeyCredentialParameters('public-key', -257),
        ],
        new AuthenticatorSelectionCriteria( // 5th argument
            AuthenticatorSelectionCriteria::AUTHENTICATOR_ATTACHMENT_NO_PREFERENCE,
            AuthenticatorSelectionCriteria::USER_VERIFICATION_REQUIREMENT_PREFERRED,
            null,
            false
        ),
        PublicKeyCredentialCreationOptions::ATTESTATION_CONVEYANCE_PREFERENCE_NONE, // attestation
        $excludeCredentials, // excludeCredentials
        60000 // timeout
    );

        $this->setChallenge(base64_encode($options->getChallenge()));

        return $this->normalizeRegistrationOptions($options);
    }

    public function processRegistration(User $user, string $clientDataJSON, string $attestationObject): array
    {
        $challenge = $this->getChallenge();
        if (!$challenge) {
            throw new \Exception('No challenge found in session');
        }

        try {
            $data = json_encode([
                'id' => base64_encode(random_bytes(32)),
                'type' => 'public-key',
                'rawId' => base64_encode(random_bytes(32)),
                'response' => [
                    'clientDataJSON' => $clientDataJSON,
                    'attestationObject' => $attestationObject,
                ]
            ]);

            // Use serializer instead of PublicKeyCredentialLoader
            $publicKeyCredential = $this->serializer->deserialize(
                $data,
                PublicKeyCredential::class,
                'json'
            );
            
            if (!$publicKeyCredential->response instanceof AuthenticatorAttestationResponse) {
                throw new \Exception('Invalid response type');
            }

            $userEntity = new PublicKeyCredentialUserEntity(
                $user->getEmail(),
                (string) $user->getId(),
                $user->getFullName()
            );

            $credential = $this->attestationValidator->check(
                $publicKeyCredential->response,
                $publicKeyCredential,
                $challenge,
                $this->getRpEntity()->getId()
            );

            $webauthnCredential = new WebauthnCredential();
            $webauthnCredential->setId(base64_decode($credential->getPublicKeyCredentialId()));
            $webauthnCredential->setUser($user);
            $webauthnCredential->setName('Passkey ' . date('Y-m-d H:i'));
            $webauthnCredential->setPublicKey(base64_encode($credential->getPublicKey()));
            $webauthnCredential->setCounter($credential->getCounter() ?? 0);
            $webauthnCredential->setAaguid($credential->getAaguid() ?? null);
            $webauthnCredential->setCredentialData(serialize($credential));

            $this->entityManager->persist($webauthnCredential);
            $this->entityManager->flush();

            $this->clearChallenge();

            return [
                'success' => true,
                'credential' => $webauthnCredential
            ];

        } catch (AuthenticatorResponseVerificationException $e) {
            throw new \Exception('Invalid attestation: ' . $e->getMessage());
        } catch (\Exception $e) {
            throw new \Exception('Registration failed: ' . $e->getMessage());
        }
    }

    public function getAuthenticationOptions(): array
    {
        $allCredentials = $this->entityManager->getRepository(WebauthnCredential::class)->findAll();
        
        $allowCredentials = [];
        foreach ($allCredentials as $credential) {
            $allowCredentials[] = new PublicKeyCredentialDescriptor(
                PublicKeyCredentialDescriptor::CREDENTIAL_TYPE_PUBLIC_KEY,
                $credential->getId(),
                []
            );
        }

        $options = new PublicKeyCredentialRequestOptions(
            base64_encode(random_bytes(32)),
            60000,
            'localhost',
            $allowCredentials,
            PublicKeyCredentialRequestOptions::USER_VERIFICATION_REQUIREMENT_PREFERRED,
            []
        );

        $this->setChallenge(base64_encode($options->getChallenge()));

        return $this->normalizeAuthenticationOptions($options);
    }

    public function processAuthentication(string $credentialId, string $clientDataJSON, string $authenticatorData, string $signature): User
    {
        $challenge = $this->getChallenge();
        if (!$challenge) {
            throw new \Exception('No challenge found in session');
        }

        try {
            $credential = $this->credentialRepository->findOneByCredentialId($credentialId);
            if (!$credential) {
                throw new \Exception('Credential not found');
            }

            $data = json_encode([
                'id' => $credentialId,
                'type' => 'public-key',
                'rawId' => base64_encode($credentialId),
                'response' => [
                    'clientDataJSON' => $clientDataJSON,
                    'authenticatorData' => $authenticatorData,
                    'signature' => $signature,
                ]
            ]);

            // Use serializer instead of PublicKeyCredentialLoader
            $publicKeyCredential = $this->serializer->deserialize(
                $data,
                PublicKeyCredential::class,
                'json'
            );
            
            if (!$publicKeyCredential->response instanceof AuthenticatorAssertionResponse) {
                throw new \Exception('Invalid response type');
            }

            $storedCredential = unserialize($credential->getCredentialData());

            $this->assertionValidator->check(
                $storedCredential,
                $publicKeyCredential->response,
                $challenge,
                $this->getRpEntity()->getId()
            );

            $credential->setCounter($storedCredential->getCounter() ?? 0);
            $credential->setUpdatedAt(new \DateTimeImmutable());
            $this->entityManager->flush();

            $this->clearChallenge();

            return $credential->getUser();

        } catch (AuthenticatorResponseVerificationException $e) {
            throw new \Exception('Invalid assertion: ' . $e->getMessage());
        } catch (\Exception $e) {
            throw new \Exception('Authentication failed: ' . $e->getMessage());
        }
    }

    private function normalizeRegistrationOptions(PublicKeyCredentialCreationOptions $options): array
    {
        return [
            'rp' => [
                'name' => $options->getRp()->getName(),
                'id' => $options->getRp()->getId(),
            ],
            'user' => [
                'id' => base64_encode($options->getUser()->getId()),
                'name' => $options->getUser()->getName(),
                'displayName' => $options->getUser()->getDisplayName(),
            ],
            'challenge' => base64_encode($options->getChallenge()),
            'pubKeyCredParams' => array_map(function($param) {
                return [
                    'type' => $param->getType(),
                    'alg' => $param->getAlg(),
                ];
            }, $options->getPubKeyCredParams()),
            'authenticatorSelection' => $options->getAuthenticatorSelection() ? [
                'authenticatorAttachment' => $options->getAuthenticatorSelection()->getAuthenticatorAttachment(),
                'requireResidentKey' => $options->getAuthenticatorSelection()->isRequireResidentKey(),
                'residentKey' => $options->getAuthenticatorSelection()->getResidentKey(),
                'userVerification' => $options->getAuthenticatorSelection()->getUserVerification(),
            ] : null,
            'attestation' => $options->getAttestation(),
            'excludeCredentials' => array_map(function($descriptor) {
                return [
                    'type' => $descriptor->getType(),
                    'id' => base64_encode($descriptor->getId()),
                    'transports' => $descriptor->getTransports(),
                ];
            }, $options->getExcludeCredentials()),
            'timeout' => $options->getTimeout(),
        ];
    }

    private function normalizeAuthenticationOptions(PublicKeyCredentialRequestOptions $options): array
    {
        return [
            'challenge' => base64_encode($options->getChallenge()),
            'rpId' => $options->getRpId(),
            'allowCredentials' => array_map(function($descriptor) {
                return [
                    'type' => $descriptor->getType(),
                    'id' => base64_encode($descriptor->getId()),
                    'transports' => $descriptor->getTransports(),
                ];
            }, $options->getAllowCredentials()),
            'userVerification' => $options->getUserVerification(),
            'timeout' => $options->getTimeout(),
        ];
    }

    public function setChallenge(string $challenge): void
    {
        $this->getSession()->set('webauthn_challenge', $challenge);
    }

    public function getChallenge(): ?string
    {
        return $this->getSession()->get('webauthn_challenge');
    }

    public function clearChallenge(): void
    {
        $this->getSession()->remove('webauthn_challenge');
    }
}