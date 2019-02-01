<?php

namespace App\Controller;

use App\Entity\Demande;
use App\Form\DemandeType;
use App\Repository\DemandeRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

use App\Entity\ReferencesArticles;
use App\Form\ReferencesArticlesType;
use App\Repository\ReferencesArticlesRepository;
use App\Repository\StatutsRepository;

use App\Repository\ArticlesRepository;

use App\Entity\Emplacement;
use App\Form\EmplacementType;
use App\Repository\EmplacementRepository;

use App\Entity\Livraison;
use App\Form\LivraisonType;
use App\Repository\LivraisonRepository;

use Knp\Component\Pager\PaginatorInterface;


/**
 * @Route("/demande")
 */
class DemandeController extends AbstractController
{
    /**
     * @Route("/{history}/index", name="demande_index", methods={"GET"})
     */
    public function index(DemandeRepository $demandeRepository, PaginatorInterface $paginator, Request $request, StatutsRepository $statutsRepository, $history): Response
    {
        
        $demandeQuery = ($history === 'true') ? $demandeRepository->findAll() : $demandeRepository->findAllByUserAndStatut($this->getUser());

        $pagination = $paginator->paginate(
            $demandeQuery, /* On récupère la requête et on la pagine */
            $request->query->getInt('page', 1),
            10
        );

        if ($history === 'true') {
            return $this->render('demande/index.html.twig', [
                'demandes' => $pagination,
                'history' => 'false',
            ]);
        } else {
            return $this->render('demande/index.html.twig', [
                'demandes' => $pagination, 
                
            ]);
        }
    }

    /**
     * @Route("/creationDemande", name="creation_demande", methods="GET|POST")
     */
    public function creationDemande(LivraisonRepository $livraisonRepository, Request $request, StatutsRepository $statutsRepository, ReferencesArticlesRepository $referencesArticlesRepository, ArticlesRepository $articlesRepository, EmplacementRepository $emplacementRepository): Response 
    {   
        //la creation de demandes n'utilise pas le formulaire symfony, les utilisateur demandent des articles de Reference non pas les articles
        // on recupere la liste de article de reference et on créer une instance de demande
        $refArticles = $referencesArticlesRepository->findAll();
        $demande = new Demande();
        // si renvoie d'un réponse POST 
        if ( array_key_exists('piece', $_POST)) {
            // on recupere la destination des articles 
            $destination = $emplacementRepository->findOneBy(array('id' =>$_POST['direction']));
            // on 'remplie' la $demande avec les data les plus simple
            $demande->setDestination($destination);
            $statut = $statutsRepository->findById(14);
            $demande->setStatut($statut[0]);
            $demande->setUtilisateur($this->getUser());
            $date =  new \DateTime('now');
            $demande->setdate($date);
            $demande->setNumero("D-" . $date->format('YmdHis'));
            // on recupere un array sous la forme ['id de l'article de réference' => 'quantite de l'article de réference voulu', ....]
            $refArtQte = $_POST["piece"];
            //on créer un array qui recupere les key de valeur de nos id 
            $refArtKey = array_keys($refArtQte);
            foreach ($refArtKey as $key) {
                $articles = $articlesRepository->findByRefAndConfAndStock($key);
                for($n=0; $n<$refArtQte[$key]; $n++){
                    $demande->addArticle($articles[$n]);
                    //on modifie le statut de l'article et sa destination 
                    $statut = $statutsRepository->findById(13);
                    $articles[$n]->setStatut($statut[0]);
                    $articles[$n]->setDirection($destination);
                }
            }
            if (count($demande->getArticles()) > 0){
            $em = $this->getDoctrine()->getManager();
            $em->persist($demande);
            $em->flush();
            }
            return $this->redirectToRoute('demande_index', array('history'=>'false'));  
        }
         //calcul de la quantite des stocks par artciles de reference
         $refArticles = $referencesArticlesRepository->findAll();
         foreach ($refArticles as $refArticle) {
             // requete Count en SQL dédié
             $quantityRef = $articlesRepository->findCountByRefArticle($refArticle);
             $quantity = $quantityRef[0];
             $refArticle->setQuantity($quantity[1]);
         }
         $this->getDoctrine()->getManager()->flush();
        
        return $this->render('demande/creationDemande.html.twig', [
            'refArticles' => $referencesArticlesRepository->findRefArtByQte(),
            'emplacements' => $emplacementRepository->findEptBy(),
            // 'articles' => $articles,//varibles de test 
        ]);
    }

    /**
     * @Route("/new", name="demande_new", methods={"GET","POST"})
     */
    public function new(Request $request): Response
    {
        $demande = new Demande();
        $form = $this->createForm(DemandeType::class, $demande);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($demande);
            $entityManager->flush();

            return $this->redirectToRoute('demande_index');
        }

        return $this->render('demande/new.html.twig', [
            'demande' => $demande,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/show/{id}", name="demande_show", methods={"GET"})
     */
    public function show(Demande $demande): Response
    {
        return $this->render('demande/show.html.twig', [
            'demande' => $demande,
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
