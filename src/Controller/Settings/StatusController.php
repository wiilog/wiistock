<?php

namespace App\Controller\Settings;

use App\Annotation\HasPermission;
use App\Controller\AbstractController;
use App\Entity\Action;
use App\Entity\CategorieStatut;
use App\Entity\CategoryType;
use App\Entity\Menu;
use App\Entity\ProductionRequest;
use App\Entity\ShippingRequest\ShippingRequest;
use App\Entity\StatusHistory;
use App\Entity\Statut;
use App\Entity\Type;
use App\Entity\Utilisateur;
use App\Service\DispatchService;
use App\Service\FormService;
use App\Service\StatusService;
use App\Service\TranslationService;
use App\Service\UserService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpClient\Exception\InvalidArgumentException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use WiiCommon\Helper\Stream;


#[Route('/parametrage')]
class StatusController extends AbstractController
{
    const MODE_ARRIVAL_DISPUTE = 'arrival-dispute';
    const MODE_RECEPTION_DISPUTE = 'reception-dispute';
    const MODE_PURCHASE_REQUEST = 'purchase-request';
    const MODE_ARRIVAL = 'arrival';
    const MODE_DISPATCH = 'dispatch';
    const MODE_HANDLING = 'handling';
    const MODE_PRODUCTION = 'production';

    const MODES = [
        self::MODE_ARRIVAL_DISPUTE,
        self::MODE_RECEPTION_DISPUTE,
        self::MODE_PURCHASE_REQUEST,
        self::MODE_ARRIVAL,
        self::MODE_DISPATCH,
        self::MODE_HANDLING,
        self::MODE_PRODUCTION,
    ];

