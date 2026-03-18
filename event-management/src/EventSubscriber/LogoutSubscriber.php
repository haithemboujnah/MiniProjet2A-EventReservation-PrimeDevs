<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Http\Event\LogoutEvent;

class LogoutSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            LogoutEvent::class => 'onLogout',
            KernelEvents::RESPONSE => 'onKernelResponse',
        ];
    }

    public function onLogout(LogoutEvent $event): void
    {
        // Invalidate the session
        $request = $event->getRequest();
        $session = $request->getSession();
        
        if ($session) {
            $session->invalidate();
        }
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        $response = $event->getResponse();
        $request = $event->getRequest();
        
        // Add headers to prevent caching for authenticated routes
        if ($this->isProtectedRoute($request->getPathInfo())) {
            $this->setNoCacheHeaders($response);
        }
    }

    private function isProtectedRoute(string $path): bool
    {
        // Add your protected routes patterns
        $protectedPatterns = [
            '#^/admin#',
            '#^/reservations#',
            '#^/profile#',
        ];

        foreach ($protectedPatterns as $pattern) {
            if (preg_match($pattern, $path)) {
                return true;
            }
        }

        return false;
    }

    private function setNoCacheHeaders(Response $response): void
    {
        $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate, private, max-age=0');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', 'Wed, 11 Jan 1984 05:00:00 GMT');
    }
}