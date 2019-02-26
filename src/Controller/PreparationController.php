<?php

namespace App\Controller;

use App\Entity\Article;
use App\Entity\Preparation;
use App\Form\PreparationType;
use App\Repository\PreparationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Tests\Compiler\D;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

use Symfony\Component\Form\Extension\Core\Type\TextType;

use App\Entity\ReferenceArticle;
use App\Form\ReferenceArticleType;
use App\Repository\ReferenceArticleRepository;

use App\Repository\ArticleRepository;

use App\Entity\Emplacement;
use App\Form\EmplacementType;
use App\Repository\EmplacementRepository;

use App\Entity\Livraison;
use App\Form\LivraisonType;
use App\Repository\LivraisonRepository;

use App\Entity\Demande;
use App\Form\DemandeType;
use App\Repository\DemandeRepository;

use Doctrine\Common\Collections\ArrayCollection;
use Knp\Component\Pager\PaginatorInterface;
use App\Repository\StatutRepository;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @Route("/preparation")
 */
class PreparationController extends AbstractController
{
    /**
     * @var StatutRepository
     */
    private $statutRepository;

    /**
     * @var ReferenceArticleRepository
     */
    private $referenceArticleRepository;

    /**
     * @var DemandeRepository
     */
    private $demandeRepository;

    public function __construct(StatutRepository $statutRepository, DemandeRepository $demandeRepository, ReferenceArticleRepository $referenceArticleRepository)
    {
        $this->statutRepository = $statutRepository;
        $this->referenceArticleRepository = $referenceArticleRepository;
        $this->demandeRepository = $demandeRepository;
    }

