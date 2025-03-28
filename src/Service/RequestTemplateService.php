<?php

namespace App\Service;

use App\Entity\CategoryType;
use App\Entity\Emplacement;
use App\Entity\ReferenceArticle;
use App\Entity\RequestTemplate\CollectRequestTemplate;
use App\Entity\RequestTemplate\DeliveryRequestTemplateInterface;
use App\Entity\RequestTemplate\DeliveryRequestTemplateSleepingStock;
use App\Entity\RequestTemplate\HandlingRequestTemplate;
use App\Entity\RequestTemplate\RequestTemplate;
use App\Entity\RequestTemplate\RequestTemplateLine;
use App\Entity\Statut;
use App\Entity\Type;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Symfony\Contracts\Service\Attribute\Required;

class RequestTemplateService {

    #[Required]
    public EntityManagerInterface $manager;

    #[Required]
    public AttachmentService $attachmentService;

    #[Required]
    public FreeFieldService $freeFieldService;

    public function getType(string $type): ?Type {
        $category = CategoryType::REQUEST_TEMPLATE;
        if(!in_array($type, [Type::LABEL_HANDLING, Type::LABEL_DELIVERY, Type::LABEL_COLLECT])) {
            throw new RuntimeException("Unknown type $type");
        }

        return $this->manager->getRepository(Type::class)->findOneByCategoryLabelAndLabel($category, $type);
    }

    public function updateRequestTemplate(RequestTemplate $template, array $data, array $files): void
    {
        $typeRepository = $this->manager->getRepository(Type::class);

        $template->setName($data["name"]);
        if (!$template->getType()) {
            $template->setType($this->getType($data["entityType"]));
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
                ->setCarriedOutOperationCount(((int)$data["carriedOutOperationCount"] ?? null) ?: null)
                ->setComment($data["comment"] ?? null);

            $this->attachmentService->persistAttachments($this->manager, $files, ["attachmentContainer" => $template]);
        } else if ($template instanceof DeliveryRequestTemplateInterface) {
            $locationRepository = $this->manager->getRepository(Emplacement::class);
            if ($template instanceof DeliveryRequestTemplateSleepingStock) {
                $attachments = $this->attachmentService->persistAttachments($this->manager, $files);
                if(!empty($attachments)) {
                    $template->setButtonIcon($attachments[0]);
                }
            }
            $template->setRequestType($typeRepository->find($data["deliveryType"]))
                ->setDestination($locationRepository->find($data["destination"]))
                ->setComment($data["comment"] ?? null);
        } else if ($template instanceof CollectRequestTemplate) {
            $locationRepository = $this->manager->getRepository(Emplacement::class);

            $template->setRequestType($typeRepository->find($data["collectType"]))
                ->setSubject($data["subject"])
                ->setCollectPoint($locationRepository->find($data["collectPoint"]))
                ->setDestination($data["destination"])
                ->setComment($data["comment"] ?? null);
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
