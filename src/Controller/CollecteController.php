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

use App\Repository\UtilisateursRepository;

use Knp\Component\Pager\PaginatorInterface;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @Route("/collecte")
 */
class CollecteController extends AbstractController
{
    /**
     * @var StatutsRepository
     */
    private $statutsRepository;

    /**
     * @var EmplacementRepository
     */
    private $emplacementRepository;

    /**
     * @var ArticlesRepository
     */
    private $articlesRepository;

    public function __construct(StatutsRepository $statutsRepository, ArticlesRepository $articlesRepository, EmplacementRepository $emplacementRepository)
    {
        $this->statutsRepository = $statutsRepository;
        $this->emplacementRepository = $emplacementRepository;
        $this->articlesRepository = $articlesRepository;
    }

    /**
     * @Route("/", name="collecte_index", methods={"GET", "POST"})
     */
    public function index(CollecteRepository $collecteRepository, UtilisateursRepository $utilisateursRepository, PaginatorInterface $paginator, Request $request): Response
    {

        $statut = 'fin';
        $collecteQuery = $collecteRepository->findAll();

        $pagination = $paginator->paginate(
            $collecteQuery, /* On récupère la requête et on la pagine */
            $request->query->getInt('page', 1),
            10
        );
        
        if (array_key_exists('fin', $_POST))
        {
            $collecte = $collecteRepository->findById($_POST['fin']);
            $statut = $this->statutsRepository->findById(18); /* 18 = Récupéré */
            $collecte[0]->setStatut($statut[0]);
            // $this->getDoctrine()->getManager()->flush();
        }

        return $this->render('collecte/index.html.twig', [
            'collectes' => $pagination,
            'utilisateurs' => $utilisateursRepository->findAll(),
            'articles'=> $this->articlesRepository->findByStatut(4), //TODO CG pas valeur id en dur
            'emplacements'=>$this->emplacementRepository->findAll(),

        ]);
    }

    /**
     * @Route("/creer", name="collecte_create", methods={"GET", "POST"})
     */
    public function creation(ArticlesRepository $articlesRepository, StatutsRepository $statutsRepository, EmplacementRepository $emplacementRepository, UtilisateursRepository $utilisateursRepository, Request $request): Response
    {
        $demandeurId = $request->request->getInt('demandeur');
        $objet = $request->request->get('objet');
        $pointCollecteId = $request->request->getInt('pointCollecte');

        $date = new \DateTime('now');
        $status = $statutsRepository->findOneByNom('demande de collecte');
        $numero = "C-". $date->format('YmdHis');

        $collecte = new Collecte;
        $collecte
            ->setDemandeur($utilisateursRepository->find($demandeurId))
            ->setNumero($numero)
            ->setDate($date)
            ->setStatut($status)
            ->setPointCollecte($emplacementRepository->find($pointCollecteId))
            ->setObjet($objet);

        $em = $this->getDoctrine()->getManager();
        $em->persist($collecte);
        $em->flush();

        return new JsonResponse(true);
    }

    /**
     * @Route("/ajouter-article", name="collecte_add_article")
     */
    public function addArticle(Request $request, CollecteRepository $collecteRepository)
    {
        $code = $request->request->get('code');
        $quantity = $request->request->get('quantity');
        $collecteId = $request->request->get('collecteId');

        $collecte = $collecteRepository->find($collecteId);
        $article = new Articles();
//        $article->set
    }

    /**
     * @Route("/test", name="collectes_json", methods={"GET", "POST"})
     */
    public function getCollectes(CollecteRepository $collecteRepository, Request $request): Response
    {
        $collectes = $collecteRepository->findAll();
        $rows = [];
        foreach ($collectes as $collecte) {
            $rows[] = [
                'Date'=> ($collecte->getDate() ? $collecte->getDate()->format('d/m/Y') : null),
                'Demandeur'=> ($collecte->getDemandeur() ? $collecte->getDemandeur()->getUserName() : null ),
                'Objet'=> ($collecte->getObjet() ? $collecte->getObjet() : null ),
                'Statut'=> ($collecte->getStatut()->getNom() ? ucfirst($collecte->getStatut()->getNom()) : null),
                'actions' => "<a href='/collecte/". $collecte->getId() ."' class='btn btn-xs btn-default command-edit backIconListjv'><i class='fas fa-eye fa-2x'></i></a>"
            ];
        }
        $data['data'] = $rows;

        return new JsonResponse($data);
    }

//    /**
//     * @Route("/new", name="collecte_new", methods={"GET","POST"})
//     */
//    public function new(Request $request): Response
//    {
//        $collecte = new Collecte();
//        $form = $this->createForm(CollecteType::class, $collecte);
//        $form->handleRequest($request);
//
//        if ($form->isSubmitted() && $form->isValid())
//        {
//            $date =  new \DateTime('now');
//            $collecte->setdate($date);
//            $collecte->setNumero("D-" . $date->format('YmdHis'));
//            $collecte->setDemandeur($this->getUser());
//            $entityManager = $this->getDoctrine()->getManager();
//            $entityManager->persist($collecte);
//            $entityManager->flush();
//
//            return $this->redirectToRoute('collecte_index');
//        }
//
//        return $this->render('collecte/new.html.twig', [
//            'collecte' => $collecte,
//            'form' => $form->createView(),
//        ]);
//    }

    /**
     * @Route("/{id}", name="collecte_show", methods={"GET", "POST"})
     */
    public function show(Collecte $collecte): Response
    {   
//        $session = $_SERVER['HTTP_REFERER'];
    //modifie le statut, la position et la direction des articles correspondant à ceux recupere par les operateurs 
        if(array_key_exists('prise', $_POST))
        {
            $article = $this->articlesRepository->findById($_POST['prise']);
            $statut = $this->statutsRepository->findById(20); /* Collecté */
            $article[0]->setQuantite($_POST['quantite']);
            $article[0]->setStatut($statut[0]);
            $this->getDoctrine()->getManager()->flush();
        }
        //si $fin === 0 alors il ne reste plus d'articles à récupérer donjc collecte fini
        if (array_key_exists('depose', $_POST)) {
            $article = $this->articlesRepository->findById($_POST['depose']);
            if( $article[0]->getDirection() !== null)
            {   //vérifie si la direction n'est pas nul, pour ne pas perdre l'emplacement si il y a des erreurs au niveau des receptions
                $article[0]->setPosition( $article[0]->getDirection());
            }
            $article[0]->setDirection(null);
            $statut = $this->statutsRepository->findById(3); /* en stock */
            $article[0]->setStatut($statut[0]);
            $this->getDoctrine()->getManager()->flush();
        }
         //verifie si une collecte est terminer 
        //Comptage des articles selon le statut 'collecte' et la collecte lié
        $fin = $this->articlesRepository->findCountByStatutAndCollecte($collecte);
        $fin = $fin[0];
        if($fin[1] === '0')
        {
            $statut = $this->statutsRepository->findById(17);
            $collecte->setStatut($statut[0]);
            $this->getDoctrine()->getManager()->flush();
        }

        return $this->render('collecte/show.html.twig', [
            'collecte' => $collecte,
//            'session' => $session
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
     * @Route("/{id}/delete", name="collecte_delete")
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
