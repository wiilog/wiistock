<?php


namespace App\Controller;

use App\Annotation\HasPermission;
use App\Entity\Action;
use App\Entity\CategoryType;
use App\Entity\Language;
use App\Entity\Menu;
use App\Entity\Nature;
use App\Entity\Translation;
use App\Entity\TranslationSource;
use App\Entity\Transport\TemperatureRange;
use App\Entity\Type;
use App\Service\NatureService;
use App\Service\UserService;

use Doctrine\ORM\EntityManagerInterface;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;
use App\Service\TranslationService;
use Symfony\Contracts\Service\Attribute\Required;
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
            $labels = $data['labels'];
            foreach ($labels as $label) {
                if (preg_match("[[,;]]", $label['label'])) {
                    return $this->json([
                        "success" => false,
                        "msg" => "Le libellé d'une nature ne peut pas contenir ; ou ,",
                    ]);
                }

                $temperatureRangeRepository = $entityManager->getRepository(TemperatureRange::class);
                $natureRepository = $entityManager->getRepository(Nature::class);

                if ($natureRepository->findDuplicates($label["label"], $label["language-id"])) {
                    $language = $entityManager->find(Language::class, $label["language-id"]);

                    return $this->json([
                        "success" => false,
                        "msg" => "Une nature existe déjà avec ce libellé dans la langue \"{$language->getLabel()}\"",
                    ]);
                }
            }

            $frenchLanguage = $entityManager->getRepository(Language::class)->findOneBy(['slug' => Language::FRENCH_SLUG]);
            $frenchLabel = Stream::from($labels)
                ->find(fn(array $element) => intval($element['language-id']) === $frenchLanguage->getId());

            $nature = new Nature();
            $nature
                ->setPrefix($data['prefix'] ?? null)
                ->setColor($data['color'])
                ->setNeedsMobileSync($data['mobileSync'] ?? false)
                ->setDefaultQuantity($data['quantity'])
                ->setDefaultQuantityForDispatch($data['defaultQuantityDispatch'] ?: null)
                ->setDescription($data['description'] ?? null)
                ->setCode($data['code'])
                ->setLabel($frenchLabel['label'] ?? $data['code'])
                ->setLabelTranslation(new TranslationSource());

            $labelTranslationSource = $nature->getLabelTranslation();
            $entityManager->persist($labelTranslationSource);
            $labelTranslationSource->setNature($nature);

            foreach ($labels as $label) {
                $labelLanguage = $entityManager->getRepository(Language::class)->find($label['language-id']);

                $newTranslation = new Translation();
                $newTranslation
                    ->setTranslation($label['label'])
                    ->setSource($labelTranslationSource)
                    ->setLanguage($labelLanguage);

                $labelTranslationSource->addTranslation($newTranslation);
                $entityManager->persist($newTranslation);
            }

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
                "msg" => "La nature {$data["label"]} a bien été créée"
            ]);
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/api-modifier", name="nature_api_edit", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({MENU::REFERENTIEL, Action::EDIT}, mode=HasPermission::IN_JSON)
     */
    public function apiEdit(Request $request,
                            EntityManagerInterface $manager,
                            TranslationService $translationService): Response
    {
        if ($data = json_decode($request->getContent(), true)) {
            $natureRepository = $manager->getRepository(Nature::class);
            $typeRepository = $manager->getRepository(Type::class);
            $nature = $natureRepository->find($data['id']);

            if ($nature->getLabelTranslation() === null) {
                $translationService->setFirstTranslation($manager, $nature, $nature->getLabel());
            }

            $temperatures = $manager->getRepository(TemperatureRange::class)->findBy([]);
            $types = [
                'transportCollect' => $typeRepository->getIdAndLabelByCategoryLabel(CategoryType::COLLECT_TRANSPORT),
                'transportDelivery' => $typeRepository->getIdAndLabelByCategoryLabel(CategoryType::DELIVERY_TRANSPORT)
            ];

            return new JsonResponse($this->renderView('nature_param/modalEditNatureContent.html.twig', [
                "nature" => $nature,
                "temperatures" => $temperatures,
                "types" => $types
            ]));
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/modifier", name="nature_edit",  options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({MENU::REFERENTIEL, Action::EDIT}, mode=HasPermission::IN_JSON)
     */
    public function edit(Request $request,
                         EntityManagerInterface $entityManager,
                         TranslationService $translationService): Response
    {
        if ($data = json_decode($request->getContent(), true)) {
            $natureRepository = $entityManager->getRepository(Nature::class);
            $temperatureRangeRepository = $entityManager->getRepository(TemperatureRange::class);
            $currentNature = $natureRepository->find($data['nature']);
            $natureLabel = $this->getFormatter()->nature($currentNature);
            $labelTranslationSource = $currentNature->getLabelTranslation();

            $labels = $data['labels'];
            $frenchLabel = $this->getFormatter()->nature($currentNature);
            foreach ($labels as $label) {
                if (preg_match("[[,;]]", $label['label'])) {
                    return $this->json([
                        "success" => false,
                        "msg" => "Le label d'une nature ne peut pas contenir ; ou ,",
                    ]);
                }

                $existingNatures = Stream::from($natureRepository->findBy(["label" => $label['label']]))
                    ->filter(fn(Nature $nature) => $nature->getId() != $currentNature->getId())
                    ->count();

                if ($existingNatures > 0) {
                    $language = $entityManager->find(Language::class, $label["language-id"]);

                    return $this->json([
                        "success" => false,
                        "msg" => "Une nature existe déjà avec ce libellé dans la langue \"{$language->getLabel()}\"",
                    ]);
                }

                $frenchLabel = $label['language-id'] == "1" ? $label['label'] : $frenchLabel;
            }

            $translationService->editEntityTranslations($entityManager, $labelTranslationSource, $labels);

            $currentNature
                ->setLabel($frenchLabel)
                ->setPrefix($data['prefix'] ?? null)
                ->setDefaultQuantity($data['quantity'])
                ->setDefaultQuantityForDispatch($data['defaultQuantityDispatch'] ?: null)
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
                'msg' => "La nature $natureLabel a bien été modifiée"
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