    /**
     * @Route("/creationpreparation", name="createPreparation", methods="POST")
     */
    public function createPreparation(Request $request) : Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) //Si la requête est de type Xml et que data est attribuée
        {
            // creation d'une nouvelle preparation basée sur une selection de demandes
            $preparation = new Preparation();

            //declaration de la date pour remplir Date et Numero
            $date = new \DateTime('now');
            $preparation->setNumero('P-' . $date->format('YmdHis'));
            $preparation->setDate($date);
            $statut = $this->statutRepository->findOneByCategorieAndStatut(Preparation::CATEGORIE, Preparation::STATUT_NOUVELLE);
            $preparation->setStatut($statut);
            //Plus de detail voir creation demande meme principe

            foreach ($data as $key)
            {
                $demande = $this->demandeRepository->find($key);
                // On avance dans le tableau
                $statut = $this->statutRepository->findOneByCategorieAndStatut(Demande::CATEGORIE, Demande::STATUT_A_TRAITER);
                $demande
                    ->setPreparation($preparation)
                    ->setStatut($statut);

                $articles = $demande->getArticles();
                foreach ($articles as $article)
                {
                    $statut = $this->statutRepository->findOneByCategorieAndStatut(Article::CATEGORIE, Article::STATUT_DEMANDE_SORTIE);
                    $article
                        ->setStatut($statut)
                        ->setDirection($demande->getDestination());
                }
            }

            $em = $this->getDoctrine()->getManager();
            $em->persist($preparation);
            $em->flush();

            $data = [
                "preparation" => [
                    "id" => $preparation->getId(),
                    "numero" => $preparation->getNumero(),
                    "date" => $preparation->getDate()->format("d/m/Y H:i:s"),
                    "Statut" => $preparation->getStatut()->getNom()
                ],
                "message" => "Votre préparation à été enregistrer"
            ];
            $data = json_encode($data);
            return new JsonResponse($data);
        }

        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/", name="preparation_index", methods="GET|POST")
     */
    public function index(Request $request) : Response
    {
        return $this->render('preparation/index.html.twig');
    }

    /**
     * @Route("/api", name="preparation_api", methods="GET|POST")
     */
    public function preparationApi(Request $request, PreparationRepository $preparationRepository) : Response
    {
        if ($request->isXmlHttpRequest()) //Si la requête est de type Xml
        {
            $preparations = $preparationRepository->findAll();
            $rows = [];
            foreach ($preparations as $preparation) 
            {
                $url['show'] = $this->generateUrl('preparation_show', ['id' => $preparation->getId()] );
                $rows[] = [
                    'Numéro' => ($preparation->getNumero() ? $preparation->getNumero() : ""),
                    'Date' => ($preparation->getDate() ? $preparation->getDate()->format('d/m/Y') : ''),
                    'Statut' => ($preparation->getStatut() ? $preparation->getStatut()->getNom() : ""),
                    'Actions' => $this->renderView('preparation/datatablePreparationRow.html.twig', ['url' => $url]),
                ];
            }
            $data['data'] = $rows;
            return new JsonResponse($data);
        }
        throw new NotFoundHttpException("404");
    }


    /**
     * @Route("/api/{id}", name="LignePreparation_api", methods={"POST"}) 
     */
    public function LignePreparationApi(Request $request, Demande $demande) : Response
    {
        if($request->isXmlHttpRequest()) //Si la requête est de type Xml
        {   
            
            $LignePreparations = $demande->getLigneArticle();
            $rows = [];
        
            foreach ($LignePreparations as $LignePreparation) 
            {
                $refPreparation = $this->referenceArticleRepository->findOneById($LignePreparation["reference"]);
                $rows[] = [
                    "Référence CEA" => ($LignePreparation["reference"] ? $LignePreparation["reference"] : ' '),
                    "Libellé" => ($refPreparation->getLibelle() ? $refPreparation->getLibelle() : ' '),
                    "Quantité" => ($LignePreparation["quantite"] ? $LignePreparation["quantite"] : ' '),
                ];
            }

            $data['data'] = $rows;
            return new JsonResponse($data);
        }
        throw new NotFoundHttpException("404");
    }


    /**
     * @Route("/new", name="preparation_new", methods="GET|POST")
     */
    public function new(Request $request, ArticleRepository $articlesRepository) : Response
    {
        $preparation = new Preparation();
        $form = $this->createForm(PreparationType::class, $preparation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) 
        {
            $preparation->setDate(new \DateTime('now'));
            $em = $this->getDoctrine()->getManager();
            $em->persist($preparation);
            $em->flush();
            return $this->redirectToRoute('preparation_index', array('history' => 'false'));
        }

        return $this->render('preparation/new.html.twig', [
            'preparation' => $preparation,
            'form' => $form->createView(),
        ]);
    }


    /**
     * @Route("/{id}", name="preparation_show", methods="GET|POST")
     */
    public function show(Preparation $preparation, ArticleRepository $articlesRepository) : Response
    {
        // modelise l'action de prendre l'article dan sle stock pour constituer la preparation  
        if (array_key_exists('fin', $_POST)) 
        {
            $article = $articlesRepository->find($_POST['fin']);
            $statut = $this->statutRepository->findOneByCategorieAndStatut(Preparation::CATEGORIE, Preparation::STATUT_EN_COURS);
            $article->setStatut($statut);
            $this->getDoctrine()->getManager()->flush();
            
            // Meme principe que pour collecte_show =>comptage des article selon un statut et une preparation si nul alors preparation fini
            $demande = $this->demandeRepository->find(array_keys($_POST['fin']));
            $finDemande = $articlesRepository->findCountByStatutAndDemande($demande);
            $fin = $finDemande[0];

            if ($fin[1] === '0') 
            {
                $statut = $this->statutRepository->findOneByCategorieAndStatut(Demande::CATEGORIE, Demande::STATUT_EN_COURS);
                $demande->setStatut($statut);
                $statut = $this->statutRepository->findOneByCategorieAndStatut(Preparation::CATEGORIE, Preparation::STATUT_EN_COURS);
                $preparation->setStatut($statut);
            }
            $this->getDoctrine()->getManager()->flush();
        }
        return $this->render('preparation/show.html.twig', ['preparation' => $preparation]);
    }



    /**
     * @Route("/{id}", name="preparation_delete", methods="DELETE")
     */
    public function delete(Request $request, Preparation $preparation) : Response
    {
        if ($this->isCsrfTokenValid('delete' . $preparation->getId(), $request->request->get('_token')))
        {
            $em = $this->getDoctrine()->getManager();
            $em->remove($preparation);
            $em->flush();
        }
        return $this->redirectToRoute('preparation_index', ['history' => 'false']);
    }
}
