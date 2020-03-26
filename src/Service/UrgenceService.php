<?php


namespace App\Service;


use App\Entity\FiltreSup;
use App\Entity\Fournisseur;
use App\Entity\Transporteur;
use App\Entity\Urgence;
use App\Entity\Utilisateur;
use App\Repository\ArticleRepository;
use DateTime;
use DateTimeZone;
use Symfony\Component\Security\Core\Security;
use Twig\Environment as Twig_Environment;
use App\Repository\UrgenceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class UrgenceService
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
     * @var ArticleRepository
     */
    private $articleRepository;

    /**
     * @var UrgenceRepository
     */
    private $urgenceRepository;

    /**
     * @var Utilisateur
     */
    private $user;

    private $entityManager;

	/**
	 * @var Security
	 */
    private $security;

    /**
     * @var SpecificService
     */
    private $specificService;

    public function __construct(TokenStorageInterface $tokenStorage,
                                RouterInterface $router,
                                EntityManagerInterface $entityManager,
                                Twig_Environment $templating,
                                ArticleRepository $articleRepository,
                                UrgenceRepository $urgenceRepository,
								SpecificService $specificService,
								Security $security)
    {
        $this->templating = $templating;
        $this->entityManager = $entityManager;
        $this->router = $router;
        $this->articleRepository = $articleRepository;
        $this->urgenceRepository = $urgenceRepository;
        $this->user = $tokenStorage->getToken()->getUser();
        $this->security = $security;
        $this->specificService = $specificService;
    }

    public function getDataForDatatable($params = null)
    {
        $filtreSupRepository = $this->entityManager->getRepository(FiltreSup::class);
		$filters = $filtreSupRepository->getFieldAndValueByPageAndUser(FiltreSup::PAGE_URGENCES, $this->security->getUser());

		$queryResult = $this->urgenceRepository->findByParamsAndFilters($params, $filters);

        $urgenceArray = $queryResult['data'];

        $rows = [];
        foreach ($urgenceArray as $urgence) {
            $rows[] = $this->dataRowUrgence($urgence);
        }

        return [
            'data' => $rows,
            'recordsTotal' => $queryResult['total'],
            'recordsFiltered' => $queryResult['count'],
        ];
    }

    public function dataRowUrgence(Urgence $urgence)
    {
        return [
            'start' => $urgence->getDateStart()->format('d/m/Y H:i'),
            'end' => $urgence->getDateEnd()->format('d/m/Y H:i'),
            'commande' => $urgence->getCommande(),
            'arrivalDate' => $urgence->getLastArrival() && $urgence->getLastArrival()->getDate() ? $urgence->getLastArrival()->getDate()->format('d/m/Y H:i') : '',
            'buyer' => $urgence->getBuyer() ? $urgence->getBuyer()->getUsername() : '',
            'provider' => $urgence->getProvider() ? $urgence->getProvider()->getNom() : '',
            'carrier' => $urgence->getCarrier() ? $urgence->getCarrier()->getLabel() : '',
            'trackingNb' => $urgence->getTrackingNb() ?? '',
            'postNb' => $urgence->getPostNb() ?? '',
            'actions' => $this->templating->render('urgence/datatableUrgenceRow.html.twig', [
                'urgence' => $urgence
            ])
        ];
    }

    public function updateUrgence(Urgence $urgence, $data): Urgence {
        $dateStart = DateTime::createFromFormat('d/m/Y H:i', $data['dateStart'], new DateTimeZone("Europe/Paris"));
        $dateEnd = DateTime::createFromFormat('d/m/Y H:i', $data['dateEnd'], new DateTimeZone("Europe/Paris"));

        $utilisateurRepository = $this->entityManager->getRepository(Utilisateur::class);
        $fournisseurRepository = $this->entityManager->getRepository(Fournisseur::class);
        $transporteurRepository = $this->entityManager->getRepository(Transporteur::class);

        $buyer = isset($data['acheteur'])
            ? $utilisateurRepository->find($data['acheteur'])
            : null;

        $provider = isset($data['provider'])
            ? $fournisseurRepository->find($data['provider'])
            : null;

        $carrier = isset($data['carrier'])
            ? $transporteurRepository->find($data['carrier'])
            : null;

        $urgence
            ->setPostNb($data['postNb'])
            ->setBuyer($buyer)
            ->setProvider($provider)
            ->setCarrier($carrier)
            ->setTrackingNb($data['trackingNb'])
            ->setCommande($data['commande'])
            ->setDateStart($dateStart)
            ->setDateEnd($dateEnd);

        return $urgence;
    }
}
