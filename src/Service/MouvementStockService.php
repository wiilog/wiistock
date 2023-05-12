<?php

namespace App\Service;

use App\Controller\Settings\SettingsController;
use App\Entity\Article;
use App\Entity\DeliveryRequest\Demande;
use App\Entity\Emplacement;
use App\Entity\FiltreSup;
use App\Entity\Import;
use App\Entity\Livraison;
use App\Entity\MouvementStock;

use App\Entity\OrdreCollecte;
use App\Entity\PreparationOrder\Preparation;
use App\Entity\Reception;
use App\Entity\TrackingMovement;
use App\Entity\ReferenceArticle;
use App\Entity\TransferOrder;
use App\Entity\Utilisateur;
use App\Helper\FormatHelper;
use DateTime;
use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Contracts\Service\Attribute\Required;
use Twig\Environment as Twig_Environment;

use Doctrine\ORM\EntityManagerInterface;

class MouvementStockService
{
    #[Required]
    public Twig_Environment $templating;

    #[Required]
    public EntityManagerInterface $entityManager;

    #[Required]
    public TranslationService $translation;

    public function getDataForDatatable(Utilisateur $user, ?InputBag $params = null): array
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

    public function dataRowMouvement(MouvementStock $mouvement): array
    {
        $fromColumnConfig = $this->getFromColumnConfig($mouvement);
        $from = $fromColumnConfig['from'];
        $fromPath = $fromColumnConfig['path'];
        $fromPathParams = $fromColumnConfig['pathParams'];

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
				'path' => $fromPath,
				'pathParams' => $fromPathParams
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

    public function getFromColumnConfig(MouvementStock $mouvement): array {
        if ($mouvement->getDeliveryRequest()) {
            $from = mb_strtolower($this->translation->translate("Demande", "Livraison", "Demande de livraison", false));
            $path = 'demande_show';
            $pathParams = [
                'id' => $mouvement->getDeliveryRequest()->getId()
            ];
        }
        else if ($mouvement->getPreparationOrder()) {
            $from = 'préparation';
            $path = 'preparation_show';
            $pathParams = [
                'id' => $mouvement->getPreparationOrder()->getId()
            ];
        } else if ($mouvement->getLivraisonOrder()) {
            $from = mb_strtolower($this->translation->translate("Ordre", "Livraison", "Ordre de livraison", false));
            $path = 'livraison_show';
            $pathParams = [
                'id' => $mouvement->getLivraisonOrder()->getId()
            ];
        } else if ($mouvement->getCollecteOrder()) {
            $from = 'collecte';
            $path = 'ordre_collecte_show';
            $pathParams = [
                'id' => $mouvement->getCollecteOrder()->getId()
            ];
        } else if ($mouvement->getReceptionOrder()) {
            $from = 'réception';
            $path = 'reception_show';
            $pathParams = [
                'id' => $mouvement->getReceptionOrder()->getId()
            ];
        } else if ($mouvement->getImport()) {
            $from = 'import';
            $path = 'settings_item';
            $pathParams = [
                'category' => SettingsController::CATEGORY_DATA,
                'menu' => SettingsController::MENU_IMPORTS,
            ];
        } else if ($mouvement->getTransferOrder()) {
            $from = 'transfert de stock';
            $path = 'transfer_order_show';
            $pathParams = [
                'id' => $mouvement->getTransferOrder()->getId()
            ];
        } else if (in_array($mouvement->getType(), [MouvementStock::TYPE_INVENTAIRE_ENTREE, MouvementStock::TYPE_INVENTAIRE_SORTIE])) {
            $from = 'inventaire';
        }
        return [
            'path' => $path ?? null,
            'pathParams' => $pathParams ?? [],
            'from' => $from ?? null
        ];
    }

    public function createMouvementStock(Utilisateur              $user,
                                         ?Emplacement             $locationFrom,
                                         int                      $quantity,
                                         Article|ReferenceArticle $article,
                                         string                   $type,
                                         array                    $options = []): MouvementStock {

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
        else { // if($article instanceof ReferenceArticle) {
            $newMouvement->setRefArticle($article);
        }

        $from = $options['from'] ?? null;
        $locationTo = $options['locationTo'] ?? null;
        $date = $options['date'] ?? null;

        if ($from) {
            if ($from instanceof Preparation) {
                $newMouvement->setPreparationOrder($from);
            }
            else if ($from instanceof Livraison) {
                $newMouvement->setLivraisonOrder($from);
            }
            else if ($from instanceof OrdreCollecte) {
                $newMouvement->setCollecteOrder($from);
            }
            else if ($from instanceof Reception) {
                $newMouvement->setReceptionOrder($from);
            }
            else if ($from instanceof Import) {
                $newMouvement->setImport($from);
            }
            else if ($from instanceof TransferOrder) {
                $newMouvement->setTransferOrder($from);
            }
            else if ($from instanceof Demande) {
                $newMouvement->setDeliveryRequest($from);
            }
        }

        if ($date) {
            $newMouvement->setDate($date);
        }

        if ($locationTo) {
            $newMouvement->setEmplacementTo($locationTo);
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

    public function manageMouvementStockPreRemove(MouvementStock $mouvementStock,
                                                  EntityManagerInterface $entityManager): void {
        $trackingMovementRepository = $entityManager->getRepository(TrackingMovement::class);
        foreach ($trackingMovementRepository->findBy(['mouvementStock' => $mouvementStock]) as $mvtTraca) {
            $entityManager->remove($mvtTraca);
        }
    }

    public function putMovementLine($handle,
                                    CSVExportService $CSVExportService,
                                    array $mouvement): void
    {
        $orderNo = $mouvement['preparationOrder']
            ?? $mouvement['livraisonOrder']
            ?? $mouvement['collecteOrder']
            ?? join(", ", $mouvement['receptionOrder'])
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
