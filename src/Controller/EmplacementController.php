<?php

namespace App\Controller;

use App\Entity\Action;
use App\Entity\Article;
use App\Entity\Emplacement;
use App\Entity\FiltreSup;
use App\Entity\Menu;

use App\Entity\ReferenceArticle;
use App\Repository\CollecteRepository;
use App\Repository\DemandeRepository;
use App\Repository\EmplacementRepository;
use App\Repository\LivraisonRepository;
use App\Repository\MouvementStockRepository;
use App\Repository\MouvementTracaRepository;

use App\Service\GlobalParamService;
use App\Service\PDFGeneratorService;
use App\Service\UserService;
use App\Service\EmplacementDataService;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Knp\Bundle\SnappyBundle\Snappy\Response\PdfResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use App\Repository\ArticleRepository;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

/**
 * @Route("/emplacement")
 */
class EmplacementController extends AbstractController
{
    /**
     * @var EmplacementDataService
     */
    private $emplacementDataService;

    /**
     * @var DemandeRepository
     */
    private $demandeRepository;

    /**
     * @var LivraisonRepository
     */
    private $livraisonRepository;

    /**
     * @var CollecteRepository
     */
    private $collecteRepository;

    /**
     * @var MouvementStockRepository
     */
    private $mouvementStockRepository;

    /**
     * @var MouvementTracaRepository
     */
    private $mouvementTracaRepository;

    /**
     * @var UserService
     */
    private $userService;

    /**
     * @var GlobalParamService
     */
    private $globalParamService;

    private $entityManager;

    public function __construct(MouvementTracaRepository $mouvementTracaRepository,
                                GlobalParamService $globalParamService,
                                EmplacementDataService $emplacementDataService,
                                UserService $userService,
                                DemandeRepository $demandeRepository,
                                LivraisonRepository $livraisonRepository,
                                CollecteRepository $collecteRepository,
                                EntityManagerInterface $entityManager,
                                MouvementStockRepository $mouvementStockRepository)
    {
        $this->emplacementDataService = $emplacementDataService;
        $this->userService = $userService;
        $this->entityManager = $entityManager;
        $this->demandeRepository = $demandeRepository;
        $this->livraisonRepository = $livraisonRepository;
        $this->collecteRepository = $collecteRepository;
        $this->mouvementStockRepository = $mouvementStockRepository;
        $this->globalParamService = $globalParamService;
        $this->mouvementTracaRepository = $mouvementTracaRepository;
    }

