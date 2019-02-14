<?php

namespace App\Controller;

use App\Entity\Demande;
use App\Form\DemandeType;
use App\Repository\DemandeRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

use App\Entity\Articles;
use App\Entity\ReferencesArticles;
use App\Form\ReferencesArticlesType;
use App\Repository\ReferencesArticlesRepository;
use App\Repository\FournisseursRepository;
use App\Repository\StatutsRepository;

use App\Repository\ArticlesRepository;

use App\Entity\Emplacement;
use App\Entity\Preparation;
use App\Form\EmplacementType;
use App\Repository\EmplacementRepository;
use App\Repository\UtilisateursRepository;

use App\Entity\Livraison;
use App\Form\LivraisonType;
use App\Repository\LivraisonRepository;

use Knp\Component\Pager\PaginatorInterface;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;


/**
 * @Route("/demande")
 */
class DemandeController extends AbstractController
{
    /**
     * @Route("/preparation/{id}", name="preparationFromDemande")
     */
    public function creationPreparationDepuisDemande(Demande $demande, StatutsRepository $statutsRepository): Response
    {  
        if($demande->getPreparation() == null)
        {
            dump("heeey");
            // Creation d'une nouvelle preparation basée sur une selection de demandes
            $preparation = new Preparation();

            $date = new \DateTime('now');
            $preparation->setNumero('P-' . $date->format('YmdHis'));
            $preparation->setDate($date);
            $preparation->setUtilisateur($demande->getUtilisateur());

            $statut = $statutsRepository->findById(11); /* Statut : nouvelle préparation */
            $preparation->setStatut($statut[0]);

            $demande->setPreparation($preparation);
            
            $statut = $statutsRepository->findById(15); /* Statut : Demande de préparation */
            $demande->setStatut($statut[0]);

            $em = $this->getDoctrine()->getManager();
            $em->persist($preparation);
            $em->flush();

            return $this->render('preparation/show.html.twig', ['preparation' => $demande->getPreparation()]);
        }
        return $this->render('preparation/show.html.twig', ['preparation' => $demande->getPreparation()]);
    }

