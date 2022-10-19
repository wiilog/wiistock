<?php

namespace App\Service;

use App\Entity\FiltreSup;
use App\Entity\Livraison;

use App\Entity\PreparationOrder\PreparationOrderArticleLine;
use App\Entity\PreparationOrder\PreparationOrderReferenceLine;
use App\Helper\FormatHelper;
use Doctrine\ORM\EntityManagerInterface;

use Symfony\Component\Security\Core\Security;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Service\Attribute\Required;
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

    private $security;

    private $entityManager;

    #[Required]
    public FormatService $formatService;

    public function __construct(RouterInterface $router,
                                EntityManagerInterface $entityManager,
                                Twig_Environment $templating,
                                Security $security) {
        $this->templating = $templating;
        $this->entityManager = $entityManager;
        $this->router = $router;
        $this->security = $security;
    }

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

    public function dataRowLivraison(Livraison $livraison)
    {
		$url['show'] = $this->router->generate('livraison_show', ['id' => $livraison->getId()]);

		$preparation = $livraison->getPreparation() ?? null;
		$lastMessage = $preparation ? $livraison->getPreparation()->getLastMessage() : null;
        $sensorCode = ($lastMessage && $lastMessage->getSensor() && $lastMessage->getSensor()->getAvailableSensorWrapper()) ? $lastMessage->getSensor()->getAvailableSensorWrapper()->getName() : null;
        $hasPairing = $preparation && !$preparation->getPairings()->isEmpty();

		$demande = $livraison->getDemande();

        return [
            'id' => $livraison->getId() ?? '',
            'Numéro' => $livraison->getNumero() ?? '',
            'Date' => $livraison->getDate() ? $livraison->getDate()->format('d/m/Y') : '',
            'Statut' => $livraison->getStatut() ? $this->formatService->status($livraison->getStatut()) : '',
            'Opérateur' => $livraison->getUtilisateur() ? $livraison->getUtilisateur()->getUsername() : '',
            'Type' => $demande ? $this->formatService->type($demande->getType()) : '',
            'Actions' => $this->templating->render('livraison/datatableLivraisonRow.html.twig', ['url' => $url,
                'titleLogo' => !$livraison->getPreparation()->getPairings()->isEmpty() ? 'pairing' : null
            ]),
            'pairing' => $this->templating->render('pairing-icon.html.twig', [
                'sensorCode' => $sensorCode,
                'hasPairing' => $hasPairing,
            ]),
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
                FormatHelper::status($livraison->getStatut()),
                FormatHelper::datetime($livraison->getDate()),
                FormatHelper::datetime($livraison->getDateFin()),
                FormatHelper::date($demande->getValidatedAt()),
                FormatHelper::deliveryRequester($demande),
                FormatHelper::user($livraison->getUtilisateur()),
                FormatHelper::type($demande->getType()),
                FormatHelper::html($demande->getCommentaire())
            ];

            /** @var PreparationOrderReferenceLine $referenceLine */
            foreach ($preparation->getReferenceLines() as $referenceLine) {
                if ($referenceLine->getPickedQuantity() > 0) {
                    $referenceArticle = $referenceLine->getReference();
                    $line = array_merge($dataLivraison, [
                        $referenceArticle->getReference() ?? '',
                        $referenceArticle->getLibelle() ?? '',
                        $demande->getDestination() ? $demande->getDestination()->getLabel() : '',
                        $referenceLine->getQuantityToPick() ?? 0,
                        $referenceArticle->getQuantiteStock() ?? 0,
                        $referenceArticle->getBarCode(),
                    ]);
                    $csvService->putLine($handle, $line);
                }
            }

            /** @var PreparationOrderArticleLine $articleLine */
            foreach ($preparation->getArticleLines() as $articleLine) {
                if ($articleLine->getPickedQuantity() > 0) {
                    $article = $articleLine->getArticle();
                    $articleFournisseur = $article->getArticleFournisseur();
                    $referenceArticle = $articleFournisseur ? $articleFournisseur->getReferenceArticle() : null;
                    $reference = $referenceArticle ? $referenceArticle->getReference() : '';

                    $line = array_merge($dataLivraison, [
                        $reference,
                        $article->getLabel() ?? '',
                        $demande->getDestination() ? $demande->getDestination()->getLabel() : '',
                        $articleLine->getQuantityToPick() ?? 0,
                        $article->getQuantite() ?? 0,
                        $article->getBarCode(),
                    ]);
                    $csvService->putLine($handle, $line);
                }
            }
        }
    }
}
