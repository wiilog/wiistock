<?php

namespace App\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
// use App\Entity\Articles;
// use App\Repository\ArticlesRepository;

/**
* @Route("/stock/stock")
*/
class StockController extends Controller
{
	/**
	 * @Route("/", name="stock")
	 */
	public function index(EntityManagerInterface $em, Request $request)
	{
		// $session = $request->getSession();

        // if ($request->isXmlHttpRequest()) {
        //     if (!$request->request->get('start')) {
        //         $zone = $session->get('zone');
        //         $quai = $session->get('quai');
		// 		$libelle = $session->get('libelle');
		// 		$numero = $session->get('numero');
        //     } else {
        //         $zone = $request->request->get('zone');
        //         $quai = $request->request->get('quai');
		// 		$libelle = $request->request->get('libelle');
		// 		$numero = $request->request->get('numero');
        //         $session->set('zone', $request->request->get('zone'));
        //         $session->set('quai', $request->request->get('quai'));
		// 		$session->set('libelle', $request->request->get('libelle'));
		// 		$session->set('numero', $request->request->get('numero'));
        //     }

        //     $current = $request->request->get('current');
        //     $rowCount = $request->request->get('rowCount');
        //     $searchPhrase = $request->request->get('searchPhrase');
        //     $sort = $request->request->get('sort');

			// $articles = $em->getRepository(Articles::class)->findAll();
            //$articles = $articlesRepository->findByFilters($zone, $quai, $libelle, $numero, $searchPhrase, $sort);

        //     if ($searchPhrase != "" || $zone || $quai) {
        //         $count = count($articles->getQuery()->getResult());
        //     } else {
        //         $count = count($articlesRepository->findAll());
        //     }

        //     if ($rowCount != -1) {
        //         $min = ($current - 1) * $rowCount;
        //         $max = $rowCount;

        //         $articles->setMaxResults($max)
        //             ->setFirstResult($min);
        //     }
        //     $articles = $articles->getQuery()->getResult();

        //     $rows = array();
        //     foreach ($articles as $article) {
        //         $row = [
        //             "id" => $article->getId(),
        //             "numero" => $article->getN(),
		// 			"statut" => $article->getStatut(),
		// 			"designation" => $article->getDesignation(),
		// 			"quantite" => $article->getQuantite(),
		// 			"libelle" => $article->getlibelle(),
        //         ];
        //         array_push($rows, $row);
        //     }

        //     $data = array(
        //         "current" => intval($current),
        //         "rowCount" => intval($rowCount),
        //         "rows" => $rows,
        //         "total" => intval($count)
        //     );

        //     return new JsonResponse($data);
        // }

		return $this->render('stock/index.html.twig', [
			'controller_name' => 'StockController',
			"f_zone" => $session->get('zone'),
            "f_quai" => $session->get('quai'),
			"f_libelle" => $session->get('libelle'),
			"f_numero" => $session->get('numero'),
		]);
	}

	/**
	* @Route("/visu2D", name="visulaisation_2D")
	*/
	public function visu2D()
	{
		return $this->render('stock/visu2D.html.twig', [
			'controller_name' => 'StockController',
		]);
	}

	/**
	* @Route("/valorisation", name="valorisation")
	*/
	public function valorisation()
	{
		return $this->render('stock/valorisation.html.twig', [
			'controller_name' => 'StockController',
		]);
	}
}
