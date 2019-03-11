<?php

namespace App\Controller;

use App\Entity\ChampsLibre;
use App\Entity\Type;

use App\Form\ChampsLibreType;

use App\Repository\ChampsLibreRepository;
use App\Repository\TypeRepository;
use App\Repository\CategoryTypeRepository;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * @Route("/champs/libre")
 */
class ChampsLibreController extends AbstractController
{

    /**
     * @var ChampslibreRepository
     */
    private $champsLibreRepository;

    /**
     * @var TypeRepository
     */
    private $typeRepository;

    /**
     * @var CategoryTypeRepository
     */
    private $categoryTypeRepository;

    public function __construct(CategoryTypeRepository $categoryTypeRepository, ChampsLibreRepository $champsLibreRepository, TypeRepository $typeRepository)
    {
        $this->champsLibreRepository = $champsLibreRepository;
        $this->typeRepository = $typeRepository;
        $this->categoryTypeRepository = $categoryTypeRepository;
    }

    /**
     * @Route("/", name="champs_libre_index", methods={"GET"})
     */
    public function index(): Response
    {
        return $this->render('champs_libre/index.html.twig', [
            'category' => $this->categoryTypeRepository->findAll(),
        ]);
    }

    /**
     * @Route("/typeApi", name="typeApi", options={"expose"=true}, methods={"POST"})
     */
    public function typeApi(Request $request): Response
    {
        if ($request->isXmlHttpRequest()) //Si la requête est de type Xml
            {
                $types = $this->typeRepository->findAll();
                $rows = [];
                foreach ($types as $type) {
                    $url = $this->generateUrl('champs_libre_show', ['id' => $type->getId()]);
                    $rows[] =
                        [
                            'id' => ($type->getId() ? $type->getId() : "Non défini"),
                            'Label' => ($type->getLabel() ? $type->getLabel() : "Non défini"),
                            'Catégorie' => ($type->getCategory() ? $type->getCategory()->getLabel() : 'Non défini'),
                            'Actions' =>  $this->renderView('champs_libre/datatableTypeRow.html.twig', [
                                'urlChampsLibre' => $url,
                                'idType' => $type->getId()
                            ]),
                        ];
                }
                $data['data'] = $rows;
                return new JsonResponse($data);
            }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/champsLibreApi/{id}", name="champsLibreApi", options={"expose"=true}, methods={"POST"})
     */
    public function champsLibreApi(Request $request, $id): Response
    {
        if ($request->isXmlHttpRequest()) //Si la requête est de type Xml
            {
                $champsLibres = $this->champsLibreRepository->getByType($this->typeRepository->find($id));
                $rows = [];
                foreach ($champsLibres as $champsLibre) {
                    $rows[] =
                        [
                            'id' => ($champsLibre->getId() ? $champsLibre->getId() : "Non défini"),
                            'Label' => ($champsLibre->getLabel() ? $champsLibre->getLabel() : "Non défini"),
                            'Typage' => ($champsLibre->getTypage() ? $champsLibre->getTypage() : "Non défini"),
                            'Valeur par défaut' => ($champsLibre->getDefaultValue() ? $champsLibre->getDefaultValue() : "Non défini"),
                            'Actions' =>  $this->renderView('champs_libre/datatableChampsLibreRow.html.twig', ['idChampsLibre' => $champsLibre->getId()]),
                        ];
                }
                $data['data'] = $rows;
                return new JsonResponse($data);
            }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/typeNew", name="type_new", options={"expose"=true}, methods={"POST"})
     */
    public function typeNew(Request $request): Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $type = new Type();
            $type
                ->setlabel($data["label"])
                ->setCategory($this->categoryTypeRepository->find($data['category']));
            $em = $this->getDoctrine()->getManager();
            $em->persist($type);
            $em->flush();
            return new JsonResponse($data);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/voir/{id}", name="champs_libre_show", methods={"GET","POST"})
     */
    public function show(Request $request, $id): Response
    {
        return $this->render('champs_libre/show.html.twig', [
            'type' => $this->typeRepository->find($id),

        ]);
    }

    /**
     * @Route("/new", name="champs_libre_new", options={"expose"=true}, methods={"GET","POST"})
     */
    public function new(Request $request): Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $type = $this->typeRepository->find($data['type']);

            $champsLibre = new ChampsLibre();
            $champsLibre
                ->setlabel($data["label"])
                ->setType($type)
                ->settypage($data['typage'])
                ->setDefaultValue($data['valeur']);
            $em = $this->getDoctrine()->getManager();
            $em->persist($champsLibre);
            $em->flush();
            return new JsonResponse($data);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/editChampLibreApi", name="champLibre_edit_api", options={"expose"=true},  methods="GET|POST")
     */
    public function editChampLibreApi(Request $request): Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $champLibre = $this->champsLibreRepository->find($data);
            $json = $this->renderView('champs_libre/modalEditChampLibreContent.html.twig', [
                'champLibre' => $champLibre,
            ]);
            return new JsonResponse($json);
        }
        throw new NotFoundHttpException("404");
    }
    /**
     * @Route("/editChampsLibre", name="champsLibre_edit", options={"expose"=true},  methods="GET|POST")
     */
    public function editChampsLibre(Request $request): Response
    {
        dump(json_decode($request->getContent(), true));
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {


            $champLibre = $this->champsLibreRepository->find($data['champLibre']);
            $champLibre
                ->setLabel($data['label'])
                ->setTypage($data['typage'])
                ->setDefaultValue($data['valeur']);

            $em = $this->getDoctrine()->getManager();
            $em->flush();

            return new JsonResponse();
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/deleteChampsLibre", name="champs_libre_delete",options={"expose"=true}, methods={"GET","POST"})
     */
    public function deleteChampsLibre(Request $request): Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $champsLibre = $this->champsLibreRepository->find($data['champsLibre']);
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->remove($champsLibre);
            $entityManager->flush();
            return new JsonResponse();
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/supprimerType", name="type_delete",options={"expose"=true}, methods={"GET","POST"})
     */
    public function deleteType(Request $request): Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $type = $this->typeRepository->find($data['type']);
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->remove($type);
            $entityManager->flush();
            return new JsonResponse();
        }
        throw new NotFoundHttpException("404");
    }


    /**
     * @Route("/editTypeApi", name="type_edit_api", options={"expose"=true},  methods="GET|POST")
     */
    public function editTypeApi(Request $request): Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $type = $this->typeRepository->find($data);
            $json = $this->renderView('champs_Libre/modalEditTypeContent.html.twig', [
                'type' => $type,
                'category' => $this->categoryTypeRepository->getNoOne($type->getCategory()->getId())
            ]);
            return new JsonResponse($json);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/editType", name="type_edit", options={"expose"=true},  methods="GET|POST")
     */
    public function editType(Request $request): Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $category = $this->categoryTypeRepository->find($data['category']);
            $type = $this->typeRepository->find($data['type']);
            $type
                ->setLabel($data['label'])
                ->setCategory($category);
            $em = $this->getDoctrine()->getManager();
            $em->flush();
            return new JsonResponse();
        }
        throw new NotFoundHttpException("404");
    }
}
