<?php
/**
 * Created by VisualStudioCode.
 * User: jv.Sicot
 * Date: 03/04/2019
 * Time: 15:09.
 */

namespace App\Service;

use App\Entity\Emplacement;

use App\Entity\FiltreSup;
use App\Repository\EmplacementRepository;

use Doctrine\ORM\NonUniqueResultException;
use Symfony\Component\Security\Core\Security;
use App\Repository\FiltreSupRepository;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Doctrine\ORM\EntityManagerInterface;

class EmplacementDataService
{
    const PAGE_EMPLACEMENT = 'emplacement';
    /**
     * @var \Twig_Environment
     */
    private $templating;

    /**
     * @var EmplacementRepository
     */
    private $emplacementRepository;

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

    public function __construct(UserService $userService, EmplacementRepository $emplacementRepository, RouterInterface $router, EntityManagerInterface $em, \Twig_Environment $templating, TokenStorageInterface $tokenStorage, FiltreSupRepository $filtreSupRepository, Security $security)
    {
    
        $this->templating = $templating;
        $this->em = $em;
        $this->router = $router;
        $this->emplacementRepository = $emplacementRepository;
        $this->userService = $userService;
        $this->filtreSupRepository = $filtreSupRepository;
        $this->security = $security;
    }
    
    public function getDataForDatatable($params = null)
    {
        $data = $this->getEmplacementDataByParams($params);
        return $data;
    }

	/**
	 * @param null $params
	 * @return array
	 * @throws \Twig_Error_Loader
	 * @throws \Twig_Error_Runtime
	 * @throws \Twig_Error_Syntax
	 * @throws NonUniqueResultException
	 */
    public function getEmplacementDataByParams($params = null)
    {
        $user = $this->security->getUser();
		$filterStatus = $this->filtreSupRepository->findOnebyFieldAndPageAndUser(FiltreSup::FIELD_STATUT, self::PAGE_EMPLACEMENT, $user);
		$active = $filterStatus ? $filterStatus->getValue() : false;


    	$queryResult = $this->emplacementRepository->findByParamsAndExcludeInactive($params, $active);

        $emplacements = $queryResult['data'];
        $listId = $queryResult['allEmplacementDataTable'];

        $emplacementsString = [];
        foreach ($listId as $id) {
            $emplacementsString[] = $id->getId();
        }

        $rows = [];
        foreach ($emplacements as $emplacement) {
            $rows[] = $this->dataRowEmplacement($emplacement);
        }
        return [
            'data' => $rows,
            'recordsFiltered' => $queryResult['count'],
            'recordsTotal' => $queryResult['total'],
            'listId' => $emplacementsString,
        ];
    }

    /**
     * @param Emplacement $emplacement
     * @return array
     * @throws \Twig_Error_Loader
     * @throws \Twig_Error_Runtime
     * @throws \Twig_Error_Syntax
     */
    public function dataRowEmplacement($emplacement)
    {
        $url['edit'] = $this->router->generate('emplacement_edit', ['id' => $emplacement->getId()]);

        $row = [
                    'id' => ($emplacement->getId() ? $emplacement->getId() : 'Non défini'),
                    'Nom' => ($emplacement->getLabel() ? $emplacement->getLabel() : 'Non défini'),
                    'Description' => ($emplacement->getDescription() ? $emplacement->getDescription() : 'Non défini'),
					'Point de livraison' => $emplacement->getIsDeliveryPoint() ? 'oui' : 'non',
                    'Délai maximum' => $emplacement->getDateMaxTime() ?? '',
					'Actif / Inactif' => $emplacement->getIsActive() ? 'actif' : 'inactif',
                    'Actions' => $this->templating->render('emplacement/datatableEmplacementRow.html.twig', [
                        'url' => $url,
                        'emplacementId' => $emplacement->getId(),
                    ]),
                    ];
        return $row;
    }
}
