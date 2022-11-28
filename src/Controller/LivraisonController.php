<?php

namespace App\Controller;

use App\Annotation\HasPermission;
use App\Entity\Action;
use App\Entity\CategorieStatut;
use App\Entity\CategoryType;
use App\Entity\DeliveryRequest\DeliveryRequestArticleLine;
use App\Entity\DeliveryRequest\Demande;
use App\Entity\Emplacement;
use App\Entity\Livraison;
use App\Entity\Menu;
use App\Entity\Pack;
use App\Entity\PreparationOrder\PreparationOrderArticleLine;
use App\Entity\PreparationOrder\PreparationOrderReferenceLine;
use App\Entity\Statut;
use App\Entity\Type;
use App\Entity\Utilisateur;
use App\Exceptions\NegativeQuantityException;
use App\Helper\FormatHelper;
use App\Service\CSVExportService;
use App\Service\LivraisonService;
use App\Service\LivraisonsManagerService;
use App\Service\PreparationsManagerService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Throwable;
use WiiCommon\Helper\Stream;


/**
 * @Route("/livraison")
 */
class LivraisonController extends AbstractController {

    /**
     * @Route("/liste/{demandId}", name="livraison_index", methods={"GET", "POST"})
     * @HasPermission({Menu::ORDRE, Action::DISPLAY_ORDRE_LIVR})
     */
    public function index(EntityManagerInterface $entityManager,
                          string                 $demandId = null): Response {

        $statutRepository = $entityManager->getRepository(Statut::class);
        $typeRepository = $entityManager->getRepository(Type::class);
        $demandeRepository = $entityManager->getRepository(Demande::class);

        $filterDemand = $demandId
            ? $demandeRepository->find($demandId)
            : null;

        return $this->render('livraison/index.html.twig', [
            'filterDemandId' => isset($filterDemand) ? $demandId : null,
            'filterDemandValue' => isset($filterDemand) ? $filterDemand->getNumero() : null,
            'filtersDisabled' => isset($filterDemand),
            'displayDemandFilter' => true,
            'statuts' => $statutRepository->findByCategorieName(CategorieStatut::ORDRE_LIVRAISON),
            'types' => $typeRepository->findByCategoryLabels([CategoryType::DEMANDE_LIVRAISON]),
        ]);
    }

    /**
     * @Route("/finir/{id}", name="livraison_finish", options={"expose"=true}, methods={"POST"}, condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::ORDRE, Action::EDIT}, mode=HasPermission::IN_JSON)
     */
    public function finish(Livraison                $livraison,
                           LivraisonsManagerService $livraisonsManager,
                           EntityManagerInterface   $entityManager): Response {
        if ($livraison->getStatut()?->getCode() === Livraison::STATUT_A_TRAITER) {
            try {
                $dateEnd = new DateTime('now');
                /** @var Utilisateur $user */
                $user = $this->getUser();
                $livraisonsManager->finishLivraison(
                    $user,
                    $livraison,
                    $dateEnd,
                    $livraison->getDemande()->getDestination()
                );
                $entityManager->flush();
            } catch (NegativeQuantityException $exception) {
                $barcode = $exception->getArticle()->getBarCode();
                return new JsonResponse([
                    'success' => false,
                    'message' => "La quantité en stock de l'article $barcode est inférieure à la quantité prélevée.",
                ]);
            }
        }

        return new JsonResponse([
            'success' => true,
            'redirect' => $this->generateUrl('livraison_show', [
                'id' => $livraison->getId(),
            ]),
        ]);
    }

    /**
     * @Route("/api", name="livraison_api", options={"expose"=true}, methods={"GET", "POST"}, condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::ORDRE, Action::DISPLAY_ORDRE_LIVR}, mode=HasPermission::IN_JSON)
     */
    public function api(Request          $request,
                        LivraisonService $livraisonService): Response {
        $filterDemandId = $request->request->get('filterDemand');
        $data = $livraisonService->getDataForDatatable($request->request, $filterDemandId);
        return new JsonResponse($data);
    }

