<?php


namespace App\Controller;

use App\Annotation\HasPermission;
use App\Entity\Action;
use App\Entity\CategoryType;
use App\Entity\Menu;
use App\Entity\Nature;
use App\Entity\Transport\TemperatureRange;
use App\Entity\Type;
use App\Service\NatureService;
use App\Service\UserService;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;
use App\Service\TranslationService;
use WiiCommon\Helper\Stream;

/**
 * @Route("/nature-colis")
 */
class NatureController extends AbstractController
{
    /** @Required */
    public UserService $userService;

    #[Route('/', name: "nature_param_index", options: ['expose' => true], methods: 'GET')]
    #[HasPermission([Menu::REFERENTIEL, Action::DISPLAY_PACK_NATURE])]
    public function index(EntityManagerInterface $manager)
    {
        $typeRepository = $manager->getRepository(Type::class);

        $temperatures = $manager->getRepository(TemperatureRange::class)->findBy([]);
        $types = [
            'transportCollect' => $typeRepository->getIdAndLabelByCategoryLabel(CategoryType::COLLECT_TRANSPORT),
            'transportDelivery' => $typeRepository->getIdAndLabelByCategoryLabel(CategoryType::DELIVERY_TRANSPORT)
        ];
        return $this->render('nature_param/index.html.twig', [
            'temperatures' => $temperatures,
            'types' => $types
        ]);
    }

    #[Route("/api", name: "nature_param_api", options: ["expose" => true], methods: "GET|POST", condition: "request.isXmlHttpRequest()")]
    #[HasPermission([Menu::REFERENTIEL, Action::DISPLAY_PACK_NATURE], mode: HasPermission::IN_JSON)]
    public function api(Request $request, NatureService $natureService): Response
    {
        return $this->json($natureService->getDataForDatatable($request->request));
    }

