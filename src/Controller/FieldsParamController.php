<?php


namespace App\Controller;

use App\Entity\Menu;
use App\Repository\FieldsParamRepository;
use App\Service\UserService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

use Symfony\Component\HttpFoundation\JsonResponse;
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
     * @var UserService
     */
    private $userService;

    /**
     * @var FieldsParamRepository
     */
    private $fieldsParamRepository;

    public function __construct(UserService $userService, FieldsParamRepository $fieldsParamRepository)
    {
        $this->userService = $userService;
        $this->fieldsParamRepository = $fieldsParamRepository;
    }

    /**
     * @Route("/", name="fields_param_index")
     */
    public function index()
    {
        if (!$this->userService->hasRightFunction(Menu::PARAM)) {
            return $this->redirectToRoute('access_denied');
        }

        return $this->render('fields_param/index.html.twig', [
        ]);
    }

    /**
     * @Route("/api", name="fields_param_api", options={"expose"=true}, methods="GET|POST")
     */
    public function api(Request $request): Response
    {
        if ($request->isXmlHttpRequest()) {
            if (!$this->userService->hasRightFunction(Menu::PARAM)) {
                return $this->redirectToRoute('access_denied');
            }

            $arrayFields = $this->fieldsParamRepository->findAll();
            $rows = [];
            foreach ($arrayFields as $field) {
                $url['edit'] = $this->generateUrl('fields_api_edit', ['id' => $field->getId()]);

                $rows[] =
                    [
                        'entityCode' => $field->getEntityCode(),
                        'fieldCode' => $field->getFieldCode(),
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
     */
    public function apiEdit(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::PARAM)) {
                return $this->redirectToRoute('access_denied');
            }

            $field = $this->fieldsParamRepository->find($data['id']);

            $json = $this->renderView('fields_param/modalEditFieldsContent.html.twig', [
                'field' => $field,
            ]);

            return new JsonResponse($json);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/modifier", name="fields_edit",  options={"expose"=true}, methods="GET|POST")
     */
    public function edit(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::PARAM)) {
                return $this->redirectToRoute('access_denied');
            }

            $em = $this->getDoctrine()->getEntityManager();
            $field = $this->fieldsParamRepository->find($data['field']);
            $fieldName = $field->getFieldCode();
            $fieldEntity = $field->getEntityCode();

            $field
                ->setMustToCreate($data['mustToCreate'])
                ->setMustToModify($data['mustToModify']);

            $em->persist($field);
            $em->flush();

            return new JsonResponse([
                'success' => true,
                'msg' => 'Le champs fixe "' . $fieldName . '" dans "' . $fieldEntity . '" a bien été modifié.'
            ]);
        }
        throw new NotFoundHttpException('404');
    }
}
