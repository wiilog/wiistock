<?php

namespace App\Controller;

use App\Entity\Collecte;
use App\Form\CollecteType;
use App\Repository\CollecteRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Entity\Article;
use App\Form\ArticleType;
use App\Repository\ArticleRepository;
use App\Repository\EmplacementRepository;
use App\Repository\StatutRepository;
use App\Repository\UtilisateurRepository;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @Route("/collecte")
 */
class CollecteController extends AbstractController
{
    /**
     * @var StatutRepository
     */
    private $statutRepository;

    /**
     * @var EmplacementRepository
     */
    private $emplacementRepository;

    /**
     * @var CollecteRepository
     */
    private $collecteRepository;

    /**
     * @var ArticleRepository
     */
    private $articlesRepository;
    
    /**
     * @var UtilisateurRepository
     */
    private $utilisateurRepository;

    public function __construct(StatutRepository $statutRepository, ArticleRepository $articlesRepository, EmplacementRepository $emplacementRepository, CollecteRepository $collecteRepository, UtilisateurRepository $utilisateurRepository)
    {
        $this->statutRepository = $statutRepository;
        $this->emplacementRepository = $emplacementRepository;
        $this->articlesRepository = $articlesRepository;
        $this->collecteRepository = $collecteRepository;
        $this->utilisateurRepository = $utilisateurRepository;
    }



    /**
     * @Route("/", name="collecte_index", methods={"GET", "POST"})
     */
    public function index(Request $request): Response
    {

        return $this->render('collecte/index.html.twig', [
            'emplacements'=>$this->emplacementRepository->findAll(),

        ]);
    }

    /**
     * @Route("/creer", name="collecte_create", methods={"GET", "POST"})
     */
    public function creation(Request $request): Response
    {
        $demandeurId = $request->request->getInt('demandeur');
        $objet = $request->request->get('objet');
        $pointCollecteId = $request->request->getInt('pointCollecte');

        $date = new \DateTime('now');
        $status = $this->statutRepository->findOneByNom(Collecte::STATUS_DEMANDE);
        $numero = "C-". $date->format('YmdHis');

        $collecte = new Collecte;
        $collecte
            ->setDemandeur($this->utilisateurRepository->find($demandeurId))
            ->setNumero($numero)
            ->setDate($date)
            ->setStatut($status)
            ->setPointCollecte($this->emplacementRepository->find($pointCollecteId))
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
    public function addArticle(Request $request): Response
    {
        $articleId = $request->request->getInt('articleId');
        $quantity = $request->request->getInt('quantity');
        $collecteId = $request->request->getInt('collecteId');

        $article = $this->articlesRepository->find($articleId);
        $collecte = $this->collecteRepository->find($collecteId);

        $article
            ->setQuantiteCollectee($quantity)
            ->addCollecte($collecte);

        $em = $this->getDoctrine()->getManager();
        $em->persist($article);
        $em->flush();

        $data = [
            'Nom'=>( $article->getNom() ?  $article->getNom():""),
            'Statut'=> ($article->getStatut()->getNom() ? $article->getStatut()->getNom() : ""),
            'Conformité'=>($article->getEtat() ? 'conforme': 'anomalie'),
            'Références Articles'=> ($article->getRefArticle() ? $article->getRefArticle()->getLibelle() : ""),
            'Emplacement'=> ($article->getPosition() ? $article->getPosition()->getNom() : "0"),
            'Destination'=> ($article->getDirection() ? $article->getDirection()->getNom() : ""),
            'Quantité à collecter'=>($article->getQuantiteCollectee() ? $article->getQuantiteCollectee() : ""),
            'Actions'=> "<div class='btn btn-xs btn-default article-edit' onclick='editRow($(this))' data-toggle='modal' data-target='#modalModifyArticle' data-quantity='" . $article->getQuantiteCollectee(). "' data-name='" . $article->getNom() . "' data-id='" . $article->getId() . "'><i class='fas fa-pencil-alt fa-2x'></i></div>
                        <div class='btn btn-xs btn-default article-delete' onclick='deleteRow($(this))' data-id='" . $article->getId() . "'><i class='fas fa-trash fa-2x'></i></div>"
        ];
//TODO CG centraliser avec la même dans ArticlesController
        return new JsonResponse($data);
    }

    /**
     * @Route("/retirer-article", name="collecte_remove_article")
     */
    public function removeArticle(Request $request)
    {
        $articleId = $request->request->getInt('articleId');
        $collecteId = $request->request->getInt('collecteId');

        $article = $this->articlesRepository->find($articleId);
        $collecte = $this->collecteRepository->find($collecteId);

        if (!empty($article)) {
            $article->removeCollecte($collecte);
            $em = $this->getDoctrine()->getManager();
            $em->persist($article);
            $em->flush();
            return new JsonResponse(true);
        } else {
            return new JsonResponse(false);
        }

    }

    /**
     * @Route("/api", name="collectes_json", methods={"GET", "POST"})
     */
    public function getCollectes(): Response
    {
        $collectes = $this->collecteRepository->findAll();
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
//    public function finishCollecte(Collecte $collecte, StatutRepository $statutRepository)
//    {
//        $em = $this->getDoctrine()->getManager();
//
//        // changement statut collecte
//        $statusFinCollecte = $statutRepository->findOneBy(['nom' => Collecte::STATUS_FIN]);
//        $collecte->setStatut($statusFinCollecte);
//
//        // changement statut article
//        $statusEnStock = $statutRepository->findOneBy(['nom' => Articles::STATUS_EN_STOCK]);
//        $article = $collecte->getArticles();
//        foreach ($article as $article) {
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
     * @Route("/{id}/modifier", name="collecte_edit", methods={"GET","POST"})
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
