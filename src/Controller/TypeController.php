<?php


namespace App\Controller;

use App\Annotation\HasPermission;
use App\Entity\Action;
use App\Entity\CategoryType;
use App\Entity\Emplacement;
use App\Entity\FieldsParam;
use App\Entity\Menu;
use App\Entity\Statut;
use App\Entity\Type;
use WiiCommon\Helper\Stream;
use App\Service\GlobalParamService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class TypeController
 * @package App\Controller
 * @Route("/types")
 */
class TypeController extends AbstractController
{

    /**
     * @Route("/", name="types_index")
     * @HasPermission({Menu::PARAM, Action::DISPLAY_TYPE})
     */
    public function index(EntityManagerInterface $entityManager) {

        $categoryTypeRepository = $entityManager->getRepository(CategoryType::class);

        $categories = $categoryTypeRepository->findBy([], ['label' => 'ASC']);
        $categories = Stream::from($categories)->filter(fn(CategoryType $category) => $category->getLabel() !== CategoryType::SENSOR);

        $fieldsParamRepository = $entityManager->getRepository(FieldsParam::class);
        $dispatchEmergencies = $fieldsParamRepository->getElements(FieldsParam::ENTITY_CODE_DISPATCH, FieldsParam::FIELD_CODE_EMERGENCY);
        $handlingEmergencies = $fieldsParamRepository->getElements(FieldsParam::ENTITY_CODE_HANDLING, FieldsParam::FIELD_CODE_EMERGENCY);

        return $this->render("types/index.html.twig", [
            "categories" => $categories,
            "dispatchEmergencies" => $dispatchEmergencies,
            "handlingEmergencies" => $handlingEmergencies,
        ]);
    }

    /**
     * @Route("/api", name="types_param_api", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::PARAM, Action::DISPLAY_TYPE}, mode=HasPermission::IN_JSON)
     */
    public function api(EntityManagerInterface $entityManager): Response
    {

        $typeRepository = $entityManager->getRepository(Type::class);
        $types = $typeRepository->findAll();
        $rows = [];
        foreach ($types as $type) {
            $url['edit'] = $this->generateUrl('types_api_edit', ['id' => $type->getId()]);

            if($type->getNotificationsEnabled()) {
                if(!empty($type->getNotificationsEmergencies())) {
                    $notifications = "Activées si urgence";
                } else {
                    $notifications = "Activées";
                }
            } else {
                $notifications = "Désactivées";
            }

            $rows[] = [
                'Label' => $type->getLabel(),
                'Categorie' => $type->getCategory() ? $type->getCategory()->getLabel() : '',
                'Description' => $type->getDescription(),
                'sendMail' => $type->getCategory() && ($type->getCategory()->getLabel() === CategoryType::DEMANDE_LIVRAISON)
                    ? ($type->getSendMail() ? 'Oui' : 'Non')
                    : '',
                'notifications' => $notifications,
                'Actions' => $this->renderView('types/datatableTypeRow.html.twig', [
                    'url' => $url,
                    'typeId' => $type->getId(),
                    'type' => $type
                ]),
            ];
        }
        $data['data'] = $rows;
        return new JsonResponse($data);
    }

