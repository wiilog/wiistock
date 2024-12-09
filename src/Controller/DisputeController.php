<?php

namespace App\Controller;

use App\Annotation\HasPermission;
use App\Entity\Action;
use App\Entity\Article;
use App\Entity\CategorieStatut;
use App\Entity\CategoryType;
use App\Entity\Dispute;
use App\Entity\FiltreSup;
use App\Entity\Menu;
use App\Entity\DisputeHistoryRecord;

use App\Entity\ReceptionReferenceArticle;
use App\Entity\Statut;
use App\Entity\Transporteur;
use App\Entity\Type;
use App\Entity\Utilisateur;

use App\Helper\FormatHelper;
use App\Service\CSVExportService;
use App\Service\DisputeService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use WiiCommon\Helper\Stream;

#[Route("/litige", name: "dispute_")]
class DisputeController extends AbstractController
{

    #[Route("/liste", name: "index", options: ["expose" => true], methods: [self::GET, self::POST])]
    #[HasPermission([Menu::QUALI, Action::DISPLAY_LITI])]
    public function index(DisputeService         $disputeService,
                          Request $request,
                          EntityManagerInterface $entityManager) {

        $typeRepository = $entityManager->getRepository(Type::class);
        $statutRepository = $entityManager->getRepository(Statut::class);
        $transporteurRepository = $entityManager->getRepository(Transporteur::class);

        /** @var Utilisateur $user */
        $user = $this->getUser();
        $data = $request->query;
        $fromDashboard = $data->has('fromDashboard') ? $data->get('fromDashboard') : false;
        $disputeEmergency = $data->has('emergency') ? $data->get('emergency') : false;
        $statusesFilter = $data->has('statuses') ? $data->all('statuses') : [];
        $typesFilter = $data->has('types') ? $data->all('types') : [];

        if ($fromDashboard) {
            if (!empty($typesFilter)) {
                $typesFilter = Stream::from($typeRepository->findBy(['id' => $typesFilter]))
                    ->filterMap(fn(Type $type) => $type->getLabelIn($user->getLanguage()))
                    ->toArray();
            }

            if (!empty($statusesFilter)) {
                $statusesFilter = Stream::from($statutRepository->findBy(['id' => $statusesFilter]))
                    ->map(fn(Statut $status) => $status->getId())
                    ->toArray();
            }
        }

        return $this->render('litige/index.html.twig',[
            'statuts' => $statutRepository->findByCategorieNames([CategorieStatut::DISPUTE_ARR, CategorieStatut::LITIGE_RECEPT]),
            'carriers' => $transporteurRepository->findAllSorted(),
            'types' => $typeRepository->findByCategoryLabels([CategoryType::DISPUTE]),
			'litigeOrigins' => $disputeService->getLitigeOrigin(),
            'fields' => $disputeService->getColumnVisibleConfig($user),
            'fromDashboard' => $fromDashboard,
            ...($fromDashboard ? [
                'filterTypes' => $typesFilter,
                'filterStatus' => $statusesFilter,
                'disputeEmergency' => $disputeEmergency,
            ] : []),
		]);
    }

    #[Route("/api", name: "api", options: ["expose" => true], methods: [self::GET, self::POST], condition: "request.isXmlHttpRequest()")]
    #[HasPermission([Menu::QUALI, Action::DISPLAY_LITI], mode: HasPermission::IN_JSON)]
    public function api(Request $request,
                        DisputeService $disputeService) {
        $fromDashboard = $request->query->getBoolean('fromDashboard');

        if ($fromDashboard) {
            $preFilledStatuses = $request->query->has('preFilledStatuses')
                ? implode(",", $request->query->all('preFilledStatuses'))
                : [];
            $preFilledTypes = $request->query->has('preFilledTypes')
                ? implode(",", $request->query->all('preFilledTypes'))
                : [];
            $disputeEmergency = $request->query->getBoolean('disputeEmergency', false);

            $preFilledFilters = [
                [
                    'field' => FiltreSup::FIELD_STATUT,
                    'value' => $preFilledStatuses,
                ],
                [
                    'field' => FiltreSup::FIELD_MULTIPLE_TYPES,
                    'value' => $preFilledTypes,
                ],
                ...($disputeEmergency ? [[
                    'field' => FiltreSup::FIELD_EMERGENCY,
                    'value' => $disputeEmergency,
                ]] : []),
            ];
        }

        $data = $disputeService->getDataForDatatable($request->request, $fromDashboard, $preFilledFilters ?? []);
        return new JsonResponse($data);
	}

