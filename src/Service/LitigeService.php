<?php

namespace App\Service;

use App\Entity\FiltreSup;

use App\Entity\Litige;
use App\Repository\FiltreSupRepository;

use App\Repository\LitigeRepository;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

use Doctrine\ORM\EntityManagerInterface;

class LitigeService
{
    /**
     * @var \Twig_Environment
     */
    private $templating;

    /**
     * @var LitigeRepository
     */
    private $litigeRepository;

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

    public function __construct(UserService $userService, LitigeRepository $litigeRepository, RouterInterface $router, EntityManagerInterface $em, \Twig_Environment $templating, TokenStorageInterface $tokenStorage, FiltreSupRepository $filtreSupRepository, Security $security)
    {
        $this->templating = $templating;
        $this->em = $em;
        $this->router = $router;
        $this->litigeRepository = $litigeRepository;
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
		$filters = $this->filtreSupRepository->getFieldAndValueByPageAndUser(FiltreSup::PAGE_LITIGE_ARR, $this->security->getUser());

		$queryResult = $this->litigeRepository->findByParamsAndFilters($params, $filters);

		$litiges = $queryResult['data'];

		$rows = [];
		foreach ($litiges as $litige) {
			$rows[] = $this->dataRowLitige($litige);
		}

		return [
			'data' => $rows,
			'recordsFiltered' => $queryResult['count'],
			'recordsTotal' => $queryResult['total'],
		];
    }

	/**
	 * @param array $litige
	 * @return array
	 * @throws \Twig_Error_Loader
	 * @throws \Twig_Error_Runtime
	 * @throws \Twig_Error_Syntax
	 */
    public function dataRowLitige($litige)
    {
    	$litigeId = $litige['id'];
		$acheteursUsernames = $this->litigeRepository->getAcheteursByLitigeId($litigeId, 'username');

		$lastHistoric = $this->litigeRepository->getLastHistoricByLitigeId($litigeId);
		$lastHistoricStr = $lastHistoric ? $lastHistoric['date']->format('d/m/Y H:i') . ' : ' . nl2br($lastHistoric['comment']) : '';
		$row = [
			'type' => $litige['type'] ?? '',
			'arrivalNumber' => $litige['numeroArrivage'] ?? '',
			'buyers' => implode(', ', $acheteursUsernames),
			'provider' => $litige['provider'] ?? '',
			'carrier' => $litige['carrier'] ?? '',
			'lastHistoric' => $lastHistoricStr,
			'status' => $litige['status'] ?? '',
			'creationDate' => $litige['creationDate'] ? $litige['creationDate']->format('d/m/Y H:i') : '',
			'updateDate' => $litige['updateDate'] ? $litige['updateDate']->format('d/m/Y H:i') : '',
			'actions' => $this->templating->render('litige/datatableLitigesArrivageRow.html.twig', [
				'litigeId' => $litige['id'],
				'arrivageId' => $litige['arrivageId']
			])
		];

        return $row;
    }
}
