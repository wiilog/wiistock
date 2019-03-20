<?php

namespace App\Controller;

use App\Entity\Article;
use App\Entity\Livraison;
use App\Form\LivraisonType;
use App\Repository\LivraisonRepository;
use App\Repository\PreparationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;


use App\Repository\DemandeRepository;
use App\Repository\StatutRepository;
use App\Repository\EmplacementRepository;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @Route("/livraison")
 */
class LivraisonController extends AbstractController
{
    /**
     * @var EmplacementRepository
     */
    private $emplacementRepository;

    /**
     * @var PreparationRepository
     */
    private $preparationRepository;

    /**
     * @var DemandeRepository
     */
    private $demandeRepository;

    /**
     * @var LivraisonRepository
     */
    private $livraisonRepository;

    /**
     * @var StatutRepository
     */
    private $statutRepository;


    public function __construct(PreparationRepository $preparationRepository, EmplacementRepository $emplacementRepository, DemandeRepository $demandeRepository, LivraisonRepository $livraisonRepository, StatutRepository $statutRepository)
    {
        $this->emplacementRepository = $emplacementRepository;
        $this->demandeRepository = $demandeRepository;
        $this->livraisonRepository = $livraisonRepository;
        $this->statutRepository = $statutRepository;
        $this->preparationRepository = $preparationRepository;
    }

    /**
    *  @Route("creation/{id}", name="createLivraison", methods={"GET","POST"} )
    */
    public function creationLivraison($id): Response
    {
        $preparation = $this->preparationRepository->find($id);
        if ($preparation->getLivraisons() == null) {
            $statut = $this->statutRepository->findOneByCategorieAndStatut(Livraison::CATEGORIE, Livraison::STATUT_A_TRAITER);
            $livraison = new Livraison();
            $date = new \DateTime('now');
            $livraison
            ->setDate($date)
            ->setNumero('L-' . $date->format('YmdHis'))
            ->setStatut($statut)
            ->setUtilisateur($this->getUser());
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($livraison);
            $preparation->addLivraison($livraison);
            $entityManager->flush();
            return $this->redirectToRoute('livraison_show', [
                'id' => $livraison->getId(),
                ]);
            }
            $livraison = $preparation->getLivraisons();
            dump( $livraison[0]);
            return  $this->redirectToRoute('livraison_show', [
                'id' => $livraison[0]->getId(),
                ]);
            
    }

    /**
     * @Route("/index", name="livraison_index", methods={"GET", "POST"})
     */
    public function index(Request $request): Response
    {
        return $this->render('livraison/index.html.twig');
    }

    /**
     * @Route("/finLivraison/{id}", name="livraison_fin", methods={"GET", "POST"})
     */
    public function finLivraison(Livraison $livraison, Request $request): Response
    {
        $livraison->setStatut($this->statutRepository->findOneByCategorieAndStatut(Livraison::CATEGORIE, Livraison::STATUT_LIVRE));
        $preparation = $livraison->getPreparation();
        $articles = $preparation->getArticle();
        foreach ($articles as $article) {
            $article->setStatut($this->statutRepository->findOneByCategorieAndStatut(Article::CATEGORIE, Article::STATUT_INACTIF));
        }
        $this->getDoctrine()->getManager()->flush();
        return $this->render('livraison/index.html.twig');
    }

    /**
     * @Route("/api", name="livraison_api", options={"expose"=true}, methods={"GET", "POST"})
     */
    public function livraisonApi(Request $request): Response
    {
        if ($request->isXmlHttpRequest()) //Si la requête est de type Xml
            {
                $livraisons = $this->livraisonRepository->findAll();
                $rows = [];
                foreach ($livraisons as $livraison) {
                    $url['show'] = $this->generateUrl('livraison_show', ['id' => $livraison->getId()]);
                    $rows[] = [
                        'id' => ($livraison->getId() ? $livraison->getId() : ''),
                        'Numéro' => ($livraison->getNumero() ? $livraison->getNumero() : ''),
                        'Date' => ($livraison->getDate() ? $livraison->getDate()->format('d-m-Y') : ''),
                        'Statut' => ($livraison->getStatut() ? $livraison->getStatut()->getNom() : ''),
                        'Opérateur' => ($livraison->getUtilisateur() ? $livraison->getUtilisateur()->getUsername() : ''),
                        'Actions' => $this->renderView('livraison/datatableLivraisonRow.html.twig', ['url' => $url])
                    ];
                }

                $data['data'] = $rows;
                return new JsonResponse($data);
            }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/voir/{id}", name="livraison_show", methods={"GET","POST"}) 
     */
    public function show(Livraison $livraison): Response
    {
        return $this->render('livraison/show.html.twig', [
            'livraison' => $livraison,
            'preparation'=> $this->preparationRepository->find($livraison->getPreparation()->getId())
        ]);
    }

    /**
     * @Route("/{id}", name="livraison_delete", methods={"DELETE"}) 
     */
    public function delete(Request $request, Livraison $livraison): Response
    {
        if ($this->isCsrfTokenValid('delete' . $livraison->getId(), $request->request->get('_token'))) {
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->remove($livraison);
            $entityManager->flush();
        }

        return $this->redirectToRoute('livraison_index');
    }
}
