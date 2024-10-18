<?php


namespace App\Service;

use App\Entity\Language;
use App\Entity\Nature;
use App\Entity\Transport\TemperatureRange;
use App\Entity\Type;
use App\Entity\Utilisateur;
use App\Exceptions\FormException;
use App\Helper\FormatHelper;
use App\Helper\LanguageHelper;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\InputBag;
use Twig\Environment as Twig_Environment;
use WiiCommon\Helper\Stream;

class NatureService {

    public function __construct(
        private EntityManagerInterface $entityManager,
        private LanguageService        $languageService,
        private TranslationService     $translationService,
        private FormatService          $formatService,
        private Security               $security,
        private Twig_Environment       $templating,
        private DateTimeService        $dateTimeService,
    ) {

    }

    public function getDataForDatatable(InputBag $params): array {
        $natureRepository = $this->entityManager->getRepository(Nature::class);

        $defaultSlug = LanguageHelper::clearLanguage($this->languageService->getDefaultSlug());
        $defaultLanguage = $this->entityManager->getRepository(Language::class)->findOneBy(['slug' => $defaultSlug]);
        $language = $this->security->getUser()->getLanguage() ?: $defaultLanguage;

        $queryResult = $natureRepository->findByParams($params, [
            "defaultLanguage" => $defaultLanguage,
            "language" => $language,
        ]);

        $natures = $queryResult['data'];

        $rows = [];
        foreach ($natures as $nature) {
            $rows[] = $this->dataRowNature($nature);
        }

        return [
            'data' => $rows,
            'recordsTotal' => $queryResult['total'],
            'recordsFiltered' => $queryResult['count'],
        ];
    }

    public function dataRowNature(Nature $nature): array
    {
        $typeRepository = $this->entityManager->getRepository(Type::class);
        $userLanguage = $this->security->getUser()->getLanguage();
        $label = $this->formatService->nature($nature);

        if ($userLanguage !== $this->entityManager->getRepository(Language::class)->find(1)
            && $nature->getLabelTranslation() && $nature->getLabelTranslation()->getTranslationIn($userLanguage->getSlug())) {
            $label = $nature->getLabelTranslation()->getTranslationIn($userLanguage->getSlug())->getTranslation();
        }

        return [
            'label' => $label,
            'code' => $nature->getCode(),
            'defaultQuantity' => $nature->getDefaultQuantity() ?? 'Non définie',
            'quantityDefaultForDispatch' => $nature->getDefaultQuantityForDispatch() ?? null,
            'prefix' => $nature->getPrefix() ?? 'Non défini',
            'needsMobileSync' => FormatHelper::bool($nature->getNeedsMobileSync()),
            'displayedOnForms' => !empty($nature->getAllowedForms())
                ? Stream::from($nature->getAllowedForms())
                    ->map(fn(array|string $types, string $index) =>
                        Nature::ENTITIES[$index]['label'] .
                        (
                            is_array($types)
                                ? (' : ' . Stream::from($typeRepository->findBy(['id' => $types]))
                                        ->map(fn(Type $type) => $type->getLabel())
                                        ->join(", "))
                                : ''
                        )
                    )
                    ->join("; ")
                : 'non',
            'color' => $nature->getColor() ? '<div style="background-color:' . $nature->getColor() . ';"><br></div>' : 'Non définie',
            'description' => $nature->getDescription() ?? 'Non définie',
            'temperatures' => Stream::from($nature->getTemperatureRanges())->map(fn(TemperatureRange $temperature) => $temperature->getValue())->join(", "),
            'actions' => $this->templating->render('nature/action-row.html.twig', [
                'natureId' => $nature->getId(),
            ]),
        ];
    }

    public function serializeNature(Nature $nature, Utilisateur $user): array
    {
        return [
            'id' => $nature->getId(),
            'label' => $this->formatService->nature($nature, "", $user),
            'color' => $nature->getColor(),
            'hide' => !$nature->getNeedsMobileSync(),
            'defaultNature' => $nature->getDefaultNature(),
            'isDisplayedOnDispatch' => $nature->isDisplayedOnForm(Nature::DISPATCH_CODE)
        ];
    }

