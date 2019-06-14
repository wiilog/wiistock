<?php
/**
 * Created by PhpStorm.
 * User: c.gazaniol
 * Date: 28/03/2019
 * Time: 16:34
 */

namespace App\Service;


use App\Entity\Action;
use App\Entity\CategoryType;
use App\Entity\Menu;
use App\Entity\ReferenceArticle;
use App\Entity\ValeurChampsLibre;
use App\Entity\CategorieCL;
use App\Entity\ArticleFournisseur;

use App\Repository\ArticleFournisseurRepository;
use App\Repository\ChampsLibreRepository;
use App\Repository\FilterRepository;
use App\Repository\ReferenceArticleRepository;
use App\Repository\StatutRepository;
use App\Repository\TypeRepository;
use App\Repository\ValeurChampsLibreRepository;
use App\Repository\CategorieCLRepository;
use App\Repository\FournisseurRepository;
use App\Repository\EmplacementRepository;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Article;

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

    /**
     * @var StatutRepository
     */
    private $statutRepository;

    /**
     * @var FournisseurRepository
     */
    private $fournisseurRepository;

    /**
     * @var ArticleFournisseurRepository
     */
    private $articleFournisseurRepository;

    /**
     * @var ValeurChampsLibreRepository
     */
    private $valeurChampsLibreRepository;

    /**
     * @var CategorieCLRepository
     */
    private $categorieCLRepository;

    /**
     * @var FilterRepository
     */
    private $filterRepository;
    /**
     * @var EmplacementRepository
     */
    private $emplacementRepository;

    /**
     * @var \Twig_Environment
     */
    private $templating;

    /**
     * @var UserService
     */
    private $userService;

    /**
     * @var object|string
     */
    private $user;

    private $em;

    /**
     * @var RouterInterface
     */
    private $router;


    public function __construct(EmplacementRepository $emplacementRepository, RouterInterface $router, UserService $userService, ArticleFournisseurRepository $articleFournisseurRepository,FournisseurRepository $fournisseurRepository, CategorieCLRepository $categorieCLRepository, TypeRepository  $typeRepository, StatutRepository $statutRepository, EntityManagerInterface $em, ValeurChampsLibreRepository $valeurChampsLibreRepository, ReferenceArticleRepository $referenceArticleRepository, ChampsLibreRepository $champsLibreRepository, FilterRepository $filterRepository, \Twig_Environment $templating, TokenStorageInterface $tokenStorage)
    {
        $this->emplacementRepository = $emplacementRepository;
        $this->fournisseurRepository = $fournisseurRepository;
        $this->referenceArticleRepository = $referenceArticleRepository;
        $this->champsLibreRepository = $champsLibreRepository;
        $this->statutRepository = $statutRepository;
        $this->valeurChampsLibreRepository = $valeurChampsLibreRepository;
        $this->filterRepository = $filterRepository;
        $this->typeRepository = $typeRepository;
        $this->categorieCLRepository = $categorieCLRepository;
        $this->templating = $templating;
        $this->articleFournisseurRepository = $articleFournisseurRepository;
        $this->user = $tokenStorage->getToken()->getUser();
        $this->em = $em;
        $this->userService = $userService;
        $this->router = $router;
    }

    public function getDataForDatatable($params = null)
    {
        $data = $this->getRefArticleDataByParams($params);
        $data['recordsTotal'] = (int)$this->referenceArticleRepository->countAll();
        return $data;
    }

    /**
     * @param null $params
     * @return array
     */
    public function getRefArticleDataByParams($params = null)
    {
        $userId = $this->user->getId();
        $filters = $this->filterRepository->getFieldsAndValuesByUser($userId);
        $queryResult = $this->referenceArticleRepository->findByFiltersAndParams($filters, $params, $this->user);
       
        $refs = $queryResult['data'];
        $count = $queryResult['count'];

        $rows = [];
        foreach ($refs as $refArticle) {
            $rows[] = $this->dataRowRefArticle($refArticle);
        }
        return ['data' => $rows, 'recordsFiltered' => $count];
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
        $statut = $this->statutRepository->findOneByCategorieAndStatut(Article::CATEGORIE, Article::STATUT_INACTIF);
        foreach ($articlesFournisseurs as $articleFournisseur) {
            $quantity = 0;
            foreach ($articleFournisseur->getArticles() as $article) {
                if ($article->getStatut() !== $statut) $quantity += $article->getQuantite();
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

    public function getViewEditRefArticle($refArticle, $isADemand = false)
    {
        $data = $this->getDataEditForRefArticle($refArticle);
        $articlesFournisseur = $this->articleFournisseurRepository->findByRefArticle($refArticle->getId());
        $type = $this->typeRepository->getIdAndLabelByCategoryLabel(CategoryType::ARTICLES_ET_REF_CEA);
        $categorieCL = $this->categorieCLRepository->findOneByLabel(CategorieCL::REFERENCE_CEA);
        $typeChampLibre =  [];
        foreach ($type as $label) {
            $champsLibresComplet = $this->champsLibreRepository->findByLabelTypeAndCategorieCL($label['label'], $categorieCL);
            $champsLibres = [];
            //création array edit pour vue
            foreach ($champsLibresComplet as $champLibre) {
                $valeurChampRefArticle = $this->valeurChampsLibreRepository->findOneByRefArticleANDChampsLibre($refArticle->getId(), $champLibre);
                $champsLibres[] = [
                    'id' => $champLibre->getId(),
                    'label' => $champLibre->getLabel(),
                    'typage' => $champLibre->getTypage(),
                    'elements' => ($champLibre->getElements() ? $champLibre->getElements() : ''),
                    'defaultValue' => $champLibre->getDefaultValue(),
                    'valeurChampLibre' => $valeurChampRefArticle,
                ];
            }
            $typeChampLibre[] = [
                'typeLabel' =>  $label['label'],
                'typeId' => $label['id'],
                'champsLibres' => $champsLibres,
            ];
        }
        //reponse Vue + data 

        
        $view =  $this->templating->render('reference_article/modalEditRefArticleContent.html.twig', [
            'articleRef' => $refArticle,
            'statut' => ($refArticle->getStatut()->getNom() == ReferenceArticle::STATUT_ACTIF),
            'valeurChampsLibre' => isset($data['valeurChampLibre']) ? $data['valeurChampLibre'] : null,
            'typeChampsLibres' => $typeChampLibre,
            'articlesFournisseur' => ($data['listArticlesFournisseur']),
            'totalQuantity' => $data['totalQuantity'],
            'articles' => $articlesFournisseur,
            'isADemand' => $isADemand
        ]);
        return $view;
    }

    /**
     * @param ReferenceArticle $refArticle
     * @param string[] $data
     * @return array|bool
     */
    public function editRefArticle($refArticle, $data)
    {
        if (!$this->userService->hasRightFunction(Menu::STOCK, Action::CREATE_EDIT)) {
            return new RedirectResponse($this->router->generate('access_denied'));
        }

        //vérification des champsLibres obligatoires
        $requiredEdit = true;
        $type =  $this->typeRepository->find(intval($data['type']));
        $emplacement =  $this->emplacementRepository->find(intval($data['emplacement']));
        $CLRequired = $this->champsLibreRepository->getByTypeAndRequiredEdit($type);
        foreach ($CLRequired as $CL) {
            if (array_key_exists($CL['id'], $data) and $data[$CL['id']] === "") {
                $requiredEdit = false;
            }
        }

        if ($requiredEdit) {
                //modification champsFixes
            $entityManager = $this->em;
                if (isset($data['reference'])) $refArticle->setReference($data['reference']);
                if (isset($data['frl'])) {
                    foreach ($data['frl'] as $frl) {
                        $fournisseurId = explode(';', $frl)[0];
                        $ref = explode(';', $frl)[1];
                        $label = explode(';', $frl)[2];
                        $fournisseur = $this->fournisseurRepository->find(intval($fournisseurId));
                        $articleFournisseur = new ArticleFournisseur();
                        $articleFournisseur
                            ->setReferenceArticle($refArticle)
                            ->setFournisseur($fournisseur)
                            ->setReference($ref)
                            ->setLabel($label);
                        $entityManager->persist($articleFournisseur);
                    }
                }
                if (isset($data['emplacement'])) $refArticle->setEmplacement($emplacement);
                if (isset($data['libelle'])) $refArticle->setLibelle($data['libelle']);
                if (isset($data['commentaire'])) $refArticle->setCommentaire($data['commentaire']);
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
            //modification ou création des champsLibres
            $champsLibreKey = array_keys($data);
            foreach ($champsLibreKey as $champ) {
                if (gettype($champ) === 'integer') {
                    $champLibre = $this->champsLibreRepository->find($champ);
                        $valeurChampLibre = $this->valeurChampsLibreRepository->findOneByRefArticleANDChampsLibre($refArticle->getId(), $champLibre);
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
            //recup de la row pour insert datatable
            $rows = $this->dataRowRefArticle($refArticle);
            $response['id'] = $refArticle->getId();
            $response['edit'] = $rows;
        } else {
            $response = false; //TODO gérer retour erreur
        }
        return $response;
    }

    public function dataRowRefArticle($refArticle)
    {
        $categorieCL = $this->categorieCLRepository->findOneByLabel(CategorieCL::REFERENCE_CEA);
        $category = CategoryType::ARTICLES_ET_REF_CEA;
        $champsLibres = $this->champsLibreRepository->getByCategoryTypeAndCategoryCL($category, $categorieCL);
        $rowCL = [];
        foreach ($champsLibres as $champLibre) {
            $champ = $this->champsLibreRepository->find($champLibre['id']);
            $valeur = $this->valeurChampsLibreRepository->findOneByRefArticleANDChampsLibre($refArticle->getId(), $champ);
            $rowCL[$champLibre['label']] = ($valeur ? $valeur->getValeur() : "");
        }
        $totalQuantity = 0;
        $statut = $this->statutRepository->findOneByCategorieAndStatut(Article::CATEGORIE, Article::STATUT_INACTIF);
        if ($refArticle->getTypeQuantite() === 'article') {
            foreach ($refArticle->getArticlesFournisseur() as $articleFournisseur) {
                $quantity = 0;
                foreach ($articleFournisseur->getArticles() as $article) {
                    if ($article->getStatut() !== $statut) $quantity += $article->getQuantite();
                }
                $totalQuantity += $quantity;
            }
        }
        $quantity = ($refArticle->getTypeQuantite() === 'reference') ? $refArticle->getQuantiteStock() : $totalQuantity;
        $rowCF = [
            "id" => $refArticle->getId(),
            "Libellé" => $refArticle->getLibelle(),
            "Référence" => $refArticle->getReference(),
            "Type" => ($refArticle->getType() ? $refArticle->getType()->getLabel() : ""),
            "Emplacement" => ($refArticle->getEmplacement() ? $refArticle->getEmplacement()->getLabel() : ""),
            "Quantité" => $quantity,
            "Commentaire" => ($refArticle->getCommentaire() ? $refArticle->getCommentaire() : ""),
            "Actions" => $this->templating->render('reference_article/datatableReferenceArticleRow.html.twig', [
                'idRefArticle' => $refArticle->getId(),
            ]),
        ];
        $rows = array_merge($rowCL, $rowCF);


        return $rows;
        
    }
    
}
