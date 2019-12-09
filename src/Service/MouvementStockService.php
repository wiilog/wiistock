<?php

namespace App\Service;

use App\Entity\FiltreSup;
use App\Entity\MouvementStock;

use App\Repository\MouvementStockRepository;
use App\Repository\FiltreSupRepository;

use Symfony\Component\Security\Core\Security;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

use Doctrine\ORM\EntityManagerInterface;

class MouvementStockService
{
    /**
     * @var \Twig_Environment
     */
    private $templating;

    /**
     * @var MouvementStockRepository
     */
    private $mouvementStockRepository;

    /**
     * @var RouterInterface
     */
    private $router;

	/**
	 * @var UserService
	 */
    private $userService;

    private $security;

    /**
     * @var FiltreSupRepository
     */
    private $filtreSupRepository;

    private $em;

    public function __construct(UserService $userService, MouvementStockRepository $mouvementStockRepository, RouterInterface $router, EntityManagerInterface $em, \Twig_Environment $templating, TokenStorageInterface $tokenStorage, FiltreSupRepository $filtreSupRepository, Security $security)
    {
    
        $this->templating = $templating;
        $this->em = $em;
        $this->router = $router;
        $this->mouvementStockRepository = $mouvementStockRepository;
        $this->userService = $userService;
        $this->filtreSupRepository = $filtreSupRepository;
        $this->security = $security;
    }

	/**
	 * @param array|null $params
	 * @return array
	 * @throws \Exception
	 */
    public function getDataForDatatable($params = null)
    {
		$filters = $this->filtreSupRepository->getFieldAndValueByPageAndUser(FiltreSup::PAGE_MVT_STOCK, $this->security->getUser());

		$queryResult = $this->mouvementStockRepository->findByParamsAndFilters($params, $filters);

		$mouvements = $queryResult['data'];

		$rows = [];
		foreach ($mouvements as $mouvement) {
			$rows[] = $this->dataRowMouvement($mouvement);
		}

		return [
			'data' => $rows,
			'recordsFiltered' => $queryResult['count'],
			'recordsTotal' => $queryResult['total'],
		];
    }

	/**
	 * @param MouvementStock $mouvement
	 * @return array
	 * @throws \Twig_Error_Loader
	 * @throws \Twig_Error_Runtime
	 * @throws \Twig_Error_Syntax
	 */
    public function dataRowMouvement($mouvement)
    {
		if ($mouvement->getPreparationOrder()) {
			$orderPath = 'preparation_show';
			$orderId = $mouvement->getPreparationOrder()->getId();
		} else if ($mouvement->getLivraisonOrder()) {
			$orderPath = 'livraison_show';
			$orderId = $mouvement->getLivraisonOrder()->getId();
		} else if ($mouvement->getCollecteOrder()) {
			$orderPath = 'ordre_collecte_show';
			$orderId = $mouvement->getCollecteOrder()->getId();
		} else {
			$orderPath = $orderId = null;
		}

		$row = [
			'id' => $mouvement->getId(),
			'date' => $mouvement->getDate() ? $mouvement->getDate()->format('d/m/Y H:i:s') : '',
			'refArticle' => $mouvement->getArticle() ? $mouvement->getArticle()->getReference() : $mouvement->getRefArticle()->getReference(),
			'quantite' => $mouvement->getQuantity(),
			'origine' => $mouvement->getEmplacementFrom() ? $mouvement->getEmplacementFrom()->getLabel() : '',
			'destination' => $mouvement->getEmplacementTo() ? $mouvement->getEmplacementTo()->getLabel() : '',
			'type' => $mouvement->getType(),
			'operateur' => $mouvement->getUser() ? $mouvement->getUser()->getUsername() : '',
			'actions' => $this->templating->render('mouvement_stock/datatableMvtStockRow.html.twig', [
				'mvt' => $mouvement,
				'orderPath' => $orderPath,
				'orderId' => $orderId
			])
		];

        return $row;
    }
}
