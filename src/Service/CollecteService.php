<?php


namespace App\Service;

use App\Repository\ArticleRepository;
use App\Repository\ReferenceArticleRepository;
use App\Repository\OrdreCollecteRepository;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\RouterInterface;

use Symfony\Component\Routing\Annotation\Route;

class CollecteService
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
     * @var OrdreCollecteRepository
     */
    private $ordreCollecteRepository;

    private $em;

    public function __construct(RouterInterface $router, EntityManagerInterface $em, \Twig_Environment $templating, ReferenceArticleRepository $referenceArticleRepository, ArticleRepository $articleRepository, OrdreCollecteRepository $ordreCollecteRepository)
    {
        $this->templating = $templating;
        $this->em = $em;
        $this->router = $router;
        $this->referenceArticleRepository = $referenceArticleRepository;
        $this->articleRepository = $articleRepository;
        $this->ordreCollecteRepository = $ordreCollecteRepository;
    }

    public function getDataForDatatable($params = null)
    {
        $queryResult = $this->ordreCollecteRepository->findByfilter($params);

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

    public function dataRowCollecte($collecte)
    {
        if ($this->ordreCollecteRepository->findOneByDemandeCollecte($collecte) == null) {
            $ordreCollecteDate = null;
        } else {
            $ordreCollecteDate = $this->ordreCollecteRepository->findOneByDemandeCollecte($collecte)->getDate()->format('d/m/Y H:i');
        }

        $url = $this->router->generate('collecte_show', ['id' => $collecte->getId()]);
        $row =
            [
                'id' => ($collecte->getId() ? $collecte->getId() : 'Non défini'),
                'Création' => ($collecte->getDate() ? $collecte->getDate()->format('d/m/Y') : null),
                'Validation' => $ordreCollecteDate,
                'Demandeur' => ($collecte->getDemandeur() ? $collecte->getDemandeur()->getUserName() : null),
                'Objet' => ($collecte->getObjet() ? $collecte->getObjet() : null),
                'Statut' => ($collecte->getStatut()->getNom() ? ucfirst($collecte->getStatut()->getNom()) : null),
                'Type' => ($collecte->getType() ? $collecte->getType()->getLabel() : ''),
                'Actions' => $this->templating->render('collecte/datatableCollecteRow.html.twig', [
                    'url' => $url,
                ]),
            ];
        return $row;
    }
}