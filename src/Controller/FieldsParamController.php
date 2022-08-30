<?php


namespace App\Controller;

use App\Annotation\HasPermission;
use App\Entity\Action;
use App\Entity\FieldsParam;
use App\Entity\Menu;
use Doctrine\ORM\EntityManagerInterface;
use App\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/champs-fixes")
 */
class FieldsParamController extends AbstractController
{

    private array $filteredFields = [
        // Arrivages
        FieldsParam::FIELD_CODE_CUSTOMS_ARRIVAGE,
        FieldsParam::FIELD_CODE_FROZEN_ARRIVAGE,
        FieldsParam::FIELD_CODE_FOURNISSEUR,
        FieldsParam::FIELD_CODE_DROP_LOCATION_ARRIVAGE,
        FieldsParam::FIELD_CODE_TRANSPORTEUR,
        FieldsParam::FIELD_CODE_TARGET_ARRIVAGE,
        FieldsParam::FIELD_CODE_BUSINESS_UNIT,

        // Acheminements
        FieldsParam::FIELD_CODE_EMERGENCY,
        FieldsParam::FIELD_CODE_RECEIVER_DISPATCH,
        FieldsParam::FIELD_CODE_COMMAND_NUMBER_DISPATCH,
        FieldsParam::FIELD_CODE_DESTINATION,

        // Services
        FieldsParam::FIELD_CODE_RECEIVERS_HANDLING
    ];

    /**
     * @Route("/", name="fields_param_index")
     */
    public function index()
    {
        return $this->render('fields_param/index.html.twig');
    }

    /**
     * @Route("/api/{entityCode}", name="fields_param_api", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     */
    public function api(EntityManagerInterface $entityManager,
                        string $entityCode): Response
    {
        $fieldsParamRepository = $entityManager->getRepository(FieldsParam::class);
        $arrayFields = $fieldsParamRepository->findByEntityForEntity($entityCode);
        $rows = [];

        foreach ($arrayFields as $field) {
            $url['edit'] = $this->generateUrl('fields_api_edit', ['id' => $field->getId()]);

            $rows[] =
                [
                    'fieldCode' => $field->getFieldLabel(),
                    'displayedCreate' => $field->isDisplayedCreate() ? 'oui' : 'non',
                    'displayedEdit' => $field->isDisplayedEdit() ? 'oui' : 'non',
                    'displayedFilters' => (in_array($field->getFieldCode(), $this->filteredFields) && $field->isDisplayedFilters()) ? 'oui' : 'non',
                    'mustCreate' => $field->isRequiredCreate() ? 'oui' : 'non',
                    'mustEdit' => $field->isRequiredEdit() ? 'oui' : 'non',
                    'Actions' => $this->renderView('fields_param/datatableFieldsRow.html.twig', [
                        'url' => $url,
                        'fieldId' => $field->getId(),
                    ]),
                ];
        }
        $data['data'] = $rows;
        return new JsonResponse($data);
    }

    /**
     * @Route("/api-modifier", name="fields_api_edit", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::PARAM, Action::EDIT}, mode=HasPermission::IN_JSON)
     */
    public function apiEdit(Request $request, EntityManagerInterface $entityManager): Response  {
        if ($data = json_decode($request->getContent(), true)) {
            $fieldsParamRepository = $entityManager->getRepository(FieldsParam::class);
            $field = $fieldsParamRepository->find($data['id']);

            $json = $this->renderView('fields_param/modalEditFieldsContent.html.twig', [
                'field' => $field,
                'filteredFields' => $this->filteredFields
            ]);

            return new JsonResponse($json);
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/modifier", name="fields_edit",  options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::PARAM, Action::EDIT}, mode=HasPermission::IN_JSON)
     */
    public function edit(Request $request,
                         EntityManagerInterface $entityManager): Response {
        if ($data = json_decode($request->getContent(), true)) {
            $fieldsParamRepository = $entityManager->getRepository(FieldsParam::class);
            $field = $fieldsParamRepository->find($data['field']);

            $fieldName = $field->getFieldLabel();
            $fieldEntity = $field->getEntityCode();

            if (!$field->getFieldRequiredHidden()) {
                $field
                    ->setRequiredEdit($data['requiredEdit'])
                    ->setRequiredCreate($data['requiredCreate']);
            }
            $field->setDisplayedCreate($data['displayedCreate'] ?? true);
            $field->setDisplayedEdit($data['displayedEdit'] ?? true);
            $field->setDisplayedFilters($data['displayedFilters'] ?? true);

            if($field->getElements() !== null) {
                if(isset($data['elements-text'])) {
                    $field->setElements(explode(';', $data['elements-text']));
                } else if(isset($data['elements'])) {
                    $field->setElements($data['elements'] ?? []);
                }
            }

            $entityManager->persist($field);
            $entityManager->flush();

            return new JsonResponse([
                'success' => true,
                'msg' => 'Le champ fixe <strong>' . $fieldName . '</strong> dans <strong>' . $fieldEntity . '</strong> a bien été modifié.'
            ]);
        }

        throw new BadRequestHttpException();
    }
}
