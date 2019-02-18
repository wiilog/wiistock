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
use App\Entity\ChampsPersonnalises;

use App\Service\FileUploader;

/**
 * @Route("/stock/references_articles")
 */
class ReferencesArticlesController extends Controller
{
    /**
     * @Route("/refArticleAPI", name="ref_article_api", methods="GET")
     */
    public function refArticleApi(Request $request, ReferencesArticlesRepository $referencesArticlesRepository) : Response
    {
        if ($request->isXmlHttpRequest()) //Si la requête est de type Xml
        {
            $refs = $referencesArticlesRepository->findAll();
            $rows = [];
            foreach ($refs as $refArticle) {
                $urlEdite = $this->generateUrl('references_articles_edit', ['id' => $refArticle->getId()] );
                $urlShow = $this->generateUrl('references_articles_show', ['id' => $refArticle->getId()] );
               
                $row = [
                    "id" => $refArticle->getId(),
                    "Libelle" => $refArticle->getLibelle(),
                    "Référence" => $refArticle->getReference(),
                    "Quantité" => $refArticle->getQuantiteStock(),
                    'actions' => "<a href='" . $urlEdite . "' class='btn btn-xs btn-default command-edit'><i class='fas fa-pencil-alt fa-2x'></i></a>
                    <a href='" . $urlShow . "' class='btn btn-xs btn-default command-edit '><i class='fas fa-eye fa-2x'></i></a>",
                ];
                array_push($rows, $row);
            }
            $data['data'] = $rows;
            return new JsonResponse($data);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/create", name="references_articles_create", methods="GET|POST")
     */
    public function create(Request $request) : Response
    {
        $referencesArticle = new ReferencesArticles();
        $form = $this->createForm(ReferencesArticlesType::class, $referencesArticle);

        $array = $this->createCustomFieldJson($this->getDoctrine()->getManager()->getRepository(ReferencesArticles::class));

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->persist($referencesArticle);
            $em->flush();

            return $this->redirectToRoute('references_articles_index');
        }

        return $this->render('references_articles/create.html.twig', [
            'references_article' => $referencesArticle,
            'form' => $form->createView(),
            'custom_json' => $array,
        ]);
    }

    /**
     * @Route("/", name="references_articles_index", methods="GET")
     */
    public function index(ReferencesArticlesRepository $referencesArticlesRepository, Request $request) : Response
    {
        return $this->render('references_articles/index.html.twig');
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
            $data = $request->request->all();
            $em = $this->getDoctrine()->getManager();
            dump($data['data']);

            $ref = $request->request->get('ref');
            $id = -1;
            if ($ref != "") {
                $id = $em->getRepository(ReferencesArticles::class)->findOneBy(['id' => $ref])->getId();
            } else {
                $referencesArticle = new ReferencesArticles();
                $referencesArticle->setLibelle($data['data'][0]['value']);
                $referencesArticle->setReference($data['data'][1]['value']);

                $i = 2;
                $array = array();
                while ($i < count($data['data']) - 1) {
                    $name = explode('[', substr($data['data'][$i]['name'], 0, -1))[1];
                    $id_field = $em->getRepository(ChampsPersonnalises::class)->findByName($name, "references_articles")->getId();
                    $item = array(
                        $id_field => $data['data'][$i]['value'],
                    );
                    array_push($array, $item);
                    $i++;
                }
                $referencesArticle->setCustom($array);
                dump($referencesArticle);
                $em->persist($referencesArticle);
                $em->flush();
                $id = $referencesArticle->getId();
            }
            return new JsonResponse($id);
        }
        throw new NotFoundHttpException('404 not found');
    }

    /**
     * @Route("/remove", name="references_articles_remove", methods="POST")
     */
    public function remove(Request $request, ReferencesArticlesRepository $referencesArticlesRepository) : Response
    {
        if ($request->isXmlHttpRequest()) {
            $em = $this->getDoctrine()->getManager();
            $referencesArticle = $referencesArticlesRepository->findOneBy(['id' => $request->request->get('id')]);
            $em->remove($champsPersonnalise);
            $em->flush();
            return $this->redirectToRoute('referentiel_articles');
        }
        throw new NotFoundHttpException('404 not found');
    }

    /**
     * @Route("/modifiy", name="references_articles_modifiy", methods="GET|POST")
     */
    public function modifiy(Request $request) : Response
    {

    }
}
