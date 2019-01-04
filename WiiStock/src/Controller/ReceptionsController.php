<?php

namespace App\Controller;

use App\Entity\Receptions;
use App\Form\ReceptionsType;
use App\Repository\ReceptionsRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

use App\Entity\Articles;
use App\Form\ArticlesType;
use App\Repository\ArticlesRepository;

use App\Entity\Emplacement;
use App\Form\EmplacementType;
use App\Repository\EmplacementRepository;

/**
 * @Route("/receptions")
 */
class ReceptionsController extends AbstractController
{
    /**
     * @Route("/", name="receptions_index", methods="GET")
     */
    public function index(ReceptionsRepository $receptionsRepository): Response
    {
        //filtrage par la date du jour et le statut, requete SQL dédié
        $date = (new \DateTime('now'));
        return $this->render('receptions/index.html.twig', [
            'receptions' => $receptionsRepository->findByDateOrStatut($date),
            'date' => $date,
            ]);
    }

    /**
     * @Route("/new", name="receptions_new", methods="GET|POST")
     */
    public function new(Request $request): Response
    {
        $reception = new Receptions();
        $form = $this->createForm(ReceptionsType::class, $reception);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $reception-> setStatut('en cours de reception');
            $reception->setDate(new \DateTime('now'));
            $em = $this->getDoctrine()->getManager();
            $em->persist($reception);
            $em->flush();
            return $this->redirectToRoute('receptions_index');
        }
        return $this->render('receptions/new.html.twig', [
            'reception' => $reception,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}", name="receptions_show", methods="GET")
     */
    public function show(Receptions $reception): Response
    {
        return $this->render('receptions/show.html.twig', ['reception' => $reception]);
    }

    /**
     * @Route("/{id}/edit", name="receptions_edit", methods="GET|POST")
     */
    public function edit(Request $request, Receptions $reception): Response
    {
        $form = $this->createForm(ReceptionsType::class, $reception);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $this->getDoctrine()->getManager()->flush();
            return $this->redirectToRoute('receptions_index');
        }
        return $this->render('receptions/edit.html.twig', [
            'reception' => $reception,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}", name="receptions_delete", methods="DELETE")
     */
    public function delete(Request $request, Receptions $reception): Response
    {
        if ($this->isCsrfTokenValid('delete'.$reception->getId(), $request->request->get('_token'))) {
            $em = $this->getDoctrine()->getManager();
            $em->remove($reception);
            $em->flush();
        }
        return $this->redirectToRoute('receptions_index');
    }

    /**
     * @Route("/article/{id}/{k}", name="reception_ajout_article", methods="GET|POST")
     */
    public function ajoutArticle(Request $request, Receptions $reception, ArticlesRepository $articlesRepository,  EmplacementRepository $emplacementRepository, $id, $k): Response
    {
        //findByReception requete SQl dédié 
        $article = new Articles();
        $form = $this->createForm(ArticlesType::class, $article);
        $form->handleRequest($request);
        //création des articles en relation avec la reception
        if ($form->isSubmitted() && $form->isValid() && $k == false) {
            $article->setStatu('en cours de reception');
            $article->setReception($reception);
            $em = $this->getDoctrine()->getManager();
            $em->persist($article);
            $em->flush();
            return $this->redirectToRoute('reception_ajout_article', array('id'=> $id, 'k'=>0));
        }
        
        //fin de reception/mise en stock des articles
        // k sert à vérifier et identifier la fin de la reception, en suite on modifie les "setStatut" des variables 
        if ($k){
            $articles =  $articlesRepository->findByReception($id);
            foreach ($articles as $article) {
                //vérifie si l'article est bien encore en reception
                if ($article->getStatu() === 'en cours de reception'){
                $article->setStatu(' demande de mise en stock');
                }
            }
            $reception->setStatut('terminer');
            $this->getDoctrine()->getManager()->flush();
            return $this->redirectToRoute('receptions_index');
        }

        return $this->render("receptions/ajoutArticle.html.twig", array(
            'reception' => $reception,
            'emplacement' => $emplacementRepository->findAll(),
            'formView' => $form->createView(),
            'id'=> $id,    
        ));
    }

}
