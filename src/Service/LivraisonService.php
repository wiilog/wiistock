<?php

namespace App\Service;

use App\Entity\FiltreSup;
use App\Entity\IOT\Pairing;
use App\Entity\Livraison;

use App\Helper\FormatHelper;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\EntityManagerInterface;

use Symfony\Component\Security\Core\Security;
use Symfony\Component\Routing\RouterInterface;
use Twig\Error\LoaderError as Twig_Error_Loader;
use Twig\Error\RuntimeError as Twig_Error_Runtime;
use Twig\Error\SyntaxError as Twig_Error_Syntax;
use Twig\Environment as Twig_Environment;

class LivraisonService
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

    public function __construct(UserService $userService,
                                RouterInterface $router,
                                EntityManagerInterface $entityManager,
                                Twig_Environment $templating,
                                Security $security) {
        $this->templating = $templating;
        $this->entityManager = $entityManager;
        $this->router = $router;
        $this->userService = $userService;
        $this->security = $security;
    }

    /**
     * @param array|null $params
     * @param int|null $filterDemandId
     * @return array
     * @throws NonUniqueResultException
     * @throws Twig_Error_Loader
     * @throws Twig_Error_Runtime
     * @throws Twig_Error_Syntax
     */
    public function getDataForDatatable($params = null, $filterDemandId = null)
    {
        $filtreSupRepository = $this->entityManager->getRepository(FiltreSup::class);
        $livraisonRepository = $this->entityManager->getRepository(Livraison::class);

        if ($filterDemandId) {
            $filters = [
                [
                    'field' => FiltreSup::FIELD_DEMANDE,
                    'value' => $filterDemandId
                ]
            ];
        }
        else {
            $filters = $filtreSupRepository->getFieldAndValueByPageAndUser(FiltreSup::PAGE_ORDRE_LIVRAISON, $this->security->getUser());
        }
		$queryResult = $livraisonRepository->findByParamsAndFilters($params, $filters);

		$livraisons = $queryResult['data'];

		$rows = [];
		foreach ($livraisons as $livraison) {
			$rows[] = $this->dataRowLivraison($livraison);
		}
		return [
			'data' => $rows,
			'recordsFiltered' => $queryResult['count'],
			'recordsTotal' => $queryResult['total'],
		];
    }

    /**
     * @param Livraison $livraison
     * @return array
     * @throws Twig_Error_Loader
     * @throws Twig_Error_Runtime
     * @throws Twig_Error_Syntax
     */
    public function dataRowLivraison($livraison)
    {
		$url['show'] = $this->router->generate('livraison_show', ['id' => $livraison->getId()]);

		$demande = $livraison->getDemande();

        return [
			'id' => $livraison->getId() ?? '',
			'Numéro' => $livraison->getNumero() ?? '',
			'Date' => $livraison->getDate() ? $livraison->getDate()->format('d/m/Y') : '',
			'Statut' => $livraison->getStatut() ? $livraison->getStatut()->getNom() : '',
			'Opérateur' => $livraison->getUtilisateur() ? $livraison->getUtilisateur()->getUsername() : '',
			'Type' => $demande && $demande->getType() ? $demande->getType()->getLabel() : '',
			'Actions' => $this->templating->render('livraison/datatableLivraisonRow.html.twig', ['url' => $url,
            'titleLogo' => !$livraison
                ->getPreparation()
                ->getPairings()
                ->filter(fn(Pairing $pairing) => $pairing->isActive()
                )->isEmpty() ? 'pairing' : null
            ])
		];
    }

    public function putLivraisonLine($handle,
                                      CSVExportService $csvService,
                                      Livraison $livraison)
    {
        $demande = $livraison->getDemande();
        $preparation = $livraison->getPreparation();
        if (isset($demande) && isset($preparation)) {
            $dataLivraison = [
                $livraison->getNumero() ?? '',
                $livraison->getStatut() ? $livraison->getStatut()->getNom() : '',
                $livraison->getDate() ? $livraison->getDate()->format('d/m/Y H:i') : '',
                $livraison->getDateFin() ? $livraison->getDateFin()->format('d/m/Y H:i') : '',
                $demande->getValidationDate() ? FormatHelper::date($demande->getValidationDate()) : '',
                $demande->getUtilisateur() ? FormatHelper::user($demande->getUtilisateur()) : '',
                $livraison->getUtilisateur() ? $livraison->getUtilisateur()->getUsername() : '',
                $demande ? ($demande->getType() ? $demande->getType()->getLabel() : '') : '',
                $demande->getCommentaire() ? strip_tags($demande->getCommentaire()) : ''
            ];

            foreach ($preparation->getLigneArticlePreparations() as $ligneArticle) {
                if ($ligneArticle->getQuantitePrelevee() > 0) {
                    $referenceArticle = $ligneArticle->getReference();
                    $line = array_merge($dataLivraison, [
                        $referenceArticle->getReference() ?? '',
                        $referenceArticle->getLibelle() ?? '',
                        $demande->getDestination() ? $demande->getDestination()->getLabel() : '',
                        $ligneArticle->getQuantite() ?? 0,
                        $referenceArticle->getQuantiteStock() ?? 0,
                        $referenceArticle->getBarCode(),
                    ]);
                    $csvService->putLine($handle, $line);
                }
            }

            foreach ($preparation->getArticles() as $article) {
                if ($article->getQuantite() > 0) {
                    $articleFournisseur = $article->getArticleFournisseur();
                    $referenceArticle = $articleFournisseur ? $articleFournisseur->getReferenceArticle() : null;
                    $reference = $referenceArticle ? $referenceArticle->getReference() : '';

                    $line = array_merge($dataLivraison, [
                        $reference,
                        $article->getLabel() ?? '',
                        $demande->getDestination() ? $demande->getDestination()->getLabel() : '',
                        $article->getQuantiteAPrelever() ?? 0,
                        $article->getQuantite() ?? 0,
                        $article->getBarCode(),
                    ]);
                    $csvService->putLine($handle, $line);
                }
            }
        }
    }
}
