<?php

namespace App\Service;

use App\Entity\FiltreSup;
use App\Entity\Import;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Doctrine\ORM\EntityManagerInterface;
use Twig\Environment as Twig_Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

class ImportDataService
{
    /**
     * @var Twig_Environment
     */
    private $templating;

    /**
     * @var RouterInterface
     */
    private $router;

    private $em;
    private $user;

    public function __construct(RouterInterface $router,
                                EntityManagerInterface $em,
                                Twig_Environment $templating,
                                TokenStorageInterface $tokenStorage)
    {
        $this->templating = $templating;
        $this->user = $tokenStorage->getToken()->getUser();
        $this->em = $em;
        $this->router = $router;
    }

	/**
	 * @param null $params
	 * @return array
	 * @throws LoaderError
	 * @throws RuntimeError
	 * @throws SyntaxError
	 */
    public function getDataForDatatable($params = null)
    {
		$importRepository = $this->em->getRepository(Import::class);
		$filtreSupRepository = $this->em->getRepository(FiltreSup::class);

		$filters = $filtreSupRepository->getFieldAndValueByPageAndUser(FiltreSup::PAGE_IMPORT, $this->user);

		$queryResult = $importRepository->findByParamsAndFilters($params, $filters);

		$imports = $queryResult['data'];

		$rows = [];
		foreach ($imports as $import) {
			$rows[] = $this->dataRowImport($import);
		}
		return [
			'data' => $rows,
			'recordsFiltered' => $queryResult['count'],
			'recordsTotal' => $queryResult['total'],
		];
    }

	/**
	 * @param Import $import
	 * @return array
	 * @throws LoaderError
	 * @throws RuntimeError
	 * @throws SyntaxError
	 */
    public function dataRowImport($import)
    {
        $importId = $import->getId();
        $url['edit'] = $this->router->generate('fournisseur_edit', ['id' => $importId]);
        $row = [
               'startDate' => $import->getStartDate() ? $import->getStartDate()->format('d/m/Y H:i') : '',
               'endDate' => $import->getEndDate() ? $import->getEndDate()->format('d/m/Y H:i') : '',
               'label' => $import->getLabel(),
               'newEntries' => $import->getNewEntries() ?? '',
               'updatedEntries' => $import->getUpdatedEntries(),
               'nbErrors' => $import->getNbErrors(),
               'status' => $import->getStatus() ? $import->getStatus()->getNom() : '',
               'user' => $import->getUser() ? $import->getUser()->getUsername() : '',
               'actions' => $this->templating->render('import/datatableImportRow.html.twig', [
                             'url' => $url,
                             'fournisseurId' => $importId
                    ]),
                    ];
        return $row;
    }
}


