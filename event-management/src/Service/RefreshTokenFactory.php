<?php

namespace App\Service;

use Gesdinet\JWTRefreshTokenBundle\Entity\RefreshToken;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenInterface;
use Symfony\Component\Uid\Uuid;

class RefreshTokenFactory
{
    public function create(string $username, int $ttl = 2592000): RefreshTokenInterface
    {
        $refreshToken = new RefreshToken();
        $refreshToken->setUsername($username);
        
        // Generate a unique refresh token
        $refreshToken->setRefreshToken($this->generateUniqueToken());
        
        // Set expiration
        $refreshToken->setValid((new \DateTime())->modify('+' . $ttl . ' seconds'));
        
        return $refreshToken;
    }

    private function generateUniqueToken(): string
    {
        // Generate a random token using UUID
        return bin2hex(random_bytes(32)) . '_' . Uuid::v4()->toRfc4122();
    }
}