    #[Route("/csv", name: "export_csv", options: ["expose" => true], methods: [self::GET, self::POST])]
    public function exportCSVDispute(Request                $request,
                                     DisputeService         $disputeService,
                                     EntityManagerInterface $entityManager,
                                     CSVExportService       $CSVExportService): Response
    {


        $dateMin = $request->query->get('dateMin');
        $dateMax = $request->query->get('dateMax');
        $status = $request->query->get('statut');
        $statuses = $status ? explode(',', $status) : [];

        $dateTimeMin = DateTime::createFromFormat('Y-m-d H:i:s', $dateMin . ' 00:00:00');
        $dateTimeMax = DateTime::createFromFormat('Y-m-d H:i:s', $dateMax . ' 23:59:59');

        $headers = [
            'Numéro de litige',
            'Type',
            'Statut',
            'Date création',
            'Date modification',
            'Unité logistiques / Réferences',
            'Code barre',
            'QteArticle',
            'Ordre arrivage / réception',
            'N° Commande / BL',
            'Déclarant',
            'Fournisseur',
            'N° ligne',
            'Acheteur(s)',
            'Date commentaire',
            'Utilisateur',
            'Commentaire'
        ];

        $today = (new DateTime('now'))->format("d-m-Y-H-i-s");
        return $CSVExportService->streamResponse(function ($output) use ($disputeService, $entityManager, $dateTimeMin, $dateTimeMax, $statuses) {

            $disputeRepository = $entityManager->getRepository(Dispute::class);
            $articleRepository = $entityManager->getRepository(Article::class);
            $receptionReferenceArticleRepository = $entityManager->getRepository(ReceptionReferenceArticle::class);

            $arrivalDisputes = $disputeRepository->iterateArrivalDisputesByDatesOrAndStatus($dateTimeMin, $dateTimeMax, $statuses);

            /** @var Dispute $dispute */
            foreach ($arrivalDisputes as $dispute) {
                $disputeService->putDisputeLine($entityManager, DisputeService::PUT_LINE_ARRIVAL, $output, $dispute, $disputeRepository);
            }

            $entityManager->clear();

            $associatedIdAndReferences = $receptionReferenceArticleRepository->getAssociatedIdAndReferences();
            $associatedIdAndOrderNumbers = $receptionReferenceArticleRepository->getAssociatedIdAndOrderNumbers();

            $receptionDisputes = $disputeRepository->iterateReceptionDisputesByDates($dateTimeMin, $dateTimeMax, $statuses);
            /** @var Dispute $dispute */
            foreach ($receptionDisputes as $dispute) {
                $articles = $articleRepository->getArticlesByDisputeId($dispute["id"]);
                $disputeService->putDisputeLine($entityManager, DisputeService::PUT_LINE_RECEPTION, $output, $dispute, $disputeRepository, $associatedIdAndReferences, $associatedIdAndOrderNumbers, $articles);
            }
        }, "Export-Litiges_$today.csv", $headers);
    }

    #[Route("/histo/{dispute}", name: "histo_api", options: ["expose" => true], methods: ["POST"], condition: "request.isXmlHttpRequest()")]
    public function apiHistoricLitige(EntityManagerInterface $entityManager,
                                      Request                $request,
                                      Dispute                $dispute): Response {
        $rows = [];
        $typeRepository = $entityManager->getRepository(Type::class);
        $statusRepository = $entityManager->getRepository(Statut::class);
        $disputeHistoryRecordRepository = $entityManager->getRepository(DisputeHistoryRecord::class);
        $requestData = $request->request->all();
        $order = $requestData['order'][0];
        $sortColumn = $requestData['columns'][$order['column']];
        $direction = $order['dir'];
        $data['recordsTotal'] = $disputeHistoryRecordRepository->count(
            ['dispute' => $dispute],
        );
        $data['recordsFiltered'] = $data['recordsTotal'];
        $disputeHistory = $disputeHistoryRecordRepository->findBy(
            ['dispute' => $dispute],
            [$sortColumn['data'] => $direction],
                $requestData['length'] ?? null,
                $requestData['start'] ?? null
        );
        foreach ($disputeHistory as $record) {
            $dispute = $record->getDispute();
            $categoryStatus = match(true) {
                $dispute->getArticles()->count() > 0 => CategorieStatut::LITIGE_RECEPT,
                $dispute->getPacks()->count() > 0 => CategorieStatut::DISPUTE_ARR,
                default => CategorieStatut::DISPUTE_ARR
            };
            $disputeStatus = $statusRepository->findOneByCategorieNameAndStatutCode($categoryStatus, $record->getStatusLabel());
            $disputeType = $typeRepository->findOneByCategoryLabelAndLabel(CategoryType::DISPUTE, $record->getTypeLabel());
            $rows[] = [
                'user' => $this->getFormatter()->user($record->getUser()),
                'date' => $this->getFormatter()->datetime($record->getDate()),
                'comment' => nl2br($record->getComment()),
                'statusLabel' => $this->getFormatter()->status($disputeStatus) ?: $record->getStatusLabel(),
                'typeLabel' => $this->getFormatter()->type($disputeType) ?? $record->getTypeLabel()
            ];
        }
        $data['data'] = $rows;
        return new JsonResponse($data);
    }

