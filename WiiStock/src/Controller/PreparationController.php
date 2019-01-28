<?php

namespace App\Controller;

use App\Entity\Preparation;
use App\Form\PreparationType;
use App\Repository\PreparationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

use Symfony\Component\Form\Extension\Core\Type\TextType;

use App\Entity\ReferencesArticles;
use App\Form\ReferencesArticlesType;
use App\Repository\ReferencesArticlesRepository;

use App\Repository\ArticlesRepository;

use App\Entity\Emplacement;
use App\Form\EmplacementType;
use App\Repository\EmplacementRepository;

use App\Entity\Livraison;
use App\Form\LivraisonType;
use App\Repository\LivraisonRepository;

use App\Entity\Demande;
use App\Form\DemandeType;
use App\Repository\DemandeRepository;

use Doctrine\Common\Collections\ArrayCollection;
use Knp\Component\Pager\PaginatorInterface;

/**
 * @Route("/preparation")
 */
class PreparationController extends AbstractController
{
    /**
     * @Route("/{history}/index", name="preparation_index", methods="GET|POST")
     */
    public function index($history, PreparationRepository $preparationRepository, DemandeRepository $demandeRepository, ReferencesArticlesRepository $referencesArticlesRepository, EmplacementRepository $emplacementRepository, Request $request, PaginatorInterface $paginator): Response
    {   
        $préparationQuery = ($history === 'true') ? $preparationRepository->findAll() : $preparationRepository->findByNoStatut('fin');
        $pagination = $paginator->paginate(
            $préparationQuery, /* On récupère la requête et on la pagine */
            $request->query->getInt('page', 1),
            10
        );
        // validation de fin de prparation
        // verification de l existance de la variable 
        if (array_key_exists('en_cours', $_POST)) {
        // on recupere l id en post 
           $preparation = $preparationRepository->findOneBy(["id"=>$_POST["en_cours"]]);
        //on modifie les statuts de la preparation et des demandes liées
           $preparation->setStatut('en cours');
           $demandes = $demandeRepository->findByPrepa($preparation);
           foreach ($demandes as $demande) {
               $demande->setStatut("en cours de préparation");
           }
           $this->getDoctrine()->getManager()->flush();
           return $this->redirectToRoute('preparation_index', array('history'=> 'false'));
        }
        // permet d'affiché rapidement un historique ou les demandes en cours
        if ($history === 'true') {
            return $this->render('preparation/index.html.twig', array(
                'preparations'=> $pagination,
                'history' => 'false',
            ));   
        }
        return $this->render('preparation/index.html.twig', array(
            'preparations'=> $pagination,
        ));
    }

    /**
     * @Route("/new", name="preparation_new", methods="GET|POST")
     */
    public function new(Request $request, ArticlesRepository $articlesRepository): Response
    {
        $preparation = new Preparation();
        $form = $this->createForm(PreparationType::class, $preparation);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $preparation->setDate( new \DateTime('now'));
            $em = $this->getDoctrine()->getManager();
            $em->persist($preparation);
            $em->flush();

            return $this->redirectToRoute('preparation_index', array('history'=> 'false'));
        }

        return $this->render('preparation/new.html.twig', [
            'preparation' => $preparation,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/creationPreparation", name="preparation_creation", methods="GET|POST" )
     */
    public function creationPreparation(DemandeRepository $demandeRepository, PreparationRepository $preparationRepository, ReferencesArticlesRepository $referencesArticlesRepository, EmplacementRepository $emplacementRepository)
    {   
        // creation d'une nouvelle preparation  bassée sur une selection de demandes
        if ($_POST){
            $preparation = new Preparation;
            //declaration de la date pour remplir Date et Numero
            $date =  new \DateTime('now');
            $preparation->setNumero('P-'. $date->format('YmdHis'));
            $preparation->setDate($date);
            $preparation->setStatut('Nouvelle préparation');
            //plus de detail voir creation demande meme principe 
            $demandeKey = array_keys($_POST['preparation']);
            foreach ($demandeKey as $key ) {
                $demande= $demandeRepository->findOneBy(['id'=> $key]);
                $demande->setPreparation($preparation);
                $demande->setStatut('demande préparation');
                $articles = $demande->getArticles();
                foreach ($articles as $article) {
                    $article->setStatu('demande de sortie');
                    $article->setDirection($demande->getDestination());
                }
                $this->getDoctrine()->getManager();
            }
            $em = $this->getDoctrine()->getManager();
            $em->persist($preparation);
            $em->flush();
            return $this->redirectToRoute('preparation_index', array('history'=> 'false'));
        }
        return $this->render("preparation/creation.html.twig", array(
            "demandes" =>$demandeRepository->findDmdByStatut('commande demandé'), //A modifier 
        ));
    }

    /**
     * @Route("/{id}", name="preparation_show", methods="GET|POST")
     */
    public function show(Preparation $preparation, PreparationRepository $preparationRepository, DemandeRepository $demandeRepository, ArticlesRepository $articlesRepository): Response
    {
        // modelise l'action de prendre l'article dan sle stock pour constituer la preparation  
        if(array_key_exists('fin', $_POST)){
            $article = $articlesRepository->findById($_POST['fin']);
            $article[0]->setStatu('préparation');
            $this->getDoctrine()->getManager()->flush();
            // Meme principe que pour collecte_show =>comptage des articles selon un statut et une preparation si nul alors preparation fini 
            $demande = $demandeRepository->findById(array_keys($_POST['fin']));
            $finDemande = $articlesRepository->findCountByStatutAndDemande($demande);
            $fin = $finDemande[0];
            if($fin[1] === '0'){
                $demande[0]->setStatut('préparation terminé');   
            }
            $this->getDoctrine()->getManager()->flush();
        }

        //vérification de la fin de la praparation requete SQL => dédié
        $fin = $demandeRepository->findCountByStatutAndPrepa($preparation);
        $fin = $fin[0];
        if($fin[1] === '0'){
            $preparation->setStatut('fin');            
            $this->getDoctrine()->getManager()->flush();
        }
        
        return $this->render('preparation/show.html.twig', ['preparation' => $preparation]);
    }

    /**
     * @Route("/{id}/edit", name="preparation_edit", methods="GET|POST")
     */
    public function edit(Request $request, Preparation $preparation): Response
    {
        $form = $this->createForm(PreparationType::class, $preparation);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $this->getDoctrine()->getManager()->flush();
            return $this->redirectToRoute('preparation_edit', ['id' => $preparation->getId()]);
        }
        return $this->render('preparation/edit.html.twig', [
            'preparation' => $preparation,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}", name="preparation_delete", methods="DELETE")
     */
    public function delete(Request $request, Preparation $preparation): Response
    {
        if ($this->isCsrfTokenValid('delete'.$preparation->getId(), $request->request->get('_token'))) {
            $em = $this->getDoctrine()->getManager();
            $em->remove($preparation);
            $em->flush();
        }
        return $this->redirectToRoute('preparation_index', ['history'=> 'false']);
    }
}
