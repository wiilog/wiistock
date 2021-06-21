<?php

namespace App\Service;

use App\Entity\CategorieCL;
use App\Entity\CategoryType;
use App\Entity\Collecte;
use App\Entity\Emplacement;
use App\Entity\IOT\CollectRequestTemplate;
use App\Entity\IOT\DeliveryRequestTemplate;
use App\Entity\IOT\HandlingRequestTemplate;
use App\Entity\IOT\RequestTemplate;
use App\Entity\IOT\RequestTemplateLine;
use App\Entity\ReferenceArticle;
use App\Entity\Statut;
use App\Entity\Type;
use App\Helper\FormatHelper;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;

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

    /**
     * @return HandlingRequestTemplate|DeliveryRequestTemplate|CollectRequestTemplate
     */
    public function createRequestTemplate(int $type): RequestTemplate {
        if ($type === RequestTemplate::TYPE_HANDLING) {
            return new HandlingRequestTemplate();
        } else if ($type === RequestTemplate::TYPE_DELIVERY) {
            return new DeliveryRequestTemplate();
        } else if ($type === RequestTemplate::TYPE_COLLECT) {
            return new CollectRequestTemplate();
        } else {
            throw new RuntimeException("Unknown type $type");
        }
    }

    public function updateRequestTemplate(RequestTemplate $template, Request $request) {
        if (!($data = json_decode($request->getContent(), true))) {
            $data = $request->request->all();
        }

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
                ->setDelay($data["delay"])
                ->setEmergency($data["emergency"] ?? null)
                ->setSource($data["source"] ?? null)
                ->setDestination($data["destination"] ?? null)
                ->setCarriedOutOperationCount(($data["carriedOutOperationCount"] ?? null) ?: null)
                ->setComment($data["comment"] ?? null)
                ->setAttachments($this->attachmentService->createAttachements($request->files));
        } else if ($template instanceof DeliveryRequestTemplate) {
            $locationRepository = $this->manager->getRepository(Emplacement::class);

            $template->setRequestType($typeRepository->find($data["deliveryType"]))
                ->setDestination($locationRepository->find($data["destination"]))
                ->setComment($data["comment"] ?? "");
        } else if ($template instanceof CollectRequestTemplate) {
            $locationRepository = $this->manager->getRepository(Emplacement::class);

            $template->setRequestType($typeRepository->find($data["collectType"]))
                ->setSubject($data["subject"])
                ->setCollectPoint($locationRepository->find($data["collectPoint"]))
                ->setDestination($data["destination"])
                ->setComment($data["comment"] ?? "");
        } else {
            throw new RuntimeException("Unknown request template");
        }
    }

    public function createHeaderDetailsConfig(RequestTemplate $requestTemplate): array {
        if ($requestTemplate instanceof DeliveryRequestTemplate) {
            $clCategory = CategorieCL::DEMANDE_LIVRAISON;
            $typeCategory = CategoryType::DEMANDE_LIVRAISON;

            $header = [
                ["label" => "Destination", "value" => FormatHelper::location($requestTemplate->getDestination())],
                ["label" => "Type", "value" => FormatHelper::type($requestTemplate->getRequestType())],
            ];
        } else if ($requestTemplate instanceof CollectRequestTemplate) {
            $clCategory = CategorieCL::DEMANDE_COLLECTE;
            $typeCategory = CategoryType::DEMANDE_COLLECTE;

            $header = [
                ["label" => "Destination", "value" => $requestTemplate->isStock() ? "Mise en stock" : "Destruction"],
                ["label" => "Type", "value" => $requestTemplate->getSubject()],
                ["label" => "Point de collecte", "value" => FormatHelper::location($requestTemplate->getCollectPoint())],
                ["label" => "Type", "value" => FormatHelper::type($requestTemplate->getRequestType())],
            ];
        } else {
            throw new RuntimeException("Unsupported type");
        }

        $freeFieldArray = $this->freeFieldService->getFilledFreeFieldArray(
            $this->manager,
            $requestTemplate,
            $clCategory,
            $typeCategory
        );

        return array_merge($header, $freeFieldArray);
    }

    public function updateRequestTemplateLine(RequestTemplateLine $line, array $data) {
        $referenceRepository = $this->manager->getRepository(ReferenceArticle::class);

        $line->setReference($referenceRepository->find($data["reference"]))
            ->setQuantityToTake($data["quantityToTake"]);
    }

}
