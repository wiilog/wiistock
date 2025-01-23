<?php

namespace App\Controller\Api\Mobile;

use App\Annotation as Wii;
use App\Controller\AbstractController;
use App\Entity\Attachment;
use App\Entity\CategorieStatut;
use App\Entity\Dispatch;
use App\Entity\DispatchPack;
use App\Entity\DispatchReferenceArticle;
use App\Entity\Emplacement;
use App\Entity\Fields\FixedFieldStandard;
use App\Entity\Nature;
use App\Entity\ReferenceArticle;
use App\Entity\Setting;
use App\Entity\Statut;
use App\Entity\Tracking\Pack;
use App\Entity\Type;
use App\Entity\Utilisateur;
use App\Exceptions\FormException;
use App\Service\AttachmentService;
use App\Service\DispatchService;
use App\Service\ExceptionLoggerService;
use App\Service\MobileApiService;
use App\Service\SettingsService;
use App\Service\StatusHistoryService;
use App\Service\UniqueNumberService;
use DateTime;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Annotation\Route;
use WiiCommon\Helper\Stream;

#[Route("/api/mobile")]
class DispatchController extends AbstractController
{

    #[Route("/new-offline-dispatches", methods: [self::POST], condition: self::IS_XML_HTTP_REQUEST)]
    #[Wii\RestVersionChecked]
    public function createNewOfflineDispatchs(Request                $request,
                                              EntityManagerInterface $entityManager,
                                              DispatchService        $dispatchService,
                                              SettingsService        $settingsService,
                                              ExceptionLoggerService $exceptionLoggerService,
                                              StatusHistoryService   $statusHistoryService,
                                              UniqueNumberService    $uniqueNumberService): Response
    {
        $data = $request->request;
        $dispatchRepository = $entityManager->getRepository(Dispatch::class);
        $typeRepository = $entityManager->getRepository(Type::class);
        $statusRepository = $entityManager->getRepository(Statut::class);
        $locationRepository = $entityManager->getRepository(Emplacement::class);
        $userRepository = $entityManager->getRepository(Utilisateur::class);
        $dispatchs = json_decode($data->get('dispatches'), true);
        $dispatchReferences = json_decode($data->get('dispatchReferences'), true);

        $localIdsToInsertIds = [];
        $createdDispatchLocalIds = [];
        $syncedAt = new DateTime();
        $errors = [];
        foreach($dispatchs as $dispatchArray){
            $currentError = false;
            $wasDraft = false;

            $dispatchId = $dispatchArray['id'] ?? null;

            $createdBy = $userRepository->findOneBy(['id' => $dispatchArray['createdBy']]);
            $createdAt = $this->getFormatter()->parseDatetime($dispatchArray['createdAt']);
            $validationDate = $this->getFormatter()->parseDatetime($dispatchArray['validatedAt']);

            if ($dispatchId) {
                $dispatch = $dispatchRepository->find($dispatchId);
            }
            else {
                // get dispatch by date and operator to avoid dispatch duplication
                $matchDispatches = $dispatchRepository->findBy(['createdBy' => $createdBy, 'creationDate' => $createdAt]);
                $dispatch = $matchDispatches[0] ?? null;
            }

            if (!$dispatchId && $dispatch) { // ignore dispatch to remove duplicate
                break;
            }

            // Dispatch creation
            if (!$dispatchId && !$dispatch) {
                $createdDispatchLocalIds[] = $dispatchArray['localId'];

                $type = $typeRepository->find($dispatchArray['typeId']);
                $dispatchStatus = $dispatchArray['statusId'] ? $statusRepository->find($dispatchArray['statusId']) : null;
                $draftStatuses = !$dispatchStatus || !$dispatchStatus->isDraft() ? $statusRepository->findStatusByType(CategorieStatut::DISPATCH, $type, [Statut::DRAFT]) : [$dispatchStatus];
                $draftStatus = !empty($draftStatuses) ? $draftStatuses[0] : $dispatchStatus;
                $locationFrom = $dispatchArray['locationFromId'] ? $locationRepository->find($dispatchArray['locationFromId']) : null;
                $locationTo = $dispatchArray['locationToId'] ? $locationRepository->find($dispatchArray['locationToId']) : null;
                $requester = $dispatchArray['requester'] ? $userRepository->findOneBy(['username' => $dispatchArray['requester']]) : null;
                $requester = $requester ?? $createdBy;
                $wasDraft = true;

                $numberFormat = $settingsService->getValue($entityManager,Setting::DISPATCH_NUMBER_FORMAT);
                if(!in_array($numberFormat, Dispatch::NUMBER_FORMATS)) {
                    throw new FormException("Le format de numéro d'acheminement n'est pas valide");
                }
                $dispatchNumber = $uniqueNumberService->create($entityManager, Dispatch::NUMBER_PREFIX, Dispatch::class, $numberFormat, $createdAt);

                $dispatch = (new Dispatch())
                    ->setNumber($dispatchNumber)
                    ->setCreationDate($createdAt)
                    ->setCreatedBy($createdBy)
                    ->setRequester($requester)
                    ->setType($type)
                    ->setLocationFrom($locationFrom)
                    ->setLocationTo($locationTo)
                    ->setCarrierTrackingNumber($dispatchArray['carrierTrackingNumber'])
                    ->setCommentaire($dispatchArray['comment'])
                    ->setEmergency($dispatchArray['emergency'] ?? null)
                    ->setUpdatedAt($syncedAt);
                $entityManager->persist($dispatch);

                if($draftStatus){
                    $statusHistoryService->updateStatus($entityManager, $dispatch, $draftStatus, [
                        'date' => $createdAt,
                        "initiatedBy" => $requester,
                    ]);
                } else {
                    $errors[] = "Vous devez paramétrer un statut Brouillon et un à traiter pour ce type";
                }

                if($dispatchStatus && $draftStatus->getId() !== $dispatchStatus->getId()){
                    $toTreatStatus = $statusRepository->findStatusByType(CategorieStatut::DISPATCH, $dispatch->getType(), [Statut::NOT_TREATED])[0] ?? null;
                    $dispatch->setValidationDate($validationDate);
                    $statusHistoryService->updateStatus($entityManager, $dispatch, $toTreatStatus, [
                        'date' => $validationDate,
                        "initiatedBy" => $requester,
                    ]);
                }
            }
            else {
                if (!$dispatch) { // given dispatchId
                    $errors[] = "L'acheminement a été supprimé.";
                    $currentError = true;
                } else if($dispatch->getUpdatedAt() && $dispatch->getUpdatedAt() > $this->getFormatter()->parseDatetime($dispatchArray['updatedAt'], [DATE_ATOM])){
                    $errors[] = "L'acheminement {$dispatch->getNumber()} a été modifié à {$this->getFormatter()->datetime($dispatch->getUpdatedAt())}, modifications locales annulées.";
                    $currentError = true;
                } else {
                    $dispatchStatus = $dispatch->getStatut();
                    $localDispatchStatus = $statusRepository->find($dispatchArray['statusId']);

                    if ($dispatchStatus->isDraft()) {
                        $wasDraft = true;
                        if ($localDispatchStatus->getId() !== $dispatchStatus->getId()) {
                            $toTreatStatus = $statusRepository->findStatusByType(CategorieStatut::DISPATCH, $dispatch->getType(), [Statut::NOT_TREATED])[0] ?? null;
                            $dispatch->setValidationDate($validationDate);
                            $statusHistoryService->updateStatus($entityManager, $dispatch, $toTreatStatus, [
                                'date' => $validationDate,
                                "initiatedBy" => $createdBy,
                            ]);
                        }
                    }
                    $dispatch->setUpdatedAt($syncedAt);
                }
            }

            if (!$currentError) {
                $filteredDispatchReferences = Stream::from($dispatchReferences)
                    ->filter(fn(array $dispatchReference) => $wasDraft && $dispatchReference['localDispatchId'] === $dispatchArray['localId']);

                try {
                    $createdReferences = [];
                    foreach ($filteredDispatchReferences as $dispatchReference) {
                        $dispatchService->treatMobileDispatchReference($entityManager, $dispatch, $dispatchReference, $createdReferences, [
                            'loggedUser' => $dispatch->getCreatedBy(),
                            'now' => $syncedAt
                        ]);
                    }
                    $entityManager->flush();
                    $localIdsToInsertIds[$dispatchArray['localId']] = $dispatch->getId();
                } catch (Exception $error) {
                    $exceptionLoggerService->sendLog($error, $request);
                    $errors[] = $error instanceof FormException ? $error->getMessage() : "Une erreur est survenue sur le traitement d'un des acheminements";
                    [$dispatchRepository, $typeRepository, $statusRepository, $locationRepository, $userRepository, $entityManager] = $this->closeAndReopenEntityManager($entityManager);
                }
            }
        }

        // grouped signature
        $groupedSignatureHistory = Stream::from(json_decode($data->get('groupedSignatureHistory'), true))
            ->sort(fn($groupedSignaturePrev, $groupedSignatureNext) => $this->getFormatter()->parseDatetime($groupedSignaturePrev['signatureDate']) <=> $this->getFormatter()->parseDatetime($groupedSignatureNext['signatureDate']))
            ->keymap(fn($groupedSignature) => [$groupedSignature['localDispatchId'], $groupedSignature], true);

        foreach ($groupedSignatureHistory as $localDispatchId => $groupedSignatureByDispatch) {
            $dispatchId = $localIdsToInsertIds[$localDispatchId] ?? null;
            $dispatch = $dispatchId ? $dispatchRepository->find($dispatchId) : null;
            if ($dispatch) {
                foreach ($groupedSignatureByDispatch as $groupedSignature) {
                    $groupedSignatureStatus = $statusRepository->find($groupedSignature['statutTo']);
                    $date = $this->getFormatter()->parseDatetime($groupedSignature['signatureDate']);
                    $signatory = $userRepository->find($groupedSignature['signatory']);
                    try {
                        $dispatchService->signDispatch(
                            $dispatch,
                            $groupedSignatureStatus,
                            $signatory,
                            $dispatch->getCreatedBy() ?? $dispatch->getRequester() ?? $signatory,
                            $date,
                            $groupedSignature['comment'] ?? '',
                            true,
                            $entityManager
                        );
                        $entityManager->flush();
                    } catch (Exception $error) {
                        $exceptionLoggerService->sendLog($error, $request);
                        $errors[] = "Une erreur est survenue lors de la signature de l'acheminement {$dispatch->getNumber()}";
                        [$dispatchRepository, $typeRepository, $statusRepository, $locationRepository, $userRepository, $entityManager] = $this->closeAndReopenEntityManager($entityManager);
                    }
                }
            }
        }

        $dispatchWaybillData = json_decode($data->get('waybillData'), true);
        foreach($dispatchWaybillData as $waybill) {
            $localId = $waybill['localId'] ?? null;
            $dispatchId = $localIdsToInsertIds[$localId] ?? null;
            unset($waybill['localId']);
            if ($localId && in_array($localId, $createdDispatchLocalIds)) {
                $dispatch = $dispatchId ? $dispatchRepository->find($dispatchId) : null;
                if ($dispatch && !($dispatch->getDispatchPacks()->isEmpty())) {
                    try {
                        $dispatchService->generateWayBill($dispatch->getCreatedBy(), $dispatch, $entityManager, $waybill);
                        $entityManager->flush();
                    } catch (Exception $error) {
                        $exceptionLoggerService->sendLog($error, $request);
                        $errors[] = "La génération de la lettre de voiture n°{$dispatch->getNumber()} s'est mal déroulée";
                        [$dispatchRepository, $typeRepository, $statusRepository, $locationRepository, $userRepository, $entityManager] = $this->closeAndReopenEntityManager($entityManager);
                    }
                }
            }
        }

        return $this->json([
            'success' => empty($errors),
            'errors' => $errors,
        ]);
    }
    #[Route("/dispatches", methods: [self::POST], condition: self::IS_XML_HTTP_REQUEST)]
    #[Wii\RestVersionChecked]
    public function patchDispatches(Request                $request,
                                    AttachmentService      $attachmentService,
                                    DispatchService        $dispatchService,
                                    EntityManagerInterface $entityManager): JsonResponse
    {
        $nomadUser = $this->getUser();

        $resData = [];

        $dispatches = json_decode($request->request->get('dispatches'), true);
        $dispatchPacksParam = json_decode($request->request->get('dispatchPacks'), true);

        $dispatchRepository = $entityManager->getRepository(Dispatch::class);
        $statusRepository = $entityManager->getRepository(Statut::class);
        $dispatchPackRepository = $entityManager->getRepository(DispatchPack::class);
        $natureRepository = $entityManager->getRepository(Nature::class);

        $entireTreatedDispatch = [];

        $dispatchPacksByDispatch = is_array($dispatchPacksParam)
            ? array_reduce($dispatchPacksParam, function (array $acc, array $current) {
                $id = (int)$current['id'];
                $natureId = $current['natureId'];
                $quantity = $current['quantity'];
                $dispatchId = (int)$current['dispatchId'];
                $photo1 = $current['photo1'] ?? null;
                $photo2 = $current['photo2'] ?? null;
                if (!isset($acc[$dispatchId])) {
                    $acc[$dispatchId] = [];
                }
                $acc[$dispatchId][] = [
                    'id' => $id,
                    'natureId' => $natureId,
                    'quantity' => $quantity,
                    'photo1' => $photo1,
                    'photo2' => $photo2,
                ];
                return $acc;
            }, [])
            : [];

        foreach ($dispatches as $dispatchArray) {
            /** @var Dispatch $dispatch */
            $dispatch = $dispatchRepository->find($dispatchArray['id']);
            $dispatchStatus = $dispatch->getStatut();
            if (!$dispatchStatus || !$dispatchStatus->isTreated()) {
                $treatedStatus = $statusRepository->find($dispatchArray['treatedStatusId']);
                if ($treatedStatus
                    && ($treatedStatus->isTreated() || $treatedStatus->isPartial())) {
                    $treatedPacks = [];
                    // we treat pack edits
                    if (!empty($dispatchPacksByDispatch[$dispatch->getId()])) {
                        foreach ($dispatchPacksByDispatch[$dispatch->getId()] as $packArray) {
                            $treatedPacks[] = $packArray['id'];
                            $packDispatch = $dispatchPackRepository->find($packArray['id']);
                            $pack = $packDispatch->getPack();
                            if (!empty($packDispatch)) {
                                if (!empty($packArray['natureId'])) {
                                    $nature = $natureRepository->find($packArray['natureId']);
                                    if ($nature) {
                                        $pack->setNature($nature);
                                    }
                                }

                                $quantity = (int)$packArray['quantity'];
                                if ($quantity > 0) {
                                    $packDispatch->setQuantity($quantity);
                                }

                                $code = $pack->getCode();
                                foreach (['photo1', 'photo2'] as $photoName) {
                                    $photoFile = $request->files->get("{$code}_{$photoName}");
                                    if ($photoFile) {
                                        $attachmentService->persistAttachment($entityManager, $photoFile, ["attachmentContainer" => $dispatch]);
                                    }
                                }
                            }
                        }
                    }

                    $dispatchService->treatDispatchRequest($entityManager, $dispatch, $treatedStatus, $nomadUser, true, $treatedPacks, $dispatch->getCommentaire());

                    if (!$treatedStatus->isPartial()) {
                        $entireTreatedDispatch[] = $dispatch->getId();
                    }
                }
            }
        }
        $statusCode = Response::HTTP_OK;
        $resData['success'] = true;
        $resData['entireTreatedDispatch'] = $entireTreatedDispatch;

        return new JsonResponse($resData, $statusCode);
    }
    #[Route("/dispatch-emergencies", methods: [self::GET], condition: self::IS_XML_HTTP_REQUEST)]
    #[Wii\RestVersionChecked]
    public function dispatchEmergencies(EntityManagerInterface $manager): Response
    {
        $elements = $manager->getRepository(FixedFieldStandard::class)->getElements(FixedFieldStandard::ENTITY_CODE_DISPATCH, FixedFieldStandard::FIELD_CODE_EMERGENCY);
        $emergencies = Stream::from($elements)
            ->map(fn(string $element) => [
                'id' => $element,
                'label' => $element,
            ])->toArray();

        return $this->json($emergencies);
    }

