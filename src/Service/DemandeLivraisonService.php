<?php


namespace App\Service;


use App\Repository\ArticleRepository;
use App\Repository\ReferenceArticleRepository;
use App\Repository\DemandeRepository;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\RouterInterface;

use Symfony\Component\Routing\Annotation\Route;

class DemandeLivraisonService
{
    /**
     * @var \Twig_Environment
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
     * @var DemandeRepository
     */
    private $demandeRepository;

    private $em;

    public function __construct(RouterInterface $router, EntityManagerInterface $em, \Twig_Environment $templating, ReferenceArticleRepository $referenceArticleRepository, ArticleRepository $articleRepository, DemandeRepository $demandeRepository)
    {
        $this->templating = $templating;
        $this->em = $em;
        $this->router = $router;
        $this->referenceArticleRepository = $referenceArticleRepository;
        $this->articleRepository = $articleRepository;
        $this->demandeRepository = $demandeRepository;
    }

    public function getDataForDatatable($params = null)
    {
        $queryResult = $this->demandeRepository->findByFilter($params);

        $demandeArray = $queryResult['data'];

        $rows = [];
        foreach ($demandeArray as $demande) {
            $rows[] = $this->dataRowDemande($demande);
        }

        return [
            'data' => $rows,
            'recordsTotal' => $queryResult['total'],
            'recordsFiltered' => $queryResult['count'],
        ];
    }

    public function dataRowDemande($demande)
    {
        $idDemande = $demande->getId();
        $url = $this->router->generate('demande_show', ['id' => $idDemande]);
        $row =
            [
                'Date' => ($demande->getDate() ? $demande->getDate()->format('d/m/Y') : ''),
                'Demandeur' => ($demande->getUtilisateur()->getUsername() ? $demande->getUtilisateur()->getUsername() : ''),
                'Numéro' => ($demande->getNumero() ? $demande->getNumero() : ''),
                'Statut' => ($demande->getStatut()->getNom() ? $demande->getStatut()->getNom() : ''),
                'Type' => ($demande->getType() ? $demande->getType()->getLabel() : ''),
                'Actions' => $this->templating->render('demande/datatableDemandeRow.html.twig',
                    [
                        'idDemande' => $idDemande,
                        'url' => $url,
                    ]
                ),
            ];
        return $row;
    }
}