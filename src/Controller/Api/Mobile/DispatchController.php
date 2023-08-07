<?php

namespace App\Controller\Api\Mobile;

use App\Annotation as Wii;
use App\Controller\Api\AbstractApiController;
use App\Entity\CategorieStatut;
use App\Entity\Dispatch;
use App\Entity\Emplacement;
use App\Entity\Setting;
use App\Entity\Statut;
use App\Entity\Type;
use App\Entity\Utilisateur;
use App\Exceptions\FormException;
use App\Service\DispatchService;
use App\Service\ExceptionLoggerService;
use App\Service\PackService;
use App\Service\RefArticleDataService;
use App\Service\StatusHistoryService;
use App\Service\UniqueNumberService;
use DateTime;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use FOS\RestBundle\Controller\Annotations as Rest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use WiiCommon\Helper\Stream;

class DispatchController extends AbstractApiController
{
    /**
     * @Rest\Post("/api/new-offline-dispatches", name="api_new_offline_dispatches", methods="POST", condition="request.isXmlHttpRequest()")
     * @Wii\RestAuthenticated()
     * @Wii\RestVersionChecked()
     */
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

            $createdBy = $userRepository->findOneBy(['username' => $dispatchArray['createdBy']]);
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
                $locationFrom = $locationRepository->find($dispatchArray['locationFromId']);
                $locationTo = $locationRepository->find($dispatchArray['locationToId']);
                $requester = $userRepository->findOneBy(['username' => $dispatchArray['requester']]);
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

    public function closeAndReopenEntityManager(EntityManagerInterface $entityManager){
        $entityManager->close();
        $entityManager = EntityManager::Create($entityManager->getConnection(), $entityManager->getConfiguration());
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
