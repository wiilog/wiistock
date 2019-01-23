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

/**
 * @Route("/preparation")
 */
class PreparationController extends AbstractController
{
    /**
     * @Route("/{history}/index", name="preparation_index", methods="GET|POST")
     */
    public function index($history, PreparationRepository $preparationRepository, DemandeRepository $demandeRepository, ReferencesArticlesRepository $referencesArticlesRepository, EmplacementRepository $emplacementRepository): Response
    {   
        // validation de fin de prparation
        // verification de l existance de la variable 
        if (array_key_exists('fin', $_POST)) {
        // on recupere l id en post 
           $preparation = $preparationRepository->findOneBy(["id"=>$_POST["fin"]]);
        //on modifie les statuts de la preparation et des demande liées
           $preparation->setStatut('fin');
           $demandes = $demandeRepository->findByPrepa($preparation);
           foreach ($demandes as $demande) {
               $demande->setStatut("préparation terminé");
           }
           $this->getDoctrine()->getManager()->flush();
           return $this->redirectToRoute('preparation_index', array('history'=> 'false'));
        }
        if ($history === 'true') {
            return $this->render('preparation/index.html.twig', array(
                'preparations'=>$preparationRepository->findAll(),
                'history' => 'false',
        ));   
        }
        return $this->render('preparation/index.html.twig', array(
            'preparations'=>$preparationRepository->findByNoStatut('fin'),
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
                $demande->setStatut('préparation commande');
                $articles = $demande->getArticles();
                foreach ($articles as $article) {
                    $article->setStatu('préparation');
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
     * @Route("/{id}", name="preparation_show", methods="GET")
     */
    public function show(Preparation $preparation): Response
    {
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

        return $this->redirectToRoute('preparation_index');
    }
}