    #[Route("/statuses-api", name: "settings_statuses_api", options: ["expose" => true])]
    public function statusesApi(Request                $request,
                                UserService            $userService,
                                StatusService          $statusService,
                                DispatchService        $dispatchService,
                                EntityManagerInterface $entityManager,
                                FormService            $formService): JsonResponse {
        $edit = $request->query->getBoolean("edit");
        $mode = $request->query->get("mode");
        $typeId = $request->query->get("type");

        if (!in_array($mode, $this::MODES)) {
            throw new InvalidArgumentException('Invalid mode');
        }

        $hasAccess = match($mode) {
            self::MODE_ARRIVAL_DISPUTE, self::MODE_ARRIVAL => $userService->hasRightFunction(Menu::PARAM, Action::SETTINGS_DISPLAY_ARRI),
            self::MODE_DISPATCH => $userService->hasRightFunction(Menu::PARAM, Action::SETTINGS_DISPLAY_TRACING_DISPATCH),
            self::MODE_HANDLING => $userService->hasRightFunction(Menu::PARAM, Action::SETTINGS_DISPLAY_TRACING_HAND),
            self::MODE_RECEPTION_DISPUTE => $userService->hasRightFunction(Menu::PARAM, Action::SETTINGS_DISPLAY_RECEP),
            self::MODE_PURCHASE_REQUEST => $userService->hasRightFunction(Menu::PARAM, Action::SETTINGS_DISPLAY_REQUESTS),
            self::MODE_PRODUCTION => $userService->hasRightFunction(Menu::PARAM, Action::SETTINGS_DISPLAY_PRODUCTION),
        };

        if (!$hasAccess) {
            throw new BadRequestHttpException();
        }

        $canDelete = $userService->hasRightFunction(Menu::PARAM, Action::DELETE);

        $data = [];

        $statusRepository = $entityManager->getRepository(Statut::class);
        $typeRepository = $entityManager->getRepository(Type::class);

        $category = match ($mode) {
            self::MODE_ARRIVAL_DISPUTE => CategorieStatut::DISPUTE_ARR,
            self::MODE_RECEPTION_DISPUTE => CategorieStatut::LITIGE_RECEPT,
            self::MODE_PURCHASE_REQUEST => CategorieStatut::PURCHASE_REQUEST,
            self::MODE_ARRIVAL => CategorieStatut::ARRIVAGE,
            self::MODE_DISPATCH => CategorieStatut::DISPATCH,
            self::MODE_HANDLING => CategorieStatut::HANDLING,
            self::MODE_PRODUCTION => CategorieStatut::PRODUCTION,
        };

        $type = $typeId ? $typeRepository->find($typeId) : null;
        $statuses = $statusRepository->findStatusByType($category, $type);

        foreach ($statuses as $status) {
            $actionColumn = $canDelete
                ? "<button class='btn btn-silent delete-row' data-id='{$status->getId()}'>
                       <i class='wii-icon wii-icon-trash text-primary'></i>
                   </button>
                   <input type='hidden' name='statusId' class='data' value='{$status->getId()}'/>
                   <input type='hidden' name='mode' class='data' value='{$mode}'/>"
                : "";

            $groupedSignatureColor = $status->getGroupedSignatureColor() ?? Statut::GROUPED_SIGNATURE_DEFAULT_COLOR;
            $color = $status->getColor();
            if ($edit) {
                $stateOptions = $statusService->getStatusStatesOptions($mode, $status->getState(), true);
                $groupedSignatureTypes = $dispatchService->getGroupedSignatureTypes($status->getGroupedSignatureType());

                $disabledMobileSyncAndColor = in_array($status->getState(), [Statut::DRAFT, Statut::TREATED]) ? 'disabled' : '';

                $defaultStatut = $status->isDefaultForCategory() ? 'checked' : "";
                $sendMailBuyers = $status->getSendNotifToBuyer() ? 'checked' : "";
                $sendMailRequesters = $status->getSendNotifToDeclarant() ? 'checked' : "";
                $sendMailDest = $status->getSendNotifToRecipient() ? 'checked' : "";
                $sendReport = $status->getSendReport() ? 'checked' : "";
                $overconsumptionBillGenerationStatus = $status->getOverconsumptionBillGenerationStatus() ? 'checked' : "";
                $needsMobileSync = (!in_array($status->getState(), [Statut::DRAFT, Statut::TREATED]) && $status->getNeedsMobileSync()) ? 'checked' : "";
                $commentNeeded = $status->getCommentNeeded() ? 'checked' : "";
                $automaticReceptionCreation = $status->getAutomaticReceptionCreation() ? 'checked' : "";
                $displayOnSchedule = $status->isDisplayedOnSchedule() ? "checked" : "";
                $createDropMovementOnDropLocation = $status->isCreateDropMovementOnDropLocation() ? "checked" : "";
                $requiredAttachment = $status->isRequiredAttachment() ? "checked" : "";
                $preventStatusChangeWithoutDeliveryFees = $status->isPreventStatusChangeWithoutDeliveryFees() ? "checked" : "";
                $passStatusAtPurchaseOrderGeneration = $status->isPassStatusAtPurchaseOrderGeneration() ? "checked" : "";
                $notifiedUsers = !$status->getnotifiedUsers()->isEmpty()
                    ? Stream::from($status->getNotifiedUsers())
                        ->map(static fn(Utilisateur $user) => [
                            "label" => $user->getUsername(),
                            "value" => $user->getId(),
                            "selected" => true,
                        ])
                        ->toArray()
                    : [];
                $types = $typeRepository->findByCategoryLabels([CategoryType::DEMANDE_DISPATCH]);
                $typeForGeneratedDispatchOnStatusChange = $status->getTypeForGeneratedDispatchOnStatusChange() ?
                    Stream::from($types)
                        ->map(fn(Type $type) => [
                            "label" => $type->getLabel(),
                            "value" => $type->getId(),
                            "selected" => $type->getId() === $status->getTypeForGeneratedDispatchOnStatusChange()?->getId(),
                        ])
                        ->toArray()
                    : [];

                $statusLabel = $this->getFormatter()->status($status);
                $data[] = [
                    "actions" => $actionColumn,
                    "label" => "<input type='text' name='label' value='$statusLabel' class='form-control data needed select-size'/>",
                    "state" => "<select name='state' class='data form-control needed select-size'>{$stateOptions}</select>",
                    "comment" => "<input type='text' name='comment' value='{$status->getComment()}' class='form-control data'/>",
                    "type" => $this->formatService->type($status->getType()),
                    "defaultStatut" => "<div class='checkbox-container'><input type='checkbox' name='defaultStatut' class='form-control data' {$defaultStatut}/></div>",
                    "sendMailBuyers" => "<div class='checkbox-container'><input type='checkbox' name='sendMailBuyers' class='form-control data' {$sendMailBuyers}/></div>",
                    "sendMailRequesters" => "<div class='checkbox-container'><input type='checkbox' name='sendMailRequesters' class='form-control data' {$sendMailRequesters}/></div>",
                    "sendMailDest" => "<div class='checkbox-container'><input type='checkbox' name='sendMailDest' class='form-control data' {$sendMailDest}/></div>",
                    "sendReport" => "<div class='checkbox-container'><input type='checkbox' name='sendReport' class='form-control data' {$sendReport}/></div>",
                    "overconsumptionBillGenerationStatus" => "<div class='checkbox-container'><input type='checkbox' name='overconsumptionBillGenerationStatus' class='form-control data' {$overconsumptionBillGenerationStatus}/></div>",
                    "groupedSignatureType" => "<select name='groupedSignatureType' class='data form-control select-size'>{$groupedSignatureTypes}</select>",
                    "groupedSignatureColor" => $formService->macro("color", "color", null, false, $groupedSignatureColor, [
                            "disabled" => $disabledMobileSyncAndColor
                        ]),
                    "color" => $formService->macro("color", "color", null, false, $color),
                    "needsMobileSync" => "<div class='checkbox-container'><input type='checkbox' name='needsMobileSync' class='form-control data' {$needsMobileSync}/></div>",
                    "commentNeeded" => "<div class='checkbox-container'><input type='checkbox' name='commentNeeded' class='form-control data' {$commentNeeded}/></div>",
                    "automaticReceptionCreation" => "<div class='checkbox-container'><input type='checkbox' name='automaticReceptionCreation' class='form-control data' {$automaticReceptionCreation}/></div>",
                    "displayedOnSchedule" => "<div class='checkbox-container'><input type='checkbox' name='displayedOnSchedule' class='form-control data $displayOnSchedule' $displayOnSchedule/></div>",
                    "createDropMovementOnDropLocation" => "<div class='checkbox-container'><input type='checkbox' name='createDropMovementOnDropLocation' class='form-control data $createDropMovementOnDropLocation' $createDropMovementOnDropLocation/></div>",
                    "notifiedUsers" => $formService->macro("select", "notifiedUsers", null, false, [
                        "type" => "user",
                        "multiple" => true,
                        "items" => $notifiedUsers,
                    ]),
                    "typeForGeneratedDispatchOnStatusChange" => $formService->macro("select", "typeForGeneratedDispatchOnStatusChange", null, false, [
                        "type" => "dispatchType",
                        "multiple" => false,
                        "items" => $typeForGeneratedDispatchOnStatusChange,
                    ]),
                    "requiredAttachment" => "<div class='checkbox-container'><input type='checkbox' name='requiredAttachment' class='form-control data $requiredAttachment' $requiredAttachment/></div>",
                    "preventStatusChangeWithoutDeliveryFees" => "<div class='checkbox-container'><input type='checkbox' name='preventStatusChangeWithoutDeliveryFees' class='form-control data $preventStatusChangeWithoutDeliveryFees' $preventStatusChangeWithoutDeliveryFees/></div>",
                    "passStatusAtPurchaseOrderGeneration" => $formService->macro("checkbox", "passStatusAtPurchaseOrderGeneration", null, false, $passStatusAtPurchaseOrderGeneration),
                    "order" => "<input type='number' name='order' min='1' value='{$status->getDisplayOrder()}' class='form-control data needed px-2 text-center' data-no-arrow/>",
                ];
            } else {
                $data[] = [
                    "actions" => $actionColumn,
                    "label" => $this->formatService->status($status),
                    "type" => $this->formatService->type($status->getType()),
                    "state" => $statusService->getStatusStateLabel($status->getState()),
                    "comment" => $status->getComment(),
                    "defaultStatut" => $this->formatService->bool($status->isDefaultForCategory()),
                    "sendMailBuyers" => $this->formatService->bool($status->getSendNotifToBuyer()),
                    "sendMailRequesters" => $this->formatService->bool($status->getSendNotifToDeclarant()),
                    "sendMailDest" => $this->formatService->bool($status->getSendNotifToRecipient()),
                    "sendReport" => $this->formatService->bool($status->getSendReport()),
                    "overconsumptionBillGenerationStatus" => $this->formatService->bool($status->getOverconsumptionBillGenerationStatus()),
                    "groupedSignatureType" => $status->getGroupedSignatureType(),
                    "groupedSignatureColor" => "<div class='dt-type-color' style='background: {$groupedSignatureColor}'></div>",
                    "color" => "<div class='dt-type-color' style='background: {$color}'></div>",
                    "needsMobileSync" => $this->formatService->bool(!in_array($status->getState(), [Statut::DRAFT, Statut::TREATED]) && $status->getNeedsMobileSync()),
                    "commentNeeded" => $this->formatService->bool($status->getCommentNeeded()),
                    "automaticReceptionCreation" => $this->formatService->bool($status->getAutomaticReceptionCreation()),
                    "displayedOnSchedule" => $this->formatService->bool($status->isDisplayedOnSchedule()),
                    "createDropMovementOnDropLocation" => $this->formatService->bool($status->isCreateDropMovementOnDropLocation()),
                    "notifiedUsers" => Stream::from($status->getNotifiedUsers())
                        ->map(static fn(Utilisateur $user) => $user->getUsername())
                        ->join(", "),
                    "typeForGeneratedDispatchOnStatusChange" => $this->formatService->type($status->getTypeForGeneratedDispatchOnStatusChange()),
                    "requiredAttachment" => $this->formatService->bool($status->isRequiredAttachment()),
                    "preventStatusChangeWithoutDeliveryFees" => $this->formatService->bool($status->isPreventStatusChangeWithoutDeliveryFees(), 'Non'),
                    "passStatusAtPurchaseOrderGeneration" => $this->formatService->bool($status->isPassStatusAtPurchaseOrderGeneration()),
                    "order" => $status->getDisplayOrder(),
                ];
            }
        }

        return $this->json([
            "data" => $data,
            "recordsTotal" => count($data),
            "recordsFiltered" => count($data),
        ]);
    }

