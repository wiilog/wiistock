<?php

namespace App\Controller;

use App\Entity\ChampLibre;

use App\Repository\ChampLibreRepository;
use App\Repository\ReferenceArticleRepository;
use App\Repository\TypeRepository;
use App\Repository\CategoryTypeRepository;
use App\Repository\CategorieCLRepository;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * @Route("/champ-libre")
 */
class ChampLibreController extends AbstractController
{
    /**
     * @var ChampLibreRepository
     */
    private $champLibreRepository;

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

    /**
     * @var CategorieCLRepository
     */
    private $categorieCLRepository;

    public function __construct(CategorieCLRepository $categorieCLRepository, CategoryTypeRepository $categoryTypeRepository, ChampLibreRepository $champsLibreRepository, TypeRepository $typeRepository, ReferenceArticleRepository $refArticleRepository)
    {
        $this->champLibreRepository = $champsLibreRepository;
        $this->typeRepository = $typeRepository;
        $this->categoryTypeRepository = $categoryTypeRepository;
        $this->refArticleRepository = $refArticleRepository;
        $this->categorieCLRepository = $categorieCLRepository;
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
            $champsLibres = $this->champLibreRepository->findByType($id);
            $rows = [];
            foreach ($champsLibres as $champLibre) {

                if ($champLibre->getTypage() === ChampLibre::TYPE_BOOL) {
                    $typageCLFr = 'Oui/Non';
                } elseif ($champLibre->getTypage() === ChampLibre::TYPE_NUMBER) {
                    $typageCLFr = 'Nombre';
                } elseif ($champLibre->getTypage() === ChampLibre::TYPE_TEXT) {
                    $typageCLFr = 'Texte';
                } elseif ($champLibre->getTypage() === ChampLibre::TYPE_LIST) {
                    $typageCLFr = 'Liste';
                } elseif ($champLibre->getTypage() === ChampLibre::TYPE_DATE) {
                    $typageCLFr = 'Date';
                } elseif ($champLibre->getTypage() === ChampLibre::TYPE_DATETIME) {
                    $typageCLFr = 'Date et heure';
                } elseif ($champLibre->getTypage() === ChampLibre::TYPE_LIST_MULTIPLE) {
                    $typageCLFr = 'Liste multiple';
                } else {
                    $typageCLFr = '';
                }

                $rows[] =
                    [
                        'id' => ($champLibre->getId() ? $champLibre->getId() : 'Non défini'),
                        'Label' => ($champLibre->getLabel() ? $champLibre->getLabel() : 'Non défini'),
                        "S'applique à" => ($champLibre->getCategorieCL() ? $champLibre->getCategorieCL()->getLabel() : ''),
                        'Typage' => $typageCLFr,
                        'Obligatoire à la création' => ($champLibre->getRequiredCreate() ? "oui" : "non"),
                        'Obligatoire à la modification' => ($champLibre->getRequiredEdit() ? "oui" : "non"),
                        'Valeur par défaut' => ($champLibre->getTypage() == ChampLibre::TYPE_BOOL
                            ? ($champLibre->getDefaultValue() ? 'oui' : 'non')
                            : ($champLibre->getDefaultValue() ?? 'Non défini')),
                        'Elements' => $champLibre->getTypage() == ChampLibre::TYPE_LIST || $champLibre->getTypage() == ChampLibre::TYPE_LIST_MULTIPLE ? $this->renderView('champ_libre/champLibreElems.html.twig', ['elems' => $champLibre->getElements()]) : '',
                        'Actions' => $this->renderView('champ_libre/datatableChampLibreRow.html.twig', ['idChampLibre' => $champLibre->getId()]),
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
        $typages = ChampLibre::TYPAGE;
        return $this->render('champ_libre/show.html.twig', [
            'type' => $this->typeRepository->find($id),
            'categoriesCL' => $this->categorieCLRepository->findAll(),
            'typages' => $typages,
        ]);
    }

    /**
     * @Route("/new", name="champ_libre_new", options={"expose"=true}, methods={"GET","POST"})
     */
    public function new(Request $request): Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {

            // on vérifie que le nom du champ libre n'est pas déjà utilisé
            $champLibreExist = $this->champLibreRepository->countByLabel($data['label']);
            if (!$champLibreExist) {
                $type = $this->typeRepository->find($data['type']);
                $categorieCL = $this->categorieCLRepository->find($data['categorieCL']);
                $champLibre = new ChampLibre();
                $champLibre
                    ->setlabel($data['label'])
                    ->setCategorieCL($categorieCL)
                    ->setRequiredCreate($data['requiredCreate'])
                    ->setRequiredEdit($data['requiredEdit'])
                    ->setType($type)
                    ->settypage($data['typage']);

                if ($champLibre->getTypage() === 'list') {
                    $champLibre
                        ->setElements(array_filter(explode(';', $data['elem'])))
                        ->setDefaultValue(null);
                } else if ($champLibre->getTypage() === ChampLibre::TYPE_LIST_MULTIPLE) {
                    $champLibre
                        ->setElements($data['Elements'])
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
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $champLibre = $this->champLibreRepository->find($data['id']);
            $typages = ChampLibre::TYPAGE;

            $json = $this->renderView('champ_libre/modalEditChampLibreContent.html.twig', [
                'champLibre' => $champLibre,
                'typageCL' => ChampLibre::TYPAGE_ARR[$champLibre->getTypage()],
                'categoriesCL' => $this->categorieCLRepository->findAll(),
                'typages' => $typages,
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
            $categorieCL = $this->categorieCLRepository->find($data['categorieCL']);
            $champLibre = $this->champLibreRepository->find($data['champLibre']);

            $champLibre
                ->setLabel($data['label'])
                ->setCategorieCL($categorieCL)
                ->setRequiredCreate($data['requiredCreate'])
                ->setRequiredEdit($data['requiredEdit'])
                ->setTypage($data['typage']);

            if ($champLibre->getTypage() === 'list') {
                $champLibre
                    ->setElements(array_filter(explode(';', $data['elem'])))
                    ->setDefaultValue(null);
            } else if ($champLibre->getTypage() === ChampLibre::TYPE_LIST_MULTIPLE) {
                $champLibre
                    ->setElements($data['Elements'])
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
            $champLibre = $this->champLibreRepository->find($data['champLibre']);
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->remove($champLibre);
            $entityManager->flush();

            return new JsonResponse();
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/display-require-champ", name="display_required_champs_libres", options={"expose"=true},  methods="GET|POST")
     */
    public function displayRequiredChampsLibres(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (array_key_exists('create', $data)) {
                $type = $this->typeRepository->find($data['create']);
                $champsLibres = $this->champLibreRepository->getByTypeAndRequiredCreate($type);
            } else if (array_key_exists('edit', $data)) {
                $type = $this->typeRepository->find($data['edit']);
                $champsLibres = $this->champLibreRepository->getByTypeAndRequiredEdit($type);
            } else {
                $json = false;
                return new JsonResponse($json);
            }
            $json = [];
            foreach ($champsLibres as $champLibre) {
                $json[] = $champLibre['id'];
            }
            return new JsonResponse($json);
        }
        throw new NotFoundHttpException('404');
    }
}
