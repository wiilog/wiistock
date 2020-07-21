<?php

namespace App\Service;

use App\Entity\ArticleFournisseur;
use App\Entity\FiltreSup;
use App\Entity\InventoryEntry;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Routing\RouterInterface;
use Doctrine\ORM\EntityManagerInterface;
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
    public function dataRowInvEntry($entry)
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
}