    #[Route('/status/{entity}/supprimer', name: 'settings_delete_status', options: ['expose' => true])]
    #[HasPermission([Menu::PARAM, Action::DELETE])]
    public function deleteStatus(EntityManagerInterface $manager, Statut $entity): JsonResponse
    {
        if($entity->isDefaultForCategory()) {
            return $this->json([
                "success" => false,
                "msg" => "Impossible de supprimer le statut car c'est un statut par défaut",
            ]);
        } else {
            $shippingRequestRepository = $manager->getRepository(ShippingRequest::class);
            $statusHistoryRepository = $manager->getRepository(StatusHistory::class);
            $productionRequestRepository = $manager->getRepository(ProductionRequest::class);

            $constraints = [
                "un litige" => $entity->getDisputes(),
                "une demande d'achat" => $entity->getPurchaseRequests(),
                "un arrivage" => $entity->getArrivages(),
                "un article" => $entity->getArticles(),
                "une collecte" => $entity->getCollectes(),
                "une demande de livraison" => $entity->getDemandes(),
                "un ordre de livraison" => $entity->getLivraisons(),
                "une préparation" => $entity->getPreparations(),
                "une réception" => $entity->getReceptions(),
                "une référence article" => $entity->getReferenceArticles(),
                "une demande de service" => $entity->getHandlings(),
                "une demande d'acheminement" => $entity->getDispatches(),
                "une demande de transfert" => $entity->getTransferRequests(),
                "un ordre de transfert" => $entity->getTransferOrders(),
                "un historique de statut" => $statusHistoryRepository->findBy(['status' => $entity]),
                "une demande d'expédition" => $shippingRequestRepository->findBy(['status' => $entity]),
                "une demande de production" => $productionRequestRepository->findBy(['status' => $entity]),
            ];

            $constraints = Stream::from($constraints)
                ->filter(fn($collection) => count($collection) > 0)
                ->takeKeys()
                ->map(fn(string $item) => "au moins $item")
                ->join(", ");

            if (!$constraints) {
                $manager->remove($entity->getLabelTranslation());
                $manager->flush();

                $manager->remove($entity);
                $manager->flush();
            } else {
                return $this->json([
                    "success" => false,
                    "msg" => "Impossible de supprimer le statut car il est lié à $constraints",
                ]);
            }

            return $this->json([
                "success" => true,
                "msg" => "Le statut a été supprimé",
            ]);
        }
    }

