<?php

namespace App\Service;

use App\Entity\Arrivage;
use App\Entity\FiltreSup;
use App\Entity\Urgence;
use App\Entity\Utilisateur;
use App\Repository\ArrivageRepository;
use Doctrine\ORM\NonUniqueResultException;
use Symfony\Component\Security\Core\Security;
use App\Repository\FiltreSupRepository;
use Symfony\Component\Routing\RouterInterface;
use Doctrine\ORM\EntityManagerInterface;
use Twig\Environment as Twig_Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;


class ArrivageDataService
{
    /**
     * @var Twig_Environment
     */
    private $templating;

    /**
     * @var ArrivageRepository
     */
    private $arrivageRepository;

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
    private $mailerService;

    public function __construct(UserService $userService,
                                ArrivageRepository $arrivageRepository,
                                RouterInterface $router,
                                EntityManagerInterface $em,
                                MailerService $mailerService,
                                Twig_Environment $templating,
                                FiltreSupRepository $filtreSupRepository,
                                Security $security)
    {

        $this->templating = $templating;
        $this->em = $em;
        $this->router = $router;
        $this->arrivageRepository = $arrivageRepository;
        $this->userService = $userService;
        $this->filtreSupRepository = $filtreSupRepository;
        $this->security = $security;
        $this->mailerService = $mailerService;
    }

	/**
	 * @param array $params
	 * @param int|null $userId
	 * @return array
	 * @throws LoaderError
	 * @throws NonUniqueResultException
	 * @throws RuntimeError
	 * @throws SyntaxError
	 */
    public function getDataForDatatable($params = null, $userId)
    {
    	$filters = $this->filtreSupRepository->getFieldAndValueByPageAndUser(FiltreSup::PAGE_ARRIVAGE, $this->security->getUser());

		$queryResult = $this->arrivageRepository->findByParamsAndFilters($params, $filters, $userId);

		$arrivages = $queryResult['data'];

		$rows = [];
		foreach ($arrivages as $arrivage) {
			$rows[] = $this->dataRowArrivage($arrivage);
		}
		return [
			'data' => $rows,
			'recordsFiltered' => $queryResult['count'],
			'recordsTotal' => $queryResult['total'],
		];
    }

	/**
	 * @param Arrivage $arrivage
	 * @return array
	 * @throws NonUniqueResultException
	 * @throws LoaderError
	 * @throws RuntimeError
	 * @throws SyntaxError
	 */
    public function dataRowArrivage($arrivage)
    {
		$url = $this->router->generate('arrivage_show', [
			'id' => $arrivage->getId(),
		]);

		$acheteursUsernames = [];
		foreach ($arrivage->getAcheteurs() as $acheteur) {
			$acheteursUsernames[] = $acheteur->getUsername();
		}

		$row = [
			'id' => $arrivage->getId(),
			'NumeroArrivage' => $arrivage->getNumeroArrivage() ?? '',
			'Transporteur' => $arrivage->getTransporteur() ? $arrivage->getTransporteur()->getLabel() : '',
			'Chauffeur' => $arrivage->getChauffeur() ? $arrivage->getChauffeur()->getPrenomNom() : '',
			'NoTracking' => $arrivage->getNoTracking() ?? '',
			'NumeroBL' => $arrivage->getNumeroBL() ?? '',
			'NbUM' => $this->arrivageRepository->countColisByArrivage($arrivage),
			'Fournisseur' => $arrivage->getFournisseur() ? $arrivage->getFournisseur()->getNom() : '',
			'Destinataire' => $arrivage->getDestinataire() ? $arrivage->getDestinataire()->getUsername() : '',
			'Acheteurs' => implode(', ', $acheteursUsernames),
			'Statut' => $arrivage->getStatut() ? $arrivage->getStatut()->getNom() : '',
			'Date' => $arrivage->getDate() ? $arrivage->getDate()->format('d/m/Y H:i:s') : '',
			'Utilisateur' => $arrivage->getUtilisateur() ? $arrivage->getUtilisateur()->getUsername() : '',
			'Actions' => $this->templating->render(
				'arrivage/datatableArrivageRow.html.twig',
				['url' => $url, 'arrivage' => $arrivage]
			),
		];

        return $row;
    }

    /**
     * @param Arrivage $arrivage
     * @param Urgence[] $emergencies
     */
    public function addBuyersToArrivage(Arrivage $arrivage, array $emergencies): void {
        foreach ($emergencies as $emergency) {
            $emergencyBuyer = $emergency->getBuyer();
            if (isset($emergencyBuyer) &&
                !$arrivage->getAcheteurs()->contains($emergencyBuyer)) {
                $arrivage->addAcheteur($emergencyBuyer);
            }
        }
    }

    public function sendArrivageUrgentEmail(Arrivage $arrivage): void {
        if($arrivage->getIsUrgent()) {
            $this->mailerService->sendMail(
                'FOLLOW GT // Arrivage urgent',
                $this->templating->render(
                    'mails/mailArrivageUrgent.html.twig',
                    [
                        'title' => 'Arrivage urgent',
                        'arrivage' => $arrivage
                    ]
                ),
                array_map(
                    function (Utilisateur $buyer) {
                        return $buyer->getEmail();
                    },
                    $arrivage->getAcheteurs()->toArray()
                )
            );
        }
    }
}
