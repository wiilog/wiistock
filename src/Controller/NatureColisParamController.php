<?php


namespace App\Controller;

use App\Annotation\HasPermission;
use App\Entity\Action;
use App\Entity\Menu;
use App\Entity\Nature;
use App\Service\UserService;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @Route("/nature-colis")
 */
class NatureColisParamController extends AbstractController
{
    /**
     * @var UserService
     */
    private $userService;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }

    /**
     * @Route("/", name="nature_param_index")
     * @HasPermission({Menu::PARAM, Action::DISPLAY_NATU_COLI})
     */
    public function index()
    {
        return $this->render('nature_param/index.html.twig');
    }

    /**
     * @Route("/api", name="nature_param_api", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::PARAM, Action::DISPLAY_NATU_COLI}, mode=HasPermission::IN_JSON)
     */
    public function api(EntityManagerInterface $entityManager): Response
    {
        $natureRepository = $entityManager->getRepository(Nature::class);

        $natures = $natureRepository->findAll();
        $rows = [];
        foreach ($natures as $nature) {
            $url['edit'] = $this->generateUrl('nature_api_edit', ['id' => $nature->getId()]);

            $rows[] =
                [
                    'Label' => $nature->getLabel(),
                    'Code' => $nature->getCode(),
                    'Quantité par défaut' => $nature->getDefaultQuantity() ?? 'Non définie',
                    'Préfixe' => $nature->getPrefix() ?? 'Non défini',
                    'mobileSync' => $nature->getNeedsMobileSync() ? 'Oui' : 'Non',
                    'displayed' => $nature->getDisplayed() ? 'Oui' : 'Non',
                    'Couleur' => $nature->getColor() ? '<div style="background-color:' . $nature->getColor() . ';"><br></div>' : 'Non définie',
                    'description' => $nature->getDescription() ?? 'Non définie',
                    'Actions' => $this->renderView('nature_param/datatableNatureRow.html.twig', [
                        'url' => $url,
                        'natureId' => $nature->getId(),
                    ]),
                ];
        }
        $data['data'] = $rows;
        return new JsonResponse($data);
    }

    /**
     * @Route("/creer", name="nature_new", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::PARAM, Action::EDIT}, mode=HasPermission::IN_JSON)
     */
    public function new(Request $request, TranslatorInterface $translator, EntityManagerInterface $entityManager): Response
    {
        if ($data = json_decode($request->getContent(), true)) {
            $em = $this->getDoctrine()->getManager();

            $natureRepository = $entityManager->getRepository(Nature::class);
            $natures = $natureRepository->findAll();

            $nature = new Nature();
            $nature
                ->setLabel($data['label'])
                ->setPrefix($data['prefix'] ?? null)
                ->setColor($data['color'])
                ->setDisplayed($data['displayed'])
                ->setNeedsMobileSync($data['mobileSync'] ?? false)
                ->setDefaultQuantity($data['quantity'])
                ->setDescription($data['description'] ?? null)
                ->setCode($data['code']);

            $isDefaultForDispatch = false;
            foreach ($natures as $checkNature){
                $isDefaultForDispatch = $checkNature->getDefaultForDispatch();
                if($isDefaultForDispatch){
                    break;
                }
            }

            if(!$isDefaultForDispatch || $data['defaultForDispatch'] == false){
                $nature->setDefaultForDispatch($data['defaultForDispatch'] ?? false);
            } else {
                return new JsonResponse([
                    'success' => false,
                    'msg' => 'Une nature par défaut a déjà été sélectionnée'
                ]);
            }

            $em->persist($nature);
            $em->flush();

            return new JsonResponse([
                'success' => true,
                'msg' =>  $translator->trans('natures.une nature') . ' "' . $data['label'] . '" a bien été créée.'
            ]);
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/api-modifier", name="nature_api_edit", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::PARAM, Action::EDIT}, mode=HasPermission::IN_JSON)
     */
    public function apiEdit(Request $request,
                            EntityManagerInterface $entityManager): Response
    {
        if ($data = json_decode($request->getContent(), true)) {
            $natureRepository = $entityManager->getRepository(Nature::class);
            $nature = $natureRepository->find($data['id']);

            $json = $this->renderView('nature_param/modalEditNatureContent.html.twig', [
                'nature' => $nature,
            ]);

            return new JsonResponse($json);
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/modifier", name="nature_edit",  options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::PARAM, Action::EDIT}, mode=HasPermission::IN_JSON)
     */
    public function edit(Request $request,
                         EntityManagerInterface $entityManager): Response
    {
        if ($data = json_decode($request->getContent(), true)) {
            $natureRepository = $entityManager->getRepository(Nature::class);
            $natures = $natureRepository->findAll();
            $nature = $natureRepository->find($data['nature']);
            $natureLabel = $nature->getLabel();

            $nature
                ->setLabel($data['label'])
                ->setPrefix($data['prefix'] ?? null)
                ->setDefaultQuantity($data['quantity'])
                ->setDisplayed($data['displayed'])
                ->setNeedsMobileSync($data['mobileSync'] ?? false)
                ->setDescription($data['description'] ?? null)
                ->setColor($data['color'])
                ->setCode($data['code']);

            $isDefaultForDispatch = false;
            foreach ($natures as $checkNature){
                $isDefaultForDispatch = $checkNature->getDefaultForDispatch();
                if($isDefaultForDispatch){
                    break;
                }
            }

            if(!$isDefaultForDispatch || $data['defaultForDispatch'] == false || $nature->getDefaultForDispatch()){
                $nature->setDefaultForDispatch($data['defaultForDispatch'] ?? false);
            } else {
                return new JsonResponse([
                    'success' => false,
                    'msg' => 'Une nature par défaut a déjà été sélectionnée'
                ]);
            }


            $entityManager->persist($nature);
            $entityManager->flush();

            return new JsonResponse([
                'success' => true,
                'msg' => 'La nature "' . $natureLabel . '" a bien été modifiée.'
            ]);
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/verification", name="nature_check_delete", options={"expose"=true}, condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::PARAM, Action::DELETE}, mode=HasPermission::IN_JSON)
     */
    public function checkStatusCanBeDeleted(Request $request,
                                            EntityManagerInterface $entityManager): Response
    {
        if ($typeId = json_decode($request->getContent(), true)) {
            $natureRepository = $entityManager->getRepository(Nature::class);
            $natureIsUsed = $natureRepository->countUsedById($typeId);

            if (!$natureIsUsed) {
                $delete = true;
                $html = $this->renderView('nature_param/modalDeleteNatureRight.html.twig');
            } else {
                $delete = false;
                $html = $this->renderView('nature_param/modalDeleteNatureWrong.html.twig');
            }

            return new JsonResponse([
                'delete' => $delete,
                'html' => $html
            ]);
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/supprimer", name="nature_delete", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::PARAM, Action::DELETE}, mode=HasPermission::IN_JSON)
     */
    public function delete(Request $request,
                           EntityManagerInterface $entityManager): Response
    {
        if ($data = json_decode($request->getContent(), true)) {
            $natureRepository = $entityManager->getRepository(Nature::class);
            $nature = $natureRepository->find($data['nature']);

            $entityManager->remove($nature);
            $entityManager->flush();
            return new JsonResponse();
        }
        throw new BadRequestHttpException();
    }
}