    #[Route("/waybill/{dispatch}", methods: [self::POST], condition: self::IS_XML_HTTP_REQUEST)]
    #[Wii\RestVersionChecked]
    public function dispatchWaybill(EntityManagerInterface $manager, Dispatch $dispatch, Request $request, DispatchService $dispatchService): Response
    {
        /** @var Utilisateur $loggedUser */
        $loggedUser = $this->getUser();

        $data = $request->request->all();
        $wayBillAttachment = $dispatchService->generateWayBill($loggedUser, $dispatch, $manager, $data);
        $manager->flush();
        $file = '/uploads/attachments/' . $wayBillAttachment->getFileName();

        return $this->json([
            'filePath' => $file,
            'fileName' => $wayBillAttachment->getOriginalName(),
        ]);
    }

    #[Route("/new-dispatch", methods: [self::POST], condition: self::IS_XML_HTTP_REQUEST)]
    #[Wii\RestVersionChecked]
    public function newDispatch(Request                $request,
                                EntityManagerInterface $manager,
                                UniqueNumberService    $uniqueNumberService,
                                DispatchService        $dispatchService,
                                MobileApiService       $mobileApiService,
                                SettingsService        $settingsService,
                                StatusHistoryService   $statusHistoryService): JsonResponse
    {

        $typeRepository = $manager->getRepository(Type::class);
        $statusRepository = $manager->getRepository(Statut::class);
        $locationRepository = $manager->getRepository(Emplacement::class);
        $userRepository = $manager->getRepository(Utilisateur::class);
        $dispatchRepository = $manager->getRepository(Dispatch::class);

        $numberFormat = $settingsService->getValue($manager,Setting::DISPATCH_NUMBER_FORMAT);
        if (!in_array($numberFormat, Dispatch::NUMBER_FORMATS)) {
            throw new FormException("Le format de numéro d'acheminement n'est pas valide");
        }
        $dispatchNumber = $uniqueNumberService->create($manager, Dispatch::NUMBER_PREFIX, Dispatch::class, $numberFormat);
        $type = $typeRepository->find($request->request->get('type'));
        $draftStatuses = $statusRepository->findStatusByType(CategorieStatut::DISPATCH, $type, [Statut::DRAFT]);
        $pickLocation = $request->request->get('pickLocation') ? $locationRepository->find($request->request->get('pickLocation')) : null;
        $dropLocation = $request->request->get('dropLocation') ? $locationRepository->find($request->request->get('dropLocation')) : null;
        $receiver = $request->request->get('receiver') ? $userRepository->find($request->request->get('receiver')) : null;
        $emails = $request->request->get('emails') ? explode(",", $request->request->get('emails')) : null;

        if (empty($draftStatuses)) {
            return $this->json([
                'success' => false,
                'msg' => "Il n'y a aucun statut brouillon paramétré pour ce type."
            ]);
        }

        $currentUser = $this->getUser();
        $now = new DateTime();
        $emergency = $request->request->get('emergency');
        $dispatch = (new Dispatch())
            ->setNumber($dispatchNumber)
            ->setCreationDate($now)
            ->setRequester($currentUser)
            ->setType($type)
            ->setStatus($draftStatuses[0])
            ->setLocationFrom($pickLocation)
            ->setLocationTo($dropLocation)
            ->setCarrierTrackingNumber($request->request->get('carrierTrackingNumber'))
            ->setCommentaire($request->request->get('comment'))
            ->setEmergency(!empty($emergency) ? $emergency : null)
            ->setCreatedBy($this->getUser())
            ->setUpdatedAt($now)
            ->setEmails($emails);

        if ($receiver) {
            $dispatch->addReceiver($receiver);
        }

        $manager->persist($dispatch);

        $statusHistoryService->updateStatus($manager, $dispatch, $draftStatuses[0], [
            'setStatus' => false,
            "initiatedBy" => $currentUser,
        ]);

        $manager->flush();

        if ($request->request->get('emergency') && $receiver) {
            $dispatchService->sendEmailsAccordingToStatus($manager, $dispatch, false, false, $receiver, true);
        }

        $serializedDispatches = $dispatchRepository->getMobileDispatches(null, $dispatch);
        $serializedDispatches = Stream::from($serializedDispatches)
            ->reduce(fn(array $accumulator, array $serializedDispatch) => $mobileApiService->serializeDispatch($accumulator, $serializedDispatch), []);

        $serializedDispatches = !empty($serializedDispatches)
            ? $serializedDispatches[array_key_first($serializedDispatches)]
            : null;
        return $this->json([
            'success' => true,
            "msg" => "L'acheminement a été créé avec succès.",
            "dispatch" => $serializedDispatches
        ]);
    }


