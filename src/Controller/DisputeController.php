<?php

namespace App\Controller;

use App\Annotation\HasPermission;
use App\Entity\Action;
use App\Entity\Article;
use App\Entity\CategorieStatut;
use App\Entity\CategoryType;
use App\Entity\Dispute;
use App\Entity\Menu;
use App\Entity\DisputeHistoryRecord;

use App\Entity\Attachment;
use App\Entity\ReceptionReferenceArticle;
use App\Entity\Statut;
use App\Entity\Transporteur;
use App\Entity\Type;
use App\Entity\Utilisateur;

use App\Helper\FormatHelper;
use App\Service\CSVExportService;
use App\Service\DisputeService;
use App\Service\LanguageService;
use App\Service\TranslationService;
use App\Service\VisibleColumnService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/litige")
 */
class DisputeController extends AbstractController
{

    /**
     * @Route("/liste", name="dispute_index", options={"expose"=true}, methods="GET|POST")
     * @HasPermission({Menu::QUALI, Action::DISPLAY_LITI})
     */
    public function index(DisputeService         $disputeService,
                          EntityManagerInterface $entityManager,
                          LanguageService $languageService)
    {

        $typeRepository = $entityManager->getRepository(Type::class);
        $statutRepository = $entityManager->getRepository(Statut::class);
        $transporteurRepository = $entityManager->getRepository(Transporteur::class);

        /** @var Utilisateur $user */
        $user = $this->getUser();

        return $this->render('litige/index.html.twig',[
            'statuts' => $statutRepository->findByCategorieNames([CategorieStatut::DISPUTE_ARR, CategorieStatut::LITIGE_RECEPT]),
            'carriers' => $transporteurRepository->findAllSorted(),
            'types' => $typeRepository->findByCategoryLabels([CategoryType::DISPUTE]),
			'litigeOrigins' => $disputeService->getLitigeOrigin(),
            'fields' => $disputeService->getColumnVisibleConfig($user)
		]);
    }

    /**
     * @Route("/api", name="litige_api", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::QUALI, Action::DISPLAY_LITI}, mode=HasPermission::IN_JSON)
     */
    public function api(Request $request,
                        DisputeService $disputeService) {

        /** @var Utilisateur $user */
        $user = $this->getUser();
        $data = $disputeService->getDataForDatatable($request->request);
        $visibleColumns = $user->getVisibleColumns()['dispute'];
        $data['visible'] = $visibleColumns;
        return new JsonResponse($data);
	}

    /**
     * @Route("/csv", name="export_csv_dispute", options={"expose"=true}, methods={"GET","POST"})
     */
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

    /**
     * @Route("/supprime-pj-litige", name="litige_delete_attachement", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     */
	public function deleteAttachementLitige(Request $request,
                                            EntityManagerInterface $entityManager)
	{
		if ($data = json_decode($request->getContent(), true)) {
			$disputeId = (int)$data['disputeId'];
			$attachmentRepository = $entityManager->getRepository(Attachment::class);

			$attachements = $attachmentRepository->findOneByFileNameAndDisputeId($data['pjName'], $disputeId);
			if (!empty($attachements)) {
			    foreach ($attachements as $attachement) {
                    $entityManager->remove($attachement);
                }
				$entityManager->flush();
				$response = true;
			} else {
				$response = false;
			}

			return new JsonResponse($response);
		} else {
			throw new BadRequestHttpException();
		}
	}

    /**
     * @Route("/histo/{dispute}", name="histo_dispute_api", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     */
	public function apiHistoricLitige(EntityManagerInterface $entityManager,
                                      Dispute                $dispute): Response
    {
        $rows = [];
        $typeRepository = $entityManager->getRepository(Type::class);
        $statusRepository = $entityManager->getRepository(Statut::class);
        $disputeHistoryRecordRepository = $entityManager->getRepository(DisputeHistoryRecord::class);
        $disputeHistory = $disputeHistoryRecordRepository->findBy(['dispute' => $dispute]);

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
                'commentaire' => nl2br($record->getComment()),
                'status' => $this->getFormatter()->status($disputeStatus) ?: $record->getStatusLabel(),
                'type' => $this->getFormatter()->type($disputeType) ?? $record->getTypeLabel()
            ];
        }
        $data['data'] = $rows;

        return new JsonResponse($data);
    }

    /**
     * @Route("/add_Comment/{dispute}", name="add_comment", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     */
    public function addComment(Request $request,
                               DisputeService $disputeService, EntityManagerInterface $em,
                               Dispute $dispute): Response
    {
        if ($data = (json_decode($request->getContent(), true) ?? [])) {
            /** @var Utilisateur $currentUser */
            $currentUser = $this->getUser();

            $historyRecord = $disputeService->createDisputeHistoryRecord(
                $dispute,
                $currentUser,
                [$data]
            );

            $em->persist($historyRecord);
            $em->flush();

            return new JsonResponse(true);
        }
        return new JsonResponse(false);
    }

    /**
     * @Route("/modifier", name="litige_edit",  options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::QUALI, Action::EDIT})
     */
	public function editLitige(Request $request): Response
	{
        $post = $request->request;
        $isArrivage = $post->get('isArrivage');

        $controllerAndFunction = $isArrivage ? 'App\Controller\ArrivageController::editLitige' : 'App\Controller\ReceptionController::editDispute';

        return $this->forward($controllerAndFunction, [
            'request' => $request
        ]);
	}

    /**
     * @Route("/supprimer", name="litige_delete", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::QUALI, Action::DELETE}, mode=HasPermission::IN_JSON)
     */
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

    /**
     * @Route("/colonne-visible", name="save_column_visible_for_litige", options={"expose"=true}, methods="POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::QUALI, Action::DISPLAY_LITI}, mode=HasPermission::IN_JSON)
     */
    public function saveColumnVisible(Request $request,
                                      EntityManagerInterface $entityManager,
                                      VisibleColumnService $visibleColumnService,
                                      TranslationService $translation): Response
    {
        $data = json_decode($request->getContent(), true);
        $fields = array_keys($data);
        /** @var $user Utilisateur */
        $user = $this->getUser();
        $fields[] = "actions";

        $visibleColumnService->setVisibleColumns('dispute', $fields, $user);
        $entityManager->flush();

        return $this->json([
            'success' => true,
            'msg' => $translation->translate('Général', null, 'Zone liste', 'Vos préférences de colonnes à afficher ont bien été sauvegardées')
        ]);
    }

    /**
     * @Route("/article/{dispute}", name="article_dispute_api", options={"expose"=true}, methods="POST|GET", condition="request.isXmlHttpRequest()")
     */
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

    /**
     * @Route("/autocomplete", name="get_dispute_number", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     */
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