    #[Route("/delivery-order-logistic-unit-api", name: "delivery_order_logistic_unit_api", options: ["expose" => true], methods: "GET", condition: "request.isXmlHttpRequest()")]
    #[HasPermission([Menu::ORDRE, Action::DISPLAY_ORDRE_LIVR], mode: HasPermission::IN_JSON)]
    public function logisticUnitApi(Request $request, EntityManagerInterface $manager): Response {
        $deliveryOrder = $manager->find(Livraison::class, $request->query->get('id'));
        $preparationOrder = $deliveryOrder->getPreparation();
        $logisticsUnits = Stream::from($preparationOrder->getArticleLines())
            ->filterMap(fn(PreparationOrderArticleLine $articleLine) => $articleLine->getPack())
            ->unique();

        $lines = Stream::from($logisticsUnits)
            ->map(fn(Pack $logisticUnit) => [
                'pack' => [
                    "packId" => $logisticUnit->getId(),
                    "code" => $logisticUnit->getCode() ?? null,
                    "location" => $this->formatService->location($logisticUnit->getLastDrop()?->getEmplacement()),
                    "project" => $logisticUnit->getProject()?->getCode() ?? null,
                    "nature" => $this->formatService->nature($logisticUnit->getNature()),
                    "color" => $logisticUnit->getNature()?->getColor() ?? null,
                    "quantity" => $logisticUnit->getQuantity() ?? null,
                    "quantityArticleInLocation" => count($logisticUnit->getChildArticles()) ?? null,
                    "articles" => Stream::from($preparationOrder->getArticleLines())
                        ->filterMap(function(PreparationOrderArticleLine $line) use ($logisticUnit) {
                            $article = $line->getArticle();
                            if ($article->getCurrentLogisticUnit()?->getId() == $logisticUnit->getId()) {
                                return [
                                    "reference" => $article->getArticleFournisseur()->getReferenceArticle()->getReference(),
                                    "barCode" => $article->getBarCode() ?: '',
                                    "label" => $article->getLabel() ?: '',
                                    "quantity" => $line->getPickedQuantity(),
                                    "Actions" => $this->renderView('livraison/datatableLivraisonListeRow.html.twig', [
                                        'id' => $article->getId(),
                                    ]),
                                ];
                            } else {
                                return null;
                            }
                        })
                        ->toArray(),
                ],
            ])->toArray();

        return $this->json([
            "success" => true,
            "html" => $this->renderView("livraison/line-list.html.twig", [
                "lines" => $lines,
            ]),
        ]);
    }

    /**
     * @Route("/api-article/{id}", name="livraison_article_api", options={"expose"=true}, methods={"GET", "POST"}, condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::ORDRE, Action::DISPLAY_ORDRE_LIVR}, mode=HasPermission::IN_JSON)
     */
    public function apiArticle(Livraison $livraison): Response {
        $preparation = $livraison->getPreparation();
        $data = [];
        if ($preparation) {
            $rows = [];
            /** @var PreparationOrderArticleLine $articleLine */
            foreach ($preparation->getArticleLines() as $articleLine) {
                $article = $articleLine->getArticle();
                if ($articleLine->getQuantityToPick() !== 0 && $articleLine->getPickedQuantity() !== 0 && !$article->getCurrentLogisticUnit()) {
                    $rows[] = [
                        "reference" => $article->getArticleFournisseur()->getReferenceArticle() ? $article->getArticleFournisseur()->getReferenceArticle()->getReference() : '',
                        "barCode" => $article->getBarCode() ?: '',
                        "label" => $article->getLabel() ?: '',
                        "location" => FormatHelper::location($article->getEmplacement()),
                        "quantity" => $articleLine->getPickedQuantity(),
                        "Actions" => $this->renderView('livraison/datatableLivraisonListeRow.html.twig', [
                            'id' => $article->getId(),
                        ]),
                    ];
                }
            }

            /** @var PreparationOrderReferenceLine $referenceLine */
            foreach ($preparation->getReferenceLines() as $referenceLine) {
                if ($referenceLine->getPickedQuantity() > 0) {
                    $reference = $referenceLine->getReference();
                    $rows[] = [
                        "reference" => $reference->getReference(),
                        "label" => $reference->getLibelle(),
                        "barCode" => $reference->getBarCode() ?: '',
                        "location" => FormatHelper::location($reference->getEmplacement()),
                        "quantity" => $referenceLine->getPickedQuantity(),
                        "Actions" => $this->renderView('livraison/datatableLivraisonListeRow.html.twig', [
                            'refArticleId' => $reference->getId(),
                        ]),
                    ];
                }
            }

            $data['data'] = $rows;
        }
        else {
            $data = false; //TODO gérer retour message erreur
        }
        return new JsonResponse($data);
    }

