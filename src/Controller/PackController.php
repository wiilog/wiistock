<?php

namespace App\Controller;

use App\Annotation\HasPermission;
use App\Entity\Action;
use App\Entity\Arrivage;
use App\Entity\Article;
use App\Entity\CategorieStatut;
use App\Entity\CategoryType;
use App\Entity\Menu;
use App\Entity\Nature;
use App\Entity\Pack;

use App\Entity\PreparationOrder\Preparation;
use App\Entity\PreparationOrder\PreparationOrderArticleLine;
use App\Entity\Project;
use App\Entity\Statut;
use App\Entity\TrackingMovement;
use App\Entity\Type;
use App\Entity\Utilisateur;
use App\Helper\FormatHelper;
use App\Service\CSVExportService;
use App\Service\LanguageService;
use App\Service\PackService;
use App\Service\TrackingMovementService;

use App\Service\VisibleColumnService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;
use DateTime;
use App\Service\TranslationService;
use Symfony\Component\Security\Core\Authentication\Token\PreAuthenticatedToken;
use Throwable;
use WiiCommon\Helper\Stream;

/**
 * @Route("/colis")
 */
class PackController extends AbstractController
{

    /**
     * @Route("/", name="pack_index", options={"expose"=true})
     * @HasPermission({Menu::TRACA, Action::DISPLAY_PACK})
     */
    public function index(EntityManagerInterface $entityManager, LanguageService $languageService)
    {
        $naturesRepository = $entityManager->getRepository(Nature::class);
        $typeRepository = $entityManager->getRepository(Type::class);
        $projectRepository = $entityManager->getRepository(Project::class);

        return $this->render('pack/index.html.twig', [
            'userLanguage' => $this->getUser()->getLanguage(),
            'defaultLanguage' => $languageService->getDefaultLanguage(),
            'natures' => $naturesRepository->findBy([], ['label' => 'ASC']),
            'types' => $typeRepository->findByCategoryLabels([CategoryType::ARRIVAGE]),
            'projects' => $projectRepository->findAll(),
        ]);
    }

    /**
     * @Route("/api", name="pack_api", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::TRACA, Action::DISPLAY_PACK}, mode=HasPermission::IN_JSON)
     */
    public function api(Request $request, PackService $packService): Response
    {
        $data = $packService->getDataForDatatable($request->request);

        return new JsonResponse($data);
    }

    /**
     * @Route("/csv", name="export_packs", options={"expose"=true}, methods={"GET"})
     * @HasPermission({Menu::TRACA, Action::EXPORT})
     */
    public function printCSVPacks(Request $request,
                                  CSVExportService $CSVExportService,
                                  TrackingMovementService $trackingMovementService,
                                  TranslationService $translation,
                                  EntityManagerInterface $entityManager): Response
    {
        $dateMin = $request->query->get('dateMin');
        $dateMax = $request->query->get('dateMax');

        try {
            $dateTimeMin = DateTime::createFromFormat('Y-m-d H:i:s', $dateMin . ' 00:00:00');
            $dateTimeMax = DateTime::createFromFormat('Y-m-d H:i:s', $dateMax . ' 23:59:59');
        } catch (Throwable $throwable) {
        }

        if (isset($dateTimeMin) && isset($dateTimeMax)) {

            $csvHeader = [
                $translation->translate('Traçabilité', 'Unités logistiques', 'Onglet "Unités logistiques"', "Numéro d'UL", false),
                $translation->translate('Traçabilité', 'Général', 'Nature', false),
                $translation->translate( 'Traçabilité', 'Général', 'Date dernier mouvement', false),
                $translation->translate( 'Traçabilité', 'Général', 'Issu de', false),
                $translation->translate( 'Traçabilité', 'Général', 'Issu de (numéro)', false),
                $translation->translate( 'Traçabilité', 'Général', 'Emplacement', false),
            ];

            return $CSVExportService->streamResponse(
                function ($output) use ($CSVExportService, $translation, $entityManager, $dateTimeMin, $dateTimeMax, $trackingMovementService) {
                    $packRepository = $entityManager->getRepository(Pack::class);
                    $packs = $packRepository->getPacksByDates($dateTimeMin, $dateTimeMax);
                    $trackingMouvementRepository = $entityManager->getRepository(TrackingMovement::class);

                    foreach ($packs as $pack) {
                        $trackingMouvment = $trackingMouvementRepository->find($pack['fromTo']);
                        $mvtData = $trackingMovementService->getFromColumnData($trackingMouvment);
                        $pack['fromLabel'] = $mvtData['fromLabel'];
                        $pack['fromTo'] = $mvtData['from'];
                        $this->putPackLine($output, $CSVExportService, $pack);
                    }
                }, 'export_colis.csv',
                $csvHeader
            );
        }

        throw new BadRequestHttpException();
    }

