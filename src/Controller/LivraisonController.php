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
use App\Repository\StatutsRepository;

use App\Entity\Emplacement;
use App\Form\EmplacementType;
use App\Repository\EmplacementRepository;
use Knp\Component\Pager\PaginatorInterface;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @Route("/livraison")
 */
class LivraisonController extends AbstractController
{

    /**
     *  @Route("creation/{id}", name="createLivraison", methods={"GET","POST"} )
     */
    public function creationLivraison($id, DemandeRepository $demandeRepository, StatutsRepository $statutsRepository, EmplacementRepository $emplacementRepository, Request $request) : Response
    {
        $demande = $demandeRepository->find($id);
        if ($demande->getLivraison() == null) {
            $emplacement = $emplacementRepository->findById($demande->getDestination()->getId());
            $statut = $statutsRepository->findById(22);
            $livraison = new Livraison();
            $date = new \DateTime('now');
            $livraison->setDate($date);
            $livraison->setNumero('L-' . $date->format('YmdHis'));
            $livraison->setStatut($statut[0]);
            $livraison->setDestination($emplacement[0]);
            $livraison->setUtilisateur($this->getUser());
            $demande->setLivraison($livraison);
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($livraison);
            $entityManager->flush();
        }
        return $this->redirectToRoute('livraison_show', [
            'id' => $demande->getLivraison()->getId(),
        ]);
    }

    /**
     * @Route("/index", name="livraison_index", methods={"GET", "POST"})
     */
    public function index(LivraisonRepository $livraisonRepository, StatutsRepository $statutsRepository, PaginatorInterface $paginator, DemandeRepository $demandeRepository, Request $request) : Response
    {
        return $this->render('livraison/index.html.twig');
    }

      /**
     * @Route("/finLivraison/{id}", name="livraison_fin", methods={"GET", "POST"})
     */
    public function finLivraison($id, LivraisonRepository $livraisonRepository, StatutsRepository $statutsRepository, PaginatorInterface $paginator, DemandeRepository $demandeRepository, Request $request) : Response
    {
        $livraison = $livraisonRepository->find($id);
        $livraison->setStatut($statutsRepository->find(26));
        $demande = $livraison->getDemande();
        dump($demande);
        $demande[0]->setStatut($statutsRepository->find(9));
        $articles = $demande[0]->getArticles();
        foreach ($articles as $article ) {
            $article->setStatut($statutsRepository->find(4));
        }
        $this->getDoctrine()->getManager()->flush();
        return $this->render('livraison/index.html.twig');
    }
    
    /**
     * @Route("/api", name="livraison_api", methods={"GET", "POST"})
     */
    public function livraisonApi(LivraisonRepository $livraisonRepository, StatutsRepository $statutsRepository, PaginatorInterface $paginator, DemandeRepository $demandeRepository, Request $request) : Response
    {
        if ($request->isXmlHttpRequest()) //Si la requête est de type Xml
        {
            $livraison = $livraisonRepository->findAll();
            
            $rows = [];
            foreach ($livraison as $livraison) {
                $url = $this->generateUrl('livraison_show', ['id' => $livraison->getId()] );
                $row = [
                    'id' => ($livraison->getId() ? $livraison->getId() : "null"),
                    'Numero' => ($livraison->getNumero() ? $livraison->getNumero() : "null"),
                    'Date' => ($livraison->getDate() ? $livraison->getDate()->format('Y-m-d') : 'null'),
                    'Statut' => ($livraison->getStatut() ? $livraison->getStatut()->getNom() : "null"),
                    'Operateur' => ($livraison->getUtilisateur() ? $livraison->getUtilisateur()->getUsername() : "null"),
                    'Actions' => "<a href='". $url ."' class='btn btn-xs btn-default command-edit '><i class='fas fa-eye fa-2x'></i></a>",
                ];
                array_push($rows, $row);
            }
            $data['data'] = $rows;
            return new JsonResponse($data);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/new", name="livraison_new", methods={"GET","POST"}) A SUPPRIMER
     */
    public function new(Request $request) : Response
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
     * @Route("/{id}", name="livraison_show", methods={"GET"}) UTILE 
     */
    public function show(Livraison $livraison) : Response
    {
        return $this->render('livraison/show.html.twig', [
            'livraison' => $livraison,
        ]);
    }

    /**
     * @Route("/{id}/edit", name="livraison_edit", methods={"GET","POST"})
     */
    public function edit(Request $request, Livraison $livraison) : Response
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
    public function delete(Request $request, Livraison $livraison) : Response
    {
        if ($this->isCsrfTokenValid('delete' . $livraison->getId(), $request->request->get('_token'))) {
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->remove($livraison);
            $entityManager->flush();
        }

        return $this->redirectToRoute('livraison_index');
    }
}
