<?php

namespace App\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use App\Entity\Articles;

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
		if ($request->isXmlHttpRequest()) {
			$articles = $em->getRepository(Articles::class)->findAll();
			$data = array("current" => 1,
						"rowCount" => 4,
						"rows" => [
							["ref" => "136737541",
							"sku" => "265",
							"libelle" => "Iphone1",
							"qte" => "14"],

							["ref" => "136737542",
							"sku" => "266",
							"libelle" => "Iphone2",
							"qte" => "69"],

							["ref" => "136737543",
							"sku" => "267",
							"libelle" => "Iphone3",
							"qte" => "3"],

							["ref" => "136737544",
							"sku" => "268",
							"libelle" => "Iphone4",
							"qte" => "42"],
						],
						"total" => 4
			);
			return new JsonResponse($data);
		}

		return $this->render('stock/index.html.twig', [
			'controller_name' => 'StockController',
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
