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
use WiiCommon\Helper\Stream;

/**
 * @Route("/nature-colis")
 */
class NatureController extends AbstractController
{
    /** @Required */
    public UserService $userService;

    /**
     * @Route("/", name="nature_param_index")
     */
    public function index()
    {
        return $this->render('nature_param/index.html.twig');
    }

    /**
     * @Route("/api", name="nature_param_api", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
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
    public function new(Request $request, TranslatorInterface $translator, EntityManagerInterface $entityManager): Response {
        if ($data = json_decode($request->getContent(), true)) {
            $em = $this->getDoctrine()->getManager();

            if(preg_match("[[,;]]", $data['label'])) {
                return $this->json([
                    "success" => false,
                    "msg" => "Le label d'une nature ne peut pas contenir ; ou ,",
                ]);
            }

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

            $natures = $entityManager->getRepository(Nature::class)->findAll();

            $defaultForDispatch = filter_var($data['defaultForDispatch'] ?? false, FILTER_VALIDATE_BOOLEAN);
            if($defaultForDispatch) {
                $isAlreadyDefaultForDispatch = !Stream::from($natures)
                    ->filter(fn(Nature $nature) => $nature->getDefaultForDispatch())
                    ->isEmpty();

                if(!$isAlreadyDefaultForDispatch) {
                    $nature->setDefaultForDispatch($defaultForDispatch);
                } else {
                    return $this->json([
                        'success' => false,
                        'msg' => 'Une nature par défaut pour les acheminements a déjà été sélectionnée'
                    ]);
                }
            } else {
                $nature->setDefaultForDispatch(false);
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
            $currentNature = $natureRepository->find($data['nature']);
            $natureLabel = $currentNature->getLabel();

            if(preg_match("[[,;]]", $data['label'])) {
                return $this->json([
                    "success" => false,
                    "msg" => "Le label d'une nature ne peut pas contenir ; ou ,",
                ]);
            }

            $currentNature
                ->setLabel($data['label'])
                ->setPrefix($data['prefix'] ?? null)
                ->setDefaultQuantity($data['quantity'])
                ->setDisplayed($data['displayed'])
                ->setNeedsMobileSync($data['mobileSync'] ?? false)
                ->setDescription($data['description'] ?? null)
                ->setColor($data['color'])
                ->setCode($data['code']);

            $natures = $natureRepository->findAll();

            $defaultForDispatch = filter_var($data['defaultForDispatch'] ?? false, FILTER_VALIDATE_BOOLEAN);
            if($defaultForDispatch) {
                $isAlreadyDefaultForDispatch = !Stream::from($natures)
                    ->filter(fn(Nature $nature) => $nature->getDefaultForDispatch() && $nature->getId() !== $currentNature->getId())
                    ->isEmpty();

                if(!$isAlreadyDefaultForDispatch) {
                    $currentNature->setDefaultForDispatch($defaultForDispatch);
                } else {
                    return $this->json([
                        'success' => false,
                        'msg' => 'Une nature par défaut pour les acheminements a déjà été sélectionnée'
                    ]);
                }
            } else {
                $currentNature->setDefaultForDispatch(false);
            }

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
