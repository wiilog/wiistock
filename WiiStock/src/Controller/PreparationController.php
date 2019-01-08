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

/**
 * @Route("/preparation")
 */
class PreparationController extends AbstractController
{
    /**
     * @Route("/", name="preparation_index", methods="GET")
     */
    public function index(PreparationRepository $preparationRepository): Response
    {
        return $this->render('preparation/index.html.twig', ['preparations' => $preparationRepository->findAll()]);
    }

    /**
     * @Route("/new", name="preparation_new", methods="GET|POST")
     */
    public function new(Request $request, ArticlesRepository $articlesRepository): Response
    {
        $preparation = new Preparation();
        $form = $this->createForm(PreparationType::class, $preparation);
        $form->handleRequest($request);

        // dump($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $table= $articlesRepository->findOneBy(['id'=> 26]);
            $preparation->addArticle($table);
            $preparation->setDate( new \DateTime('now'));
            // dump($preparation);
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
        $refArticle = $referencesArticlesRepository->findAll();//a modifier
        $preparation = new Preparation();

        if ($_POST) {
            $preparation->setDestination($emplacementRepository->findOneBy(array('id' =>$_POST['direction'])));
            $preparation->setStatut('commande demandé');
            $preparation->setUtilisateur($this->getUser());
            $preparation->setDate( new \DateTime('now'));
            $refArtQte = $_POST["piece"];



            dump($refArticle[3]->getId());
            dump($refArtQte);
            dump($refArtQte[5]);

            $table= NUll;
             for($i=0;$i<count($refArticle);$i++){
                $articles= $articlesRepository->findByRefAndConf($refArticle[$i]);
                if(array_key_exists($i, $refArtQte) === true && $refArtQte[$i] !== '0' && $refArticle[$i]->getId()===$refArtQte[$i]){
                    $table= $articlesRepository->findOneBy(['id'=> 26]);
                }
             }
            if( $table !== NULL){
                $preparation->addArticle($table);
            }

            // dump($preparation);
            // $em = $this->getDoctrine()->getManager();
            // $em->persist($preparation);
            // $em->flush();
        }

        return $this->render('preparation/creationPrepa.html.twig', [
            'refArticles' => $refArticle,
            'emplacements' => $emplacementRepository->findAll(),//a modifier
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
