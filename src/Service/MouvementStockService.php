<?php

namespace App\Service;

use App\Entity\Article;
use App\Entity\Emplacement;
use App\Entity\FiltreSup;
use App\Entity\MouvementStock;

use App\Entity\MouvementTraca;
use App\Entity\ReferenceArticle;
use App\Entity\Utilisateur;
use DateTime;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Routing\RouterInterface;
use Twig\Environment as Twig_Environment;

use Doctrine\ORM\EntityManagerInterface;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

class MouvementStockService
{
    /**
     * @var Twig_Environment
     */
    private $templating;

    /**
     * @var RouterInterface
     */
    private $router;

	/**
	 * @var UserService
	 */
    private $userService;

    private $security;

    private $entityManager;

    public function __construct(UserService $userService,
                                RouterInterface $router,
                                EntityManagerInterface $entityManager,
                                Twig_Environment $templating,
                                Security $security)
    {

        $this->templating = $templating;
        $this->entityManager = $entityManager;
        $this->router = $router;
        $this->userService = $userService;
        $this->security = $security;
    }

	/**
	 * @param array|null $params
	 * @return array
	 * @throws \Exception
	 */
    public function getDataForDatatable($params = null)
    {
        $filtreSupRepository = $this->entityManager->getRepository(FiltreSup::class);
        $mouvementStockRepository = $this->entityManager->getRepository(MouvementStock::class);

		$filters = $filtreSupRepository->getFieldAndValueByPageAndUser(FiltreSup::PAGE_MVT_STOCK, $this->security->getUser());

		$queryResult = $mouvementStockRepository->findByParamsAndFilters($params, $filters);

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
	 * @throws LoaderError
	 * @throws RuntimeError
	 * @throws SyntaxError
	 * @throws NoResultException
	 * @throws NonUniqueResultException
	 */
    public function dataRowMouvement($mouvement)
    {
        $mouvementTracaRepository = $this->entityManager->getRepository(MouvementTraca::class);

		$orderPath = $orderId = $from = null;
		if ($mouvement->getPreparationOrder()) {
			$from = 'préparation';
			$orderPath = 'preparation_show';
			$orderId = $mouvement->getPreparationOrder()->getId();
		} else if ($mouvement->getLivraisonOrder()) {
			$from = 'livraison';
			$orderPath = 'livraison_show';
			$orderId = $mouvement->getLivraisonOrder()->getId();
		} else if ($mouvement->getCollecteOrder()) {
			$from = 'collecte';
			$orderPath = 'ordre_collecte_show';
			$orderId = $mouvement->getCollecteOrder()->getId();
		} else if ($mouvement->getReceptionOrder()) {
			$from = 'réception';
			$orderPath = 'reception_show';
			$orderId = $mouvement->getReceptionOrder()->getId();
		} else if ($mouvement->getImport()) {
			$from = 'import';
			$orderPath = 'import_index';
			$orderId = null;
		} else if ($mouvementTracaRepository->countByMouvementStock($mouvement) > 0) {
			$from = 'transfert de stock';
		}

		$row = [
			'id' => $mouvement->getId(),
			'from' => $this->templating->render('mouvement_stock/datatableMvtStockRowFrom.html.twig', [
				'from' => $from,
				'mvt' => $mouvement,
				'orderPath' => $orderPath,
				'orderId' => $orderId
			]),
			'date' => $mouvement->getDate() ? $mouvement->getDate()->format('d/m/Y H:i:s') : '',
			'refArticle' => $mouvement->getArticle() ? $mouvement->getArticle()->getReference() : $mouvement->getRefArticle()->getReference(),
			'quantite' => $mouvement->getQuantity(),
			'origine' => $mouvement->getEmplacementFrom() ? $mouvement->getEmplacementFrom()->getLabel() : '',
			'destination' => $mouvement->getEmplacementTo() ? $mouvement->getEmplacementTo()->getLabel() : '',
			'type' => $mouvement->getType(),
			'operateur' => $mouvement->getUser() ? $mouvement->getUser()->getUsername() : '',
			'actions' => $this->templating->render('mouvement_stock/datatableMvtStockRow.html.twig', [
				'mvt' => $mouvement,
			])
		];

        return $row;
    }

    /**
     * @param Utilisateur $user
     * @param Emplacement|null $locationFrom
     * @param int $quantity
     * @param Article|ReferenceArticle $article
     * @param string $type
     * @return MouvementStock
     */
    public function createMouvementStock(Utilisateur $user, ?Emplacement $locationFrom, int $quantity, $article, string $type): MouvementStock {
        $newMouvement = new MouvementStock();
        $newMouvement
            ->setUser($user)
            ->setEmplacementFrom($locationFrom)
            ->setType($type)
            ->setQuantity($quantity);

        if($article instanceof Article) {
            $newMouvement->setArticle($article);
        }
        else if($article instanceof ReferenceArticle) {
            $newMouvement->setRefArticle($article);
        }

        return $newMouvement;
    }

    /**
     * @param MouvementStock $mouvementStock
     * @param DateTime $date
     * @param Emplacement $locationTo
     */
    public function finishMouvementStock(MouvementStock $mouvementStock,
                                         DateTime $date,
                                         Emplacement $locationTo): void {
        $mouvementStock
            ->setDate($date)
            ->setEmplacementTo($locationTo);
    }
}
