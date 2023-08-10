<?php

namespace App\Service;

use App\Entity\SessionHistory;
use App\Entity\Type;
use App\Entity\Utilisateur;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;

class SessionHistoryService{

    public const UNLIMITED_SESSION = -1;

    public function logSession(EntityManagerInterface $entityManager, ?Utilisateur $user, Request $request,DateTime $date, Type $type): bool{
        $sessionHistoryRepository = $entityManager->getRepository(SessionHistory::class);
        $session = $request->getSession();

        $sessionHistory = (new SessionHistory())
            ->setUser($user)
            ->setOpenedAt($date)
            ->setType($type)
            ->setSessionId($session->getId());

        $entityManager->persist($sessionHistory);

        $user->setLastLogin($date);
        $entityManager->flush();
        return true;
    }



    public function isLogginPossible(EntityManagerInterface $entityManager, Utilisateur $utilisateur): bool{
        $sessionHistoryRepository = $entityManager->getRepository(SessionHistory::class);
        $oppenedSessionLimit = (int)$_SERVER["SESSION_LIMIT"] ?? self::UNLIMITED_SESSION;
        if ($oppenedSessionLimit === self::UNLIMITED_SESSION) {
            return true;
        } else {
            $oppenedSessionsHistory = $sessionHistoryRepository->count([
                'closedAt' => null,
            ]);
            return $oppenedSessionsHistory < $oppenedSessionLimit;
        }
    }
}