    /**
     * @Route("/voir/{id}", name="livraison_show", methods={"GET","POST"})
     * @HasPermission({Menu::ORDRE, Action::DISPLAY_ORDRE_LIVR})
     */
    public function show(Livraison $livraison): Response {
        $demande = $livraison->getDemande();

        $utilisateurPreparation = $livraison->getPreparation()?->getUtilisateur();
        $destination = $demande?->getDestination();
        $dateLivraison = $livraison->getDateFin();
        $comment = $demande->getCommentaire();

        return $this->render('livraison/show.html.twig', [
            'demande' => $demande,
            'livraison' => $livraison,
            'preparation' => $livraison->getPreparation(),
            'finished' => $livraison->isCompleted(),
            'headerConfig' => [
                ['label' => 'Numéro', 'value' => $livraison->getNumero()],
                ['label' => 'Statut', 'value' => $livraison->getStatut() ? ucfirst($this->getFormatter()->status($livraison->getStatut())) : ''],
                ['label' => 'Opérateur', 'value' => $utilisateurPreparation ? $utilisateurPreparation->getUsername() : ''],
                ['label' => 'Demandeur', 'value' => FormatHelper::deliveryRequester($demande)],
                ['label' => 'Point de livraison', 'value' => $destination ? $destination->getLabel() : ''],
                ['label' => 'Date de livraison', 'value' => $dateLivraison ? $dateLivraison->format('d/m/Y') : ''],
                ['label' => 'Date attendue', 'value' => $demande ? $demande->getExpectedAt()?->format('d/m/Y') : ''],
                ['label' => 'Projet', 'value' => $demande ? $demande->getProject()?->getCode() : ''],
                [
                    'label' => 'Commentaire',
                    'value' => $comment ?: '',
                    'isRaw' => true,
                    'colClass' => 'col-sm-6 col-12',
                    'isScrollable' => true,
                    'isNeededNotEmpty' => true,
                ],
            ],
        ]);
    }

    /**
     * @Route("/{livraison}", name="livraison_delete", options={"expose"=true}, methods={"DELETE"}, condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::ORDRE, Action::DELETE}, mode=HasPermission::IN_JSON)
     */
    public function delete(Request                    $request,
                           Livraison                  $livraison,
                           LivraisonsManagerService   $livraisonsManager,
                           PreparationsManagerService $preparationsManager,
                           EntityManagerInterface     $entityManager): Response {
        $emplacementRepository = $entityManager->getRepository(Emplacement::class);
        $preparation = $livraison->getpreparation();

        /** @var Utilisateur $user */
        $user = $this->getUser();

        $livraisonStatus = $livraison->getStatut();
        $demande = $livraison->getDemande();

        $articleDestinationId = $request->request->get('dropLocation');
        $articlesDestination = !empty($articleDestinationId) ? $emplacementRepository->find($articleDestinationId) : null;
        if (empty($articlesDestination)) {
            $articlesDestination = isset($demande) ? $demande->getDestination() : null;
        }

        if (isset($livraisonStatus)
            && isset($articlesDestination)) {
            $livraisonsManager->resetStockMovementsOnDelete(
                $livraison,
                $articlesDestination,
                $user,
                $entityManager
            );
        }

        $preparationsManager->resetPreparationToTreat($preparation, $entityManager);

        $entityManager->flush();

        $preparation->setLivraison(null);
        $entityManager->remove($livraison);
        $entityManager->flush();

        return new JsonResponse ([
            'success' => true,
            'redirect' => $this->generateUrl('preparation_show', [
                'id' => $preparation->getId(),
                ]),
        ]);
    }

    /**
     * @Route("/csv", name="get_delivery_order_csv", options={"expose"=true}, methods={"GET"})
     */
    public function getDeliveryOrderCSV(Request                $request,
                                        CSVExportService       $CSVExportService,
                                        EntityManagerInterface $entityManager,
                                        LivraisonService       $livraisonService): Response {
        $dateMin = $request->query->get('dateMin');
        $dateMax = $request->query->get('dateMax');

        try {
            $dateTimeMin = DateTime::createFromFormat('Y-m-d H:i:s', $dateMin . ' 00:00:00');
            $dateTimeMax = DateTime::createFromFormat('Y-m-d H:i:s', $dateMax . ' 23:59:59');
        } catch (Throwable $throwable) {
        }
        if (isset($dateTimeMin) && isset($dateTimeMax)) {

            $csvHeader = [
                'numéro',
                'statut',
                'date création',
                'date de livraison',
                'date de la demande',
                'demandeur',
                'opérateur',
                'type',
                'commentaire',
                'référence',
                'libellé',
                'emplacement',
                'quantité à livrer',
                'quantité en stock',
                'code-barre',
            ];

            return $CSVExportService->streamResponse(
                function($output) use ($entityManager, $dateTimeMin, $dateTimeMax, $CSVExportService, $livraisonService) {
                    $livraisonRepository = $entityManager->getRepository(Livraison::class);
                    $deliveryIterator = $livraisonRepository->iterateByDates($dateTimeMin, $dateTimeMax);

                    foreach ($deliveryIterator as $delivery) {
                        $livraisonService->putLivraisonLine($output, $CSVExportService, $delivery);
                    }
                },
                'export_Ordres_Livraison.csv',
                $csvHeader
            );
        }
        else {
            throw new NotFoundHttpException('404');
        }
    }

}
