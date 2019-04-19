<?php

namespace App\Controller;

use App\Entity\ChampsLibre;
use App\Entity\Type;
use App\Repository\ChampsLibreRepository;
use App\Repository\ReferenceArticleRepository;
use App\Repository\TypeRepository;
use App\Repository\CategoryTypeRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * @Route("/champ-libre")
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
     * @var ReferenceArticleRepository
     */
    private $refArticleRepository;

    /**
     * @var CategoryTypeRepository
     */
    private $categoryTypeRepository;

    public function __construct(CategoryTypeRepository $categoryTypeRepository, ChampsLibreRepository $champsLibreRepository, TypeRepository $typeRepository, ReferenceArticleRepository $refArticleRepository)
    {
        $this->champsLibreRepository = $champsLibreRepository;
        $this->typeRepository = $typeRepository;
        $this->categoryTypeRepository = $categoryTypeRepository;
        $this->refArticleRepository = $refArticleRepository;
    }

    /**
     * @Route("/", name="champ_libre_index", methods={"GET"})
     */
    public function index(): Response
    {
        return $this->render('champ_libre/index.html.twig', [
            'category' => $this->categoryTypeRepository->findAll(),
        ]);
    }

    /**
     * @Route("/api/{id}", name="champ_libre_api", options={"expose"=true}, methods={"POST"})
     */
    public function api(Request $request, $id): Response
    {
        if ($request->isXmlHttpRequest()) { //Si la requête est de type Xml
            $champsLibres = $this->champsLibreRepository->getByType($this->typeRepository->find($id));
            $rows = [];
            foreach ($champsLibres as $champsLibre) {
                $rows[] =
                    [
                        'id' => ($champsLibre->getId() ? $champsLibre->getId() : 'Non défini'),
                        'Label' => ($champsLibre->getLabel() ? $champsLibre->getLabel() : 'Non défini'),
                        'Typage' => ($champsLibre->getTypage() ? $champsLibre->getTypage() : 'Non défini'),
                        'Valeur par défaut' => ($champsLibre->getDefaultValue() ? $champsLibre->getDefaultValue() : 'Non défini'),
                        'Elements' => $this->renderView('champ_libre/champLibreElems.html.twig', ['elems' => $champsLibre->getElements()]),
                        'Actions' => $this->renderView('champ_libre/datatableChampsLibreRow.html.twig', ['idChampsLibre' => $champsLibre->getId()]),
                    ];
            }
            $data['data'] = $rows;

            return new JsonResponse($data);
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/voir/{id}", name="champs_libre_show", methods={"GET","POST"})
     */
    public function show(Request $request, $id): Response
    {
        return $this->render('champ_libre/show.html.twig', [
            'type' => $this->typeRepository->find($id),
        ]);
    }

    /**
     * @Route("/new", name="champ_libre_new", options={"expose"=true}, methods={"GET","POST"})
     */
    public function new(Request $request): Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {

            // on vérifie que le nom du champ libre n'est pas déjà utilisé
            $champLibreExist = $this->champsLibreRepository->countByLabel($data['label']);

            if (!$champLibreExist) {

                $type = $this->typeRepository->find($data['type']);
                $champLibre = new ChampsLibre();
                $champLibre
                    ->setlabel($data['label'])
                    ->setType($type)
                    ->settypage($data['typage']);
                if ($champLibre->getTypage() === 'list') {
                    $champLibre
                        ->setElements(array_filter(explode(';', $data['elem'])))
                        ->setDefaultValue(null);
                } else {
                    $champLibre
                        ->setElements(null)
                        ->setDefaultValue($data['valeur']);
                }
                $em = $this->getDoctrine()->getManager();
                $em->persist($champLibre);
                $em->flush();

                return new JsonResponse($data);
            } else {
                return new JsonResponse(false);
            }
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/api-modifier", name="champ_libre_api_edit", options={"expose"=true},  methods="GET|POST")
     */
    public function editApi(Request $request): Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $champLibre = $this->champsLibreRepository->find($data);
            $json = $this->renderView('champ_libre/modalEditChampLibreContent.html.twig', [
                'champLibre' => $champLibre,
            ]);

            return new JsonResponse($json);
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/modifier", name="champ_libre_edit", options={"expose"=true},  methods="GET|POST")
     */
    public function edit(Request $request): Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $champLibre = $this->champsLibreRepository->find($data['champLibre']);
            $champLibre
                ->setLabel($data['label'])
                ->setTypage($data['typage']);
            if ($champLibre->getTypage() === 'list') {
                $champLibre
                    ->setElements(array_filter(explode(';', $data['elem'])))
                    ->setDefaultValue(null);
            } else {
                $champLibre
                    ->setElements(null)
                    ->setDefaultValue($data['valeur']);
            }
            $em = $this->getDoctrine()->getManager();
            $em->flush();

            return new JsonResponse();
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/delete", name="champ_libre_delete",options={"expose"=true}, methods={"GET","POST"})
     */
    public function delete(Request $request): Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $champsLibre = $this->champsLibreRepository->find($data['champsLibre']);
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->remove($champsLibre);
            $entityManager->flush();

            return new JsonResponse();
        }
        throw new NotFoundHttpException('404');
    }
}
