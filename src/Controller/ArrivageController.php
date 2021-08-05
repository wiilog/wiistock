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
use App\Entity\Litige;
use App\Entity\LitigeHistoric;
use App\Entity\Menu;
use App\Entity\Nature;
use App\Entity\ParametrageGlobal;
use App\Entity\Attachment;
use App\Entity\Statut;
use App\Entity\Transporteur;
use App\Entity\Type;
use App\Entity\Utilisateur;
use WiiCommon\Helper\Stream;
use App\Service\ArrivageDataService;
use App\Service\AttachmentService;
use App\Service\DispatchService;
use App\Service\FieldsParamService;
use App\Service\TrackingMovementService;
use App\Service\PackService;
use App\Service\CSVExportService;
use App\Service\DashboardService;
use App\Service\GlobalParamService;
use App\Service\LitigeService;
use App\Service\PDFGeneratorService;
use App\Service\SpecificService;
use App\Service\UniqueNumberService;
use App\Service\UrgenceService;
use App\Service\UserService;
use App\Service\MailerService;
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
use Symfony\Contracts\Translation\TranslatorInterface;
use Throwable;
use Twig\Environment as Twig_Environment;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Entity;

/**
 * @Route("/arrivage")
 */
class ArrivageController extends AbstractController
{
    /**
     * @var UserService
     */
    private $userService;

    /**
     * @var GlobalParamService
     */
    private $globalParamService;

    /**
     * @var MailerService
     */
    private $mailerService;

    /**
     * @var SpecificService
     */
    private $specificService;

    /**
     * @var AttachmentService
     */
    private $attachmentService;

    /**
     * @var ArrivageDataService
     */
    private $arrivageDataService;

    /**
     * @var DashboardService
     */
    private $dashboardService;

    public function __construct(ArrivageDataService $arrivageDataService,
                                DashboardService $dashboardService,
                                AttachmentService $attachmentService,
                                SpecificService $specificService,
                                MailerService $mailerService,
                                GlobalParamService $globalParamService,
                                UserService $userService)
    {
        $this->dashboardService = $dashboardService;
        $this->specificService = $specificService;
        $this->globalParamService = $globalParamService;
        $this->userService = $userService;
        $this->mailerService = $mailerService;
        $this->attachmentService = $attachmentService;
        $this->arrivageDataService = $arrivageDataService;
    }

