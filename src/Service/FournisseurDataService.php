<?php

namespace App\Service;

use App\Entity\Fournisseur;

use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
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

    private $em;

    public function __construct(RouterInterface $router,
                                EntityManagerInterface $em,
                                Twig_Environment $templating,
                                TokenStorageInterface $tokenStorage)
    {
        $this->templating = $templating;
        $this->user = $tokenStorage->getToken()->getUser();
        $this->em = $em;
        $this->router = $router;
    }


    public function getDataForDatatable(EntityManagerInterface $entityManager, $params = null)
    {
        $fournisseurRepository = $entityManager->getRepository(Fournisseur::class);

        $data = $this->getFournisseurDataByParams($params);
        $data['recordsTotal'] = $data['recordsFiltered'] = (int)$fournisseurRepository->countAll();
        return $data;
    }

    /**
     * @param EntityManagerInterface $entityManager
     * @param null $params
     * @return array
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function getFournisseurDataByParams(EntityManagerInterface $entityManager, $params = null)
    {
        $fournisseurRepository = $entityManager->getRepository(Fournisseur::class);
        $fournisseurs = $fournisseurRepository->findByParams($params);

        $rows = [];
        foreach ($fournisseurs as $fournisseur) {
            $rows[] = $this->dataRowFournisseur($fournisseur);
        }
        return ['data' => $rows];
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


