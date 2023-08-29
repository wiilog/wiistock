<?php

namespace App\Service;

use App\Entity\CategoryType;
use App\Entity\SessionHistoryRecord;
use App\Entity\Type;
use App\Entity\UserSession;
use App\Entity\Utilisateur;
use App\Entity\Wiilock;
use App\Helper\FormatHelper;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Service\Attribute\Required;

class SessionHistoryRecordService{

    #[Required]
    public FormatService $formatService;

    public const UNLIMITED_SESSIONS = 0;

    public const MAX_SESSIONS_POSSIBLE = 2000;

    public function newSessionHistoryRecord(EntityManagerInterface $entityManager,
                                            Utilisateur            $user,
                                            DateTime               $date,
                                            Type                   $type,
                                            Request|string         $request): bool {
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

    public function isLoginPossible(EntityManagerInterface $entityManager, Utilisateur $user): bool {
        $sessionHistoryRepository = $entityManager->getRepository(SessionHistoryRecord::class);
        $openedSessionLimit = intval($_SERVER["SESSION_LIMIT"] ?? self::UNLIMITED_SESSIONS);

        if ($openedSessionLimit === self::UNLIMITED_SESSIONS || $user->isWiilogUser()) {
            return true;
        } else {
            $openedSessionsHistory = $sessionHistoryRepository->countsNonWiilogOpenedSessions();
            return $openedSessionsHistory < $openedSessionLimit;
        }
    }

    public function getOpenedSessionLimit(): int {
        return intval($_SERVER["SESSION_LIMIT"] ?? self::MAX_SESSIONS_POSSIBLE);
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
            $userSessionRepository = $entityManager->getRepository(UserSession::class);
            $sessionHistory->setClosedAt($date);
            $userSession = $userSessionRepository->find($sessionHistory->getSessionId());
            if ($userSession) {
                $entityManager->remove($userSession);
            }

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
        $dateTime = $dateTime ?? new DateTime();
        $sessionsToClose = $sessionHistoryRepository->findBy([
            "user" => $user,
            "closedAt" => null,
            "type" => $type,
        ]);

        foreach ($sessionsToClose as $sessionToClose) {
            $this->closeSessionHistoryRecord($entityManager, $sessionToClose, $dateTime);
        }
    }

    public function refreshDate(EntityManagerInterface $entityManager): string {
        $wiilockRepository = $entityManager->getRepository(Wiilock::class);
        $lock = $wiilockRepository->findOneBy(["lockKey" => Wiilock::INACTIVE_SESSIONS_CLEAN_KEY]);

        return $lock
            ? $this->formatService->datetime($lock->getUpdateDate())
            : "-";
    }
}
