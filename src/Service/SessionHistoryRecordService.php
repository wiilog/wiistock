<?php

namespace App\Service;

use App\Entity\SessionHistoryRecord;
use App\Entity\Type;
use App\Entity\UserSession;
use App\Entity\Utilisateur;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;

class SessionHistoryRecordService{

    public const UNLIMITED_SESSION = -1;

    public function newSessionHistoryRecord(EntityManagerInterface $entityManager, ?Utilisateur $user, Request $request, DateTime $date, Type $type): bool{
        $sessionHistoryRepository = $entityManager->getRepository(SessionHistoryRecord::class);
        $session = $request->getSession();

        $historyAllreadyExists = $sessionHistoryRepository->findOneBy([
            'sessionId' => $session->getId(),
            'closedAt' => null,
        ]);
        if (!$historyAllreadyExists) {
            $sessionHistory = (new SessionHistoryRecord())
                ->setUser($user)
                ->setOpenedAt($date)
                ->setType($type)
                ->setSessionId($session->getId());

            $entityManager->persist($sessionHistory);

            $user->setLastLogin($date);
        }

        $entityManager->flush();
        return true;
    }

    public function isLoginPossible(EntityManagerInterface $entityManager, Utilisateur $utilisateur): bool{
        $sessionHistoryRepository = $entityManager->getRepository(SessionHistoryRecord::class);
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

    public function closeSessionHistoryRecord(EntityManagerInterface $entityManager, SessionHistoryRecord|string $sessionHistory, DateTime $date): bool{
        if(is_string($sessionHistory)){
            $sessionHistoryRepository = $entityManager->getRepository(SessionHistoryRecord::class);
            $sessionHistory = $sessionHistoryRepository->findOneBy([
                'sessionId' => $sessionHistory,
                'closedAt' => null,
            ]);
        }

        if ($sessionHistory) {
            $sessionHistory->setClosedAt($date);
            $entityManager->flush();
            return true;
        }
        return false;
    }

    public function closeInactiveSessions(EntityManagerInterface $entityManager): void{
        $sessionHistoryRepository = $entityManager->getRepository(SessionHistoryRecord::class);
        $sessionsToClose = $sessionHistoryRepository->findSessionHistoryRecordToClose();
        $now = (new DateTime());
        foreach ($sessionsToClose as $sessionToClose) {
            $this->closeSessionHistoryRecord($entityManager, $sessionToClose, $now);
        }
    }
}
