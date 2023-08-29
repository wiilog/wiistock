<?php

namespace App\Security;

use App\Entity\Utilisateur as AppUser;
use App\Service\SessionHistoryRecordService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Contracts\Service\Attribute\Required;

class UserChecker implements UserCheckerInterface
{
    public const ACCOUNT_DISABLED_CODE = 666;
    public const NO_MORE_SESSION_AVAILABLE = 667;

    #[Required]
    public SessionHistoryRecordService $sessionService;

    #[Required]
    public EntityManagerInterface $entityManager;

    public function checkPreAuth(UserInterface $user): void
    {
        if (!$user instanceof AppUser) {
            return;
        }

        if (!$user->getStatus()) {
            throw new CustomUserMessageAccountStatusException('Votre compte est désactivé. Veuillez contacter votre administrateur.', [], self::ACCOUNT_DISABLED_CODE);
        }
        $this->sessionService->closeInactiveSessions($this->entityManager);
        if (!$this->sessionService->isLoginPossible($this->entityManager)) {
            throw new CustomUserMessageAccountStatusException('Le nombre de licence utilisés en cours sur cette instance a déjà été atteint. ', [], self::NO_MORE_SESSION_AVAILABLE);
        }
    }


    public function checkPostAuth(UserInterface $user): void
    {
    }
}
