<?php

namespace App\Service;

use App\Entity\CategoryType;
use App\Entity\SessionHistoryRecord;
use App\Entity\Type;
use App\Entity\Utilisateur;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;

class SessionHistoryRecordService{

    public const UNLIMITED_SESSIONS = -1;

    public function newSessionHistoryRecord(EntityManagerInterface $entityManager,
                                            ?Utilisateur           $user,
                                            DateTime               $date,
                                            Type                   $type,
                                            Request|string         $request): bool
    {
        $sessionHistoryRepository = $entityManager->getRepository(SessionHistoryRecord::class);
        $sessionId = $request instanceof Request ? $request?->getSession()?->getId() : $request;

        $historyAlreadyExists = $sessionHistoryRepository->findOneBy([
            'sessionId' => $sessionId,
            'closedAt' => null,
        ]);

        if (!$historyAlreadyExists) {
            $sessionHistory = (new SessionHistoryRecord())
                ->setUser($user)
                ->setOpenedAt($date)
                ->setType($type)
                ->setSessionId($sessionId);

            $entityManager->persist($sessionHistory);
            $user->setLastLogin($date);
        }

        $entityManager->flush();
        return true;
    }

    public function isLoginPossible(EntityManagerInterface $entityManager, Utilisateur $utilisateur): bool {
        $sessionHistoryRepository = $entityManager->getRepository(SessionHistoryRecord::class);
        $oppenedSessionLimit = intval($_SERVER["SESSION_LIMIT"] ?? self::UNLIMITED_SESSIONS);

        if ($oppenedSessionLimit === self::UNLIMITED_SESSIONS) {
            return true;
        } else {
            $oppenedSessionsHistory = $sessionHistoryRepository->count([
                'closedAt' => null,
            ]);
            return $oppenedSessionsHistory < $oppenedSessionLimit;
        }
    }

    public function closeSessionHistoryRecord(EntityManagerInterface $entityManager, SessionHistoryRecord|string $sessionHistory, DateTime $date): bool {
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

    public function closeInactiveSessions(EntityManagerInterface $entityManager): void {
        $sessionHistoryRepository = $entityManager->getRepository(SessionHistoryRecord::class);
        $typeRepository = $entityManager->getRepository(Type::class);
        $sessionType = $typeRepository->findOneByCategoryLabelAndLabel(CategoryType::SESSION_HISTORY, Type::LABEL_WEB_SESSION_HISTORY);
        $sessionsToClose = $sessionHistoryRepository->findSessionHistoryRecordToClose($sessionType);
        $now = new DateTime();
        foreach ($sessionsToClose as $sessionToClose) {
            $this->closeSessionHistoryRecord($entityManager, $sessionToClose, $now);
        }
    }

    public function closeOpenedSessionsByUserAndType(EntityManagerInterface $entityManager, Utilisateur $user, Type $type, ?DateTime $dateTime= null): void{
        $sessionHistoryRepository = $entityManager->getRepository(SessionHistoryRecord::class);
        $datetime = $datetime ?? new DateTime();
        $sessionsToClose = $sessionHistoryRepository->findBy([
            "user" => $user,
            "closedAt" => null,
            "type" => $type,
        ]);

        foreach ($sessionsToClose as $sessionToClose) {
            $this->closeSessionHistoryRecord($entityManager, $sessionToClose, $datetime);
        }
    }
}
