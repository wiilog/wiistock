<?php


namespace App\Service;


use App\Entity\FiltreSup;
use App\Entity\Manutention;
use App\Entity\Utilisateur;
use App\Repository\ArticleRepository;
use App\Repository\FiltreSupRepository;
use App\Repository\ManutentionRepository;
use App\Repository\ReferenceArticleRepository;
use Twig\Environment as Twig_Environment;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class ManutentionService
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
     * @var ManutentionRepository
     */
    private $manutentionRepository;

    /**
     * @var FiltreSupRepository
     */
    private $filtreSupRepository;

    /**
     * @var Utilisateur
     */
    private $user;

    private $em;

    public function __construct(TokenStorageInterface $tokenStorage,
                                RouterInterface $router,
                                FiltreSupRepository $filtreSupRepository,
                                EntityManagerInterface $em,
                                Twig_Environment $templating,
                                ReferenceArticleRepository $referenceArticleRepository,
                                ArticleRepository $articleRepository,
                                ManutentionRepository $manutentionRepository)
    {
        $this->templating = $templating;
        $this->em = $em;
        $this->router = $router;
        $this->referenceArticleRepository = $referenceArticleRepository;
        $this->articleRepository = $articleRepository;
        $this->manutentionRepository = $manutentionRepository;
        $this->filtreSupRepository = $filtreSupRepository;
        $this->user = $tokenStorage->getToken()->getUser();
    }

    public function getDataForDatatable($params = null, $statusFilter = null)
    {
        if ($statusFilter) {
            $filters = [
                [
                    'field' => 'statut',
                    'value' => $statusFilter
                ]
            ];
        } else {
            $filters = $this->filtreSupRepository->getFieldAndValueByPageAndUser(FiltreSup::PAGE_MANUT, $this->user);
        }
        $queryResult = $this->manutentionRepository->findByParamAndFilters($params, $filters);

        $manutArray = $queryResult['data'];

        $rows = [];
        foreach ($manutArray as $manutention) {
            $rows[] = $this->dataRowManut($manutention);
        }

        return [
            'data' => $rows,
            'recordsTotal' => $queryResult['total'],
            'recordsFiltered' => $queryResult['count'],
        ];
    }

    public function dataRowManut(Manutention $manutention)
    {
        $row =
            [
                'id' => ($manutention->getId() ? $manutention->getId() : 'Non défini'),
                'Date demande' => ($manutention->getDate() ? $manutention->getDate()->format('d/m/Y') : null),
                'Demandeur' => ($manutention->getDemandeur() ? $manutention->getDemandeur()->getUserName() : null),
                'Libellé' => ($manutention->getlibelle() ? $manutention->getLibelle() : null),
                'Date souhaitée' => ($manutention->getDateAttendue() ? $manutention->getDateAttendue()->format('d/m/Y H:i') : null),
                'Date de réalisation' => ($manutention->getDateEnd() ? $manutention->getDateEnd()->format('d/m/Y H:i') : null),
                'Statut' => ($manutention->getStatut()->getNom() ? $manutention->getStatut()->getNom() : null),
                'Actions' => $this->templating->render('manutention/datatableManutentionRow.html.twig', [
                    'manut' => $manutention
                ]),
            ];
        return $row;
    }
}
