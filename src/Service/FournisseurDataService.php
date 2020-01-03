<?php
/**
 * Created by VisualStudioCode.
 * User: jv.Sicot
 * Date: 03/04/2019
 * Time: 15:09.
 */

namespace App\Service;

use App\Repository\FournisseurRepository;

use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Doctrine\ORM\EntityManagerInterface;
use Twig\Environment as Twig_Environment;

class FournisseurDataService
{

    /**
     * @var Twig_Environment
     */
    private $templating;

    /**
     * @var FournisseurRepository
     */
    private $fournisseurRepository;

    /**
     * @var RouterInterface
     */
    private $router;

    private $em;

    public function __construct(FournisseurRepository $fournisseurRepository,
                                RouterInterface $router,
                                EntityManagerInterface $em,
                                Twig_Environment $templating,
                                TokenStorageInterface $tokenStorage)
    {
        $this->templating = $templating;
        $this->user = $tokenStorage->getToken()->getUser();
        $this->em = $em;
        $this->router = $router;
        $this->fournisseurRepository = $fournisseurRepository;
    }


    public function getDataForDatatable($params = null)
    {
        $data = $this->getFournisseurDataByParams($params);
        $data['recordsTotal'] = (int)$this->fournisseurRepository->countAll();
        $data['recordsFiltered'] = (int)$this->fournisseurRepository->countAll();
        return $data;
    }

    /**
     * @param null $params
     * @return array
     * @throws \Twig_Error_Loader
     * @throws \Twig_Error_Runtime
     * @throws \Twig_Error_Syntax
     */
    public function getFournisseurDataByParams($params = null)
    {
        $fournisseurs = $this->fournisseurRepository->findByParams($params);

        $rows = [];
        foreach ($fournisseurs as $fournisseur) {
            $rows[] = $this->dataRowFournisseur($fournisseur);
        }
        return ['data' => $rows];
    }

    /**
     * @param Fournisseur $fournisseur
     * @return array
     * @throws \Twig_Error_Loader
     * @throws \Twig_Error_Runtime
     * @throws \Twig_Error_Syntax
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


