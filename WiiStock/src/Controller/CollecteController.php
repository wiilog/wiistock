<?php

namespace App\Controller;

use App\Entity\Collecte;
use App\Form\CollecteType;
use App\Repository\CollecteRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

use App\Entity\Articles;
use App\Form\ArticlesType;
use App\Repository\ArticlesRepository;

use App\Repository\EmplacementRepository;

use Knp\Component\Pager\PaginatorInterface;

/**
 * @Route("/collecte")
 */
class CollecteController extends AbstractController
{
    /**
     * @Route("/", name="collecte_index", methods={"GET", "POST"})
     */
    public function index(CollecteRepository $collecteRepository, PaginatorInterface $paginator, Request $request): Response
    {
        if (array_key_exists('fin', $_POST)){
            $collecte = $collecteRepository->findById($_POST['fin']);
            $collecte[0]->setStatut('récupéré');
            $this->getDoctrine()->getManager()->flush();
        }
        return $this->render('collecte/index.html.twig', [
            'collectes' => $paginator->paginate($collecteRepository->findAll(), $request->query->getInt('page', 1), 10),
        ]);
    }

    /**
     * @Route("/create", name="collecte_create", methods={"GET", "POST"})
     */
    public function creation(ArticlesRepository $articlesRepository, EmplacementRepository $emplacementRepository): Response
    {
        // creation d'une demande de collecte 
        if (array_key_exists('collecte', $_POST)) {
            // construction de la new collecte plus d'info voir demande_creation
            $articlesId = array_keys($_POST['collecte']);
            $destinationId = $_POST['destination'];
            $collecte = new collecte();
            $date = new \DateTime('now');
            $collecte->setdate($date);
            $collecte->setNumero("C-". $date->format('YmdHis'));
            $collecte->setDemandeur($this->getUser());
            $collecte->setStatut('demande de collecte');
            foreach ($articlesId as $key) {
                $article = $articlesRepository->findById($key);
                $destination = $emplacementRepository->findEptById($destinationId[$key]);
                $article[0]->setDirection($destination[0]);
                $article[0]->setStatu('collecte');
                $collecte->addArticle($article[0]);
            }
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($collecte);
            $entityManager->flush();
            return $this->redirectToRoute('collecte_index');
        }

        // systéme de filtre par emplacement => recherche les articles selon l'emplacement choisi et l'statut
        // verifie l'existence de la clef dans $_POST et si elle n'est pas vide 
        if(array_key_exists('emplacement', $_POST) && $_POST['emplacement']){
            $empl = $emplacementRepository->findEptById($_POST['emplacement']);
            // recuperation de l'emplacement utilisé pour recuperer les articles en lien avec SQL => requete dédié
            return $this->render('collecte/create.html.twig', array(
                'articles'=>$articlesRepository->findBystatutAndEmpl($empl),
                'emplacements'=>$emplacementRepository->findAll(),
                'empl'=>$empl,
            ));
        }
        return $this->render('collecte/create.html.twig', array(
            'articles'=> $articlesRepository->findByStatut('destokage'),
            'emplacements'=>$emplacementRepository->findAll(),
        ));
    }

    /**
     * @Route("/new", name="collecte_new", methods={"GET","POST"})
     */
    public function new(Request $request): Response
    {
        $collecte = new Collecte();
        $form = $this->createForm(CollecteType::class, $collecte);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $date =  new \DateTime('now');
            $collecte->setdate($date);
            $collecte->setNumero("D-" . $date->format('YmdHis'));
            $collecte->setDemandeur($this->getUser());
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($collecte);
            $entityManager->flush();

            return $this->redirectToRoute('collecte_index');
        }

        return $this->render('collecte/new.html.twig', [
            'collecte' => $collecte,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}", name="collecte_show", methods={"GET", "POST"})
     */
    public function show(Collecte $collecte, ArticlesRepository $articlesRepository): Response
    {   

        if(array_key_exists('fin', $_POST)){
            $article = $articlesRepository->findById($_POST['fin']);
           
            if( $article[0]->getDirection() !== null){//vérifie si la direction n'est pas nul, pour ne pas perdre l'emplacement si il y a des erreurs au niveau des receptions
                $article[0]->setPosition( $article[0]->getDirection());
            }
            $article[0]->setDirection(null);
            $article[0]->setStatu('en stock');
            $this->getDoctrine()->getManager()->flush();
        }
        
        $fin = $articlesRepository->findCountByStatutAndCollecte($collecte);
        $fin = $fin[0];
        if($fin[1] === '0'){
            $collecte->setStatut('fin');
            dump($collecte);
            $this->getDoctrine()->getManager()->flush();
        }
        return $this->render('collecte/show.html.twig', [
            'collecte' => $collecte,
        ]);
    }

    /**
     * @Route("/{id}/edit", name="collecte_edit", methods={"GET","POST"})
     */
    public function edit(Request $request, Collecte $collecte): Response
    {
        $form = $this->createForm(CollecteType::class, $collecte);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->getDoctrine()->getManager()->flush();
            return $this->redirectToRoute('collecte_index', [
                'id' => $collecte->getId(),
            ]);
        }

        return $this->render('collecte/edit.html.twig', [
            'collecte' => $collecte,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}", name="collecte_delete", methods={"DELETE"})
     */
    public function delete(Request $request, Collecte $collecte): Response
    {
        if ($this->isCsrfTokenValid('delete'.$collecte->getId(), $request->request->get('_token'))) {
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->remove($collecte);
            $entityManager->flush();
        }

        return $this->redirectToRoute('collecte_index');
    }
}
