<?php


namespace App\Controller;

use App\Entity\Action;
use App\Entity\FieldsParam;
use App\Entity\Menu;
use App\Service\UserService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/champs-fixes")
 */
class FieldsParamController extends AbstractController
{

	/**
	 * @Route("/", name="fields_param_index")
	 * @param UserService $userService
	 * @return RedirectResponse|Response
	 */
    public function index(UserService $userService)
    {
        if (!$userService->hasRightFunction(Menu::PARAM, Action::DISPLAY_CF)) {
            return $this->redirectToRoute('access_denied');
        }

        return $this->render('fields_param/index.html.twig', [
            'tables' => [
                FieldsParam::ENTITY_CODE_ARRIVAGE,
                FieldsParam::ENTITY_CODE_RECEPTION,
                FieldsParam::ENTITY_CODE_DISPATCH
            ]
        ]);
    }

    /**
     * @Route("/api/{entityCode}", name="fields_param_api", options={"expose"=true}, methods="GET|POST")
     * @param Request $request
     * @param UserService $userService
     * @param EntityManagerInterface $entityManager
     * @param string $entityCode
     * @return Response
     */
    public function api(Request $request,
                        UserService $userService,
                        EntityManagerInterface $entityManager,
                        string $entityCode): Response
    {
        if ($request->isXmlHttpRequest()) {
            if (!$userService->hasRightFunction(Menu::PARAM, Action::DISPLAY_CF)) {
                return $this->redirectToRoute('access_denied');
            }

            $fieldsParamRepository = $entityManager->getRepository(FieldsParam::class);
            $arrayFields = $fieldsParamRepository->findByEntityForEntity($entityCode);
            $rows = [];
            foreach ($arrayFields as $field) {
                $url['edit'] = $this->generateUrl('fields_api_edit', ['id' => $field->getId()]);

                $rows[] =
                    [
                        'fieldCode' => $field->getFieldLabel(),
						'displayed' => $field->getDisplayed() ? 'oui' : 'non',
						'mustCreate' => $field->getMustToCreate() ? 'oui' : 'non',
						'mustEdit' => $field->getMustToModify() ? 'oui' : 'non',
						'Actions' => $this->renderView('fields_param/datatableFieldsRow.html.twig', [
                            'url' => $url,
                            'fieldId' => $field->getId(),
                        ]),
                    ];
            }
            $data['data'] = $rows;
            return new JsonResponse($data);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/api-modifier", name="fields_api_edit", options={"expose"=true}, methods="GET|POST")
     * @param Request $request
     * @param UserService $userService
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    public function apiEdit(Request $request,
                            UserService $userService,
                            EntityManagerInterface $entityManager): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$userService->hasRightFunction(Menu::PARAM, Action::EDIT)) {
                return $this->redirectToRoute('access_denied');
            }
            $fieldsParamRepository = $entityManager->getRepository(FieldsParam::class);
            $field = $fieldsParamRepository->find($data['id']);

            $json = $this->renderView('fields_param/modalEditFieldsContent.html.twig', [
                'field' => $field,
            ]);

            return new JsonResponse($json);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/modifier", name="fields_edit",  options={"expose"=true}, methods="GET|POST")
     * @param Request $request
     * @param UserService $userService
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    public function edit(Request $request,
                         UserService $userService,
                         EntityManagerInterface $entityManager): Response {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$userService->hasRightFunction(Menu::PARAM, Action::EDIT)) {
                return $this->redirectToRoute('access_denied');
            }

            $fieldsParamRepository = $entityManager->getRepository(FieldsParam::class);
            $field = $fieldsParamRepository->find($data['field']);

            $fieldName = $field->getFieldCode();
            $fieldEntity = $field->getEntityCode();

            if (!$field->getFieldRequiredHidden()) {
                $field
                    ->setMustToModify($data['mustToModify'])
                    ->setMustToCreate($data['mustToCreate']);
            }
            $field->setDisplayed($data['displayed'] ?? true);

            $entityManager->persist($field);
            $entityManager->flush();

            return new JsonResponse([
                'success' => true,
                'msg' => 'Le champ fixe "' . $fieldName . '" dans "' . $fieldEntity . '" a bien été modifié.'
            ]);
        }
        throw new NotFoundHttpException('404');
    }
}
