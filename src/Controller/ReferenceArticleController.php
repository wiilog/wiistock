<?php

namespace App\Controller;

use App\Entity\ReferenceArticle;
use App\Form\ReferenceArticleType;
use App\Repository\ChampPersonnaliseRepository;
use App\Repository\ReferenceArticleRepository;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpFoundation\JsonResponse;
use App\Entity\ChampPersonnalise;

use App\Service\FileUploader;

/**
 * @Route("/stock/reference_article")
 */
class ReferenceArticleController extends Controller
{

     /**
     * @var ReferenceArticleRepository
     */
    private $referenceArticleRepository;

    /**
     * @var ChampPersonnaliseRepository
     */
    private $champPersonnaliseRepository;


    public function __construct(ReferenceArticleRepository $referenceArticleRepository, ChampPersonnaliseRepository $champPersonnaliseRepository)
    {
        $this->referenceArticleRepository = $referenceArticleRepository;
        $this->champPersonnaliseRepository = $champPersonnaliseRepository;
    }



    /**
     * @Route("/creation-reference-article", name="createRefArticle", options={"expose"=true}, methods={"POST", "GET"})
     */
    public function createRefArticle(Request $request) 
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (count($data) >= 2) {

                $em =$this->getDoctrine()->getEntityManager();

                $referenceArticle = new ReferenceArticle();
                $referenceArticle->setLibelle($data['libelle'])
                        ->setReference($data['reference']);

                $em->persist($referenceArticle);
                $em->flush();

                return new JsonResponse($data);
            }
        }
        throw new NotFoundHttpException("404");
    }



    /**
     * @Route("/refArticleAPI", name="ref_article_api", options={"expose"=true}, methods="GET")
     */
    public function refArticleApi(Request $request) : Response
    {
        if ($request->isXmlHttpRequest()) //Si la requête est de type Xml
        {
            $refs = $this->referenceArticleRepository->findAll();
            $rows = [];
            foreach ($refs as $refArticle) {
                $url['edit'] = $this->generateUrl('reference_article_edit', ['id' => $refArticle->getId()]);
                $url['show'] = $this->generateUrl('reference_article_show', ['id' => $refArticle->getId()]);
               
                $rows[] = [
                    "id" => $refArticle->getId(),
                    "Libellé" => $refArticle->getLibelle(),
                    "Référence" => $refArticle->getReference(),
                    'Actions' => $this->renderView('reference_article/datatableReferenceArticleRow.html.twig', ['url' => $url]),
                ];
            }
            $data['data'] = $rows;
            return new JsonResponse($data);
        }
        throw new NotFoundHttpException("404");
    }



    /**
     * @Route("/create", name="reference_article_create", methods="GET|POST")
     */
    public function create(Request $request) : Response
    {
        $referenceArticle = new ReferenceArticle();
        $form = $this->createForm(ReferenceArticleType::class, $referenceArticle);
        $array = $this->createCustomFieldJson($this->getDoctrine()->getManager()->getRepository(ReferenceArticle::class));

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->persist($referenceArticle);
            $em->flush();

            return $this->redirectToRoute('reference_article_index');
        }

        return $this->render('reference_article/create.html.twig', [
            'reference_article' => $referenceArticle,
            'form' => $form->createView(),
            'custom_json' => $array,
        ]);
    }



    /**
     * @Route("/", name="reference_article_index", methods="GET")
     */
    public function index(Request $request) : Response
    {
        return $this->render('reference_article/index.html.twig');
    }



    /**
     * @Route("/{id}", name="reference_article_show", methods="GET")
     */
    public function show(ReferenceArticle $referenceArticle) : Response
    {
        return $this->render('reference_article/show.html.twig', ['reference_article' => $referenceArticle]);
    }



    /**
     * @Route("/{id}/edit", name="reference_article_edit", methods="GET|POST")
     */
    public function edit(Request $request, ReferenceArticle $referenceArticle) : Response
    {
        $form = $this->createForm(ReferenceArticleType::class, $referenceArticle);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->getDoctrine()->getManager()->flush();

            return $this->redirectToRoute('reference_article_index');
        }

        return $this->render('reference_article/edit.html.twig', [
            'reference_article' => $referenceArticle,
            'form' => $form->createView(),
        ]);
    }



    /**
     * @Route("/{id}", name="reference_article_delete", methods="DELETE")
     */
    public function delete(Request $request, ReferenceArticle $referenceArticle) : Response
    {
        if ($this->isCsrfTokenValid('delete' . $referenceArticle->getId(), $request->request->get('_token'))) {
            $em = $this->getDoctrine()->getManager();
            $em->remove($referenceArticle);
            $em->flush();
        }

        return $this->redirectToRoute('reference_article_index');
    }



    /**
     * @Route("/add", name="reference_article_add", methods="GET|POST")
     */
    public function add(Request $request) : Response
    {
        if ($request->isXmlHttpRequest()) {
            $data = $request->request->all();
            $em = $this->getDoctrine()->getManager();

            $ref = $request->request->get('ref');
            $id = -1;
            if ($ref != "") {
                $id = $em->getRepository(ReferenceArticle::class)->findOneBy(['id' => $ref])->getId();
            } else {
                $referenceArticle = new ReferenceArticle();
                $referenceArticle
                    ->setLibelle($data['data'][0]['value'])
                    ->setReference($data['data'][1]['value']);

                $i = 2;
                $array = array();
                while ($i < count($data['data']) - 1) {
                    $name = explode('[', substr($data['data'][$i]['name'], 0, -1))[1];
                    $id_field = $this->champPersonnaliseRepository->findByName($name, "reference_article")->getId();
                    $item = array(
                        $id_field => $data['data'][$i]['value'],
                    );
                    array_push($array, $item);
                    $i++;
                }
                $referenceArticle->setCustom($array);
                $em->persist($referenceArticle);
                $em->flush();
                $id = $referenceArticle->getId();
            }
            return new JsonResponse($id);
        }
        throw new NotFoundHttpException('404 not found');
    }

//    /**
//     * @Route("/remove", name="reference_article_remove", methods="POST")
//     */
//    public function remove(Request $request) : Response
//    {
//        if ($request->isXmlHttpRequest()) {
//            $em = $this->getDoctrine()->getManager();
//            $referenceArticle = $this->referenceArticleRepository->findOneBy(['id' => $request->request->get('id')]);
//            $em->remove($referenceArticle);
//            $em->flush();
//            return $this->redirectToRoute('referentiel_articles');
//        }
//        throw new NotFoundHttpException('404 not found');
//    }

}
