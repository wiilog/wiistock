<?php

namespace App\Controller;

use App\Entity\Livraison;
use App\Form\LivraisonType;
use App\Repository\LivraisonRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

use App\Entity\Demande;
use App\Form\DemandeType;
use App\Repository\DemandeRepository;

use App\Entity\Emplacement;
use App\Form\EmplacementType;
use App\Repository\EmplacementRepository;
use Knp\Component\Pager\PaginatorInterface;

/**
 * @Route("/livraison")
 */
class LivraisonController extends AbstractController
{
    /**
     * @Route("/{history}/index", name="livraison_index", methods={"GET", "POST"})
     */
    public function index($history, LivraisonRepository $livraisonRepository, PaginatorInterface $paginator, DemandeRepository $demandeRepository, Request $request): Response
    {
        // modification des statut lorsque la livraison est terminé 
        if ($_POST) {
            $livraison = $livraisonRepository->findOneBy(['id'=>$_POST['id']]);
            $livraison->setStatut('livré');
            $demandes = $demandeRepository->findByLivrais($_POST['id']);
            foreach ($demandes as $demande) {
                $demande->setStatut('livré');
                $articles = $demande->getArticles();
                foreach ($articles as $article) {
                    $article->setStatu('destokage');
                    if($article->getDirection()){
                        $article->setPosition($article->getDirection());
                        $article->setDirection(NULL);
                    }
                }
            }
            $this->getDoctrine()->getManager()->flush();
            return $this->redirectToRoute('livraison_index', ['history' => 'false']);
        }
        if($history === 'true'){
            return $this->render('livraison/index.html.twig', [
                'livraisons' => $paginator->paginate($livraisonRepository->findAll(), $request->query->getInt('page', 1), 10),
                'history' => 'true',
            ]);    
        }
        $statut = 'livré'; 
        return $this->render('livraison/index.html.twig', [
            'livraisons' => $paginator->paginate($livraisonRepository->findByNoStatut($statut), $request->query->getInt('page', 1),10)
        ]);
    }
    
    /**
     *  @Route("creation", name="livraison_creation", methods={"GET","POST"} )
     */
    public function creation(DemandeRepository $demandeRepository, EmplacementRepository $emplacementRepository, Request $request): Response
    {
         // recuperation des destination ID distincte des demandes selon le statut "preparation terminé"
         $destinationsId = $demandeRepository->findEmplacementByStatut("préparation terminé");
        // generation automatique des livraison selon leur lieux de destination
        if ($_POST){
            foreach ($destinationsId as $destinationId) {
                // création des livraisons selon les differentes destinations unique 
                $livraison = new Livraison();
                // Systeme de numerotation identique aux dmendes et aux preparations => L-20190114154
                $date =  new \DateTime('now');
                $livraison->setNumero('L-'. $date->format('YmdHis'));
                $livraison->setStatut("demande de livraison");
                $livraison->setDestination($emplacementRepository->findOneBy(['id' => $destinationId['id']]));
                // recuperation des demande selon leurs destination et si elles sont terminées
                $demandes = $demandeRepository->findByDestiAndStatut($emplacementRepository->findOneBy(['id' => $destinationId['id']]), 'préparation terminé');
                // liaison avec la livraison et changement du statut
                foreach ($demandes as $demande) {
                    $demande->setStatut('en cours de livraison');
                    $demande->setLivraison($livraison);
                    $articles = $demande->getArticles();
                    foreach ($articles as $article) {
                        $article->setStatu('en livraison');
                    }
                }
                $entityManager = $this->getDoctrine()->getManager(); 
                $entityManager->persist($livraison);               
           }
            $entityManager->flush();
            return $this->redirectToRoute('livraison_index', array('history'=>'false'));
        }
        return $this->render('livraison/creation.html.twig',array(
            'demandes'=>$demandeRepository->findDmdByStatut('préparation terminé'),
            'destinations'=>$demandeRepository->findEmplacementByStatut("préparation terminé"),
        ));
    }

    /**
     * @Route("/new", name="livraison_new", methods={"GET","POST"})
     */
    public function new(Request $request): Response
    {
        $livraison = new Livraison();
        $form = $this->createForm(LivraisonType::class, $livraison);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($livraison);
            $entityManager->flush();

            return $this->redirectToRoute('livraison_index');
        }

        return $this->render('livraison/new.html.twig', [
            'livraison' => $livraison,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}", name="livraison_show", methods={"GET"})
     */
    public function show(Livraison $livraison): Response
    {
        return $this->render('livraison/show.html.twig', [
            'livraison' => $livraison,
        ]);
    }

    /**
     * @Route("/{id}/edit", name="livraison_edit", methods={"GET","POST"})
     */
    public function edit(Request $request, Livraison $livraison): Response
    {
        $form = $this->createForm(LivraisonType::class, $livraison);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->getDoctrine()->getManager()->flush();

            return $this->redirectToRoute('livraison_index', [
                'id' => $livraison->getId(),
            ]);
        }

        return $this->render('livraison/edit.html.twig', [
            'livraison' => $livraison,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}", name="livraison_delete", methods={"DELETE"})
     */
    public function delete(Request $request, Livraison $livraison): Response
    {
        if ($this->isCsrfTokenValid('delete'.$livraison->getId(), $request->request->get('_token'))) {
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->remove($livraison);
            $entityManager->flush();
        }

        return $this->redirectToRoute('livraison_index');
    }
}
