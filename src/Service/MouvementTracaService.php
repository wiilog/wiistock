<?php

namespace App\Service;

use App\Entity\CategorieStatut;
use App\Entity\Emplacement;
use App\Entity\FiltreSup;
use App\Entity\MouvementStock;
use App\Entity\MouvementTraca;

use App\Entity\Utilisateur;
use App\Repository\MouvementTracaRepository;
use App\Repository\FiltreSupRepository;

use App\Repository\StatutRepository;
use DateTime;
use Doctrine\ORM\NonUniqueResultException;
use Exception;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Routing\RouterInterface;

use Doctrine\ORM\EntityManagerInterface;
use Twig\Environment as Twig_Environment;

class MouvementTracaService
{
    public const INVALID_LOCATION_TO = 'invalid-location-to';

    /**
     * @var Twig_Environment
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
    private $statutRepository;

    public function __construct(UserService $userService,
                                MouvementTracaRepository $mouvementTracaRepository,
                                StatutRepository $statutRepository,
                                RouterInterface $router,
                                EntityManagerInterface $em,
                                Twig_Environment $templating,
                                FiltreSupRepository $filtreSupRepository,
                                Security $security)
    {
        $this->templating = $templating;
        $this->em = $em;
        $this->router = $router;
        $this->mouvementTracaRepository = $mouvementTracaRepository;
        $this->userService = $userService;
        $this->statutRepository = $statutRepository;
        $this->filtreSupRepository = $filtreSupRepository;
        $this->security = $security;
    }

	/**
	 * @param array|null $params
	 * @return array
	 * @throws Exception
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

    /**
     * @param string $colis
     * @param Emplacement $locationFrom
     * @param Utilisateur $user
     * @param DateTime $date
     * @param bool $fromNomade
     * @param bool|null $finished
     * @param string $typeMouvementTraca
     * @param MouvementStock|null $mouvementStock
     * @return MouvementTraca
     * @throws NonUniqueResultException
     * @throws Exception
     */
    public function persistMouvementTraca(string $colis,
                                          Emplacement $locationFrom,
                                          Utilisateur $user,
                                          DateTime $date,
                                          bool $fromNomade,
                                          ?bool $finished,
                                          string $typeMouvementTraca,
                                          MouvementStock $mouvementStock = null): MouvementTraca {

        if ($typeMouvementTraca !== MouvementTraca::TYPE_PRISE &&
            $typeMouvementTraca !== MouvementTraca::TYPE_DEPOSE) {
            throw new Exception('Le type de mouvement traca donné est invalide');
        }

        $type = $this->statutRepository->findOneByCategorieNameAndStatutName(CategorieStatut::MVT_TRACA, $typeMouvementTraca);

        $mouvementTraca = new MouvementTraca();
        $mouvementTraca
            ->setColis($colis)
            ->setEmplacement($locationFrom)
            ->setOperateur($user)
            ->setUniqueIdForMobile($fromNomade ? $this->generateUniqueIdForMobile($date) : null)
            ->setDatetime($date)
            ->setFinished($finished)
            ->setType($type)
            ->setMouvementStock($mouvementStock);
        $this->em->persist($mouvementTraca);
        return $mouvementTraca;
    }

    private function generateUniqueIdForMobile(DateTime $date): string {
        $uniqueId = null;
        //same format as moment.defaultFormat
        $dateStr = $date->format('Y-m-d\TH:i:sP');
        $randomLength = 9;
        do {
            $random = strtolower(substr(sha1(rand()), 0, $randomLength));
            $uniqueId = $dateStr . '_' . $random;
            $existingMouvements = $this->mouvementTracaRepository->findBy(['uniqueIdForMobile' => $uniqueId]);
        } while (!empty($existingMouvements));

        return $uniqueId;
    }
}
