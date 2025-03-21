<?php

namespace App\Service;

use App\Entity\CategoryType;
use App\Entity\Collecte;
use App\Entity\CollecteReference;
use App\Entity\DeliveryRequest\DeliveryRequestArticleLine;
use App\Entity\DeliveryRequest\DeliveryRequestReferenceLine;
use App\Entity\DeliveryRequest\Demande;
use App\Entity\Emplacement;
use App\Entity\Handling;
use App\Entity\IOT\SensorWrapper;
use App\Entity\OrdreCollecte;
use App\Entity\OrdreCollecteReference;
use App\Entity\ReferenceArticle;
use App\Entity\RequestTemplate\CollectRequestTemplate;
use App\Entity\RequestTemplate\DeliveryRequestTemplateInterface;
use App\Entity\RequestTemplate\DeliveryRequestTemplateSleepingStock;
use App\Entity\RequestTemplate\HandlingRequestTemplate;
use App\Entity\RequestTemplate\RequestTemplate;
use App\Entity\RequestTemplate\RequestTemplateLineArticle;
use App\Entity\RequestTemplate\RequestTemplateLineReference;
use App\Entity\Statut;
use App\Entity\Type;
use App\Repository\StatutRepository;
use DateInterval;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;

class RequestTemplateService {
    public function __construct(
        private EntityManagerInterface $manager,
        private AttachmentService      $attachmentService,
        private FreeFieldService       $freeFieldService,
        private NotificationService    $notificationService,
        private UniqueNumberService    $uniqueNumberService,
        private StatusHistoryService   $statusHistoryService,
    ) {}

    public function getType(string $type): ?Type {
        $category = CategoryType::REQUEST_TEMPLATE;
        if(!in_array($type, [Type::LABEL_HANDLING, Type::LABEL_DELIVERY, Type::LABEL_COLLECT])) {
            throw new RuntimeException("Unknown type $type");
        }

        return $this->manager->getRepository(Type::class)->findOneByCategoryLabelAndLabel($category, $type);
    }

