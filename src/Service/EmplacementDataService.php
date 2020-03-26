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
use Doctrine\ORM\EntityManagerInterface;

use Symfony\Component\Security\Core\Security;
use Symfony\Component\Routing\RouterInterface;

use Twig\Environment as Twig_Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

class EmplacementDataService
{
    const PAGE_EMPLACEMENT = 'emplacement';
    /**
     * @var Twig_Environment
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

    public function getDataForDatatable($params = null)
    {
        $data = $this->getEmplacementDataByParams($params);
        return $data;
    }

	/**
	 * @param null $params
	 * @return array
	 * @throws LoaderError
	 * @throws NonUniqueResultException
	 * @throws RuntimeError
	 * @throws SyntaxError
	 */
    public function getEmplacementDataByParams($params = null)
    {
        $user = $this->security->getUser();

        $filtreSupRepository = $this->entityManager->getRepository(FiltreSup::class);
        $emplacementRepository = $this->entityManager->getRepository(Emplacement::class);

		$filterStatus = $filtreSupRepository->findOnebyFieldAndPageAndUser(FiltreSup::FIELD_STATUT, self::PAGE_EMPLACEMENT, $user);
		$active = $filterStatus ? $filterStatus->getValue() : false;


    	$queryResult = $emplacementRepository->findByParamsAndExcludeInactive($params, $active);

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
	 * @throws LoaderError
	 * @throws RuntimeError
	 * @throws SyntaxError
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
