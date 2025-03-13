<?php


namespace App\EventListener\Doctrine;

use App\Entity\CategoryType;
use App\Entity\Type;
use App\Entity\Utilisateur;
use App\Service\CacheService;
use App\Service\SessionHistoryRecordService;
use DateTime;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;
use Symfony\Component\HttpFoundation\RequestStack;

#[AsEntityListener(event: Events::preUpdate, method: 'preUpdate', lazy: true, entity: Utilisateur::class)]
#[AsDoctrineListener(event: Events::postFlush)]
class PasswordChangedListener {

    /**
     * @var array<Utilisateur> $updatedUsers
     */
    private array $updatedUsers = [];

    public function __construct(private SessionHistoryRecordService $sessionHistoryRecordService,
                                private RequestStack                $requestStack,
                                private CacheService                $cacheService) {}

    public function preUpdate(Utilisateur        $user,
                              PreUpdateEventArgs $lifecycleEventArgs): void {
        if ($lifecycleEventArgs->hasChangedField("password")) {
            $this->updatedUsers[] = $user;
        }
    }

    public function postFlush(PostFlushEventArgs $args): void {
        if(empty($this->updatedUsers)) {
            return;
        }
        $entityManager = $args->getObjectManager();

        $sessionId = $this->requestStack->getSession()->getId();
        $closedAt = new DateTime();

        $sessionNomadeType = $this->cacheService->getEntity($entityManager, Type::class, CategoryType::SESSION_HISTORY, Type::LABEL_NOMADE_SESSION_HISTORY);
        $sessionWebType = $this->cacheService->getEntity($entityManager, Type::class, CategoryType::SESSION_HISTORY, Type::LABEL_WEB_SESSION_HISTORY);

        foreach ($this->updatedUsers as $user) {
            $this->sessionHistoryRecordService->closeOpenedSessionsByUserAndType($entityManager, $user, $sessionNomadeType, $closedAt, $sessionId);
            $this->sessionHistoryRecordService->closeOpenedSessionsByUserAndType($entityManager, $user, $sessionWebType, $closedAt, $sessionId);
        }

        $this->updatedUsers = [];
        $entityManager->flush();
    }
}
