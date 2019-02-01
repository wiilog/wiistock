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
use App\Repository\StatutsRepository;

/**
 * @Route("/preparation")
 */
class PreparationController extends AbstractController
{
    /**
     * @Route("/{history}/index", name="preparation_index", methods="GET|POST")
     */
    public function index($history, PreparationRepository $preparationRepository, StatutsRepository $statutsRepository, DemandeRepository $demandeRepository, ReferencesArticlesRepository $referencesArticlesRepository, EmplacementRepository $emplacementRepository, Request $request, PaginatorInterface $paginator): Response
    {   
        $préparationQuery = ($history === 'true') ? $preparationRepository->findAll() : $preparationRepository->findByNoStatut('fin');
        $pagination = $paginator->paginate(
            $préparationQuery, /* On récupère la requête et on la pagine */
            $request->query->getInt('page', 1),
            10
        );
        // validation de fin de preparation
        // verification de l existance de la variable 
        if (array_key_exists('en_cours', $_POST)) {
        // on recupere l id en post 
            $preparation = $preparationRepository->findOneBy(["id"=>$_POST["en_cours"]]);
        //on modifie les statuts de la preparation et des demandes liées
            dump($preparation);

            /* On modifie le statut de la préparation à en cours de préparation */
            $statut = $statutsRepository->findById(12); /* Demande de préparation Catégorie : préparation */
            dump($statut);
            $preparation->setStatut($statut[0]);
            $demandes = $demandeRepository->findByPrepa($preparation);
            foreach ($demandes as $demande)
            {
               $statut = $statutsRepository->findById(30);  /* Demande de préparation Catégorie : Demandes */
               $demande->setStatut($statut[0]);
           }
           $this->getDoctrine()->getManager()->flush();
           return $this->redirectToRoute('preparation_index', array('history'=> 'false'));
        }
        // permet d'affiché rapidement un historique ou les demandes en cours
        if ($history === 'true') 
        {
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
    public function creationPreparation(DemandeRepository $demandeRepository, StatutsRepository $statutsRepository, PreparationRepository $preparationRepository, ReferencesArticlesRepository $referencesArticlesRepository, EmplacementRepository $emplacementRepository)
    {   
        // creation d'une nouvelle preparation basée sur une selection de demandes
        if ($_POST)
        {
            $preparation = new Preparation;
            //declaration de la date pour remplir Date et Numero
            $date =  new \DateTime('now');
            $preparation->setNumero('P-'. $date->format('YmdHis'));
            $preparation->setDate($date);
            $statut = $statutsRepository->findById(11); /* Statut : nouvelle préparation */
            $preparation->setStatut($statut[0]);
            //plus de detail voir creation demande meme principe 
            $demandeKey = array_keys($_POST['preparation']);

            foreach ($demandeKey as $key)
            {
                $demande = $demandeRepository->findOneBy(['id'=> $key]);
                $demande->setPreparation($preparation);
                $statut = $statutsRepository->findById(15); /* Statut : Demande de préparation */
                $demande->setStatut($statut[0]);
                $articles = $demande->getArticles();

                foreach ($articles as $article) 
                {
                    $statut = $statutsRepository->findById(13); /* Statut : Demande de sortie */
                    $article->setStatut($statut[0]);
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
            "demandes" =>$demandeRepository->findDmdByStatut(14), /* Nouvelle demande */ 
        ));
    }

    /**
     * @Route("/{id}", name="preparation_show", methods="GET|POST")
     */
    public function show(Preparation $preparation, StatutsRepository $statutsRepository, PreparationRepository $preparationRepository, DemandeRepository $demandeRepository, ArticlesRepository $articlesRepository): Response
    {
        // modelise l'action de prendre l'article dan sle stock pour constituer la preparation  
        if(array_key_exists('fin', $_POST))
        {
            $article = $articlesRepository->findById($_POST['fin']);
            $statut = $statutsRepository->findById(16); /*  Le statut passe en préparation */
            $article[0]->setStatut($statut[0]);
            $this->getDoctrine()->getManager()->flush();
            
            // Meme principe que pour collecte_show =>comptage des articles selon un statut et une preparation si nul alors preparation fini 
            $demande = $demandeRepository->findById(array_keys($_POST['fin']));
            $finDemande = $articlesRepository->findCountByStatutAndDemande($demande);
            $fin = $finDemande[0];

            dump($fin);

            if($fin[1] === '0')
            {
                $statut = $statutsRepository->findById(8);
                $demande[0]->setStatut($statut[0]); 
                $statut = $statutsRepository->findById(24);
                $preparation->setStatut($statut[0]);
            }
            $this->getDoctrine()->getManager()->flush();
        }

        //vérification de la fin de la preparation requete SQL => dédié
       /*  dump($preparation);
        $statut = $statutsRepository->findById(24) */; /* On cherche demande de sortie */
        /* $fin = $demandeRepository->findCountByStatutAndPrepa($statut, $preparation); */ /* On compte le nombre  */
/*         $fin = $fin[0];

        dump($fin);
        dump($fin[1]);

        if($fin[1] === '0')
        {
            $statut = $statutsRepository->findById(17); 
            $preparation->setStatut($statut[0]);
            $this->getDoctrine()->getManager()->flush();
        } */
        
        return $this->render('preparation/show.html.twig', ['preparation' => $preparation]);
    }

    /**
     * @Route("/{id}/edit", name="preparation_edit", methods="GET | POST")
     */
    public function edit(Request $request, StatutsRepository $statutsRepository, Preparation $preparation): Response
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
    public function delete(Request $request, StatutsRepository $statutsRepository, Preparation $preparation): Response
    {
        if ($this->isCsrfTokenValid('delete'.$preparation->getId(), $request->request->get('_token'))) {
            $em = $this->getDoctrine()->getManager();
            $em->remove($preparation);
            $em->flush();
        }
        return $this->redirectToRoute('preparation_index', ['history'=> 'false']);
    }
}
