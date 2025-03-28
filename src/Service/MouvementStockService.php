<?php

namespace App\Service;

use App\Controller\Settings\SettingsController;
use App\Entity\Article;
use App\Entity\DeliveryRequest\Demande;
use App\Entity\Emplacement;
use App\Entity\FiltreSup;
use App\Entity\Livraison;
use App\Entity\MouvementStock;
use App\Entity\OrdreCollecte;
use App\Entity\PreparationOrder\Preparation;
use App\Entity\Reception;
use App\Entity\ReferenceArticle;
use App\Entity\ScheduledTask\Import;
use App\Entity\ShippingRequest\ShippingRequest;
use App\Entity\Tracking\TrackingMovement;
use App\Entity\TransferOrder;
use App\Entity\Utilisateur;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Contracts\Service\Attribute\Required;
use Twig\Environment as Twig_Environment;

class MouvementStockService
{
    #[Required]
    public Twig_Environment $templating;

    #[Required]
    public EntityManagerInterface $entityManager;

    #[Required]
    public TranslationService $translation;

    #[Required]
    public FormatService $formatService;

    #[Required]
    public FieldModesService $fieldModesService;

    public function getDataForDatatable(Utilisateur $user, ?InputBag $params = null): array
    {
        $filtreSupRepository = $this->entityManager->getRepository(FiltreSup::class);
        $mouvementStockRepository = $this->entityManager->getRepository(MouvementStock::class);

		$filters = $filtreSupRepository->getFieldAndValueByPageAndUser(FiltreSup::PAGE_MVT_STOCK, $user);

		$queryResult = $mouvementStockRepository->findByParamsAndFilters($params, $filters, $this->fieldModesService, $user);

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

		return [
			'id' => $mouvement->getId(),
			'from' => $this->templating->render('mouvement_stock/datatableMvtStockRowFrom.html.twig', [
				'from' => $from,
				'mvt' => $mouvement,
				'path' => $fromPath,
				'pathParams' => $fromPathParams
			]),
			'date' => $this->formatService->datetime($mouvement->getDate()),
			'refArticle' => (
                $mouvement->getArticle()?->getReferenceArticle()?->getReference()
                ?: $mouvement->getRefArticle()?->getReference()
                ?: ""
            ),
            'barCode' => (
                $mouvement->getArticle()?->getBarCode()
                ?: $mouvement->getRefArticle()?->getBarCode()
                ?: ""
            ),
            'quantite' => $this->formatHTMLQuantity($mouvement),
			'origine' => $this->formatService->location($mouvement->getEmplacementFrom()),
			'destination' => $this->formatService->location($mouvement->getEmplacementTo()),
			'type' => $mouvement->getType(),
			'operateur' => $this->formatService->user($mouvement->getUser()),
			'unitPrice' => $mouvement->getUnitPrice(),
			'actions' => $this->templating->render('mouvement_stock/datatableMvtStockRow.html.twig', [
				'mvt' => $mouvement,
			]),
            'comment' => $mouvement->getComment() ?: '',
		];
    }

    /**
     * Allow to get the configuration of one line of the datatable for the quantity of a stock movement
     */
    public function formatHTMLQuantity(MouvementStock $stockMovement): string
    {
        $type = $stockMovement->getType();
        $quantity = $stockMovement->getQuantity();

        $color = match ($type) {
            MouvementStock::TYPE_ENTREE, MouvementStock::TYPE_INVENTAIRE_ENTREE =>  "green",
            MouvementStock::TYPE_SORTIE, MouvementStock::TYPE_INVENTAIRE_SORTIE => "red",
            default => "black"
        };

        $operator = match($type) {
            MouvementStock::TYPE_ENTREE, MouvementStock::TYPE_INVENTAIRE_ENTREE => "+",
            MouvementStock::TYPE_SORTIE, MouvementStock::TYPE_INVENTAIRE_SORTIE => "-",
            default => ''
        };

        return "<div class='d-flex w-100'>
                    <span style='font-weight: bold; color: {$color}; width:10px; height: auto;'>{$operator}</span>
                    <span style='font-weight: bold; color: {$color};'>{$quantity}</span>
                </div>";

    }

