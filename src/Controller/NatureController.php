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
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use App\Service\TranslationService;
use WiiCommon\Helper\Stream;

#[Route("/nature", name: "nature_")]
class NatureController extends AbstractController {

    #[Route("/", name: "index", options: ['expose' => true], methods: self::GET)]
    #[HasPermission([Menu::REFERENTIEL, Action::DISPLAY_PACK_NATURE])]
    public function index(EntityManagerInterface $manager): Response {
        $typeRepository = $manager->getRepository(Type::class);

        $temperatures = $manager->getRepository(TemperatureRange::class)->findBy([]);
        $types = [
            'transportCollect' => $typeRepository->getIdAndLabelByCategoryLabel(CategoryType::COLLECT_TRANSPORT),
            'transportDelivery' => $typeRepository->getIdAndLabelByCategoryLabel(CategoryType::DELIVERY_TRANSPORT)
        ];
        return $this->render('nature/index.html.twig', [
            'temperatures' => $temperatures,
            'types' => $types,
        ]);
    }

    #[Route("/api", name: "api", options: ["expose" => true], methods: self::POST, condition: "request.isXmlHttpRequest()")]
    #[HasPermission([Menu::REFERENTIEL, Action::DISPLAY_PACK_NATURE], mode: HasPermission::IN_JSON)]
    public function api(Request $request, NatureService $natureService): Response {
        return $this->json($natureService->getDataForDatatable($request->request));
    }

    #[Route("/new", name: "new", options: ["expose" => true], methods: self::POST, condition: "request.isXmlHttpRequest()")]
    #[HasPermission([Menu::REFERENTIEL, Action::CREATE], mode: HasPermission::IN_JSON)]
    public function new(Request $request, EntityManagerInterface $entityManager): Response {
        if ($data = json_decode($request->getContent(), true)) {
            $labels = $data['labels'];

            $temperatureRangeRepository = $entityManager->getRepository(TemperatureRange::class);
            $natureRepository = $entityManager->getRepository(Nature::class);

            foreach ($labels as $label) {
                if (preg_match("[[,;]]", $label['label'])) {
                    return $this->json([
                        "success" => false,
                        "msg" => "Le libellé d'une nature ne peut pas contenir ; ou ,",
                    ]);
                }

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

                if($data[Nature::DISPATCH_CODE]) {
                    $allowedForms[Nature::DISPATCH_CODE] = 'all';
                }

                if($data[Nature::TRANSPORT_COLLECT_CODE]) {
                    $allowedForms[Nature::TRANSPORT_COLLECT_CODE] = $data['transportCollectTypes'];
                }

                if($data[Nature::TRANSPORT_DELIVERY_CODE]) {
                    $allowedForms[Nature::TRANSPORT_DELIVERY_CODE] = $data['transportDeliveryTypes'];
                }

                if($data[Nature::DISPATCH_CODE]) {
                    $allowedForms[Nature::DISPATCH_CODE] = 'all';
                }

                $nature
                    ->setDisplayedOnForms(true)
                    ->setAllowedForms($allowedForms);
            } else {
                $nature->setDisplayedOnForms(false);
            }

            $natures = $entityManager->getRepository(Nature::class)->findAll();

            $default = filter_var($data['default'] ?? false, FILTER_VALIDATE_BOOLEAN);
            if($default) {
                $isAlreadyDefault = !Stream::from($natures)
                    ->filter(fn(Nature $nature) => $nature->getDefaultNature())
                    ->isEmpty();

                if(!$isAlreadyDefault) {
                    $nature->setDefaultNature($default);
                } else {
                    return $this->json([
                        'success' => false,
                        'msg' => 'Une nature par défaut pour les acheminements a déjà été sélectionnée.',
                    ]);
                }
            } else {
                $nature->setDefaultNature(false);
            }

            $entityManager->persist($nature);
            $entityManager->flush();

            return $this->json([
                "success" => true,
                "msg" => "La nature {$nature->getLabel()} a bien été créée."
            ]);
        }
        throw new BadRequestHttpException();
    }

