<?php

namespace App\Controller;

use App\Entity\Preparation;
use App\Form\PreparationType;
use App\Repository\PreparationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

use Symfony\Component\Form\Extension\Core\Type\TextType;

use App\Entity\ReferencesArticles;
use App\Form\ReferencesArticlesType;
use App\Repository\ReferencesArticlesRepository;

use App\Repository\ArticlesRepository;

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
use App\Repository\StatutsRepository;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @Route("/preparation")
 */
class PreparationController extends AbstractController
{
    /**
     * @var StatutsRepository
     */
    private $statutsRepository;

    /**
     * @var ReferencesArticlesRepository
     */
    private $referencesArticlesRepository;

    /**
     * @var DemandeRepository
     */
    private $demandeRepository;

    public function __construct(StatutsRepository $statutsRepository, DemandeRepository $demandeRepository, ReferencesArticlesRepository $referencesArticlesRepository)
    {
        $this->statutsRepository = $statutsRepository;
        $this->referencesArticlesRepository = $referencesArticlesRepository;
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
            $statut = $this->statutsRepository->findById(11); /* Statut : nouvelle préparation */
            $preparation->setStatut($statut[0]);
            //Plus de detail voir creation demande meme principe

            foreach ($data as $key)
            {
                $demande = $this->demandeRepository->findById($key);
                $demande = $demande[0];
                dump($demande); // On avance dans le tableau
                $demande->setPreparation($preparation);
                $statut = $this->statutsRepository->findById(14); /* Statut : Demande de préparation */
                $demande->setStatut($statut[0]);
                $articles = $demande->getArticles();

                foreach ($articles as $article)
                {
                    $statut = $this->statutsRepository->findById(13); /* Statut : Demande de sortie */
                    $article->setStatut($statut[0]);
                    $article->setDirection($demande->getDestination());
                }
                $this->getDoctrine()->getManager();
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
     * @Route("/{id}/edit", name="preparation_edit", methods="GET|POST")
     */
    public function edit(Request $request, Preparation $preparation) : Response
    {
        $form = $this->createForm(PreparationType::class, $preparation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid())
        {
            $this->getDoctrine()->getManager()->flush();
            return $this->redirectToRoute('preparation_edit', ['id' => $preparation->getId()]);
        }

        return $this->render('preparation/edit.html.twig', [
            'preparation' => $preparation,
            'form' => $form->createView(),
        ]);
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
                $urlShow = $this->generateUrl('preparation_show', ['id' => $preparation->getId()] );
                $row = [
                    'Numéro' => ($preparation->getNumero() ? $preparation->getNumero() : ""),
                    'Date' => ($preparation->getDate() ? $preparation->getDate()->format('d/m/Y') : ''),
                    'Statut' => ($preparation->getStatut() ? $preparation->getStatut()->getNom() : ""),
                    'Actions' => "<a href='" . $urlShow . "' class='btn btn-xs btn-default command-edit '><i class='fas fa-eye fa-2x'></i></a>",
                ];
                array_push($rows, $row);
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
                $refPreparation = $this->referencesArticlesRepository->findOneById($LignePreparation["reference"]);
                $row = [ 
                    "Références CEA" => ($LignePreparation["reference"] ? $LignePreparation["reference"] : ''),
                    "Libellé" => ($refPreparation->getLibelle() ? $refPreparation->getLibelle() : ''),
                    "Quantité" => ($LignePreparation["quantite"] ? $LignePreparation["quantite"] : ''),
                    "Actions" => "actions", 
                ];
                array_push($rows, $row);
            }

            $data['data'] = $rows;
            return new JsonResponse($data);
        }
        throw new NotFoundHttpException("404");
    }


    /**
     * @Route("/new", name="preparation_new", methods="GET|POST")
     */
    public function new(Request $request, ArticlesRepository $articlesRepository) : Response
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
    public function show(Preparation $preparation, ArticlesRepository $articlesRepository) : Response
    {
        // modelise l'action de prendre l'article dan sle stock pour constituer la preparation  
        if (array_key_exists('fin', $_POST)) 
        {
            $article = $articlesRepository->findById($_POST['fin']);
            $statut = $this->statutsRepository->findById(16); /*  Le statut passe en préparation */
            $article[0]->setStatut($statut[0]);
            $this->getDoctrine()->getManager()->flush();
            
            // Meme principe que pour collecte_show =>comptage des articles selon un statut et une preparation si nul alors preparation fini 
            $demande = $this->demandeRepository->findById(array_keys($_POST['fin']));
            $finDemande = $articlesRepository->findCountByStatutAndDemande($demande);
            $fin = $finDemande[0];

            if ($fin[1] === '0') 
            {
                $statut = $this->statutsRepository->findById(8);
                $demande[0]->setStatut($statut[0]);
                $statut = $this->statutsRepository->findById(24);
                $preparation->setStatut($statut[0]);
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
