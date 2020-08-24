<?php


namespace App\Service;

use App\Entity\Acheminements;
use App\Entity\FiltreSup;
use App\Entity\Utilisateur;
use DateTime;
use DateTimeZone;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Twig\Error\LoaderError as Twig_Error_Loader;
use Twig\Error\RuntimeError as Twig_Error_Runtime;
use Twig\Error\SyntaxError as Twig_Error_Syntax;
use Twig\Environment as Twig_Environment;

class AcheminementsService
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
     * @var Utilisateur
     */
    private $user;

    private $entityManager;

    public function __construct(TokenStorageInterface $tokenStorage,
                                RouterInterface $router,
                                EntityManagerInterface $entityManager,
                                Twig_Environment $templating) {
        $this->templating = $templating;
        $this->entityManager = $entityManager;
        $this->router = $router;
        $this->user = $tokenStorage->getToken()->getUser();
    }

    /**
     * @param null $params
     * @return array
     * @throws Twig_Error_Loader
     * @throws Twig_Error_Runtime
     * @throws Twig_Error_Syntax
     */
    public function getDataForDatatable($params = null) {

        $filtreSupRepository = $this->entityManager->getRepository(FiltreSup::class);
        $acheminementsRepository = $this->entityManager->getRepository(Acheminements::class);

        $filters = $filtreSupRepository->getFieldAndValueByPageAndUser(FiltreSup::PAGE_ACHEMINEMENTS, $this->user);
        $queryResult = $acheminementsRepository->findByParamAndFilters($params, $filters);

        $acheminementsArray = $queryResult['data'];

        $rows = [];
        foreach ($acheminementsArray as $acheminement) {
            $rows[] = $this->dataRowAcheminement($acheminement);
        }

        return [
            'data' => $rows,
            'recordsTotal' => $queryResult['total'],
            'recordsFiltered' => $queryResult['count'],
        ];
    }

	/**
	 * @param Acheminements $acheminement
	 * @return array
	 * @throws Twig_Error_Loader
	 * @throws Twig_Error_Runtime
	 * @throws Twig_Error_Syntax
	 */
    public function dataRowAcheminement($acheminement)
    {
        $nbColis = count($acheminement->getPacks());
        return [
            'id' => $acheminement->getId() ?? 'Non défini',
            'Date' => $acheminement->getDate() ? $acheminement->getDate()->format('d/m/Y H:i:s') : 'Non défini',
            'Demandeur' => $acheminement->getRequester() ? $acheminement->getRequester()->getUserName() : '',
            'Destinataire' => $acheminement->getReceiver() ? $acheminement->getReceiver()->getUserName() : '',
            'Emplacement prise' => $acheminement->getLocationFrom() ? $acheminement->getLocationFrom()->getLabel() : '',
            'Emplacement de dépose' => $acheminement->getLocationTo() ? $acheminement->getLocationTo()->getLabel() : '',
            'Nb Colis' => $nbColis ?? 0,
            'Type' => $acheminement->getType() ? $acheminement->getType()->getLabel() : '',
            'Statut' => $acheminement->getStatut() ? $acheminement->getStatut()->getNom() : '',
            'Urgence' => $acheminement->isUrgent() ? 'oui' : 'non',
            'Actions' => $this->templating->render('acheminements/datatableAcheminementsRow.html.twig', [
                'acheminement' => $acheminement
            ]),
        ];
    }

    public function createDateFromStr(?string $dateStr): ?DateTime {
        $date = null;
        foreach (['Y-m-d', 'd/m/Y'] as $format) {
            $date = (!empty($dateStr) && empty($date))
                ? DateTime::createFromFormat($format, $dateStr, new DateTimeZone("Europe/Paris"))
                : $date;
        }
        return $date ?: null;
    }
}
