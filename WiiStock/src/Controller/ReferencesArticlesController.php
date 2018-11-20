<?php

namespace App\Controller;

use App\Entity\ReferencesArticles;
use App\Form\ReferencesArticlesType;
use App\Repository\ReferencesArticlesRepository;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * @Route("/stock/references_articles")
 */
class ReferencesArticlesController extends Controller
{
    /**
     * @Route("/get", name="references_articles_get", methods="GET")
     */
    public function getReferencesArticles(Request $request, ReferencesArticlesRepository $referencesArticlesRepository) : Response
    {
        if ($request->isXmlHttpRequest()) {
            $q = $request->query->get('q');
            $refs = $referencesArticlesRepository->findByLibelleOrRef($q);
            $rows = array();
            foreach ($refs as $ref) {
                $row = [
                    "id" => $ref->getId(),
                    "libelle" => $ref->getLibelle(),
                    "reference" => $ref->getReference(),
                    "photo_article" => $ref->getPhotoArticle(),
                ];
                array_push($rows, $row);
            }

            $data = array(
                "total_count" => count($rows),
                "items" => $rows,
            );
            return new JsonResponse($data);
        }
        throw new NotFoundHttpException('404 not found');
    }

    /**
     * @Route("/", name="references_articles_index", methods="GET")
     */
    public function index(ReferencesArticlesRepository $referencesArticlesRepository) : Response
    {
        return $this->render('references_articles/index.html.twig', ['references_articles' => $referencesArticlesRepository->findAll()]);
    }

    /**
     * @Route("/new", name="references_articles_new", methods="GET|POST")
     */
    public function new(Request $request) : Response
    {
        $referencesArticle = new ReferencesArticles();
        $form = $this->createForm(ReferencesArticlesType::class, $referencesArticle);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->persist($referencesArticle);
            $em->flush();

            return $this->redirectToRoute('references_articles_index');
        }

        return $this->render('references_articles/new.html.twig', [
            'references_article' => $referencesArticle,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}", name="references_articles_show", methods="GET")
     */
    public function show(ReferencesArticles $referencesArticle) : Response
    {
        return $this->render('references_articles/show.html.twig', ['references_article' => $referencesArticle]);
    }

    /**
     * @Route("/{id}/edit", name="references_articles_edit", methods="GET|POST")
     */
    public function edit(Request $request, ReferencesArticles $referencesArticle) : Response
    {
        $form = $this->createForm(ReferencesArticlesType::class, $referencesArticle);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->getDoctrine()->getManager()->flush();

            return $this->redirectToRoute('references_articles_edit', ['id' => $referencesArticle->getId()]);
        }

        return $this->render('references_articles/edit.html.twig', [
            'references_article' => $referencesArticle,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}", name="references_articles_delete", methods="DELETE")
     */
    public function delete(Request $request, ReferencesArticles $referencesArticle) : Response
    {
        if ($this->isCsrfTokenValid('delete' . $referencesArticle->getId(), $request->request->get('_token'))) {
            $em = $this->getDoctrine()->getManager();
            $em->remove($referencesArticle);
            $em->flush();
        }

        return $this->redirectToRoute('references_articles_index');
    }

    /**
     * @Route("/add", name="references_articles_add", methods="GET|POST")
     */
    public function add(Request $request) : Response
    {
        if ($request->isXmlHttpRequest()) {
            $em = $this->getDoctrine()->getManager();
            $libelle = $request->request->get('libelle');
            $reference = $request->request->get('reference');
            $ref = $request->request->get('ref');
            $id = -1;
            if ($reference != "" && $libelle != "") {
                $referencesArticle = new ReferencesArticles();
                $referencesArticle->setLibelle($libelle);
                $referencesArticle->setReference($reference);
                $em->persist($referencesArticle);
                $em->flush();
                $id = $referencesArticle->getId();
            } else {
                $id = $em->getRepository(ReferencesArticles::class)->findOneBy(['id' => $ref])->getId();
            }
            return new JsonResponse($id);
        }
        throw new NotFoundHttpException('404 not found');
    }
}
