<?php

namespace App\Controller;

use App\Annotation\HasPermission;
use App\Entity\Action;
use App\Entity\Arrivage;
use App\Entity\CategorieCL;
use App\Entity\CategorieStatut;
use App\Entity\CategoryType;
use App\Entity\Emplacement;
use App\Entity\FreeField;
use App\Entity\Chauffeur;
use App\Entity\Pack;
use App\Entity\FieldsParam;
use App\Entity\Fournisseur;
use App\Entity\Dispute;
use App\Entity\Menu;
use App\Entity\Nature;
use App\Entity\Setting;
use App\Entity\Attachment;
use App\Entity\Statut;
use App\Entity\Transporteur;
use App\Entity\Type;
use App\Entity\Utilisateur;
use App\Service\VisibleColumnService;
use WiiCommon\Helper\Stream;
use App\Service\ArrivageService;
use App\Service\AttachmentService;
use App\Service\FieldsParamService;
use App\Service\TrackingMovementService;
use App\Service\PackService;
use App\Service\CSVExportService;
use App\Service\DisputeService;
use App\Service\PDFGeneratorService;
use App\Service\SpecificService;
use App\Service\UniqueNumberService;
use App\Service\UrgenceService;
use App\Service\UserService;
use App\Service\FreeFieldService;
use DateTime;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Bundle\SnappyBundle\Snappy\Response\PdfResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;
use App\Service\TranslationService;
use Throwable;
use Twig\Environment as Twig_Environment;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Entity;

/**
 * @Route("/arrivage")
 */
class ArrivageController extends AbstractController {

    /** @Required */
    public UserService $userService;

    /** @Required */
    public AttachmentService $attachmentService;

    /**
     * @Route("/", name="arrivage_index")
     * @HasPermission({Menu::TRACA, Action::DISPLAY_ARRI})
     */
    public function index(EntityManagerInterface $entityManager, ArrivageService $arrivageDataService)
    {
        $fieldsParamRepository = $entityManager->getRepository(FieldsParam::class);
        $settingRepository = $entityManager->getRepository(Setting::class);
        $emplacementRepository = $entityManager->getRepository(Emplacement::class);
        $typeRepository = $entityManager->getRepository(Type::class);
        $champLibreRepository = $entityManager->getRepository(FreeField::class);
        $fournisseurRepository = $entityManager->getRepository(Fournisseur::class);
        $chauffeurRepository = $entityManager->getRepository(Chauffeur::class);
        $transporteurRepository = $entityManager->getRepository(Transporteur::class);
        $natureRepository = $entityManager->getRepository(Nature::class);
        $utilisateurRepository = $entityManager->getRepository(Utilisateur::class);
        $statutRepository = $entityManager->getRepository(Statut::class);

        /** @var Utilisateur $user */
        $user = $this->getUser();

        $fields = $arrivageDataService->getColumnVisibleConfig($entityManager, $user);

        $fieldsParam = $fieldsParamRepository->getByEntity(FieldsParam::ENTITY_CODE_ARRIVAGE);
        $paramGlobalRedirectAfterNewArrivage = $settingRepository->findOneBy(['label' => Setting::REDIRECT_AFTER_NEW_ARRIVAL]);

        $statuses = $statutRepository->findStatusByType(CategorieStatut::ARRIVAGE);
        $defaultLocation = $settingRepository->getOneParamByLabel(Setting::MVT_DEPOSE_DESTINATION);
        $defaultLocation = $defaultLocation ? $emplacementRepository->find($defaultLocation) : null;
        return $this->render('arrivage/index.html.twig', [
            'carriers' => $transporteurRepository->findAllSorted(),
            'chauffeurs' => $chauffeurRepository->findAllSorted(),
            'users' => $utilisateurRepository->findBy(['status' => true], ['username'=> 'ASC']),
            'fournisseurs' => $fournisseurRepository->findBy([], ['nom' => 'ASC']),
            'disputeTypes' => $typeRepository->findByCategoryLabels([CategoryType::DISPUTE]),
            'natures' => $natureRepository->findByAllowedForms([Nature::ARRIVAL_CODE]),
            'statuts' => $statuses,
            'typesArrival' => $typeRepository->findByCategoryLabels([CategoryType::ARRIVAGE]),
            'fieldsParam' => $fieldsParam,
            'redirect' => $paramGlobalRedirectAfterNewArrivage ? $paramGlobalRedirectAfterNewArrivage->getValue() : true,
            'champsLibres' => $champLibreRepository->findByCategoryTypeLabels([CategoryType::ARRIVAGE]),
            'pageLengthForArrivage' => $user->getPageLengthForArrivage() ?: 10,
            'autoPrint' => $settingRepository->getOneParamByLabel(Setting::AUTO_PRINT_COLIS),
            'fields' => $fields,
            'defaultLocation' => $defaultLocation,
            'businessUnits' => $fieldsParamRepository->getElements(FieldsParam::ENTITY_CODE_ARRIVAGE, FieldsParam::FIELD_CODE_BUSINESS_UNIT),
            'defaultStatuses' => $statutRepository->getIdDefaultsByCategoryName(CategorieStatut::ARRIVAGE),
            'modalNewConfig' => [
                'defaultStatuses' => $statutRepository->getIdDefaultsByCategoryName(CategorieStatut::ARRIVAGE),
                'statuses' => $statuses,
            ],
        ]);
    }

    /**
     * @Route("/api", name="arrivage_api", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::TRACA, Action::DISPLAY_ARRI}, mode=HasPermission::IN_JSON)
     */
    public function api(Request $request,
                        ArrivageService $arrivageService): Response
    {
        if($this->userService->hasRightFunction(Menu::TRACA, Action::LIST_ALL) || !$this->getUser()) {
            $userId = null;
        } else {
            $userId = $this->getUser()->getId();
        }

        return $this->json($arrivageService->getDataForDatatable($request, $userId));
    }

