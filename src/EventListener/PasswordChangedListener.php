<?php


namespace App\EventListener;

use App\Entity\CategoryType;
use App\Entity\Type;
use App\Entity\Utilisateur;
use App\Service\SessionHistoryRecordService;
use DateTime;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Symfony\Component\HttpFoundation\RequestStack;

class PasswordChangedListener implements EventSubscriber {

    private array $updatedUsers = [];
    public function getSubscribedEvents(): array
    {
        return [
            "preUpdate",
            "postFlush"
        ];
    }

    public function __construct(private SessionHistoryRecordService $sessionHistoryRecordService,
                                private RequestStack                $requestStack) {}

    #[AsEventListener(event: "preUpdate")]
    public function preUpdate(Utilisateur        $user,
                              PreUpdateEventArgs $lifecycleEventArgs): void {
        if ($lifecycleEventArgs->hasChangedField("password")) {
            $this->updatedUsers[] = $user;
        }
    }

    #[AsEventListener(event: "postFlush")]
    public function postFlush(PostFlushEventArgs $args): void {
        if(empty($this->updatedUsers)) {
            return;
        }
        $entityManager = $args->getObjectManager();
        $typeRepository = $entityManager->getRepository(Type::class);

        $sessionId = $this->requestStack->getSession()->getId();
        $closedAt = new DateTime();

        $sessionNomadeType = $typeRepository->findOneByCategoryLabelAndLabel(CategoryType::SESSION_HISTORY, Type::LABEL_NOMADE_SESSION_HISTORY);
        $sessionWebType = $typeRepository->findOneByCategoryLabelAndLabel(CategoryType::SESSION_HISTORY, Type::LABEL_WEB_SESSION_HISTORY);

        foreach ($this->updatedUsers as $user) {
            $this->sessionHistoryRecordService->closeOpenedSessionsByUserAndType($entityManager, $user, $sessionNomadeType, $closedAt, $sessionId);
            $this->sessionHistoryRecordService->closeOpenedSessionsByUserAndType($entityManager, $user, $sessionWebType, $closedAt, $sessionId);
        }

        $this->updatedUsers = [];
        $entityManager->flush();
    }
}
