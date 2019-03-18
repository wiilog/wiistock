<?php

namespace App\Controller;

use App\Entity\ReferenceArticle;
use App\Entity\ValeurChampsLibre;
use App\Form\ReferenceArticleType;

use App\Repository\ReferenceArticleRepository;
use App\Repository\ChampsLibreRepository;
use App\Repository\ValeurChampsLibreRepository;
use App\Repository\TypeRepository;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpFoundation\JsonResponse;

use App\Service\FileUploader;

/**
 * @Route("/reference_article")
 */
class ReferenceArticleController extends Controller
{

    /**
     * @var ReferenceArticleRepository
     */
    private $referenceArticleRepository;

    /**
     * @var TypeRepository
     */
    private $typeRepository;

    /**
     * @var ChampslibreRepository
     */
    private $champsLibreRepository;

    /**
     * @var ValeurChampslibreRepository
     */
    private $valeurChampsLibreRepository;

    public function __construct(ValeurChampslibreRepository $valeurChampsLibreRepository, ReferenceArticleRepository $referenceArticleRepository, TypeRepository  $typeRepository, ChampsLibreRepository $champsLibreRepository)
    {
        $this->referenceArticleRepository = $referenceArticleRepository;
        $this->champsLibreRepository = $champsLibreRepository;
        $this->valeurChampsLibreRepository = $valeurChampsLibreRepository;
        $this->typeRepository = $typeRepository;
    }



    /**
     * @Route("/refArticleAPI", name="ref_article_api", options={"expose"=true}, methods="GET|POST")
     */
    public function refArticleApi(Request $request): Response
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
                        'Actions' => $this->renderView('reference_article/datatableReferenceArticleRow.html.twig', [
                            'url' => $url,
                            'idRefArticle' => $refArticle->getId()
                        ]),
                    ];
                }
                $data['data'] = $rows;
                return new JsonResponse($data);
            }
        throw new NotFoundHttpException("404");
    }


    /**
     * @Route("/nouveau", name="reference_article_new", options={"expose"=true}, methods="GET|POST")
     */
    public function new(Request $request): Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $em = $this->getDoctrine()->getManager();
            $refArticle = new ReferenceArticle();
            $refArticle
                ->setLibelle($data['libelle'])
                ->setReference($data['reference'])
                ->setType($this->typeRepository->find($data['type']));
            $em->persist($refArticle);
            $em->flush();
            $champsLibreKey = array_keys($data);

            foreach ($champsLibreKey as $champs) {
                if (gettype($champs) === 'integer') {
                    $valeurChampLibre = new ValeurChampsLibre();
                    $valeurChampLibre
                        ->setValeur($data[$champs])
                        ->addArticleReference($refArticle)
                        ->setChampLibre($this->champsLibreRepository->find($champs));
                    $em->persist($valeurChampLibre);
                    $em->flush();
                }
            }
            return new JsonResponse($data);
        }
        throw new NotFoundHttpException("404");
    }



    /**
     * @Route("/", name="reference_article_index",  methods="GET")
     */
    public function index(Request $request): Response
    {
        return $this->render('reference_article/index.html.twig', [
            'types' => $this->typeRepository->getByCategoryLabel('référence article'),
        ]);
    }

    /**
     * @Route("/show", name="reference_article_show", options={"expose"=true},  methods="GET|POST")
     */
    public function show(Request $request): Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $articleRef = $this->referenceArticleRepository->find($data);
            $idType = $articleRef->getType()->getId();
            $valeurChampLibre = $this->valeurChampsLibreRepository->getByArticleType($data, $idType);
            $json = $this->renderView('reference_article/modalShowRefArticleContent.html.twig', [
                'articleRef' => $articleRef,
                'valeurChampLibre'=> $valeurChampLibre
                ]);
            return new JsonResponse($json);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/editApi", name="reference_article_edit_api", options={"expose"=true},  methods="GET|POST")
     */
    public function editApi(Request $request): Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {

            $articleRef = $this->referenceArticleRepository->find($data);
            $json = $this->renderView('reference_article/modalEditRefArticleContent.html.twig', [
                'articleRef' => $articleRef,
                'types' => $this->typeRepository-> getByCategoryLabel('référence article'),
            ]);
            return new JsonResponse($json);
        }
        throw new NotFoundHttpException("404");
    }



    /**
     * @Route("/edit", name="reference_article_edit",  options={"expose"=true}, methods="GET|POST")
     */
    public function edit(Request $request): Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $em = $this->getDoctrine()->getManager();
            $refArticle = $this->referenceArticleRepository->find($data['id']);
            $refArticle
                ->setLibelle($data['libelle'])
                ->setReference($data['reference'])
                ->setType($this->typeRepository->find($data['type']));
            $em->flush();
            $champsLibreKey = array_keys($data);
            foreach ($champsLibreKey as $champs) {
                if (gettype($champs) === 'integer') {
                    $valeurChampLibre = $this->valeurChampsLibreRepository->find($champs);
                    $valeurChampLibre
                        ->setValeur($data[$champs]);
                    $entityManager = $this->getDoctrine()->getManager();
                    $entityManager->flush();
                }
            }
            return new JsonResponse();
        }
        throw new NotFoundHttpException("404");
    }



    /**
     * @Route("/supprimerRefArticle", name="reference_article_delete", options={"expose"=true}, methods="GET|POST")
     */
    public function delete(Request $request): Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $refArticle = $this->referenceArticleRepository->find($data['refArticle']);
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->remove($refArticle);
            $entityManager->flush();
            return new JsonResponse();
        }
        throw new NotFoundHttpException("404");
    }
}
