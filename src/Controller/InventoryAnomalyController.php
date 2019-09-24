<?php


namespace App\Controller;

use App\Entity\Action;
use App\Entity\Menu;

use App\Entity\MouvementStock;
use App\Repository\InventoryMissionRepository;
use App\Repository\InventoryEntryRepository;
use App\Repository\ReferenceArticleRepository;
use App\Repository\ArticleRepository;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use App\Service\UserService;


/**
 * @Route("/inventaire/anomalie")
 */
class InventoryAnomalyController extends AbstractController
{
    /**
     * @var UserService
     */
    private $userService;

    /**
     * @var InventoryMissionRepository
     */
    private $inventoryMissionRepository;

    /**
     * @var InventoryEntryRepository
     */
    private $inventoryEntryRepository;

    /**
     * @var ReferenceArticleRepository
     */
    private $referenceArticleRepository;

    /**
     * @var ArticleRepository
     */
    private $articleRepository;

    public function __construct(UserService $userService, InventoryMissionRepository $inventoryMissionRepository, InventoryEntryRepository $inventoryEntryRepository, ReferenceArticleRepository $referenceArticleRepository, ArticleRepository $articleRepository)
    {
        $this->userService = $userService;
        $this->inventoryMissionRepository = $inventoryMissionRepository;
        $this->inventoryEntryRepository = $inventoryEntryRepository;
        $this->referenceArticleRepository = $referenceArticleRepository;
        $this->articleRepository = $articleRepository;
    }

	/**
	 * @Route("/", name="show_anomalies")
	 */
    public function showAnomalies()
	{
		if (!$this->userService->hasRightFunction(Menu::INVENTAIRE, Action::LIST)) {
			return $this->redirectToRoute('access_denied');
		}

		return $this->render('inventaire/anomalies.html.twig');
	}

	/**
	 * @Route("/api", name="inv_anomalies_api", options={"expose"=true}, methods="GET|POST")
	 */
	public function apiAnomalies(Request $request)
	{
		if ($request->isXmlHttpRequest()) {
			if (!$this->userService->hasRightFunction(Menu::INVENTAIRE, Action::INVENTORY_MANAGER)) {
				return $this->redirectToRoute('access_denied');
			}

			$refAnomalies = $this->inventoryMissionRepository->getInventoryRefAnomalies();
			$artAnomalies = $this->inventoryMissionRepository->getInventoryArtAnomalies();

			$anomalies = array_merge($refAnomalies, $artAnomalies);

			$rows = [];
			foreach ($anomalies as $anomaly) {
				$rows[] =
					[
						'reference' => $anomaly['reference'],
						'libelle' => $anomaly['label'],
						'quantite' => $anomaly['quantity'],
						'Actions' => $this->renderView('inventaire/datatableAnomaliesRow.html.twig',
							[
								'reference' => $anomaly['reference'],
								'isRef' => $anomaly['is_ref'],
								'quantity' => $anomaly['quantity'],
								'location' => $anomaly['location'],
							]),
					];
			}
			$data['data'] = $rows;
			return new JsonResponse($data);
		}
		throw new NotFoundHttpException("404");
	}

	/**
	 * @Route("/traitement", name="anomaly_treat", options={"expose"=true}, methods="GET|POST")
	 */
	public function treatAnomaly(Request $request)
	{
		if ($request->isXmlHttpRequest()  && $data = json_decode($request->getContent(), true)) {
			if (!$this->userService->hasRightFunction(Menu::INVENTAIRE, Action::INVENTORY_MANAGER)) {
				return $this->redirectToRoute('access_denied');
			}

			$isRef = $data['isRef'];
			$newQuantity = (int)$data['newQuantity'];

			$em = $this->getDoctrine()->getManager();
			if ($isRef) {
				$refOrArt = $this->referenceArticleRepository->findOneByReference($data['reference']);
				$quantity = $refOrArt->getQuantiteStock();
			} else {
				$refOrArt = $this->articleRepository->findOneByReference($data['reference']);
				$quantity = $refOrArt->getQuantite();
			}

			$diff = $newQuantity - $quantity;

			if ($data['choice'] == 'confirm' && $diff != 0) {
				$mvt = new MouvementStock();
				$mvt
					->setUser($this->getUser())
					->setDate(new \DateTime('now'))
					->setComment($data['comment']);

				if ($isRef) {
					$mvt->setRefArticle($refOrArt);
					//TODO à supprimer quand la quantité sera calculée directement via les mouvements de stock
					$refOrArt->setQuantiteStock($newQuantity);
				} else {
					$mvt->setArticle($refOrArt);
					//TODO à supprimer quand la quantité sera calculée directement via les mouvements de stock
					$refOrArt->setQuantite($newQuantity);
				}

				if ($diff < 0) {
					$mvt
						->setType(MouvementStock::TYPE_INVENTAIRE_SORTIE)
						->setQuantity(-$diff);
				} else {
					$mvt
						->setType(MouvementStock::TYPE_INVENTAIRE_ENTREE)
						->setQuantity($diff);
				}
				$em->persist($mvt);
			}

			$refOrArt->setHasInventoryAnomaly(false);
			$em->flush();

			return new JsonResponse($data['choice'] == 'confirm');
		}
		throw new NotFoundHttpException("404");
	}

}