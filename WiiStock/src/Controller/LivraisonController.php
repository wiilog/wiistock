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

/**
 * @Route("/livraison")
 */
class LivraisonController extends AbstractController
{
    /**
     * @Route("/", name="livraison_index", methods={"GET", "POST"})
     */
    public function index(LivraisonRepository $livraisonRepository, DemandeRepository $demandeRepository ): Response
    {
        dump($_POST);
        if ($_POST) {
            $livraison = $livraisonRepository->findOneBy(['id'=>$_POST['id']]);
            $livraison->setStatut('livré');
            $demandes = $demandeRepository->findByLivrais($_POST['id']);
            foreach ($demandes as $demande) {
                $demande->setStatut('livré');
                $articles = $demande->getArticles();
                foreach ($articles as $article) {
                    $article->setStatu('livré');
                    $article->setPosition($article->getDirection());
                    $article->setDirection(NULL);
                    dump($article); //ATTENTION
                }
            }
            dump($demande);
            // $this->getDoctrine()->getManager()->flush();
            return $this->redirectToRoute('livraison_index');
        }
        return $this->render('livraison/index.html.twig', [
            'livraisons' => $livraisonRepository->findAll(),
        ]);
    }
    
    /**
     *  @Route("creation", name="livraison_creation", methods={"GET","POST"} )
     */
    public function creation(DemandeRepository $demandeRepository, EmplacementRepository $emplacementRepository, Request $request): Response
    {
        if ($_POST){
            $destinations = $demandeRepository->findEmplacementByStatut("préparation terminé");
            foreach ($destinations as $destination) {
                $livraison = new Livraison();
                $date =  new \DateTime('now');
                $livraison->setNumero('L-'. $date->format('YmdHis'));
                $livraison->setStatut("demande de livraison");
                $livraison->setDestination($emplacementRepository->findOneBy(['id' => $destination['id']]));
                $demandes = $demandeRepository->findByDestiAndStatut($emplacementRepository->findOneBy(['id' => $destination['id']]), 'préparation terminé');
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
            return $this->redirectToRoute('livraison_index');
        }
        return $this->render('livraison/creation.html.twig',array(
            'demandes'=>$demandeRepository->findDmdByStatut('préparation terminé'),

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