    public function getFromColumnConfig(MouvementStock $mouvement): array {
        if ($mouvement->getDeliveryRequest()) {
            $from = mb_strtolower($this->translation->translate("Demande", "Livraison", "Demande de livraison", false));
            $path = 'demande_show';
            $pathParams = [
                'id' => $mouvement->getDeliveryRequest()->getId()
            ];
        }
        else if ($mouvement->getShippingRequest()) {
            $from = mb_strtolower($this->translation->translate("Demande", "Expédition", "Demande d'expédition", false));
            $path = 'shipping_request_show';
            $pathParams = [
                'id' => $mouvement->getShippingRequest()->getId()
            ];
        }
        else if ($mouvement->getPreparationOrder()) {
            $from = $mouvement->getPreparationOrder()->getDemande()->isFastDelivery()
                ? 'préparation de livraison rapide'
                : 'préparation';
            $path = 'preparation_show';
            $pathParams = [
                'id' => $mouvement->getPreparationOrder()->getId()
            ];
        } else if ($mouvement->getLivraisonOrder()) {
            $from = $mouvement->getLivraisonOrder()->getDemande()->isFastDelivery()
                ? "ordre de livraison rapide"
                : mb_strtolower($this->translation->translate("Ordre", "Livraison", "Ordre de livraison", false));
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
            ->setQuantity($quantity)
            ->setUnitPrice($article->getPrixUnitaire());

        if($article instanceof Article) {
            $newMouvement->setArticle($article);

            if($type === MouvementStock::TYPE_SORTIE) {
                $article->setInactiveSince(new DateTime());
            }
        } else { // if($article instanceof ReferenceArticle) {
            $newMouvement->setRefArticle($article);
        }

        if (!$article->getLastMovement() || $article->getLastMovement()->getDate() < $newMouvement->getDate()) {
            $article->setLastMovement($newMouvement);
        }

        $from = $options['from'] ?? null;
        $locationTo = $options['locationTo'] ?? null;
        $comment = $options['comment'] ?? null;
        $date = $options['date'] ?? null;

        if($date){
            $this->updateMovementDates($newMouvement, $date);
        }

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
            else if ($from instanceof ShippingRequest) {
                $newMouvement->setShippingRequest($from);
            }
        }

        if ($locationTo) {
            $newMouvement->setEmplacementTo($locationTo);
        }

        if ($comment) {
            $newMouvement->setComment($comment);
        }

        return $newMouvement;
    }

    public function finishStockMovement(MouvementStock $mouvementStock,
                                        DateTime       $date,
                                        ?Emplacement   $locationTo): void {
        $mouvementStock->setEmplacementTo($locationTo);

        $this->updateMovementDates($mouvementStock, $date);
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
            ?? (isset($mouvement['receptionOrder']) ? join(", ", $mouvement['receptionOrder']) : '')
            ?? null;

        $data = [
            $this->formatService->datetime($mouvement['date'] ?? ''),
            $orderNo,
            $mouvement['refArticleRef'] ?? '',
            !empty($mouvement['refArticleBarCode']) ? $mouvement['refArticleBarCode']: '',
            !empty($mouvement['articleBarCode']) ? $mouvement['articleBarCode'] : '',
            $mouvement['quantity'] ?? '',
            $mouvement['originEmpl'] ?? '',
            $mouvement['destinationEmpl'] ?? '',
            $mouvement['type'] ?? '',
            $mouvement['operator'] ?? '',
            $mouvement['unitPrice'] ?? "",
            strip_tags($mouvement['comment']) ?? "",
        ];
        $CSVExportService->putLine($handle, $data);
    }

    public function updateMovementDates(MouvementStock $stockMovement, DateTime $date): void {
        $type = $stockMovement->getType();
        $reference = $stockMovement->getRefArticle() ?: $stockMovement->getArticle()->getReferenceArticle();

        $stockMovement->setDate($date);

        if ($type === MouvementStock::TYPE_SORTIE) {
            $reference->setLastStockExit($date);
        } else if ($type === MouvementStock::TYPE_ENTREE) {
            $reference->setLastStockEntry($date);
        }
    }

    public function getColumnVisibleConfig(Utilisateur $user): array {
        $columnsVisible = $user->getFieldModes('stockMovement');
        $fieldConfig = [
            ['name' => "actions", "class" => "noVis", "orderable" => false, "alwaysVisible" => true],
            ["title" => "Date", "name" => "date", 'searchable' => true],
            ["title" => "Issu de", "name" => "from", "orderable" => false, 'searchable' => true],
            ["title" => "Code barre", "name" => "barCode", 'searchable' => true],
            ["title" => "Référence article", "name" => "refArticle", 'searchable' => true],
            ["title" => "Quantité", "name" => "quantite", 'searchable' => true],
            ["title" => "Origine", "name" => "origine", 'searchable' => true],
            ["title" => "Destination", "name" => "destination", 'searchable' => true],
            ["title" => "Type", "name" => "type", 'searchable' => true],
            ["title" => "Opérateur", "name" => "operateur", 'searchable' => true],
            ["title" => "Prix Unitaire", "name" => "unitPrice", 'searchable' => true],
            ["title" => "Commentaire", "name" => "comment", "orderable" => false, 'searchable' => true],
        ];
        return $this->fieldModesService->getArrayConfig($fieldConfig, [], $columnsVisible);
    }

    public function isArticleMovable(string                   $movementType,
                                     ReferenceArticle|Article $article): bool {
        $expectedStatuses = match(true) {
            $article instanceof Article => match($movementType) {
                MouvementStock::TYPE_ENTREE => [Article::STATUT_ACTIF, Article::STATUT_EN_LITIGE, Article::STATUT_INACTIF],
                MouvementStock::TYPE_SORTIE, MouvementStock::TYPE_TRANSFER => [Article::STATUT_ACTIF, Article::STATUT_EN_LITIGE],
                default => null,
            },
            $article instanceof ReferenceArticle => [ReferenceArticle::STATUT_ACTIF],
            default => throw new RuntimeException("Unavailable article"),
        };

        return in_array($article->getStatut()?->getCode(), $expectedStatuses);
    }
}
