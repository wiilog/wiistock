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

use Doctrine\Common\Collections\ArrayCollection;


/**
 * @Route("/preparation")
 */
class PreparationController extends AbstractController
{
    /**
     * @Route("/", name="preparation_index", methods="GET|POST")
     */
    public function index(PreparationRepository $preparationRepository, ReferencesArticlesRepository $referencesArticlesRepository, EmplacementRepository $emplacementRepository): Response
    {
        // dump($this->getUser()->getId());
        return $this->render('preparation/index.html.twig', [
            'preparations' => $preparationRepository->findAllByUser($this->getUser()->getId()),
            ]);
    }

    /**
     * @Route ("/validation", name="preparation_validation", methods="GET|POST")
     */
    public function validation(PreparationRepository $preparationRepository, ReferencesArticlesRepository $referencesArticlesRepository, EmplacementRepository $emplacementRepository): Response
    {
        if($_POST){
            $prepaKey = array_keys($_POST['prepaValide']);
            foreach ($prepaKey as $key) { 
                $prepa = $preparationRepository->findOneById($key);
                $prepa->setStatut('Validé');
                $articles = $prepa->getArticles();
                
                foreach ($articles as $article) {
                    $article->setStatu("demande de  sortie");
                    $article->setDirection($prepa->getDestination());
                    dump($article);
                }
            }
            $this->getDoctrine()->getManager()->flush();
            return $this->redirectToRoute('preparation_index');
        }
        return $this->render('preparation/prepaValide.html.twig', [
            'prepaDemande' => $preparationRepository->findPrepaByStatut('commande demandé'),
            ]); 
    }

    /**
     * @Route("/new", name="preparation_new", methods="GET|POST")
     */
    public function new(Request $request, ArticlesRepository $articlesRepository): Response
    {
        $preparation = new Preparation();
        $form = $this->createForm(PreparationType::class, $preparation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $preparation->setDate( new \DateTime('now'));
            $em = $this->getDoctrine()->getManager();
            $em->persist($preparation);
            $em->flush();

            return $this->redirectToRoute('preparation_index');
        }

        return $this->render('preparation/new.html.twig', [
            'preparation' => $preparation,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/creationPrepa", name="creation_preparation", methods="GET|POST")
     */
    public function creationPrepa(Request $request, ReferencesArticlesRepository $referencesArticlesRepository,ArticlesRepository $articlesRepository, EmplacementRepository $emplacementRepository): Response 
    {   
        //la creation de preparation n'utilise pas le formulaire symfony, les utilisateur demandent des articles de Reference non pas les articles
        // on recupere la liste de article de reference et on créer une instance de preparation
        $refArticles = $referencesArticlesRepository->findAll();
        $preparation = new Preparation();
       
        // si renvoie d'un réponse POST 
        if ( $_POST) {
            // on recupere la destination des articles 
            $destination = $emplacementRepository->findOneBy(array('id' =>$_POST['direction']));
            // on 'remplie' la $preparation avec les data les plus simple
            $preparation->setDestination($destination);
            $preparation->setStatut('commande demandé');
            $preparation->setUtilisateur($this->getUser());
            $preparation->setDate( new \DateTime('now'));
            // on recupere un array sous la forme ['id de l'article de réference' => 'quantite de l'article de réference voulu', ....]
            $refArtQte = $_POST["piece"];
            //on créer un array qui recupere les key de valeur de nos id 
            $refArtKey = array_keys($refArtQte);
                foreach ($refArtKey as $key) {
                    $articles = $articlesRepository->findByRefAndConfAndStock($key);
                    for($n=0; $n<$refArtQte[$key]; $n++){
                        $preparation->addArticle($articles[$n]);
                        //on modifie le statut de l'article et sa destination 
                        $articles[$n]->setStatu('demande de sortie');
                        $articles[$n]->setDirection($destination);
                    }
                }
            if (count($preparation->getArticles()) > 0){
            $em = $this->getDoctrine()->getManager();
            $em->persist($preparation);
            $em->flush();
            }
            return $this->redirectToRoute('preparation_index');  
        }
        // calcul des quantite avant la creation des preparations 
        foreach ($refArticles as $refArticle) {
            //on recupere seulement la quantite des articles requete SQL dédié
            $articleByRef = $articlesRepository->findQteByRefAndConf($refArticle);
            $quantityRef = 0;
            foreach ($articleByRef as $article){
                $quantityRef ++;
            }
            $refArticle->setQuantity($quantityRef);  
        }
        $this->getDoctrine()->getManager()->flush();
        
        return $this->render('preparation/creationPrepa.html.twig', [
            'refArticles' => $referencesArticlesRepository->findRefArtByQte(),
            'emplacements' => $emplacementRepository->findEptBy(),
            // 'articles' => $articles,//varibles de test 
        ]);
    }

    /**
     * @Route("/{id}", name="preparation_show", methods="GET")
     */
    public function show(Preparation $preparation): Response
    {
        return $this->render('preparation/show.html.twig', ['preparation' => $preparation]);
    }

    /**
     * @Route("/{id}/edit", name="preparation_edit", methods="GET|POST")
     */
    public function edit(Request $request, Preparation $preparation): Response
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
     * @Route("/{id}", name="preparation_delete", methods="DELETE")
     */
    public function delete(Request $request, Preparation $preparation): Response
    {
        if ($this->isCsrfTokenValid('delete'.$preparation->getId(), $request->request->get('_token'))) {
            $em = $this->getDoctrine()->getManager();
            $em->remove($preparation);
            $em->flush();
        }

        return $this->redirectToRoute('preparation_index');
    }
}