    /**
     * @Route("/ajoutArticle/{id}", name="ajoutArticle", methods="GET|POST")
     */
    public function ajoutRefArticle(Demande $demande, FournisseursRepository $fournisseursRepository, ReferencesArticlesRepository $refArticlesRepository, Request $request, StatutsRepository $statutsRepository): Response 
    {
        if(!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true))
        {
            if(count($data) >= 3)
            {
                $em = $this->getDoctrine()->getEntityManager();

                $json = [
                    "reference" => $data[0]["reference"],
                    "quantite" => $data[2]["quantite"],
                ];

                $demande->addLigneArticle($json);
                $em->persist($demande);
                $em->flush();

                return new JsonResponse($json);
            }
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/modifDemande/{id}", name="modifDemande", methods="GET|POST")
     */
    public function modifDemande(Demande $demande, UtilisateursRepository $utilisateursRepository, Request $request, StatutsRepository $statutsRepository): Response 
    {
        if(!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true))
        {
            if(count($data) >= 3)
            {

                $em = $this->getDoctrine()->getEntityManager();

                dump($data);
                $utilisateur = $utilisateursRepository->findById(intval($data[0]["demandeur"]));
                $statut = $statutsRepository->findById($data[2]["statut"]);

                $demande->setUtilisateur($utilisateur[0]);
                $demande->setDateAttendu(new \Datetime($data[1]["date-attendu"]));
                $demande->setStatut($statut[0]);

                $em->persist($demande);
                $em->flush();

                $data = json_encode($data);
                return new JsonResponse($data);
            }
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/creationDemande", name="creation_demande", methods="GET|POST")
     */
    public function creationDemande(LivraisonRepository $livraisonRepository, Request $request, UtilisateursRepository $utilisateursRepository, StatutsRepository $statutsRepository, ReferencesArticlesRepository $referencesArticlesRepository, ArticlesRepository $articlesRepository, EmplacementRepository $emplacementRepository): Response 
    {   

        if(!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true))
        {
            $em = $this->getDoctrine()->getManager();
            $demandeur = $data[0];
            $demande = new Demande();
            $statut = $statutsRepository->findById(14);
            $demande->setStatut($statut[0]);
            $utilisateur = $utilisateursRepository->findOneById($demandeur["demandeur"]);
            $demande->setUtilisateur($utilisateur);
            $date =  new \DateTime('now');
            $demande->setdate($date);
            $demande->setNumero("D-" . $date->format('YmdHis')); // On recupere un array sous la forme ['id de l'article de réference' => 'quantite de l'article de réference voulu', ....]
            $em->persist($demande);
            $em->flush();
            
            /* if (count($data) >= 2) 
            {
                $dataKeys = [];

                foreach($data as $key)
                {
                    if(!array_key_exists("direction", $key))
                    {
                        $dataKeys += $key;
                    }
                }

                $IdKey = array_keys($dataKeys);
                $i = 0;

                foreach($IdKey as $key)
                {
                    
                    if($dataKeys[$key] <= 0)
                    {
                        unset($dataKeys[$key]);
                        unset($IdKey[$i]);
                    }
                    $i++;
                }

                dump($IdKey);
                dump($dataKeys);

                if(count($dataKeys) !== 0)
                {
                    $demande = new Demande();
                    $destination = $emplacementRepository->findOneBy(array('id' => $data[0]['direction'])); // On recupere la destination des articles

                    $demande->setDestination($destination); // On 'remplie' la $demande avec les data les plus simple
                    $statut = $statutsRepository->findById(14);

                    $demande->setStatut($statut[0]);
                    $demande->setUtilisateur($this->getUser());

                    $date =  new \DateTime('now');
                    $demande->setdate($date);
                    $demande->setNumero("D-" . $date->format('YmdHis')); // On recupere un array sous la forme ['id de l'article de réference' => 'quantite de l'article de réference voulu', ....]
                
                    $em = $this->getDoctrine()->getManager();

                    foreach ($IdKey as $id)
                    {
                        $json = [
                            "reference" => $id,
                            "quantite" => $dataKeys[$id],
                        ];
                        $demande->addLigneArticle($json); // On modifie le statut de l'article et sa destination
                        $refArticle = $referencesArticlesRepository->findOneById($id);
                        $quantiteInitial = $refArticle->getQuantiteReservee();
                        $refArticle->setQuantiteReservee($quantiteInitial + $dataKeys[$id]);
                        $em->persist($refArticle);
                    }

                    $em->persist($demande);
                    $em->flush();
                    
                    return new JsonResponse($data);
                }
            } */
            return new JsonResponse($data);
        }
        throw new NotFoundHttpException("404");
    }


    /**
     * @Route("/{history}/index", name="demande_index", methods={"GET"})
     */
    public function index(DemandeRepository $demandeRepository, UtilisateursRepository $utilisateursRepository, EmplacementRepository $emplacementRepository, ReferencesArticlesRepository $referencesArticlesRepository, PaginatorInterface $paginator, Request $request, ArticlesRepository $articlesRepository, $history): Response
    {
        
        $demandeQuery = ($history === 'true') ? $demandeRepository->findAll() : $demandeRepository->findAllByUserAndStatut($this->getUser());

        $pagination = $paginator->paginate(
            $demandeQuery, /* On récupère la requête et on la pagine */
            $request->query->getInt('page', 1),
            10
        );

        $RefArticle = $referencesArticlesRepository->findRefArtByQte();
        dump($RefArticle);
        $em = $this->getDoctrine()->getManager();

        foreach($RefArticle as $referenceArticle)
        {   
            $Articles = $articlesRepository->findByRefArticle($referenceArticle['id']);
            $quantiteArticle = 0;

            foreach($Articles as $article)
            {
                if($article->getStatut()->getId() === 3){
                    $quantiteArticle += intval($article->getQuantite());
                    dump($quantiteArticle);
                }
            }

            $referenceArticle = $referencesArticlesRepository->findOneById($referenceArticle['id']);
            $referenceArticle->setQuantiteStock($quantiteArticle);
            $quantiteArticleReservee = $referenceArticle->getQuantiteReservee();
            $referenceArticle->setQuantiteDisponible($quantiteArticle - $quantiteArticleReservee);
            $em->persist($referenceArticle);
            $em->flush();
        }

        if ($history === 'true') 
        {
            return $this->render('demande/index.html.twig', [
                'demandes' => $pagination,
                'history' => 'false',
                'refArticles' => $RefArticle,
                'utilisateurs' => $utilisateursRepository->findAll(),
                'emplacements' => $emplacementRepository->findEptBy(),
            ]);
        }
        else
        {
            return $this->render('demande/index.html.twig', [
                'demandes' => $pagination,
                'refArticles' => $RefArticle,
                'utilisateurs' => $utilisateursRepository->findAll(),
                'emplacements' => $emplacementRepository->findEptBy(),
            ]);
        }
    }


    /**
     * @Route("/show/{id}", name="demande_show", methods={"GET"})
     */
    public function show(Demande $demande, StatutsRepository $statutsRepository, EmplacementRepository $emplacementRepository, ReferencesArticlesRepository $referencesArticlesRepository, UtilisateursRepository $utilisateursRepository): Response
    {
        $ligneArticle = $demande->getLigneArticle();
        $lignes = [];
        
        foreach ($ligneArticle as $ligne) 
        {
            $refArticle = $referencesArticlesRepository->findById($ligne["reference"]);
            $data = [
                "reference" => $ligne["reference"],
                "quantite" =>$ligne["quantite"],
                "libelle" => $refArticle[0]->getLibelle(), 

            ];
            array_push($lignes, $data);
        }

        return $this->render('demande/show.html.twig', [
            'demande' => $demande,
            'lignesArticles' => $lignes,
            'utilisateurs' => $utilisateursRepository->findAll(),
            'statuts' => $statutsRepository->findAll(),
            'references' => $referencesArticlesRepository->findAll()
        ]);
    }

    /**
     * @Route("/{id}/edit", name="demande_edit", methods={"GET","POST"})
     */
    public function edit(Request $request, Demande $demande): Response
    {
        $form = $this->createForm(DemandeType::class, $demande);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->getDoctrine()->getManager()->flush();

            return $this->redirectToRoute('demande_index', [
                'id' => $demande->getId(),
            ]);
        }

        return $this->render('demande/edit.html.twig', [
            'demande' => $demande,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}", name="demande_delete", methods={"DELETE"})
     */
    public function delete(Request $request, Demande $demande): Response
    {
        if ($this->isCsrfTokenValid('delete'.$demande->getId(), $request->request->get('_token'))) {
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->remove($demande);
            $entityManager->flush();
        }

        return $this->redirectToRoute('demande_index');
    }

    


}
