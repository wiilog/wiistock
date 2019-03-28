<?php
/**
 * Created by PhpStorm.
 * User: c.gazaniol
 * Date: 28/03/2019
 * Time: 16:34
 */

namespace App\Service;


use App\Entity\ReferenceArticle;
use App\Repository\ArticleFournisseurRepository;
use App\Repository\ChampsLibreRepository;
use App\Repository\FilterRepository;
use App\Repository\ReferenceArticleRepository;
use App\Repository\StatutRepository;
use App\Repository\TypeRepository;
use App\Repository\ValeurChampsLibreRepository;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class RefArticleDataService
{
    /**
     * @var ReferenceArticleRepository
     */
    private $referenceArticleRepository;

    /*
     * @var ChampsLibreRepository
     */
    private $champsLibreRepository;

    /**
     * @var ValeurChampsLibreRepository
     */
    private $valeurChampsLibreRepository;

    /**
     * @var FilterRepository
     */
    private $filterRepository;

    /**
     * @var \Twig_Environment
     */
    private $templating;

    /**
     * @var object|string
     */
    private $user;


    public function __construct(ValeurChampsLibreRepository $valeurChampsLibreRepository, ReferenceArticleRepository $referenceArticleRepository, ChampsLibreRepository $champsLibreRepository, FilterRepository $filterRepository, \Twig_Environment $templating, TokenStorageInterface $tokenStorage)
    {
        $this->referenceArticleRepository = $referenceArticleRepository;
        $this->champsLibreRepository = $champsLibreRepository;
        $this->valeurChampsLibreRepository = $valeurChampsLibreRepository;
        $this->filterRepository = $filterRepository;
        $this->templating = $templating;
        $this->user = $tokenStorage->getToken()->getUser();
    }

    /**
     * @param int $userId
     * @return array
     * @throws \Twig_Error_Loader
     * @throws \Twig_Error_Runtime
     * @throws \Twig_Error_Syntax
     */
    public function getRefArticleData()
    {
        $userId = $this->user->getId();
        $filters = $this->filterRepository->getFieldsAndValuesByUser($userId);
        $refs = $this->referenceArticleRepository->findByFilters($filters);

        $rows = [];
        foreach ($refs as $refArticle) {
            $champsLibres = $this->champsLibreRepository->getLabelByCategory(ReferenceArticle::CATEGORIE);
            $rowCL = [];

            foreach ($champsLibres as $champLibre) {
                $valeur = $this->valeurChampsLibreRepository->getByRefArticleANDChampsLibre($refArticle->getId(), $champLibre['id']);
                $rowCL[$champLibre['label']] = ($valeur ? $valeur->getValeur() : "");
            }

            $rowCF = [
                "id" => $refArticle->getId(),
                "Libellé" => $refArticle->getLibelle(),
                "Référence" => $refArticle->getReference(),
                "Type" => ($refArticle->getType() ? $refArticle->getType()->getLabel() : ""),
                "Quantité" => $refArticle->getQuantiteStock(),
                'Actions' => $this->templating->render('reference_article/datatableReferenceArticleRow.html.twig', [
                    'idRefArticle' => $refArticle->getId()
                ]),
            ];
            $rows[] = array_merge($rowCL, $rowCF);
        }
        return $rows;
    }
}