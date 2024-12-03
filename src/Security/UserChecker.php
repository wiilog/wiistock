<?php

namespace App\Security;

use App\Entity\Utilisateur as AppUser;
use App\Service\SessionHistoryRecordService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class UserChecker implements UserCheckerInterface
{
    public const ACCOUNT_DISABLED_CODE = 666;
    public const NO_MORE_SESSION_AVAILABLE = 667;

    public function __construct(
        private SessionHistoryRecordService $sessionService,
        private EntityManagerInterface $entityManager,
    ){}

    public function checkPreAuth(UserInterface $user): void {
        if (!$user instanceof AppUser) {
            return;
        }

        if (!$user->getStatus()) {
            throw new CustomUserMessageAccountStatusException("Votre compte est désactivé. Veuillez contacter votre administrateur.", [], self::ACCOUNT_DISABLED_CODE);
        }
        $this->sessionService->closeInactiveSessions($this->entityManager);
        if (!$this->sessionService->isLoginPossible($this->entityManager, $user)) {
            throw new CustomUserMessageAccountStatusException("Le nombre de licences utilisées en cours sur cette instance a déjà été atteint.", [], self::NO_MORE_SESSION_AVAILABLE);
        }

        if (($_POST["_remember_me"] ?? false) === "on" && !$user->isAllowedToBeRemembered()) {
            throw new CustomUserMessageAccountStatusException("Vous n'êtes pas autorisé à rester connecté.", [], self::ACCOUNT_DISABLED_CODE);
        }
    }

    public function checkPostAuth(UserInterface $user): void {}
}
