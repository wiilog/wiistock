<?php


namespace App\Controller;

use App\Annotation\HasPermission;
use App\Entity\Action;
use App\Entity\ReferenceArticle;
use App\Service\UserService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
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

    public function __construct(UserService $userService) {
        $this->userService = $userService;
    }

    /**
     * @Route("/", name="inventaire_param_index")
     */
    public function index(EntityManagerInterface $entityManager)
    {
        $inventoryFrequencyRepository = $entityManager->getRepository(InventoryFrequency::class);
        $frequences = $inventoryFrequencyRepository->findAll();

        return $this->render('inventaire_param/index.html.twig', [
            'frequencies' => $frequences
        ]);
    }

    /**
     * @Route("/api", name="invParam_api", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     */
    public function api(): Response
    {
        $inventoryCategoryRepository = $this->getDoctrine()->getRepository(InventoryCategory::class);
        /** @var $category InventoryCategory */
        $categories = $inventoryCategoryRepository->findAll();
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

    /**
     * @Route("/creer", name="categorie_new", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::PARAM, Action::EDIT}, mode=HasPermission::IN_JSON)
     */
    public function new(Request $request,
                        EntityManagerInterface $entityManager): Response
    {
        if ($data = json_decode($request->getContent(), true)) {
            $inventoryCategoryRepository = $entityManager->getRepository(InventoryCategory::class);
            $inventoryFrequencyRepository = $entityManager->getRepository(InventoryFrequency::class);

            // on vérifie que le label n'est pas déjà utilisé
            $labelExist = $inventoryCategoryRepository->countByLabel($data['label']);

            if (!$labelExist) {
                $frequency = $inventoryFrequencyRepository->find($data['frequency']);
                $category = new InventoryCategory();
                $category
                    ->setLabel($data['label'])
                    ->setFrequency($frequency)
                    ->setPermanent($data['permanent']);

                $entityManager->persist($category);
                $entityManager->flush();

                return new JsonResponse([
                    'success' => true,
                    'msg' => 'La catégorie a bien été créée.'
                ]);
            } else {
                return new JsonResponse([
                    'success' => false,
                    'msg' => 'Ce label de catégorie existe déjà. Veuillez en choisir un autre.'
                ]);
            }
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/api-modifier", name="category_api_edit", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::PARAM, Action::EDIT}, mode=HasPermission::IN_JSON)
     */
    public function apiEdit(Request $request,
                            EntityManagerInterface $entityManager): Response
    {
        if ($data = json_decode($request->getContent(), true)) {
            $inventoryCategoryRepository = $entityManager->getRepository(InventoryCategory::class);
            $inventoryFrequencyRepository = $entityManager->getRepository(InventoryFrequency::class);

            $category = $inventoryCategoryRepository->find($data['id']);
            $frequencies = $inventoryFrequencyRepository->findAll();

            $json = $this->renderView('inventaire_param/modalEditCategoryContent.html.twig', [
                'category' => $category,
                'frequencies' => $frequencies,
            ]);

            return new JsonResponse($json);
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/modifier", name="category_edit",  options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::PARAM, Action::EDIT}, mode=HasPermission::IN_JSON)
     */
    public function edit(Request $request,
                         EntityManagerInterface $entityManager): Response
    {
        if ($data = json_decode($request->getContent(), true)) {
            $inventoryCategoryRepository = $entityManager->getRepository(InventoryCategory::class);
            $inventoryFrequencyRepository = $entityManager->getRepository(InventoryFrequency::class);

            $category = $inventoryCategoryRepository->find($data['category']);
            $categoryLabel = $category->getLabel();

            // on vérifie que le label n'est pas déjà utilisé
            $labelExist = $inventoryCategoryRepository->countByLabelDiff($data['label'], $categoryLabel);

            if (!$labelExist) {
                $frequency = $inventoryFrequencyRepository->find($data['frequency']);
                $category
                    ->setLabel($data['label'])
                    ->setFrequency($frequency)
                    ->setPermanent($data['permanent']);

                $entityManager->persist($category);
                $entityManager->flush();

                return new JsonResponse([
                    'success' => true,
                    'msg' => 'La catégorie a bien été modifiée'
                ]);
            } else {
                return new JsonResponse([
                    'success' => false,
                    'msg' => 'Ce label de catégorie existe déjà. Veuillez en choisir un autre.'
                ]);
            }
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/verification", name="category_check_delete", options={"expose"=true}, condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::PARAM, Action::DELETE}, mode=HasPermission::IN_JSON)
     */
    public function checkUserCanBeDeleted(Request $request, EntityManagerInterface $entityManager): Response
    {
        if ($categoryId = json_decode($request->getContent(), true)) {
            $referenceArticleRepository = $entityManager->getRepository(ReferenceArticle::class);
            $userIsUsed = $referenceArticleRepository->countByCategory($categoryId);

            if (!$userIsUsed) {
                $delete = true;
                $html = $this->renderView('inventaire_param/modalDeleteCategoryRight.html.twig');
            } else {
                $delete = false;
                $html = $this->renderView('inventaire_param/modalDeleteCategoryWrong.html.twig');
            }

            return new JsonResponse(['delete' => $delete, 'html' => $html]);
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/supprimer", name="category_delete", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::PARAM, Action::DELETE}, mode=HasPermission::IN_JSON)
     */
    public function delete(Request $request): Response
    {
        if ($data = json_decode($request->getContent(), true)) {
            $entityManager = $this->getDoctrine()->getManager();
            $inventoryCategoryRepository = $entityManager->getRepository(InventoryCategory::class);

            $category = $inventoryCategoryRepository->find($data['category']);

            $entityManager->remove($category);
            $entityManager->flush();
            return new JsonResponse();
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/creer-frequence", name="frequency_new", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::PARAM, Action::EDIT}, mode=HasPermission::IN_JSON)
     */
    public function newFrequency(Request $request,
                                 EntityManagerInterface $entityManager): Response
    {
        if ($data = json_decode($request->getContent(), true)) {
            $inventoryFrequencyRepository = $entityManager->getRepository(InventoryFrequency::class);

            // on vérifie que le label n'est pas déjà utilisé
            $labelExist = $inventoryFrequencyRepository->countByLabel($data['label']);

            if (!$labelExist) {

                $frequency = new InventoryFrequency();
                $frequency
                    ->setLabel($data['label'])
                    ->setNbMonths($data['nbMonths']);

                $entityManager->persist($frequency);
                $entityManager->flush();
                return new JsonResponse([
                    'success' => true,
                    'msg' => 'La fréquence a bien été ajoutée.'
                ]);
            } else {
                return new JsonResponse([
                    'success' => false,
                    'msg' => 'Ce label de fréquence existe déjà. Veuillez en choisir un autre.'
                ]);
            }
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/frequences/voir", name="invParamFrequencies_api", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     */
    public function apiFrequencies(EntityManagerInterface $entityManager): Response
    {
        $inventoryFrequencyRepository = $entityManager->getRepository(InventoryFrequency::class);
        $frequencies = $inventoryFrequencyRepository->findAll();
        $rows = [];
        foreach ($frequencies as $frequency) {
            $url['edit'] = $this->generateUrl('frequency_api_edit', ['id' => $frequency->getId()]);

            $rows[] =
                [
                    'Label' => $frequency->getLabel(),
                    'NbMonths' => $frequency->getNbMonths() . ' mois',
                    'Actions' => $this->renderView('inventaire_param/datatableFrequencyRow.html.twig', [
                        'url' => $url,
                        'frequencyId' => $frequency->getId(),
                    ]),
                ];
        }
        $data['data'] = $rows;
        return new JsonResponse($data);
    }

    /**
     * @Route("/apiFrequence-modifier", name="frequency_api_edit", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::PARAM, Action::EDIT}, mode=HasPermission::IN_JSON)
     */
    public function apiEditFrequency(Request $request,
                                     EntityManagerInterface $entityManager): Response
    {
        if ($data = json_decode($request->getContent(), true)) {
            $inventoryFrequencyRepository = $entityManager->getRepository(InventoryFrequency::class);
            $frequency = $inventoryFrequencyRepository->find($data['id']);

            $json = $this->renderView('inventaire_param/modalEditFrequencyContent.html.twig', [
                'frequency' => $frequency,
            ]);

            return new JsonResponse($json);
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/frequence-modifier", name="frequency_edit",  options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::PARAM, Action::EDIT}, mode=HasPermission::IN_JSON)
     */
    public function editFrequency(Request $request,
                                  EntityManagerInterface $entityManager): Response
    {
        if ($data = json_decode($request->getContent(), true)) {
            $inventoryFrequencyRepository = $entityManager->getRepository(InventoryFrequency::class);
            $frequency = $inventoryFrequencyRepository->find($data['frequency']);
            $frequencyLabel = $frequency->getLabel();

            // on vérifie que le label n'est pas déjà utilisé
            $labelExist = $inventoryFrequencyRepository->countByLabelDiff($data['label'], $frequencyLabel);

            if (!$labelExist) {
                $frequency = $inventoryFrequencyRepository->find($data['frequency']);
                $frequency
                    ->setLabel($data['label'])
                    ->setNbMonths($data['nbMonths']);

                $entityManager->persist($frequency);
                $entityManager->flush();

                return new JsonResponse([
                    'success' => true,
                    'msg' => 'La fréquence a bien été modifiée.'
                ]);
            } else {
                return new JsonResponse([
                    'success' => false,
                    'msg' => 'Ce label de fréquence existe déjà. Veuillez en choisir un autre.'
                ]);
            }
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/frequence-verification", name="frequency_check_delete", options={"expose"=true}, condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::PARAM, Action::DELETE}, mode=HasPermission::IN_JSON)
     */
    public function checkFrequencyCanBeDeleted(Request $request,
                                               EntityManagerInterface $entityManager): Response
    {
        if ($frequencyId = json_decode($request->getContent(), true)) {
            $inventoryCategoryRepository = $entityManager->getRepository(InventoryCategory::class);

            $frequencyIsUsed = $inventoryCategoryRepository->countByFrequency($frequencyId);

            if (!$frequencyIsUsed) {
                $delete = true;
                $html = $this->renderView('inventaire_param/modalDeleteFrequencyRight.html.twig');
            } else {
                $delete = false;
                $html = $this->renderView('inventaire_param/modalDeleteFrequencyWrong.html.twig');
            }

            return new JsonResponse(['delete' => $delete, 'html' => $html]);
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/frequence-supprimer", name="frequency_delete", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::PARAM, Action::DELETE}, mode=HasPermission::IN_JSON)
     */
    public function deleteFrequency(Request $request,
                                    EntityManagerInterface $entityManager): Response
    {
        if ($data = json_decode($request->getContent(), true)) {
            $inventoryFrequencyRepository = $entityManager->getRepository(InventoryFrequency::class);
            $frequency = $inventoryFrequencyRepository->find($data['category']);

            $entityManager->remove($frequency);
            $entityManager->flush();

            return new JsonResponse();
        }
        throw new BadRequestHttpException();
    }


    /**
     * @Route("/import-categories", name="update_category", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     */
    public function updateCategory(Request $request): Response
    {
        $entityManager = $this->getDoctrine()->getManager();
        $referenceArticleRepository = $entityManager->getRepository(ReferenceArticle::class);
        $inventoryCategoryRepository = $entityManager->getRepository(InventoryCategory::class);

        $file = $request->files->get('file');

        $delimiters = [
            ';' => 0,
            ',' => 0,
            "\t" => 0,
            "|" => 0
        ];

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
            $rows[] = $data;
        }

        if (!file_exists('./uploads/log')) {
            mkdir('./uploads/log', 0777, true);
        }
        $path = '../public/uploads/log/';
        $nameFile = uniqid() . ".txt";
        $uri = $path . $nameFile;
        $myFile = fopen($uri, "w");
        $success = true;

        foreach ($rows as $row) {
            $inventoryCategory = $inventoryCategoryRepository->findOneBy(['label' => $row[1]]);
            $refArticle = $referenceArticleRepository->findOneBy(['reference' => $row[0]]);

            if (!empty($refArticle) && !empty($inventoryCategory)) {
                $refArticle->setCategory($inventoryCategory);
                $entityManager->persist($refArticle);
                $entityManager->flush();
            } else {
                $success = false;
                if (empty($refArticle)) {
                    fwrite($myFile, "La référence " . "'" . $row[0] . "' n'existe pas. \n");
                }
                if (empty($inventoryCategory)) {
                    fwrite($myFile, "La catégorie " . "'" . $row[1] . "' n'existe pas. \n");
                }
            }
        }

        if ($success) {
            $entityManager->flush();
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

    /**
     * @Route("/autocomplete-frequencies", name="get_frequencies", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     */
    public function getFrequencies(Request $request,
                                   EntityManagerInterface $entityManager): Response
    {
        $search = $request->query->get('term');

        $frequencyRepository = $entityManager->getRepository(InventoryFrequency::class);
        $results = $frequencyRepository->getLabelBySearch($search);

        return new JsonResponse(['results' => $results]);
    }
}
