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

        return $this->render('collecte/index.html.twig', [
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
        $status = $statutsRepository->findOneByNom(Collecte::STATUS_DEMANDE);
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

        $url = $this->generateUrl('collecte_show', ['id' => $collecte->getId()]);
        $data = [
            'Date'=> ($collecte->getDate() ? $collecte->getDate()->format('d/m/Y') : null),
            'Demandeur'=> ($collecte->getDemandeur() ? $collecte->getDemandeur()->getUserName() : null ),
            'Libellé'=> ($collecte->getObjet() ? $collecte->getObjet() : null ),
            'Statut'=> ($collecte->getStatut()->getNom() ? ucfirst($collecte->getStatut()->getNom()) : null),
            'actions' => "<a href='" . $url . "' class='btn btn-xs btn-default command-edit'><i class='fas fa-eye fa-2x'></i></a>"
        ];

        return new JsonResponse($data);
    }

    /**
     * @Route("/ajouter-article", name="collecte_add_article")
     */
    public function addArticle(Request $request, CollecteRepository $collecteRepository, ArticlesRepository $articlesRepository): Response
    {
        $articleId = $request->request->getInt('articleId');
        $quantity = $request->request->getInt('quantity');
        $collecteId = $request->request->getInt('collecteId');

        $article = $articlesRepository->find($articleId);
        $collecte = $collecteRepository->find($collecteId);

        $article
            ->setQuantiteCollectee($quantity)
            ->addCollecte($collecte);

        $em = $this->getDoctrine()->getManager();
        $em->persist($article);
        $em->flush();

        $data = [
            'Nom'=>( $article->getNom() ?  $article->getNom():"null"),
            'Statut'=> ($article->getStatut()->getNom() ? $article->getStatut()->getNom() : "null"),
            'Conformité'=>($article->getEtat() ? 'conforme': 'anomalie'),
            'Reférences Articles'=> ($article->getRefArticle() ? $article->getRefArticle()->getLibelle() : "null"),
            'Position'=> ($article->getPosition() ? $article->getPosition()->getNom() : "null"),
            'Destination'=> ($article->getDirection() ? $article->getDirection()->getNom() : "null"),
            'Quantité à collecter'=>($article->getQuantiteCollectee() ? $article->getQuantiteCollectee() : "null"),
            'Actions'=> "<div class='btn btn-xs btn-default article-edit' onclick='editRow($(this))'><i class='fas fa-pencil-alt fa-2x'></i></div>
                        <div class='btn btn-xs btn-default article-delete' onclick='deleteRow($(this))'><i class='fas fa-trash fa-2x'></i></div>"
        ];
//TODO CG centraliser avec la même dans ArticlesController
        return new JsonResponse($data);
    }

    /**
     * @Route("/api", name="collectes_json", methods={"GET", "POST"})
     */
    public function getCollectes(CollecteRepository $collecteRepository): Response
    {
        $collectes = $collecteRepository->findAll();
        $rows = [];
        foreach ($collectes as $collecte) {
            $url = $this->generateUrl('collecte_show', ['id' => $collecte->getId()]);
            $rows[] = [
                'Date'=> ($collecte->getDate() ? $collecte->getDate()->format('d/m/Y') : null),
                'Demandeur'=> ($collecte->getDemandeur() ? $collecte->getDemandeur()->getUserName() : null ),
                'Libellé'=> ($collecte->getObjet() ? $collecte->getObjet() : null ),
                'Statut'=> ($collecte->getStatut()->getNom() ? ucfirst($collecte->getStatut()->getNom()) : null),
                'actions' => "<a href='" . $url . "' class='btn btn-xs btn-default command-edit'><i class='fas fa-eye fa-2x'></i></a>"
            ];
        }
        $data['data'] = $rows;

        return new JsonResponse($data);
    }
//
//    /**
//     * @Route("{id}/finish", name="finish_collecte")
//     */
//    public function finishCollecte(Collecte $collecte, StatutsRepository $statutsRepository)
//    {
//        $em = $this->getDoctrine()->getManager();
//
//        // changement statut collecte
//        $statusFinCollecte = $statutsRepository->findOneBy(['nom' => Collecte::STATUS_FIN]);
//        $collecte->setStatut($statusFinCollecte);
//
//        // changement statut articles
//        $statusEnStock = $statutsRepository->findOneBy(['nom' => Articles::STATUS_EN_STOCK]);
//        $articles = $collecte->getArticles();
//        foreach ($articles as $article) {
//            $article->setStatut($statusEnStock);
//            $em->persist($article);
//        }
//        $em->flush();
//    }

    /**
     * @Route("/{id}", name="collecte_show", methods={"GET", "POST"})
     */
    public function show(Collecte $collecte): Response
    {
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
    public function delete(Collecte $collecte):Response
    {
        if ($collecte->getStatut()->getNom() == Collecte::STATUS_DEMANDE)
        {
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->remove($collecte);
            $entityManager->flush();
        }

        return new JsonResponse(true); //TODO CG
    }
}
