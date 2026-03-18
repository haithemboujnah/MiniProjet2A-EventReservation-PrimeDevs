<?php

namespace App\Repository;

use App\Entity\User;
use App\Entity\WebauthnCredential;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Webauthn\PublicKeyCredentialSource;
use Webauthn\PublicKeyCredentialSourceRepository;
use Webauthn\PublicKeyCredentialUserEntity;
use Symfony\Component\Uid\Uuid;

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
            ->where('c.credentialId = :credentialId')
            ->setParameter('credentialId', $publicKeyCredentialId)
            ->getQuery()
            ->getOneOrNullResult();

        return $credential ? $credential->toPublicKeyCredentialSource() : null;
    }

    public function saveCredentialSource(PublicKeyCredentialSource $credentialSource): void
    {
        // Find user by user handle
        $user = $this->getEntityManager()
            ->getRepository(User::class)
            ->findOneBy(['id' => Uuid::fromBinary($credentialSource->getUserHandle())]);

        if (!$user) {
            throw new \RuntimeException('User not found');
        }

        $this->saveCredential($user, $credentialSource);
    }

    /**
     * @return PublicKeyCredentialSource[]
     */
    public function findAllForUserEntity(PublicKeyCredentialUserEntity $publicKeyCredentialUserEntity): array
    {
        $user = $this->getEntityManager()
            ->getRepository(User::class)
            ->findOneBy(['email' => $publicKeyCredentialUserEntity->getName()]);

        if (!$user) {
            return [];
        }

        $credentials = $this->createQueryBuilder('c')
            ->where('c.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getResult();

        return array_map(fn($c) => $c->toPublicKeyCredentialSource(), $credentials);
    }

    public function saveCredential(User $user, PublicKeyCredentialSource $credentialSource, ?string $name = null): void
    {
        $credential = WebauthnCredential::fromPublicKeyCredentialSource($credentialSource, $user);
        $credential->setName($name ?? $this->generateCredentialName($user));
        
        $this->getEntityManager()->persist($credential);
        $this->getEntityManager()->flush();
    }

    public function removeCredential(WebauthnCredential $credential): void
    {
        $this->getEntityManager()->remove($credential);
        $this->getEntityManager()->flush();
    }

    public function findByUser(User $user): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.user = :user')
            ->setParameter('user', $user)
            ->orderBy('c.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function updateCounter(WebauthnCredential $credential, int $counter): void
    {
        $credential->setCounter($counter);
        $credential->touch();
        $this->getEntityManager()->flush();
    }

    private function generateCredentialName(User $user): string
    {
        $count = $this->count(['user' => $user]);
        return sprintf('Passkey %d', $count + 1);
    }
}