    /**
     * @Route("/{packCode}", name="get_pack_intel", options={"expose"=true}, methods={"GET"}, condition="request.isXmlHttpRequest()")
     */
    public function getPackIntel(EntityManagerInterface $entityManager,
                                 string $packCode): JsonResponse
    {
        $packRepository = $entityManager->getRepository(Pack::class);
        $naturesRepository = $entityManager->getRepository(Nature::class);
        $natures = $naturesRepository->findBy([], ['label' => 'ASC']);
        $uniqueNature = count($natures) === 1;
        $pack = $packRepository->findOneBy(['code' => $packCode]);

        if ($pack && $pack->getNature()) {
            $nature = [
                'id' => $pack->getNature()->getId(),
                'label' => $this->getFormatter()->nature($pack->getNature()),
            ];
        } else {
            $nature = ($uniqueNature ? [
                'id' => $natures[0]->getId(),
                'label' => $this->getFormatter()->nature($natures[0]),
            ] : null);
        }

        return new JsonResponse([
            'success' => true,
            'pack' => [
                'code' => $packCode,
                'quantity' => $pack ? $pack->getQuantity() : null,
                'comment' => $pack ? $pack->getComment() : null,
                'weight' => $pack ? $pack->getWeight() : null,
                'volume' => $pack ? $pack->getVolume() : null,
                'nature' => $nature
            ]
        ]);
    }

