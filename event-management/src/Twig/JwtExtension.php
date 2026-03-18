<?php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;
use Symfony\Component\HttpFoundation\RequestStack;

class JwtExtension extends AbstractExtension
{
    private RequestStack $requestStack;

    public function __construct(RequestStack $requestStack)
    {
        $this->requestStack = $requestStack;
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('get_jwt_token', [$this, 'getJwtToken']),
        ];
    }

    public function getJwtToken(): ?string
    {
        $request = $this->requestStack->getCurrentRequest();
        return $request?->getSession()?->get('jwt_token');
    }
}