<?php

namespace App\Service;

use App\Entity\FiltreSup;
use App\Entity\Litige;
use Exception;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment as Twig_Environment;
use Doctrine\ORM\EntityManagerInterface;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

class LitigeService
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
    private $translator;

    public function __construct(UserService $userService,
                                RouterInterface $router,
                                EntityManagerInterface $entityManager,
                                Twig_Environment $templating,
                                TranslatorInterface $translator,
                                Security $security)
    {
        $this->templating = $templating;
        $this->entityManager = $entityManager;
        $this->translator = $translator;
        $this->router = $router;
        $this->userService = $userService;
        $this->security = $security;
    }

	/**
	 * @param array|null $params
	 * @return array
	 * @throws Exception
	 */
    public function getDataForDatatable($params = null)
    {

        $filtreSupRepository = $this->entityManager->getRepository(FiltreSup::class);
        $litigeRepository = $this->entityManager->getRepository(Litige::class);

		$filters = $filtreSupRepository->getFieldAndValueByPageAndUser(FiltreSup::PAGE_LITIGE, $this->security->getUser());

		$queryResult = $litigeRepository->findByParamsAndFilters($params, $filters);

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
	 * @throws LoaderError
	 * @throws RuntimeError
	 * @throws SyntaxError
	 */
    public function dataRowLitige($litige)
    {
        $litigeRepository = $this->entityManager->getRepository(Litige::class);

    	$litigeId = $litige['id'];
		$acheteursArrivage = $litigeRepository->getAcheteursArrivageByLitigeId($litigeId, 'username');
		$acheteursReception = $litigeRepository->getAcheteursReceptionByLitigeId($litigeId, 'username');

		$lastHistoric = $litigeRepository->getLastHistoricByLitigeId($litigeId);
		$lastHistoricStr = $lastHistoric ? $lastHistoric['date']->format('d/m/Y H:i') . ' : ' . nl2br($lastHistoric['comment']) : '';

		$commands = $litigeRepository->getCommandesByLitigeId($litigeId);

		$references = $litigeRepository->getReferencesByLitigeId($litigeId);

		$isNumeroBLJson = !empty($litige['arrivageId']);
		$numerosBL = isset($litige['numCommandeBl'])
            ? ($isNumeroBLJson
                ? implode(', ', json_decode($litige['numCommandeBl'], true))
                : $litige['numCommandeBl'])
            : '';

		$row = [
			'actions' => $this->templating->render('litige/datatableLitigesRow.html.twig', [
				'litigeId' => $litige['id'],
				'arrivageId' => $litige['arrivageId'],
				'receptionId' => $litige['receptionId'],
				'isArrivage' => !empty($litige['arrivageId']) ? 1 : 0
			]),
			'type' => $litige['type'] ?? '',
			'arrivalNumber' => $litige['numeroArrivage'] ?? '',
			'receptionNumber' => $this->templating->render('litige/datatableLitigesRowFrom.html.twig', [
				'receptionNb' => $litige['numeroReception'] ?? '',
				'receptionId' => $litige['receptionId']
			]),
            'references' => $references,
			'command' => $commands,
			'numCommandeBl' => $numerosBL,
			'buyers' => implode(', ', array_merge($acheteursArrivage, $acheteursReception)),
			'provider' => $litige['provider'] ?? '',
			'lastHistoric' => $lastHistoricStr,
			'creationDate' => $litige['creationDate'] ? $litige['creationDate']->format('d/m/Y H:i') : '',
			'updateDate' => $litige['updateDate'] ? $litige['updateDate']->format('d/m/Y H:i') : '',
			'status' => $litige['status'] ?? '',
            'urgence' => $litige['emergencyTriggered']
		];
        return $row;
    }

    public function getLitigeOrigin(): array {
        return [
            Litige::ORIGIN_ARRIVAGE => $this->translator->trans('arrivage.arrivage'),
            Litige::ORIGIN_RECEPTION => $this->translator->trans('réception.réception')
        ];
    }
}
