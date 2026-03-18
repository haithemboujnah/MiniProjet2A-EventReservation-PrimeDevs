<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Gesdinet\JWTRefreshTokenBundle\Entity\RefreshToken as BaseRefreshToken;

#[ORM\Entity]
#[ORM\Table(name: 'refresh_tokens')]
class RefreshToken extends BaseRefreshToken
{
    /**
     * This method is optional - only override if you need to customize
     * You can remove this entire class if you don't need custom fields
     */
    
    // Example: Adding a custom field
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $deviceInfo = null;

    public function getDeviceInfo(): ?string
    {
        return $this->deviceInfo;
    }

    public function setDeviceInfo(?string $deviceInfo): self
    {
        $this->deviceInfo = $deviceInfo;
        return $this;
    }
}