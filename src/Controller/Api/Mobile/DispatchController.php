<?php

namespace App\Controller\Api\Mobile;

use App\Annotation as Wii;
use App\Controller\Api\AbstractApiController;
use App\Entity\CategorieStatut;
use App\Entity\Dispatch;
use App\Entity\DispatchPack;
use App\Entity\Emplacement;
use App\Entity\Fields\FixedFieldStandard;
use App\Entity\Nature;
use App\Entity\Setting;
use App\Entity\Statut;
use App\Entity\Type;
use App\Entity\Utilisateur;
use App\Exceptions\FormException;
use App\Service\AttachmentService;
use App\Service\DispatchService;
use App\Service\ExceptionLoggerService;
use App\Service\MobileApiService;
use App\Service\StatusHistoryService;
use App\Service\UniqueNumberService;
use DateTime;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use FOS\RestBundle\Controller\Annotations as Rest;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use WiiCommon\Helper\Stream;

#[Rest\Route("/api")]
class DispatchController extends AbstractApiController
{

    #[Rest\Post("/new-offline-dispatches", condition: "request.isXmlHttpRequest()")]
    #[Wii\RestAuthenticated]
    #[Wii\RestVersionChecked]
    public function createNewOfflineDispatchs(Request                $request,
                                              EntityManagerInterface $entityManager,
                                              DispatchService        $dispatchService,
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
        $settingRepository = $entityManager->getRepository(Setting::class);
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
                $wasDraft = true;

                $numberFormat = $settingRepository->getOneParamByLabel(Setting::DISPATCH_NUMBER_FORMAT);
                if(!in_array($numberFormat, Dispatch::NUMBER_FORMATS)) {
                    throw new FormException("Le format de numéro d'acheminement n'est pas valide");
                }
                $dispatchNumber = $uniqueNumberService->create($entityManager, Dispatch::NUMBER_PREFIX, Dispatch::class, $numberFormat, $createdAt);

                $dispatch = (new Dispatch())
                    ->setNumber($dispatchNumber)
                    ->setCreationDate($createdAt)
                    ->setRequester($requester ?? $createdBy)
                    ->setType($type)
                    ->setLocationFrom($locationFrom)
                    ->setLocationTo($locationTo)
                    ->setCarrierTrackingNumber($dispatchArray['carrierTrackingNumber'])
                    ->setCommentaire($dispatchArray['comment'])
                    ->setEmergency($dispatchArray['emergency'] ?? null)
                    ->setCreatedBy($createdBy)
                    ->setUpdatedAt($syncedAt);
                $entityManager->persist($dispatch);

                if($draftStatus){
                    $statusHistoryService->updateStatus($entityManager, $dispatch, $draftStatus, [
                        'date' => $createdAt,
                    ]);
                } else {
                    $errors[] = "Vous devez paramétrer un statut Brouillon et un à traiter pour ce type";
                }

                if($dispatchStatus && $draftStatus->getId() !== $dispatchStatus->getId()){
                    $toTreatStatus = $statusRepository->findStatusByType(CategorieStatut::DISPATCH, $dispatch->getType(), [Statut::NOT_TREATED])[0] ?? null;
                    $dispatch->setValidationDate($validationDate);
                    $statusHistoryService->updateStatus($entityManager, $dispatch, $toTreatStatus, [
                        'date' => $validationDate,
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
    #[Rest\Post("/dispatches", condition: "request.isXmlHttpRequest()")]
    #[Wii\RestAuthenticated]
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
                                        $fileName = $attachmentService->saveFile($photoFile);
                                        $attachments = $attachmentService->createAttachments($fileName);
                                        foreach ($attachments as $attachment) {
                                            $entityManager->persist($attachment);
                                            $dispatch->addAttachment($attachment);
                                        }
                                    }
                                }
                            }
                        }
                    }

                    $dispatchService->treatDispatchRequest($entityManager, $dispatch, $treatedStatus, $nomadUser, true, $treatedPacks);

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
    #[Rest\Get("/dispatch-emergencies", condition: "request.isXmlHttpRequest()")]
    #[Wii\RestAuthenticated]
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

    #[Rest\Post("/waybill/{dispatch}", condition: "request.isXmlHttpRequest()")]
    #[Wii\RestAuthenticated]
    #[Wii\RestVersionChecked]
    public function dispatchWaybill(EntityManagerInterface $manager, Dispatch $dispatch, Request $request, DispatchService $dispatchService, KernelInterface $kernel): Response
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

    #[Rest\Post("/new-dispatch", condition: "request.isXmlHttpRequest()")]
    #[Wii\RestAuthenticated]
    #[Wii\RestVersionChecked]
    public function newDispatch(Request                $request,
                                EntityManagerInterface $manager,
                                UniqueNumberService    $uniqueNumberService,
                                DispatchService        $dispatchService,
                                MobileApiService       $mobileApiService,
                                StatusHistoryService   $statusHistoryService): Response
    {

        $typeRepository = $manager->getRepository(Type::class);
        $statusRepository = $manager->getRepository(Statut::class);
        $locationRepository = $manager->getRepository(Emplacement::class);
        $userRepository = $manager->getRepository(Utilisateur::class);
        $dispatchRepository = $manager->getRepository(Dispatch::class);
        $settingRepository = $manager->getRepository(Setting::class);

        $numberFormat = $settingRepository->getOneParamByLabel(Setting::DISPATCH_NUMBER_FORMAT);
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

        $now = new DateTime();
        $emergency = $request->request->get('emergency');
        $dispatch = (new Dispatch())
            ->setNumber($dispatchNumber)
            ->setCreationDate($now)
            ->setRequester($this->getUser())
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


    #[Rest\Post("/dispatch-validate", condition: "request.isXmlHttpRequest()")]
    #[Wii\RestAuthenticated]
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

        $createdReferences = [];
        foreach ($references as $data) {
            $dispatchService->treatMobileDispatchReference($entityManager, $dispatch, $data, $createdReferences, [
                'loggedUser' => $user,
                'now' => $now
            ]);
        }
        $dispatch
            ->setValidationDate(new DateTime('now'));
        $statusHistoryService->updateStatus($entityManager, $dispatch, $toTreatStatus);

        $entityManager->flush();

        return $this->json([
            'success' => true
        ]);
    }

    #[Rest\Get("/get-waybill-data/{dispatch}", condition: "request.isXmlHttpRequest()")]
    #[Wii\RestAuthenticated]
    #[Wii\RestVersionChecked]
    public function getWayBillData(EntityManagerInterface $manager, Dispatch $dispatch, DispatchService $dispatchService): Response
    {
        return $this->json([
            'success' => true,
            'data' => $dispatchService->getWayBillDataForUser($this->getUser(), $manager, $dispatch)
        ]);
    }

    public function closeAndReopenEntityManager(EntityManagerInterface $entityManager){
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
}
