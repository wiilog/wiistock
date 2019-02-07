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
use App\Repository\StatutsRepository;

use Knp\Component\Pager\PaginatorInterface;

/**
 * @Route("/collecte")
 */
class CollecteController extends AbstractController
{
    /**
     * @Route("/{history}/index", name="collecte_index", methods={"GET", "POST"})
     */
    public function index(CollecteRepository $collecteRepository, StatutsRepository $statutsRepository, PaginatorInterface $paginator, Request $request, $history): Response
    {
        if (array_key_exists('fin', $_POST))
        {
            $collecte = $collecteRepository->findById($_POST['fin']);
            $statut = $statutsRepository->findById(18); /* 18 = Récupéré */
            $collecte[0]->setStatut($statut[0]);
            // $this->getDoctrine()->getManager()->flush();
        }

        $statut = 'fin';
        $collecteQuery = ($history === 'true') ? $collecteRepository->findAll() : $collecteRepository->findByNoStatut($statut);

        $pagination = $paginator->paginate(
            $collecteQuery, /* On récupère la requête et on la pagine */
            $request->query->getInt('page', 1),
            10
        );

        if ($history === 'true') 
        {
            return $this->render('collecte/index.html.twig', [
                'collectes' => $pagination,
                'history' => 'false',
            ]);
        } 
        else 
        {
            return $this->render('collecte/index.html.twig', [
                'collectes' => $pagination, 
                
            ]);
        }
        // return $this->render('collecte/index.html.twig', [
        //     'collectes' => $paginator->paginate($collecteRepository->findAll(), $request->query->getInt('page', 1), 10),
        // ]);
    }

    /**
     * @Route("/create", name="collecte_create", methods={"GET", "POST"})
     */
    public function creation(ArticlesRepository $articlesRepository, StatutsRepository $statutsRepository, EmplacementRepository $emplacementRepository): Response
    {
        // creation d'une demande de collecte 
        if (array_key_exists('collecte', $_POST)) 
        {
            // construction de la new collecte plus d'info voir demande_creation
            $articlesId = array_keys($_POST['collecte']);
            $destinationId = $_POST['destination'];
            $collecte = new collecte();
            $date = new \DateTime('now');
            $collecte->setdate($date);
            $collecte->setNumero("C-". $date->format('YmdHis'));
            $collecte->setDemandeur($this->getUser());
            $statut = $statutsRepository->findById(19); /* Statut 19 = Demande de collecte */
            $collecte->setStatut($statut[0]);

            foreach ($articlesId as $key) 
            {
                $article = $articlesRepository->findById($key);
                $destination = $emplacementRepository->findEptById($destinationId[$key]);
                $article[0]->setDirection($destination[0]);
                $statut = $statutsRepository->findById(4);
                $article[0]->setStatut($statut[0]);
                $collecte->addArticle($article[0]);
            }

            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($collecte);
            $entityManager->flush();

            return $this->redirectToRoute('collecte_index', array('history'=>'false'));
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
            'articles'=> $articlesRepository->findByStatut(4),
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

        if ($form->isSubmitted() && $form->isValid()) 
        {
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
    public function show(Collecte $collecte, StatutsRepository $statutsRepository, ArticlesRepository $articlesRepository): Response
    {   
        $session = $_SERVER['HTTP_REFERER'];
    //modifie le statut, la position et la direction des articles correspondant à ceux recupere par les operateurs 
        if(array_key_exists('prise', $_POST))
        {
            $article = $articlesRepository->findById($_POST['prise']);
            $statut = $statutsRepository->findById(20); /* Collecté */
            $article[0]->setQuantite($_POST['quantite']);
            $article[0]->setStatut($statut[0]);
            $this->getDoctrine()->getManager()->flush();
        }
        //si $fin === 0 alors il ne reste plus d'articles à récupérer donjc collecte fini
        if (array_key_exists('depose', $_POST)) {
            $article = $articlesRepository->findById($_POST['depose']);
            if( $article[0]->getDirection() !== null)
            {   //vérifie si la direction n'est pas nul, pour ne pas perdre l'emplacement si il y a des erreurs au niveau des receptions
                $article[0]->setPosition( $article[0]->getDirection());
            }
            $article[0]->setDirection(null);
            $statut = $statutsRepository->findById(3); /* en stock */
            $article[0]->setStatut($statut[0]);
            $this->getDoctrine()->getManager()->flush();
        }
         //verifie si une collecte est terminer 
        //Comptage des articles selon le statut 'collecte' et la collecte lié
        $fin = $articlesRepository->findCountByStatutAndCollecte($collecte);
        $fin = $fin[0];
        if($fin[1] === '0')
        {
            $statut = $statutsRepository->findById(17);
            $collecte->setStatut($statut[0]);
            $this->getDoctrine()->getManager()->flush();
        }

        return $this->render('collecte/show.html.twig', [
            'collecte' => $collecte,
            'session' => $session
        ]);
    }

    /**
     * @Route("/{id}/edit", name="collecte_edit", methods={"GET","POST"})
     */
    public function edit(Request $request, Collecte $collecte): Response
    {
        $form = $this->createForm(CollecteType::class, $collecte);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) 
        {
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
        if ($this->isCsrfTokenValid('delete'.$collecte->getId(), $request->request->get('_token'))) 
        {
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->remove($collecte);
            $entityManager->flush();
        }

        return $this->redirectToRoute('collecte_index');
    }
}
