<?php

namespace App\Controller;

use App\Entity\ReferenceArticle;
use App\Form\ReferenceArticleType;
use App\Repository\ReferenceArticleRepository;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpFoundation\JsonResponse;

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

    public function __construct(ReferenceArticleRepository $referenceArticleRepository)
    {
        $this->referenceArticleRepository = $referenceArticleRepository;
    }

    /**
     * @Route("/nouveau", name="reference_article_new", options={"expose"=true}, methods="GET|POST")
     */
    public function new(Request $request) : Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            

            // $em = $this->getDoctrine()->getManager();
            // $em->persist($champsLibre);
            // $em->flush();
            // return new JsonResponse($data);
        }
        throw new NotFoundHttpException("404");
    }
    
    /**
     * @Route("/", name="reference_article_index", methods="GET")
     */
    public function index(Request $request) : Response
    {
        return $this->render('reference_article/index.html.twig');
    }
    
     /**
     * @Route("/refArticleApi", name="ref_article_api", options={"expose"=true}, methods="POST")
     */
    public function refArticleApi(Request $request) : Response
    {
        if ($request->isXmlHttpRequest()) //Si la requête est de type Xml
        {
            $refs = $this->referenceArticleRepository->findAll();
            $rows = [];
            foreach ($refs as $refArticle) {
                $url['edit'] = $this->generateUrl('reference_article_edit', ['id' => $refArticle->getId()] );
                $url['show'] = $this->generateUrl('reference_article_show', ['id' => $refArticle->getId()] );
               
                $rows[] = [
                    "id" => $refArticle->getId(),
                    "Libellé" => $refArticle->getLibelle(),
                    "Référence" => $refArticle->getReference(),
                    'Actions' => $this->renderView('reference_article/datatableReferenceArticleRow.html.twig', [
                            'url' => $url,
                            'idrefArticle'=> $refArticle->getId()
                            ]),
                ];
            }
            $data['data'] = $rows;
            return new JsonResponse($data);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/{id}", name="reference_article_show", methods="GET")
     */
    public function show(ReferenceArticle $referenceArticle) : Response
    {
        return $this->render('reference_article/show.html.twig', ['reference_article' => $referenceArticle]);
    }
    
    /**
     * @Route("/{id}", name="reference_article_delete", methods="DELETE")
     */
    public function delete(Request $request, ReferenceArticle $referenceArticle) : Response
    {
        // if ($this->isCsrfTokenValid('delete' . $referenceArticle->getId(), $request->request->get('_token'))) {
            //     $em = $this->getDoctrine()->getManager();
            //     $em->remove($referenceArticle);
            //     $em->flush();
            // }
            
            return $this->redirectToRoute('reference_article_index');
        }
        
       
    }
    