    /**
     * @Route("/creer", name="types_new", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::PARAM, Action::EDIT}, mode=HasPermission::IN_JSON)
     */
    public function new(Request $request,
                        GlobalParamService $globalParamService,
                        EntityManagerInterface $entityManager): Response {
        if ($data = json_decode($request->getContent(), true)) {
            $em = $this->getDoctrine()->getManager();

            $typeRepository = $entityManager->getRepository(Type::class);
            $categoryTypeRepository = $entityManager->getRepository(CategoryType::class);
            $emplacementRepository = $entityManager->getRepository(Emplacement::class);

            // on vérifie que le label n'est pas déjà utilisé
            $labelExist = $typeRepository->countByLabelAndCategory($data['label'], $data['category']);


            if (!$labelExist) {
                $type = new Type();

                $globalParamService->treatTypeCreationOrEdition($type, $categoryTypeRepository, $emplacementRepository, $data);

                $em->persist($type);
                $em->flush();

				return new JsonResponse([
					'success' => true,
					'msg' => 'Le type <strong>' . $data['label'] . '</strong> a bien été créé.'
				]);
            } else {
				return new JsonResponse([
					'success' => false,
					'msg' => 'Le type <strong>' . $data['label'] . '</strong> existe déjà pour cette catégorie. Veuillez en choisir un autre.'
				]);
            }
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/api-modifier", name="types_api_edit", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::PARAM, Action::EDIT}, mode=HasPermission::IN_JSON)
     */
    public function apiEdit(Request $request,
                            EntityManagerInterface $entityManager): Response {

        if ($data = json_decode($request->getContent(), true)) {
            $type = $entityManager->find(Type::class, $data['id']);
            $categoryTypeRepository = $entityManager->getRepository(CategoryType::class);

            $categories = $categoryTypeRepository->findBy([], ['label' => 'ASC']);
            $categories = Stream::from($categories)->filter(fn(CategoryType $category) => $category->getLabel() !== CategoryType::SENSOR);

            $fieldsParamRepository = $entityManager->getRepository(FieldsParam::class);
            $dispatchEmergencies = $fieldsParamRepository->getElements(FieldsParam::ENTITY_CODE_DISPATCH, FieldsParam::FIELD_CODE_EMERGENCY);
            $handlingEmergencies = $fieldsParamRepository->getElements(FieldsParam::ENTITY_CODE_HANDLING, FieldsParam::FIELD_CODE_EMERGENCY);

            return $this->json($this->renderView('types/modalEditTypeContent.html.twig', [
                "type" => $type,
                "categories" => $categories,
                "dispatchEmergencies" => $dispatchEmergencies,
                "handlingEmergencies" => $handlingEmergencies,
            ]));
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/modifier", name="types_edit",  options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::PARAM, Action::EDIT}, mode=HasPermission::IN_JSON)
     */
    public function edit(Request $request,
                         GlobalParamService $globalParamService,
                         EntityManagerInterface $entityManager): Response {

        if ($data = json_decode($request->getContent(), true)) {
            $categoryTypeRepository = $entityManager->getRepository(CategoryType::class);
            $typeRepository = $entityManager->getRepository(Type::class);
            $emplacementRepository = $entityManager->getRepository(Emplacement::class);

            $type = $typeRepository->find($data['type']);
            $typeLabel = $type->getLabel();

            // on vérifie que le label n'est pas déjà utilisé
            $labelExist = $typeRepository->countByLabelDiff($data['label'], $typeLabel, $data['category']);

            if (!$labelExist) {
                $globalParamService->treatTypeCreationOrEdition($type, $categoryTypeRepository, $emplacementRepository, $data);

                $entityManager->persist($type);
                $entityManager->flush();

                return new JsonResponse([
                	'success' => true,
					'msg' => 'Le type <strong>' . $typeLabel . '</strong> a bien été modifié.'
				]);
            } else {
                return new JsonResponse([
                	'success' => false,
					'msg' => 'Le type <strong>' . $typeLabel . '</strong> existe déjà pour cette catégorie. Veuillez en choisir un autre.'
				]);
            }
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/verification", name="types_check_delete", options={"expose"=true}, condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::PARAM, Action::DELETE}, mode=HasPermission::IN_JSON)
     */
    public function checkTypeCanBeDeleted(Request $request,
                                          EntityManagerInterface $entityManager): Response {

        if ($typeId = json_decode($request->getContent(), true)) {
            $typeRepository = $entityManager->getRepository(Type::class);
            $statusRepository = $entityManager->getRepository(Statut::class);

            $canDelete = !$typeRepository->isTypeUsed($typeId);
            $usedStatuses = Stream::from($statusRepository->findBy(['type' => $typeId]))
                ->filter(function(Statut $statut) use ($statusRepository) {
                    $count = $statusRepository->countUsedById($statut->getId());
                    return $count > 0;
                })
                ->toArray();

            $html = $canDelete && empty($usedStatuses)
                ? $this->renderView('types/modalDeleteTypeRight.html.twig')
                : $this->renderView('types/modalDeleteTypeWrong.html.twig');

            return new JsonResponse(['delete' => $canDelete, 'html' => $html]);
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/supprimer", name="types_delete", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::PARAM, Action::DELETE}, mode=HasPermission::IN_JSON)
     */
    public function delete(Request $request,
                           EntityManagerInterface $entityManager): Response
    {
        if ($data = json_decode($request->getContent(), true)) {
            $type = $entityManager->find(Type::class, $data['type']);
            $typeLabel = $type->getLabel();

            $entityManager->remove($type);
            $entityManager->flush();
            return new JsonResponse([
                'success' => true,
                'msg' => 'Le type <strong>' . $typeLabel . '</strong> a bien été supprimé.'
            ]);
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/autocomplete-unique-types", name="get_unique_types", options={"expose"=true}, condition="request.isXmlHttpRequest()")
     */
    public function getUniqueTypes(Request $request, EntityManagerInterface $entityManager) {

        $types = $request->query->get('types');
        $types = array_filter($types ?? [], fn($type) => $type !== 'all');
        $search = $request->query->get('term');

        $typeRepository = $entityManager->getRepository(Type::class);
        $uniqueTypes = $typeRepository->getUniqueTypes($types, $search);

        if(empty($types)) {
            $allTypes = [
                'id' => 'all',
                'text' => 'Tous les types'
            ];

            array_unshift($uniqueTypes, $allTypes);
        }

        return $this->json([
            'results' => $uniqueTypes
        ]);
    }
}