    #[Route('/status-api/edit/translate', name: 'settings_edit_status_translations_api', options: ['expose' => true], methods: 'GET', condition: 'request.isXmlHttpRequest()')]
    #[HasPermission([Menu::PARAM, Action::EDIT])]
    public function apiEditTranslations(Request $request,
                                        EntityManagerInterface $manager,
                                        TranslationService $translationService): JsonResponse
    {
        $mode = $request->query->get("mode");
        $typeId = $request->query->get("type");

        $statusRepository = $manager->getRepository(Statut::class);
        $typeRepository = $manager->getRepository(Type::class);

        $category = match ($mode) {
            self::MODE_ARRIVAL_DISPUTE => CategorieStatut::DISPUTE_ARR,
            self::MODE_RECEPTION_DISPUTE => CategorieStatut::LITIGE_RECEPT,
            self::MODE_PURCHASE_REQUEST => CategorieStatut::PURCHASE_REQUEST,
            self::MODE_ARRIVAL => CategorieStatut::ARRIVAGE,
            self::MODE_DISPATCH => CategorieStatut::DISPATCH,
            self::MODE_HANDLING => CategorieStatut::HANDLING
        };

        $type = $typeId ? $typeRepository->find($typeId) : null;
        $statuses = $statusRepository->findStatusByType($category, $type);

        foreach ($statuses as $status) {
            if ($status->getLabelTranslation() === null) {
                $translationService->setDefaultTranslation($manager, $status, $status->getNom());
            }
        }
        $manager->flush();


        $html = $this->renderView('settings/modal_edit_translations_content.html.twig', [
            'lines' => $statuses
        ]);

        return $this->json([
            'success' => true,
            'html' => $html
            ]);
    }

