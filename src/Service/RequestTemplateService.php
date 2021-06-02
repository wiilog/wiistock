<?php

namespace App\Service;

use App\Entity\CategoryType;
use App\Entity\IOT\CollectRequestTemplate;
use App\Entity\IOT\DeliveryRequestTemplate;
use App\Entity\IOT\HandlingRequestTemplate;
use App\Entity\IOT\RequestTemplate;
use App\Entity\Statut;
use App\Entity\Type;
use App\Repository\TypeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;

class RequestTemplateService {

    /** @Required */
    public EntityManagerInterface $manager;

    /** @Required */
    public AttachmentService $attachmentService;

    public function getType(int $type): ?Type {
        $category = CategoryType::REQUEST_TEMPLATE;

        if ($type === RequestTemplate::TYPE_HANDLING) {
            $type = Type::LABEL_HANDLING;
        } else if ($type === RequestTemplate::TYPE_DELIVERY) {
            $type = Type::LABEL_DELIVERY;
        } else if ($type === RequestTemplate::TYPE_COLLECT) {
            $type = Type::LABEL_COLLECT;
        } else {
            throw new \RuntimeException("Unknown type $type");
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
            throw new \RuntimeException("Unknown type $type");
        }
    }

    public function updateRequestTemplate(RequestTemplate $template, Request $request) {
        $data = $request->request->all();

        $typeRepository = $this->manager->getRepository(Type::class);
        $statusRepository = $this->manager->getRepository(Statut::class);

        $template->setName($data["name"]);
        if (!$template->getType()) {
            $template->setType($this->getType($data["type"]));
        }

        $template->setRequestType($typeRepository->find($data["requestType"]));

        if ($template instanceof HandlingRequestTemplate) {
            $template->setSubject($data["subject"])
                ->setRequestStatus($statusRepository->find($data["status"]))
                ->setDelay($data["delay"])
                ->setComment($data["comment"] ?? "")
                ->setAttachments($this->attachmentService->createAttachements($request->files));
        } else if ($template instanceof DeliveryRequestTemplate) {
            //TODO 4456: update the request template
        } else if ($template instanceof CollectRequestTemplate) {
            //TODO 4457: update the request template
        } else {
            throw new \RuntimeException("Unknown request template");
        }
    }

}
