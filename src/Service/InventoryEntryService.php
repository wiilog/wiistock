<?php

namespace App\Service;

use App\Entity\Article;
use App\Entity\ArticleFournisseur;
use App\Entity\FiltreSup;
use App\Entity\Inventory\InventoryEntry;
use App\Entity\ReferenceArticle;
use App\Helper\FormatHelper;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Security;
use Twig\Environment as Twig_Environment;


class InventoryEntryService
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

    private $CSVExportService;

    public function __construct(UserService $userService,
                                RouterInterface $router,
                                EntityManagerInterface $entityManager,
                                Twig_Environment $templating,
                                Security $security,
                                CSVExportService $CSVExportService)
    {

        $this->templating = $templating;
        $this->entityManager = $entityManager;
        $this->router = $router;
        $this->userService = $userService;
        $this->security = $security;
        $this->CSVExportService = $CSVExportService;
    }

	/**
	 * @param array|null $params
	 * @return array
	 * @throws \Exception
	 */
    public function getDataForDatatable($params = null)
    {
        $filtreSupRepository = $this->entityManager->getRepository(FiltreSup::class);
        $inventoryEntryRepository = $this->entityManager->getRepository(InventoryEntry::class);

		$filters = $filtreSupRepository->getFieldAndValueByPageAndUser(FiltreSup::PAGE_INV_ENTRIES, $this->security->getUser());
		$queryResult = $inventoryEntryRepository->findByParamsAndFilters($params, $filters);

		$invEntries = $queryResult['data'];

		$rows = [];
		foreach ($invEntries as $invEntry) {
			$rows[] = $this->dataRowInvEntry($invEntry instanceof InventoryEntry ? $invEntry : $invEntry[0]);
		}

		return [
			'data' => $rows,
			'recordsFiltered' => $queryResult['count'],
			'recordsTotal' => $queryResult['total'],
		];
    }

	/**
	 * @param InventoryEntry $entry
	 * @return array
	 */
    public function dataRowInvEntry(InventoryEntry $entry): array
    {
        $article = $entry->getArticle();
        $refArticle = $entry->getRefArticle();

		if ($article) {
			$label = $article->getLabel();
			/** @var ArticleFournisseur $articleFournisseur */
			$articleFournisseur = $article->getArticleFournisseur();
			$referenceArticle = isset($articleFournisseur) ? $articleFournisseur->getReferenceArticle() : null;
            $ref = isset($referenceArticle) ? $referenceArticle->getReference() : '';
            $barCode = $article->getBarCode();
		} else if ($refArticle) {
			$label = $refArticle->getLibelle();
            $ref = $refArticle->getReference();
            $barCode = $refArticle->getBarCode();
		} else {
			$ref = '';
            $label = '';
            $barCode = '';
		}

        return [
            'Ref' => $ref,
            'Label' => $label,
            'barCode' => $barCode,
            'Operator' => $entry->getOperator() ? $entry->getOperator()->getUsername() : '',
            'Location' => $entry->getLocation() ? $entry->getLocation()->getLabel() : '',
            'Date' => $entry->getDate() ? $entry->getDate()->format('d/m/Y') : '',
            'Quantity' => $entry->getQuantity(),
        ];
    }

    /**
     * @param InventoryEntry $entry
     * @param $handle
     */
    public function putEntryLine(InventoryEntry $entry,
                                 $handle)
    {
        $article = $entry->getArticle();
        $referenceArticle = $entry->getRefArticle();

        if (!empty($referenceArticle)) {
            $dataEntry = [
                $referenceArticle->getLibelle() ?? '',
                $referenceArticle->getReference() ?? '',
                $referenceArticle->getBarCode() ?? '',
            ];
        }
        else if (!empty($article)) {
            $articleFournisseur = $article->getArticleFournisseur();
            $referenceArticle = $articleFournisseur ? $articleFournisseur->getReferenceArticle() : null;

            $dataEntry = [
                $article->getLabel() ?? '',
                $referenceArticle ? $referenceArticle->getReference() : '',
                $article->getBarCode() ?? '',
            ];
        }

        if (isset($dataEntry)) {
            $data = array_merge($dataEntry, $entry->serialize());
            $this->CSVExportService->putLine($handle, $data);
        }
    }
    /**
     * @param Article|ReferenceArticle $entity
     * @param InventoryEntry|null $entry
     * @param $handle
     */
    public function putMissionEntryLine($entity,
                                        ?InventoryEntry $entry,
                                        $handle): void {

        if ($entity instanceof ReferenceArticle) {
            $dataEntry = [
                $entity->getLibelle() ?? '',
                $entity->getReference() ?? '',
                $entity->getBarCode() ?? '',
                $entity->getQuantiteStock() ?? '',
                FormatHelper::location($entity->getEmplacement())
            ];
        }
        else if ($entity instanceof Article) {
            $articleFournisseur = $entity->getArticleFournisseur();
            $referenceArticle = $articleFournisseur ? $articleFournisseur->getReferenceArticle() : null;

            $dataEntry = [
                $entity->getLabel() ?? '',
                $referenceArticle ? $referenceArticle->getReference() : '',
                $entity->getBarCode() ?? '',
                $entity->getQuantite() ?? '',
                FormatHelper::location($entity->getEmplacement())
            ];
        }

        if (isset($dataEntry)) {
            $data = array_merge(
                $dataEntry,
                $entry
                    ? [
                        FormatHelper::date($entry->getDate()),
                        FormatHelper::bool($entry->getAnomaly())
                    ]
                    : []
            );
            $this->CSVExportService->putLine($handle, $data);
        }
    }
}
