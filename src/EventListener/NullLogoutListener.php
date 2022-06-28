<?php


namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Security\Http\Event\LogoutEvent;

/**
 * Override saml logout so we dont have to do anything
 */
final class NullLogoutListener
{
    public function __construct(
    ) {}

    #[AsEventListener(LogoutEvent::class)]
    public function processSingleLogout(LogoutEvent $event): void
    {

    }
}
