<?php


namespace App\Service;


use App\Entity\FiltreSup;
use App\Entity\Urgence;
use App\Entity\Utilisateur;
use App\Repository\ArticleRepository;
use App\Repository\FiltreSupRepository;
use App\Repository\ReferenceArticleRepository;
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
     * @var ReferenceArticleRepository
     */
    private $referenceArticleRepository;

    /**
     * @var ArticleRepository
     */
    private $articleRepository;

    /**
     * @var UrgenceRepository
     */
    private $urgenceRepository;

    /**
     * @var FiltreSupRepository
     */
    private $filtreSupRepository;

    /**
     * @var Utilisateur
     */
    private $user;

    private $em;

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
                                FiltreSupRepository $filtreSupRepository,
                                EntityManagerInterface $em,
                                Twig_Environment $templating,
                                ReferenceArticleRepository $referenceArticleRepository,
                                ArticleRepository $articleRepository,
                                UrgenceRepository $urgenceRepository,
								SpecificService $specificService,
								Security $security)
    {
        $this->templating = $templating;
        $this->em = $em;
        $this->router = $router;
        $this->referenceArticleRepository = $referenceArticleRepository;
        $this->articleRepository = $articleRepository;
        $this->urgenceRepository = $urgenceRepository;
        $this->filtreSupRepository = $filtreSupRepository;
        $this->user = $tokenStorage->getToken()->getUser();
        $this->security = $security;
        $this->specificService = $specificService;
    }

    public function getDataForDatatable($params = null)
    {
		$filters = $this->filtreSupRepository->getFieldAndValueByPageAndUser(FiltreSup::PAGE_URGENCES, $this->security->getUser());

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
}
