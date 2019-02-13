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
     * @Route("/creationpreparation", name="createPreparation", methods="POST")
     */
    public function createPreparation(Request $request, DemandeRepository $demandeRepository, StatutsRepository $statutsRepository, PreparationRepository $preparationRepository, ReferencesArticlesRepository $referencesArticlesRepository, EmplacementRepository $emplacementRepository) : Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) //Si la requête est de type Xml et que data est attribuée
        {
            // creation d'une nouvelle preparation basée sur une selection de demandes
            $preparation = new Preparation();

            //declaration de la date pour remplir Date et Numero
            $date = new \DateTime('now');
            $preparation->setNumero('P-' . $date->format('YmdHis'));
            $preparation->setDate($date);
            $statut = $statutsRepository->findById(11); /* Statut : nouvelle préparation */
            $preparation->setStatut($statut[0]);
            //Plus de detail voir creation demande meme principe

            foreach ($data as $key) {
                $demande = $demandeRepository->findById($key);
                $demande = $demande[0];
                dump($demande); // On avance dans le tableau
                $demande->setPreparation($preparation);
                $statut = $statutsRepository->findById(15); /* Statut : Demande de préparation */
                $demande->setStatut($statut[0]);
                $articles = $demande->getArticles();

                foreach ($articles as $article) {
                    $statut = $statutsRepository->findById(13); /* Statut : Demande de sortie */
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
                    "date" => $preparation->getDate()->format("Y-m-d H:i:s"),
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
    public function edit(Request $request, StatutsRepository $statutsRepository, Preparation $preparation) : Response
    {
        $form = $this->createForm(PreparationType::class, $preparation);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
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
    public function index(PreparationRepository $preparationRepository, StatutsRepository $statutsRepository, DemandeRepository $demandeRepository, ReferencesArticlesRepository $referencesArticlesRepository, EmplacementRepository $emplacementRepository, Request $request) : Response
    {

        return $this->render('preparation/index.html.twig');
    }

    /**
     * @Route("/api", name="preparation_api", methods="GET|POST")
     */
    public function preparationApi(PreparationRepository $preparationRepository, StatutsRepository $statutsRepository, DemandeRepository $demandeRepository, ReferencesArticlesRepository $referencesArticlesRepository, EmplacementRepository $emplacementRepository, Request $request) : Response
    {
        $preparations = $preparationRepository->findAll();
        $rows = [];
        foreach ($preparations as $preparation) {
            $row = [
                'id' => ($preparation->getId() ? $preparation->getId() : "null"),
                'Numéro' => ($preparation->getNumero() ? $preparation->getNumero() : "null"),
                'Date' => ($preparation->getDate() ? $preparation->getDate()->format('Y-m-d') : 'null'),
                'Statut' => ($preparation->getStatut() ? $preparation->getStatut()->getNom() : "null"),
                'actions' => "<a href='/WiiStock/WiiStock/public/index.php/preparation/" . $preparation->getId() . "' class='btn btn-xs btn-default command-edit '><i class='fas fa-eye fa-2x'></i></a>",
            ];
            array_push($rows, $row);
        }
        $data['data'] = $rows;
        return new JsonResponse($data);
    }

    /**
     * @Route("/new", name="preparation_new", methods="GET|POST")
     */
    public function new(Request $request, ArticlesRepository $articlesRepository) : Response
    {
        $preparation = new Preparation();
        $form = $this->createForm(PreparationType::class, $preparation);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
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
    public function show(Preparation $preparation, StatutsRepository $statutsRepository, PreparationRepository $preparationRepository, DemandeRepository $demandeRepository, ArticlesRepository $articlesRepository) : Response
    {
        // modelise l'action de prendre l'article dan sle stock pour constituer la preparation  
        if (array_key_exists('fin', $_POST)) {
            $article = $articlesRepository->findById($_POST['fin']);
            $statut = $statutsRepository->findById(16); /*  Le statut passe en préparation */
            $article[0]->setStatut($statut[0]);
            $this->getDoctrine()->getManager()->flush();
            
            // Meme principe que pour collecte_show =>comptage des articles selon un statut et une preparation si nul alors preparation fini 
            $demande = $demandeRepository->findById(array_keys($_POST['fin']));
            $finDemande = $articlesRepository->findCountByStatutAndDemande($demande);
            $fin = $finDemande[0];
            if ($fin[1] === '0') {
                $statut = $statutsRepository->findById(8);
                $demande[0]->setStatut($statut[0]);
                $statut = $statutsRepository->findById(24);
                $preparation->setStatut($statut[0]);
            }
            $this->getDoctrine()->getManager()->flush();
        }
        return $this->render('preparation/show.html.twig', ['preparation' => $preparation]);
    }



    /**
     * @Route("/{id}", name="preparation_delete", methods="DELETE")
     */
    public function delete(Request $request, StatutsRepository $statutsRepository, Preparation $preparation) : Response
    {
        if ($this->isCsrfTokenValid('delete' . $preparation->getId(), $request->request->get('_token'))) {
            $em = $this->getDoctrine()->getManager();
            $em->remove($preparation);
            $em->flush();
        }
        return $this->redirectToRoute('preparation_index', ['history' => 'false']);
    }
}
