<?php

namespace App\Service;

use App\Entity\FiltreSup;
use App\Entity\InventoryEntry;
use App\Repository\InventoryEntryRepository;
use App\Repository\FiltreSupRepository;
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
     * @var InventoryEntryRepository
     */
    private $inventoryEntryRepository;

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

    public function __construct(UserService $userService,
                                InventoryEntryRepository $inventoryEntryRepository,
                                RouterInterface $router,
                                EntityManagerInterface $em,
                                Twig_Environment $templating,
                                FiltreSupRepository $filtreSupRepository,
                                Security $security)
    {

        $this->templating = $templating;
        $this->em = $em;
        $this->router = $router;
        $this->inventoryEntryRepository = $inventoryEntryRepository;
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
		$filters = $this->filtreSupRepository->getFieldAndValueByPageAndUser(FiltreSup::PAGE_INV_ENTRIES, $this->security->getUser());

		$queryResult = $this->inventoryEntryRepository->findByParamsAndFilters($params, $filters);

		$invEntries = $queryResult['data'];

		$rows = [];
		foreach ($invEntries as $invEntry) {
			$rows[] = $this->dataRowInvEntry($invEntry);
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
		if ($article = $entry->getArticle()) {
			$label = $article->getLabel();
			$ref = $article->getReference();
		} else if ($refArticle = $entry->getRefArticle()) {
			$label = $refArticle->getLibelle();
			$ref = $refArticle->getReference();
		} else {
			$ref = $label = '';
		}
		$row =
		[
			'Ref' => $ref,
			'Label' => $label,
			'Operator' => $entry->getOperator() ? $entry->getOperator()->getUsername() : '',
			'Location' => $entry->getLocation() ? $entry->getLocation()->getLabel() : '',
			'Date' => $entry->getDate() ? $entry->getDate()->format('d/m/Y') : '',
			'Quantity' => $entry->getQuantity(),
		];

		return $row;
    }
}