    public function updateNature(EntityManagerInterface $entityManager,
                                 Nature                 $nature,
                                 array                  $data): void {
        $temperatureRangeRepository = $entityManager->getRepository(TemperatureRange::class);
        $userRepository = $entityManager->getRepository(Utilisateur::class);
        $natureRepository = $entityManager->getRepository(Nature::class);

        $natureWithSameCode = Stream::from($natureRepository->findBy(["code" => $data["code"]]))
            ->filter(static fn(Nature $savedNature) => (!$nature->getId() || $savedNature->getId() !== $nature->getId()))
            ->count();

        if ($natureWithSameCode > 0) {
            throw new FormException("Une nature existe déjà avec ce code");
        }

        $labels = json_decode($data['labels'], true);
        foreach ($labels as $label) {
            if (!empty($label["label"])) {
                if (preg_match("[[,;]]", $label['label'])) {
                    throw new FormException("Le libellé d'une nature ne peut pas contenir ; ou ,");
                }

                $natureWithSameLabel = $natureRepository->findDuplicateLabels(
                    $label["label"],
                    $label["language-id"],
                    $nature->getId()
                        ? [$nature->getId()]
                        : []
                );

                if (!empty($natureWithSameLabel)) {
                    $language = $entityManager->find(Language::class, $label["language-slug"]);

                    throw new FormException("Une nature existe déjà avec ce libellé dans la langue \"{$language->getLabel()}\"");
                }
            }
        }

        $natureManager = $data["natureManager"]
            ? $userRepository->findOneBy(["id" => $data["natureManager"]])
            : null;

        $trackingDelayInSeconds = $data['natureTrackingDelay']
            ? $this->dateTimeService->calculateSecondsFrom($data['natureTrackingDelay'], Nature::TRACKING_DELAY_REGEX, "h")
            : null;

        if ($data['natureTrackingDelay'] && !($trackingDelayInSeconds > 0)) {
            throw new FormException("Le délai de traitement minimale de la nature est de 1min");
        }

        $segmentsMax = Stream::explode(",", $data['segments'] ?? "")
            ->filter()
            ->toArray();
        $segmentsColor = Stream::explode(",", $data['segmentColor'] ?? "")
            ->filter()
            ->toArray();;
        $trackingDelaySegments = Stream::from($segmentsMax)
            ->map(static fn($segmentMax, $index) => [
                "segmentMax" => $segmentMax,
                "segmentColor" => $segmentsColor[$index],
            ])
            ->toArray();

        $nature
            ->setPrefix($data['prefix'] ?? null)
            ->setColor($data['color'])
            ->setNeedsMobileSync($data['mobileSync'] ?? false)
            ->setDefaultQuantity($data['quantity'])
            ->setDefaultQuantityForDispatch($data['defaultQuantityDispatch'] ?: null)
            ->setDescription($data['description'] ?? null)
            ->setCode($data['code'])
            ->setExceededDelayColor($data["natureExceededDelayColor"] ?? null)
            ->setNatureManager($natureManager)
            ->setTrackingDelay($trackingDelayInSeconds)
            ->setTrackingDelaySegments($trackingDelaySegments);

        $nature->getTemperatureRanges()->clear();
        $allowedTemperatureIds = Stream::explode(",", $data["allowedTemperatures"])
            ->filter()
            ->toArray();
        if (!empty($allowedTemperatureIds)) {
            $temperatureRanges = $temperatureRangeRepository->findBy(["id" => $allowedTemperatureIds]);
            $nature->setTemperatureRanges($temperatureRanges);
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
                $allowedForms[Nature::TRANSPORT_COLLECT_CODE] = Stream::explode(',', $data['transportCollectTypes'])
                    ->filter()
                    ->values();
            }

            if($data[Nature::DISPATCH_CODE]) {
                $allowedForms[Nature::DISPATCH_CODE] = 'all';
            }

            if($data[Nature::TRANSPORT_DELIVERY_CODE]) {
                $allowedForms[Nature::TRANSPORT_DELIVERY_CODE] = Stream::explode(',', $data['transportDeliveryTypes'] ?? '')
                    ->filter()
                    ->values();
            }

            $nature
                ->setDisplayedOnForms(true)
                ->setAllowedForms($allowedForms);
        } else {
            $nature
                ->setDisplayedOnForms(false)
                ->setAllowedForms(null);
        }

        $labelTranslationSource = $nature->getLabelTranslation();
        if($labelTranslationSource) {
            $this->translationService->editEntityTranslations($entityManager, $labelTranslationSource, $labels);
        }
        $frenchLabel = $this->formatService->nature($nature);
        $nature->setLabel($frenchLabel ?: $data['code']);

        $natures = $natureRepository->findAll();

        $default = filter_var($data['default'] ?? false, FILTER_VALIDATE_BOOLEAN);
        if($default) {
            $currentNatureId = $nature->getId();
            $isAlreadyDefault = !Stream::from($natures)
                ->filter(static fn(Nature $nature) => $nature->getDefaultNature() && (!$currentNatureId || $nature->getId() !== $currentNatureId))
                ->isEmpty();

            if(!$isAlreadyDefault) {
                $nature->setDefaultNature($default);
            } else {
                throw new FormException("Une nature par défaut pour les acheminements a déjà été sélectionnée");
            }
        } else {
            $nature->setDefaultNature(false);
        }
    }
}