    #[Route("/dispatch-validate", methods: [self::POST], condition: self::IS_XML_HTTP_REQUEST)]
    #[Wii\RestVersionChecked]
    public function dispatchValidate(Request                $request,
                                     EntityManagerInterface $entityManager,
                                     DispatchService        $dispatchService,
                                     StatusHistoryService   $statusHistoryService): Response
    {
        $statusRepository = $entityManager->getRepository(Statut::class);

        $references = json_decode($request->request->get('references'), true);
        $user = $this->getUser();
        $now = new DateTime();

        $dispatch = $entityManager->find(Dispatch::class, $request->request->get('dispatch'));
        $toTreatStatus = $statusRepository->findStatusByType(CategorieStatut::DISPATCH, $dispatch->getType(), [Statut::NOT_TREATED])[0] ?? null;

        if (!$toTreatStatus) {
            throw new FormException("Il n'y a aucun statut à traiter paramétré pour ce type.");
        }

        if(!$dispatch->getType()->hasReusableStatuses() && $dispatchService->statusIsAlreadyUsedInDispatch($dispatch, $toTreatStatus)){
            throw new FormException("Ce statut a déjà été utilisé pour cette demande.");
        }

        $createdReferences = [];
        foreach ($references as $data) {
            $dispatchService->treatMobileDispatchReference($entityManager, $dispatch, $data, $createdReferences, [
                'loggedUser' => $user,
                'now' => $now
            ]);
        }
        $dispatch
            ->setValidationDate(new DateTime('now'));
        $statusHistoryService->updateStatus($entityManager, $dispatch, $toTreatStatus, [
            "initiatedBy" => $user,
        ]);

        $entityManager->flush();

        return $this->json([
            'success' => true
        ]);
    }

