<?php

namespace App\Service;

use App\Entity\Fournisseur;

use Symfony\Component\Routing\RouterInterface;
use Doctrine\ORM\EntityManagerInterface;
use Twig\Environment as Twig_Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

class FournisseurDataService
{

    /**
     * @var Twig_Environment
     */
    private $templating;
    /**
     * @var RouterInterface
     */
    private $router;

    private $entityManager;

    public function __construct(RouterInterface $router,
                                EntityManagerInterface $entityManager,
                                Twig_Environment $templating)
    {
        $this->templating = $templating;
        $this->entityManager = $entityManager;
        $this->router = $router;
    }

    /**
     * @param null $params
     * @return array
     */
    public function getFournisseurDataByParams($params = null)
    {
        $fournisseurRepository = $this->entityManager->getRepository(Fournisseur::class);
        $fournisseursData = $fournisseurRepository->getByParams($params);

        $fournisseursData['data'] = array_map(
            function($fournisseur) {
                return $this->dataRowFournisseur($fournisseur);
            },
            $fournisseursData['data']
        );

        return $fournisseursData;
    }

    /**
     * @param Fournisseur $fournisseur
     * @return array
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function dataRowFournisseur($fournisseur)
    {
        $fournisseurId = $fournisseur->getId();
        $url['edit'] = $this->router->generate('fournisseur_edit', ['id' => $fournisseurId]);
        $row = [
            "Nom" => $fournisseur->getNom(),
            "Code de référence" => $fournisseur->getCodeReference(),
            'Actions' => $this->templating->render('fournisseur/datatableFournisseurRow.html.twig', [
                'url' => $url,
                'fournisseurId' => $fournisseurId
            ]),
        ];
        return $row;
    }
}


