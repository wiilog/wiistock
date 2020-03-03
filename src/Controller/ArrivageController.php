<?php

namespace App\Controller;

use App\Entity\Action;
use App\Entity\Arrivage;
use App\Entity\CategorieStatut;
use App\Entity\CategoryType;
use App\Entity\ChampLibre;
use App\Entity\Chauffeur;
use App\Entity\Colis;
use App\Entity\FieldsParam;
use App\Entity\Fournisseur;
use App\Entity\Litige;
use App\Entity\LitigeHistoric;
use App\Entity\Menu;
use App\Entity\Nature;
use App\Entity\ParametrageGlobal;
use App\Entity\PieceJointe;

use App\Entity\Statut;
use App\Entity\Transporteur;
use App\Entity\Type;
use App\Entity\Urgence;
use App\Entity\Utilisateur;
use App\Entity\ValeurChampLibre;
use App\Repository\ArrivageRepository;
use App\Repository\ChampLibreRepository;
use App\Repository\ColisRepository;
use App\Repository\FieldsParamRepository;
use App\Repository\LitigeRepository;
use App\Repository\ChauffeurRepository;
use App\Repository\FournisseurRepository;
use App\Repository\MouvementTracaRepository;
use App\Repository\NatureRepository;
use App\Repository\ParametrageGlobalRepository;
use App\Repository\PieceJointeRepository;
use App\Repository\StatutRepository;
use App\Repository\TransporteurRepository;
use App\Repository\TypeRepository;
use App\Repository\UrgenceRepository;
use App\Repository\UtilisateurRepository;

use App\Repository\ValeurChampLibreRepository;
use App\Service\ArrivageDataService;
use App\Service\AttachmentService;
use App\Service\ColisService;
use App\Service\DashboardService;
use App\Service\GlobalParamService;
use App\Service\PDFGeneratorService;
use App\Service\SpecificService;
use App\Service\UserService;
use App\Service\MailerService;