    #[Route("api-edit", name: "api_edit", options: ["expose" => true], methods: self::POST, condition: "request.isXmlHttpRequest()")]
    #[HasPermission([Menu::REFERENTIEL, Action::EDIT], mode: HasPermission::IN_JSON)]
    public function apiEdit(Request                $request,
                            EntityManagerInterface $manager,
                            TranslationService     $translationService): Response {
        if ($data = json_decode($request->getContent(), true)) {
            $natureRepository = $manager->getRepository(Nature::class);
            $typeRepository = $manager->getRepository(Type::class);
            $nature = $natureRepository->find($data['id']);

            if ($nature->getLabelTranslation() === null) {
                $translationService->setDefaultTranslation($manager, $nature, $nature->getLabel());
            }

            $temperatures = $manager->getRepository(TemperatureRange::class)->findBy([]);
            $types = [
                'transportCollect' => $typeRepository->getIdAndLabelByCategoryLabel(CategoryType::COLLECT_TRANSPORT),
                'transportDelivery' => $typeRepository->getIdAndLabelByCategoryLabel(CategoryType::DELIVERY_TRANSPORT)
            ];

            return new JsonResponse($this->renderView('nature/modal/form.html.twig', [
                "nature" => $nature,
                "temperatures" => $temperatures,
                "types" => $types
            ]));
        }
        throw new BadRequestHttpException();
    }

    #[Route("edit", name: "edit", options: ["expose" => true], methods: self::POST, condition: "request.isXmlHttpRequest()")]
    #[HasPermission([Menu::REFERENTIEL, Action::EDIT], mode: HasPermission::IN_JSON)]
    public function edit(Request                $request,
                         EntityManagerInterface $entityManager,
                         TranslationService     $translationService): Response {
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

            if($labelTranslationSource) {
                $translationService->editEntityTranslations($entityManager, $labelTranslationSource, $labels);
            }

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

                if($data[Nature::DISPATCH_CODE]) {
                    $allowedForms[Nature::DISPATCH_CODE] = 'all';
                }

                if($data[Nature::TRANSPORT_DELIVERY_CODE]) {
                    $allowedForms[Nature::TRANSPORT_DELIVERY_CODE] = $data['transportDeliveryTypes'];
                }

                if($data[Nature::DISPATCH_CODE]) {
                    $allowedForms[Nature::DISPATCH_CODE] = 'all';
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

            $default = filter_var($data['default'] ?? false, FILTER_VALIDATE_BOOLEAN);
            if($default) {
                $isAlreadyDefault = !Stream::from($natures)
                    ->filter(fn(Nature $nature) => $nature->getDefaultNature() && $nature->getId() !== $currentNature->getId())
                    ->isEmpty();

                if(!$isAlreadyDefault) {
                    $currentNature->setDefaultNature($default);
                } else {
                    return $this->json([
                        'success' => false,
                        'msg' => 'Une nature par défaut pour les acheminements a déjà été sélectionnée'
                    ]);
                }
            } else {
                $currentNature->setDefaultNature(false);
            }

            $entityManager->flush();

            return new JsonResponse([
                'success' => true,
                'msg' => "La nature $natureLabel a bien été modifiée"
            ]);
        }
        throw new BadRequestHttpException();
    }

    #[Route("check-delete", name: "check_delete", options: ["expose" => true], methods: self::POST, condition: "request.isXmlHttpRequest()")]
    #[HasPermission([Menu::REFERENTIEL, Action::DELETE], mode: HasPermission::IN_JSON)]
    public function checkDelete(Request                $request,
                                EntityManagerInterface $entityManager): Response {
        if ($typeId = json_decode($request->getContent(), true)) {
            $natureRepository = $entityManager->getRepository(Nature::class);
            $natureIsUsed = $natureRepository->countUsedById($typeId);

            if (!$natureIsUsed) {
                $delete = true;
                $html = $this->renderView('nature/modal/delete-right.html.twig');
            } else {
                $delete = false;
                $html = $this->renderView('nature/modal/delete-wrong.html.twig');
            }

            return new JsonResponse([
                'delete' => $delete,
                'html' => $html
            ]);
        }
        throw new BadRequestHttpException();
    }

    #[Route("delete", name: "delete", options: ["expose" => true], methods: self::POST, condition: "request.isXmlHttpRequest()")]
    #[HasPermission([Menu::REFERENTIEL, Action::DELETE], mode: HasPermission::IN_JSON)]
    public function delete(Request                $request,
                           EntityManagerInterface $entityManager): Response {
        if ($data = json_decode($request->getContent(), true)) {
            $natureRepository = $entityManager->getRepository(Nature::class);

            $nature = $natureRepository->find($data['nature']);

            $entityManager->remove($nature);
            $entityManager->flush();

            return $this->json([
                "success" => true,
                "msg" => "La nature a bien été supprimée.",
            ]);
        }
        throw new BadRequestHttpException();
    }
}
