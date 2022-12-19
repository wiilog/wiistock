<?php

namespace App\Service;

use App\Entity\CategoryType;
use App\Entity\Emplacement;
use App\Entity\IOT\CollectRequestTemplate;
use App\Entity\IOT\DeliveryRequestTemplate;
use App\Entity\IOT\HandlingRequestTemplate;
use App\Entity\IOT\RequestTemplate;
use App\Entity\IOT\RequestTemplateLine;
use App\Entity\ReferenceArticle;
use App\Entity\Statut;
use App\Entity\Type;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use WiiCommon\Helper\StringHelper;

class RequestTemplateService {

    /** @Required */
    public EntityManagerInterface $manager;

    /** @Required */
    public AttachmentService $attachmentService;

    /** @Required */
    public FreeFieldService $freeFieldService;

    public function getType(int $type): ?Type {
        $category = CategoryType::REQUEST_TEMPLATE;

        if ($type === RequestTemplate::TYPE_HANDLING) {
            $type = Type::LABEL_HANDLING;
        } else if ($type === RequestTemplate::TYPE_DELIVERY) {
            $type = Type::LABEL_DELIVERY;
        } else if ($type === RequestTemplate::TYPE_COLLECT) {
            $type = Type::LABEL_COLLECT;
        } else {
            throw new RuntimeException("Unknown type $type");
        }

        return $this->manager->getRepository(Type::class)->findOneByCategoryLabelAndLabel($category, $type);
    }

    public function updateRequestTemplate(RequestTemplate $template, array $data, array $files) {
        $typeRepository = $this->manager->getRepository(Type::class);

        $template->setName($data["name"]);
        if (!$template->getType()) {
            $template->setType($this->getType($data["type"]));
        }
        $this->freeFieldService->manageFreeFields($template, $data, $this->manager);

        if ($template instanceof HandlingRequestTemplate) {
            $statusRepository = $this->manager->getRepository(Statut::class);

            $template->setRequestType($typeRepository->find($data["handlingType"]))
                ->setSubject($data["subject"])
                ->setRequestStatus($statusRepository->find($data["status"]))
                ->setDelay((int) $data["delay"])
                ->setEmergency($data["emergency"] ?? null)
                ->setSource($data["source"] ?? null)
                ->setDestination($data["destination"] ?? null)
                ->setCarriedOutOperationCount(($data["carriedOutOperationCount"] ?? null) ?: null)
                ->setComment(StringHelper::cleanedComment($data["comment"] ?? null))
                ->setAttachments($this->attachmentService->createAttachements($files));
        } else if ($template instanceof DeliveryRequestTemplate) {
            $locationRepository = $this->manager->getRepository(Emplacement::class);

            $template->setRequestType($typeRepository->find($data["deliveryType"]))
                ->setDestination($locationRepository->find($data["destination"]))
                ->setComment(StringHelper::cleanedComment($data["comment"] ?? null));
        } else if ($template instanceof CollectRequestTemplate) {
            $locationRepository = $this->manager->getRepository(Emplacement::class);

            $template->setRequestType($typeRepository->find($data["collectType"]))
                ->setSubject($data["subject"])
                ->setCollectPoint($locationRepository->find($data["collectPoint"]))
                ->setDestination($data["destination"])
                ->setComment(StringHelper::cleanedComment($data["comment"] ?? null));
        } else {
            throw new RuntimeException("Unknown request template");
        }
    }


    public function updateRequestTemplateLine(RequestTemplateLine $line, array $data) {
        $referenceRepository = $this->manager->getRepository(ReferenceArticle::class);

        $line->setReference($referenceRepository->find($data["reference"]))
            ->setQuantityToTake($data["quantityToTake"]);
    }

}