use DateTime;
use DateTimeZone;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Exception;
use Knp\Bundle\SnappyBundle\Snappy\Response\PdfResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Entity;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;
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
     * @var ArrivageRepository
     */
    private $arrivageRepository;

    /**
     * @var UtilisateurRepository
     */
    private $utilisateurRepository;

    /**
     * @var StatutRepository
     */
    private $statutRepository;

    /**
     * @var FournisseurRepository
     */
    private $fournisseurRepository;

    /**
     * @var ChauffeurRepository
     */
    private $chauffeurRepository;

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
     * @var TypeRepository
     */
    private $typeRepository;

    /**
     * @var PieceJointeRepository
     */
    private $pieceJointeRepository;

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
     * @var ChampLibreRepository
     */
    private $champLibreRepository;

    /**
     * @var LitigeRepository
     */
    private $litigeRepository;

    /**
     * @var ColisRepository
     */
    private $colisRepository;

    /**
     * @var MouvementTracaRepository
     */
    private $mouvementTracaRepository;

    /**
     * @var UrgenceRepository
     */
    private $urgenceRepository;
    /**
     * @var NatureRepository
     */
    private $natureRepository;

    /**
     * @var DashboardService
     */
    private $dashboardService;

    /**
     * @var FieldsParamRepository
     */
    private $fieldsParamRepository;

    /**
     * @var ValeurChampLibreRepository
     */
    private $valeurChampLibreRepository;

    public function __construct(ValeurChampLibreRepository $valeurChampLibreRepository, FieldsParamRepository $fieldsParamRepository, ArrivageDataService $arrivageDataService, DashboardService $dashboardService, UrgenceRepository $urgenceRepository, AttachmentService $attachmentService, NatureRepository $natureRepository, MouvementTracaRepository $mouvementTracaRepository, ColisRepository $colisRepository, PieceJointeRepository $pieceJointeRepository, LitigeRepository $litigeRepository, ChampLibreRepository $champsLibreRepository, SpecificService $specificService, MailerService $mailerService, GlobalParamService $globalParamService, TypeRepository $typeRepository, ChauffeurRepository $chauffeurRepository, TransporteurRepository $transporteurRepository, FournisseurRepository $fournisseurRepository, StatutRepository $statutRepository, UtilisateurRepository $utilisateurRepository, UserService $userService, ArrivageRepository $arrivageRepository)
    {
        $this->fieldsParamRepository = $fieldsParamRepository;
        $this->dashboardService = $dashboardService;
        $this->urgenceRepository = $urgenceRepository;
        $this->specificService = $specificService;
        $this->globalParamService = $globalParamService;
        $this->userService = $userService;
        $this->arrivageRepository = $arrivageRepository;
        $this->utilisateurRepository = $utilisateurRepository;
        $this->statutRepository = $statutRepository;
        $this->fournisseurRepository = $fournisseurRepository;
        $this->transporteurRepository = $transporteurRepository;
        $this->chauffeurRepository = $chauffeurRepository;
        $this->typeRepository = $typeRepository;
        $this->mailerService = $mailerService;
        $this->champLibreRepository = $champsLibreRepository;
        $this->litigeRepository = $litigeRepository;
        $this->pieceJointeRepository = $pieceJointeRepository;
        $this->colisRepository = $colisRepository;
        $this->mouvementTracaRepository = $mouvementTracaRepository;
        $this->attachmentService = $attachmentService;
        $this->natureRepository = $natureRepository;
        $this->arrivageDataService = $arrivageDataService;
        $this->valeurChampLibreRepository = $valeurChampLibreRepository;
    }

    /**
     * @Route("/", name="arrivage_index")
     * @param EntityManagerInterface $entityManager
     * @return RedirectResponse|Response
     * @throws NonUniqueResultException
     */
    public function index(EntityManagerInterface $entityManager)
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
        $statutRepository = $entityManager->getRepository(Statut::class);
        $utilisateurRepository = $entityManager->getRepository(Utilisateur::class);

        $fieldsParam = $fieldsParamRepository->getByEntity(FieldsParam::ENTITY_CODE_ARRIVAGE);
        $paramGlobalRedirectAfterNewArrivage = $parametrageGlobalRepository->findOneByLabel(ParametrageGlobal::REDIRECT_AFTER_NEW_ARRIVAL);

        return $this->render('arrivage/index.html.twig', [
            'carriers' => $transporteurRepository->findAllSorted(),
            'chauffeurs' => $chauffeurRepository->findAllSorted(),
            'users' => $utilisateurRepository->findAllSorted(),
            'fournisseurs' => $fournisseurRepository->findAllSorted(),
            'typesLitige' => $typeRepository->findByCategoryLabel(CategoryType::LITIGE),
            'natures' => $natureRepository->findAll(),
            'statuts' => $statutRepository->findByCategorieName(CategorieStatut::ARRIVAGE),
            'fieldsParam' => $fieldsParam,
            'redirect' => $paramGlobalRedirectAfterNewArrivage ? $paramGlobalRedirectAfterNewArrivage->getValue() : true,
			'champsLibres' => $champLibreRepository->findByCategoryTypeLabels([CategoryType::ARRIVAGE]),
            'pageLengthForArrivage' => $this->getUser()->getPageLengthForArrivage(),
            'autoPrint' => $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::AUTO_PRINT_COLIS)
        ]);
    }

    /**
     * @Route("/api", name="arrivage_api", options={"expose"=true}, methods="GET|POST")
     * @param Request $request
     * @return Response
     * @throws NonUniqueResultException
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

            $fieldsParam = $this->fieldsParamRepository->getHiddenByEntity(FieldsParam::ENTITY_CODE_ARRIVAGE);
            $data['columnsToHide'] = $fieldsParam;

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
     * @param SpecificService $specificService
     * @param ArrivageDataService $arrivageDataService
     * @param ColisService $colisService
     * @return Response
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    public function new(Request $request,
                        EntityManagerInterface $entityManager,
                        AttachmentService $attachmentService,
                        UserService $userService,
                        SpecificService $specificService,
                        ArrivageDataService $arrivageDataService,
                        ColisService $colisService): Response
    {
        if ($request->isXmlHttpRequest()) {
            if (!$userService->hasRightFunction(Menu::TRACA, Action::CREATE)) {
                return $this->redirectToRoute('access_denied');
            }

            $data = $request->request->all();
            $parametrageGlobalRepository = $entityManager->getRepository(ParametrageGlobal::class);
            $urgenceRepository = $entityManager->getRepository(Urgence::class);
            $statutRepository = $entityManager->getRepository(Statut::class);
            $fournisseurRepository = $entityManager->getRepository(Fournisseur::class);
            $transporteurRepository = $entityManager->getRepository(Transporteur::class);
            $chauffeurRepository = $entityManager->getRepository(Chauffeur::class);
            $userRepository = $entityManager->getRepository(Utilisateur::class);
            $champLibreRepository = $entityManager->getRepository(ChampLibre::class);

            $isSEDCurrentClient = $specificService->isCurrentClientNameFunction(SpecificService::CLIENT_SAFRAN_ED);

            $date = new DateTime('now', new DateTimeZone('Europe/Paris'));
            $numeroArrivage = $date->format('ymdHis');

            $arrivage = new Arrivage();
            $arrivage
                ->setIsUrgent(false)
                ->setDate($date)
                ->setStatut($statutRepository->find($data['statut']))
                ->setUtilisateur($this->getUser())
                ->setNumeroArrivage($numeroArrivage)
                ->setCommentaire($data['commentaire'] ?? null);

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
            $noBL = $data['noBL'];
            if (!empty($data['noBL'])) {
                $arrivage->setNumeroBL(substr($noBL, 0, 64));
            }
            if (!empty($data['destinataire'])) {
                $arrivage->setDestinataire($userRepository->find($data['destinataire']));
            }
            if (!empty($data['acheteurs'])) {
                $acheteursId = explode(',', $data['acheteurs']);
                foreach ($acheteursId as $acheteurId) {
                    $arrivage->addAcheteur($userRepository->find($acheteurId));
                }
            }

            $entityManager->persist($arrivage);
            $entityManager->flush();

            $attachmentService->addAttachements($request->files, $arrivage);

            if ($arrivage->getNumeroBL()) {
                $urgencesMatching = $urgenceRepository->findUrgencesMatching($arrivage, $isSEDCurrentClient);
                if (!empty($urgencesMatching)) {
                    if (!$isSEDCurrentClient) {
                        $arrivageDataService->setArrivalUrgent($arrivage, $urgencesMatching);
                    }
                }
            }
            $entityManager->flush();

            $natures = array_reduce(
                isset($data['nature']) ? json_decode($data['nature'], true) : [],
                function (array $carry, $value) {
                    if (isset($value['id']) && isset($value['val'])) {
                        $carry[intval($value['id'])] = intval($value['val']);
                    }
                    return $carry;
                },
                []
            );

            $colisService->persistMultiColis($arrivage, $natures, $this->getUser());
            $entityManager->flush();

            $printColis = null;
            $printArrivage = null;
            if (isset($data['printColis']) && $data['printColis'] === 'true') {
                $printColis = true;
            }
            if ($data['printArrivage'] === 'true') {
                $printArrivage = true;
            }

            $champsLibresKey = array_keys($data);
            foreach ($champsLibresKey as $champs) {
                if (gettype($champs) === 'integer') {
                    $valeurChampLibre = new ValeurChampLibre();
                    $valeurChampLibre
                        ->setValeur(is_array($data[$champs]) ? implode(";", $data[$champs]) : $data[$champs])
                        ->addArrivage($arrivage)
                        ->setChampLibre($champLibreRepository->find($champs));
                    $entityManager->persist($valeurChampLibre);
                    $entityManager->flush();
                    $arrivage->addValeurChampLibre($valeurChampLibre);
                }
            }

            $paramGlobalRedirectAfterNewArrivage = $parametrageGlobalRepository->findOneByLabel(ParametrageGlobal::REDIRECT_AFTER_NEW_ARRIVAL);
            $statutConformeId = $statutRepository->getOneIdByCategorieNameAndStatusName(CategorieStatut::ARRIVAGE, Arrivage::STATUS_CONFORME);

            $data = [
                "redirectAfterAlert" => ($paramGlobalRedirectAfterNewArrivage ? $paramGlobalRedirectAfterNewArrivage->getValue() : true)
                    ? $this->generateUrl('arrivage_show', ['id' => $arrivage->getId()])
                    : null,
                'printColis' => $printColis,
                'printArrivage' => $printArrivage,
                'arrivageId' => $arrivage->getId(),
                'numeroArrivage' => $arrivage->getNumeroArrivage(),
                'champsLibresBlock' => $this->renderView('arrivage/champsLibresArrivage.html.twig', [
                    'champsLibres' => $champLibreRepository->findByCategoryTypeLabels([CategoryType::ARRIVAGE]),
                ]),
                'statutConformeId' => $statutConformeId,
                'alertConfig' => $this->createArrivalAlertConfig($arrivage, $isSEDCurrentClient, $urgencesMatching ?? [])
            ];
            return new JsonResponse($data);
        }
        throw new NotFoundHttpException('404 not found');
    }

    /**
     * @Route("/api-modifier", name="arrivage_edit_api", options={"expose"=true}, methods="GET|POST")
     * @param Request $request
     * @param ChampLibreRepository $champLibreRepository
     * @param ValeurChampLibreRepository $valeurChampLibreRepository
     * @return Response
     * @throws NonUniqueResultException
     */
    public function editApi(Request $request,
                            ChampLibreRepository $champLibreRepository,
                            ValeurChampLibreRepository $valeurChampLibreRepository): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::TRACA, Action::DISPLAY_ARRI)) {
                return $this->redirectToRoute('access_denied');
            }
            $arrivage = $this->arrivageRepository->find($data['id']);

            // construction de la chaîne de caractères pour alimenter le select2
            $acheteursUsernames = [];
            foreach ($arrivage->getAcheteurs() as $acheteur) {
                $acheteursUsernames[] = $acheteur->getUsername();
            }
            $fieldsParam = $this->fieldsParamRepository->getByEntity(FieldsParam::ENTITY_CODE_ARRIVAGE);

            $champsLibres = $champLibreRepository->findByCategoryTypeLabels([CategoryType::ARRIVAGE]);
            $champsLibresArray = [];
            foreach ($champsLibres as $champLibre) {
                $valeurChampArr = $valeurChampLibreRepository->getValueByArrivageAndChampLibre($arrivage, $champLibre);
                $champsLibresArray[] = [
                    'id' => $champLibre->getId(),
                    'label' => $champLibre->getLabel(),
                    'typage' => $champLibre->getTypage(),
                    'elements' => $champLibre->getElements() ?? '',
                    'requiredEdit' => $champLibre->getRequiredEdit(),
                    'valeurChampLibre' => $valeurChampArr,
                    'edit' => true
                ];
            }

            if ($this->userService->hasRightFunction(Menu::TRACA, Action::EDIT)) {
                $html = $this->renderView('arrivage/modalEditArrivageContent.html.twig', [
                    'arrivage' => $arrivage,
                    'attachements' => $this->pieceJointeRepository->findBy(['arrivage' => $arrivage]),
                    'utilisateurs' => $this->utilisateurRepository->findAllSorted(),
                    'fournisseurs' => $this->fournisseurRepository->findAllSorted(),
                    'transporteurs' => $this->transporteurRepository->findAllSorted(),
                    'chauffeurs' => $this->chauffeurRepository->findAllSorted(),
                    'typesLitige' => $this->typeRepository->findByCategoryLabel(CategoryType::LITIGE),
                    'statuts' => $this->statutRepository->findByCategorieName(CategorieStatut::ARRIVAGE),
                    'fieldsParam' => $fieldsParam,
                    'champsLibres' => $champsLibresArray
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
     * @param Arrivage $arrival
     * @param ArrivageDataService $arrivageDataService
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    public function patchUrgentArrival(Arrivage $arrival,
                                       ArrivageDataService $arrivageDataService,
                                       EntityManagerInterface $entityManager): Response {
        $success = false;
        if (!$arrival->getIsUrgent()) {
            if ($arrival->getNumeroBL()) {
                $urgenceRepository = $entityManager->getRepository(Urgence::class);
                $urgencesMatching = $urgenceRepository->findUrgencesMatching($arrival, true);
                if (!empty($urgencesMatching)) {
                    $success = true;
                    $arrivageDataService->setArrivalUrgent($arrival, $urgencesMatching);
                    $entityManager->flush();
                }
            }
        }

        $response = $this->getResponseReloadArrivage($arrival->getId());

        $response['success'] = $success;
        $response['alertConfig'] = $success ? $this->createArrivalAlertConfig($arrival, false, $urgencesMatching ?? []) : null;

        return new JsonResponse($response);
    }

    /**
     * @param Arrivage $arrivage
     * @param bool $isSEDCurrentClient
     * @param array $urgences
     * @return array
     */
    private function createArrivalAlertConfig(Arrivage $arrivage,
                                              bool $isSEDCurrentClient,
                                              array $urgences): array {
        $posts = array_map(
            function (Urgence $urgence) {
                return $urgence->getPostNb();
            },
            $urgences
        );
        $isArrivalUrgent = count($urgences);

        if ($isSEDCurrentClient && $isArrivalUrgent) {
			$nbPosts = count($posts);
			if ($nbPosts == 0) {
				$msgSedUrgent = "L'arrivage est-il urgent sur la commande " . $arrivage->getNumeroBL() . " ?";
			} else {
				if ($nbPosts == 1) {
					$msgSedUrgent = "Le poste <span class='bold'>" . $posts[0] . '</span> est urgent sur la commande <span class="bold">' . $arrivage->getNumeroBL() . "</span> .
					<br>L'avez-vous reçu dans cet arrivage ?";
				} else {
					$msgSedUrgent = "Les postes <span class='bold'>" . implode(', ', $posts) . '</span> sont urgents sur la commande <span class="bold">' . $arrivage->getNumeroBL() . "</span> .
					<br>Les avez-vous reçus dans cet arrivage ?";
				}
			}
		}

        return [
            'autoHide' => !$isSEDCurrentClient && !$isArrivalUrgent,
            'message' => (!$isSEDCurrentClient
                ? ($isArrivalUrgent
                    ? 'Arrivage URGENT enregistré avec succès.'
                    : 'Arrivage enregistré avec succès.')
                : ($isArrivalUrgent
                    ? ($msgSedUrgent ?? '')
                    : 'Arrivage enregistré avec succès.')),
            'iconType' => $isArrivalUrgent ? 'warning' : 'success',
            'modalType' => ($isSEDCurrentClient && $isArrivalUrgent) ? 'yes-no-question' : 'info',
            'arrivalId' => $arrivage->getId()
        ];
    }


    /**
     * @Route("/modifier", name="arrivage_edit", options={"expose"=true}, methods="GET|POST")
     * @param Request $request
     * @return Response
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    public function edit(Request $request): Response
    {
        if ($request->isXmlHttpRequest()) {
            if (!$this->userService->hasRightFunction(Menu::TRACA, Action::EDIT)) {
                return $this->redirectToRoute('access_denied');
            }
            $post = $request->request;
            $em = $this->getDoctrine()->getManager();
			$isSEDCurrentClient = $this->specificService->isCurrentClientNameFunction(SpecificService::CLIENT_SAFRAN_ED);

			$arrivage = $this->arrivageRepository->find($post->get('id'));

            $fournisseurId = $post->get('fournisseur');
            $transporteurId = $post->get('transporteur');
            $destinataireId = $post->get('destinataire');
            $statutId = $post->get('statut');
            $chauffeurId = $post->get('chauffeur');

            $oldNumeroBL = $arrivage->getNumeroBL();

            $arrivage
                ->setCommentaire($post->get('commentaire'))
                ->setNoTracking(substr($post->get('noTracking'), 0, 64))
                ->setNumeroBL(substr($post->get('noBL'), 0, 64))
                ->setFournisseur($fournisseurId ? $this->fournisseurRepository->find($fournisseurId) : null)
                ->setTransporteur($transporteurId ? $this->transporteurRepository->find($transporteurId) : null)
                ->setChauffeur($chauffeurId ? $this->chauffeurRepository->find($chauffeurId) : null)
                ->setStatut($statutId ? $this->statutRepository->find($statutId) : null)
                ->setDestinataire($destinataireId ? $this->utilisateurRepository->find($destinataireId) : null);

            $acheteurs = $post->get('acheteurs');
            // on détache les acheteurs existants...
            $existingAcheteurs = $arrivage->getAcheteurs();

            foreach ($existingAcheteurs as $existingAcheteur) {
                $arrivage->removeAcheteur($existingAcheteur);
            }
            if (!empty($acheteurs)) {
                // ... et on ajoute ceux sélectionnés
                $listAcheteurs = explode(',', $acheteurs);
                foreach ($listAcheteurs as $acheteur) {
                    $arrivage->addAcheteur($this->utilisateurRepository->findOneByUsername($acheteur));
                }
            }

            $em->flush();

            $listAttachmentIdToKeep = $post->get('files') ?? [];

            $attachments = $arrivage->getAttachements()->toArray();
            foreach ($attachments as $attachment) {
                /** @var PieceJointe $attachment */
                if (!in_array($attachment->getId(), $listAttachmentIdToKeep)) {
                    $this->attachmentService->removeAndDeleteAttachment($attachment, $arrivage);
                }
            }

            $this->attachmentService->addAttachements($request->files, $arrivage);

            if ($arrivage->getNumeroBL() &&
                ($oldNumeroBL !== $arrivage->getNumeroBL())) {
				$urgencesMatching = $this->urgenceRepository->findUrgencesMatching($arrivage, $isSEDCurrentClient);
				$arrivage->clearUrgences();
                if (!empty($urgencesMatching)) {
                    if (!$isSEDCurrentClient) {
						$this->arrivageDataService->setArrivalUrgent($arrivage, $urgencesMatching);
					}
                } else {
                    $arrivage->setIsUrgent(false);
                }
            }

            $em->flush();

            $champLibreKey = array_keys($post->all());
            foreach ($champLibreKey as $champ) {
                if (gettype($champ) === 'integer') {
                    $champLibre = $this->champLibreRepository->find($champ);
                    $valeurChampLibre = $this->valeurChampLibreRepository->findOneByArrivageAndChampLibre($arrivage, $champLibre);
                    // si la valeur n'existe pas, on la crée
                    if (!$valeurChampLibre) {
                        $valeurChampLibre = new ValeurChampLibre();
                        $valeurChampLibre
                            ->addArrivage($arrivage)
                            ->setChampLibre($this->champLibreRepository->find($champ));
                        $em->persist($valeurChampLibre);
                    }
                    $valeurChampLibre->setValeur(is_array($post->get($champ)) ? implode(";", $post->get($champ)) : $post->get($champ));
                    $em->flush();
                }
            }

            $listTypes = $this->typeRepository->getIdAndLabelByCategoryLabel(CategoryType::ARRIVAGE);
            $champsLibres = [];
            foreach ($listTypes as $type) {
                $listChampsLibres = $this->champLibreRepository->findByType($type['id']);

                foreach ($listChampsLibres as $champLibre) {
                    $valeurChampLibre = $this->valeurChampLibreRepository->findOneByArrivageAndChampLibre($arrivage, $champLibre);

                    $champsLibres[] = [
                        'id' => $champLibre->getId(),
                        'label' => $champLibre->getLabel(),
                        'typage' => $champLibre->getTypage(),
                        'elements' => $champLibre->getElements() ? $champLibre->getElements() : '',
                        'defaultValue' => $champLibre->getDefaultValue(),
                        'valeurChampLibre' => $valeurChampLibre,
                    ];
                }
            }

            $fieldsParam = $this->fieldsParamRepository->getByEntity(FieldsParam::ENTITY_CODE_ARRIVAGE);

            $response = [
                'entete' => $this->renderView('arrivage/enteteArrivage.html.twig', [
                    'arrivage' => $arrivage,
                    'canBeDeleted' => $this->arrivageRepository->countLitigesUnsolvedByArrivage($arrivage) == 0,
                    'fieldsParam' => $fieldsParam,
                    'champsLibres' => $champsLibres
                ]),
				'alertConfig' => $this->createArrivalAlertConfig($arrivage, $isSEDCurrentClient, $urgencesMatching ?? [])
            ];
            return new JsonResponse($response);
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/supprimer", name="arrivage_delete", options={"expose"=true},methods={"GET","POST"})
     */
    public function delete(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $arrivage = $this->arrivageRepository->find($data['arrivage']);

            if (!$this->userService->hasRightFunction(Menu::TRACA, Action::DELETE)) {
                return $this->redirectToRoute('access_denied');
            }

            $canBeDeleted = ($this->arrivageRepository->countLitigesUnsolvedByArrivage($arrivage) == 0);

            if ($canBeDeleted) {
                $entityManager = $this->getDoctrine()->getManager();
                foreach ($arrivage->getColis() as $colis) {
                    $litiges = $colis->getLitiges();
                    foreach ($this->mouvementTracaRepository->getByColisAndPriorToDate($colis->getCode(), $arrivage->getDate()) as $mvtToDelete) {
                        $entityManager->remove($mvtToDelete);
                    }
                    $entityManager->remove($colis);
                    foreach ($litiges as $litige) {
                        $entityManager->remove($litige);
                    }
                }
                foreach ($arrivage->getAttachements() as $attachement) {
                    $this->attachmentService->removeAndDeleteAttachment($attachement, $arrivage);
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
     */
    public function depose(Request $request): Response
    {
        if ($request->isXmlHttpRequest()) {
            $em = $this->getDoctrine()->getManager();

            $fileNames = [];
            $path = "../public/uploads/attachements";

            $id = (int)$request->request->get('id');
            $arrivage = $this->arrivageRepository->find($id);

            for ($i = 0; $i < count($request->files); $i++) {
                $file = $request->files->get('file' . $i);
                if ($file) {
                    if ($file->getClientOriginalExtension()) {
                        $filename = uniqid() . "." . $file->getClientOriginalExtension();
                    } else {
                        $filename = uniqid();
                    }
                    $file->move($path, $filename);

                    $pj = new PieceJointe();
                    $pj
                        ->setFileName($filename)
                        ->setOriginalName($file->getClientOriginalName())
                        ->setArrivage($arrivage);
                    $em->persist($pj);

                    $fileNames[] = ['name' => $filename, 'originalName' => $file->getClientOriginalName()];
                }
            }
            $em->flush();

            $html = '';
            foreach ($fileNames as $fileName) {
                $html .= $this->renderView('arrivage/attachementLine.html.twig', [
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

    private function sendMailToAcheteurs(Litige $litige)
    {
        //TODO HM getId ?
        $acheteursEmail = $this->litigeRepository->getAcheteursArrivageByLitigeId($litige->getId());
        foreach ($acheteursEmail as $email) {
            $title = 'Un litige a été déclaré sur un arrivage vous concernant :';

            $this->mailerService->sendMail(
                'FOLLOW GT // Litige sur arrivage',
                $this->renderView('mails/mailLitiges.html.twig', [
                    'litiges' => [$litige],
                    'title' => $title,
                    'urlSuffix' => 'arrivage'
                ]),
                $email
            );
        }
    }

    /**
     * @Route("/ajoute-commentaire", name="add_comment",  options={"expose"=true}, methods="GET|POST")
     */
    public function addComment(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $response = '';

            // spécifique SAFRAN CERAMICS ajout de commentaire
            $isSafran = $this->specificService->isCurrentClientNameFunction(SpecificService::CLIENT_SAFRAN_CS);
            if ($isSafran) {
                $type = $this->typeRepository->find($data['typeLitigeId']);
                $response = $type->getDescription();
            }

            return new JsonResponse($response);
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/lister-colis", name="arrivage_list_colis_api", options={"expose"=true})
     */
    public function listColisByArrivage(Request $request)
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $arrivage = $this->arrivageRepository->find($data['id']);

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
     */
    public function displayAttachmentForNew(Request $request)
    {
        if ($request->isXmlHttpRequest()) {
            $em = $this->getDoctrine()->getManager();

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
                    $html .= $this->renderView('arrivage/attachementLine.html.twig', [
                        'pjName' => $filename,
                        'originalName' => $file->getClientOriginalName()
                    ]);
                    $pj = new PieceJointe();
                    $pj
                        ->setOriginalName($file->getClientOriginalName())
                        ->setFileName($filename);
                    $em->persist($pj);
                }
                $em->flush();
            }

            return new JsonResponse($html);
        } else {
            throw new NotFoundHttpException('404');
        }
    }

    /**
     * @Route("/arrivage-infos", name="get_arrivages_for_csv", options={"expose"=true}, methods={"GET","POST"})
     */
    public function getArrivageIntels(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $dateMin = $data['dateMin'] . ' 00:00:00';
            $dateMax = $data['dateMax'] . ' 23:59:59';

            $dateTimeMin = DateTime::createFromFormat('d/m/Y H:i:s', $dateMin);
            $dateTimeMax = DateTime::createFromFormat('d/m/Y H:i:s', $dateMax);

            $arrivages = $this->arrivageRepository->findByDates($dateTimeMin, $dateTimeMax);

            $headers = [];
            // en-têtes champs fixes
            $headers = array_merge($headers, ['n° arrivage', 'destinataire', 'fournisseur', 'transporteur', 'chauffeur', 'n° tracking transporteur',
                'n° commande/BL', 'acheteurs', 'statut', 'commentaire', 'date', 'utilisateur']);

            $data = [];
            $data[] = $headers;

            foreach ($arrivages as $arrivage) {
                $arrivageData = [];

                $arrivageData[] = $arrivage->getNumeroArrivage() ?? ' ';
                $arrivageData[] = $arrivage->getDestinataire() ? $arrivage->getDestinataire()->getUsername() : ' ';
                $arrivageData[] = $arrivage->getFournisseur() ? $arrivage->getFournisseur()->getNom() : ' ';
                $arrivageData[] = $arrivage->getTransporteur() ? $arrivage->getTransporteur()->getLabel() : ' ';
                $arrivageData[] = $arrivage->getChauffeur() ? $arrivage->getChauffeur()->getNom() . ' ' . $arrivage->getChauffeur()->getPrenom() : '';
                $arrivageData[] = $arrivage->getNoTracking() ? $arrivage->getNoTracking() : '';
                $arrivageData[] = $arrivage->getNumeroBL() ? $arrivage->getNumeroBL() : '';

                $acheteurs = $arrivage->getAcheteurs();
                $acheteurData = [];
                foreach ($acheteurs as $acheteur) {
                    $acheteurData[] = $acheteur->getUsername();
                }
                $arrivageData[] = implode(' / ', $acheteurData);
                $arrivageData[] = $arrivage->getStatut()->getNom();
                $arrivageData[] = strip_tags($arrivage->getCommentaire());
                $arrivageData[] = $arrivage->getDate()->format('Y/m/d-H:i:s');
                $arrivageData[] = $arrivage->getUtilisateur()->getUsername();

                $data[] = $arrivageData;
            }
            return new JsonResponse($data);
        } else {
            throw new NotFoundHttpException('404');
        }
    }

    /**
     * @param Arrivage $arrivage
     * @param bool $printColis
     * @param bool $printArrivage
     * @return JsonResponse
     * @throws NonUniqueResultException
     * @throws NoResultException
     * @Route("/voir/{id}/{printColis}/{printArrivage}", name="arrivage_show", options={"expose"=true}, methods={"GET", "POST"})
     */
    public function show(Arrivage $arrivage, bool $printColis = false, bool $printArrivage = false): Response
    {
        if (!$this->userService->hasRightFunction(Menu::TRACA, Action::LIST_ALL)
            && !in_array($this->getUser(), $arrivage->getAcheteurs()->toArray())) {
            return $this->redirectToRoute('access_denied');
        }

        $paramGlobalRepository = $this->getDoctrine()->getRepository(ParametrageGlobal::class);

        $acheteursNames = [];
        foreach ($arrivage->getAcheteurs() as $user) {
            $acheteursNames[] = $user->getUsername();
        }
        $fieldsParam = $this->fieldsParamRepository->getByEntity(FieldsParam::ENTITY_CODE_ARRIVAGE);

        $listTypes = $this->typeRepository->getIdAndLabelByCategoryLabel(CategoryType::ARRIVAGE);
        $champsLibres = [];
        foreach ($listTypes as $type) {
            $listChampsLibres = $this->champLibreRepository->findByType($type['id']);

            foreach ($listChampsLibres as $champLibre) {
                $valeurChampLibre = $this->valeurChampLibreRepository->findOneByArrivageAndChampLibre($arrivage, $champLibre);

                $champsLibres[] = [
                    'id' => $champLibre->getId(),
                    'label' => $champLibre->getLabel(),
                    'typage' => $champLibre->getTypage(),
                    'elements' => $champLibre->getElements() ? $champLibre->getElements() : '',
                    'defaultValue' => $champLibre->getDefaultValue(),
                    'valeurChampLibre' => $valeurChampLibre,
                ];
            }
        }

        return $this->render("arrivage/show.html.twig",
            [
                'arrivage' => $arrivage,
                'typesLitige' => $this->typeRepository->findByCategoryLabel(CategoryType::LITIGE),
                'acheteurs' => $acheteursNames,
                'statusLitige' => $this->statutRepository->findByCategorieName(CategorieStatut::LITIGE_ARR, true),
                'allColis' => $arrivage->getColis(),
                'natures' => $this->natureRepository->findAll(),
                'printColis' => $printColis,
                'printArrivage' => $printArrivage,
                'canBeDeleted' => $this->arrivageRepository->countLitigesUnsolvedByArrivage($arrivage) == 0,
                'fieldsParam' => $fieldsParam,
				'champsLibres' => $champsLibres,
				'defaultLitigeStatusId' => $paramGlobalRepository->getOneParamByLabel(ParametrageGlobal::DEFAULT_STATUT_LITIGE_ARR)
			]);
    }

    /**
     * @Route("/creer-litige", name="litige_new", options={"expose"=true}, methods={"POST"})
     */
    public function newLitige(Request $request): Response
    {
        if ($request->isXmlHttpRequest()) {
            if (!$this->userService->hasRightFunction(Menu::TRACA, Action::CREATE)) {
                return $this->redirectToRoute('access_denied');
            }

            $post = $request->request;
            $em = $this->getDoctrine()->getManager();

            $litige = new Litige();
            $litige
                ->setStatus($this->statutRepository->find($post->get('statutLitige')))
                ->setType($this->typeRepository->find($post->get('typeLitige')))
                ->setCreationDate(new DateTime('now'));
            $arrivage = null;
            if (!empty($colis = $post->get('colisLitige'))) {
                $listColisId = explode(',', $colis);
                foreach ($listColisId as $colisId) {
                    $litige->addColis($this->colisRepository->find($colisId));
                    $arrivage = $this->colisRepository->find($colisId)->getArrivage();
                }
            }
            if ($post->get('emergency')) {
                $litige->setEmergencyTriggered($post->get('emergency') === 'true');
            }
            if ((!$litige->getStatus() || !$litige->getStatus()->isTreated()) && $arrivage) {
                $arrivage->setStatut($this->statutRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::ARRIVAGE, Arrivage::STATUS_LITIGE));
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
                $em->persist($histo);
            }

            $em->persist($litige);
            $em->flush();

            $this->attachmentService->addAttachements($request->files, null, $litige);
            $em->flush();

            $this->sendMailToAcheteurs($litige);

            $arrivageResponse = $this->getResponseReloadArrivage($request->query->get('reloadArrivage'));
            $response = $arrivageResponse ? $arrivageResponse : [];

            return new JsonResponse($response);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/supprimer-litige", name="litige_delete", options={"expose"=true}, methods="GET|POST")
     */
    public function deleteLitige(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::QUALI, Action::DELETE)) {
                return $this->redirectToRoute('access_denied');
            }

            $litige = $this->litigeRepository->find($data['litige']);

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
     * @param ColisService $colisService
     * @return JsonResponse
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    public function addColis(Request $request,
                             EntityManagerInterface $entityManager,
                             ColisService $colisService)
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::TRACA, Action::EDIT)) {
                return $this->redirectToRoute('access_denied');
            }

            $arrivage = $this->arrivageRepository->find($data['arrivageId']);



            $natures = array_reduce(
                array_keys($data),
                function (array $carry, string $key) use ($data) {
                    $keyIntval = intval($key);
                    if (!empty($keyIntval)) {
                        $carry[$key] = $data[$key];
                    }
                    return $carry;
                },
                []
            );

            $persistedColis = $colisService->persistMultiColis($arrivage, $natures, $this->getUser());
            $entityManager->flush();

            return new JsonResponse([
                'colisIds' => array_map(function (Colis $colis) {
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
     */
    public function apiArrivageLitiges(Request $request, Arrivage $arrivage): Response
    {
        if ($request->isXmlHttpRequest()) {

            /** @var Litige[] $litiges */
            $litiges = $this->litigeRepository->findByArrivage($arrivage);

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
     */
    public function apiEditLitige(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {

            $litige = $this->litigeRepository->find($data['litigeId']);

            $colisCode = [];
            foreach ($litige->getColis() as $colis) {
                $colisCode[] = $colis->getId();
            }

            $arrivage = $this->arrivageRepository->find($data['arrivageId']);

            $hasRightToTreatLitige = $this->userService->hasRightFunction(Menu::QUALI, Action::TREAT_LITIGE);

            $html = $this->renderView('arrivage/modalEditLitigeContent.html.twig', [
                'litige' => $litige,
                'hasRightToTreatLitige' => $hasRightToTreatLitige,
                'typesLitige' => $this->typeRepository->findByCategoryLabel(CategoryType::LITIGE),
                'statusLitige' => $this->statutRepository->findByCategorieName(CategorieStatut::LITIGE_ARR, true),
                'attachements' => $this->pieceJointeRepository->findBy(['litige' => $litige]),
                'colis' => $arrivage->getColis(),
            ]);

            return new JsonResponse(['html' => $html, 'colis' => $colisCode]);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/modifier-litige", name="litige_edit_arrivage",  options={"expose"=true}, methods="GET|POST")
     */
    public function editLitige(Request $request): Response
    {
        if ($request->isXmlHttpRequest()) {
            if (!$this->userService->hasRightFunction(Menu::QUALI, Action::EDIT)) {
                return $this->redirectToRoute('access_denied');
            }
            $post = $request->request;
            $em = $this->getDoctrine()->getManager();

            $litige = $this->litigeRepository->find($post->get('id'));
            $typeBefore = $litige->getType()->getId();
            $typeBeforeName = $litige->getType()->getLabel();
            $typeAfter = (int)$post->get('typeLitige');
            $statutBefore = $litige->getStatus()->getId();
            $statutBeforeName = $litige->getStatus()->getNom();
            $statutAfter = (int)$post->get('statutLitige');
            $litige->setUpdateDate(new DateTime('now'));

            $newStatus = $this->statutRepository->find($statutAfter);
            $hasRightToTreatLitige = $this->userService->hasRightFunction(Menu::QUALI, Action::TREAT_LITIGE);
            if ($hasRightToTreatLitige || !$newStatus->getTreated()) {
                $litige->setStatus($newStatus);
            }

            if ($hasRightToTreatLitige) {
                $litige->setType($this->typeRepository->find($typeAfter));
            }

            if (!empty($newColis = $post->get('colis'))) {
                // on détache les colis existants...
                $existingColis = $litige->getColis();
                foreach ($existingColis as $existingColi) {
                    $litige->removeColis($existingColi);
                }
                // ... et on ajoute ceux sélectionnés
                $listColis = explode(',', $newColis);
                foreach ($listColis as $colisId) {
                    $litige->addColis($this->colisRepository->find($colisId));
                }
            }

            $em->flush();

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
                $em->persist($histoLitige);
                $em->flush();
            }

            $listAttachmentIdToKeep = $post->get('files') ?? [];

            $attachments = $litige->getAttachements()->toArray();
            foreach ($attachments as $attachment) {
                /** @var PieceJointe $attachment */
                if (!in_array($attachment->getId(), $listAttachmentIdToKeep)) {
                    $this->attachmentService->removeAndDeleteAttachment($attachment, null, $litige);
                }
            }

            $this->attachmentService->addAttachements($request->files, null, $litige);
            $em->flush();

            $response = $this->getResponseReloadArrivage($request->query->get('reloadArrivage'));

            return new JsonResponse($response);
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/depose-pj-litige", name="litige_depose", options={"expose"=true}, methods="GET|POST")
     */
    public function deposeLitige(Request $request): Response
    {
        if ($request->isXmlHttpRequest()) {
            $em = $this->getDoctrine()->getManager();

            $fileNames = [];
            $path = "../public/uploads/attachements";

            $id = (int)$request->request->get('id');
            $litige = $this->litigeRepository->find($id);

            for ($i = 0; $i < count($request->files); $i++) {
                $file = $request->files->get('file' . $i);
                if ($file) {
                    if ($file->getClientOriginalExtension()) {
                        $filename = uniqid() . "." . $file->getClientOriginalExtension();
                    } else {
                        $filename = uniqid();
                    }
                    $file->move($path, $filename);

                    $pj = new PieceJointe();
                    $pj
                        ->setFileName($filename)
                        ->setOriginalName($file->getClientOriginalName())
                        ->setLitige($litige);
                    $em->persist($pj);

                    $fileNames[] = ['name' => $filename, 'originalName' => $file->getClientOriginalName()];
                }
            }
            $em->flush();

            $html = '';
            foreach ($fileNames as $fileName) {
                $html .= $this->renderView('arrivage/attachementLine.html.twig', [
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
     * @throws Exception
     */
    public function apiColis(Request $request, Arrivage $arrivage): Response
    {
        if ($request->isXmlHttpRequest()) {
            $listColis = $arrivage->getColis()->toArray();

            $rows = [];
            foreach ($listColis as $colis) {
                /** @var $colis Colis */
                $mouvement = $this->mouvementTracaRepository->getLastByColis($colis->getCode());
                $rows[] = [
                    'nature' => $colis->getNature() ? $colis->getNature()->getLabel() : '',
                    'code' => $colis->getCode(),
                    'lastMvtDate' => $mouvement ? ($mouvement->getDatetime() ? $mouvement->getDatetime()->format('d/m/Y H:i') : '') : '',
                    'lastLocation' => $mouvement ? ($mouvement->getEmplacement() ? $mouvement->getEmplacement()->getLabel() : '') : '',
                    'operator' => $mouvement ? ($mouvement->getOperateur() ? $mouvement->getOperateur()->getUsername() : '') : '',
                    'actions' => $this->renderView('arrivage/datatableColisRow.html.twig', [
                        'arrivageId' => $arrivage->getId(),
                        'colisId' => $colis->getId()
                    ])
                ];
            }
            $data['data'] = $rows;

            return new JsonResponse($data);
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/{arrivage}/etiquette", name="print_arrivage_bar_code", options={"expose"=true}, methods="GET")
     * @param Arrivage $arrivage
     * @param PDFGeneratorService $PDFGeneratorService
     * @return Response
     * @throws LoaderError
     * @throws NonUniqueResultException
     * @throws RuntimeError
     * @throws SyntaxError
     * @throws NoResultException
     */
    public function printArrivageBarCode(Arrivage $arrivage,
                                         PDFGeneratorService $PDFGeneratorService): Response
    {
        $barcodeConfigs = [[
            'code' => $arrivage->getNumeroArrivage()
        ]];

        $fileName = $PDFGeneratorService->getBarcodeFileName($barcodeConfigs, 'arrivage');

        return new PdfResponse(
            $PDFGeneratorService->generatePDFBarCodes($fileName, $barcodeConfigs),
            $fileName
        );
    }

    /**
     * @Route(
     *     "/{arrivage}/etiquettes-colis/{colisId}",
     *     name="print_arrivage_colis_bar_codes",
     *     methods={"GET"},
     *     defaults={"colisId": null},
     *     options={"expose"=true}
     * )
     * @Entity("colis", expr="colisId ? repository.find(colisId) : colisId")
     *
     * @param PDFGeneratorService $PDFGeneratorService
     * @param ColisRepository $colisRepository
     * @param Request $request
     * @param Arrivage $arrivage
     * @param Colis|null $colis
     * @return PdfResponse
     * @throws LoaderError
     * @throws NoResultException
     * @throws NonUniqueResultException
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function printArrivageColisBarCodes(PDFGeneratorService $PDFGeneratorService,
                                               ColisRepository $colisRepository,
                                               Request $request,
                                               Arrivage $arrivage,
                                               ?Colis $colis): PdfResponse
    {
        $colisListStr = $request->query->get('colisList');
        if (!empty($colisListStr)) {
            $colisList = array_map(function ($id) use ($colisRepository) {
                return $colisRepository->find($id);
            }, explode(',', $colisListStr));
        } else if (isset($colis)) {
            $colisList = [$colis];
        }

        if (!empty($colisList)) {
            /** @var Colis $colisRequested */
            foreach ($colisList as $colisRequested) {
                if ($colisRequested->getArrivage()->getId() !== $arrivage->getId()) {
                    throw new NotFoundHttpException("404");
                }
            }
        }

        $barcodeConfigs = array_map(
            function (Colis $colisInArrivage) use ($arrivage) {
                $acheteurs = $arrivage->getAcheteurs();
                $acheteursCounter = $acheteurs->count();
                return [
                    'code' => $colisInArrivage->getCode(),
                    'labels' => [
                        ($acheteursCounter === 1)
                            ? ($acheteurs->first()->getDropzone()
                            ? $acheteurs->first()->getDropzone()->getLabel()
                            : '')
                            : ''
                    ]
                ];
            },
            !empty($colisList)
                ? $colisList
                : $arrivage->getColis()->toArray()
        );

        $fileName = $PDFGeneratorService->getBarcodeFileName($barcodeConfigs, 'colis_arrivage');

        return new PdfResponse(
            $PDFGeneratorService->generatePDFBarCodes($fileName, $barcodeConfigs),
            $fileName
        );
    }

    private function getResponseReloadArrivage($reloadArrivageId): ?array
    {
        $response = null;
        if (isset($reloadArrivageId)) {
            $arrivageToReload = $this->arrivageRepository->find($reloadArrivageId);
            if ($arrivageToReload) {
                $fieldsParam = $this->fieldsParamRepository->getByEntity(FieldsParam::ENTITY_CODE_ARRIVAGE);

                $listTypes = $this->typeRepository->getIdAndLabelByCategoryLabel(CategoryType::ARRIVAGE);
                $champsLibres = [];
                foreach ($listTypes as $type) {
                    $listChampsLibres = $this->champLibreRepository->findByType($type['id']);

                    foreach ($listChampsLibres as $champLibre) {
                        $valeurChampLibre = $this->valeurChampLibreRepository->findOneByArrivageAndChampLibre($arrivageToReload, $champLibre);

                        $champsLibres[] = [
                            'id' => $champLibre->getId(),
                            'label' => $champLibre->getLabel(),
                            'typage' => $champLibre->getTypage(),
                            'elements' => $champLibre->getElements() ? $champLibre->getElements() : '',
                            'defaultValue' => $champLibre->getDefaultValue(),
                            'valeurChampLibre' => $valeurChampLibre,
                        ];
                    }
                }

                $response = [
                    'entete' => $this->renderView('arrivage/enteteArrivage.html.twig', [
                        'arrivage' => $arrivageToReload,
                        'canBeDeleted' => $this->arrivageRepository->countLitigesUnsolvedByArrivage($arrivageToReload) == 0,
                        'fieldsParam' => $fieldsParam,
                        'champsLibres' => $champsLibres
                    ]),
                ];
            }
        }

        return $response;
    }

}
