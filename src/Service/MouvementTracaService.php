<?php

namespace App\Service;

use App\Entity\FiltreSup;
use App\Entity\MouvementTraca;

use App\Repository\MouvementTracaRepository;
use App\Repository\FiltreSupRepository;

use Symfony\Component\Security\Core\Security;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

use Doctrine\ORM\EntityManagerInterface;

class MouvementTracaService
{
    /**
     * @var \Twig_Environment
     */
    private $templating;

    /**
     * @var MouvementTracaRepository
     */
    private $mouvementTracaRepository;

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

    public function __construct(UserService $userService, MouvementTracaRepository $mouvementTracaRepository, RouterInterface $router, EntityManagerInterface $em, \Twig_Environment $templating, TokenStorageInterface $tokenStorage, FiltreSupRepository $filtreSupRepository, Security $security)
    {
        $this->templating = $templating;
        $this->em = $em;
        $this->router = $router;
        $this->mouvementTracaRepository = $mouvementTracaRepository;
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
		$filters = $this->filtreSupRepository->getFieldAndValueByPageAndUser(FiltreSup::PAGE_MVT_TRACA, $this->security->getUser());

		$queryResult = $this->mouvementTracaRepository->findByParamsAndFilters($params, $filters);

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
	 * @param MouvementTraca $mouvement
	 * @return array
	 * @throws \Twig_Error_Loader
	 * @throws \Twig_Error_Runtime
	 * @throws \Twig_Error_Syntax
	 */
    public function dataRowMouvement($mouvement)
    {
		$row = [
			'id' => $mouvement->getId(),
			'date' => $mouvement->getDatetime() ? $mouvement->getDatetime()->format('d/m/Y H:i') : '',
			'colis' => $mouvement->getColis(),
			'location' => $mouvement->getEmplacement() ? $mouvement->getEmplacement()->getLabel() : '',
			'type' => $mouvement->getType() ? $mouvement->getType()->getNom() : '',
			'operateur' => $mouvement->getOperateur() ? $mouvement->getOperateur()->getUsername() : '',
			'Actions' => $this->templating->render('mouvement_traca/datatableMvtTracaRow.html.twig', [
				'mvt' => $mouvement,
			])
		];

        return $row;
    }
}