    /**
     * @Route("/creer", name="arrivage_new", options={"expose"=true}, methods={"GET", "POST"}, condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::TRACA, Action::CREATE}, mode=HasPermission::IN_JSON)
     */
    public function new(Request                $request,
                        EntityManagerInterface $entityManager,
                        AttachmentService      $attachmentService,
                        ArrivageService        $arrivageDataService,
                        FreeFieldService       $champLibreService,
                        PackService            $colisService,
                        TranslationService    $translation): Response
    {
        $data = $request->request->all();
        $settingRepository = $entityManager->getRepository(Setting::class);
        $arrivageRepository = $entityManager->getRepository(Arrivage::class);
        $statutRepository = $entityManager->getRepository(Statut::class);
        $fournisseurRepository = $entityManager->getRepository(Fournisseur::class);
        $transporteurRepository = $entityManager->getRepository(Transporteur::class);
        $emplacementRepository = $entityManager->getRepository(Emplacement::class);
        $chauffeurRepository = $entityManager->getRepository(Chauffeur::class);
        $userRepository = $entityManager->getRepository(Utilisateur::class);
        $typeRepository = $entityManager->getRepository(Type::class);
        $sendMail = $settingRepository->getOneParamByLabel(Setting::SEND_MAIL_AFTER_NEW_ARRIVAL);

        $date = new DateTime('now');
        $counter = $arrivageRepository->countByDate($date) + 1;
        $suffix = $counter < 10 ? ("0" . $counter) : $counter;
        $numeroArrivage = $date->format('ymdHis') . '-' . $suffix;

        /** @var Utilisateur $currentUser */
        $currentUser = $this->getUser();
        $dropLocation = !empty($data['dropLocation']) ? $emplacementRepository->find($data['dropLocation']) : null;

        $arrivage = new Arrivage();
        $arrivage
            ->setIsUrgent(false)
            ->setDate($date)
            ->setUtilisateur($currentUser)
            ->setDropLocation($dropLocation)
            ->setNumeroArrivage($numeroArrivage)
            ->setCustoms(isset($data['customs']) ? $data['customs'] == 'true' : false)
            ->setFrozen(isset($data['frozen']) ? $data['frozen'] == 'true' : false)
            ->setCommentaire($data['commentaire'] ?? null)
            ->setType($typeRepository->find($data['type']));

        $status = !empty($data['status']) ? $statutRepository->find($data['status']) : null;
        if (!empty($status)) {
            $arrivage->setStatut($status);
        } else {
            return new JsonResponse([
                'success' => false,
                'msg' => $translation->translate("Général", null, "Modale", "Veuillez renseigner le champ {1}", [
                    1 =>  $translation->translate('Traçabilité', 'Flux - Arrivages', 'Champs fixes', 'Statut'),
                ]),
            ]);
        }

        if (!empty($data['fournisseur'])) {
            $arrivage->setFournisseur($fournisseurRepository->find($data['fournisseur']));
        }

        if (!empty($data['transporteur'])) {
            $arrivage->setTransporteur($transporteurRepository->find($data['transporteur']));
        }

        if (!empty($data['chauffeur'])) {
            $arrivage->setChauffeur($chauffeurRepository->find($data['chauffeur']));
        }

        if (!empty($data['noTracking'])) {
            $arrivage->setNoTracking(substr($data['noTracking'], 0, 64));
        }

        $numeroCommandeList = explode(',', $data['numeroCommandeList'] ?? '');
        if (!empty($numeroCommandeList)) {
            $arrivage->setNumeroCommandeList($numeroCommandeList);
        }

        if (!empty($data['destinataire'])) {
            $arrivage->setDestinataire($userRepository->find($data['destinataire']));
        }

        if (!empty($data['businessUnit'])) {
            $arrivage->setBusinessUnit($data['businessUnit']);
        }

        if (!empty($data['noProject'])) {
            $arrivage->setProjectNumber($data['noProject']);
        }

        if (!empty($data['acheteurs'])) {
            $acheteursId = explode(',', $data['acheteurs']);
            foreach ($acheteursId as $acheteurId) {
                $arrivage->addAcheteur($userRepository->find($acheteurId));
            }
        }
        $this->persistAttachmentsForEntity($arrivage, $attachmentService, $request, $entityManager);

        try {
            $entityManager->flush();
        }
        /** @noinspection PhpRedundantCatchClauseInspection */
        catch (UniqueConstraintViolationException $e) {
            return new JsonResponse([
                'success' => false,
                'msg' => $translation->translate('Traçabilité', 'Flux - Arrivages', 'Divers fixes', 'Un autre arrivage est en cours de création, veuillez réessayer')
            ]);
        }

        $colis = isset($data['colis']) ? json_decode($data['colis'], true) : [];
        $natures = [];
        foreach ($colis as $key => $value) {
            if (isset($value)) {
                $natures[intval($key)] = intval($value);
            }
        }
        $total = array_reduce($natures, function (int $carry, $nature) {
            return $carry + $nature;
        }, 0);

        if ($total === 0) {
            return new JsonResponse([
                'success' => false,
                'msg' => $translation->translate("Général", null, "Modale", "Veuillez renseigner le champ {1}", [
                    1 =>  $translation->translate('Traçabilité', 'Général', 'Unités logistiques'),
                ]),
            ]);
        }

        $champLibreService->manageFreeFields($arrivage, $data, $entityManager, $this->getUser());

        $supplierEmergencyAlert = $arrivageDataService->createSupplierEmergencyAlert($arrivage);
        $isArrivalUrgent = isset($supplierEmergencyAlert);
        $alertConfigs = $isArrivalUrgent
            ? [
                $supplierEmergencyAlert,
                $arrivageDataService->createArrivalAlertConfig($arrivage, false)
            ]
            : $arrivageDataService->processEmergenciesOnArrival($arrivage);

        if ($isArrivalUrgent) {
            $arrivage->setIsUrgent(true);
        }

        // persist packs after set arrival urgent
        $colisService->persistMultiPacks(
            $entityManager,
            $arrivage,
            $natures,
            $currentUser,
            false
        );

        $entityManager->flush();

        if ($sendMail) {
            $arrivageDataService->sendArrivalEmails($arrivage);
        }

        $entityManager->flush();
        $paramGlobalRedirectAfterNewArrivage = $settingRepository->findOneBy(['label' => Setting::REDIRECT_AFTER_NEW_ARRIVAL]);

        return new JsonResponse([
            'success' => true,
            "redirectAfterAlert" => ($paramGlobalRedirectAfterNewArrivage ? $paramGlobalRedirectAfterNewArrivage->getValue() : true)
                ? $this->generateUrl('arrivage_show', ['id' => $arrivage->getId()])
                : null,
            'printColis' => (isset($data['printColis']) && $data['printColis'] === 'true'),
            'printArrivage' => isset($data['printArrivage']) && $data['printArrivage'] === 'true',
            'arrivageId' => $arrivage->getId(),
            'numeroArrivage' => $arrivage->getNumeroArrivage(),
            'alertConfigs' => $alertConfigs
        ]);
    }

