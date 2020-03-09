<?php

namespace App\Service;

use App\Entity\Arrivage;
use App\Entity\FiltreSup;
use App\Entity\Urgence;
use App\Entity\Utilisateur;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Routing\RouterInterface;
use Doctrine\ORM\EntityManagerInterface;
use Twig\Environment as Twig_Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;


class ArrivageDataService
{
    private $templating;
    private $router;
    private $userService;
    private $security;
    private $em;
    private $mailerService;
    private $entityManager;

    public function __construct(UserService $userService,
                                RouterInterface $router,
                                EntityManagerInterface $em,
                                MailerService $mailerService,
                                Twig_Environment $templating,
                                EntityManagerInterface $entityManager,
                                Security $security)
    {

        $this->templating = $templating;
        $this->em = $em;
        $this->router = $router;
        $this->entityManager = $entityManager;
        $this->userService = $userService;
        $this->security = $security;
        $this->mailerService = $mailerService;
    }

    /**
     * @param array $params
     * @param int|null $userId
     * @return array
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function getDataForDatatable($params = null, $userId)
    {
        $arrivageRepository = $this->entityManager->getRepository(Arrivage::class);
        $filtreSupRepository = $this->entityManager->getRepository(FiltreSup::class);

        $filters = $filtreSupRepository->getFieldAndValueByPageAndUser(FiltreSup::PAGE_ARRIVAGE, $this->security->getUser());

        $queryResult = $arrivageRepository->findByParamsAndFilters($params, $filters, $userId);

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
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    public function dataRowArrivage($arrivage)
    {
		$url = $this->router->generate('arrivage_show', [
			'id' => $arrivage->getId(),
		]);
        $arrivageRepository = $this->entityManager->getRepository(Arrivage::class);

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
			'NbUM' => $arrivageRepository->countColisByArrivage($arrivage),
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
            'urgent' => $arrivage->getIsUrgent()
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

    public function sendArrivageUrgentEmail(Arrivage $arrivage, array $emergencies): void {

        $posts = array_reduce(
            $emergencies,
            function (array $carry, Urgence $emergency) {
                if ($emergency->getPostNb()) {
                    $carry[] = $emergency->getPostNb();
                }
                return $carry;
            },
            []
        );

        $this->mailerService->sendMail(
            'FOLLOW GT // Arrivage urgent',
            $this->templating->render(
                'mails/mailArrivageUrgent.html.twig',
                [
                    'title' => 'Arrivage urgent',
                    'arrivage' => $arrivage,
                    'posts' => $posts
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

	/**
	 * @param Arrivage $arrivage
	 * @param Urgence[] $emergencies
	 */
    public function setArrivalUrgent(Arrivage $arrivage, array $emergencies): void {
        if (!empty($emergencies)) {
            $arrivage->setIsUrgent(true);
            foreach ($emergencies as $emergency) {
            	$emergency->setLastArrival($arrivage);
			}
            $this->addBuyersToArrivage($arrivage, $emergencies);
            $this->entityManager->flush();
			$this->sendArrivageUrgentEmail($arrivage, $emergencies);
		}
    }
}
