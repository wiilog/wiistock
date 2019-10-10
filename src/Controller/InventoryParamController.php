<?php


namespace App\Controller;

use App\Repository\InventoryFrequencyRepository;
use App\Repository\UtilisateurRepository;
use App\Repository\InventoryCategoryRepository;
use App\Repository\ReferenceArticleRepository;

use App\Service\UserService;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

use App\Entity\Menu;
use App\Entity\InventoryCategory;
use App\Entity\InventoryFrequency;

/**
 * @Route("/parametres-inventaire")
 */
class InventoryParamController extends AbstractController
{
    /**
     * @var UserService
     */
    private $userService;

    /**
     * @var UtilisateurRepository
     */
    private $utilisateurRepository;

    /**
     * @var InventoryCategoryRepository
     */
    private $inventoryCategoryRepository;

    /**
     * @var InventoryFrequencyRepository
     */
    private $inventoryFrequencyRepository;

    /**
     * @var ReferenceArticleRepository
     */
    private $referenceArticleRepository;

    public function __construct(UserService $userService, UtilisateurRepository $utilisateurRepository, InventoryCategoryRepository $inventoryCategoryRepository, InventoryFrequencyRepository $inventoryFrequencyRepository, ReferenceArticleRepository $referenceArticleRepository)
    {
        $this->userService = $userService;
        $this->utilisateurRepository = $utilisateurRepository;
        $this->inventoryCategoryRepository = $inventoryCategoryRepository;
        $this->inventoryFrequencyRepository = $inventoryFrequencyRepository;
        $this->referenceArticleRepository = $referenceArticleRepository;
    }

    /**
     * @Route("/", name="inventaire_param")
     */
    public function index()
    {
        if (!$this->userService->hasRightFunction(Menu::PARAM)) {
            return $this->redirectToRoute('access_denied');
        }

        $frequences = $this->inventoryFrequencyRepository->findAll();

        return $this->render('inventaire_param/index.html.twig', [
            'frequencies' => $frequences
        ]);
    }

    /**
     * @Route("/api", name="invParam_api", options={"expose"=true}, methods="GET|POST")
     */
    public function api(Request $request): Response
    {
        if ($request->isXmlHttpRequest()) {
            if (!$this->userService->hasRightFunction(Menu::PARAM)) {
                return $this->redirectToRoute('access_denied');
            }

            $categories = $this->inventoryCategoryRepository->findAll();
            $rows = [];
            foreach ($categories as $category) {
                $url['edit'] = $this->generateUrl('category_api_edit', ['id' => $category->getId()]);

                $rows[] =
                    [
                        'Label' => $category->getLabel(),
                        'Frequence' => $category->getFrequency()->getLabel(),
                        'Permanent' => $category->getPermanent() ? 'oui' : 'non',
                        'Actions' => $category->getId(),
                        'Actions' => $this->renderView('inventaire_param/datatableCategoryRow.html.twig', [
                            'url' => $url,
                            'categoryId' => $category->getId(),
                        ]),
                    ];
            }
            $data['data'] = $rows;
            return new JsonResponse($data);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/creer", name="categorie_new", options={"expose"=true}, methods="GET|POST")
     */
    public function new(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::PARAM)) {
                return $this->redirectToRoute('access_denied');
            }

            $em = $this->getDoctrine()->getEntityManager();

            // on vérifie que le label n'est pas déjà utilisé
            $labelExist = $this->inventoryCategoryRepository->countByLabel($data['label']);

            if (!$labelExist) {
                $frequency = $this->inventoryFrequencyRepository->find($data['frequency']);
                $category = new InventoryCategory();
                $category
                    ->setLabel($data['label'])
                    ->setFrequency($frequency)
                    ->setPermanent($data['permanent']);

                $em->persist($category);
                $em->flush();

                return new JsonResponse();
            } else {
                return new JsonResponse(false);
            }
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/api-modifier", name="category_api_edit", options={"expose"=true}, methods="GET|POST")
     */
    public function apiEdit(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::PARAM)) {
                return $this->redirectToRoute('access_denied');
            }

            $category = $this->inventoryCategoryRepository->find($data['id']);
            $frequencies = $this->inventoryFrequencyRepository->findAll();

            $json = $this->renderView('inventaire_param/modalEditCategoryContent.html.twig', [
                'category' => $category,
                'frequencies' => $frequencies,
            ]);