    #[Route("/add_Comment/{dispute}", name: "add_comment", options: ["expose" => true], methods: [self::POST], condition: "request.isXmlHttpRequest()")]
    public function addComment(Request $request,
                               DisputeService $disputeService,
                               EntityManagerInterface $em,
                               Dispute $dispute): Response {
        $comment = $request->request->get('comment');

        if (empty($comment)) {
            return new JsonResponse([
                'success' => false,
                'msg' => 'Le commentaire ne peut pas être vide'
            ]);
        }

        /** @var Utilisateur $currentUser */
        $currentUser = $this->getUser();

        $historyRecord = $disputeService->createDisputeHistoryRecord(
            $dispute,
            $currentUser,
            [$comment]
        );

        $em->persist($historyRecord);
        $em->flush();

        return new JsonResponse([
            'success' => true,
            'msg' => 'Commentaire ajouté avec succès'
        ]);
    }

    #[Route("/modifier", name: "edit", options: ["expose" => true], methods: [self::GET, self::POST], condition: "request.isXmlHttpRequest()")]
    #[HasPermission([Menu::QUALI, Action::EDIT], mode: HasPermission::IN_JSON)]
	public function editLitige(Request $request): Response
	{
        $post = $request->request;
        $isArrivage = $post->get('isArrivage');

        $controllerAndFunction = $isArrivage ? 'App\Controller\ArrivageController::editDispute' : 'App\Controller\ReceptionController::editDispute';

        return $this->forward($controllerAndFunction, [
            'request' => $request
        ]);
	}

    #[Route("/supprimer", name: "delete", options: ["expose" => true], methods: [self::GET, self::POST], condition: "request.isXmlHttpRequest()")]
    #[HasPermission([Menu::QUALI, Action::DELETE], mode: HasPermission::IN_JSON)]
    public function deleteLitige(Request $request,
                                 EntityManagerInterface $entityManager): Response
    {
        if ($data = json_decode($request->getContent(), true)) {
            $disputeRepository = $entityManager->getRepository(Dispute::class);
            $dispute = $disputeRepository->find($data['litige']);

            $articlesInDispute = $dispute->getArticles()->toArray();
            $controller = !empty($articlesInDispute) ? ReceptionController::class : ArrivageController::class;

            return $this->forward("$controller::deleteDispute", [
                'request' => $request
            ]);
        }
        throw new BadRequestHttpException();
    }

    #[Route("/article/{dispute}", name: "article_api", options: ["expose" => true], methods: [self::GET, self::POST], condition: "request.isXmlHttpRequest()")]
    public function articlesByLitige(Dispute $dispute): Response
    {
        $rows = [];
        $articlesInLitige = $dispute->getFiveLastArticles();

        foreach ($articlesInLitige as $article) {
            $rows[] = [
                'codeArticle' => $article ? $article->getBarCode() : '',
                'status' => FormatHelper::status($article->getStatut()),
                'libelle' => $article->getLabel() ?: '',
                'reference' => $article->getReference() ?: '',
                'quantity' => $article ? $article->getQuantite() : 'non renseigné',
            ];
        }
        $data['data'] = $rows;
        return new JsonResponse($data);
    }

    #[Route("/autocomplete", name: "get_number", options: ["expose" => true], methods: [self::GET, self::POST], condition: "request.isXmlHttpRequest()")]
    public function getDisputeNumberAutoComplete(Request $request,
                                                 EntityManagerInterface $entityManager): Response
    {
        $search = $request->query->get('term');

        $utilisateurRepository = $entityManager->getRepository(Dispute::class);
        $user = $utilisateurRepository->getIdAndDisputeNumberBySearch($search);
        return new JsonResponse([
            'results' => $user
        ]);
    }
}