    /**
     * @Route("/api", name="emplacement_api", options={"expose"=true}, methods="GET|POST")
     */
    public function api(Request $request): Response
    {
        if ($request->isXmlHttpRequest()) {
            if (!$this->userService->hasRightFunction(Menu::REFERENTIEL, Action::DISPLAY_EMPL)) {
                return $this->redirectToRoute('access_denied');
            }
            $data = $this->emplacementDataService->getDataForDatatable($request->request);

            return new JsonResponse($data);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/", name="emplacement_index", methods="GET")
     * @param EntityManagerInterface $entityManager
     * @return Response
     * @throws NonUniqueResultException
     */
    public function index(EntityManagerInterface $entityManager): Response
    {
        if (!$this->userService->hasRightFunction(Menu::REFERENTIEL, Action::DISPLAY_EMPL)) {
            return $this->redirectToRoute('access_denied');
        }

        $filtreSupRepository = $entityManager->getRepository(FiltreSup::class);

        $filterStatus = $filtreSupRepository->findOnebyFieldAndPageAndUser(FiltreSup::FIELD_STATUT, EmplacementDataService::PAGE_EMPLACEMENT, $this->getUser());
        $active = $filterStatus ? $filterStatus->getValue() : false;

		return $this->render('emplacement/index.html.twig', [
			'active' => $active
		]);
    }

    /**
     * @Route("/creer", name="emplacement_new", options={"expose"=true}, methods="GET|POST")
     */
    public function new(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::REFERENTIEL, Action::CREATE)) {
                return $this->redirectToRoute('access_denied');
            }


            $errorResponse = $this->checkLocationLabel($data["Label"] ?? null);
            if ($errorResponse) {
                return $errorResponse;
            }

            $dateMaxTime = !empty($data['dateMaxTime']) ? $data['dateMaxTime'] : null;
            $errorResponse = $this->checkMaxTime($dateMaxTime);
            if ($errorResponse) {
                return $errorResponse;
            }

            $em = $this->getDoctrine()->getManager();
            $emplacement = new Emplacement();
            $emplacement
				->setLabel($data["Label"])
				->setDescription($data["Description"])
				->setIsActive(true)
                ->setDateMaxTime($dateMaxTime)
				->setIsDeliveryPoint($data["isDeliveryPoint"]);

            $em->persist($emplacement);
            $em->flush();
            return new JsonResponse(true);
        }

        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/api-modifier", name="emplacement_api_edit", options={"expose"=true}, methods="GET|POST")
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    public function apiEdit(Request $request,
                            EntityManagerInterface $entityManager): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::REFERENTIEL, Action::CREATE)) {
                return $this->redirectToRoute('access_denied');
            }

            $emplacementRepository = $entityManager->getRepository(Emplacement::class);

            $emplacement = $emplacementRepository->find($data['id']);
            $json = $this->renderView('emplacement/modalEditEmplacementContent.html.twig', [
                'emplacement' => $emplacement,
            ]);

            return new JsonResponse($json);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/edit", name="emplacement_edit", options={"expose"=true}, methods="GET|POST")
     */
    public function edit(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::REFERENTIEL, Action::EDIT)) {
                return $this->redirectToRoute('access_denied');
            }

            $emplacementRepository = $this->entityManager->getRepository(Emplacement::class);

            $errorResponse = $this->checkLocationLabel($data["Label"] ?? null, $data['id']);
            if ($errorResponse) {
                return $errorResponse;
            }

            $dateMaxTime = !empty($data['dateMaxTime']) ? $data['dateMaxTime'] : null;
            $errorResponse = $this->checkMaxTime($dateMaxTime);
            if ($errorResponse) {
                return $errorResponse;
            }

            $emplacement = $emplacementRepository->find($data['id']);
            $emplacement
                ->setLabel($data["Label"])
                ->setDescription($data["Description"])
            	->setIsDeliveryPoint($data["isDeliveryPoint"])
                ->setDateMaxTime($dateMaxTime)
				->setIsActive($data['isActive']);

            $em = $this->getDoctrine()->getManager();
            $em->flush();
            return new JsonResponse();
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/verification", name="emplacement_check_delete", options={"expose"=true}, methods="GET|POST")
     */
    public function checkEmplacementCanBeDeleted(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $emplacementId = json_decode($request->getContent(), true)) {

            if (!$this->userService->hasRightFunction(Menu::REFERENTIEL, Action::DISPLAY_EMPL)) {
                return $this->redirectToRoute('access_denied');
            }

            $isUsedBy = $this->isEmplacementUsed($emplacementId);
            if (empty($isUsedBy)) {
                $delete = true;
                $html = $this->renderView('emplacement/modalDeleteEmplacementRight.html.twig');
            } else {
                $delete = false;
                $html = $this->renderView('emplacement/modalDeleteEmplacementWrong.html.twig', [
                    'delete' => false,
                    'isUsedBy' => $isUsedBy
                ]);
            }

            return new JsonResponse(['delete' => $delete, 'html' => $html]);
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @param int $emplacementId
     * @return array
     */
    private function isEmplacementUsed($emplacementId) {
        $referenceArticleRepository = $this->entityManager->getRepository(ReferenceArticle::class);
        $articleRepository = $this->entityManager->getRepository(Article::class);

        $usedBy = [];

        $demandes = $this->demandeRepository->countByEmplacement($emplacementId);
        if ($demandes > 0) $usedBy[] = 'demandes';

        $livraisons = $this->livraisonRepository->countByEmplacement($emplacementId);
        if ($livraisons > 0) $usedBy[] = 'livraisons';

        $collectes = $this->collecteRepository->countByEmplacement($emplacementId);
        if ($collectes > 0) $usedBy[] = 'collectes';

        $mouvementsStock = $this->mouvementStockRepository->countByEmplacement($emplacementId);
        if ($mouvementsStock > 0) $usedBy[] = 'mouvements de stock';

        $mouvementsStock = $this->mouvementTracaRepository->countByEmplacement($emplacementId);
        if ($mouvementsStock > 0) $usedBy[] = 'mouvements de traçabilité';

        $refArticle = $referenceArticleRepository->countByEmplacement($emplacementId);
        if ($refArticle > 0)$usedBy[] = 'références article';

        $articles = $articleRepository->countByEmplacement($emplacementId);
        if ($articles > 0) $usedBy[] ='articles';

        return $usedBy;
    }

    /**
     * @Route("/supprimer", name="emplacement_delete", options={"expose"=true}, methods="GET|POST")
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    public function delete(Request $request,
                           EntityManagerInterface $entityManager): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::REFERENTIEL, Action::DELETE)) {
                return $this->redirectToRoute('access_denied');
            }
            $response = [];

            $emplacementRepository = $entityManager->getRepository(Emplacement::class);

            if ($emplacementId = (int)$data['emplacement']) {
                $emplacement = $emplacementRepository->find($emplacementId);

                if ($emplacement) {
					$usedEmplacement = $this->isEmplacementUsed($emplacementId);

					if (!empty($usedEmplacement)) {
						$emplacement->setIsActive(false);
					} else {
						$entityManager->remove($emplacement);
						$response['delete'] = $emplacementId;
					}
					$entityManager->flush();
				}
            }

            return new JsonResponse($response);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/autocomplete", name="get_emplacement", options={"expose"=true})
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return JsonResponse
     */
    public function getRefArticles(Request $request,
                                   EntityManagerInterface $entityManager)
    {
    	if ($request->isXmlHttpRequest()) {

            $search = $request->query->get('term');

            $emplacementRepository = $entityManager->getRepository(Emplacement::class);
            $emplacement = $emplacementRepository->getIdAndLabelActiveBySearch($search);
            return new JsonResponse(['results' => $emplacement]);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/etiquettes", name="print_locations_bar_codes", options={"expose"=true}, methods={"GET"})
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @param PDFGeneratorService $PDFGeneratorService
     * @return PdfResponse
     * @throws LoaderError
     * @throws NonUniqueResultException
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function printLocationsBarCodes(Request $request,
                                           EntityManagerInterface $entityManager,
                                           PDFGeneratorService $PDFGeneratorService): PdfResponse {
        $listEmplacements = explode(',', $request->query->get('listEmplacements') ?? '');
        $start = $request->query->get('start');
        $length = $request->query->get('length');

        $emplacementRepository = $entityManager->getRepository(Emplacement::class);

        if (!empty($listEmplacements) && isset($start) && isset($length)) {
            $barCodeConfigs = array_map(
                function (Emplacement $location) {
                    return ['code' => $location->getLabel()];
                },
                array_slice($emplacementRepository->findByIds($listEmplacements), $start, $length)
            );

            $fileName = $PDFGeneratorService->getBarcodeFileName($barCodeConfigs, 'emplacements');

            return new PdfResponse(
                $PDFGeneratorService->generatePDFBarCodes($fileName, $barCodeConfigs),
                $fileName
            );
        }
        else {
            throw new NotFoundHttpException('Aucune étiquette à imprimer');
        }
    }

    /**
     * @Route("/{location}/etiquette", name="print_single_location_bar_code", options={"expose"=true}, methods={"GET"})
     * @param Emplacement $location
     * @param PDFGeneratorService $PDFGeneratorService
     * @return PdfResponse
     * @throws LoaderError
     * @throws NoResultException
     * @throws NonUniqueResultException
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function printSingleLocationBarCode(Emplacement $location,
                                               PDFGeneratorService $PDFGeneratorService): PdfResponse {
        $barCodeConfigs = [['code' => $location->getLabel()]];

        $fileName = $PDFGeneratorService->getBarcodeFileName($barCodeConfigs, 'emplacements');

        return new PdfResponse(
            $PDFGeneratorService->generatePDFBarCodes($fileName, $barCodeConfigs),
            $fileName
        );
    }

    private function checkLocationLabel(?string $label, $locationId = null) {
        $labelTrimmed = $label ? trim($label) : null;
        if (!empty($labelTrimmed)) {
            $emplacementRepository = $this->entityManager->getRepository(Emplacement::class);
            $emplacementAlreadyExist = $emplacementRepository->countByLabel($label, $locationId);
            if ($emplacementAlreadyExist) {
                return new JsonResponse([
                    'success' => false,
                    'message' => "Ce nom d'emplacement existe déjà. Veuillez en choisir un autre."
                ]);
            }
        }
        else {
            return new JsonResponse([
                'success' => false,
                'message' => "Vous devez donner un nom valide."
            ]);
        }
        return null;
    }

    private function checkMaxTime(?string $dateMaxTime) {
        if (!empty($dateMaxTime)) {
            $matchHours = '\d+';
            $matchMinutes = '([0-5][0-9])';
            $matchHoursMinutes = "$matchHours:$matchMinutes";
            $resultFormat = preg_match(
                "/^$matchHoursMinutes$/",
                $dateMaxTime
            );
            if (empty($resultFormat)) {
                return new JsonResponse([
                    'success' => false,
                    'message' => "Le délai saisi est invalide."
                ]);
            }
        }
    }
}