    /**
     * @Route("/api-modifier", name="arrivage_edit_api", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::TRACA, Action::DISPLAY_ARRI}, mode=HasPermission::IN_JSON)
     */
    public function editApi(Request $request,
                            EntityManagerInterface $entityManager): Response
    {
        if ($data = json_decode($request->getContent(), true)) {
            if ($this->userService->hasRightFunction(Menu::TRACA, Action::EDIT)) {
                $arrivageRepository = $entityManager->getRepository(Arrivage::class);
                $fieldsParamRepository = $entityManager->getRepository(FieldsParam::class);
                $chauffeurRepository = $entityManager->getRepository(Chauffeur::class);
                $fournisseurRepository = $entityManager->getRepository(Fournisseur::class);
                $attachmentRepository = $entityManager->getRepository(Attachment::class);
                $utilisateurRepository = $entityManager->getRepository(Utilisateur::class);
                $statutRepository = $entityManager->getRepository(Statut::class);
                $transporteurRepository = $entityManager->getRepository(Transporteur::class);

                $arrivage = $arrivageRepository->find($data['id']);

                // construction de la chaîne de caractères pour alimenter le select2
                $acheteursUsernames = [];
                foreach ($arrivage->getAcheteurs() as $acheteur) {
                    $acheteursUsernames[] = $acheteur->getUsername();
                }
                $fieldsParam = $fieldsParamRepository->getByEntity(FieldsParam::ENTITY_CODE_ARRIVAGE);

                $statuses = $statutRepository->findStatusByType(CategorieStatut::ARRIVAGE, $arrivage->getType());

                $html = $this->renderView('arrivage/modalEditArrivageContent.html.twig', [
                    'arrivage' => $arrivage,
                    'attachments' => $attachmentRepository->findBy(['arrivage' => $arrivage]),
                    'utilisateurs' => $utilisateurRepository->findBy(['status' => true], ['username' => 'ASC']),
                    'fournisseurs' => $fournisseurRepository->findBy([], ['nom' => 'ASC']),
                    'transporteurs' => $transporteurRepository->findAllSorted(),
                    'chauffeurs' => $chauffeurRepository->findAllSorted(),
                    'statuts' => $statuses,
                    'fieldsParam' => $fieldsParam,
                    'businessUnits' => $fieldsParamRepository->getElements(FieldsParam::ENTITY_CODE_ARRIVAGE, FieldsParam::FIELD_CODE_BUSINESS_UNIT)
                ]);
            }

            return new JsonResponse([
                'html' => $html ?? "",
                'acheteurs' => $acheteursUsernames ?? []
            ]);
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/{arrival}/urgent", name="patch_arrivage_urgent", options={"expose"=true}, methods="PATCH", condition="request.isXmlHttpRequest() && ('%client%' == constant('\\App\\Service\\SpecificService::CLIENT_SAFRAN_ED') || '%client%' == constant('\\App\\Service\\SpecificService::CLIENT_SAFRAN_NS'))")
     * @Entity("arrival", expr="repository.find(arrival) ?: repository.findOneBy({'numeroArrivage': arrival})")
     */
    public function patchUrgentArrival(Arrivage $arrival,
                                       Request $request,
                                       ArrivageService $arrivageDataService,
                                       UrgenceService $urgenceService,
                                       EntityManagerInterface $entityManager): Response
    {
        $numeroCommande = $request->request->get('numeroCommande');
        $postNb = $request->request->get('postNb');

        $urgencesMatching = !empty($numeroCommande)
            ? $urgenceService->matchingEmergencies(
                $arrival,
                $numeroCommande,
                $postNb,
                true
            )
            : [];

        $success = !empty($urgencesMatching);

        if ($success) {
            $arrivageDataService->setArrivalUrgent($arrival, $urgencesMatching);
            $entityManager->flush();
        }

        $response = [
            'success' => $success,
            'alertConfigs' => $success
                ? [$arrivageDataService->createArrivalAlertConfig($arrival, false, $urgencesMatching)]
                : null
        ];

        return new JsonResponse($response);
    }

    /**
     * @Route("/{arrival}/tracking-movements", name="post_arrival_tracking_movements", options={"expose"=true}, methods="POST", condition="request.isXmlHttpRequest()")
     * @Entity("arrival", expr="repository.find(arrival) ?: repository.findOneBy({'numeroArrivage': arrival})")
     */
    public function postArrivalTrackingMovements(Arrivage                $arrival,
                                                 ArrivageService         $arrivageDataService,
                                                 TrackingMovementService $trackingMovementService,
                                                 EntityManagerInterface  $entityManager): Response
    {
        $location = $arrivageDataService->getLocationForTracking($entityManager, $arrival);

        if (isset($location)) {
            /** @var Utilisateur $user */
            $user = $this->getUser();
            $now = new DateTime('now');
            foreach ($arrival->getPacks() as $pack) {
                $trackingMovementService->persistTrackingForArrivalPack(
                    $entityManager,
                    $pack,
                    $location,
                    $user,
                    $now,
                    $arrival
                );
            }

            $entityManager->flush();
        }
        return new JsonResponse(['success' => true]);
    }

    /**
     * @Route("/modifier", name="arrivage_edit", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::TRACA, Action::EDIT}, mode=HasPermission::IN_JSON)
     */
    public function edit(Request                $request,
                         SpecificService        $specificService,
                         ArrivageService        $arrivageDataService,
                         FreeFieldService       $champLibreService,
                         EntityManagerInterface $entityManager): Response
    {
        $statutRepository = $entityManager->getRepository(Statut::class);
        $fournisseurRepository = $entityManager->getRepository(Fournisseur::class);
        $settingRepository = $entityManager->getRepository(Setting::class);
        $arrivageRepository = $entityManager->getRepository(Arrivage::class);
        $chauffeurRepository = $entityManager->getRepository(Chauffeur::class);
        $utilisateurRepository = $entityManager->getRepository(Utilisateur::class);
        $emplacementRepository = $entityManager->getRepository(Emplacement::class);
        $typeRepository = $entityManager->getRepository(Type::class);
        $transporteurRepository = $entityManager->getRepository(Transporteur::class);

        $post = $request->request;
        $isSEDCurrentClient = $specificService->isCurrentClientNameFunction(SpecificService::CLIENT_SAFRAN_ED)
            || $specificService->isCurrentClientNameFunction(SpecificService::CLIENT_SAFRAN_NS);

        $arrivage = $arrivageRepository->find($post->get('id'));

        $fournisseurId = $post->get('fournisseur');
        $transporteurId = $post->get('transporteur');
        $destinataireId = $post->get('destinataire');
        $statutId = $post->get('statut');
        $dropLocationId = $post->get('dropLocation');
        $chauffeurId = $post->get('chauffeur');
        $type = $post->get('type');
        $newDestinataire = $destinataireId ? $utilisateurRepository->find($destinataireId) : null;
        $destinataireChanged = $newDestinataire && $newDestinataire !== $arrivage->getDestinataire();
        $numeroCommadeListStr = $post->get('numeroCommandeList');
        $dropLocation = $post->has('dropLocation')
            ? ($dropLocationId ? $emplacementRepository->find($dropLocationId) : null)
            : $arrivage->getDropLocation();

        $sendMail = $settingRepository->getOneParamByLabel(Setting::SEND_MAIL_AFTER_NEW_ARRIVAL);

        $oldSupplierId = $arrivage->getFournisseur() ? $arrivage->getFournisseur()->getId() : null;

        $arrivage
            ->setCommentaire($post->get('commentaire'))
            ->setNoTracking(substr($post->get('noTracking'), 0, 64))
            ->setNumeroCommandeList(explode(',', $numeroCommadeListStr))
            ->setDropLocation($dropLocation)
            ->setFournisseur($fournisseurId ? $fournisseurRepository->find($fournisseurId) : null)
            ->setTransporteur($transporteurId ? $transporteurRepository->find($transporteurId) : null)
            ->setChauffeur($chauffeurId ? $chauffeurRepository->find($chauffeurId) : null)
            ->setStatut($statutId ? $statutRepository->find($statutId) : null)
            ->setCustoms($post->get('customs') == 'true')
            ->setFrozen($post->get('frozen') == 'true')
            ->setDestinataire($newDestinataire)
            ->setBusinessUnit($post->get('businessUnit') ?? null)
            ->setProjectNumber($post->get('noProject') ?? null)
            ->setType($typeRepository->find($type));

        $newSupplierId = $arrivage->getFournisseur() ? $arrivage->getFournisseur()->getId() : null;

        $acheteurs = $post->get('acheteurs');

        $acheteursEntities = array_map(function ($acheteur) use ($utilisateurRepository) {
            return $utilisateurRepository->findOneBy(['username' => $acheteur]);
        }, explode(',', $acheteurs));

        $arrivage->removeAllAcheteur();
        if (!empty($acheteurs)) {
            foreach ($acheteursEntities as $acheteursEntity) {
                $arrivage->addAcheteur($acheteursEntity);
            }
        }
        $entityManager->flush();
        if ($sendMail && $destinataireChanged) {
            $arrivageDataService->sendArrivalEmails($arrivage);
        }

        $listAttachmentIdToKeep = $post->all('files') ?? [];

        $attachments = $arrivage->getAttachments()->toArray();
        foreach ($attachments as $attachment) {
            /** @var Attachment $attachment */
            if (!in_array($attachment->getId(), $listAttachmentIdToKeep)) {
                $this->attachmentService->removeAndDeleteAttachment($attachment, $arrivage);
            }
        }

        $this->persistAttachmentsForEntity($arrivage, $this->attachmentService, $request, $entityManager);

        $champLibreService->manageFreeFields($arrivage, $post->all(), $entityManager, $this->getUser());
        $entityManager->flush();

        $supplierEmergencyAlert = ($oldSupplierId !== $newSupplierId && $newSupplierId)
            ? $arrivageDataService->createSupplierEmergencyAlert($arrivage)
            : null;
        $isArrivalUrgent = isset($supplierEmergencyAlert);
        $alertConfig = $isArrivalUrgent
            ? [
                $supplierEmergencyAlert,
                $arrivageDataService->createArrivalAlertConfig($arrivage, false)
            ]
            : $arrivageDataService->createArrivalAlertConfig($arrivage, $isSEDCurrentClient);

        if ($isArrivalUrgent) {
            $arrivage->setIsUrgent(true);
            $entityManager->flush();
        }

        $response = [
            'success' => true,
            'msg' => "L'arrivage a bien été modifié",
            'entete' => $this->renderView('arrivage/arrivage-show-header.html.twig', [
                'arrivage' => $arrivage,
                'canBeDeleted' => $arrivageRepository->countUnsolvedDisputesByArrivage($arrivage) == 0,
                'showDetails' => $arrivageDataService->createHeaderDetailsConfig($arrivage),
                'allPacksAlreadyInDispatch' => $arrivage->getPacks()->count() <= $arrivageRepository->countArrivalPacksInDispatch($arrivage)
            ]),
            'alertConfigs' => $alertConfig
        ];
        return new JsonResponse($response);
    }

    /**
     * @Route("/supprimer", name="arrivage_delete", options={"expose"=true}, methods={"GET","POST"}, condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::TRACA, Action::DELETE_ARRI}, mode=HasPermission::IN_JSON)
     */
    public function delete(Request $request,
                           EntityManagerInterface $entityManager): Response
    {
        if ($data = json_decode($request->getContent(), true)) {
            $arrivageRepository = $entityManager->getRepository(Arrivage::class);

            /** @var Arrivage $arrivage */
            $arrivage = $arrivageRepository->find($data['arrivage']);

            $canBeDeleted = ($arrivageRepository->countUnsolvedDisputesByArrivage($arrivage) == 0);

            if ($canBeDeleted) {
                foreach ($arrivage->getPacks() as $pack) {
                    foreach ($pack->getTrackingMovements() as $arrivageMvtTraca) {
                        $entityManager->remove($arrivageMvtTraca);
                    }

                    $pack->getTrackingMovements()->clear();

                    $disputes = $pack->getDisputes();
                    foreach ($disputes as $dispute) {
                        $entityManager->remove($dispute);
                    }
                    $pack->getDisputes()->clear();

                    $entityManager->remove($pack);
                }
                $arrivage->getPacks()->clear();
                $entityManager->flush();

                foreach ($arrivage->getAttachments() as $attachement) {
                    $this->attachmentService->removeAndDeleteAttachment($attachement, $arrivage);
                }
                foreach ($arrivage->getUrgences() as $urgence) {
                    $urgence->setLastArrival(null);
                }

                $entityManager->remove($arrivage);
                $entityManager->flush();
                $data = [
                    "redirect" => $this->generateUrl('arrivage_index')
                ];
            } else {
                $data = false;
            }
            return new JsonResponse($data);
        }

        throw new BadRequestHttpException();
    }

    /**
     * @Route("/lister-colis", name="arrivage_list_colis_api", options={"expose"=true}, condition="request.isXmlHttpRequest()")
     */
    public function listColisByArrivage(Request $request,
                                        EntityManagerInterface $entityManager)
    {
        if ($data = json_decode($request->getContent(), true)) {
            $arrivageRepository = $entityManager->getRepository(Arrivage::class);
            $arrivage = $arrivageRepository->find($data['id']);

            $html = $this->renderView('arrivage/modalListColisContent.html.twig', [
                'arrivage' => $arrivage
            ]);

            return new JsonResponse($html);

        } else {
            throw new BadRequestHttpException();
        }
    }

    /**
     * @Route("/csv", name="get_arrivages_csv", options={"expose"=true}, methods={"GET"})
     */
    public function exportArrivals(Request                $request,
                                   TranslationService    $translation,
                                   EntityManagerInterface $entityManager,
                                   CSVExportService       $csvService,
                                   FieldsParamService     $fieldsParamService,
                                   ArrivageService        $arrivageDataService,
                                   FreeFieldService       $freeFieldService) {
        $FORMAT = "Y-m-d H:i:s";

        $arrivageRepository = $entityManager->getRepository(Arrivage::class);
        $utilisateurRepository = $entityManager->getRepository(Utilisateur::class);
        $natureRepository = $entityManager->getRepository(Nature::class);
        $packRepository = $entityManager->getRepository(Pack::class);
        $fieldsParamRepository = $entityManager->getRepository(FieldsParam::class);
        try {
            $from = DateTime::createFromFormat($FORMAT, $request->query->get("dateMin") . " 00:00:00");
            $to = DateTime::createFromFormat($FORMAT, $request->query->get("dateMax") . " 23:59:59");
        } catch (Throwable $throwable) {
            return $this->json([
                "success" => false,
                "msg" => "Dates invalides"
            ]);
        }

        $arrivals = $arrivageRepository->iterateBetween($from, $to);
        $packsTotalWeight = $arrivageRepository->getTotalWeightByArrivals($from, $to);

        $freeFieldsConfig = $freeFieldService->createExportArrayConfig($entityManager, [CategorieCL::ARRIVAGE]);

        $packs = $packRepository->countColisByArrivageAndNature($from->format($FORMAT), $to->format($FORMAT));
        $buyersByArrival = $utilisateurRepository->getUsernameBuyersGroupByArrival();
        $natureLabels = $natureRepository->findAllLabels();
        $fieldsParam = $fieldsParamRepository->getByEntity(FieldsParam::ENTITY_CODE_ARRIVAGE);

        $baseHeader = [
            "n° arrivage",
            "poids total",
            "destinataire",
            "fournisseur",
            "transporteur",
            "chauffeur",
            "n° tracking transporteur",
            "n° commande/BL",
            "type",
            "acheteurs",
            "douane",
            "congelé",
            "statut",
            "commentaire",
            "date",
            "utilisateur",
            "numéro de projet",
            $translation->trans('acheminement.Business unit'),
        ];

        if ($fieldsParamService->isFieldRequired($fieldsParam, FieldsParam::FIELD_CODE_DROP_LOCATION_ARRIVAGE, 'displayedCreate')
            || $fieldsParamService->isFieldRequired($fieldsParam, FieldsParam::FIELD_CODE_DROP_LOCATION_ARRIVAGE, 'displayedEdit')) {
            $baseHeader[] = 'Emplacement de dépose';
        }

        $header = array_merge($baseHeader, $natureLabels, $freeFieldsConfig["freeFieldsHeader"]);
        $today = new DateTime();
        $user = $this->getUser();
        $today = $today->format($user->getDateFormat() ? $user->getDateFormat() . ' H:i:s' : "d-m-Y H:i:s");
        return $csvService->streamResponse(function($output) use ($arrivageDataService, $csvService, $fieldsParam, $freeFieldService, $freeFieldsConfig, $arrivals, $buyersByArrival, $natureLabels, $packs, $packsTotalWeight) {
            foreach($arrivals as $arrival) {
                $arrivageDataService->putArrivalLine($this->getUser(), $output, $csvService, $freeFieldsConfig, $arrival, $buyersByArrival, $natureLabels, $packs, $fieldsParam, $packsTotalWeight);
            }
        }, "export-arrivages-$today.csv", $header);
    }

    /**
     * @Route("/voir/{id}/{printColis}/{printArrivage}", name="arrivage_show", options={"expose"=true}, methods={"GET", "POST"})
     */
    public function show(EntityManagerInterface $entityManager,
                         ArrivageService        $arrivageDataService,
                         Arrivage               $arrivage,
                         bool                   $printColis = false,
                         bool                   $printArrivage = false): Response
    {
        // HasPermission annotation impossible
        if (!$this->userService->hasRightFunction(Menu::TRACA, Action::LIST_ALL)
            && !in_array($this->getUser(), $arrivage->getAcheteurs()->toArray())) {
            return $this->render('securite/access_denied.html.twig');
        }

        $statutRepository = $entityManager->getRepository(Statut::class);
        $typeRepository = $entityManager->getRepository(Type::class);
        $fieldsParamRepository = $entityManager->getRepository(FieldsParam::class);
        $arrivageRepository = $entityManager->getRepository(Arrivage::class);
        $natureRepository = $entityManager->getRepository(Nature::class);
        $acheteursNames = [];
        foreach ($arrivage->getAcheteurs() as $user) {
            $acheteursNames[] = $user->getUsername();
        }

        $fieldsParam = $fieldsParamRepository->getByEntity(FieldsParam::ENTITY_CODE_ARRIVAGE);

        $defaultDisputeStatus = $statutRepository->getIdDefaultsByCategoryName(CategorieStatut::DISPUTE_ARR);

        return $this->render("arrivage/show.html.twig", [
            'arrivage' => $arrivage,
            'disputeTypes' => $typeRepository->findByCategoryLabels([CategoryType::DISPUTE]),
            'acheteurs' => $acheteursNames,
            'disputeStatuses' => $statutRepository->findByCategorieName(CategorieStatut::DISPUTE_ARR, 'displayOrder'),
            'allColis' => $arrivage->getPacks(),
            'natures' => $natureRepository->findByAllowedForms([Nature::ARRIVAL_CODE]),
            'printColis' => $printColis,
            'printArrivage' => $printArrivage,
            'canBeDeleted' => $arrivageRepository->countUnsolvedDisputesByArrivage($arrivage) == 0,
            'fieldsParam' => $fieldsParam,
            'showDetails' => $arrivageDataService->createHeaderDetailsConfig($arrivage),
            'defaultDisputeStatusId' => $defaultDisputeStatus[0] ?? null,
        ]);
    }

    /**
     * @Route("/creer-litige", name="dispute_new", options={"expose"=true}, methods={"POST"}, condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::TRACA, Action::CREATE}, mode=HasPermission::IN_JSON)
     */
    public function newDispute(Request                $request,
                               ArrivageService        $arrivageDataService,
                               DisputeService         $disputeService,
                               EntityManagerInterface $entityManager,
                               UniqueNumberService    $uniqueNumberService,
                               TranslationService    $translation): Response
    {
        $post = $request->request;

        $statutRepository = $entityManager->getRepository(Statut::class);
        $typeRepository = $entityManager->getRepository(Type::class);
        $packRepository = $entityManager->getRepository(Pack::class);
        $usersRepository = $entityManager->getRepository(Utilisateur::class);

        $now = new DateTime('now');

        $disputeNumber = $uniqueNumberService->create($entityManager, Dispute::DISPUTE_ARRIVAL_PREFIX, Dispute::class, UniqueNumberService::DATE_COUNTER_FORMAT_DEFAULT);

        $dispute = new Dispute();
        $dispute
            ->setReporter($usersRepository->find($post->get('disputeReporter')))
            ->setStatus($statutRepository->find($post->get('disputeStatus')))
            ->setType($typeRepository->find($post->get('disputeType')))
            ->setCreationDate($now)
            ->setNumber($disputeNumber);

        $arrivage = null;
        if (!empty($packsStr = $post->get('disputePacks'))) {
            $packIds = explode(',', $packsStr);
            foreach ($packIds as $packId) {
                $pack = $packRepository->find($packId);
                if ($pack) {
                    $dispute->addPack($pack);
                    $arrivage = $pack->getArrivage();
                }
            }
        }
        if ($post->get('emergency')) {
            $dispute->setEmergencyTriggered($post->get('emergency') === 'true');
        }
        if ((!$dispute->getStatus() || !$dispute->getStatus()->isTreated()) && $arrivage) {
            $typeStatuses = $statutRepository->findStatusByType(CategorieStatut::ARRIVAGE, $arrivage->getType());
            $disputeStatus = array_reduce(
                $typeStatuses,
                function(?Statut $disputeStatus, Statut $status) {
                    return $disputeStatus
                        ?? ($status->isDispute() ? $status : null);
                },
                null
            );
            $arrivage->setStatut($disputeStatus);
        }

        /** @var Utilisateur $currentUser */
        $currentUser = $this->getUser();

        $entityManager->persist($dispute);

        $historyRecord = $disputeService->createDisputeHistoryRecord(
            $dispute,
            $currentUser,
            [
                $post->get('commentaire'),
                $dispute->getType()->getDescription()
            ]
        );

        $entityManager->persist($historyRecord);

        $this->persistAttachmentsForEntity($dispute, $this->attachmentService, $request, $entityManager);
        try {
            $entityManager->flush();
        }
        /** @noinspection PhpRedundantCatchClauseInspection */
        catch (UniqueConstraintViolationException $e) {
            return new JsonResponse([
                'success' => false,
                'msg' => $translation->trans('arrivage.Un autre litige d\'arrivage est en cours de création, veuillez réessayer').'.'
            ]);
        }

        $disputeService->sendMailToAcheteursOrDeclarant($dispute, DisputeService::CATEGORY_ARRIVAGE);

        $response = $this->getResponseReloadArrivage($entityManager, $arrivageDataService, $request->query->get('reloadArrivage')) ?? [];
        $response['success'] = true;
        $response['msg'] = 'Le litige <strong>' . $dispute->getNumber() . '</strong> a bien été créé.';
        return new JsonResponse($response);
    }

    /**
     * @Route("/supprimer-litige", name="litige_delete_arrivage", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::QUALI, Action::DELETE}, mode=HasPermission::IN_JSON)
     */
    public function deleteDispute(Request $request,
                                  EntityManagerInterface $entityManager): Response
    {
        if ($data = json_decode($request->getContent(), true)) {
            $disputeRepository = $entityManager->getRepository(Dispute::class);
            $dispute = $disputeRepository->find($data['litige']);

            $dispute->setLastHistoryRecord(null);
            //required before removing dispute or next flush will fail
            $entityManager->flush();

            foreach($dispute->getDisputeHistory() as $history) {
                $entityManager->remove($history);
            }

            $entityManager->remove($dispute);
            $entityManager->flush();
            return new JsonResponse();
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/ajouter-colis", name="arrivage_add_colis", options={"expose"=true}, methods={"GET", "POST"}, condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::TRACA, Action::EDIT}, mode=HasPermission::IN_JSON)
     */
    public function addColis(Request $request,
                             EntityManagerInterface $entityManager,
                             PackService $colisService)
    {
        if ($data = json_decode($request->getContent(), true)) {
            $arrivageRepository = $entityManager->getRepository(Arrivage::class);

            $arrivage = $arrivageRepository->find($data['arrivageId']);

            $natures = json_decode($data['colis'], true);

            /** @var Utilisateur $currentUser */
            $currentUser = $this->getUser();

            $persistedColis = $colisService->persistMultiPacks($entityManager, $arrivage, $natures, $currentUser);
            $entityManager->flush();

            return new JsonResponse([
                'success' => true,
                'packs' => array_map(function (Pack $pack) {
                    return [
                        'id' => $pack->getId(),
                        'code' => $pack->getCode()
                    ];
                }, $persistedColis),
                'arrivageId' => $arrivage->getId(),
                'arrivage' => $arrivage->getNumeroArrivage()
            ]);
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/litiges/api/{arrivage}", name="arrivageLitiges_api", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     */
    public function apiArrivageLitiges(EntityManagerInterface $entityManager,
                                       Arrivage $arrivage): Response
    {
        $disputeRepository = $entityManager->getRepository(Dispute::class);
        $disputes = $disputeRepository->findByArrivage($arrivage);
        $rows = [];
        /** @var Utilisateur $user */
        $user = $this->getUser();

        foreach ($disputes as $dispute) {
            $rows[] = [
                'firstDate' => $dispute->getCreationDate()->format($user->getDateFormat() ? $user->getDateFormat() . ' H:i' : 'd/m/Y H:i'),
                'status' => $dispute->getStatus() ? $dispute->getStatus()->getNom() : '',
                'type' => $dispute->getType() ? $dispute->getType()->getLabel() : '',
                'updateDate' => $dispute->getUpdateDate() ? $dispute->getUpdateDate()->format($user->getDateFormat() ? $user->getDateFormat() . ' H:i' : 'd/m/Y H:i') : '',
                'Actions' => $this->renderView('arrivage/datatableLitigesRow.html.twig', [
                    'arrivageId' => $arrivage->getId(),
                    'url' => [
                        'edit' => $this->generateUrl('litige_api_edit', ['id' => $dispute->getId()])
                    ],
                    'disputeId' => $dispute->getId(),
                    'disputeNumber' => $dispute->getNumber()
                ]),
                'urgence' => $dispute->getEmergencyTriggered()
            ];
        }

        $data['data'] = $rows;

        return new JsonResponse($data);
    }

    /**
     * @Route("/api-modifier-litige", name="litige_api_edit", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     */
    public function apiEditLitige(Request $request,
                                  UserService $userService,
                                  EntityManagerInterface $entityManager): Response
    {
        if ($data = json_decode($request->getContent(), true)) {
            $statutRepository = $entityManager->getRepository(Statut::class);
            $typeRepository = $entityManager->getRepository(Type::class);
            $disputeRepository = $entityManager->getRepository(Dispute::class);
            $arrivageRepository = $entityManager->getRepository(Arrivage::class);
            $attachmentRepository = $entityManager->getRepository(Attachment::class);
            $usersRepository = $entityManager->getRepository(Utilisateur::class);

            $dispute = $disputeRepository->find($data['disputeId']);

            $colisCode = [];
            foreach ($dispute->getPacks() as $pack) {
                $colisCode[] = $pack->getId();
            }

            $arrivage = $arrivageRepository->find($data['arrivageId']);

            $hasRightToTreatLitige = $userService->hasRightFunction(Menu::QUALI, Action::TREAT_DISPUTE);

            $html = $this->renderView('arrivage/modalEditLitigeContent.html.twig', [
                'dispute' => $dispute,
                'hasRightToTreatLitige' => $hasRightToTreatLitige,
                'disputeTypes' => $typeRepository->findByCategoryLabels([CategoryType::DISPUTE]),
                'disputeStatuses' => $statutRepository->findByCategorieName(CategorieStatut::DISPUTE_ARR, 'displayOrder'),
                'attachments' => $attachmentRepository->findBy(['dispute' => $dispute]),
                'colis' => $arrivage->getPacks(),
            ]);

            return new JsonResponse(['html' => $html, 'colis' => $colisCode]);
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/modifier-litige", name="litige_edit_arrivage",  options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::QUALI, Action::EDIT}, mode=HasPermission::IN_JSON)
     */
    public function editLitige(Request                $request,
                               ArrivageService        $arrivageDataService,
                               EntityManagerInterface $entityManager,
                               DisputeService         $disputeService,
                               Twig_Environment       $templating): Response
    {
        $post = $request->request;

        $statutRepository = $entityManager->getRepository(Statut::class);
        $typeRepository = $entityManager->getRepository(Type::class);
        $packRepository = $entityManager->getRepository(Pack::class);
        $disputeRepository = $entityManager->getRepository(Dispute::class);
        $utilisateurRepository = $entityManager->getRepository(Utilisateur::class);

        $dispute = $disputeRepository->find($post->get('id'));
        $typeBefore = $dispute->getType()->getId();
        $typeAfter = (int)$post->get('disputeType');
        $statutBefore = $dispute->getStatus()->getId();
        $statutAfter = (int)$post->get('disputeStatus');
        $dispute
            ->setReporter($utilisateurRepository->find($post->get('disputeReporter')))
            ->setUpdateDate(new DateTime('now'));
        $this->templating = $templating;
        $newStatus = $statutRepository->find($statutAfter);
        $hasRightToTreatLitige = $this->userService->hasRightFunction(Menu::QUALI, Action::TREAT_DISPUTE);

        /** @var Utilisateur $currentUser */
        $currentUser = $this->getUser();

        if ($hasRightToTreatLitige || !$newStatus->isTreated()) {
            $dispute->setStatus($newStatus);
        }

        if ($hasRightToTreatLitige) {
            $dispute->setType($typeRepository->find($typeAfter));
        }

        if (!empty($newColis = $post->get('colis'))) {
            // on détache les colis existants...
            $existingPacks = $dispute->getPacks();
            foreach ($existingPacks as $existingPack) {
                $dispute->removePack($existingPack);
            }
            // ... et on ajoute ceux sélectionnés
            $listColis = explode(',', $newColis);
            foreach ($listColis as $colisId) {
                $dispute->addPack($packRepository->find($colisId));
            }
        }

        $entityManager->flush();

        if ($post->get('emergency')) {
            $dispute->setEmergencyTriggered($post->get('emergency') === 'true');
        }

        $comment = trim($post->get('commentaire', ''));
        $typeDescription = $dispute->getType()->getDescription();
        if ($statutBefore !== $statutAfter
            || $typeBefore !== $typeAfter
            || $comment) {

            $historyRecord = $disputeService->createDisputeHistoryRecord(
                $dispute,
                $currentUser,
                [$comment, $typeDescription]
            );

            $entityManager->persist($historyRecord);
            $entityManager->flush();
        }

        $listAttachmentIdToKeep = $post->all('files') ?? [];

        $attachments = $dispute->getAttachments()->toArray();
        foreach ($attachments as $attachment) {
            /** @var Attachment $attachment */
            if (!in_array($attachment->getId(), $listAttachmentIdToKeep)) {
                $this->attachmentService->removeAndDeleteAttachment($attachment, $dispute);
            }
        }

        $this->persistAttachmentsForEntity($dispute, $this->attachmentService, $request, $entityManager);
        $entityManager->flush();
        $isStatutChange = ($statutBefore !== $statutAfter);
        if ($isStatutChange) {
            $disputeService->sendMailToAcheteursOrDeclarant($dispute, DisputeService::CATEGORY_ARRIVAGE, true);
        }

        $response = $this->getResponseReloadArrivage($entityManager, $arrivageDataService, $request->query->get('reloadArrivage')) ?? [];

        $response['success'] = true;

        return new JsonResponse($response);
    }

    /**
     * @Route("/colis/api/{arrivage}", name="colis_api", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     */
    public function apiColis(Arrivage $arrivage): Response
    {
        $packs = $arrivage->getPacks()->toArray();
        /** @var Utilisateur $user */
        $user = $this->getUser();
        $rows = [];
        /** @var Pack $pack */
        foreach ($packs as $pack) {
            $mouvement = $pack->getLastTracking();
            $rows[] = [
                'nature' => $pack->getNature() ? $pack->getNature()->getLabel() : '',
                'code' => $pack->getCode(),
                'lastMvtDate' => $mouvement ? ($mouvement->getDatetime() ? $mouvement->getDatetime()->format($user->getDateFormat() ? $user->getDateFormat() . ' H:i' : 'd/m/Y H:i') : '') : '',
                'lastLocation' => $mouvement ? ($mouvement->getEmplacement() ? $mouvement->getEmplacement()->getLabel() : '') : '',
                'operator' => $mouvement ? ($mouvement->getOperateur() ? $mouvement->getOperateur()->getUsername() : '') : '',
                'actions' => $this->renderView('arrivage/datatableColisRow.html.twig', [
                    'arrivageId' => $arrivage->getId(),
                    'colisId' => $pack->getId()
                ])
            ];
        }
        $data['data'] = $rows;

        return new JsonResponse($data);
    }

    /**
     * @Route("/{arrivage}/colis/{colis}/etiquette", name="print_arrivage_single_colis_bar_codes", options={"expose"=true}, methods="GET")
     */
    public function printArrivageColisBarCodes(Arrivage $arrivage,
                                               Request $request,
                                               EntityManagerInterface $entityManager,
                                               PDFGeneratorService $PDFGeneratorService,
                                               Pack $colis = null,
                                               array $packIdsFilter = []): Response
    {
        $barcodeConfigs = [];
        $settingRepository = $entityManager->getRepository(Setting::class);
        $usernameParamIsDefined = $settingRepository->getOneParamByLabel(Setting::INCLUDE_RECIPIENT_IN_LABEL);
        $dropzoneParamIsDefined = $settingRepository->getOneParamByLabel(Setting::INCLUDE_DZ_LOCATION_IN_LABEL);
        $typeArrivalParamIsDefined = $settingRepository->getOneParamByLabel(Setting::INCLUDE_ARRIVAL_TYPE_IN_LABEL);
        $packCountParamIsDefined = $settingRepository->getOneParamByLabel(Setting::INCLUDE_PACK_COUNT_IN_LABEL);
        $commandAndProjectNumberIsDefined = $settingRepository->getOneParamByLabel(Setting::INCLUDE_COMMAND_AND_PROJECT_NUMBER_IN_LABEL);
        $printTwiceIfCustoms = $settingRepository->getOneParamByLabel(Setting::PRINT_TWICE_CUSTOMS);
        $businessUnitParam = $settingRepository->getOneParamByLabel(Setting::INCLUDE_BUSINESS_UNIT_IN_LABEL);


        $firstCustomIconInclude = $settingRepository->getOneParamByLabel(Setting::INCLUDE_CUSTOMS_IN_LABEL);
        $firstCustomIconName = $settingRepository->getOneParamByLabel(Setting::CUSTOM_ICON);
        $firstCustomIconText = $settingRepository->getOneParamByLabel(Setting::CUSTOM_TEXT_LABEL);

        $firstCustomIconConfig = ($firstCustomIconInclude && $firstCustomIconName && $firstCustomIconText)
            ? [
                'icon' => $firstCustomIconName,
                'text' => $firstCustomIconText
            ]
            : null;

        $firstCustomIconInclude = $settingRepository->getOneParamByLabel(Setting::INCLUDE_EMERGENCY_IN_LABEL);
        $secondCustomIconName = $settingRepository->getOneParamByLabel(Setting::EMERGENCY_ICON);;
        $secondCustomIconText = $settingRepository->getOneParamByLabel(Setting::EMERGENCY_TEXT_LABEL);

        $secondCustomIconConfig = ($firstCustomIconInclude && $secondCustomIconName && $secondCustomIconText)
            ? [
                'icon' => $secondCustomIconName,
                'text' => $secondCustomIconText
            ]
            : null;

        if (!isset($colis)) {
            $printColis = $request->query->getBoolean('printColis');
            $printArrivage = $request->query->getBoolean('printArrivage');

            if ($printColis) {
                $barcodeConfigs = $this->getBarcodeConfigPrintAllColis(
                    $arrivage,
                    $typeArrivalParamIsDefined,
                    $usernameParamIsDefined,
                    $dropzoneParamIsDefined,
                    $packCountParamIsDefined,
                    $commandAndProjectNumberIsDefined,
                    $firstCustomIconConfig,
                    $secondCustomIconConfig,
                    $packIdsFilter,
                    $businessUnitParam
                );
            }

            if ($printArrivage) {
                $barcodeConfigs[] = [
                    'code' => $arrivage->getNumeroArrivage()
                ];
            }
        } else {
            if (!$colis->getArrivage() || $colis->getArrivage()->getId() !== $arrivage->getId()) {
                throw new BadRequestHttpException();
            }

            $total = $arrivage->getPacks()->count();
            $position = $arrivage->getPacks()->indexOf($colis) + 1;

            $barcodeConfigs[] = $this->getBarcodeColisConfig(
                $colis,
                $arrivage->getDestinataire(),
                "$position/$total",
                $typeArrivalParamIsDefined,
                $usernameParamIsDefined,
                $dropzoneParamIsDefined,
                $packCountParamIsDefined,
                $commandAndProjectNumberIsDefined,
                $firstCustomIconConfig,
                $secondCustomIconConfig,
                $businessUnitParam
            );
        }

        $printTwice = ($printTwiceIfCustoms && $arrivage->getCustoms());

        if($printTwice) {
            $barcodeConfigs = Stream::from($barcodeConfigs, $barcodeConfigs)
                ->toArray();
        }

        if (empty($barcodeConfigs)) {
            throw new BadRequestHttpException('Vous devez imprimer au moins une étiquette');
        }

        $fileName = $PDFGeneratorService->getBarcodeFileName($barcodeConfigs, 'arrivage');

        return new PdfResponse(
            $PDFGeneratorService->generatePDFBarCodes($fileName, $barcodeConfigs),
            $fileName
        );
    }

    /**
     * @Route("/{arrivage}/etiquettes", name="print_arrivage_bar_codes", options={"expose"=true}, methods="GET")
     */
    public function printArrivageAlias(Arrivage $arrivage,
                                       Request $request,
                                       EntityManagerInterface $entityManager,
                                       PDFGeneratorService $PDFGeneratorService)
    {
        $packIdsFilter = $request->query->all('packs') ?: [];
        return $this->printArrivageColisBarCodes($arrivage, $request, $entityManager, $PDFGeneratorService, null, $packIdsFilter);
    }

    private function getBarcodeConfigPrintAllColis(Arrivage $arrivage,
                                                   ?bool $typeArrivalParamIsDefined = false,
                                                   ?bool $usernameParamIsDefined = false,
                                                   ?bool $dropzoneParamIsDefined = false,
                                                   ?bool $packCountParamIsDefined = false,
                                                   ?bool $commandAndProjectNumberIsDefined = false,
                                                   ?array $firstCustomIconConfig = null,
                                                   ?array $secondCustomIconConfig = null,
                                                   array $packIdsFilter = [],
                                                   ?bool $businessUnitParam = false ): array {
        $total = $arrivage->getPacks()->count();
        $packs = [];

        foreach($arrivage->getPacks() as $index => $pack) {
            $position = $index + 1;
            if (empty($packIdsFilter) || in_array($pack->getId(), $packIdsFilter)) {
                $packs[] = $this->getBarcodeColisConfig(
                    $pack,
                    $arrivage->getDestinataire(),
                    "$position/$total",
                    $typeArrivalParamIsDefined,
                    $usernameParamIsDefined,
                    $dropzoneParamIsDefined,
                    $packCountParamIsDefined,
                    $commandAndProjectNumberIsDefined,
                    $firstCustomIconConfig,
                    $secondCustomIconConfig,
                    $businessUnitParam
                );
            }
        }

        return $packs;
    }

    private function getBarcodeColisConfig(Pack $colis,
                                           ?Utilisateur $destinataire,
                                           ?string $packIndex = '',
                                           ?bool $typeArrivalParamIsDefined,
                                           ?bool $usernameParamIsDefined = false,
                                           ?bool $dropzoneParamIsDefined = false,
                                           ?bool $packCountParamIsDefined = false,
                                           ?bool $commandAndProjectNumberIsDefined = false,
                                           ?array $firstCustomIconConfig = null,
                                           ?array $secondCustomIconConfig = null,
                                           ?bool $businessUnitParam = false)
    {

        $arrival = $colis->getArrivage();

        $businessUnit = $businessUnitParam
            ? $arrival->getBusinessUnit()
            : '';

        $arrivalType = $typeArrivalParamIsDefined
            ? $arrival->getType()->getLabel()
            : '';

        $recipientUsername = ($usernameParamIsDefined && $destinataire)
            ? $destinataire->getUsername()
            : '';

        $dropZoneLabel = ($dropzoneParamIsDefined && $destinataire)
            ? ($destinataire->getDropzone()
                ? $destinataire->getDropzone()->getLabel()
                : '')
            : '';

        $arrivalCommand = [];
        $arrivalLine = "";
        $i = 0;
        foreach($arrival->getNumeroCommandeList() as $command) {
            $arrivalLine .= $command;

            if(++$i % 4 == 0) {
                $arrivalCommand[] = $arrivalLine;
                $arrivalLine = "";
            } else {
                $arrivalLine .= " ";
            }
        }

        if(!empty($arrivalLine)) {
            $arrivalCommand[] = $arrivalLine;
        }

        $arrivalProjectNumber = $arrival
            ? ($arrival->getProjectNumber() ?? '')
            : '';

        $packLabel = ($packCountParamIsDefined ? $packIndex : '');

        $usernameSeparator = ($recipientUsername && $dropZoneLabel) ? ' / ' : '';

        $labels = [$arrivalType];

        $labels[] = $recipientUsername . $usernameSeparator . $dropZoneLabel;

        if ($commandAndProjectNumberIsDefined) {
            if ($arrivalCommand && $arrivalProjectNumber) {
                if(count($arrivalCommand) > 1) {
                    $labels = array_merge($labels, $arrivalCommand);
                    $labels[] = $arrivalProjectNumber;
                } else if(count($arrivalCommand) == 1) {
                    $labels[] = $arrivalCommand[0] . ' / ' . $arrivalProjectNumber;
                }
            } else if ($arrivalCommand) {
                $labels = array_merge($labels, $arrivalCommand);
            } else if ($arrivalProjectNumber) {
                $labels[] = $arrivalProjectNumber;
            }
        }

        if($businessUnitParam) {
            $labels[] = $businessUnit;
        }

        if ($packLabel) {
            $labels[] = $packLabel;
        }

        return [
            'code' => $colis->getCode(),
            'labels' => $labels,
            'firstCustomIcon' => $arrival->getCustoms() ? $firstCustomIconConfig : null,
            'secondCustomIcon' => $arrival->getIsUrgent() ? $secondCustomIconConfig : null
        ];
    }

    private function getResponseReloadArrivage(EntityManagerInterface $entityManager,
                                               ArrivageService        $arrivageDataService,
                                                                      $reloadArrivageId): ?array
    {
        $response = null;
        if (isset($reloadArrivageId)) {
            $arrivageRepository = $entityManager->getRepository(Arrivage::class);
            $arrivageToReload = $arrivageRepository->find($reloadArrivageId);
            if ($arrivageToReload) {
                $response = [
                    'entete' => $this->renderView('arrivage/arrivage-show-header.html.twig', [
                        'arrivage' => $arrivageToReload,
                        'canBeDeleted' => $arrivageRepository->countUnsolvedDisputesByArrivage($arrivageToReload) == 0,
                        'showDetails' => $arrivageDataService->createHeaderDetailsConfig($arrivageToReload)
                    ]),
                ];
            }
        }

        return $response;
    }

    private function persistAttachmentsForEntity($entity, AttachmentService $attachmentService, Request $request, EntityManagerInterface $entityManager)
    {
        $attachments = $attachmentService->createAttachements($request->files);
        foreach ($attachments as $attachment) {
            $entityManager->persist($attachment);
            $entity->addAttachment($attachment);
        }
        $entityManager->persist($entity);
    }

    /**
     * @Route("/colonne-visible", name="save_column_visible_for_arrivage", options={"expose"=true}, methods="POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::TRACA, Action::DISPLAY_ARRI}, mode=HasPermission::IN_JSON)
     */
    public function saveColumnVisible(Request $request,
                                      EntityManagerInterface $entityManager,
                                      VisibleColumnService $visibleColumnService, TranslationService $translation): Response
    {
        $data = json_decode($request->getContent(), true);

        $fields = array_keys($data);
        /** @var Utilisateur $user */
        $user = $this->getUser();

        $visibleColumnService->setVisibleColumns('arrival', $fields, $user);
        $entityManager->flush();

        return $this->json([
            'success' => true,
            'msg' => $translation->translate('Général', null, 'Zone liste', 'Vos préférences de colonnes à afficher ont bien été sauvegardées')
        ]);
    }

    /**
     * @Route("/api-columns", name="arrival_api_columns", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::TRACA, Action::DISPLAY_ARRI}, mode=HasPermission::IN_JSON)
     */
    public function apiColumns(ArrivageService        $arrivageDataService,
                               EntityManagerInterface $entityManager,
                               Request $request): Response
    {
        /** @var Utilisateur $currentUser */
        $currentUser = $this->getUser();
        $dispatchMode = $request->query->getBoolean('dispatchMode');

        $columns = $arrivageDataService->getColumnVisibleConfig($entityManager, $currentUser, $dispatchMode);
        return new JsonResponse($columns);
    }

    /**
     * @Route("/new-dispute-template", name="new_dispute_template", options={"expose"=true}, methods="GET", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::QUALI, Action::CREATE}, mode=HasPermission::IN_JSON)
     */
    public function newDisputeTemplate(Request $request, EntityManagerInterface $manager): Response {
        $statusRepository = $manager->getRepository(Statut::class);
        $typeRepository = $manager->getRepository(Type::class);

        $arrival = $manager->find(Arrivage::class, $request->query->get('id'));
        $disputeStatuses = $statusRepository->findByCategorieName(CategorieStatut::DISPUTE_ARR, 'displayOrder');
        $defaultDisputeStatus = $statusRepository->getIdDefaultsByCategoryName(CategorieStatut::DISPUTE_ARR);
        $disputeTypes = $typeRepository->findByCategoryLabels([CategoryType::DISPUTE]);
        $fixedFields = $manager->getRepository(FieldsParam::class)->getByEntity(FieldsParam::ENTITY_CODE_ARRIVAGE);

        $buyers = Stream::from($arrival->getAcheteurs())->map(fn(Utilisateur $buyer) => $buyer->getUsername())->join(',');
        $orderNumers = Stream::from($arrival->getNumeroCommandeList())->join(',');

        return $this->json([
            'success' => true,
            'content' => $this->renderView('arrivage/modalNewDisputeContent.html.twig', [
                'arrivage' => $arrival,
                'disputeTypes' => $disputeTypes,
                'disputeStatuses' => $disputeStatuses,
                'buyers' => $buyers,
                'orderNumers' => $orderNumers,
                'defaultDisputeStatusId' => $defaultDisputeStatus[0] ?? null,
                'packs' => $arrival->getPacks(),
                'fieldsParam' => $fixedFields
            ])
        ]);
    }
}
