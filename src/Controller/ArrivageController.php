<?php

namespace App\Controller;

use App\Entity\Action;
use App\Entity\Arrivage;
use App\Entity\CategorieCL;
use App\Entity\CategorieStatut;
use App\Entity\CategoryType;
use App\Entity\ChampLibre;
use App\Entity\Chauffeur;
use App\Entity\Pack;
use App\Entity\FieldsParam;
use App\Entity\Fournisseur;
use App\Entity\Litige;
use App\Entity\LitigeHistoric;
use App\Entity\Menu;
use App\Entity\MouvementTraca;
use App\Entity\Nature;
use App\Entity\ParametrageGlobal;
use App\Entity\PieceJointe;
use App\Entity\Statut;
use App\Entity\Transporteur;
use App\Entity\Type;
use App\Entity\Urgence;
use App\Entity\Utilisateur;
use App\Repository\TransporteurRepository;
use App\Service\ArrivageDataService;
use App\Service\AttachmentService;
use App\Service\MouvementTracaService;
use App\Service\PackService;
use App\Service\CSVExportService;
use App\Service\DashboardService;
use App\Service\GlobalParamService;
use App\Service\LitigeService;
use App\Service\PDFGeneratorService;
use App\Service\SpecificService;
use App\Service\StatusService;
use App\Service\UserService;
use App\Service\MailerService;
use App\Service\FreeFieldService;
use DateTime;
use DateTimeZone;
use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\ORMException;
use Exception;
use Knp\Bundle\SnappyBundle\Snappy\Response\PdfResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment as Twig_Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

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
     * @var TransporteurRepository
     */
    private $transporteurRepository;

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
                                TransporteurRepository $transporteurRepository,
                                UserService $userService)
    {
        $this->dashboardService = $dashboardService;
        $this->specificService = $specificService;
        $this->globalParamService = $globalParamService;
        $this->userService = $userService;
        $this->transporteurRepository = $transporteurRepository;
        $this->mailerService = $mailerService;
        $this->attachmentService = $attachmentService;
        $this->arrivageDataService = $arrivageDataService;
    }

    /**
     * @Route("/", name="arrivage_index")
     * @param EntityManagerInterface $entityManager
     * @param ArrivageDataService $arrivageDataService
     * @param StatusService $statusService
     * @return RedirectResponse|Response
     * @throws NonUniqueResultException
     */
    public function index(EntityManagerInterface $entityManager,
                          ArrivageDataService $arrivageDataService,
                          StatusService $statusService)
    {
        if (!$this->userService->hasRightFunction(Menu::TRACA, Action::DISPLAY_ARRI)) {
            return $this->redirectToRoute('access_denied');
        }

        $fieldsParamRepository = $entityManager->getRepository(FieldsParam::class);
        $parametrageGlobalRepository = $entityManager->getRepository(ParametrageGlobal::class);
        $typeRepository = $entityManager->getRepository(Type::class);
        $champLibreRepository = $entityManager->getRepository(ChampLibre::class);
        $fournisseurRepository = $entityManager->getRepository(Fournisseur::class);
        $chauffeurRepository = $entityManager->getRepository(Chauffeur::class);
        $transporteurRepository = $entityManager->getRepository(Transporteur::class);
        $natureRepository = $entityManager->getRepository(Nature::class);
        $utilisateurRepository = $entityManager->getRepository(Utilisateur::class);

        /** @var Utilisateur $user */
        $user = $this->getUser();

        $champs = $arrivageDataService->getColumnVisibleConfig($entityManager, $user);

        $fieldsParam = $fieldsParamRepository->getByEntity(FieldsParam::ENTITY_CODE_ARRIVAGE);
        $paramGlobalRedirectAfterNewArrivage = $parametrageGlobalRepository->findOneByLabel(ParametrageGlobal::REDIRECT_AFTER_NEW_ARRIVAL);
        $paramGlobalDefaultStatusArrivageId = $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::DEFAULT_STATUT_ARRIVAGE);

        $status = $statusService->findAllStatusArrivage();

        return $this->render('arrivage/index.html.twig', [
            'carriers' => $transporteurRepository->findAllSorted(),
            'chauffeurs' => $chauffeurRepository->findAllSorted(),
            'users' => $utilisateurRepository->findBy(['status' => true],['username'=> 'ASC']),
            'fournisseurs' => $fournisseurRepository->findAllSorted(),
            'typesLitige' => $typeRepository->findByCategoryLabels([CategoryType::LITIGE]),
            'natures' => $natureRepository->findAll(),
            'statuts' => $status,
            'typesArrival' => $typeRepository->findByCategoryLabels([CategoryType::ARRIVAGE]),
            'fieldsParam' => $fieldsParam,
            'redirect' => $paramGlobalRedirectAfterNewArrivage ? $paramGlobalRedirectAfterNewArrivage->getValue() : true,
            'champsLibres' => $champLibreRepository->findByCategoryTypeLabels([CategoryType::ARRIVAGE]),
            'pageLengthForArrivage' => $user->getPageLengthForArrivage() ?: 10,
            'autoPrint' => $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::AUTO_PRINT_COLIS),
            'defaultStatutArrivageId' => $paramGlobalDefaultStatusArrivageId,
            'champs' => $champs,
            'columnsVisibles' => $user->getColumnsVisibleForArrivage(),
            'businessUnits' => json_decode($parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::BUSINESS_UNIT_VALUES))
        ]);
    }

    /**
     * @Route("/api", name="arrivage_api", options={"expose"=true}, methods="GET|POST")
     * @param Request $request
     * @return Response
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function api(Request $request): Response
    {
        if ($request->isXmlHttpRequest()) {
            if (!$this->userService->hasRightFunction(Menu::TRACA, Action::DISPLAY_ARRI)) {
                return $this->redirectToRoute('access_denied');
            }

            $canSeeAll = $this->userService->hasRightFunction(Menu::TRACA, Action::LIST_ALL);
            $userId = $canSeeAll ? null : ($this->getUser() ? $this->getUser()->getId() : null);
            $data = $this->arrivageDataService->getDataForDatatable($request->request, $userId);

            return new JsonResponse($data);
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/creer", name="arrivage_new", options={"expose"=true}, methods={"GET", "POST"})
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @param AttachmentService $attachmentService
     * @param UserService $userService
     * @param ArrivageDataService $arrivageDataService
     * @param FreeFieldService $champLibreService
     * @param PackService $colisService
     * @return Response
     * @throws LoaderError
     * @throws NoResultException
     * @throws NonUniqueResultException
     * @throws RuntimeError
     * @throws SyntaxError
     * @throws ORMException
     * @throws Exception
     */
    public function new(Request $request,
                        EntityManagerInterface $entityManager,
                        AttachmentService $attachmentService,
                        UserService $userService,
                        ArrivageDataService $arrivageDataService,
                        FreeFieldService $champLibreService,
                        PackService $colisService): Response
    {
        if ($request->isXmlHttpRequest()) {
            if (!$userService->hasRightFunction(Menu::TRACA, Action::CREATE)) {
                return $this->redirectToRoute('access_denied');
            }

            $data = $request->request->all();
            $parametrageGlobalRepository = $entityManager->getRepository(ParametrageGlobal::class);
            $arrivageRepository = $entityManager->getRepository(Arrivage::class);
            $statutRepository = $entityManager->getRepository(Statut::class);
            $fournisseurRepository = $entityManager->getRepository(Fournisseur::class);
            $transporteurRepository = $entityManager->getRepository(Transporteur::class);
            $chauffeurRepository = $entityManager->getRepository(Chauffeur::class);
            $userRepository = $entityManager->getRepository(Utilisateur::class);
            $typeRepository = $entityManager->getRepository(Type::class);
            $sendMail = $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::SEND_MAIL_AFTER_NEW_ARRIVAL);

            $date = new DateTime('now', new DateTimeZone('Europe/Paris'));
            $counter = $arrivageRepository->countByDate($date) + 1;
            $suffix = $counter < 10 ? ("0" . $counter) : $counter;
            $numeroArrivage = $date->format('ymdHis') . '-' . $suffix;

            $arrivage = new Arrivage();
            $arrivage
                ->setIsUrgent(false)
                ->setDate($date)
                ->setStatut($statutRepository->find($data['statut']))
                ->setUtilisateur($this->getUser())
                ->setNumeroArrivage($numeroArrivage)
                ->setDuty(isset($data['duty']) ? $data['duty'] == 'true' : false)
                ->setFrozen(isset($data['frozen']) ? $data['frozen'] == 'true' : false)
                ->setCommentaire($data['commentaire'] ?? null)
                ->setType($typeRepository->find($data['type']));

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

            try {
                // persist and flush in function below
                $this->persistAttachmentsForEntity($arrivage, $attachmentService, $request, $entityManager);
            }

            /** @noinspection PhpRedundantCatchClauseInspection */
            catch (UniqueConstraintViolationException $e) {
                return new JsonResponse([
                    'success' => false,
                    'msg' => "Une création d'arrivage était déjà en cours, veuillez réessayer.<br>"
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

            $colisService->persistMultiPacks($arrivage, $natures, $this->getUser(), $entityManager);

            $champLibreService->manageFreeFields($arrivage, $data, $entityManager);

            $entityManager->flush();

            $alertConfigs = $arrivageDataService->processEmergenciesOnArrival($arrivage);
            $entityManager->flush();
            if ($sendMail) {
                $arrivageDataService->sendArrivalEmails($arrivage);
            }

            $entityManager->flush();
            $paramGlobalRedirectAfterNewArrivage = $parametrageGlobalRepository->findOneByLabel(ParametrageGlobal::REDIRECT_AFTER_NEW_ARRIVAL);
            $statutConformeId = $statutRepository->getOneIdByCategorieNameAndStatusName(CategorieStatut::ARRIVAGE, Arrivage::STATUS_CONFORME);

            return new JsonResponse([
                'success' => true,
                "redirectAfterAlert" => ($paramGlobalRedirectAfterNewArrivage ? $paramGlobalRedirectAfterNewArrivage->getValue() : true)
                    ? $this->generateUrl('arrivage_show', ['id' => $arrivage->getId()])
                    : null,
                'printColis' => (isset($data['printColis']) && $data['printColis'] === 'true'),
                'printArrivage' => isset($data['printArrivage']) && $data['printArrivage'] === 'true',
                'arrivageId' => $arrivage->getId(),
                'numeroArrivage' => $arrivage->getNumeroArrivage(),
                'statutConformeId' => $statutConformeId,
                'alertConfigs' => $alertConfigs
            ]);
        }
        throw new NotFoundHttpException('404 not found');
    }

    /**
     * @Route("/api-modifier", name="arrivage_edit_api", options={"expose"=true}, methods="GET|POST")
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @param StatusService $statusService
     * @return Response
     * @throws NonUniqueResultException
     */
    public function editApi(Request $request,
                            EntityManagerInterface $entityManager,
                            StatusService $statusService): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::TRACA, Action::DISPLAY_ARRI)) {
                return $this->redirectToRoute('access_denied');
            }
            if ($this->userService->hasRightFunction(Menu::TRACA, Action::EDIT)) {
                $arrivageRepository = $entityManager->getRepository(Arrivage::class);
                $fieldsParamRepository = $entityManager->getRepository(FieldsParam::class);
                $chauffeurRepository = $entityManager->getRepository(Chauffeur::class);
                $fournisseurRepository = $entityManager->getRepository(Fournisseur::class);
                $pieceJointeRepository = $entityManager->getRepository(PieceJointe::class);
                $utilisateurRepository = $entityManager->getRepository(Utilisateur::class);
                $parametrageGlobalRepository = $entityManager->getRepository(ParametrageGlobal::class);

                $arrivage = $arrivageRepository->find($data['id']);

                // construction de la chaîne de caractères pour alimenter le select2
                $acheteursUsernames = [];
                foreach ($arrivage->getAcheteurs() as $acheteur) {
                    $acheteursUsernames[] = $acheteur->getUsername();
                }
                $fieldsParam = $fieldsParamRepository->getByEntity(FieldsParam::ENTITY_CODE_ARRIVAGE);

                $status = $statusService->findAllStatusArrivage();

                $html = $this->renderView('arrivage/modalEditArrivageContent.html.twig', [
                    'arrivage' => $arrivage,
                    'attachments' => $pieceJointeRepository->findBy(['arrivage' => $arrivage]),
                    'utilisateurs' => $utilisateurRepository->findBy(['status' => true], ['username' => 'ASC']),
                    'fournisseurs' => $fournisseurRepository->findAllSorted(),
                    'transporteurs' => $this->transporteurRepository->findAllSorted(),
                    'chauffeurs' => $chauffeurRepository->findAllSorted(),
                    'statuts' => $status,
                    'fieldsParam' => $fieldsParam,
                    'businessUnits' => json_decode($parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::BUSINESS_UNIT_VALUES))
                ]);
            } else {
                $html = '';
            }

            return new JsonResponse(['html' => $html, 'acheteurs' => $acheteursUsernames]);
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route(
     *     "/{arrival}/urgent",
     *     name="patch_arrivage_urgent",
     *     options={"expose"=true},
     *     methods="PATCH",
     *     condition="request.isXmlHttpRequest() && '%client%' == constant('\\App\\Service\\SpecificService::CLIENT_SAFRAN_ED')"
     * )
     *
     * @param Arrivage $arrival
     * @param Request $request
     * @param ArrivageDataService $arrivageDataService
     * @param EntityManagerInterface $entityManager
     *
     * @return Response
     *
     * @throws LoaderError
     * @throws NonUniqueResultException
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function patchUrgentArrival(Arrivage $arrival,
                                       Request $request,
                                       ArrivageDataService $arrivageDataService,
                                       EntityManagerInterface $entityManager): Response
    {
        $urgenceRepository = $entityManager->getRepository(Urgence::class);
        $numeroCommande = $request->request->get('numeroCommande');
        $postNb = $request->request->get('postNb');

        $urgencesMatching = !empty($numeroCommande)
            ? $urgenceRepository->findUrgencesMatching(
                $arrival->getDate(),
                $arrival->getFournisseur(),
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
     * @Route("/modifier", name="arrivage_edit", options={"expose"=true}, methods="GET|POST")
     * @param Request $request
     * @param ArrivageDataService $arrivageDataService
     * @param FreeFieldService $champLibreService
     * @param EntityManagerInterface $entityManager
     *
     * @return Response
     *
     * @throws LoaderError
     * @throws NoResultException
     * @throws NonUniqueResultException
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function edit(Request $request,
                         ArrivageDataService $arrivageDataService,
                         FreeFieldService $champLibreService,
                         EntityManagerInterface $entityManager): Response
    {
        if ($request->isXmlHttpRequest()) {
            if (!$this->userService->hasRightFunction(Menu::TRACA, Action::EDIT)) {
                return $this->redirectToRoute('access_denied');
            }
            $statutRepository = $entityManager->getRepository(Statut::class);
            $fournisseurRepository = $entityManager->getRepository(Fournisseur::class);
            $parametrageGlobalRepository = $entityManager->getRepository(ParametrageGlobal::class);
            $arrivageRepository = $entityManager->getRepository(Arrivage::class);
            $chauffeurRepository = $entityManager->getRepository(Chauffeur::class);
            $utilisateurRepository = $entityManager->getRepository(Utilisateur::class);
            $typeRepository = $entityManager->getRepository(Type::class);

            $post = $request->request;
            $isSEDCurrentClient = $this->specificService->isCurrentClientNameFunction(SpecificService::CLIENT_SAFRAN_ED);

            $arrivage = $arrivageRepository->find($post->get('id'));

            $fournisseurId = $post->get('fournisseur');
            $transporteurId = $post->get('transporteur');
            $destinataireId = $post->get('destinataire');
            $statutId = $post->get('statut');
            $chauffeurId = $post->get('chauffeur');
            $type = $post->get('type');
            $newDestinataire = $destinataireId ? $utilisateurRepository->find($destinataireId) : null;
            $destinataireChanged = $newDestinataire && $newDestinataire !== $arrivage->getDestinataire();
            $numeroCommadeListStr = $post->get('numeroCommandeList');

            $sendMail = $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::SEND_MAIL_AFTER_NEW_ARRIVAL);

            $arrivage
                ->setCommentaire($post->get('commentaire'))
                ->setNoTracking(substr($post->get('noTracking'), 0, 64))
                ->setNumeroCommandeList(explode(',', $numeroCommadeListStr))
                ->setFournisseur($fournisseurId ? $fournisseurRepository->find($fournisseurId) : null)
                ->setTransporteur($transporteurId ? $this->transporteurRepository->find($transporteurId) : null)
                ->setChauffeur($chauffeurId ? $chauffeurRepository->find($chauffeurId) : null)
                ->setStatut($statutId ? $statutRepository->find($statutId) : null)
                ->setDuty($post->get('duty') == 'true')
                ->setFrozen($post->get('frozen') == 'true')
                ->setDestinataire($newDestinataire)
                ->setBusinessUnit($post->get('businessUnit') ?? null)
                ->setProjectNumber($post->get('businessUnit') ?? null)
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
                /** @var PieceJointe $attachment */
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
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/supprimer", name="arrivage_delete", options={"expose"=true},methods={"GET","POST"})
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @param MouvementTracaService $mouvementTracaService
     * @return Response
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    public function delete(Request $request,
                           EntityManagerInterface $entityManager,
                           MouvementTracaService $mouvementTracaService): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $arrivageRepository = $entityManager->getRepository(Arrivage::class);
            $mouvementTracaRepository = $entityManager->getRepository(MouvementTraca::class);
            $arrivage = $arrivageRepository->find($data['arrivage']);

            if (!$this->userService->hasRightFunction(Menu::TRACA, Action::DELETE)) {
                return $this->redirectToRoute('access_denied');
            }

            $canBeDeleted = ($arrivageRepository->countLitigesUnsolvedByArrivage($arrivage) == 0);

            if ($canBeDeleted) {
                foreach ($arrivage->getPacks() as $pack) {
                    $entityManager->remove($pack);
                    foreach ($pack->getTrackingMovements() as $arrivageMvtTraca) {
                        $mouvementTracaService->manageMouvementTracaPreRemove($arrivageMvtTraca);
                        $entityManager->flush();
                        $entityManager->remove($arrivageMvtTraca);
                    }
                    $litiges = $pack->getLitiges();
                    foreach ($litiges as $litige) {
                        $entityManager->remove($litige);
                    }
                }
                foreach ($arrivage->getAttachments() as $attachement) {
                    $this->attachmentService->removeAndDeleteAttachment($attachement, $arrivage);
                }
                foreach ($arrivage->getUrgences() as $urgence) {
                    $urgence->setLastArrival(null);
                }

                foreach ($arrivage->getMouvementsTraca() as $mouvementTraca) {
                    $mouvementTracaService->manageMouvementTracaPreRemove($mouvementTraca);
                    $entityManager->flush();
                    $entityManager->remove($mouvementTraca);
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

        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/depose-pj", name="arrivage_depose", options={"expose"=true}, methods="GET|POST")
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    public function depose(Request $request,
                           EntityManagerInterface $entityManager): Response
    {
        if ($request->isXmlHttpRequest()) {
            $arrivageRepository = $entityManager->getRepository(Arrivage::class);
            $fileNames = [];

            $id = (int)$request->request->get('id');
            $arrivage = $arrivageRepository->find($id);

            for ($i = 0; $i < count($request->files); $i++) {
                $file = $request->files->get('file' . $i);
                if ($file) {
                    $pj = $this->attachmentService->createPieceJointe($file, $arrivage);
                    $entityManager->persist($pj);

                    $fileNames[] = [
                        'name' => $pj->getFileName(),
                        'originalName' => $file->getClientOriginalName()
                    ];
                }
            }
            $entityManager->flush();

            $html = '';
            foreach ($fileNames as $fileName) {
                $html .= $this->renderView('attachment/attachmentLine.html.twig', [
                    'arrivage' => $arrivage,
                    'pjName' => $fileName['name'],
                    'originalName' => $fileName['originalName']
                ]);
            }

            return new JsonResponse($html);
        } else {
            throw new NotFoundHttpException('404');
        }
    }

    /**
     * @Route("/ajoute-commentaire", name="add_comment",  options={"expose"=true}, methods="GET|POST")
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    public function addComment(Request $request,
                               EntityManagerInterface $entityManager): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
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
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/lister-colis", name="arrivage_list_colis_api", options={"expose"=true})
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return JsonResponse
     */
    public function listColisByArrivage(Request $request,
                                        EntityManagerInterface $entityManager)
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $arrivageRepository = $entityManager->getRepository(Arrivage::class);
            $arrivage = $arrivageRepository->find($data['id']);

            $html = $this->renderView('arrivage/modalListColisContent.html.twig', [
                'arrivage' => $arrivage
            ]);

            return new JsonResponse($html);

        } else {
            throw new NotFoundHttpException('404');
        }
    }

    /**
     * @Route("/garder-pj", name="garder_pj", options={"expose"=true}, methods="GET|POST")
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return JsonResponse
     */
    public function displayAttachmentForNew(Request $request, EntityManagerInterface $entityManager)
    {
        if ($request->isXmlHttpRequest()) {
            $fileNames = [];
            $html = '';
            $path = "../public/uploads/attachements/temp/";
            if (!file_exists($path)) {
                mkdir($path, 0777);
            }
            for ($i = 0; $i < count($request->files); $i++) {
                $file = $request->files->get('file' . $i);
                if ($file) {
                    if ($file->getClientOriginalExtension()) {
                        $filename = uniqid() . "." . $file->getClientOriginalExtension();
                    } else {
                        $filename = uniqid();
                    }
                    $fileNames[] = $filename;
                    $file->move($path, $filename);
                    $html .= $this->renderView('attachment/attachmentLine.html.twig', [
                        'pjName' => $filename,
                        'originalName' => $file->getClientOriginalName()
                    ]);
                    $pj = new PieceJointe();
                    $pj
                        ->setOriginalName($file->getClientOriginalName())
                        ->setFileName($filename);
                    $entityManager->persist($pj);
                }
                $entityManager->flush();
            }

            return new JsonResponse($html);
        } else {
            throw new NotFoundHttpException('404');
        }
    }

    /**
     * @Route("/csv", name="get_arrivages_csv", options={"expose"=true}, methods={"GET"})
     * @param Request $request
     * @param CSVExportService $CSVExportService
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    public function getArrivageCSV(Request $request,
                                   CSVExportService $CSVExportService,
                                   EntityManagerInterface $entityManager): Response
    {
        $dateMin = $request->query->get('dateMin');
        $dateMax = $request->query->get('dateMax');

        try {
            $dateTimeMin = DateTime::createFromFormat('Y-m-d H:i:s', $dateMin . ' 00:00:00');
            $dateTimeMax = DateTime::createFromFormat('Y-m-d H:i:s', $dateMax . ' 23:59:59');
        } catch (\Throwable $throwable) {
        }

        if (isset($dateTimeMin) && isset($dateTimeMax)) {
            $arrivageRepository = $entityManager->getRepository(Arrivage::class);
            $utilisateurRepository = $entityManager->getRepository(Utilisateur::class);
            $natureRepository = $entityManager->getRepository(Nature::class);
            $packRepository = $entityManager->getRepository(Pack::class);
            $categorieCLRepository = $entityManager->getRepository(CategorieCL::class);
            $champLibreRepository = $entityManager->getRepository(ChampLibre::class);

            $categorieCL = $categorieCLRepository->findOneByLabel(CategorieCL::ARRIVAGE);
            $category = CategoryType::ARRIVAGE;
            $freeFields = $champLibreRepository->getByCategoryTypeAndCategoryCL($category, $categorieCL);

            $freeFieldsIds = array_map(
                function (array $cl) {
                    return $cl['id'];
                },
                $freeFields
            );
            $freeFieldsHeader = array_map(
                function (array $cl) {
                    return $cl['label'];
                },
                $freeFields
            );

            $colisData = $packRepository->countColisByArrivageAndNature(
                [
                    $dateTimeMin->format('Y-m-d H:i:s'),
                    $dateTimeMax->format('Y-m-d H:i:s')
                ]
            );
            $arrivals = $arrivageRepository->getByDates($dateTimeMin, $dateTimeMax);
            $buyersByArrival = $utilisateurRepository->getUsernameBuyersGroupByArrival();
            $natureLabels = $natureRepository->findAllLabels();
            // en-têtes champs fixes
            $csvHeader = [
                'n° arrivage',
                'destinataire',
                'fournisseur',
                'transporteur',
                'chauffeur',
                'n° tracking transporteur',
                'n° commande/BL',
                'type',
                'acheteurs',
                'douane',
                'congelé',
                'statut',
                'commentaire',
                'date',
                'utilisateur',
                'numéro de projet',
                'business unit'
            ];
            $csvHeader = array_merge($csvHeader, $natureLabels, $freeFieldsHeader);

            return $CSVExportService->createBinaryResponseFromData(
                'export.csv',
                $arrivals,
                $csvHeader,
                function ($arrival) use ($buyersByArrival, $natureLabels, $colisData, $freeFieldsIds) {
                    $arrivalId = (int) $arrival['id'];
                    $row = [];
                    $row[] = $arrival['numeroArrivage'] ?: '';
                    $row[] = $arrival['recipientUsername'] ?: '';
                    $row[] = $arrival['fournisseurName'] ?: '';
                    $row[] = $arrival['transporteurLabel'] ?: '';
                    $row[] = (!empty($arrival['chauffeurFirstname']) && !empty($arrival['chauffeurSurname']))
                        ? $arrival['chauffeurFirstname'] . ' ' . $arrival['chauffeurSurname']
                        : ($arrival['chauffeurFirstname'] ?: $arrival['chauffeurSurname'] ?: '');
                    $row[] = $arrival['noTracking'] ?: '';
                    $row[] = !empty($arrival['numeroCommandeList']) ? implode(' / ', $arrival['numeroCommandeList']) : '';
                    $row[] = $arrival['type'] ?: '';
                    $row[] = $buyersByArrival[$arrivalId] ?? '';
                    $row[] = $arrival['duty'] ? 'oui' : 'non';
                    $row[] = $arrival['frozen'] ? 'oui' : 'non';
                    $row[] = $arrival['statusName'] ?: '';
                    $row[] = $arrival['commentaire'] ? strip_tags($arrival['commentaire']) : '';
                    $row[] = $arrival['date'] ? $arrival['date']->format('d/m/Y H:i:s') : '';
                    $row[] = $arrival['userUsername'] ?: '';
                    $row[] = $arrival['projectNumber'] ?: '';
                    $row[] = $arrival['businessUnit'] ?: '';

                    foreach ($natureLabels as $natureLabel) {
                        $count = (isset($colisData[$arrivalId]) && isset($colisData[$arrivalId][$natureLabel]))
                            ? $colisData[$arrivalId][$natureLabel]
                            : 0;
                        $row[] = $count;
                    }
                    foreach ($freeFieldsIds as $freeField) {
                        $row[] = $mouvement['freeFields'][$freeField] ?? "";
                    }
                    return [$row];
                }
            );
        } else {
            throw new NotFoundHttpException('404');
        }
    }

    /**
     * @Route("/voir/{id}/{printColis}/{printArrivage}", name="arrivage_show", options={"expose"=true}, methods={"GET", "POST"})
     *
     * @param EntityManagerInterface $entityManager
     * @param ArrivageDataService $arrivageDataService
     * @param Arrivage $arrivage
     * @param bool $printColis
     * @param bool $printArrivage
     *
     * @return JsonResponse
     *
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    public function show(EntityManagerInterface $entityManager,
                         ArrivageDataService $arrivageDataService,
                         Arrivage $arrivage,
                         bool $printColis = false,
                         bool $printArrivage = false): Response
    {
        if (!$this->userService->hasRightFunction(Menu::TRACA, Action::LIST_ALL)
            && !in_array($this->getUser(), $arrivage->getAcheteurs()->toArray())) {
            return $this->redirectToRoute('access_denied');
        }

        $statutRepository = $entityManager->getRepository(Statut::class);
        $typeRepository = $entityManager->getRepository(Type::class);
        $fieldsParamRepository = $entityManager->getRepository(FieldsParam::class);
        $arrivageRepository = $entityManager->getRepository(Arrivage::class);
        $natureRepository = $entityManager->getRepository(Nature::class);
        $usersRepository = $entityManager->getRepository(Utilisateur::class);

        $acheteursNames = [];
        foreach ($arrivage->getAcheteurs() as $user) {
            $acheteursNames[] = $user->getUsername();
        }

        $fieldsParam = $fieldsParamRepository->getByEntity(FieldsParam::ENTITY_CODE_ARRIVAGE);

        $defaultDisputeStatus = $statutRepository->getIdDefaultsByCategoryName(CategorieStatut::LITIGE_ARR);

        return $this->render("arrivage/show.html.twig", [
            'arrivage' => $arrivage,
            'typesLitige' => $typeRepository->findByCategoryLabels([CategoryType::LITIGE]),
            'acheteurs' => $acheteursNames,
            'statusLitige' => $statutRepository->findByCategorieName(CategorieStatut::LITIGE_ARR, true),
            'allColis' => $arrivage->getPacks(),
            'natures' => $natureRepository->findAll(),
            'printColis' => $printColis,
            'printArrivage' => $printArrivage,
            'utilisateurs' => $usersRepository->getIdAndLibelleBySearch(''),
            'canBeDeleted' => $arrivageRepository->countLitigesUnsolvedByArrivage($arrivage) == 0,
            'fieldsParam' => $fieldsParam,
            'showDetails' => $arrivageDataService->createHeaderDetailsConfig($arrivage),
            'defaultDisputeStatusId' => $defaultDisputeStatus[0] ?? null
        ]);
    }

    /**
     * @Route("/creer-litige", name="litige_new", options={"expose"=true}, methods={"POST"})
     * @param Request $request
     * @param ArrivageDataService $arrivageDataService
     * @param LitigeService $litigeService
     * @param EntityManagerInterface $entityManager
     * @return Response
     * @throws NoResultException
     * @throws NonUniqueResultException
     * @throws Exception
     */
    public function newLitige(Request $request,
                              ArrivageDataService $arrivageDataService,
                              LitigeService $litigeService,
                              EntityManagerInterface $entityManager): Response
    {
        if ($request->isXmlHttpRequest()) {
            if (!$this->userService->hasRightFunction(Menu::TRACA, Action::CREATE)) {
                return $this->redirectToRoute('access_denied');
            }

            $post = $request->request;

            $statutRepository = $entityManager->getRepository(Statut::class);
            $typeRepository = $entityManager->getRepository(Type::class);
            $packRepository = $entityManager->getRepository(Pack::class);
            $usersRepository = $entityManager->getRepository(Utilisateur::class);

            $now = new DateTime('now', new DateTimeZone('Europe/Paris'));
            $disputeNumber = $litigeService->createDisputeNumber($entityManager, 'LA', $now);

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
                $arrivage->setStatut($statutRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::ARRIVAGE, Arrivage::STATUS_LITIGE));
            }
            $typeDescription = $litige->getType()->getDescription();
            $typeLabel = $litige->getType()->getLabel();
            $statutNom = $litige->getStatus()->getNom();

            $trimmedTypeDescription = trim($typeDescription);
            $userComment = trim($post->get('commentaire'));
            $nl = !empty($userComment) ? "\n" : '';
            $trimmedTypeDescription = !empty($trimmedTypeDescription) ? "\n" . $trimmedTypeDescription : '';
            $commentaire = $userComment . $nl . 'Type à la création -> ' . $typeLabel . $trimmedTypeDescription . "\n" . 'Statut à la création -> ' . $statutNom;
            if (!empty($commentaire)) {
                $histo = new LitigeHistoric();
                $histo
                    ->setDate(new DateTime('now'))
                    ->setComment($commentaire)
                    ->setLitige($litige)
                    ->setUser($this->getUser());
                $entityManager->persist($histo);
            }

            $this->persistAttachmentsForEntity($litige, $this->attachmentService, $request, $entityManager);
            $entityManager->flush();

            $litigeService->sendMailToAcheteursOrDeclarant($litige, LitigeService::CATEGORY_ARRIVAGE);
            $response = $this->getResponseReloadArrivage($entityManager, $arrivageDataService, $request->query->get('reloadArrivage')) ?? [];
            $response['success'] = true;

            return new JsonResponse($response);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/supprimer-litige", name="litige_delete_arrivage", options={"expose"=true}, methods="GET|POST")
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    public function deleteLitige(Request $request,
                                 EntityManagerInterface $entityManager): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::QUALI, Action::DELETE)) {
                return $this->redirectToRoute('access_denied');
            }

            $litigeRepository = $entityManager->getRepository(Litige::class);
            $litige = $litigeRepository->find($data['litige']);

            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->remove($litige);
            $entityManager->flush();
            return new JsonResponse();
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/ajouter-colis", name="arrivage_add_colis", options={"expose"=true}, methods={"GET", "POST"})
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @param PackService $colisService
     * @return JsonResponse|RedirectResponse
     * @throws Exception
     */
    public function addColis(Request $request,
                             EntityManagerInterface $entityManager,
                             PackService $colisService)
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::TRACA, Action::EDIT)) {
                return $this->redirectToRoute('access_denied');
            }

            $arrivageRepository = $entityManager->getRepository(Arrivage::class);

            $arrivage = $arrivageRepository->find($data['arrivageId']);

            $natures = json_decode($data['colis'], true);

            $persistedColis = $colisService->persistMultiPacks($arrivage, $natures, $this->getUser(), $entityManager);
            $entityManager->flush();

            return new JsonResponse([
                'colisIds' => array_map(function (Pack $colis) {
                    return $colis->getId();
                }, $persistedColis),
                'arrivageId' => $arrivage->getId(),
                'arrivage' => $arrivage->getNumeroArrivage()
            ]);
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/litiges/api/{arrivage}", name="arrivageLitiges_api", options={"expose"=true}, methods="GET|POST")
     * @param EntityManagerInterface $entityManager
     * @param Request $request
     * @param Arrivage $arrivage
     * @return Response
     */
    public function apiArrivageLitiges(EntityManagerInterface $entityManager,
                                       Request $request,
                                       Arrivage $arrivage): Response
    {
        if ($request->isXmlHttpRequest()) {
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
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/api-modifier-litige", name="litige_api_edit", options={"expose"=true}, methods="GET|POST")
     * @param Request $request
     * @param UserService $userService
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    public function apiEditLitige(Request $request,
                                  UserService $userService,
                                  EntityManagerInterface $entityManager): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $statutRepository = $entityManager->getRepository(Statut::class);
            $typeRepository = $entityManager->getRepository(Type::class);
            $litigeRepository = $entityManager->getRepository(Litige::class);
            $arrivageRepository = $entityManager->getRepository(Arrivage::class);
            $pieceJointeRepository = $entityManager->getRepository(PieceJointe::class);
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
                'statusLitige' => $statutRepository->findByCategorieName(CategorieStatut::LITIGE_ARR, true),
                'attachments' => $pieceJointeRepository->findBy(['litige' => $litige]),
                'colis' => $arrivage->getPacks(),
            ]);

            return new JsonResponse(['html' => $html, 'colis' => $colisCode]);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/modifier-litige", name="litige_edit_arrivage",  options={"expose"=true}, methods="GET|POST")
     * @param Request $request
     * @param ArrivageDataService $arrivageDataService
     * @param EntityManagerInterface $entityManager
     * @param LitigeService $litigeService
     * @param Twig_Environment $templating
     * @return Response
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    public function editLitige(Request $request,
                               ArrivageDataService $arrivageDataService,
                               EntityManagerInterface $entityManager,
                               LitigeService $litigeService,
                               Twig_Environment $templating): Response
    {
        if ($request->isXmlHttpRequest()) {
            if (!$this->userService->hasRightFunction(Menu::QUALI, Action::EDIT)) {
                return $this->redirectToRoute('access_denied');
            }
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
                    ->setUser($this->getUser())
                    ->setComment($comment);
                $entityManager->persist($histoLitige);
                $entityManager->flush();
            }

            $listAttachmentIdToKeep = $post->get('files') ?? [];

            $attachments = $litige->getAttachments()->toArray();
            foreach ($attachments as $attachment) {
                /** @var PieceJointe $attachment */
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
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/depose-pj-litige", name="litige_depose", options={"expose"=true}, methods="GET|POST")
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    public function deposeLitige(Request $request,
                                 EntityManagerInterface $entityManager): Response
    {
        if ($request->isXmlHttpRequest()) {
            $fileNames = [];

            $litigeRepository = $entityManager->getRepository(Litige::class);
            $id = (int)$request->request->get('id');
            $litige = $litigeRepository->find($id);

            for ($i = 0; $i < count($request->files); $i++) {
                $file = $request->files->get('file' . $i);
                if ($file) {
                    $pj = $this->attachmentService->createPieceJointe($file, $litige);
                    $entityManager->persist($pj);

                    $fileNames[] = [
                        'name' => $pj->getFileName(),
                        'originalName' => $file->getClientOriginalName()
                    ];
                }
            }
            $entityManager->flush();

            $html = '';
            foreach ($fileNames as $fileName) {
                $html .= $this->renderView('attachment/attachmentLine.html.twig', [
                    'litige' => $litige,
                    'pjName' => $fileName['name'],
                    'originalName' => $fileName['originalName']
                ]);
            }

            return new JsonResponse($html);
        } else {
            throw new NotFoundHttpException('404');
        }
    }

    /**
     * @Route("/colis/api/{arrivage}", name="colis_api", options={"expose"=true}, methods="GET|POST")
     * @param Request $request
     * @param Arrivage $arrivage
     * @return Response
     */
    public function apiColis(Request $request,
                             Arrivage $arrivage): Response
    {
        if ($request->isXmlHttpRequest()) {

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
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route(
     *     "/{arrivage}/colis/{colis}/etiquette",
     *     name="print_arrivage_single_colis_bar_codes",
     *     options={"expose"=true},
     *     methods="GET"
     * )
     *
     * @param Arrivage $arrivage
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @param PDFGeneratorService $PDFGeneratorService
     * @param Pack|null $colis
     *
     * @return Response
     *
     * @throws LoaderError
     * @throws NonUniqueResultException
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function printArrivageColisBarCodes(Arrivage $arrivage,
                                               Request $request,
                                               EntityManagerInterface $entityManager,
                                               PDFGeneratorService $PDFGeneratorService,
                                               Pack $colis = null): Response
    {
        $barcodeConfigs = [];
        $parametrageGlobalRepository = $entityManager->getRepository(ParametrageGlobal::class);
        $usernameParamIsDefined = $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::INCLUDE_RECIPIENT_IN_LABEL);
        $dropzoneParamIsDefined = $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::INCLUDE_DZ_LOCATION_IN_LABEL);

        if (!isset($colis)) {
            $printColis = $request->query->getBoolean('printColis');
            $printArrivage = $request->query->getBoolean('printArrivage');

            if ($printColis) {
                $barcodeConfigs = $this->getBarcodeConfigPrintAllColis(
                    $arrivage,
                    $usernameParamIsDefined,
                    $dropzoneParamIsDefined);
            }

            if ($printArrivage) {
                $barcodeConfigs[] = [
                    'code' => $arrivage->getNumeroArrivage()
                ];
            }
        } else {
            if (!$colis->getArrivage() || $colis->getArrivage()->getId() !== $arrivage->getId()) {
                throw new NotFoundHttpException("404");
            }

            $barcodeConfigs[] = $this->getBarcodeColisConfig(
                $colis,
                $arrivage->getDestinataire(),
                $usernameParamIsDefined,
                $dropzoneParamIsDefined);
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
     * @Route(
     *     "/{arrivage}/etiquettes",
     *     name="print_arrivage_bar_codes",
     *     options={"expose"=true},
     *     methods="GET"
     * )
     * @param Arrivage $arrivage
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @param PDFGeneratorService $PDFGeneratorService
     * @return Response
     * @throws LoaderError
     * @throws NonUniqueResultException
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function printArrivageAlias(Arrivage $arrivage,
                                       Request $request,
                                       EntityManagerInterface $entityManager,
                                       PDFGeneratorService $PDFGeneratorService)
    {
        return $this->printArrivageColisBarCodes($arrivage, $request, $entityManager, $PDFGeneratorService);
    }

    private function getBarcodeConfigPrintAllColis(Arrivage $arrivage, ?bool $usernameParamIsDefined, ?bool $dropzoneParamIsDefined)
    {
        return array_map(
            function (Pack $colisInArrivage) use ($arrivage, $dropzoneParamIsDefined, $usernameParamIsDefined) {
                return $this->getBarcodeColisConfig(
                    $colisInArrivage,
                    $arrivage->getDestinataire(),
                    $usernameParamIsDefined,
                    $dropzoneParamIsDefined
                );
            },
            $arrivage->getPacks()->toArray()
        );
    }

    private function getBarcodeColisConfig(Pack $colis,
                                           ?Utilisateur $destinataire,
                                           ?bool $usernameParamIsDefined,
                                           ?bool $dropzoneParamIsDefined)
    {

        $recipientUsername = ($usernameParamIsDefined && $destinataire)
            ? $destinataire->getUsername()
            : '';

        $dropZoneLabel = ($dropzoneParamIsDefined && $destinataire)
            ? ($destinataire->getDropzone()
                ? $destinataire->getDropzone()->getLabel()
                : '')
            : '';

        $usernameIsDefined = ($recipientUsername && $dropZoneLabel) ? ' / ' : '';
        return [
            'code' => $colis->getCode(),
            'labels' => [
                $recipientUsername . $usernameIsDefined . $dropZoneLabel
            ]
        ];
    }

    /**
     * @param EntityManagerInterface $entityManager
     * @param ArrivageDataService $arrivageDataService
     * @param $reloadArrivageId
     * @return array|null
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
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

    /**
     * @param Arrivage|Litige $entity
     * @param AttachmentService $attachmentService
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     */
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
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    public function saveColumnVisible(Request $request, EntityManagerInterface $entityManager): Response
    {
        if ($request->isXmlHttpRequest()) {
            if (!$this->userService->hasRightFunction(Menu::TRACA, Action::DISPLAY_ARRI)) {
                return $this->redirectToRoute('access_denied');
            }
            $data = json_decode($request->getContent(), true);

            $champs = array_keys($data);
            $user = $this->getUser();
            /** @var $user Utilisateur */
            $champs[] = "actions";
            $user->setColumnsVisibleForArrivage($champs);
            $entityManager->flush();

            return new JsonResponse();
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route(
     *     "/colonne-visible",
     *     name="get_column_visible_for_arrivage",
     *     options={"expose"=true},
     *     methods="GET",
     *     condition="request.isXmlHttpRequest()"
     * )
     * @return Response
     */
    public function getColumnVisible(): Response
    {
        if (!$this->userService->hasRightFunction(Menu::TRACA, Action::DISPLAY_ARRI)) {
            return $this->redirectToRoute('access_denied');
        }
        $user = $this->getUser();

        return new JsonResponse($user->getColumnsVisibleForArrivage());
    }

    /**
     * @Route("/api-columns", name="arrival_api_columns", options={"expose"=true}, methods="GET|POST")
     * @param Request $request
     * @param ArrivageDataService $arrivageDataService
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    public function apiColumns(Request $request,
                               ArrivageDataService $arrivageDataService,
                               EntityManagerInterface $entityManager): Response
    {
        if ($request->isXmlHttpRequest()) {
            if (!$this->userService->hasRightFunction(Menu::TRACA, Action::DISPLAY_ARRI)) {
                return $this->redirectToRoute('access_denied');
            }

            /** @var Utilisateur $currentUser */
            $currentUser = $this->getUser();
            $columns = $arrivageDataService->getColumnVisibleConfig($entityManager, $currentUser);
            return new JsonResponse($columns);
        }
        throw new NotFoundHttpException("404");
    }
}
