<?php


namespace App\Service;

use App\Entity\Article;
use App\Entity\ArticleFournisseur;
use App\Entity\CategorieCL;
use App\Entity\CategoryType;
use App\Entity\Collecte;
use App\Entity\FiltreSup;
use App\Entity\Fournisseur;
use App\Entity\ReferenceArticle;
use App\Entity\Statut;
use App\Entity\Utilisateur;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Twig\Environment as Twig_Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

class DemandeCollecteService
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
    private $stringService;
    private $freeFieldService;
    private $articleFournisseurService;
    private $articleDataService;
    private $mouvementStockService;

    public function __construct(TokenStorageInterface $tokenStorage,
                                RouterInterface $router,
                                StringService $stringService,
                                FreeFieldService $champLibreService,
                                ArticleFournisseurService $articleFournisseurService,
                                ArticleDataService $articleDataService,
                                EntityManagerInterface $entityManager,
                                MouvementStockService $mouvementStockService,
                                Twig_Environment $templating)
    {
        $this->templating = $templating;
        $this->entityManager = $entityManager;
        $this->stringService = $stringService;
        $this->freeFieldService = $champLibreService;
        $this->articleFournisseurService = $articleFournisseurService;
        $this->articleDataService = $articleDataService;
        $this->router = $router;
        $this->user = $tokenStorage->getToken()->getUser();
        $this->mouvementStockService = $mouvementStockService;
    }

    /**
     * @param null $params
     * @param null $statusFilter
     * @return array
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
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
            $filtreSupRepository = $this->entityManager->getRepository(FiltreSup::class);
    		$filters = $filtreSupRepository->getFieldAndValueByPageAndUser(FiltreSup::PAGE_DEM_COLLECTE, $this->user);
		}

        $collecteRepository = $this->entityManager->getRepository(Collecte::class);

        $queryResult = $collecteRepository->findByParamsAndFilters($params, $filters);

        $collecteArray = $queryResult['data'];

        $rows = [];
        foreach ($collecteArray as $collecte) {
            $rows[] = $this->dataRowCollecte($collecte);
        }

        return [
            'data' => $rows,
            'recordsTotal' => $queryResult['total'],
            'recordsFiltered' => $queryResult['count'],
        ];
    }

    /**
     * @param Collecte $collecte
     * @return array
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function dataRowCollecte($collecte)
    {
        $url = $this->router->generate('collecte_show', ['id' => $collecte->getId()]);
        $row =
            [
                'id' => $collecte->getId() ?? '',
                'Création' => $collecte->getDate() ? $collecte->getDate()->format('d/m/Y') : '',
                'Validation' => $collecte->getValidationDate() ? $collecte->getValidationDate()->format('d/m/Y') : '',
                'Demandeur' => $collecte->getDemandeur() ? $collecte->getDemandeur()->getUserName() : '',
				'Objet' => $collecte->getObjet() ?? '',
				'Numéro' => $collecte->getNumero() ?? '',
                'Statut' => $collecte->getStatut()->getNom() ?? '',
                'Type' => $collecte->getType() ? $collecte->getType()->getLabel() : '',
                'Actions' => $this->templating->render('collecte/datatableCollecteRow.html.twig', [
                    'url' => $url,
                ]),
            ];
        return $row;
    }


    public function createHeaderDetailsConfig(Collecte $collecte): array {
        $requester = $collecte->getDemandeur();
        $status = $collecte->getStatut();
        $date = $collecte->getDate();
        $validationDate = $collecte->getValidationDate();
        $pointCollecte = $collecte->getPointCollecte();
        $object = $collecte->getObjet();
        $type = $collecte->getType();
        $comment = $collecte->getCommentaire();

        $freeFieldArray = $this->freeFieldService->getFilledFreeFieldArray(
            $this->entityManager,
            $collecte,
            CategorieCL::DEMANDE_COLLECTE,
            CategoryType::DEMANDE_COLLECTE
        );

        return array_merge(
            [
                [ 'label' => 'Statut', 'value' => $status ? $this->stringService->mbUcfirst($status->getNom()) : '' ],
                [ 'label' => 'Demandeur', 'value' => $requester ? $requester->getUsername() : '' ],
                [ 'label' => 'Date de la demande', 'value' => $date ? $date->format('d/m/Y') : '' ],
                [ 'label' => 'Date de validation', 'value' => $validationDate ? $validationDate->format('d/m/Y H:i') : '' ],
                [ 'label' => 'Destination', 'value' => $collecte->isStock() ? 'Mise en stock' : 'Destruction' ],
                [ 'label' => 'Objet', 'value' => $object ],
                [ 'label' => 'Point de collecte', 'value' => $pointCollecte ? $pointCollecte->getLabel() : '' ],
                [ 'label' => 'Type', 'value' => $type ? $type->getLabel() : '' ]
            ],
            $freeFieldArray,
            [
                [
                    'label' => 'Commentaire',
                    'value' => $comment ?: '',
                    'isRaw' => true,
                    'colClass' => 'col-sm-6 col-12',
                    'isScrollable' => true,
                    'isNeededNotEmpty' => true
                ]
            ]
        );
    }

    /**
     * @param array $data
     * @param ReferenceArticle $referenceArticle
     * @param Collecte $collecte
     * @return Article
     * @throws Exception
     */
    public function persistArticleInDemand(array $data,
                                           ReferenceArticle $referenceArticle,
                                           Collecte $collecte): Article {
        $statutRepository = $this->entityManager->getRepository(Statut::class);
        $fournisseurRepository = $this->entityManager->getRepository(Fournisseur::class);
        $articleFournisseurRepository = $this->entityManager->getRepository(ArticleFournisseur::class);

        $article = new Article();
        $statut = $statutRepository->findOneByCategorieNameAndStatutCode(Article::CATEGORIE, Article::STATUT_INACTIF);
        $date = new DateTime('now', new \DateTimeZone('Europe/Paris'));
        $ref = $date->format('YmdHis');

        $index = $articleFournisseurRepository->countByRefArticle($referenceArticle);
        $articleFournisseur = $this->articleFournisseurService->findSimilarArticleFournisseur($referenceArticle);

        if (!isset($articleFournisseur)) {
            $fournisseurTemp = $fournisseurRepository->findOneByCodeReference('A_DETERMINER');
            if (!$fournisseurTemp) {
                $fournisseurTemp = new Fournisseur();
                $fournisseurTemp
                    ->setCodeReference('A_DETERMINER')
                    ->setNom('A DETERMINER');
                $this->entityManager->persist($fournisseurTemp);
            }

            $articleFournisseur = $this->articleFournisseurService->createArticleFournisseur([
                'label' => 'A déterminer - ' . $index,
                'article-reference' => $referenceArticle,
                'reference' => $referenceArticle->getReference(),
                'fournisseur' => $fournisseurTemp
            ], true);
        }

        $this->entityManager->persist($articleFournisseur);
        $article
            ->setLabel($referenceArticle->getLibelle() . '-' . $index)
            ->setConform(true)
            ->setStatut($statut)
            ->setReference($ref . '-' . $index)
            ->setQuantite(max($data['quantity-to-pick'], 0)) // protection contre quantités négatives
            ->setEmplacement($collecte->getPointCollecte())
            ->setArticleFournisseur($articleFournisseur)
            ->setType($referenceArticle->getType())
            ->setBarCode($this->articleDataService->generateBarCode());
        $this->entityManager->persist($article);
        $collecte->addArticle($article);
        return $article;
    }

    public function serialiseExportRow(Collecte $collect,
                                       array $freeFieldsConfig,
                                       FreeFieldService $freeFieldService,
                                       callable $getSpecificColumn) {

        $collecteData = $collect->serialize();

        $freeFieldsData = [];
        foreach ($freeFieldsConfig['freeFieldIds'] as $freeFieldId) {
            $freeFieldsData[] = $freeFieldService->serializeValue([
                'typage' => $freeFieldsConfig['freeFieldsIdToTyping'][$freeFieldId],
                'valeur' => $collecteData['freeFields'][$freeFieldId] ?? ""
            ]);
        }

        unset($collecteData['freeFields']);

        return array_merge(
            array_values($collecteData),
            $getSpecificColumn(),
            $freeFieldsData
        );
    }
}
