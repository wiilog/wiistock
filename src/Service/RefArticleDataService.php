<?php
/**
 * Created by PhpStorm.
 * User: c.gazaniol
 * Date: 28/03/2019
 * Time: 16:34
 */

namespace App\Service;


use App\Entity\ReferenceArticle;
use App\Entity\ValeurChampsLibre;
use App\Repository\ChampsLibreRepository;
use App\Repository\FilterRepository;
use App\Repository\ReferenceArticleRepository;
use App\Repository\StatutRepository;
use App\Repository\TypeRepository;
use App\Repository\ValeurChampsLibreRepository;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Doctrine\ORM\EntityManagerInterface;



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
     * @var TypeRepository
     */
    private $typeRepository;

    /*
     * @var StatutRepository
     */
    private $statutRepository;

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
   
    private $em;


    public function __construct(TypeRepository  $typeRepository ,StatutRepository $statutRepository,EntityManagerInterface $em,ValeurChampsLibreRepository $valeurChampsLibreRepository, ReferenceArticleRepository $referenceArticleRepository, ChampsLibreRepository $champsLibreRepository, FilterRepository $filterRepository, \Twig_Environment $templating, TokenStorageInterface $tokenStorage)
    {
        $this->referenceArticleRepository = $referenceArticleRepository;
        $this->champsLibreRepository = $champsLibreRepository;
        $this->statutRepository = $statutRepository;
        $this->valeurChampsLibreRepository = $valeurChampsLibreRepository;
        $this->filterRepository = $filterRepository;
        $this->typeRepository = $typeRepository;
        $this->templating = $templating;
        $this->user = $tokenStorage->getToken()->getUser();
        $this->em = $em;
    }

    /**
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
                    'idRefArticle' => $refArticle->getId(),
                ]),
            ];
            $rows[] = array_merge($rowCL, $rowCF);
        }
        return $rows;
    }


    /**
     * @param ReferenceArticle $articleRef
     * @return array
     */
    public function getDataEditForRefArticle($articleRef)
    {
            $type = $articleRef->getType();
            if ($type) {
                $valeurChampLibre = $this->valeurChampsLibreRepository->getByRefArticleAndType($articleRef->getId(), $type->getId());
            } else {
                $valeurChampLibre = [];
            }
            // construction du tableau des articles fournisseurs
            $listArticlesFournisseur = [];
            $articlesFournisseurs = $articleRef->getArticlesFournisseur();
            $totalQuantity = 0;
            foreach ($articlesFournisseurs as $articleFournisseur) {
                $quantity = 0;
                foreach ($articleFournisseur->getArticles() as $article) {
                    $quantity += $article->getQuantite();
                }
                $totalQuantity += $quantity;

                $listArticlesFournisseur[] = [
                    'fournisseurRef' => $articleFournisseur->getFournisseur()->getCodeReference(),
                    'label' => $articleFournisseur->getLabel(),
                    'fournisseurName' => $articleFournisseur->getFournisseur()->getNom(),
                    'quantity' => $quantity
                ];
            }

        return $data = [
            'listArticlesFournisseur' => $listArticlesFournisseur,
            'totalQuantity' => $totalQuantity,
            'valeurChampLibre' => $valeurChampLibre
        ];
    }


    /**
     * @param ReferenceArticle $refArticle
     * @param string[] $data
     * @return array|bool
     * @throws \Twig_Error_Loader
     * @throws \Twig_Error_Runtime
     * @throws \Twig_Error_Syntax
     */
    public function editRefArticle($refArticle, $data)
    {
        if ($refArticle) {
            $entityManager = $this->em;
            if (isset($data['reference'])) $refArticle->setReference($data['reference']);
            if (isset($data['libelle'])) $refArticle->setLibelle($data['libelle']);
            if (isset($data['quantite'])) $refArticle->setQuantiteStock(intval($data['quantite']));
            if (isset($data['statut'])) {
                $statutLabel = ($data['statut'] == 1) ? ReferenceArticle::STATUT_ACTIF : ReferenceArticle::STATUT_INACTIF;
                $statut = $this->statutRepository->findOneByCategorieAndStatut(ReferenceArticle::CATEGORIE, $statutLabel);
                $refArticle->setStatut($statut);
            }
            if (isset($data['type'])) {
                $type = $this->typeRepository->find(intval($data['type']));
                if ($type) $refArticle->setType($type);
            }
            if (isset($data['type_quantite'])) $refArticle->setTypeQuantite($data['type_quantite']);

            $entityManager->flush();

            $champsLibreKey = array_keys($data);
            foreach ($champsLibreKey as $champ) {
                if (gettype($champ) === 'integer') {
                    $valeurChampLibre = $this->valeurChampsLibreRepository->getByRefArticleANDChampsLibre($refArticle->getId(), $champ);
                    // si la valeur n'existe pas, on la crée
                    if (!$valeurChampLibre) {
                        $valeurChampLibre = new ValeurChampsLibre();
                        $valeurChampLibre
                            ->addArticleReference($refArticle)
                            ->setChampLibre($this->champsLibreRepository->find($champ));
                        $entityManager->persist($valeurChampLibre);
                    }
                    $valeurChampLibre->setValeur($data[$champ]);
                    $entityManager->flush();
                }
            }

            $champsLibres = $this->champsLibreRepository->getLabelByCategory(ReferenceArticle::CATEGORIE);

            $rowCL = [];
            foreach ($champsLibres as $champLibre) {
                $valeur = $this->valeurChampsLibreRepository->getByRefArticleANDChampsLibre($refArticle->getId(), $champLibre['id']);
                $rowCL[$champLibre['label']] = ($valeur ? $valeur->getValeur() : "");
            }
            $rowDD = [
                "id" => $refArticle->getId(),
                "Libellé" => $refArticle->getLibelle(),
                "Référence" => $refArticle->getReference(),
                "Type" => ($refArticle->getType() ? $refArticle->getType()->getLabel() : ""),
                "Quantité" => $refArticle->getQuantiteStock(),
                'Actions' =>  $this->templating->render('reference_article/datatableReferenceArticleRow.html.twig', [
                    'idRefArticle' => $refArticle->getId(),
                ]),
            ];
            $rows = array_merge($rowCL, $rowDD);
            $response['id'] = $refArticle->getId();
            $response['edit'] = $rows;
        } else {
            $response = false; //TODO gérer retour erreur
        }
        return $response;
    }
}