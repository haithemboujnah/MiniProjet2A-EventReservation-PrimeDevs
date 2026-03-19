<?php

namespace App\Repository;

use App\Entity\WebauthnCredential;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Webauthn\PublicKeyCredentialSource;
use Webauthn\PublicKeyCredentialSourceRepository;
use Webauthn\PublicKeyCredentialUserEntity;

/**
 * @extends ServiceEntityRepository<WebauthnCredential>
 */
class WebauthnCredentialRepository extends ServiceEntityRepository implements PublicKeyCredentialSourceRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WebauthnCredential::class);
    }

    public function findOneByCredentialId(string $publicKeyCredentialId): ?PublicKeyCredentialSource
    {
        $credential = $this->createQueryBuilder('c')
            ->andWhere('c.credentialId = :credentialId')
            ->setParameter('credentialId', base64_encode($publicKeyCredentialId))
            ->getQuery()
            ->getOneOrNullResult();

        if (!$credential) {
            return null;
        }

        return PublicKeyCredentialSource::createFromArray(
            json_decode($credential->getCredentialSource(), true)
        );
    }

    public function findAllForUserEntity(PublicKeyCredentialUserEntity $publicKeyCredentialUserEntity): array
    {
        $credentials = $this->createQueryBuilder('c')
            ->join('c.user', 'u')
            ->andWhere('u.id = :userId')
            ->setParameter('userId', $publicKeyCredentialUserEntity->getId())
            ->getQuery()
            ->getResult();

        return array_map(function ($credential) {
            return PublicKeyCredentialSource::createFromArray(
                json_decode($credential->getCredentialSource(), true)
            );
        }, $credentials);
    }

    public function saveCredentialSource(PublicKeyCredentialSource $publicKeyCredentialSource): void
    {
        // This method is called by the WebAuthn library to save credentials
        // You'll need to find or create a WebauthnCredential entity and save it
        $credential = $this->findOneByCredentialId($publicKeyCredentialSource->getPublicKeyCredentialId());
        
        if (!$credential) {
            // This should be handled by your registration process
            // The library will call this method, but we'll let the registration handle it
        }
    }

    /**
     * Find credentials for a user
     */
    public function findCredentialsForUser($user): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.user = :user')
            ->setParameter('user', $user)
            ->orderBy('c.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}