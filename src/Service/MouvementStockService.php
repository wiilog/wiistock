<?php

namespace App\Service;

use App\Entity\Article;
use App\Entity\Emplacement;
use App\Entity\FiltreSup;
use App\Entity\MouvementStock;

use App\Entity\TrackingMovement;
use App\Entity\ReferenceArticle;
use App\Entity\Utilisateur;
use App\Helper\FormatHelper;
use DateTime;
use Twig\Environment as Twig_Environment;

use Doctrine\ORM\EntityManagerInterface;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

class MouvementStockService
{
    /** @Required */
    public Twig_Environment $templating;

    /** @Required */
    public EntityManagerInterface $entityManager;

    /**
     * @param Utilisateur $user
     * @param array|null $params
     * @return array
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function getDataForDatatable(Utilisateur $user, $params = null)
    {
        $filtreSupRepository = $this->entityManager->getRepository(FiltreSup::class);
        $mouvementStockRepository = $this->entityManager->getRepository(MouvementStock::class);

		$filters = $filtreSupRepository->getFieldAndValueByPageAndUser(FiltreSup::PAGE_MVT_STOCK, $user);

		$queryResult = $mouvementStockRepository->findByParamsAndFilters($params, $filters, $user);

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
     */
    public function dataRowMouvement($mouvement)
    {
        $fromColumnConfig = $this->getFromColumnConfig($this->entityManager, $mouvement);
        $from = $fromColumnConfig['from'];
        $orderPath = $fromColumnConfig['orderPath'];
        $orderId = $fromColumnConfig['orderId'];

		$refArticleCheck = '';
        if($mouvement->getArticle()) {
            $articleFournisseur = $mouvement->getArticle()->getArticleFournisseur();
            if($articleFournisseur) {
                $referenceArticle = $articleFournisseur->getReferenceArticle();
                if($referenceArticle) {
                    $refArticleCheck = $referenceArticle->getReference() ?: '';
                }
            }
        }
        else {
            $refArticleCheck = $mouvement->getRefArticle()->getReference();
        }

		return [
			'id' => $mouvement->getId(),
			'from' => $this->templating->render('mouvement_stock/datatableMvtStockRowFrom.html.twig', [
				'from' => $from,
				'mvt' => $mouvement,
				'orderPath' => $orderPath,
				'orderId' => $orderId
			]),
			'date' => $mouvement->getDate() ? $mouvement->getDate()->format('d/m/Y H:i:s') : '',
			'refArticle' => $refArticleCheck,
            'barCode' => $mouvement->getArticle() ? $mouvement->getArticle()->getBarCode() : $mouvement->getRefArticle()->getBarCode(),
            'quantite' => $mouvement->getQuantity(),
			'origine' => $mouvement->getEmplacementFrom() ? $mouvement->getEmplacementFrom()->getLabel() : '',
			'destination' => $mouvement->getEmplacementTo() ? $mouvement->getEmplacementTo()->getLabel() : '',
			'type' => $mouvement->getType(),
			'operateur' => $mouvement->getUser() ? $mouvement->getUser()->getUsername() : '',
			'actions' => $this->templating->render('mouvement_stock/datatableMvtStockRow.html.twig', [
				'mvt' => $mouvement,
			])
		];
    }

    public function getFromColumnConfig(EntityManagerInterface $entityManager, MouvementStock $mouvement) {
        $trackingMovementRepository = $entityManager->getRepository(TrackingMovement::class);

        $orderPath = null;
        $orderId = null;
        $from = null;
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
        } else if ($mouvement->getTransferOrder()) {
            $from = 'transfert de stock';
            $orderPath = 'transfer_order_show';
            $orderId = $mouvement->getTransferOrder()->getId();
        }  else if (in_array($mouvement->getType(), [MouvementStock::TYPE_INVENTAIRE_ENTREE, MouvementStock::TYPE_INVENTAIRE_SORTIE])) {
            $from = 'inventaire';
        }
        return [
            'orderPath' => $orderPath,
            'orderId' => $orderId,
            'from' => $from
        ];
    }

    /**
     * @param Utilisateur $user
     * @param Emplacement|null $locationFrom
     * @param int $quantity
     * @param Article|ReferenceArticle $article
     * @param string $type
     * @param bool $needsTracing
     * @return void
     * @throws \Exception
     */
    public function createMouvementStock(Utilisateur $user,
                                         ?Emplacement $locationFrom,
                                         int $quantity,
                                         $article,
                                         string $type): MouvementStock {

        $newMouvement = new MouvementStock();
        $newMouvement
            ->setUser($user)
            ->setEmplacementFrom($locationFrom)
            ->setType($type)
            ->setQuantity($quantity);

        if($article instanceof Article) {
            $newMouvement->setArticle($article);

            if($type === MouvementStock::TYPE_SORTIE) {
                $article->setInactiveSince(new DateTime());
            }
        }
        else if($article instanceof ReferenceArticle) {
            $newMouvement->setRefArticle($article);
        }

        return $newMouvement;
    }

    public function finishMouvementStock(MouvementStock $mouvementStock,
                                         DateTime $date,
                                         ?Emplacement $locationTo): void {
        $mouvementStock
            ->setDate($date)
            ->setEmplacementTo($locationTo);
    }

    /**
     * @param MouvementStock $mouvementStock
     * @param EntityManagerInterface $entityManager
     */
    public function manageMouvementStockPreRemove(MouvementStock $mouvementStock,
                                                  EntityManagerInterface $entityManager) {
        $trackingMovementRepository = $entityManager->getRepository(TrackingMovement::class);
        foreach ($trackingMovementRepository->findBy(['mouvementStock' => $mouvementStock]) as $mvtTraca) {
            $entityManager->remove($mvtTraca);
        }
    }

    public function putMovementLine($handle,
                                    CSVExportService $CSVExportService,
                                    array $mouvement)
    {
        $orderNo = $mouvement['preparationOrder']
            ?? $mouvement['livraisonOrder']
            ?? $mouvement['collecteOrder']
            ?? $mouvement['receptionOrder']
            ?? null;

        $data = [
            FormatHelper::datetime($mouvement['date']),
            $orderNo,
            $mouvement['refArticleRef'],
            !empty($mouvement['refArticleBarCode']) ? $mouvement['refArticleBarCode']: '',
            !empty($mouvement['articleBarCode']) ? $mouvement['articleBarCode'] : '',
            $mouvement['quantity'],
            $mouvement['originEmpl'],
            $mouvement['destinationEmpl'],
            $mouvement['type'],
            $mouvement['operator']
        ];
        $CSVExportService->putLine($handle, $data);
    }
}