            return new JsonResponse($json);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/modifier", name="category_edit",  options={"expose"=true}, methods="GET|POST")
     */
    public function edit(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::PARAM)) {
                return $this->redirectToRoute('access_denied');
            }

            $em = $this->getDoctrine()->getEntityManager();
            $category = $this->inventoryCategoryRepository->find($data['category']);
            $categoryLabel = $category->getLabel();

            // on vérifie que le label n'est pas déjà utilisé
            $labelExist = $this->inventoryCategoryRepository->countByLabelDiff($data['label'], $categoryLabel);

            if (!$labelExist) {
                $frequency = $this->inventoryFrequencyRepository->find($data['frequency']);
                $category
                    ->setLabel($data['label'])
                    ->setFrequency($frequency)
                    ->setPermanent($data['permanent']);

                $em->persist($category);
                $em->flush();

                return new JsonResponse();
            } else {
                return new JsonResponse(false);
            }
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/verification", name="category_check_delete", options={"expose"=true})
     */
    public function checkUserCanBeDeleted(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $categoryId = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::PARAM)) {
                return $this->redirectToRoute('access_denied');
            }

            $userIsUsed = $this->referenceArticleRepository->countByCategory($categoryId);

            if (!$userIsUsed) {
                $delete = true;
                $html = $this->renderView('inventaire_param/modalDeleteCategoryRight.html.twig');
            } else {
                $delete = false;
                $html = $this->renderView('inventaire_param/modalDeleteCategoryWrong.html.twig');
            }

            return new JsonResponse(['delete' => $delete, 'html' => $html]);
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/supprimer", name="category_delete", options={"expose"=true}, methods="GET|POST")
     */
    public function delete(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::PARAM)) {
                return $this->redirectToRoute('access_denied');
            }

            $category = $this->inventoryCategoryRepository->find($data['category']);

            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->remove($category);
            $entityManager->flush();
            return new JsonResponse();
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/creer-frequence", name="frequency_new", options={"expose"=true}, methods="GET|POST")
     */
    public function newFrequency(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::PARAM)) {
                return $this->redirectToRoute('access_denied');
            }

            $em = $this->getDoctrine()->getEntityManager();

            // on vérifie que le label n'est pas déjà utilisé
            $labelExist = $this->inventoryFrequencyRepository->countByLabel($data['label']);

            if (!$labelExist) {

                $frequency = new InventoryFrequency();
                $frequency
                    ->setLabel($data['label'])
                    ->setNbMonths($data['nbMonths']);

                $em->persist($frequency);
                $em->flush();
                return new JsonResponse();
            } else {
                return new JsonResponse(false);
            }
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/import-categories", name="update_category", options={"expose"=true}, methods="GET|POST")
     */
    public function updateCategory(Request $request): Response
    {
        if ($request->isXmlHttpRequest()) {
            $em = $this->getDoctrine()->getManager();

            $file = $request->files->get('file');

            $delimiters = array(
                ';' => 0,
                ',' => 0,
                "\t" => 0,
                "|" => 0
            );

            $fileDetectDelimiter = fopen($file, "r");
            $firstLine = fgets($fileDetectDelimiter);
            fclose($fileDetectDelimiter);
            foreach ($delimiters as $delimiter => &$count) {
                $count = count(str_getcsv($firstLine, $delimiter));
            }

            $delimiter = array_search(max($delimiters), $delimiters);

            $rows = [];

            $fileOpen = fopen($file->getPathname(), "r");
            while (($data = fgetcsv($fileOpen, 1000, $delimiter)) !== false) {
                $rows[] = array_map('utf8_encode', $data);
            }

            if (!file_exists('./uploads/log')) {
            	mkdir('./uploads/log', 0777, true);
			}
            $path = '../public/uploads/log/';
            $nameFile = uniqid() . ".txt";
            $uri = $path . $nameFile;
            $myFile = fopen($uri, "w");
            $success = true;

            foreach ($rows as $row)
            {
                $inventoryCategory = $this->inventoryCategoryRepository->findOneBy(['label' => $row[1]]);
                $refArticle = $this->referenceArticleRepository->findOneBy(['reference' => $row[0]]);
                if (!empty($refArticle) && !empty($inventoryCategory))
                {
                    $refArticle->setCategory($inventoryCategory);
                    $em->persist($refArticle);
                    $em->flush();
                }
                else
                {
                    $success = false;
                    if (empty($refArticle)) {
                        fwrite($myFile, "La référence " . "'" . $row[0] . "' n'existe pas. \n");
                    }
                    if (empty($inventoryCategory))
                    {
                        fwrite($myFile, "La catégorie " . "'" . $row[1] . "' n'existe pas. \n" );
                    }
                }
            }

            if ($success) {
            	$em->flush();
			} else {
            	fwrite($myFile, "\n\nVotre fichier .csv doit contenir 2 colonnes :\n" .
            	"- Une avec les références des articles de référence\n" .
            	"- Une avec les libellés des catégories d'inventaire.\n\n" .
            	"Il ne doit pas contenir de ligne d'en-tête.\n\n" .
            	"Merci de vérifier votre fichier.");
			}

            fclose($myFile);

            return new JsonResponse(['success' => $success, 'nameFile' => $nameFile]);
        }
    }

}