    public function updateRequestTemplate(RequestTemplate $template, array $data, array $files): void {
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


    public function updateRequestTemplateLine(RequestTemplateLineReference $line, array $data): void {
        $referenceRepository = $this->manager->getRepository(ReferenceArticle::class);

        $line->setReference($referenceRepository->find($data["reference"]))
            ->setQuantityToTake($data["quantityToTake"]);
    }

    public function treatRequestTemplateTriggerType(RequestTemplate        $requestTemplate,
                                                    EntityManagerInterface $entityManager,
                                                    ?SensorWrapper         $wrapper = null): void {
        $statutRepository = $entityManager->getRepository(Statut::class);

        if ($requestTemplate instanceof DeliveryRequestTemplateInterface) {
            $request = $this->cleanCreateDeliveryRequest($statutRepository, $entityManager, $requestTemplate, $wrapper);

            $valid = true;
            foreach ($request->getReferenceLines() as $ligneArticle) {
                $article = $ligneArticle->getReference();
                if ($article->getQuantiteDisponible() < $ligneArticle->getQuantityToPick()) {
                    $valid = false;
                    break;
                }
            }

            foreach ($request->getArticleLines() as $ligneArticle) {
                $article = $ligneArticle->getArticle();
                if ($article->getQuantite() < $ligneArticle->getQuantityToPick()) {
                    $valid = false;
                    break;
                }
            }

            if ($valid) {
                // TODO REPARER CA
                // $this->deliveryRequestService->validateDLAfterCheck($entityManager, $request, false, true, false);
            }

            $this->uniqueNumberService->createWithRetry(
                $entityManager,
                Demande::NUMBER_PREFIX,
                Demande::class,
                UniqueNumberService::DATE_COUNTER_FORMAT_DEFAULT,
                function (string $number) use ($request, $entityManager) {
                    $request->setNumero($number);
                    $entityManager->persist($request);
                    $entityManager->flush();
                }
            );
        } else if ($requestTemplate instanceof CollectRequestTemplate) {
            $request = $this->cleanCreateCollectRequest($statutRepository, $entityManager, $wrapper, $requestTemplate);
            $entityManager->persist($request);
            $entityManager->flush();
        } else if ($requestTemplate instanceof HandlingRequestTemplate) {
            $request = $this->cleanCreateHandlingRequest($wrapper, $requestTemplate, $entityManager);

            $this->uniqueNumberService->createWithRetry(
                $entityManager,
                Handling::NUMBER_PREFIX,
                Handling::class,
                UniqueNumberService::DATE_COUNTER_FORMAT_DEFAULT,
                function (string $number) use ($request, $entityManager) {
                    $request->setNumber($number);
                    $entityManager->persist($request);
                    $entityManager->flush();

                    if (($request->getStatus()->getState() == Statut::NOT_TREATED)
                        && $request->getType()
                        && (($request->getType()->isNotificationsEnabled() && !$request->getType()->getNotificationsEmergencies())
                            || $request->getType()->isNotificationsEmergency($request->getEmergency()))) {
                        $this->notificationService->toTreat($request);
                    }
                }
            );
        }
    }

    private function cleanCreateDeliveryRequest(StatutRepository                 $statutRepository,
                                                EntityManagerInterface           $entityManager,
                                                DeliveryRequestTemplateInterface $requestTemplate,
                                                ?SensorWrapper                   $wrapper = null): Demande {
        $statut = $statutRepository->findOneByCategorieNameAndStatutCode(Demande::CATEGORIE, Demande::STATUT_BROUILLON);

        $date = new DateTime('now');

        $request = new Demande();
        $request
            ->setStatut($statut)
            ->setCreatedAt($date)
            ->setCommentaire($requestTemplate->getComment())
            ->setTriggeringSensorWrapper($wrapper)
            ->setType($requestTemplate->getRequestType())
            ->setDestination($requestTemplate->getDestination())
            ->setFreeFields($requestTemplate->getFreeFields());

        foreach ($requestTemplate->getLines() as $requestTemplateLine) {
            $line = match (true) {
                $requestTemplateLine Instanceof RequestTemplateLineReference => (new DeliveryRequestReferenceLine())
                    ->setReference($requestTemplateLine->getReference()),
                $requestTemplateLine Instanceof RequestTemplateLineArticle => (new DeliveryRequestArticleLine())
                    ->setArticle($requestTemplateLine->getArticle()),
            };

            $line
                ->setRequest($request)
                ->setQuantityToPick($requestTemplateLine->getQuantityToTake()); // protection contre quantités négatives
            $entityManager->persist($line);

            $request->addLine($line);
        }

        return $request;
    }

    private function cleanCreateCollectRequest(StatutRepository $statutRepository,
                                               EntityManagerInterface $entityManager,
                                               SensorWrapper $wrapper,
                                               CollectRequestTemplate $requestTemplate): Collecte {
        $date = new DateTime('now');
        $numero = $this->uniqueNumberService->create($entityManager, Collecte::NUMBER_PREFIX, Collecte::class, UniqueNumberService::DATE_COUNTER_FORMAT_COLLECT);
        $status = $statutRepository->findOneByCategorieNameAndStatutCode(Collecte::CATEGORIE, Collecte::STATUT_BROUILLON);

        $request = new Collecte();
        $request
            ->setTriggeringSensorWrapper($wrapper)
            ->setNumero($numero)
            ->setDate($date)
            ->setFreeFields($requestTemplate->getFreeFields())
            ->setType($requestTemplate->getRequestType())
            ->setStatut($status)
            ->setPointCollecte($requestTemplate->getCollectPoint())
            ->setObjet($requestTemplate->getSubject())
            ->setCommentaire($requestTemplate->getComment())
            ->setstockOrDestruct($requestTemplate->getDestination());
        $entityManager->persist($request);
        $entityManager->flush();

        foreach ($requestTemplate->getLines() as $requestTemplateLine) {
            $ligneArticle = new CollecteReference();
            $ligneArticle
                ->setReferenceArticle($requestTemplateLine->getReference())
                ->setCollecte($request)
                ->setQuantite($requestTemplateLine->getQuantityToTake()); // protection contre quantités négatives
            $entityManager->persist($ligneArticle);
            $request->addCollecteReference($ligneArticle);
        }
        $ordreCollecte = $this->cleanCreateCollectOrder($statutRepository, $request, $entityManager);
        $entityManager->flush();

        if ($ordreCollecte->getDemandeCollecte()->getType()->isNotificationsEnabled()) {
            $this->notificationService->toTreat($ordreCollecte);
        }
        return $request;
    }


    private function cleanCreateCollectOrder(StatutRepository $statutRepository, Collecte $demandeCollecte, EntityManagerInterface $entityManager): OrdreCollecte {

        $statut = $statutRepository
            ->findOneByCategorieNameAndStatutCode(OrdreCollecte::CATEGORIE, OrdreCollecte::STATUT_A_TRAITER);
        $ordreCollecte = new OrdreCollecte();
        $date = new DateTime('now');
        $ordreCollecte
            ->setDate($date)
            ->setNumero('C-' . $date->format('YmdHis'))
            ->setStatut($statut)
            ->setDemandeCollecte($demandeCollecte);
        foreach ($demandeCollecte->getArticles() as $article) {
            $ordreCollecte->addArticle($article);
        }
        foreach ($demandeCollecte->getCollecteReferences() as $collecteReference) {
            $ordreCollecteReference = new OrdreCollecteReference();
            $ordreCollecteReference
                ->setOrdreCollecte($ordreCollecte)
                ->setQuantite($collecteReference->getQuantite())
                ->setReferenceArticle($collecteReference->getReferenceArticle());
            $entityManager->persist($ordreCollecteReference);
            $ordreCollecte->addOrdreCollecteReference($ordreCollecteReference);
        }

        $entityManager->persist($ordreCollecte);


        // on modifie statut + date validation de la demande
        $demandeCollecte
            ->setStatut(
                $statutRepository->findOneByCategorieNameAndStatutCode(Collecte::CATEGORIE, Collecte::STATUT_A_TRAITER)
            )
            ->setValidationDate($date);

        return $ordreCollecte;
    }

    private function cleanCreateHandlingRequest(SensorWrapper           $sensorWrapper,
                                                HandlingRequestTemplate $requestTemplate,
                                                EntityManagerInterface  $entityManager): Handling {
        $handling = new Handling();
        $date = new DateTime('now');

        $desiredDate = clone $date;
        $desiredDate = $desiredDate->add(new DateInterval('PT' . $requestTemplate->getDelay() . 'H'));

        $this->statusHistoryService->updateStatus($entityManager, $handling, $requestTemplate->getRequestStatus(), [
            "forceCreation" => false,
        ]);

        $handling
            ->setFreeFields($requestTemplate->getFreeFields())
            ->setCarriedOutOperationCount($requestTemplate->getCarriedOutOperationCount())
            ->setSource($requestTemplate->getSource())
            ->setEmergency($requestTemplate->getEmergency())
            ->setDestination($requestTemplate->getDestination())
            ->setType($requestTemplate->getRequestType())
            ->setCreationDate($date)
            ->setTriggeringSensorWrapper($sensorWrapper)
            ->setComment($requestTemplate->getComment())
            ->setAttachments($requestTemplate->getAttachments())
            ->setSubject($requestTemplate->getSubject())
            ->setDesiredDate($desiredDate);

        return $handling;
    }
}