    #[Route("/get-waybill-data/{dispatch}", methods: [self::GET], condition: self::IS_XML_HTTP_REQUEST)]
    #[Wii\RestVersionChecked]
    public function getWayBillData(EntityManagerInterface $manager, Dispatch $dispatch, DispatchService $dispatchService): Response
    {
        return $this->json([
            'success' => true,
            'data' => $dispatchService->getWayBillDataForUser($this->getUser(), $manager, $dispatch)
        ]);
    }

    private function closeAndReopenEntityManager(EntityManagerInterface $entityManager){
        $entityManager->close();
        $entityManager = new EntityManager($entityManager->getConnection(), $entityManager->getConfiguration());
        $dispatchRepository = $entityManager->getRepository(Dispatch::class);
        $typeRepository = $entityManager->getRepository(Type::class);
        $statusRepository = $entityManager->getRepository(Statut::class);
        $locationRepository = $entityManager->getRepository(Emplacement::class);
        $userRepository = $entityManager->getRepository(Utilisateur::class);

        return [
            $dispatchRepository,
            $typeRepository,
            $statusRepository,
            $locationRepository,
            $userRepository,
            $entityManager,
        ];
    }


    #[Route("/finish-grouped-signature", methods: [self::POST], condition: self::IS_XML_HTTP_REQUEST)]
    #[Wii\RestVersionChecked]
    public function finishGroupedSignature(Request                $request,
                                           EntityManagerInterface $manager,
                                           DispatchService        $dispatchService): Response
    {

        $locationData = [
            'from' => $request->request->get('from') === "null" ? null : $request->request->get('from'),
            'to' => $request->request->get('to') === "null" ? null : $request->request->get('to'),
        ];
        $signatoryTrigramData = $request->request->get("signatoryTrigram");
        $signatoryPasswordData = $request->request->get("signatoryPassword");
        $statusData = $request->request->get("status");
        $commentData = $request->request->get("comment");
        $dispatchesToSignIds = explode(',', $request->request->get('dispatchesToSign'));

        $response = $dispatchService->finishGroupedSignature(
            $manager,
            $locationData,
            $signatoryTrigramData,
            $signatoryPasswordData,
            $statusData,
            $commentData,
            $dispatchesToSignIds,
            true,
            $this->getUser()
        );

        $manager->flush();

        return $this->json($response);
    }
    #[Route("/get-associated-ref-intels/{packCode}/{dispatch}", methods: [self::GET], condition: self::IS_XML_HTTP_REQUEST)]
    #[Wii\RestVersionChecked]
    public function getAssociatedPackIntels(EntityManagerInterface $manager,
                                            string $packCode,
                                            Dispatch $dispatch,
                                            KernelInterface $kernel): Response
    {

        $pack = $manager->getRepository(Pack::class)->findOneBy(['code' => $packCode]);

        $data = [];
        /** @var DispatchPack $line */
        $line = $dispatch
            ->getDispatchPacks()
            ->filter(fn(DispatchPack $linePack) => $linePack->getPack()->getId() === $pack->getId())
            ->first();

        if ($line) {
            /** @var DispatchReferenceArticle $ref */
            $ref = $line
                ->getDispatchReferenceArticles()
                ->first();
            if ($ref) {
                $photos = Stream::from($ref->getAttachments())
                    ->map(function (Attachment $attachment) use ($kernel) {
                        $path = $kernel->getProjectDir() . '/public/uploads/attachments/' . $attachment->getFileName();
                        $type = pathinfo($path, PATHINFO_EXTENSION);
                        $data = file_get_contents($path);

                        return 'data:image/' . $type . ';base64,' . base64_encode($data);
                    })->toArray();

                $data = [
                    'reference' => $ref->getReferenceArticle()->getReference(),
                    'quantity' => $ref->getQuantity(),
                    'outFormatEquipment' => $ref->getReferenceArticle()->getDescription()['outFormatEquipment'] ?? null,
                    'manufacturerCode' => $ref->getReferenceArticle()->getDescription()['manufacturerCode'] ?? null,
                    'sealingNumber' => $ref->getSealingNumber(),
                    'serialNumber' => $ref->getSerialNumber(),
                    'batchNumber' => $ref->getBatchNumber(),
                    'width' => $ref->getReferenceArticle()->getDescription()['width'] ?? null,
                    'height' => $ref->getReferenceArticle()->getDescription()['height'] ?? null,
                    'length' => $ref->getReferenceArticle()->getDescription()['length'] ?? null,
                    'weight' => $ref->getReferenceArticle()->getDescription()['weight'] ?? null,
                    'volume' => $ref->getReferenceArticle()->getDescription()['volume'] ?? null,
                    'adr' => $ref->isADR() ? 'Oui' : 'Non',
                    'associatedDocumentTypes' => null,
                    'comment' => $ref->getCleanedComment() ?: $ref->getComment(),
                    'photos' => json_encode($photos)
                ];
            }
        }

        return $this->json($data);
    }
    #[Route("/get-reference", methods: [self::GET], condition: self::IS_XML_HTTP_REQUEST)]
    #[Wii\RestVersionChecked]
    public function getReference(Request $request, EntityManagerInterface $manager): Response
    {
        $referenceArticleRepository = $manager->getRepository(ReferenceArticle::class);

        $text = $request->query->get("reference");
        $reference = $referenceArticleRepository->findOneBy(['reference' => $request->query->get("reference")]);
        if ($reference) {
            $description = $reference->getDescription();
            $serializedReference = [
                'reference' => $reference->getReference(),
                'outFormatEquipment' => $description['outFormatEquipment'] ?? '',
                'manufacturerCode' => $description['manufacturerCode'] ?? '',
                'width' => $description['width'] ?? '',
                'height' => $description['height'] ?? '',
                'length' => $description['length'] ?? '',
                'volume' => $description['volume'] ?? '',
                'weight' => $description['weight'] ?? '',
                'associatedDocumentTypes' => '',
                'exists' => true,
            ];
        } else {
            $serializedReference = [
                'reference' => $text,
                'exists' => false,
            ];
        }

        return $this->json([
            "success" => true,
            "reference" => $serializedReference
        ]);
    }

    #[Route("/get-associated-document-type-elements", methods: [self::GET], condition: self::IS_XML_HTTP_REQUEST)]
    #[Wii\RestVersionChecked]
    public function getAssociatedDocumentTypeElements(EntityManagerInterface $entityManager,
                                                      SettingsService        $settingsService): Response
    {
        $associatedDocumentTypeElements = $settingsService->getValue($entityManager, Setting::REFERENCE_ARTICLE_ASSOCIATED_DOCUMENT_TYPE_VALUES);

        return $this->json($associatedDocumentTypeElements);
    }
}