    /**
     * @Route("/creer", name="nature_new", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({MENU::REFERENTIEL, Action::CREATE}, mode=HasPermission::IN_JSON)
     */
    public function new(Request $request, TranslationService $translation, EntityManagerInterface $entityManager): Response {
        if ($data = json_decode($request->getContent(), true)) {
            if(preg_match("[[,;]]", $data['label'])) {
                return $this->json([
                    "success" => false,
                    "msg" => "Le libellé d'une nature ne peut pas contenir ; ou ,",
                ]);
            }

            $temperatureRangeRepository = $entityManager->getRepository(TemperatureRange::class);
            $natureRepository = $entityManager->getRepository(Nature::class);

            if($natureRepository->findOneBy(["label" => $data["label"]])) {
                return $this->json([
                    "success" => false,
                    "msg" => "Une nature existe déjà avec ce libellé",
                ]);
            }

            $nature = new Nature();
            $nature
                ->setLabel($data['label'])
                ->setPrefix($data['prefix'] ?? null)
                ->setColor($data['color'])
                ->setNeedsMobileSync($data['mobileSync'] ?? false)
                ->setDefaultQuantity($data['quantity'])
                ->setDescription($data['description'] ?? null)
                ->setCode($data['code']);

            if (!empty($data['allowedTemperatures'])) {
                foreach ($data['allowedTemperatures'] as $allowedTemperatureId) {
                    $nature
                        ->addTemperatureRange($temperatureRangeRepository->find($allowedTemperatureId));
                }
            }

            if($data['displayedOnForms']) {
                $allowedForms = [];
                if($data[Nature::ARRIVAL_CODE]) {
                    $allowedForms[Nature::ARRIVAL_CODE] = 'all';
                }

                if($data[Nature::TRANSPORT_COLLECT_CODE]) {
                    $allowedForms[Nature::TRANSPORT_COLLECT_CODE] = $data['transportCollectTypes'];
                }

                if($data[Nature::TRANSPORT_DELIVERY_CODE]) {
                    $allowedForms[Nature::TRANSPORT_DELIVERY_CODE] = $data['transportDeliveryTypes'];
                }
                $nature
                    ->setDisplayedOnForms(true)
                    ->setAllowedForms($allowedForms);
            } else {
                $nature->setDisplayedOnForms(false);
            }

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

            $entityManager->persist($nature);
            $entityManager->flush();

            return $this->json([
                "success" => true,
                "msg" => $translation->translate("Référentiel", "Natures", "La nature {1} a bien été créée", [
                    1 => $data["label"],
                ])
            ]);
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/api-modifier", name="nature_api_edit", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({MENU::REFERENTIEL, Action::EDIT}, mode=HasPermission::IN_JSON)
     */
    public function apiEdit(Request $request,
                            EntityManagerInterface $manager): Response
    {
        if ($data = json_decode($request->getContent(), true)) {
            $natureRepository = $manager->getRepository(Nature::class);
            $typeRepository = $manager->getRepository(Type::class);
            $nature = $natureRepository->find($data['id']);

            $temperatures = $manager->getRepository(TemperatureRange::class)->findBy([]);
            $types = [
                'transportCollect' => $typeRepository->getIdAndLabelByCategoryLabel(CategoryType::COLLECT_TRANSPORT),
                'transportDelivery' => $typeRepository->getIdAndLabelByCategoryLabel(CategoryType::DELIVERY_TRANSPORT)
            ];

            $json = $this->renderView('nature_param/modalEditNatureContent.html.twig', [
                'nature' => $nature,
                'temperatures' => $temperatures,
                'types' => $types
            ]);

            return new JsonResponse($json);
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/modifier", name="nature_edit",  options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({MENU::REFERENTIEL, Action::EDIT}, mode=HasPermission::IN_JSON)
     */
    public function edit(Request $request,
                         EntityManagerInterface $entityManager): Response
    {
        if ($data = json_decode($request->getContent(), true)) {
            $natureRepository = $entityManager->getRepository(Nature::class);
            $temperatureRangeRepository = $entityManager->getRepository(TemperatureRange::class);
            $currentNature = $natureRepository->find($data['nature']);
            $natureLabel = $currentNature->getLabel();

            if(preg_match("[[,;]]", $data['label'])) {
                return $this->json([
                    "success" => false,
                    "msg" => "Le label d'une nature ne peut pas contenir ; ou ,",
                ]);
            }

            $existingNatures = Stream::from($natureRepository->findBy(["label" => $data["label"]]))
                ->filter(fn(Nature $nature) => $nature->getId() != $currentNature->getId())
                ->count();

            if($existingNatures > 0) {
                return $this->json([
                    "success" => false,
                    "msg" => "Une nature existe déjà avec ce libellé",
                ]);
            }

            $currentNature
                ->setLabel($data['label'])
                ->setPrefix($data['prefix'] ?? null)
                ->setDefaultQuantity($data['quantity'])
                ->setNeedsMobileSync($data['mobileSync'] ?? false)
                ->setDescription($data['description'] ?? null)
                ->setColor($data['color'])
                ->setCode($data['code']);

            $currentNature->getTemperatureRanges()->clear();

            if (!empty($data['allowedTemperatures'])) {
                foreach ($data['allowedTemperatures'] as $allowedTemperatureId) {
                    $currentNature
                        ->addTemperatureRange($temperatureRangeRepository->find($allowedTemperatureId));
                }
            }

            if($data['displayedOnForms']) {
                $allowedForms = [];
                if($data[Nature::ARRIVAL_CODE]) {
                    $allowedForms[Nature::ARRIVAL_CODE] = 'all';
                }

                if($data[Nature::TRANSPORT_COLLECT_CODE]) {
                    $allowedForms[Nature::TRANSPORT_COLLECT_CODE] = $data['transportCollectTypes'];
                }

                if($data[Nature::TRANSPORT_DELIVERY_CODE]) {
                    $allowedForms[Nature::TRANSPORT_DELIVERY_CODE] = $data['transportDeliveryTypes'];
                }
                $currentNature
                    ->setDisplayedOnForms(true)
                    ->setAllowedForms($allowedForms);
            } else {
                $currentNature
                    ->setDisplayedOnForms(false)
                    ->setAllowedForms(null);
            }

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
                'msg' => "La nature <strong>$natureLabel</strong> a bien été modifiée."
            ]);
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/verification", name="nature_check_delete", options={"expose"=true}, condition="request.isXmlHttpRequest()")
     * @HasPermission({MENU::REFERENTIEL, Action::DELETE}, mode=HasPermission::IN_JSON)
     */
    public function checkNatureCanBeDeleted(Request $request,
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
     * @HasPermission({MENU::REFERENTIEL, Action::DELETE}, mode=HasPermission::IN_JSON)
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