    #[Route('/status/edit/translate', name: 'settings_edit_status_translations', options: ['expose' => true], methods: 'GET|POST', condition: 'request.isXmlHttpRequest()')]
    #[HasPermission([Menu::PARAM, Action::EDIT], mode: HasPermission::IN_JSON)]
    public function editTranslations(Request                $request,
                                     StatusService          $statusService,
                                     EntityManagerInterface $manager,
                                     TranslationService     $translationService): JsonResponse {
        if ($data = json_decode($request->getContent(), true)) {
            $statusRepository = $manager->getRepository(Statut::class);
            $statuses = json_decode($data['lines'], true);

            $persistedStatuses = [];
            foreach ($statuses as $statusId) {
                $status = $statusRepository->find($statusId);
                $persistedStatuses[] = $status;

                $name = 'labels-'.$status->getId();
                $labels = $data[$name];
                $labelTranslationSource = $status->getLabelTranslation();

                $translationService->editEntityTranslations($manager, $labelTranslationSource, $labels);
            }

            $duplicateLabels = $statusService->countDuplicateStatusLabels($persistedStatuses);
            if($duplicateLabels > 0) {
                return $this->json([
                    "success" => false,
                    "msg" => "Il n'est pas possible d'avoir deux libellés de statut identiques pour le même type et la même langue",
                ]);
            }

            $manager->flush();

            return new JsonResponse([
                'success' => true,
                'msg' => "Les traductions ont bien été modifiées."
            ]);
        }
        throw new BadRequestHttpException();
    }
}
