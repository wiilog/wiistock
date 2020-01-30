<?php

namespace App\Controller;

use App\Entity\CategoryType;
use App\Entity\ReferenceArticle;
use App\Entity\Type;

use App\Repository\CategoryTypeRepository;
use App\Repository\ChampLibreRepository;
use App\Repository\ReferenceArticleRepository;
use App\Repository\TypeRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;
use App\Repository\FiltreRefRepository;
use App\Repository\ArticleRepository;

/**
 * Class TypeController
 * @package App\Controller
 * @Route("/type")
 */
class TypeController extends AbstractController
{
    /**
     * @var TypeRepository
     */
    private $typeRepository;

    /**
     * @var FiltreRefRepository
     */
    private $filtreRefRepository;

    /**
     * @var CategoryTypeRepository
     */
    private $categoryTypeRepository;

    /**
     * @var ReferenceArticleRepository
     */
    private $refArticleRepository;

    /**
     * @var ArticleRepository
     */
    private $articleRepository;

    /**
     * @var ChampLibreRepository
     */
    private $champLibreRepository;

	/**
	 * TypeController constructor.
	 * @param ArticleRepository $articleRepository
	 * @param FiltreRefRepository $filtreRefRepository
	 * @param TypeRepository $typeRepository
	 * @param CategoryTypeRepository $categoryTypeRepository
	 * @param ChampLibreRepository $champLibreRepository
	 * @param ReferenceArticleRepository $refArticleRepository
	 */
    public function __construct(ArticleRepository $articleRepository, FiltreRefRepository $filtreRefRepository, TypeRepository $typeRepository, CategoryTypeRepository $categoryTypeRepository, ChampLibreRepository $champLibreRepository, ReferenceArticleRepository $refArticleRepository)
    {
        $this->articleRepository = $articleRepository;
        $this->filtreRefRepository = $filtreRefRepository;
        $this->typeRepository = $typeRepository;
        $this->categoryTypeRepository = $categoryTypeRepository;
        $this->refArticleRepository = $refArticleRepository;
        $this->champLibreRepository = $champLibreRepository;
    }

    /**
     * @Route("/", name="type_show_select", options={"expose"=true}, methods={"GET","POST"})
     */
    public function showSelectInput(Request $request)
    {
        if ($request->isXmlHttpRequest() && $value = json_decode($request->getContent(), true)) {

            $isType = true;
            if (is_numeric($value['value'])) {
                $cl = $this->champLibreRepository->find(intval($value['value']));
                $options = $cl->getElements();
                $isType = false;
            } else {
                $options = $this->typeRepository->findByCategoryLabel(CategoryType::ARTICLE);
            }

            $view = $this->renderView('type/inputSelectTypes.html.twig', [
                'options' => $options,
                'isType' => $isType
            ]);
            return new JsonResponse($view);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/api", name="type_api", options={"expose"=true}, methods={"POST"})
     */
    public function api(Request $request): Response
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
                        "S'applique" => ($type->getCategory() ? $type->getCategory()->getLabel() : 'Non défini'),
                        'Actions' =>  $this->renderView('champ_libre/datatableTypeRow.html.twig', [
                            'urlChampLibre' => $url,
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
     * @Route("/creer", name="type_new", options={"expose"=true}, methods={"POST"})
     */
    public function new(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {

            // on vérifie que le nom du type n'est pas déjà utilisé
            $typeExist = $this->typeRepository->countByLabel($data['label']);

            if (!$typeExist) {
                if ($data['category'] === null) {
                    $category = $this->categoryTypeRepository->findoneBy(['label' => CategoryType::ARTICLE]);
                } else {
                    $category = $this->categoryTypeRepository->find($data['category']);
                }

                $type = new Type();
                $type
                    ->setlabel($data["label"])
                    ->setCategory($category);
                $em = $this->getDoctrine()->getManager();
                $em->persist($type);
                $em->flush();
                return new JsonResponse($data);
            } else {
                return new JsonResponse(false);
            }
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/supprimer", name="type_delete", options={"expose"=true}, methods={"GET","POST"})
     */
    public function delete(Request $request): Response
    {
        if ($data = json_decode($request->getContent(), true)) {
            $type = $this->typeRepository->find(intval($data['type']));
            $entityManager = $this->getDoctrine()->getManager();
            // si on a confirmé la suppression, on supprime les enregistrements liés
            if (isset($data['force'])) {
                $this->refArticleRepository->setTypeIdNull($type);
                $this->articleRepository->setTypeIdNull($type);
                foreach ($this->champLibreRepository->findByType($type) as $cl) {
                    $this->filtreRefRepository->deleteByChampLibre($cl);
                }
                $this->champLibreRepository->deleteByType($type);
                $entityManager->flush();
            } else {
                // sinon on vérifie qu'il n'est pas lié par des contraintes de clé étrangère
                $articlesRefExist = $this->refArticleRepository->countByType($type);
                $articlesExist = $this->articleRepository->countByType($type);
                $champsLibresExist = $this->champLibreRepository->countByType($type);
                $filters = 0;
                foreach ($this->champLibreRepository->findByType($type) as $cl) {
                    $filters += $this->filtreRefRepository->countByChampLibre($cl);
                }
                if ((int)$champsLibresExist + (int)$articlesExist + (int)$articlesRefExist > 0) {
                    $result = $this->renderView('champ_libre/modalDeleteTypeConfirm.html.twig', [
                        'champLibreFilter' => $filters !== 0
                    ]);
                    return new JsonResponse($result);
                }
            }

            if ($type !== null) $entityManager->remove($type);
            $entityManager->flush();
            $result = true;

            return new JsonResponse($result);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/api-modifier", name="type_api_edit", options={"expose"=true},  methods="GET|POST")
     */
    public function editApi(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $type = $this->typeRepository->find($data['id']);
            $json = $this->renderView('champ_libre/modalEditTypeContent.html.twig', [
                'type' => $type,
                'category' => $this->categoryTypeRepository->getNoOne($type->getCategory()->getId())
            ]);
            return new JsonResponse($json);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/modifier", name="type_edit", options={"expose"=true},  methods="GET|POST")
     */
    public function edit(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
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