    /**
     * @Route("/api-modifier", name="pack_edit_api", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::TRACA, Action::EDIT}, mode=HasPermission::IN_JSON)
     */
    public function editApi(Request $request,
                            EntityManagerInterface $entityManager): Response
    {
        if ($data = json_decode($request->getContent(), true)) {
            $packRepository = $entityManager->getRepository(Pack::class);
            $preparationOrderArticleLineRepository = $entityManager->getRepository(PreparationOrderArticleLine::class);
            $natureRepository = $entityManager->getRepository(Nature::class);
            $projectRepository = $entityManager->getRepository(Project::class);
            $statusRepository = $entityManager->getRepository(Statut::class);
            $pack = $packRepository->find($data['id']);
            $projects = Stream::from($projectRepository->findAll())
                ->map(fn(Project $project) => [
                    "label" => $project->getCode(),
                    "value" => $project->getId(),
                    "selected" => $pack->getProject() === $project
                ]);
            $status = $statusRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::PREPARATION, Preparation::STATUT_A_TRAITER);
            $disabledProject = $preparationOrderArticleLineRepository->getPreparationOrderArticleLine($pack, [$status->getId()]);
            $articlesQuantity = Stream::from($pack->getChildArticles())->reduce(fn(int $carry, Article $article) => $carry + $article->getQuantite());
            $html = $this->renderView('pack/modalEditPackContent.html.twig', [
                'natures' => $natureRepository->findBy([], ['label' => 'ASC']),
                'pack' => $pack,
                'projects' => $projects,
                'containsArticle' => $pack->getChildArticles()->isEmpty(),
                'articlesQuantity' => $articlesQuantity,
                'disabledProject' => !empty($disabledProject)
            ]);

            return new JsonResponse($html);
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/modifier", name="pack_edit", options={"expose"=true}, methods="POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::TRACA, Action::EDIT}, mode=HasPermission::IN_JSON)
     */
    public function edit(Request $request,
                         EntityManagerInterface $entityManager,
                         PackService $packService,
                         TranslationService $translation): Response
    {
        $data = json_decode($request->getContent(), true);
        $response = [];
        $packRepository = $entityManager->getRepository(Pack::class);
        $natureRepository = $entityManager->getRepository(Nature::class);
        $projectRepository = $entityManager->getRepository(Project::class);

        $pack = $packRepository->find($data['id']);
        $packDataIsValid = $packService->checkPackDataBeforeEdition($data);
        if (!empty($pack) && $packDataIsValid['success']) {
            $packService
                ->editPack($data, $natureRepository, $projectRepository, $pack);

            $entityManager->flush();
            $response = [
                'success' => true,
                'msg' => $translation->translate('Traçabilité', 'Unités logistiques', 'Onglet "Unités logistiques"', "L'unité logistique {1} a bien été modifiée", [
                    1 => $pack->getCode()
                ])

            ];
        } else if (!$packDataIsValid['success']) {
            $response = $packDataIsValid;
        }
        return new JsonResponse($response);
    }

    /**
     * @Route("/supprimer", name="pack_delete", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::TRACA, Action::DELETE}, mode=HasPermission::IN_JSON)
     */
    public function delete(Request $request,
                           EntityManagerInterface $entityManager,
                           TranslationService $translation): Response
    {
        if ($data = json_decode($request->getContent(), true)) {
            $packRepository = $entityManager->getRepository(Pack::class);
            $arrivageRepository = $entityManager->getRepository(Arrivage::class);

            $pack = $packRepository->find($data['pack']);
            $packCode = $pack->getCode();
            $arrivage = isset($data['arrivage']) ? $arrivageRepository->find($data['arrivage']) : null;
            if (!$pack->getTrackingMovements()->isEmpty()) {
                $msg = $translation->translate('Traçabilité', 'Unités logistiques', 'Onglet "Unités logistiques"', "Cette unité logistique est référencé dans un ou plusieurs mouvements de traçabilité");
            }

            if (!$pack->getDispatchPacks()->isEmpty()) {
                $msg = $translation->translate('Traçabilité', 'Unités logistiques', 'Onglet "Unités logistiques"', "Cette unité logistique est référencé dans un ou plusieurs acheminements");
            }

            if (!$pack->getDisputes()->isEmpty()) {
                $msg = $translation->translate('Traçabilité', 'Unités logistiques', 'Onglet "Unités logistiques"', "Cette unité logistique est référencé dans un ou plusieurs litiges");
            }
            if ($pack->getArrivage() && $arrivage !== $pack->getArrivage()) {
                $msg = $translation->translate('Traçabilité', 'Unités logistiques', 'Onglet "Unités logistiques"', 'Cette unité logistique est utilisé dans l\'arrivage {1}', [
                    1 => $pack->getArrivage()->getNumeroArrivage()
                ]);
            }
            if ($pack->getTransportDeliveryOrderPack() ) {
                $msg = $translation->translate('Traçabilité', 'Unités logistiques', 'Onglet "Unités logistiques"', 'Cette unité logistique est utilisé dans un ordre de livraison');
            }

            if (isset($msg)) {
                return $this->json([
                    "success" => false,
                    "msg" => $msg
                ]);
            }

            $entityManager->remove($pack);
            $entityManager->flush();

            return new JsonResponse([
                'success' => true,"",
                'msg' => $translation->translate('Traçabilité', 'Unités logistiques', 'Onglet "Unités logistiques"', "L'unité logistique {1} a bien été supprimée", [
                        1 => $packCode
                    ])
            ]);
        }

        throw new BadRequestHttpException();
    }

    private function putPackLine($handle, CSVExportService $csvService, array $pack)
    {
        $line = [
            $pack['code'],
            $pack['nature'],
            FormatHelper::datetime($pack['lastMvtDate'], "", false, $this->getUser()),
            $pack['fromLabel'],
            $pack['fromTo'],
            $pack['location']
        ];
        $csvService->putLine($handle, $line);
    }

    /**
     * @Route("/group_history/{pack}", name="group_history_api", options={"expose"=true}, methods="GET|POST")
     */
    public function groupHistory(Request $request, PackService $packService, $pack): Response {
        if ($request->isXmlHttpRequest()) {
            $data = $packService->getGroupHistoryForDatatable($pack, $request->request);
            return $this->json($data);
        }
        throw new BadRequestHttpException();
    }

    #[Route("/project_history/{pack}", name: "project_history_api", options: ["expose" => true], methods: "GET|POST", condition: "request.isXmlHttpRequest()")]
    public function projectHistory(Request $request, PackService $packService, $pack): Response {
        if ($request->isXmlHttpRequest()) {
            $data = $packService->getProjectHistoryForDatatable($pack, $request->request);
            return $this->json($data);
        }
        throw new BadRequestHttpException();
    }

    #[Route("/colonne-visible", name: "save_column_visible_for_pack", options: ["expose" => true], methods: "POST", condition: "request.isXmlHttpRequest()")]
    #[HasPermission([Menu::TRACA, Action::DISPLAY_PACK], mode: HasPermission::IN_JSON)]
    public function saveColumnVisible(Request $request,
                                      EntityManagerInterface $entityManager,
                                      VisibleColumnService $visibleColumnService,
                                      TranslationService $translation): Response
    {
        $data = json_decode($request->getContent(), true);

        $fields = array_keys($data);
        /** @var Utilisateur $user */
        $user = $this->getUser();

        $visibleColumnService->setVisibleColumns('arrivalPack', $fields, $user);
        $entityManager->flush();

        return $this->json([
            'success' => true,
            'msg' => $translation->translate('Général', null, 'Zone liste', 'Vos préférences de colonnes à afficher ont bien été sauvegardées')
        ]);
    }
}