    /**
     * @Route("/", name="arrivage_index")
     * @HasPermission({Menu::TRACA, Action::DISPLAY_ARRI})
     */
    public function index(EntityManagerInterface $entityManager,
                          ArrivageDataService $arrivageDataService)
    {
        $fieldsParamRepository = $entityManager->getRepository(FieldsParam::class);
        $parametrageGlobalRepository = $entityManager->getRepository(ParametrageGlobal::class);
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
        $paramGlobalRedirectAfterNewArrivage = $parametrageGlobalRepository->findOneBy(['label' => ParametrageGlobal::REDIRECT_AFTER_NEW_ARRIVAL]);

        $statuses = $statutRepository->findStatusByType(CategorieStatut::ARRIVAGE);
        $defaultLocation = $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::MVT_DEPOSE_DESTINATION);
        $defaultLocation = $defaultLocation ? $emplacementRepository->find($defaultLocation) : null;
        return $this->render('arrivage/index.html.twig', [
            'carriers' => $transporteurRepository->findAllSorted(),
            'chauffeurs' => $chauffeurRepository->findAllSorted(),
            'users' => $utilisateurRepository->findBy(['status' => true], ['username'=> 'ASC']),
            'fournisseurs' => $fournisseurRepository->findBy([], ['nom' => 'ASC']),
            'typesLitige' => $typeRepository->findByCategoryLabels([CategoryType::LITIGE]),
            'natures' => $natureRepository->findBy([
                'displayed' => true
            ]),
            'statuts' => $statuses,
            'typesArrival' => $typeRepository->findByCategoryLabels([CategoryType::ARRIVAGE]),
            'fieldsParam' => $fieldsParam,
            'redirect' => $paramGlobalRedirectAfterNewArrivage ? $paramGlobalRedirectAfterNewArrivage->getValue() : true,
            'champsLibres' => $champLibreRepository->findByCategoryTypeLabels([CategoryType::ARRIVAGE]),
            'pageLengthForArrivage' => $user->getPageLengthForArrivage() ?: 10,
            'autoPrint' => $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::AUTO_PRINT_COLIS),
            'fields' => $fields,
            'defaultLocation' => $defaultLocation,
            'businessUnits' => $fieldsParamRepository->getElements(FieldsParam::ENTITY_CODE_ARRIVAGE, FieldsParam::FIELD_CODE_BUSINESS_UNIT),
            'defaultStatuses' => $statutRepository->getIdDefaultsByCategoryName(CategorieStatut::ARRIVAGE),
            'modalNewConfig' => [
                'defaultStatuses' => $statutRepository->getIdDefaultsByCategoryName(CategorieStatut::ARRIVAGE),
                'statuses' => $statuses,
            ]
        ]);
    }

    /**
     * @Route("/api", name="arrivage_api", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::TRACA, Action::DISPLAY_ARRI}, mode=HasPermission::IN_JSON)
     */
    public function api(Request $request): Response
    {
        if($this->userService->hasRightFunction(Menu::TRACA, Action::LIST_ALL) || !$this->getUser()) {
            $userId = null;
        } else {
            $userId = $this->getUser()->getId();
        }

        return $this->json($this->arrivageDataService->getDataForDatatable($request->request, $userId));
    }

    /**
     * @Route("/creer", name="arrivage_new", options={"expose"=true}, methods={"GET", "POST"}, condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::TRACA, Action::CREATE}, mode=HasPermission::IN_JSON)
     */
    public function new(Request $request,
                        EntityManagerInterface $entityManager,
                        AttachmentService $attachmentService,
                        ArrivageDataService $arrivageDataService,
                        FreeFieldService $champLibreService,
                        PackService $colisService,
                        TranslatorInterface $translator): Response
    {
        $data = $request->request->all();
        $parametrageGlobalRepository = $entityManager->getRepository(ParametrageGlobal::class);
        $arrivageRepository = $entityManager->getRepository(Arrivage::class);
        $statutRepository = $entityManager->getRepository(Statut::class);
        $fournisseurRepository = $entityManager->getRepository(Fournisseur::class);
        $transporteurRepository = $entityManager->getRepository(Transporteur::class);
        $emplacementRepository = $entityManager->getRepository(Emplacement::class);
        $chauffeurRepository = $entityManager->getRepository(Chauffeur::class);
        $userRepository = $entityManager->getRepository(Utilisateur::class);
        $typeRepository = $entityManager->getRepository(Type::class);
        $sendMail = $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::SEND_MAIL_AFTER_NEW_ARRIVAL);

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
                'msg' => "Veuillez renseigner le statut."
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
                'msg' => $translator->trans('arrivage.Un autre arrivage est en cours de création, veuillez réessayer') . '.'
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
                'msg' => "Veuillez renseigner au moins un colis.<br>"
            ]);
        }

        $champLibreService->manageFreeFields($arrivage, $data, $entityManager);

        $alertConfigs = $arrivageDataService->processEmergenciesOnArrival($arrivage);

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
        $paramGlobalRedirectAfterNewArrivage = $parametrageGlobalRepository->findOneBy(['label' => ParametrageGlobal::REDIRECT_AFTER_NEW_ARRIVAL]);

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
     * @Route("/{arrival}/urgent", name="patch_arrivage_urgent", options={"expose"=true}, methods="PATCH", condition="request.isXmlHttpRequest() && '%client%' == constant('\\App\\Service\\SpecificService::CLIENT_SAFRAN_ED')")
     * @Entity("arrival", expr="repository.find(arrival) ?: repository.findOneBy({'numeroArrivage': arrival})")
     */
    public function patchUrgentArrival(Arrivage $arrival,
                                       Request $request,
                                       ArrivageDataService $arrivageDataService,
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
    public function postArrivalTrackingMovements(Arrivage $arrival,
                                                 ArrivageDataService $arrivageDataService,
                                                 TrackingMovementService $trackingMovementService,
                                                 EntityManagerInterface $entityManager): Response
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
    public function edit(Request $request,
                         ArrivageDataService $arrivageDataService,
                         FreeFieldService $champLibreService,
                         EntityManagerInterface $entityManager): Response
    {
        $statutRepository = $entityManager->getRepository(Statut::class);
        $fournisseurRepository = $entityManager->getRepository(Fournisseur::class);
        $parametrageGlobalRepository = $entityManager->getRepository(ParametrageGlobal::class);
        $arrivageRepository = $entityManager->getRepository(Arrivage::class);
        $chauffeurRepository = $entityManager->getRepository(Chauffeur::class);
        $utilisateurRepository = $entityManager->getRepository(Utilisateur::class);
        $emplacementRepository = $entityManager->getRepository(Emplacement::class);
        $typeRepository = $entityManager->getRepository(Type::class);
        $transporteurRepository = $entityManager->getRepository(Transporteur::class);

        $post = $request->request;
        $isSEDCurrentClient = $this->specificService->isCurrentClientNameFunction(SpecificService::CLIENT_SAFRAN_ED);

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
        $dropLocation = $dropLocationId ? $emplacementRepository->find($dropLocationId) : null;

        $sendMail = $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::SEND_MAIL_AFTER_NEW_ARRIVAL);

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

        $listAttachmentIdToKeep = $post->get('files') ?? [];

        $attachments = $arrivage->getAttachments()->toArray();
        foreach ($attachments as $attachment) {
            /** @var Attachment $attachment */
            if (!in_array($attachment->getId(), $listAttachmentIdToKeep)) {
                $this->attachmentService->removeAndDeleteAttachment($attachment, $arrivage);
            }
        }

        $this->persistAttachmentsForEntity($arrivage, $this->attachmentService, $request, $entityManager);

        $champLibreService->manageFreeFields($arrivage, $post->all(), $entityManager);
        $entityManager->flush();
        $response = [
            'success' => true,
            'entete' => $this->renderView('arrivage/arrivage-show-header.html.twig', [
                'arrivage' => $arrivage,
                'canBeDeleted' => $arrivageRepository->countLitigesUnsolvedByArrivage($arrivage) == 0,
                'showDetails' => $arrivageDataService->createHeaderDetailsConfig($arrivage)
            ]),
            'alertConfigs' => [
                $arrivageDataService->createArrivalAlertConfig($arrivage, $isSEDCurrentClient)
            ]
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

            $canBeDeleted = ($arrivageRepository->countLitigesUnsolvedByArrivage($arrivage) == 0);

            if ($canBeDeleted) {
                foreach ($arrivage->getPacks() as $pack) {
                    foreach ($pack->getTrackingMovements() as $arrivageMvtTraca) {
                        $entityManager->remove($arrivageMvtTraca);
                    }

                    $pack->getTrackingMovements()->clear();

                    $litiges = $pack->getLitiges();
                    foreach ($litiges as $litige) {
                        $entityManager->remove($litige);
                    }
                    $pack->getLitiges()->clear();

                    $entityManager->remove($pack);
                }
                $arrivage->getPacks()->clear();

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
     * @Route("/ajoute-commentaire", name="add_comment", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     */
    public function addComment(Request $request,
                               EntityManagerInterface $entityManager): Response
    {
        if ($data = json_decode($request->getContent(), true)) {
            $response = '';

            // spécifique SAFRAN CERAMICS ajout de commentaire
            $isSafran = $this->specificService->isCurrentClientNameFunction(SpecificService::CLIENT_SAFRAN_CS);
            if ($isSafran) {
                $typeRepository = $entityManager->getRepository(Type::class);
                $type = $typeRepository->find($data['typeLitigeId']);
                $response = $type->getDescription();
            }

            return new JsonResponse($response);
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
    public function exportArrivals(Request $request,
                                   EntityManagerInterface $entityManager,
                                   CSVExportService $csvService,
                                   FieldsParamService $fieldsParamService,
                                   ArrivageDataService $arrivageDataService,
                                   FreeFieldService $freeFieldService) {
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

        $ffConfig = $freeFieldService->createExportArrayConfig($entityManager, [CategorieCL::ARRIVAGE]);

        $packs = $packRepository->countColisByArrivageAndNature($from->format($FORMAT), $to->format($FORMAT));
        $buyersByArrival = $utilisateurRepository->getUsernameBuyersGroupByArrival();
        $natureLabels = $natureRepository->findAllLabels();
        $fieldsParam = $fieldsParamRepository->getByEntity(FieldsParam::ENTITY_CODE_ARRIVAGE);

        $baseHeader = [
            "n° arrivage",
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
            "business unit",

        ];

        if ($fieldsParamService->isFieldRequired($fieldsParam, FieldsParam::FIELD_CODE_DROP_LOCATION_ARRIVAGE, 'displayedFormsCreate')
            || $fieldsParamService->isFieldRequired($fieldsParam, FieldsParam::FIELD_CODE_DROP_LOCATION_ARRIVAGE, 'displayedFormsEdit')) {
            $baseHeader[] = 'Emplacement de dépose';
        }

        $header = array_merge($baseHeader, $natureLabels, $ffConfig["freeFieldsHeader"]);
        $today = new DateTime();
        $today = $today->format("d-m-Y H:i:s");
        return $csvService->streamResponse(function($output) use ($arrivageDataService, $csvService, $fieldsParam, $freeFieldService, $ffConfig, $arrivals, $buyersByArrival, $natureLabels, $packs) {
            foreach($arrivals as $arrival) {
                $arrivageDataService->putArrivalLine($output, $csvService, $freeFieldService, $ffConfig, $arrival, $buyersByArrival, $natureLabels, $packs, $fieldsParam);
            }
        }, "export-arrivages-$today.csv", $header);
    }

    /**
     * @Route("/voir/{id}/{printColis}/{printArrivage}", name="arrivage_show", options={"expose"=true}, methods={"GET", "POST"})
     */
    public function show(EntityManagerInterface $entityManager,
                         ArrivageDataService $arrivageDataService,
                         Arrivage $arrivage,
                         DispatchService  $dispatchService,
                         bool $printColis = false,
                         bool $printArrivage = false): Response
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
        $usersRepository = $entityManager->getRepository(Utilisateur::class);
        $champLibreRepository = $entityManager->getRepository(FreeField::class);
        $parametrageGlobalRepository = $entityManager->getRepository(ParametrageGlobal::class);
        $acheteursNames = [];
        foreach ($arrivage->getAcheteurs() as $user) {
            $acheteursNames[] = $user->getUsername();
        }

        $fieldsParam = $fieldsParamRepository->getByEntity(FieldsParam::ENTITY_CODE_ARRIVAGE);
        $types = $typeRepository->findByCategoryLabels([CategoryType::DEMANDE_DISPATCH]);

        $defaultDisputeStatus = $statutRepository->getIdDefaultsByCategoryName(CategorieStatut::LITIGE_ARR);

        return $this->render("arrivage/show.html.twig", [
            'arrivage' => $arrivage,
            'typesLitige' => $typeRepository->findByCategoryLabels([CategoryType::LITIGE]),
            'acheteurs' => $acheteursNames,
            'statusLitige' => $statutRepository->findByCategorieName(CategorieStatut::LITIGE_ARR, 'displayOrder'),
            'allColis' => $arrivage->getPacks(),
            'natures' => $natureRepository->findBy([
                'displayed' => true
            ]),
            'printColis' => $printColis,
            'printArrivage' => $printArrivage,
            'utilisateurs' => $usersRepository->getIdAndLibelleBySearch(''),
            'canBeDeleted' => $arrivageRepository->countLitigesUnsolvedByArrivage($arrivage) == 0,
            'fieldsParam' => $fieldsParam,
            'showDetails' => $arrivageDataService->createHeaderDetailsConfig($arrivage),
            'defaultDisputeStatusId' => $defaultDisputeStatus[0] ?? null,
            'modalNewDispatchConfig' => $dispatchService->getNewDispatchConfig($statutRepository,
                $champLibreRepository, $fieldsParamRepository, $parametrageGlobalRepository, $types, $arrivage)
        ]);
    }

    /**
     * @Route("/creer-litige", name="litige_new", options={"expose"=true}, methods={"POST"}, condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::TRACA, Action::CREATE}, mode=HasPermission::IN_JSON)
     */
    public function newLitige(Request $request,
                              ArrivageDataService $arrivageDataService,
                              LitigeService $litigeService,
                              EntityManagerInterface $entityManager,
                              UniqueNumberService $uniqueNumberService,
                              TranslatorInterface $translator): Response
    {
        $post = $request->request;

        $statutRepository = $entityManager->getRepository(Statut::class);
        $typeRepository = $entityManager->getRepository(Type::class);
        $packRepository = $entityManager->getRepository(Pack::class);
        $usersRepository = $entityManager->getRepository(Utilisateur::class);

        $now = new DateTime('now');

        $disputeNumber = $uniqueNumberService->createUniqueNumber($entityManager, Litige::DISPUTE_ARRIVAL_PREFIX, Litige::class, UniqueNumberService::DATE_COUNTER_FORMAT_DEFAULT);

        $litige = new Litige();
        $litige
            ->setDeclarant($usersRepository->find($post->get('declarantLitige')))
            ->setStatus($statutRepository->find($post->get('statutLitige')))
            ->setType($typeRepository->find($post->get('typeLitige')))
            ->setCreationDate($now)
            ->setNumeroLitige($disputeNumber);

        $arrivage = null;
        if (!empty($colis = $post->get('colisLitige'))) {
            $listColisId = explode(',', $colis);
            foreach ($listColisId as $colisId) {
                $colis = $packRepository->find($colisId);
                $litige->addPack($colis);
                $arrivage = $colis->getArrivage();
            }
        }
        if ($post->get('emergency')) {
            $litige->setEmergencyTriggered($post->get('emergency') === 'true');
        }
        if ((!$litige->getStatus() || !$litige->getStatus()->isTreated()) && $arrivage) {
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
        $typeDescription = $litige->getType()->getDescription();
        $typeLabel = $litige->getType()->getLabel();
        $statutNom = $litige->getStatus()->getNom();

        $trimmedTypeDescription = trim($typeDescription);
        $userComment = trim($post->get('commentaire'));
        $nl = !empty($userComment) ? "\n" : '';
        $trimmedTypeDescription = !empty($trimmedTypeDescription) ? "\n" . $trimmedTypeDescription : '';
        $commentaire = $userComment . $nl . 'Type à la création -> ' . $typeLabel . $trimmedTypeDescription . "\n" . 'Statut à la création -> ' . $statutNom;

        /** @var Utilisateur $currentUser */
        $currentUser = $this->getUser();

        if (!empty($commentaire)) {
            $histo = new LitigeHistoric();
            $histo
                ->setDate(new DateTime('now'))
                ->setComment($commentaire)
                ->setLitige($litige)
                ->setUser($currentUser);
            $entityManager->persist($histo);
        }

        $this->persistAttachmentsForEntity($litige, $this->attachmentService, $request, $entityManager);
        try {
            $entityManager->flush();
        }
        /** @noinspection PhpRedundantCatchClauseInspection */
        catch (UniqueConstraintViolationException $e) {
            return new JsonResponse([
                'success' => false,
                'msg' => $translator->trans('arrivage.Un autre litige d\'arrivage est en cours de création, veuillez réessayer').'.'
            ]);
        }

        $litigeService->sendMailToAcheteursOrDeclarant($litige, LitigeService::CATEGORY_ARRIVAGE);

        $response = $this->getResponseReloadArrivage($entityManager, $arrivageDataService, $request->query->get('reloadArrivage')) ?? [];
        $response['success'] = true;
        $response['msg'] = 'Le litige <strong>' . $litige->getNumeroLitige() . '</strong> a bien été créé.';
        return new JsonResponse($response);
    }

    /**
     * @Route("/supprimer-litige", name="litige_delete_arrivage", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::QUALI, Action::DELETE}, mode=HasPermission::IN_JSON)
     */
    public function deleteLitige(Request $request,
                                 EntityManagerInterface $entityManager): Response
    {
        if ($data = json_decode($request->getContent(), true)) {
            $litigeRepository = $entityManager->getRepository(Litige::class);
            $litige = $litigeRepository->find($data['litige']);

            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->remove($litige);
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
        $litigeRepository = $entityManager->getRepository(Litige::class);
        $litiges = $litigeRepository->findByArrivage($arrivage);
        $rows = [];
        foreach ($litiges as $litige) {
            $rows[] = [
                'firstDate' => $litige->getCreationDate()->format('d/m/Y H:i'),
                'status' => $litige->getStatus() ? $litige->getStatus()->getNom() : '',
                'type' => $litige->getType() ? $litige->getType()->getLabel() : '',
                'updateDate' => $litige->getUpdateDate() ? $litige->getUpdateDate()->format('d/m/Y H:i') : '',
                'Actions' => $this->renderView('arrivage/datatableLitigesRow.html.twig', [
                    'arrivageId' => $arrivage->getId(),
                    'url' => [
                        'edit' => $this->generateUrl('litige_api_edit', ['id' => $litige->getId()])
                    ],
                    'litigeId' => $litige->getId(),
                    'disputeNumber' => $litige->getNumeroLitige()
                ]),
                'urgence' => $litige->getEmergencyTriggered()
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
            $litigeRepository = $entityManager->getRepository(Litige::class);
            $arrivageRepository = $entityManager->getRepository(Arrivage::class);
            $attachmentRepository = $entityManager->getRepository(Attachment::class);
            $usersRepository = $entityManager->getRepository(Utilisateur::class);

            $litige = $litigeRepository->find($data['litigeId']);

            $colisCode = [];
            foreach ($litige->getPacks() as $pack) {
                $colisCode[] = $pack->getId();
            }

            $arrivage = $arrivageRepository->find($data['arrivageId']);

            $hasRightToTreatLitige = $userService->hasRightFunction(Menu::QUALI, Action::TREAT_LITIGE);

            $html = $this->renderView('arrivage/modalEditLitigeContent.html.twig', [
                'litige' => $litige,
                'hasRightToTreatLitige' => $hasRightToTreatLitige,
                'utilisateurs' => $usersRepository->getIdAndLibelleBySearch(''),
                'typesLitige' => $typeRepository->findByCategoryLabels([CategoryType::LITIGE]),
                'statusLitige' => $statutRepository->findByCategorieName(CategorieStatut::LITIGE_ARR, 'displayOrder'),
                'attachments' => $attachmentRepository->findBy(['litige' => $litige]),
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
    public function editLitige(Request $request,
                               ArrivageDataService $arrivageDataService,
                               EntityManagerInterface $entityManager,
                               LitigeService $litigeService,
                               Twig_Environment $templating): Response
    {
        $post = $request->request;

        $statutRepository = $entityManager->getRepository(Statut::class);
        $typeRepository = $entityManager->getRepository(Type::class);
        $packRepository = $entityManager->getRepository(Pack::class);
        $litigeRepository = $entityManager->getRepository(Litige::class);
        $utilisateurRepository = $entityManager->getRepository(Utilisateur::class);

        $litige = $litigeRepository->find($post->get('id'));
        $typeBefore = $litige->getType()->getId();
        $typeBeforeName = $litige->getType()->getLabel();
        $typeAfter = (int)$post->get('typeLitige');
        $statutBefore = $litige->getStatus()->getId();
        $statutBeforeName = $litige->getStatus()->getNom();
        $statutAfter = (int)$post->get('statutLitige');
        $litige
            ->setDeclarant($utilisateurRepository->find($post->get('declarantLitige')))
            ->setUpdateDate(new DateTime('now'));
        $this->templating = $templating;
        $newStatus = $statutRepository->find($statutAfter);
        $hasRightToTreatLitige = $this->userService->hasRightFunction(Menu::QUALI, Action::TREAT_LITIGE);

        /** @var Utilisateur $currentUser */
        $currentUser = $this->getUser();

        if ($hasRightToTreatLitige || !$newStatus->isTreated()) {
            $litige->setStatus($newStatus);
        }

        if ($hasRightToTreatLitige) {
            $litige->setType($typeRepository->find($typeAfter));
        }

        if (!empty($newColis = $post->get('colis'))) {
            // on détache les colis existants...
            $existingPacks = $litige->getPacks();
            foreach ($existingPacks as $existingPack) {
                $litige->removePack($existingPack);
            }
            // ... et on ajoute ceux sélectionnés
            $listColis = explode(',', $newColis);
            foreach ($listColis as $colisId) {
                $litige->addPack($packRepository->find($colisId));
            }
        }

        $entityManager->flush();

        $comment = '';
        $typeDescription = $litige->getType()->getDescription();
        if ($typeBefore !== $typeAfter) {
            $comment .= "Changement du type : "
                . $typeBeforeName . " -> " . $litige->getType()->getLabel() . "." .
                (!empty($typeDescription) ? ("\n" . $typeDescription . ".") : '');
        }
        if ($statutBefore !== $statutAfter) {
            if (!empty($comment)) {
                $comment .= "\n";
            }
            $comment .= "Changement du statut : " .
                $statutBeforeName . " -> " . $litige->getStatus()->getNom() . ".";
        }

        if ($post->get('commentaire')) {
            if (!empty($comment)) {
                $comment .= "\n";
            }
            $comment .= trim($post->get('commentaire'));
        }

        if ($post->get('emergency')) {
            $litige->setEmergencyTriggered($post->get('emergency') === 'true');
        }

        if (!empty($comment)) {
            $histoLitige = new LitigeHistoric();
            $histoLitige
                ->setLitige($litige)
                ->setDate(new DateTime('now'))
                ->setUser($currentUser)
                ->setComment($comment);
            $entityManager->persist($histoLitige);
            $entityManager->flush();
        }

        $listAttachmentIdToKeep = $post->get('files') ?? [];

        $attachments = $litige->getAttachments()->toArray();
        foreach ($attachments as $attachment) {
            /** @var Attachment $attachment */
            if (!in_array($attachment->getId(), $listAttachmentIdToKeep)) {
                $this->attachmentService->removeAndDeleteAttachment($attachment, $litige);
            }
        }

        $this->persistAttachmentsForEntity($litige, $this->attachmentService, $request, $entityManager);
        $entityManager->flush();
        $isStatutChange = ($statutBefore !== $statutAfter);
        if ($isStatutChange) {
            $litigeService->sendMailToAcheteursOrDeclarant($litige, LitigeService::CATEGORY_ARRIVAGE, true);
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

        $rows = [];
        /** @var Pack $pack */
        foreach ($packs as $pack) {
            $mouvement = $pack->getLastTracking();
            $rows[] = [
                'nature' => $pack->getNature() ? $pack->getNature()->getLabel() : '',
                'code' => $pack->getCode(),
                'lastMvtDate' => $mouvement ? ($mouvement->getDatetime() ? $mouvement->getDatetime()->format('d/m/Y H:i') : '') : '',
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
        $parametrageGlobalRepository = $entityManager->getRepository(ParametrageGlobal::class);
        $usernameParamIsDefined = $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::INCLUDE_RECIPIENT_IN_LABEL);
        $dropzoneParamIsDefined = $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::INCLUDE_DZ_LOCATION_IN_LABEL);
        $typeArrivalParamIsDefined = $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::INCLUDE_ARRIVAL_TYPE_IN_LABEL);
        $packCountParamIsDefined = $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::INCLUDE_PACK_COUNT_IN_LABEL);
        $commandAndProjectNumberIsDefined = $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::INCLUDE_COMMAND_AND_PROJECT_NUMBER_IN_LABEL);
        $printTwiceIfCustoms = $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::PRINT_TWICE_CUSTOMS);


        $firstCustomIconInclude = $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::INCLUDE_CUSTOMS_IN_LABEL);
        $firstCustomIconName = $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::CUSTOM_ICON);
        $firstCustomIconText = $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::CUSTOM_TEXT_LABEL);

        $firstCustomIconConfig = ($firstCustomIconInclude && $firstCustomIconName && $firstCustomIconText)
            ? [
                'icon' => $firstCustomIconName,
                'text' => $firstCustomIconText
            ]
            : null;

        $firstCustomIconInclude = $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::INCLUDE_EMERGENCY_IN_LABEL);
        $secondCustomIconName = $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::EMERGENCY_ICON);;
        $secondCustomIconText = $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::EMERGENCY_TEXT_LABEL);

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
                    $packIdsFilter
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
                $secondCustomIconConfig
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
        $packIdsFilter = $request->query->get('packs') ?: [];
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
                                                   array $packIdsFilter = []) {
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
                    $secondCustomIconConfig
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
                                           ?array $secondCustomIconConfig = null)
    {

        $arrival = $colis->getArrivage();

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
                                               ArrivageDataService $arrivageDataService,
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
                        'canBeDeleted' => $arrivageRepository->countLitigesUnsolvedByArrivage($arrivageToReload) == 0,
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
    public function saveColumnVisible(Request $request, EntityManagerInterface $entityManager): Response
    {
        $data = json_decode($request->getContent(), true);

        $champs = array_keys($data);
        $user = $this->getUser();
        /** @var $user Utilisateur */
        $champs[] = "actions";
        $user->setColumnsVisibleForArrivage($champs);
        $entityManager->flush();

        return new JsonResponse();
    }

    /**
     * @Route("/colonne-visible", name="get_column_visible_for_arrivage", options={"expose"=true}, methods="GET", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::TRACA, Action::DISPLAY_ARRI}, mode=HasPermission::IN_JSON)
     */
    public function getColumnVisible(): Response
    {
        /** @var Utilisateur $user */
        $user = $this->getUser();

        return new JsonResponse($user->getColumnsVisibleForArrivage());
    }

    /**
     * @Route("/api-columns", name="arrival_api_columns", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::TRACA, Action::DISPLAY_ARRI}, mode=HasPermission::IN_JSON)
     */
    public function apiColumns(ArrivageDataService $arrivageDataService,
                               EntityManagerInterface $entityManager): Response
    {
        /** @var Utilisateur $currentUser */
        $currentUser = $this->getUser();
        $columns = $arrivageDataService->getColumnVisibleConfig($entityManager, $currentUser);
        return new JsonResponse($columns);
    }
}
