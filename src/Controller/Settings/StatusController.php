<?php

namespace App\Controller\Settings;

use App\Annotation\HasPermission;
use App\Entity\Action;
use App\Entity\CategorieStatut;
use App\Entity\CategoryType;
use App\Entity\Emplacement;
use App\Entity\Language;
use App\Entity\Menu;
use App\Entity\Nature;
use App\Entity\Role;
use App\Entity\ShippingRequest\ShippingRequest;
use App\Entity\StatusHistory;
use App\Entity\Statut;
use App\Entity\Translation;
use App\Entity\TranslationSource;
use App\Entity\Type;
use App\Exceptions\FormException;
use App\Service\DispatchService;
use App\Service\StatusService;
use App\Service\TranslationService;
use App\Service\UserService;
use Doctrine\ORM\EntityManagerInterface;
use App\Controller\AbstractController;
use Symfony\Component\HttpClient\Exception\InvalidArgumentException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;
use WiiCommon\Helper\Stream;

#[Route("/parametrage")]
class StatusController extends AbstractController
{

    #[Route("/statuses-api", name: "settings_statuses_api", options: ["expose" => true])]
    public function statusesApi(Request                $request,
                                UserService            $userService,
                                StatusService          $statusService,
                                DispatchService        $dispatchService,
                                EntityManagerInterface $entityManager): JsonResponse {
        $edit = $request->query->getBoolean("edit");
        $mode = $request->query->get("mode");
        $typeId = $request->query->get("type");

        if (!in_array($mode, array_keys(Statut::STATUS_MODES))) {
            throw new InvalidArgumentException('Invalid mode');
        }

        $hasAccess = match($mode) {
            Statut::MODE_ARRIVAL_DISPUTE, Statut::MODE_ARRIVAL => $userService->hasRightFunction(Menu::PARAM, Action::SETTINGS_DISPLAY_ARRI),
            Statut::MODE_DISPATCH => $userService->hasRightFunction(Menu::PARAM, Action::SETTINGS_DISPLAY_TRACING_DISPATCH),
            Statut::MODE_HANDLING => $userService->hasRightFunction(Menu::PARAM, Action::SETTINGS_DISPLAY_TRACING_HAND),
            Statut::MODE_RECEPTION_DISPUTE => $userService->hasRightFunction(Menu::PARAM, Action::SETTINGS_DISPLAY_RECEP),
            Statut::MODE_PURCHASE_REQUEST => $userService->hasRightFunction(Menu::PARAM, Action::SETTINGS_DISPLAY_REQUESTS)
        };

        if (!$hasAccess) {
            throw new BadRequestHttpException();
        }

        $canDelete = $userService->hasRightFunction(Menu::PARAM, Action::DELETE);

        $data = [];

        $statusRepository = $entityManager->getRepository(Statut::class);
        $typeRepository = $entityManager->getRepository(Type::class);

        $type = $typeId ? $typeRepository->find($typeId) : null;
        $statuses = $statusRepository->findStatusByType(Statut::STATUS_MODES[$mode], $type);

        foreach ($statuses as $status) {
            $actionColumn = $canDelete
                ? "<button class='btn btn-silent delete-row' data-id='{$status->getId()}'>
                       <i class='wii-icon wii-icon-trash text-primary'></i>
                   </button>
                   <input type='hidden' name='statusId' class='data' value='{$status->getId()}'/>
                   <input type='hidden' name='mode' class='data' value='{$mode}'/>"
                : "";

            $groupedSignatureColor = $status->getGroupedSignatureColor() ?? Statut::GROUPED_SIGNATURE_DEFAULT_COLOR;
            if ($edit) {
                $stateOptions = $statusService->getStatusStatesOptions($mode, $status->getState(), true);
                $groupedSignatureTypes = $dispatchService->getGroupedSignatureTypes($status->getGroupedSignatureType());

                $disabledMobileSyncAndColor = in_array($status->getState(), [Statut::DRAFT, Statut::TREATED]) ? 'disabled' : '';

                $defaultStatut = $status->isDefaultForCategory() ? 'checked' : "";
                $sendMailBuyers = $status->getSendNotifToBuyer() ? 'checked' : "";
                $sendMailRequesters = $status->getSendNotifToDeclarant() ? 'checked' : "";
                $sendMailDest = $status->getSendNotifToRecipient() ? 'checked' : "";
                $sendReport = $status->getSendReport() ? 'checked' : "";
                $needsMobileSync = (!in_array($status->getState(), [Statut::DRAFT, Statut::TREATED]) && $status->getNeedsMobileSync()) ? 'checked' : "";
                $commentNeeded = $status->getCommentNeeded() ? 'checked' : "";
                $automaticReceptionCreation = $status->getAutomaticReceptionCreation() ? 'checked' : "";
                $showAutomaticReceptionCreation = $status->getState() === Statut::TREATED ? "" : "d-none";

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
                    "groupedSignatureType" => "<select name='groupedSignatureType' class='data form-control select-size'>{$groupedSignatureTypes}</select>",
                    "groupedSignatureColor" => "<input type='color' class='form-control wii-color-picker data' name='color' value='{$groupedSignatureColor}' list='type-color' {$disabledMobileSyncAndColor}/>
                        <datalist id='type-color'>
                            <option>#D76433</option>
                            <option>#D7B633</option>
                            <option>#A5D733</option>
                            <option>#33D7D1</option>
                            <option>#33A5D7</option>
                            <option>#3353D7</option>
                            <option>#6433D7</option>
                            <option>#D73353</option>
                        </datalist>",
                    "needsMobileSync" => "<div class='checkbox-container'><input type='checkbox' name='needsMobileSync' class='form-control data' {$disabledMobileSyncAndColor} {$needsMobileSync}/></div>",
                    "commentNeeded" => "<div class='checkbox-container'><input type='checkbox' name='commentNeeded' class='form-control data' {$commentNeeded}/></div>",
                    "automaticReceptionCreation" => "<div class='checkbox-container'><input type='checkbox' name='automaticReceptionCreation' class='form-control data $showAutomaticReceptionCreation' {$automaticReceptionCreation}/></div>",
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
                    "groupedSignatureType" => $status->getGroupedSignatureType(),
                    "groupedSignatureColor" => "<div class='dt-type-color' style='background: {$groupedSignatureColor}'></div>",
                    "needsMobileSync" => $this->formatService->bool(!in_array($status->getState(), [Statut::DRAFT, Statut::TREATED]) && $status->getNeedsMobileSync()),
                    "commentNeeded" => $this->formatService->bool($status->getCommentNeeded()),
                    "automaticReceptionCreation" => $this->formatService->bool($status->getAutomaticReceptionCreation()),
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

    #[Route("/status/{entity}/supprimer", name: "settings_delete_status", options: ["expose" => true])]
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

    #[Route("/status-api/edit/translate", name: "settings_edit_status_translations_api", options: ["expose" => true], methods: "GET", condition: "request.isXmlHttpRequest()")]
    #[HasPermission([Menu::PARAM, Action::EDIT])]
    public function apiEditTranslations(Request $request,
                                        EntityManagerInterface $manager,
                                        TranslationService $translationService): JsonResponse
    {
        $mode = $request->query->get("mode");
        $typeId = $request->query->get("type");

        $statusRepository = $manager->getRepository(Statut::class);
        $typeRepository = $manager->getRepository(Type::class);

        $type = $typeId ? $typeRepository->find($typeId) : null;
        $statuses = $statusRepository->findStatusByType(Statut::STATUS_MODES[$mode], $type);

        foreach ($statuses as $status) {
            if ($status->getLabelTranslation() === null) {
                $translationService->setFirstTranslation($manager, $status, $status->getNom());
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

    #[Route("/status/edit/translate", name: "settings_edit_status_translations", options: ["expose" => true], methods: "GET|POST", condition: "request.isXmlHttpRequest()")]
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

    #[Route("/form-template/{mode}/{type}/{status}", name: "status_form_template", options: ["expose" => true], defaults: ["status" => null], methods: "GET")]
    public function formTemplate(Type $type, string $mode, ?int $status, EntityManagerInterface $manager): Response {
        $roleRepository = $manager->getRepository(Role::class);
        $typeRepository = $manager->getRepository(Type::class);
        $natureRepository = $manager->getRepository(Nature::class);

        $status = $status
            ? $manager->find(Statut::class, $status)
            : null;

        return $this->json([
            "html" => $this->renderView("settings/trace/acheminements/status_form/form.html.twig", [
                "status" => $status ?: new Statut(),
                "type" => $type,
                "mode" => $mode,
                "roles" => $roleRepository->findAll(),
                "dispatchTypes" => $typeRepository->findByCategoryLabels([CategoryType::DEMANDE_DISPATCH]),
                "natures" => $natureRepository->findByAllowedForms([Nature::DISPATCH_CODE]),
            ]),
        ]);
    }

    #[Route("/form-submit", name: "status_form_submit", options: ["expose" => true], methods: "POST")]
    public function formSubmit(Request                $request,
                               EntityManagerInterface $manager,
                               StatusService          $statusService,
                               TranslationService     $translationService): Response
    {
        $query = $request->query;

        $roleRepository = $manager->getRepository(Role::class);
        $statusRepository = $manager->getRepository(Statut::class);
        $typeRepository = $manager->getRepository(Type::class);
        $natureRepository = $manager->getRepository(Nature::class);
        $locationRepository = $manager->getRepository(Emplacement::class);
        $categoryStatusRepository = $manager->getRepository(CategorieStatut::class);

        $mode = $query->get("mode");
        $locationIds = explode(",", $query->get("locations", ""));
        $labels = json_decode($query->get("labels"), true);

        $status = $query->get("status")
            ? $statusRepository->find($query->getInt("status"))
            : new Statut();
        $type = $typeRepository->find($query->getInt("type"));
        $newDispatchType = $query->get("newDispatchType")
            ? $typeRepository->find($query->getInt("newDispatchType"))
            : null;
        $categoryStatus = $categoryStatusRepository->findOneBy(['nom' => Statut::STATUS_MODES[$mode]]);

        $frenchLanguage = $manager->getRepository(Language::class)->findOneBy(['slug' => Language::FRENCH_SLUG]);
        $frenchLabel = Stream::from($labels)
            ->find(fn(array $element) => intval($element['language-id']) === $frenchLanguage->getId());

        $status
            ->setNom($frenchLabel['label'])
            ->setState($query->getInt("state"))
            ->setCategorie($categoryStatus)
            ->setType($type)
            ->setDisplayOrder($query->getInt("order"))
            ->setSendNotifToDeclarant($query->getBoolean("sendMailRequester"))
            ->setSendNotifToRecipient($query->getBoolean("sendMailReceivers"))
            ->setNeedsMobileSync($query->getBoolean("mobileSync"))
            ->setAutomaticDispatchCreation($query->getBoolean("automaticDispatchCreation"))
            ->setAutomaticDispatchCreationType($newDispatchType)
            ->setAutomatic($query->getBoolean("automatic"))
            ->setAutomaticStatusMovementType($query->getInt("movementType"))
            ->setAutomaticAllPacksOnDepositLocation($query->getBoolean("automaticAllPacksOnDepositLocation"));

        if($query->has("natures")) {
            $natures = $natureRepository->findBy(["id" => explode(",", $query->get("natures", ""))]);
            $status->setAutomaticStatusExcludedNatures($natures);
        }

        if($query->has("locations")) {
            $validLocations = $locationRepository->countWithoutAutoStatus($locationIds, $type, $status);
            if ($validLocations !== count($locationIds)){
                throw new FormException("Un des emplacements sélectionné l'est déjà pour un autre statut.");
            } else {
                $locations = $locationRepository->findBy(["id" => $locationIds]);
                $status->setAutomaticStatusLocations($locations);
            }
        }

        $accessibleBy = $roleRepository->findBy(["id" => explode(",", $query->get("accessibleBy"))]);
        $status->getAccessibleBy()->clear();
        foreach ($accessibleBy as $role) {
            $status->addAccessibleBy($role);
        }

        $labelTranslation = $status->getLabelTranslation();
        if (!$labelTranslation) {
            $labelTranslation = new TranslationSource();
            $manager->persist($labelTranslation);

            $status->setLabelTranslation($labelTranslation);

            foreach ($labels as $label) {
                $labelLanguage = $manager->getRepository(Language::class)->find($label['language-id']);

                $newTranslation = new Translation();
                $newTranslation
                    ->setTranslation($label['label'])
                    ->setSource($labelTranslation)
                    ->setLanguage($labelLanguage);

                $labelTranslation->addTranslation($newTranslation);
                $manager->persist($newTranslation);
            }
        } else {
            $translationService->editEntityTranslations($manager, $labelTranslation, $labels);
        }

        $validation = $statusService->validateStatusesData([...$statusRepository->findBy(["type" => $type]), ...!$status->getId() ? [$status] : []]);
        if (!$validation['success']) {
            throw new FormException($validation['message']);
        }

        $message = "Le statut a bien été " . ($status->getId() ? "modifié" : "créé") . ".";
        $manager->persist($status);

        $manager->flush();

        return $this->json([
            "success" => true,
            "msg" => $message,
        ]);
    }
}
