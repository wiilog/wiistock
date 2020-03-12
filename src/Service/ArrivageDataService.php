<?php

namespace App\Service;

use App\Entity\Arrivage;
use App\Entity\FiltreSup;
use App\Entity\Urgence;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
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
    private $mailerService;
    private $entityManager;
    private $specificService;

    public function __construct(UserService $userService,
                                RouterInterface $router,
                                MailerService $mailerService,
                                SpecificService $specificService,
                                Twig_Environment $templating,
                                EntityManagerInterface $entityManager,
                                Security $security)
    {

        $this->templating = $templating;
        $this->router = $router;
        $this->entityManager = $entityManager;
        $this->userService = $userService;
        $this->security = $security;
        $this->mailerService = $mailerService;
        $this->specificService = $specificService;
    }

	/**
	 * @param array $params
	 * @param int|null $userId
	 * @return array
	 * @throws LoaderError
	 * @throws NoResultException
	 * @throws NonUniqueResultException
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
	 * @throws NoResultException
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
            'NumeroCommandeList' => $arrivage->getNumeroCommandeList(),
            'NbUM' => $arrivageRepository->countColisByArrivage($arrivage),
			'Duty' => $arrivage->getDuty() ? 'oui' : 'non',
			'Frozen' => $arrivage->getFrozen() ? 'oui' : 'non',
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
     * @param Arrivage $arrival
     * @param Urgence[] $emergencies
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function sendArrivageUrgentEmail(Arrivage $arrival, array $emergencies): void {

        /*
         * TODO AB
            ('FOLLOW GT // Arrivage') . (count($emergencies) > 0 ? ' urgent' : ''),
            $this->templating->render(
                'mails/mailArrivage' . (count($emergencies) > 0 ? 'Urgent' : 'Regular') . '.html.twig',
                [
                    'title' => 'Arrivage ' . (count($emergencies) > 0 ? 'urgent ' : '') . 'reçu',
                    'arrivage' => $arrivage,
                    'posts' => $posts
         */

        $this->mailerService->sendMail(
            'FOLLOW GT // Arrivage urgent',
            $this->templating->render(
                'mails/mailArrivageUrgent.html.twig',
                [
                    'title' => 'Arrivage urgent',
                    'arrival' => $arrival,
                    'emergencies' => $emergencies
                ]
            ),
            array_reduce(
                $emergencies,
                function (array $carry, Urgence $emergency) {
                    $email = $emergency->getBuyer()->getEmail();
                    if (!in_array($email, $carry)) {
                        $carry[] = $email;
                    }
                    return $carry;
                },
                []
            )
        );
    }

    /**
     * @param Arrivage $arrivage
     * @param Urgence[] $emergencies
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function setArrivalUrgent(Arrivage $arrivage, array $emergencies): void {
        if (!empty($emergencies)) {
            $arrivage->setIsUrgent(true);
            foreach ($emergencies as $emergency) {
            	$emergency->setLastArrival($arrivage);
			}
            $this->entityManager->flush();
			$this->sendArrivageUrgentEmail($arrivage, $emergencies);
		}
    }

    /**
     * @param Arrivage $arrivage
     * @param bool $isSEDCurrentClient
     * @param Urgence[] $urgences
     * @return array
     */
    public function createArrivalAlertConfig(Arrivage $arrivage,
                                             bool $isSEDCurrentClient,
                                             array $urgences = []): array {
        $isArrivalUrgent = count($urgences);

        if ($isSEDCurrentClient && $isArrivalUrgent) {
            $numeroCommande = $urgences[0]->getCommande();

            $posts = array_map(
                function (Urgence $urgence) {
                    return $urgence->getPostNb();
                },
                $urgences
            );

            $nbPosts = count($posts);

            if ($nbPosts == 0) {
                $msgSedUrgent = "L'arrivage est-il urgent sur la commande $numeroCommande ?";
            } else {
                if ($nbPosts == 1) {
                    $msgSedUrgent = "
                        Le poste <span class='bold'>" . $posts[0] . "</span> est urgent sur la commande <span class=\"bold\">$numeroCommande</span>.<br/>
					    L'avez-vous reçu dans cet arrivage ?
					";
                } else {
                    $postsStr = implode(', ', $posts);
                    $msgSedUrgent = "
                        Les postes <span class=\"bold\">$postsStr</span> sont urgents sur la commande <span class=\"bold\">$numeroCommande</span>.<br/>
					    Les avez-vous reçus dans cet arrivage ?
                    ";
                }
            }
        }

        return [
            'autoHide' => (!$isSEDCurrentClient && !$isArrivalUrgent),
            'message' => ($isArrivalUrgent
                ? (!$isSEDCurrentClient
                    ? 'Arrivage URGENT enregistré avec succès.'
                    : ($msgSedUrgent ?? ''))
                : 'Arrivage enregistré avec succès.'),
            'iconType' => $isArrivalUrgent ? 'warning' : 'success',
            'modalType' => ($isSEDCurrentClient && $isArrivalUrgent) ? 'yes-no-question' : 'info',
            'emergencyAlert' => $isArrivalUrgent,
            'arrivalId' => $arrivage->getId()
        ];
    }

    /**
     * @param Arrivage $arrival
     * @return array List of alertConfig to display to the client
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function processEmergenciesOnArrival(Arrivage $arrival): array {
        $numeroCommandeList = $arrival->getNumeroCommandeList();
        $alertConfigs = [];

        $isSEDCurrentClient = $this->specificService->isCurrentClientNameFunction(SpecificService::CLIENT_SAFRAN_ED);

        if (!empty($numeroCommandeList)) {
            $urgenceRepository = $this->entityManager->getRepository(Urgence::class);

            foreach ($numeroCommandeList as $numeroCommande) {
                $urgencesMatching = $urgenceRepository->findUrgencesMatching(
                    $arrival->getDate(),
                    $arrival->getFournisseur(),
                    $numeroCommande,
                    $isSEDCurrentClient
                );

                if (!empty($urgencesMatching)) {
                    if (!$isSEDCurrentClient) {
                        $this->setArrivalUrgent($arrival, $urgencesMatching);
                        break;
                    }
                    else {
                        $alertConfigs[] = $this->createArrivalAlertConfig(
                            $arrival,
                            $isSEDCurrentClient,
                            $urgencesMatching
                        );
                    }
                }
            }
        }

        if (empty($alertConfigs) || !$isSEDCurrentClient) {
            $alertConfigs[] = $this->createArrivalAlertConfig($arrival, $isSEDCurrentClient);
        }

        return $alertConfigs;
    }
}
