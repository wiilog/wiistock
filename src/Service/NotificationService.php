<?php

namespace App\Service;

use App\Entity\Collecte;
use App\Entity\Dispatch;
use App\Entity\Handling;
use App\Entity\Livraison;
use App\Entity\NotificationTemplate;
use App\Entity\OrdreCollecte;
use App\Entity\Preparation;
use App\Entity\TransferOrder;
use Doctrine\ORM\EntityManagerInterface;
use Kreait\Firebase\Messaging;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;

class NotificationService
{

    public const CHANNELS = [
        Livraison::class => "stock",
        Preparation::class => "stock",
        Collecte::class => "stock",
        TransferOrder::class => "stock",
        Dispatch::class => "tracking",
        Handling::class => "demande",
    ];

    /** @Required */
    public EntityManagerInterface $manager;

    /** @Required */
    public VariableService $variableService;

    /** @Required */
    public Messaging $messaging;

    public function toTreat($entity)
    {
        $channel = self::CHANNELS[get_class($entity)];
        $title = NotificationTemplate::READABLE_TYPES[NotificationTemplate::TYPE_BY_CLASS[get_class($entity)]];

        $notificationTemplateRepository = $this->manager->getRepository(NotificationTemplate::class);
        $template = $notificationTemplateRepository->findByType($entity);

        $this->send($channel, $title, $this->variableService->replaceVariables($template->getContent(), $entity), [
            "type" => NotificationTemplate::TYPE_BY_CLASS[get_class($entity)],
            "id" => $entity->getId()
        ]);
    }

    public function send(string $channel, string $title, string $content, ?array $data = null)
    {
        $message = CloudMessage::withTarget("topic", $_SERVER["APP_INSTANCE"] . "-" . $channel)
            ->withNotification(Notification::create($title, $content));

        if($data) {
            $message->withData($data);
        }

        $this->messaging->send($message);
    }

}
