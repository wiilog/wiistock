<?php

namespace App\Service;

use App\Controller\Settings\StatusController;
use App\Entity\Action;
use App\Entity\CategorieCL;
use App\Entity\CategorieStatut;
use App\Entity\CategoryType;
use App\Entity\Emplacement;
use App\Entity\Fields\FixedField;
use App\Entity\Fields\FixedFieldByType;
use App\Entity\Fields\FixedFieldEnum;
use App\Entity\Fields\FixedFieldStandard;
use App\Entity\Fields\SubLineFixedField;
use App\Entity\FreeField\FreeField;
use App\Entity\FreeField\FreeFieldManagementRule;
use App\Entity\Inventory\InventoryCategory;
use App\Entity\Inventory\InventoryFrequency;
use App\Entity\Language;
use App\Entity\Menu;
use App\Entity\NativeCountry;
use App\Entity\Nature;
use App\Entity\Reception;
use App\Entity\IOT\AlertTemplate;
use App\Entity\RequestTemplate\CollectRequestTemplate;
use App\Entity\RequestTemplate\DeliveryRequestTemplateSleepingStock;
use App\Entity\RequestTemplate\DeliveryRequestTemplateTriggerAction;
use App\Entity\RequestTemplate\DeliveryRequestTemplateUsageEnum;
use App\Entity\RequestTemplate\HandlingRequestTemplate;
use App\Entity\RequestTemplate\RequestTemplate;
use App\Entity\RequestTemplate\RequestTemplateLine;
use App\Entity\ReserveType;
use App\Entity\Role;
use App\Entity\ScheduledTask\SleepingStockPlan;
use App\Entity\Setting;
use App\Entity\SleepingStockRequestInformation;
use App\Entity\Statut;
use App\Entity\TagTemplate;
use App\Entity\Translation;
use App\Entity\TranslationSource;
use App\Entity\Transport\CollectTimeSlot;
use App\Entity\Transport\TemperatureRange;
use App\Entity\Transport\TransportRoundStartingHour;
use App\Entity\Type;
use App\Entity\Utilisateur;
use App\Entity\VisibilityGroup;
use App\Entity\WorkPeriod\WorkedDay;
use App\Entity\WorkPeriod\WorkFreeDay;
use App\Exceptions\FormException;
use App\Service\IOT\AlertTemplateService;
use App\Service\ScheduleRuleService;
use App\Service\WorkPeriod\WorkPeriodService;
use DateInterval;
use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use JetBrains\PhpStorm\ArrayShape;
use ReflectionClass;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Yaml\Yaml;
use WiiCommon\Helper\Stream;

class SettingsService {

    public const CHARACTER_VALID_REGEX = '^[A-Za-z0-9_\-\/ ]{1,24}$';

    private const DEFAULT_SETTING_VALUES = [
        Setting::FONT_FAMILY => Setting::DEFAULT_FONT_FAMILY,
    ];

    private array $settingsConstants;

    public function __construct(
        private DateTimeService        $dateTimeService,
        private WorkPeriodService      $workPeriodService,
        private KernelInterface        $kernel,
        private AttachmentService      $attachmentService,
        private RequestTemplateService $requestTemplateService,
        private AlertTemplateService   $alertTemplateService,
        private StatusService          $statusService,
        private TranslationService     $translationService,
        private UserService            $userService,
        private CacheService           $cacheService,
        private ScheduleRuleService    $scheduleRuleService,
    ) {
        $reflectionClass = new ReflectionClass(Setting::class);
        $this->settingsConstants = Stream::from($reflectionClass->getConstants())
            ->filter(fn($settingLabel) => is_string($settingLabel))
            ->toArray();
    }

    public function getValue(EntityManagerInterface $entityManager,
                             string                 $settingLabel,
                             string|int|null|bool   $default = null): mixed {
        return $this->cacheService->get(
            CacheService::COLLECTION_SETTINGS,
            $settingLabel,
            static function () use ($settingLabel, $entityManager, $default) {
                $settingRepository = $entityManager->getRepository(Setting::class);
                $setting = $settingRepository->findOneBy(['label' => $settingLabel]);
                return $setting
                    ? $setting->getValue()
                    : (
                        self::DEFAULT_SETTING_VALUES[$settingLabel]
                        ?? $default
                    );
            });
    }

    public function persistSetting(EntityManagerInterface $entityManager, array &$settings, string $key): ?Setting {
        if (!isset($settings[$key])
            && in_array($key, $this->settingsConstants)) {
            $settingRepository = $entityManager->getRepository(Setting::class);
            $setting = $settingRepository->findOneBy(['label' => $key]);
            if (!$setting) {
                $setting = new Setting();
                $setting->setLabel($key);
            }
            $settings[$key] = $setting;

            $entityManager->persist($setting);
        }

        return $settings[$key] ?? null;
    }

    private function isTimeBeforeOrEqual($time1, $time2): bool
    {
        $timestamp1 = strtotime($time1);
        $timestamp2 = strtotime($time2);

        return $timestamp1 < $timestamp2;
    }

    public function save(EntityManagerInterface $entityManager,
                         Request                $request): array {
        $settingRepository = $entityManager->getRepository(Setting::class);

        $beforeStart = $request->request->get("TRUCK_ARRIVALS_PROCESSING_HOUR_CREATE_BEFORE_START");
        $beforeEnd = $request->request->get("TRUCK_ARRIVALS_PROCESSING_HOUR_CREATE_BEFORE_END");
        $afterStart = $request->request->get("TRUCK_ARRIVALS_PROCESSING_HOUR_CREATE_AFTER_START");
        $afterEnd = $request->request->get("TRUCK_ARRIVALS_PROCESSING_HOUR_CREATE_AFTER_END");

        if ((!$beforeStart || !$beforeEnd || !$afterStart || !$afterEnd)
            && ($beforeStart || $beforeEnd || $afterStart || $afterEnd)) {
            throw new RuntimeException("Tous les champs horaires doivent être renseignés.");
        } else if($beforeStart && $beforeEnd && $afterStart && $afterEnd) {
            $isValid = $this->isTimeBeforeOrEqual($beforeStart, $beforeEnd);
            if (!$isValid) {
                throw new RuntimeException('Il est nécessaire que les heures de début soient antérieures aux heures de fin.');
            }
        }

        $defaultLocationUL = $request->request->get("BR_ASSOCIATION_DEFAULT_MVT_LOCATION_UL");
        $defaultLocationReception = $request->request->get("BR_ASSOCIATION_DEFAULT_MVT_LOCATION_RECEPTION_NUM");

        if ($request->request->get('createMvt')) {
            if ($defaultLocationUL === null) {
                throw new RuntimeException("Vous devez sélectionner un emplacement de dépose UL par défaut.");
            }
            if ($defaultLocationReception === null) {
                throw new RuntimeException("Vous devez sélectionner un emplacement de dépose Réception par défaut.");
            }
        }


        $settingNames = array_merge(
            array_keys($request->request->all()),
            array_keys($request->files->all()),
        );

        $allFormSettingNames = json_decode($request->request->get('__form_fieldNames', '[]'), true);

        $result = [];

        $settings = $settingRepository->findByLabel(array_merge($settingNames, $allFormSettingNames));
        if ($request->request->has("datatables")) {
            $this->saveDatatables(
                $entityManager,
                json_decode($request->request->get("datatables"), true),
                $request->request->all(),
                $request->files->all(),
                $result
            );
        }
        $updated = [];

        $this->saveCustom($entityManager, $request, $settings, $updated, $result);
        $this->saveStandard($entityManager, $request, $settings, $updated, $allFormSettingNames);
        $entityManager->flush();
        $this->saveFiles($entityManager, $request, $settings, $allFormSettingNames, $updated);

        $settingNamesToClear = array_diff($allFormSettingNames, $settingNames, $updated);
        $settingToClear = !empty($settingNamesToClear) ? $settingRepository->findByLabel($settingNamesToClear) : [];
        $this->clearSettings($settingToClear);

        $entityManager->flush();

        $this->postSaveTreatment($entityManager, $updated);

        $result = $result ?? [];
        if (isset($result['type'])) {
            /** @var Type $type */
            $type = $result['type'];
            $result['entity'] = [
                'id' => $type->getId(),
                'label' => $type->getLabel(),
            ];
            unset($result['type']);
        } else {
            if (isset($result['template'])) {
                /** @var RequestTemplate $template */
                $template = $result['template'];
                $result['entity'] = [
                    'id' => $template->getId(),
                    'label' => $template->getName(),
                ];
                unset($result['template']);
            }
        }

        return $result;
    }

    /**
     * Saves custom settings
     *
     * @param Setting[] $settings Existing settings
     */
    private function saveCustom(EntityManagerInterface $entityManager,
                                Request                $request,
                                array                  &$settings,
                                array                  &$updated,
                                array                  &$result): void {
        $data = $request->request;
        $settingRepository = $entityManager->getRepository(Setting::class);

        if ($client = $data->get(Setting::APP_CLIENT)) {
            $this->changeClient($client);
            $updated[] = Setting::APP_CLIENT;
        }

        if ($data->has("en_attente_de_réception") && $data->has("réception_partielle") && $data->has("réception_totale") && $data->has("anomalie")) {
            $codes = [
                Reception::STATUT_EN_ATTENTE => $data->get("en_attente_de_réception"),
                Reception::STATUT_RECEPTION_PARTIELLE => $data->get("réception_partielle"),
                Reception::STATUT_RECEPTION_TOTALE => $data->get("réception_totale"),
                Reception::STATUT_ANOMALIE => $data->get("anomalie"),
            ];

            $statuses = $entityManager->getRepository(Statut::class)->findBy(["code" => array_keys($codes)]);
            foreach ($statuses as $status) {
                $status->setNom($codes[$status->getCode()]);
            }
        }

        if ($request->request->has("deliveryRequestBehavior")) {
            $deliveryRequestBehavior = $request->request->get("deliveryRequestBehavior");

            $previousDeliveryRequestBehaviorSetting = $settingRepository->findOneBy([
                'label' => [Setting::DIRECT_DELIVERY, Setting::CREATE_PREPA_AFTER_DL, Setting::CREATE_DELIVERY_ONLY],
                'value' => 1
            ]);
            $previousDeliveryRequestBehaviorSetting?->setValue(0);

            $currentDeliveryRequestBehaviorSetting = $settingRepository->findOneBy(["label" => $deliveryRequestBehavior]);
            $currentDeliveryRequestBehaviorSetting->setValue(1);
            $updated[] = $deliveryRequestBehavior;
        }

        if ($request->request->getBoolean("alertTemplate")) {
            $alertTemplateRepository = $entityManager->getRepository(AlertTemplate::class);
            if (!$request->request->get("entity")) {
                $template = new AlertTemplate();
                $template->setType($request->request->get('type'));
                $entityManager->persist($template);

                $result['template'] = $template;
            } else {
                $template = $entityManager->find(AlertTemplate::class, $request->request->get("entity"));
            }

            $sameName = $alertTemplateRepository->findOneBy(["name" => $request->request->get("name")]);
            if ($sameName && $sameName->getId() !== $template->getId()) {
                throw new RuntimeException("Un modèle de demande avec le même nom existe déjà");
            }

            $this->alertTemplateService->updateAlertTemplate($request, $entityManager, $template);
        }

        if ($request->request->has("temperatureRanges")) {
            $temperatureRepository = $entityManager->getRepository(TemperatureRange::class);
            $existingRanges = Stream::from($temperatureRepository->findAll())
                ->keymap(fn(TemperatureRange $range) => [$range->getValue(), $range])
                ->toArray();
            $removedRanges = Stream::from($existingRanges)->toArray();
            $submittedTemperatureRanges = Stream::explode(",", $request->request->get("temperatureRanges"))
                ->filter()
                ->unique()
                ->toArray();

            foreach ($submittedTemperatureRanges as $temperatureRange) {
                if (!isset($existingRanges[$temperatureRange])) {
                    $range = new TemperatureRange();
                    $range->setValue($temperatureRange);

                    $entityManager->persist($range);
                } else {
                    unset($removedRanges[$temperatureRange]);
                }
            }

            //loop the ranges that have been deleted to check
            //if they were used somewhere else
            /** @var TemperatureRange $entity */
            foreach ($removedRanges as $entity) {
                if (!$entity->getLocations()->isEmpty() || !$entity->getNatures()->isEmpty()) {
                    throw new RuntimeException("La plage de température {$entity->getValue()} ne peut pas être supprimée car elle est utilisée par des natures ou emplacements");
                }else if(!$entity->getTransportDeliveryRequestNatures()->isEmpty()){
                    throw new RuntimeException("La plage de température {$entity->getValue()} ne peut pas être supprimée car elle est utilisée par des demandes de transport");
                } else{
                    $entityManager->remove($entity);
                }
            }

            $updated[] = "temperatureRanges";
        }

        if ($request->request->has('createMvt')) {
            $defaultLocationUL = $request->request->get("BR_ASSOCIATION_DEFAULT_MVT_LOCATION_UL");
            $defaultLocationReception = $request->request->get("BR_ASSOCIATION_DEFAULT_MVT_LOCATION_RECEPTION_NUM");
            $check = $request->request->get('createMvt');

            if (!$check) {
                if ($defaultLocationUL !== null) {
                    $defaultLocationUL = null;
                }
                if ($defaultLocationReception !== null) {
                    $defaultLocationReception = null;
                }
            }

            $settingUL = $settingRepository->findOneBy(["label" => Setting::BR_ASSOCIATION_DEFAULT_MVT_LOCATION_UL]);
            $settingUL->setValue($defaultLocationUL);

            $settingReception = $settingRepository->findOneBy(["label" => Setting::BR_ASSOCIATION_DEFAULT_MVT_LOCATION_RECEPTION_NUM]);
            $settingReception->setValue($defaultLocationReception);

            $updated[] = "BR_ASSOCIATION_DEFAULT_MVT_LOCATION_UL";
            $updated[] = "BR_ASSOCIATION_DEFAULT_MVT_LOCATION_RECEPTION_NUM";
        }

        if ($request->request->has(Setting::MAILER_PASSWORD)) {
            $newMailPassword = $request->request->get(Setting::MAILER_PASSWORD);
            if ($newMailPassword) {
                $settingMailPassword = $this->persistSetting($entityManager, $settings, Setting::MAILER_PASSWORD)?->setValue($newMailPassword) ?:$settingRepository->findOneBy(["label" => Setting::MAILER_PASSWORD]);
                if ($settingMailPassword && $settingMailPassword->getValue() != $newMailPassword) {
                    $settingMailPassword->setValue($newMailPassword);
                }
            }
            $updated[] = Setting::MAILER_PASSWORD;
        }

        if ($request->request->has(Setting::MAX_SESSION_TIME)) {
            $value = $request->request->get(Setting::MAX_SESSION_TIME);

            $valueContainsOnlyDigits = preg_match('/^\d+$/i', $value);
            $valueInt = ((int) $value);
            $maxValue = 1440; // 24h

            if (!$valueContainsOnlyDigits
                || !$valueInt
                || $valueInt > $maxValue) {
                throw new RuntimeException("Le temps de session doit être un entier compris entre 1 et 1440");
            }

            $settingMaxSessionTime = $this->persistSetting($entityManager, $settings, Setting::MAX_SESSION_TIME);
            $settingMaxSessionTime->setValue($valueInt);

            $updated[] = Setting::MAX_SESSION_TIME;
        }

        if ($request->request->has(Setting::MANAGE_DELIVERIES_WITHOUT_STOCK_QUANTITY)
            && $request->request->get(Setting::MANAGE_DELIVERIES_WITHOUT_STOCK_QUANTITY)) {
            if (!$this->getValue($entityManager, Setting::DEFAULT_LOCATION_RECEPTION)) {
                throw new FormException("Veuillez d'abord définir un emplacement de réception par défaut dans les paramètres Stock | Réceptions | Réceptions - Statuts avant de renseigner cette option.");
            }
        }

        if ($request->request->has("maxStorageTime")
            && $request->request->get("planType")) {
            $sleepingStockPlanRepository = $entityManager->getRepository(SleepingStockPlan::class);

            $type = $entityManager->getReference(Type::class, $request->request->get("planType"));

            $sleepingStockPlan = $sleepingStockPlanRepository->findOneBy(["type" => $type])
                ?: (new SleepingStockPlan())->setType($type);

            $sleepingStockPlan->setMaxStorageTime(
                $request->request->getInt("maxStorageTime") * 24 * 60 * 60
            );

            $sleepingStockPlan->setScheduleRule(
                $this->scheduleRuleService->updateRule($sleepingStockPlan->getScheduleRule(), $request->request)
            );

            if (!$sleepingStockPlan->getId()) {
                $entityManager->persist($sleepingStockPlan);
            }
        }
    }

    /**
     * @param Setting[] $settings Existing settings
     */
    private function saveStandard(EntityManagerInterface $entityManager,
                                  Request   $request,
                                  array     &$settings,
                                  array     &$updated,
                                  array     $allFormSettingNames = []): void {
        foreach ($request->request->all() as $key => $value) {
            if (!in_array($key, $updated)
                && !in_array("keep-$key", $allFormSettingNames)
                && !in_array("$key\_DELETED", $allFormSettingNames)) {
                $setting = $this->persistSetting($entityManager, $settings, $key);
                if ($setting) {
                    if (is_array($value)) {
                        $value = json_encode($value);
                    }

                    if ($value !== $setting->getValue()) {
                        $setting->setValue($value);
                        $updated[] = $key;
                    }
                }
            }
        }
    }

    /**
     * @param Setting[] $settings Existing settings
     */
    private function saveFiles(EntityManagerInterface $entityManager, Request $request, array $settings, array $allFormSettingNames, array &$updated): void {
        foreach ($request->files->all() as $key => $value) {
            $setting = $this->persistSetting($entityManager, $settings, $key);
            if (isset($setting)) {
                $fileName = $this->attachmentService->saveFile($value, $key);
                $setting->setValue("uploads/attachments/" . $fileName[array_key_first($fileName)]);
                $updated[] = $key;
            }
        }

        $defaultLogosToSave = [
            [Setting::FILE_WEBSITE_LOGO, Setting::DEFAULT_WEBSITE_LOGO_VALUE],
            [Setting::FILE_MOBILE_LOGO_LOGIN, Setting::DEFAULT_MOBILE_LOGO_LOGIN_VALUE],
            [Setting::FILE_EMAIL_LOGO, Setting::DEFAULT_EMAIL_LOGO_VALUE],
            [Setting::FILE_MOBILE_LOGO_HEADER, Setting::DEFAULT_MOBILE_LOGO_HEADER_VALUE],
            [Setting::FILE_TOP_LEFT_LOGO, Setting::DEFAULT_TOP_LEFT_VALUE],
            [Setting::DELIVERY_STATION_TOP_LEFT_LOGO, Setting::DEFAULT_TOP_LEFT_VALUE],
            [Setting::FILE_TOP_RIGHT_LOGO, null],
            [Setting::DELIVERY_STATION_TOP_RIGHT_LOGO, null],
            [Setting::FILE_LABEL_EXAMPLE_LOGO, Setting::DEFAULT_LABEL_EXAMPLE_VALUE],
            [Setting::FILE_WAYBILL_LOGO, null], // TODO WIIS-8882
            [Setting::FILE_OVERCONSUMPTION_LOGO, null],
            [Setting::FILE_SHIPMENT_NOTE_LOGO, null],
            [Setting::LABEL_LOGO, null],
        ];

        foreach ($defaultLogosToSave as [$defaultLogoLabel, $default]) {
            if (in_array($defaultLogoLabel, $allFormSettingNames)) {
                $setting = $this->persistSetting($entityManager, $settings, $defaultLogoLabel);
                if (!$request->request->getBoolean('keep-' . $defaultLogoLabel)
                    && !isset($files[$defaultLogoLabel])) {
                    $setting->setValue($default);
                }
            }
            $updated[] = $defaultLogoLabel;
        }

        foreach($request->request->all() as $key => $value) {
            if (str_ends_with($key, '_DELETED')) {
                $defaultLogoLabel = str_replace('_DELETED', '', $key);
                $linkedLabel = $defaultLogoLabel . '_FILE_NAME';
                $setting = $this->persistSetting($entityManager, $settings, $defaultLogoLabel);
                $linkedSetting = $this->persistSetting($entityManager, $settings, $linkedLabel);
                if ($value === "1") {
                    $setting->setValue(null);
                    $linkedSetting->setValue(null);
                }
                $updated[] = $defaultLogoLabel;
            }
        }
    }

    //    TODO WIIS-6693 mettre dans des services different ?
    private function saveDatatables(EntityManagerInterface $entityManager,
                                    array                  $tables,
                                    array                  $data,
                                    array                  $files,
                                    array                  &$result): void {
        if (isset($tables["workingHours"])) {
            $ids = Stream::from($tables["workingHours"])
                ->filter(fn(array $day) => !empty($day))
                ->map(fn($day) => $day["id"])
                ->toArray();

            $daysWorkedRepository = $entityManager->getRepository(WorkedDay::class);
            $days = empty($ids)
                ? []
                : Stream::from($daysWorkedRepository->findBy(["id" => $ids]))
                    ->keymap(fn($day) => [$day->getId(), $day])
                    ->toArray();

            $this->dateTimeService->processWorkingHours($tables["workingHours"], $days);
        }

        if (isset($tables["hourShifts"]) && count($tables["hourShifts"]) && count($tables["hourShifts"][0])) {
            $editShift = function(CollectTimeSlot $shift, array $edition) {
                $hours = $edition["hours"] ?? null;
                if ($hours && !preg_match("/^\d{2}:\d{2}-\d{2}:\d{2}$/", $hours)) {
                    throw new RuntimeException("Le champ horaires doit être au format HH:MM-HH:MM");
                } else if ($hours
                    && !preg_match("/^(0\d|1\d|2[0-3]):(0\d|[1-5]\d)-(0\d|1\d|2[0-3]):(0\d|[1-5]\d)?$/",
                        $hours)) {
                    throw new RuntimeException("Les heures doivent être comprises entre 00:00 et 23:59");
                }

                $hours = explode('-', $hours);
                $startHour = str_replace(":","",$hours[0]);
                $endHour = str_replace(":","",$hours[1]);
                if (intval($startHour) >= intval($endHour)) {
                    throw new RuntimeException("L'heure de début doit être inférieur à la date de fin du créneau horaire.");
                }

                $shift
                    ->setName($edition['name'])
                    ->setStart($hours[0])
                    ->setEnd($hours[1]);
            };

            $hourShiftsRepository = $entityManager->getRepository(CollectTimeSlot::class);

            $newShifts = Stream::from($tables["hourShifts"])
                ->filter(fn(array $shift) => !isset($shift['id']))
                ->filter(fn(array $shift) => isset($shift['name']))
                ->toArray();

            $existingShifts = Stream::from($tables["hourShifts"])
                ->filter(fn(array $shift) => isset($shift['id']))
                ->filter(fn(array $shift) => isset($shift['name']))
                ->keymap(fn(array $shift) => [$shift['id'], $shift])
                ->toArray();

            foreach ($hourShiftsRepository->findAll() as $existingShift) {
                if (!empty($existingShifts[$existingShift->getId()])) {
                    $editShift($existingShift, $existingShifts[$existingShift->getId()]);
                } else {
                    $this->deleteTimeSlot($entityManager, $existingShift);
                }
            }

            foreach ($newShifts as $hourShifts) {
                $shift = new CollectTimeSlot();
                $entityManager->persist($shift);
                $editShift($shift, $hourShifts);
            }
        }

        if (isset($tables["offDays"])) {
            $workFreeDayRepository = $entityManager->getRepository(WorkFreeDay::class);
            foreach (array_filter($tables["offDays"]) as $offDay) {
                $date = DateTime::createFromFormat("Y-m-d", $offDay["day"]);
                if ($workFreeDayRepository->findBy(["day" => $date])) {
                    throw new RuntimeException("Le jour " . $date->format("d/m/Y") . " est déjà renseigné");
                }

                $day = new WorkFreeDay();
                $day->setDay($date);

                $entityManager->persist($day);
            }

            $this->workPeriodService->clearCaches();
        }

        if (isset($tables["startingHours"])) {
            $userRepository = $entityManager->getRepository(Utilisateur::class);
            $editShift = function(TransportRoundStartingHour $shift, array $edition) use ($userRepository) {
                $hour = $edition["hour"] ?? null;
                if(!$edition['deliverers']) {
                    throw new RuntimeException("Aucun livreur n'a été sélectionné");
                }

                if ($hour) {
                    if (!preg_match("/^\d{2}:\d{2}$/", $hour)) {
                        throw new RuntimeException("Le champ horaire doit être au format HH:MM");
                    }
                    $shift
                        ->setHour($hour);

                    $userIds = explode(',', $edition['deliverers']);
                    $users = $userRepository->findBy([
                        'id' => $userIds,
                    ]);
                    foreach ($users as $user) {
                        $shift->addDeliverer($user);
                    }
                } else {
                    throw new RuntimeException("Veuillez renseigner tous les champs des heures de départ.");
                }
            };

            $hourShiftsRepository = $entityManager->getRepository(TransportRoundStartingHour::class);

            $newShifts = Stream::from($tables["startingHours"])
                ->filter(fn(array $shift) => !empty($shift) && !isset($shift['id']))
                ->toArray();

            $existingShifts = Stream::from($tables["startingHours"])
                ->filter(fn(array $shift) => isset($shift['id']))
                ->keymap(fn(array $shift) => [$shift->getId(), $shift])
                ->toArray();

            foreach ($hourShiftsRepository->findAll() as $existingShift) {
                if (!empty($existingShifts[$existingShift->getId()])) {
                    $editShift($existingShift, $existingShifts[$existingShift->getId()]);
                } else {
                    $this->deleteStartingHour($entityManager, $existingShift);
                }
            }

            foreach ($newShifts as $hourShifts) {
                $shift = new TransportRoundStartingHour();
                $entityManager->persist($shift);
                $editShift($shift, $hourShifts);
            }
        }

        if (isset($tables["frequencesTable"])) {
            foreach (array_filter($tables["frequencesTable"]) as $frequenceData) {
                $frequenceRepository = $entityManager->getRepository(InventoryFrequency::class);
                $frequence = "";
                if (isset($frequenceData['frequenceId'])) {
                    $frequence = $frequenceRepository->find($frequenceData['frequenceId']);
                } else {
                    $frequence = new InventoryFrequency();
                }
                $frequence->setLabel($frequenceData['label']);
                $frequence->setNbMonths($frequenceData['nbMonths']);

                $entityManager->persist($frequence);
            }
        }

        if (isset($tables["categoriesTable"])) {
            $frequenceRepository = $entityManager->getRepository(InventoryFrequency::class);
            $categoryRepository = $entityManager->getRepository(InventoryCategory::class);

            foreach (array_filter($tables["categoriesTable"]) as $categoryData) {
                $frequence = $frequenceRepository->find($categoryData['frequency']);
                $category = isset($categoryData['categoryId'])
                    ? $categoryRepository->find($categoryData['categoryId'])
                    : new InventoryCategory();

                if(strlen($categoryData['label']) > 32){
                    throw new RuntimeException("Vous ne pouvez pas enregistrer de libellé de plus de 32 caractères. Veuillez raccourcir votre libellé pour enregistrer");
                }
                $category->setLabel($categoryData['label']);
                $category->setFrequency($frequence);

                $entityManager->persist($category);
            }
        }

        if(isset($tables["tagTemplateTable"])) {
            $tagTemplateRepository = $entityManager->getRepository(TagTemplate::class);
            $typeRepository = $entityManager->getRepository(Type::class);
            $natureRepository = $entityManager->getRepository(Nature::class);

            foreach (array_filter($tables["tagTemplateTable"]) as $tagTemplateData) {
                $tagTemplateExist = $tagTemplateRepository->findOneBy(['prefix' => $tagTemplateData['prefix']]);
                if ($tagTemplateExist && $tagTemplateExist->getId() !== intval($tagTemplateData['tagTemplateId'] ?? null)){
                    throw new RuntimeException("Un modèle d'étiquette existe déjà avec ce préfixe.");
                }

                $tagTemplate = isset($tagTemplateData['tagTemplateId'])
                    ? $tagTemplateRepository->find($tagTemplateData['tagTemplateId'])
                    : new TagTemplate();

                if (str_contains($tagTemplateData['prefix'], '/') || str_contains($tagTemplateData['prefix'], '\\')) {
                    throw new FormException("Le préfixe ne doit pas contenir les caractères / ou \\.");
                }

                $tagTemplate->setPrefix($tagTemplateData['prefix']);
                $tagTemplate->setBarcodeOrQr($tagTemplateData['barcodeType']);
                $tagTemplate->setHeight($tagTemplateData['height']);
                $tagTemplate->setWidth($tagTemplateData['width']);
                $tagTemplate->setModule($tagTemplateData['module']);

                $natures = [];
                $types = [];

                Stream::explode(',', $tagTemplateData['natureOrType'])
                    ->filter()
                    ->each(function(int $id) use ($tagTemplateData, $natureRepository, $typeRepository, $tagTemplate, &$natures, &$types) {
                        if($tagTemplateData['module'] === CategoryType::ARRIVAGE) {
                            $nature = $natureRepository->find($id);
                            $natures[] = $nature;
                        } else {
                            $type = $typeRepository->find($id);
                            $types[] = $type;
                        }
                    } );

                $tagTemplate->setNatures(new ArrayCollection($natures));
                $tagTemplate->setTypes(new ArrayCollection($types));

                $entityManager->persist($tagTemplate);
            }
        }

        if (isset($tables["freeFields"])) {
            $categoryFFRepository = $entityManager->getRepository(CategorieCL::class);
            $categoryFF = $categoryFFRepository->findOneBy(['label' => $data["category"]]);

            $ids = array_map(fn($freeField) => $freeField["id"] ?? null, $tables["freeFields"]);
            $freeFieldRepository = $entityManager->getRepository(FreeField::class);
            $freeFields = Stream::from($freeFieldRepository->findBy(["id" => $ids]))
                ->keymap(fn($freeField) => [$freeField->getId(), $freeField])
                ->toArray();

            foreach (array_filter($tables["freeFields"]) as $item) {
                /** @var FreeField $freeField */
                $freeField = isset($item["id"]) ? $freeFields[$item["id"]] : new FreeField();

                $existing = $freeFieldRepository->findOneBy(["label" => $item["label"]]);
                if ($existing && $existing->getId() != $freeField->getId()) {
                    throw new RuntimeException("Un champ libre existe déjà avec le libellé {$item["label"]}");
                }

                if (isset($item["elements"])) {
                    $elements = Stream::explode(";", $item["elements"])
                        ->filterMap(fn(string $element) => trim($element) ?: null)
                        ->toArray();
                }

                if ($item["label"] === "") {
                    throw new RuntimeException("Le libellé du champ libre ne peut pas être vide");
                }

                $minCharactersLength = (isset($item["minCharactersLength"]) && $item["minCharactersLength"] !== "")
                    ? intval($item["minCharactersLength"])
                    : null;

                $maxCharactersLength = (isset($item["maxCharactersLength"]) && $item["maxCharactersLength"] !== "")
                    ? intval($item["maxCharactersLength"])
                    : null;

                if ($minCharactersLength !== null
                    && $maxCharactersLength !== null
                    && ($minCharactersLength > $maxCharactersLength)) {
                    throw new RuntimeException("Le nombre de caractères minimum doit être inférieur au maximum pour le champ libre <strong>{$item["label"]}</strong>.");
                }

                $freeField
                    ->setLabel($item["label"])
                    ->setTypage($item["type"] ?? $freeField->getTypage())
                    ->setCategorieCL(isset($item["category"])
                        ? $categoryFFRepository->findOneBy(['id' => $item["category"]])
                        : $categoryFF
                    )
                    ->setDefaultValue(($item["defaultValue"] ?? null) === "null" ? "" : $item["defaultValue"] ?? null)
                    ->setElements(isset($item["elements"]) ? $elements : null)
                    ->setMinCharactersLength($minCharactersLength)
                    ->setMaxCharactersLength($maxCharactersLength);

                $defaultTranslation = $freeField->getLabelTranslation()?->getTranslationIn(Language::FRENCH_SLUG);
                if ($defaultTranslation) {
                    $defaultTranslation->setTranslation($freeField->getLabel());
                } else {
                    $this->translationService->setDefaultTranslation($entityManager, $freeField, $freeField->getLabel());
                }

                $defaultValue = $freeField->getDefaultValue();
                $defaultValueTranslation = $freeField->getDefaultValueTranslation();
                if($defaultValue && !$defaultValueTranslation) {
                    $this->translationService->setDefaultTranslation($entityManager, $freeField, $freeField->getDefaultValue(), "setDefaultValueTranslation");
                } else if($defaultValue && $defaultValueTranslation) {
                    $translation = $defaultValueTranslation->getTranslationIn(Language::FRENCH_SLUG)
                        ?: (new Translation())
                            ->setLanguage($entityManager->getRepository(Language::class)->findOneBy((['slug'=> Language::FRENCH_SLUG])))
                            ->setSource($defaultValueTranslation);
                    $entityManager->persist($translation);
                    $translation->setTranslation($defaultValue);
                }

                foreach($freeField->getElementsTranslations() as $source) {
                    if(!in_array($source->getTranslationIn(Language::FRENCH_SLUG)->getTranslation(), $freeField->getElements())) {
                        $freeField->removeElementTranslation($source);
                        $entityManager->remove($source);
                    }
                }

                foreach($freeField->getElements() as $element) {
                    $source = $freeField->getElementTranslation($element);
                    if(!$source) {
                        $this->translationService->setDefaultTranslation($entityManager, $freeField, $element, "addElementTranslation");
                    }
                }

                $entityManager->persist($freeField);
            }
        }

        if (isset($tables["freeFieldManagementRules"])) {
            $typeRepository = $entityManager->getRepository(Type::class);
            $categoryTypeRepository = $entityManager->getRepository(CategoryType::class);

            $ids = array_map(fn($freeField) => $freeField["id"] ?? null, $tables["freeFieldManagementRules"]);

            if(isset($data["typeId"])){
                $type = $typeRepository->find($data["typeId"]);
            } else if (isset($data["category"]) && !is_numeric($data["category"])) {
                $category = $categoryTypeRepository->findOneBy(["label" => $data["category"]]);

                $alreadyCreatedType = $typeRepository->count([
                    'label' => $data["label"],
                    'category' => $category,
                ]);

                if ($alreadyCreatedType > 0) {
                    throw new RuntimeException("Le type existe déjà pour cette categorie");
                }

                $type = new Type();
                $type->setCategory($category);
                $entityManager->persist($type);
            }

            if (!isset($type)) {
                throw new RuntimeException("Le type est introuvable");
            }

            if (empty($data["label"])) {
                $data["label"] = $type->getLabel();
            }

            if ($type->getCategory()->getLabel() !== CategoryType::SENSOR && empty($data["label"])) {
                throw new RuntimeException("Vous devez saisir un libellé pour le type");
            }

            $suggestedDropLocations = null;
            if (isset($data["suggestedDropLocations"])) {
                $dropLocation = isset($data["dropLocation"])
                    ? $entityManager->find(Emplacement::class, $data["dropLocation"])->getId()
                    : $type->getDropLocation()?->getId();

                $suggestedDropLocations = !empty($data["suggestedDropLocations"]) ? explode(',', $data["suggestedDropLocations"]) : [];

                if (!empty($suggestedDropLocations) && $dropLocation && !in_array($dropLocation, $suggestedDropLocations)) {
                    throw new RuntimeException("L'emplacement de dépose par défaut doit être compris dans les emplacements de dépose suggérés");
                }
            }

            $suggestedPickLocations = null;
            if (isset($data["suggestedPickLocations"])) {
                $pickLocation = isset($data["pickLocation"])
                    ? $entityManager->find(Emplacement::class, $data["pickLocation"])->getId()
                    : $type->getPickLocation()?->getId();

                $suggestedPickLocations = !empty($data["suggestedPickLocations"]) ? explode(',', $data["suggestedPickLocations"]) : [];;

                if (!empty($suggestedPickLocations) && $pickLocation && !in_array($pickLocation, $suggestedPickLocations)) {
                    throw new RuntimeException("L'emplacement de prise par défaut doit être compris dans les emplacements de prise suggérés");
                }
            }

            if(isset($data["active"]) && $type->getId()){
                $categoryTypeId = $type->getCategory()->getId();
                $countActiveTypeByCategoryType = $typeRepository->countActiveTypeByCategoryType($categoryTypeId);

                if($countActiveTypeByCategoryType <= 1 && !$data["active"]){
                    throw new RuntimeException("Au moins un type doit être actif pour cette entité.");
                }
            }

            if(isset($data["averageTime"])){
                $averageTime = $data["averageTime"];
                if(!preg_match("/" . DateTimeService::AVERAGE_TIME_REGEX . "/", $averageTime)){
                    throw new RuntimeException("Le temps moyen doit être au format HH:MM");
                }
            }

            $newLabel = $data["label"] ?? $type->getLabel();
            $type
                ->setLabel($newLabel)
                ->setDescription($data["description"] ?? null)
                ->setPickLocation(isset($data["pickLocation"]) ? $entityManager->find(Emplacement::class, $data["pickLocation"]) : null)
                ->setDropLocation(isset($data["dropLocation"]) ? $entityManager->find(Emplacement::class, $data["dropLocation"]) : null)
                ->setSuggestedPickLocations($suggestedPickLocations)
                ->setSuggestedDropLocations($suggestedDropLocations)
                ->setNotificationsEnabled($data["pushNotifications"] ?? false)
                ->setNotificationsEmergencies(isset($data["notificationEmergencies"]) ? explode(",", $data["notificationEmergencies"]) : null)
                ->setSendMailRequester($data["mailRequester"] ?? false)
                ->setSendMailReceiver($data["mailReceiver"] ?? false)
                ->setReusableStatuses($data["reusableStatuses"] ?? false)
                ->setActive($data["active"] ?? true)
                ->setColor($data["color"] ?? null)
                ->setAverageTime($data["averageTime"] ?? null)
                ->setCreateDropMovementById($data["createDropMovementById"] ?? null);

            if(isset($data["createdIdentifierNature"])) {
                $natureRepository = $entityManager->getRepository(Nature::class);
                $nature = $natureRepository->findOneBy([
                    "id" => $data["createdIdentifierNature"]
                ]);
                $type->setCreatedIdentifierNature($nature);
            } else {
                $type->setCreatedIdentifierNature(null);
            }


            $defaultTranslation = $type->getLabelTranslation()?->getTranslationIn(Language::FRENCH_SLUG);
            if ($defaultTranslation) {
                $defaultTranslation->setTranslation($newLabel);
            } else {
                $this->translationService->setDefaultTranslation($entityManager, $type, $newLabel);
            }

            if(isset($data["isDefault"])) {
                if($data["isDefault"]) {
                    $alreadyByDefaultType = $typeRepository->findOneBy(['category' => $type->getCategory(), 'defaultType' => true]);
                    if($alreadyByDefaultType) {
                        $alreadyByDefaultType->setDefault(false);
                    }
                }

                $type->setDefault($data["isDefault"]);
            }

            if (isset($files["logo"])) {
                $logoAttachment = $this->attachmentService->persistAttachment($entityManager, $files["logo"]);
                $type->setLogo($logoAttachment);
            } else {
                if (isset($data["keep-logo"]) && !$data["keep-logo"]) {
                    $type->setLogo(null);
                }
            }

            $freeFieldManagementRuleRepository = $entityManager->getRepository(FreeFieldManagementRule::class);
            $freeFieldRepository = $entityManager->getRepository(FreeField::class);

            $freeFieldManagementRules = Stream::from($freeFieldManagementRuleRepository->findBy(["id" => $ids]))
                ->keymap(fn(FreeFieldManagementRule $freeFieldManagementRule) => [$freeFieldManagementRule->getId(), $freeFieldManagementRule])
                ->toArray();

            $treatedfreeFieldManagementRules = [];
            foreach (array_filter($tables["freeFieldManagementRules"]) as $item) {
                /** @var FreeField $freeField */
                $freeFieldManagementRule = isset($item["id"]) ? $freeFieldManagementRules[$item["id"]] : new FreeFieldManagementRule();
                $freeField = $freeFieldRepository->find($item["freeField"]);
                $existing = $treatedfreeFieldManagementRules[$freeField->getId() . "-" . $type->getId()] ?? $freeFieldManagementRuleRepository->findOneBy([
                    "freeField" => $freeField,
                    "type" => $type
                ]);
                if ($existing && $existing->getId() != $freeFieldManagementRule->getId()) {
                    throw new RuntimeException("le champ libre {$existing->getFreeField()->getLabel()} peut être associé qu'une seule fois à un type");
                }

                $freeFieldManagementRule
                    ->setType($type)
                    ->setFreeField($freeField)
                    ->setDisplayedCreate($item["displayedCreate"])
                    ->setDisplayedEdit($item["displayedEdit"] ?? true)
                    ->setRequiredCreate($item["requiredCreate"])
                    ->setRequiredEdit($item["requiredEdit"]);

                $entityManager->persist($freeFieldManagementRule);
                $treatedfreeFieldManagementRules[$freeField->getId() . "-" . $type->getId()] = $freeFieldManagementRule;
            }
        }

        if (isset($tables["fixedFields"])) {
            $ids = array_map(fn($freeField) => $freeField["id"] ?? null, $tables["fixedFields"]);

            $fieldsParamRepository = $entityManager->getRepository(FixedFieldStandard::class);
            $fieldsParams = Stream::from($fieldsParamRepository->findBy(["id" => $ids]))
                ->keymap(fn($day) => [$day->getId(), $day])
                ->toArray();

            foreach (array_filter($tables["fixedFields"]) as $item) {
                /** @var FixedFieldStandard $subLineFieldParam */
                $subLineFieldParam = $fieldsParams[$item["id"]] ?? null;

                if ($subLineFieldParam) {
                    $code = $subLineFieldParam->getFieldCode();
                    $alwaysRequired = in_array($code, FixedField::ALWAYS_REQUIRED_FIELDS);
                    $subLineFieldParam
                        ->setDisplayedCreate($item["displayedCreate"] ?? null)
                        ->setRequiredCreate($alwaysRequired || ($item["requiredCreate"] ?? null))
                        ->setKeptInMemory($item["keptInMemory"] ?? null)
                        ->setDisplayedEdit($item["displayedEdit"] ?? null)
                        ->setRequiredEdit($alwaysRequired || ($item["requiredEdit"] ?? null))
                        ->setDisplayedFilters($item["displayedFilters"] ?? null);
                }
            }
        }

        if (isset($tables["fixedFieldsByType"]) && isset($data["type"])) {
            $typeRepository = $typeRepository ?? $entityManager->getRepository(Type::class);
            $fixedFieldByTypeRepository = $entityManager->getRepository(FixedFieldByType::class);
            $type = $typeRepository->find($data["type"]);
            $paramById =  Stream::from($tables["fixedFieldsByType"])
                ->keymap(fn($fieldParam) => [$fieldParam["id"], $fieldParam]);

            $fixedField = $fixedFieldByTypeRepository->findBy(["id" => Stream::keys($paramById)->toArray()]);
            foreach ($fixedField as $field) {
                $fieldParam = $paramById[$field->getId()];
                foreach ($fieldParam as $key => $value) {
                    if ($key !== "id") {
                        $method = ($value ? 'add' : 'remove') . ucfirst($key);
                        $field->$method($type);
                    }
                }
            }
        }

        if (isset($tables["subFixedFields"])) {
            $ids = array_map(fn($freeField) => $freeField["id"] ?? null, $tables["subFixedFields"]);

            $subLineFieldsParamRepository = $entityManager->getRepository(SubLineFixedField::class);
            $fieldsParams = Stream::from($subLineFieldsParamRepository->findBy(["id" => $ids]))
                ->keymap(fn($day) => [$day->getId(), $day])
                ->toArray();

            foreach (array_filter($tables["subFixedFields"]) as $item) {
                /** @var SubLineFixedField|null $subLineFieldParam */
                $subLineFieldParam = $fieldsParams[$item["id"]] ?? null;

                if ($subLineFieldParam) {
                    $subLineFieldCanBeDisplayedUnderCondition = !in_array($subLineFieldParam->getFieldCode(), SubLineFixedField::DISABLED_DISPLAYED_UNDER_CONDITION[$subLineFieldParam->getEntityCode()] ?? []);
                    $displayedUnderCondition = ($item["displayedUnderCondition"] ?? false) && $subLineFieldCanBeDisplayedUnderCondition;
                    $conditionFixedFieldValue = Stream::explode(",", $subLineFieldCanBeDisplayedUnderCondition ? ($item["conditionFixedFieldValue"] ?? "") : "")
                        ->filter()
                        ->toArray();

                    if ($displayedUnderCondition && empty($conditionFixedFieldValue)) {
                        throw new FormException("Vous devez saisir la colonne valeur");
                    }

                    $subLineFieldRequired = ($item["required"] ?? false )
                        && !in_array($subLineFieldParam->getFieldCode(), SubLineFixedField::DISABLED_REQUIRED[$subLineFieldParam->getEntityCode()] ?? []);

                    $subLineFieldParam
                        ->setDisplayed($item["displayed"] ?? null)
                        ->setConditionFixedField($item["conditionFixedField"] ?? null)
                        ->setRequired($subLineFieldRequired)
                        ->setDisplayedUnderCondition($displayedUnderCondition)
                        ->setConditionFixedFieldValue($conditionFixedFieldValue);
                }
            }
        }

        if (isset($tables["visibilityGroup"])) {
            foreach (array_filter($tables["visibilityGroup"]) as $visibilityGroupData) {
                $visibilityGroupRepository = $entityManager->getRepository(VisibilityGroup::class);
                if (isset($visibilityGroupData['visibilityGroupId'])) {
                    $visibilityGroup = $visibilityGroupRepository->find($visibilityGroupData['visibilityGroupId']);
                } else {
                    $visibilityGroup = new VisibilityGroup();
                }
                $visibilityGroup->setLabel($visibilityGroupData['label']);
                $visibilityGroup->setDescription($visibilityGroupData['description']);
                $visibilityGroup->setActive($visibilityGroupData['actif']);

                $entityManager->persist($visibilityGroup);
            }
        }

        if (isset($tables["typesLitigeTable"])) {
            foreach (array_filter($tables["typesLitigeTable"]) as $typeLitigeData) {
                $typeLitigeRepository = $entityManager->getRepository(Type::class);
                $categoryLitige = $entityManager->getRepository(CategoryType::class)
                    ->findOneBy(['label' => CategoryType::DISPUTE]);

                if (isset($typeLitigeData['typeLitigeId'])) {
                    $typeLitige = $typeLitigeRepository->find($typeLitigeData['typeLitigeId']);
                } else {
                    $typeLitige = new Type();
                    $typeLitige->setCategory($categoryLitige);
                }

                $typeLitige->setLabel($typeLitigeData['label']);
                $typeLitige->setDescription($typeLitigeData['description'] ?? "");

                $entityManager->persist($typeLitige);
            }
        }

        if (isset($tables["genericStatuses"])) {
            $statusesData = array_filter($tables["genericStatuses"]);

            if (!empty($statusesData)) {
                $statusRepository = $entityManager->getRepository(Statut::class);
                $categoryRepository = $entityManager->getRepository(CategorieStatut::class);
                $typeRepository = $entityManager->getRepository(Type::class);
                $languageRepository = $entityManager->getRepository(Language::class);
                $userRepository = $entityManager->getRepository(Utilisateur::class);
                $roleRepository = $entityManager->getRepository(Role::class);

                $hasRightGroupedSignature = $this->userService->hasRightFunction(Menu::PARAM, Action::SETTINGS_DISPLAY_GROUPED_SIGNATURE_SETTINGS);

                $categoryName = match ($statusesData[0]['mode']) {
                    StatusController::MODE_ARRIVAL_DISPUTE => CategorieStatut::DISPUTE_ARR,
                    StatusController::MODE_RECEPTION_DISPUTE => CategorieStatut::LITIGE_RECEPT,
                    StatusController::MODE_PURCHASE_REQUEST => CategorieStatut::PURCHASE_REQUEST,
                    StatusController::MODE_ARRIVAL => CategorieStatut::ARRIVAGE,
                    StatusController::MODE_DISPATCH => CategorieStatut::DISPATCH,
                    StatusController::MODE_HANDLING => CategorieStatut::HANDLING,
                    StatusController::MODE_PRODUCTION => CategorieStatut::PRODUCTION,
                };

                $category = $categoryRepository->findOneBy(['nom' => $categoryName]);
                $persistedStatuses = $statusRepository->findBy([
                    'categorie' => $category,
                ]);

                $countOverconsumptionBillGenerationStatus = Stream::from($statusesData)
                    ->filter(fn(array $statusData) => ($statusData['overconsumptionBillGenerationStatus'] ?? null) === "1")
                    ->count();
                if ($countOverconsumptionBillGenerationStatus > 1) {
                    throw new FormException('Un seul statut peut être sélectionné pour le changement dès génération du bon de surconsommation');
                }

                // check if "passStatusAtPurchaseOrderGeneration" attributes is true only one time
                $countPassStatusAtPurchaseOrderGeneration = Stream::from($statusesData)
                    ->filter(fn(array $statusData) => ($statusData['passStatusAtPurchaseOrderGeneration'] ?? null) === "1")
                    ->count();
                if ($countPassStatusAtPurchaseOrderGeneration > 1) {
                    throw new FormException('Un seul statut peut être sélectionné pour le passage du statut à la génération de la commande');
                }

                // check if "createDropMovementOnDropLocation" attributes is true only one time
                $countCreateDropMovementOnDropLocation = Stream::from($statusesData)
                    ->filter(fn(array $statusData) => ($statusData['createDropMovementOnDropLocation'] ?? null) === "1")
                    ->count();
                if ($countCreateDropMovementOnDropLocation > 1) {
                    throw new FormException("Un seul statut peut être sélectionné pour la création d'un mouvement de dépose sur l'emplacement de dépose");
                }

                foreach ($statusesData as $statusData) {
                    // check if "passStatusAtPurchaseOrderGeneration" attributes have valid status (only DRAFT and NOT_TREATED)
                    if($statusData['passStatusAtPurchaseOrderGeneration'] ?? false == "1"){
                        if(in_array($statusData['state'], [Statut::NOT_TREATED, Statut::DRAFT])){
                            throw new FormException("Le paramétrage 'Passage au statut à la génération du bon de commande' ne peut être activé que pour les statuts 'Brouillon' ou 'Non traité'");
                        }
                    }

                    if (!in_array($statusData['state'], [
                        Statut::TREATED, Statut::NOT_TREATED, Statut::DRAFT, Statut::IN_PROGRESS, Statut::DISPUTE,
                        Statut::PARTIAL,
                    ])) {
                        throw new RuntimeException("L'état du statut est invalide");
                    }

                    if (isset($statusData['statusId'])) {
                        $status = Stream::from($persistedStatuses)
                            ->filter(fn(Statut $status) => $status->getId() == $statusData['statusId'])
                            ->first();

                        if (!$status) {
                            $status = $statusRepository->find($statusData['statusId']);
                            $persistedStatuses[] = $status;
                        }
                    } else {
                        $status = new Statut();
                        $statusData['category'] = $categoryName;
                        $status->setCategorie($category);

                        // we set type only on creation
                        if (isset($statusData['type'])) {
                            $status->setType($typeRepository->find($statusData['type']));
                        }
                        $persistedStatuses[] = $status;
                    }

                    $notifiedUsers = [];
                    if (isset($statusData["notifiedUsers"]) && $statusData["notifiedUsers"]) {
                        $notifiedUsers = $userRepository->findBy(["id" => explode(",", $statusData["notifiedUsers"])]);
                    }

                    $status
                        ->setNom($statusData['label'])
                        ->setState($statusData['state'])
                        ->setComment($statusData['comment'] ?? null)
                        ->setDefaultForCategory($statusData['defaultStatut'] ?? false)
                        ->setSendNotifToBuyer($statusData['sendMailBuyers'] ?? false)
                        ->setSendNotifToDeclarant($statusData['sendMailRequesters'] ?? false)
                        ->setSendNotifToRecipient($statusData['sendMailDest'] ?? false)
                        ->setNeedsMobileSync($statusData['needsMobileSync'] ?? false)
                        ->setAutomaticReceptionCreation($statusData['automaticReceptionCreation'] ?? false)
                        ->setOverconsumptionBillGenerationStatus($statusData['overconsumptionBillGenerationStatus'] ?? false)
                        ->setDisplayOrder($statusData['order'] ?? 0)
                        ->setOverconsumptionBillGenerationStatus($statusData['overconsumptionBillGenerationStatus'] ?? false)
                        ->setDisplayOnSchedule($statusData['displayedOnSchedule'] ?? false)
                        ->setCreateDropMovementOnDropLocation($statusData['createDropMovementOnDropLocation'] ?? false)
                        ->setNotifiedUsers($notifiedUsers)
                        ->setRequiredAttachment($statusData['requiredAttachment'] ?? false)
                        ->setColor($statusData['color'] ?? null)
                        ->setPreventStatusChangeWithoutDeliveryFees($statusData['preventStatusChangeWithoutDeliveryFees'] ?? false)
                        ->setPassStatusAtPurchaseOrderGeneration($statusData['passStatusAtPurchaseOrderGeneration'] ?? false);

                    if(isset($statusData['typeForGeneratedDispatchOnStatusChange'])){
                        $dispatchRequestType = $typeRepository->findOneBy(['id' => $statusData['typeForGeneratedDispatchOnStatusChange']]);
                        $status->setTypeForGeneratedDispatchOnStatusChange($dispatchRequestType);
                    }

                    if(isset($statusData['allowedCreationForRoles'])) {
                        $allowedCreationForRoles = $roleRepository->findBy(["id" => explode(',', $statusData['allowedCreationForRoles']) ?? ""]);
                        $status->setAuthorizedRequestCreationRoles($allowedCreationForRoles);
                    }

                    if($hasRightGroupedSignature){
                        $status
                            ->setSendReport($statusData['sendReport'] ?? false)
                            ->setCommentNeeded($statusData['commentNeeded'] ?? false)
                            ->setGroupedSignatureType($statusData['groupedSignatureType'] ?? '')
                            ->setGroupedSignatureColor($statusData['color'] ?? Statut::GROUPED_SIGNATURE_DEFAULT_COLOR);
                    }

                    // label given on creation or edit is the French one
                    $labelTranslation = $status->getLabelTranslation();
                    if(!$labelTranslation) {
                        $labelTranslation = new TranslationSource();
                        $entityManager->persist($labelTranslation);

                        $status->setLabelTranslation($labelTranslation);
                    }

                    $translation = $labelTranslation->getTranslationIn(Language::FRENCH_SLUG);

                    if (!$translation) {
                        $language = $languageRepository->findOneBy(['slug' => Language::FRENCH_SLUG]);
                        $translation = new Translation();
                        $translation
                            ->setSource($labelTranslation)
                            ->setLanguage($language);
                        $entityManager->persist($translation);
                    }

                    $translation->setTranslation($statusData['label']);
                    $entityManager->persist($status);
                }
                $validation = $this->statusService->validateStatusesData($persistedStatuses);

                if (!$validation['success']) {
                    throw new RuntimeException($validation['message']);
                }
            }
        }

        if (isset($tables["requestTemplates"])) {
            $ids = array_map(fn($line) => $line["id"] ?? null, $tables["requestTemplates"]);
            $requestTemplateRepository = $entityManager->getRepository(RequestTemplate::class);
            $typeRepository = $entityManager->getRepository(Type::class);
            if (is_numeric($data["typeId"])){
                $template = $entityManager->find(RequestTemplate::class, $data["typeId"]);
            } else {
                $template = match ($data["entityType"]) {
                    Type::LABEL_DELIVERY => match ($data["deliveryRequestTemplateUsage"] ?? null) {
                        DeliveryRequestTemplateUsageEnum::TRIGGER_ACTION->value => new DeliveryRequestTemplateTriggerAction(),
                        DeliveryRequestTemplateUsageEnum::SLEEPING_STOCK->value => new DeliveryRequestTemplateSleepingStock(),
                        default => throw new BadRequestException()
                    },
                    Type::LABEL_COLLECT => new CollectRequestTemplate(),
                    default => new HandlingRequestTemplate(),

                };

                $template->setType($typeRepository->findOneByCategoryLabelAndLabel(CategoryType::REQUEST_TEMPLATE, $data["entityType"]));

                $entityManager->persist($template);

                $result['template'] = $template;
            }
            $sameName = $requestTemplateRepository->findOneBy(["name" => $data["name"]]);
            if ($sameName && $sameName->getId() !== $template->getId()) {
                throw new RuntimeException("Un modèle de demande avec le même nom existe déjà");
            }

            $this->requestTemplateService->updateRequestTemplate($template, $data, $files);

            if($template instanceof DeliveryRequestTemplateTriggerAction && $template->getUsage() !== DeliveryRequestTemplateUsageEnum::TRIGGER_ACTION) {
                $tables["requestTemplates"] = [];
            }
            $requestTemplateLineRepository = $entityManager->getRepository(RequestTemplateLine::class);
            $lines = Stream::from($requestTemplateLineRepository->findBy(["id" => $ids]))
                ->keymap(fn($line) => [$line->getId(), $line])
                ->toArray();

            foreach (array_filter($tables["requestTemplates"]) as $item) {
                /** @var FreeField $freeField */
                $line = isset($item["id"]) ? $lines[$item["id"]] : new RequestTemplateLine();

                $line->setRequestTemplate($template);

                $this->requestTemplateService->updateRequestTemplateLine($line, $item);

                $entityManager->persist($line);
            }
        }

        if (isset($tables["nativeCountriesTable"])) {
            $nativeCountriesData = array_filter($tables["nativeCountriesTable"]);
            $nativeCountryRepository = $entityManager->getRepository(NativeCountry::class);

            if (!empty($nativeCountriesData)) {
                foreach ($nativeCountriesData as $nativeCountryData) {
                    $persistedNativeCountries = [];
                    if (isset($nativeCountryData['nativeCountryId'])) {
                        $nativeCountry = Stream::from($persistedNativeCountries)
                            ->filter(fn(NativeCountry $nativeCountry) => $nativeCountry->getId() == $nativeCountryData['nativeCountryId'])
                            ->first();

                        if (!$nativeCountry) {
                            $nativeCountry = $nativeCountryRepository->find($nativeCountryData['nativeCountryId']);
                            $persistedNativeCountries[] = $nativeCountry;
                        }
                    } else {
                        $nativeCountry = new NativeCountry();
                        $persistedNativeCountries[] = $nativeCountry;
                    }

                    $nativeCountry
                        ->setCode($nativeCountryData['code'])
                        ->setLabel($nativeCountryData['label'])
                        ->setActive($nativeCountryData['active']);

                    $entityManager->persist($nativeCountry);
                }
            }
        }

        if (isset($tables["TruckArrivalReserves"])) {
            $reserveTypesData = array_filter($tables["TruckArrivalReserves"]);
            $reserveTypeRepository = $entityManager->getRepository(ReserveType::class);
            $userRepository = $entityManager->getRepository(Utilisateur::class);

            if (!empty($reserveTypesData)) {
                $isDefaultReserveTypes = Stream::from($reserveTypesData)->filter(fn($data) => $data['defaultReserveType'] === '1')->count();
                if ($isDefaultReserveTypes !== 1) {
                    throw new RuntimeException("Il doit y avoir un seul type de réserve par défaut.");
                }

                $labelReserveTypes = Stream::from($reserveTypesData)->map(fn($data) => $data['label'])->toArray();
                $nbLabelsWithoutDoubles = count(array_unique($labelReserveTypes));
                if ($nbLabelsWithoutDoubles != count($reserveTypesData)) {
                    throw new RuntimeException("Il ne peut pas y avoir plusieurs fois le même libellé de type de réserve.");
                }

                foreach ($reserveTypesData as $reserveTypeData) {
                    $persistedReserveTypes = [];
                    if (isset($reserveTypeData['id'])) {
                        $reserveType = Stream::from($persistedReserveTypes)
                            ->filter(fn(ReserveType $reserveType) => $reserveType->getId() == $reserveTypeData['id'])
                            ->first();

                        if (!$reserveType) {
                            $reserveType = $reserveTypeRepository->find($reserveTypeData['id']);
                            $persistedReserveTypes[] = $reserveType;
                        }
                    } else {
                        $reserveType = new ReserveType();
                        $persistedReserveTypes[] = $reserveType;
                    }

                    if (isset($reserveTypeData['emails']) && $reserveTypeData['emails'] != "") {
                        $emails = explode(',', $reserveTypeData['emails']);
                        $notifiedUsers = Stream::from($emails)
                            ->map(fn($userId) => $userRepository->find($userId))
                            ->toArray();
                    } else {
                        $notifiedUsers = [];
                    }

                    if($reserveTypeData['defaultReserveType'] && !$reserveTypeData['active']){
                        throw new RuntimeException("Impossible de rendre inactif le type de réserve par défaut.");
                    }

                    $reserveType
                        ->setLabel($reserveTypeData['label'])
                        ->setNotifiedUsers(!empty($notifiedUsers) ? $notifiedUsers : null)
                        ->setDefaultReserveType($reserveTypeData['defaultReserveType'])
                        ->setActive($reserveTypeData['active'])
                        ->setDisableTrackingNumber($reserveTypeData['disableTrackingNumber']);

                    $entityManager->persist($reserveType);
                }
            }
        }

        if (isset($tables["sleepingStockRequestInformations"])) {
            $sleepingStockRequestInformationRepository = $entityManager->getRepository(SleepingStockRequestInformation::class);

            $sleepingStockRequestInformations = Stream::from($sleepingStockRequestInformationRepository->findAll())
                ->keymap(fn(SleepingStockRequestInformation $sleepingStockRequestInformation) => [
                    $sleepingStockRequestInformation->getId(),
                    $sleepingStockRequestInformation,
                ])
                ->toArray();

            foreach (array_filter($tables["sleepingStockRequestInformations"]) as $sleepingStockRequestInformationData) {
                $id = $sleepingStockRequestInformationData["id"] ?? null;
                $sleepingStockRequestInformation = ($id)
                    ? $sleepingStockRequestInformations[$id]
                    : new SleepingStockRequestInformation;

                $typeReference = $entityManager->getReference(
                    DeliveryRequestTemplateSleepingStock::class,
                    $sleepingStockRequestInformationData["deliveryRequestTemplate"]
                );
                $sleepingStockRequestInformation
                    ->setDeliveryRequestTemplate($typeReference)
                    ->setButtonActionLabel($sleepingStockRequestInformationData["buttonLabel"]);

                if (!$sleepingStockRequestInformation->getId()) {
                    $entityManager->persist($sleepingStockRequestInformation);
                }
            }
        }
    }

    /**
     * Runs utilities when needed after settings have been saved
     * such as cache clearing translation updates or webpack build
     */
    private function postSaveTreatment(EntityManagerInterface $entityManager,
                                       array                  $updated): void {
        $this->getTimestamp(true);

        foreach ($updated as $setting) {
            $this->cacheService->delete(CacheService::COLLECTION_SETTINGS, $setting);
        }

        if (array_intersect($updated, [Setting::MAX_SESSION_TIME])) {
            $this->generateSessionConfig($entityManager);
            $this->cacheClear();
        }
    }

    public function changeClient(string $client) {
        $configPath = "/etc/php82/php-fpm.conf";

        //if we're not on a kubernetes pod => file doesn't exist => ignore
        if (!file_exists($configPath)) {
            throw new RuntimeException("Le client ne peut pas être modifié sur cette instance");
        }

        try {
            $config = file_get_contents($configPath);
            $newAppClient = "env[APP_CLIENT] = $client";

            $config = preg_replace("/^env\[APP_CLIENT\] = .*$/mi", $newAppClient, $config);
            file_put_contents($configPath, $config);

            //magie noire qui recharge la config php fpm sur les pods kubernetes :
            //pgrep recherche l'id du processus de php fpm
            //kill envoie un message USR2 (qui veut dire "recharge la configuration") à phpfpm
            exec("kill -USR2 $(pgrep -o php-fpm)");
        } catch (Exception $exception) {
            throw new RuntimeException("Une erreur est survenue lors du changement de client");
        }
    }

    public function generateSessionConfig(EntityManagerInterface $entityManager): void {
        $sessionLifetime = $this->getValue($entityManager, Setting::MAX_SESSION_TIME);

        $generated = "{$this->kernel->getProjectDir()}/config/generated.yaml";
        $config = [
            "parameters" => [
                "session_lifetime" => $sessionLifetime * 60,
            ],
        ];

        file_put_contents($generated, Yaml::dump($config));
    }

    public function getTimestamp(bool $reset = false): string {
        if ($reset) {
            $this->cacheService->delete(CacheService::COLLECTION_SETTINGS, "timestamp");
        }

        return $this->cacheService->get(CacheService::COLLECTION_SETTINGS, "timestamp", fn() => time());
    }

    public function cacheClear(): void {
        $env = $this->kernel->getEnvironment();
        $application = new Application($this->kernel);
        $application->setAutoExit(false);

        $input = new ArrayInput([
            "command" => "cache:warmup",
            "--env" => $env,
        ]);

        $output = new BufferedOutput();
        $application->run($input, $output);
    }

    /**
     * @param Setting[] $settings
     */
    private function clearSettings(array $settings): void {
        foreach ($settings as $setting) {
            $setting->setValue(null);
        }
    }

    #[ArrayShape(["logo" => "mixed", "height" => "mixed", "width" => "mixed", "isCode128" => "mixed"])]
    public function getDimensionAndTypeBarcodeArray(EntityManagerInterface $entityManager): array {
        return [
            "logo" => $this->getValue($entityManager, Setting::LABEL_LOGO),
            "height" => $this->getValue($entityManager, Setting::LABEL_HEIGHT) ?? 0,
            "width" => $this->getValue($entityManager, Setting::LABEL_WIDTH) ?? 0,
            "isCode128" => $this->getValue($entityManager, Setting::BARCODE_TYPE_IS_128),
        ];
    }

    public function getParamLocation(EntityManagerInterface $entityManager, string $label) {
        $emplacementRepository = $entityManager->getRepository(Emplacement::class);

        $locationId = $this->getValue($entityManager, $label);

        if ($locationId) {
            $location = $emplacementRepository->find($locationId);

            if ($location) {
                $resp = [
                    'id' => $locationId,
                    'text' => $location->getLabel(),
                ];
            }
        }

        return $resp ?? null;
    }

    public function getDefaultDeliveryLocationsByType(EntityManagerInterface $entityManager): array {
        $typeRepository = $entityManager->getRepository(Type::class);
        $locationRepository = $entityManager->getRepository(Emplacement::class);
        $fieldsParamRepository = $entityManager->getRepository(FixedFieldStandard::class);

        $defaultDeliveryLocationsParam = $fieldsParamRepository->getElements(FixedFieldStandard::ENTITY_CODE_DEMANDE, FixedFieldStandard::FIELD_CODE_DESTINATION_DEMANDE);
        $defaultDeliveryLocationsIds = $defaultDeliveryLocationsParam;

        $defaultDeliveryLocations = [];
        foreach ($defaultDeliveryLocationsIds as $typeId => $locationId) {
            if ($typeId !== 'all' && $typeId) {
                $type = $typeRepository->find($typeId);
                $typeOption = $type
                    ? [
                        'id' => $type->getId(),
                       'label' => $type->getLabel(),
                    ]
                    : null;
                // Déclarer une variable qui vaut 1013 et 1014
            }elseif ($typeId === 'all') {
                $typeOption = [
                    'id' => 'all',
                    'label' => 'Tous les types',
                ];
                // Déclarer une variable qui vaut id => 'all'
            }
            if ($locationId) {
                $location = $locationRepository->find($locationId);
            }

            if (isset($location) && $typeOption) {
                $defaultDeliveryLocations[] = [
                    'value' => [
                        'id' => $location->getId(),
                        'label' => $location->getLabel(),
                    ],
                    // Remplacer par la
                    'type' => $typeOption ?? null,
                ];
            }
        }
        return $defaultDeliveryLocations;
    }

    public function getDefaultProductionExpectedAtByType(EntityManagerInterface $entityManager): array {

        $fixedFieldByTypeRepository = $entityManager->getRepository(FixedFieldByType::class);
        $typeRepository = $entityManager->getRepository(Type::class);
        $expectedAtField = $fixedFieldByTypeRepository->findOneBy(['entityCode' => FixedFieldStandard::ENTITY_CODE_PRODUCTION, 'fieldCode' => FixedFieldEnum::expectedAt->name]);
        $savedTypeIds = Stream::keys($expectedAtField->getElements())->toArray();
        $savedTypes = !empty($savedTypeIds) ? $typeRepository->findBy(["id" => $savedTypeIds]) : [];
        $delayByType = $expectedAtField->getElements();

        if(isset($delayByType["all"])){
            $allTypes = [[
                "type"=> [
                    "label" => "Tous les types",
                    "id" => "all",
                ],
                "value" => $delayByType["all"],
            ]];
        }

        return Stream::from(
            Stream::from($savedTypes)
                ->map(fn(Type $type) => [
                    "type"=> [
                        "label" => $type->getLabel(),
                        "id" => $type->getId(),
                    ],
                    "value" => $delayByType[$type->getId()],
                ]),
                $allTypes ?? []
        )
            ->toArray();

    }

    public function getDefaultDeliveryLocationsByTypeId(EntityManagerInterface $entityManager): array {
        $locationRepository = $entityManager->getRepository(Emplacement::class);
        $fieldsParamRepository = $entityManager->getRepository(FixedFieldStandard::class);

        $defaultDeliveryLocationsParam = $fieldsParamRepository->getElements(FixedFieldStandard::ENTITY_CODE_DEMANDE, FixedFieldStandard::FIELD_CODE_DESTINATION_DEMANDE);
        $defaultDeliveryLocationsIds = $defaultDeliveryLocationsParam;

        $defaultDeliveryLocations = [];
        foreach ($defaultDeliveryLocationsIds as $typeId => $locationId) {
            if ($locationId) {
                $location = $locationRepository->find($locationId);
            }

            $defaultDeliveryLocations[$typeId] = isset($location)
                ? [
                    'label' => $location->getLabel(),
                    'id' => $location->getId(),
                ]
                : null;
        }
        return $defaultDeliveryLocations;
    }

    public function deleteTimeSlot(EntityManagerInterface $entityManager, CollectTimeSlot $timeSlot) {
        $canDelete = $timeSlot->getTransportCollectRequests()->isEmpty();
        if ($canDelete) {
            $entityManager->remove($timeSlot);
        } else {
            throw new RuntimeException("Le créneau " . $timeSlot->getName() . " est utilisé. Impossible de le supprimer.");
        }
    }

    public function deleteStartingHour(EntityManagerInterface $entityManager, TransportRoundStartingHour $startingHour) {
        foreach ($startingHour->getDeliverers() as $deliverer) {
            $deliverer->setTransportRoundStartingHour(null);
        }
        $entityManager->remove($startingHour);
    }

    public function getSelectOptionsBySetting(EntityManagerInterface $entityManager, string $setting, ?string $entity = ''): array {
        $typeRepository = $entityManager->getRepository(Type::class);
        $roleRepository = $entityManager->getRepository(Role::class);

        $settingValues = $this->getValue($entityManager, $setting);
        $entities = match($entity) {
            'types' => $typeRepository->findByCategoryLabels([CategoryType::DEMANDE_DISPATCH]),
            'roles' => $roleRepository->findAll(),
            default => [],
        };

        $selectOptions = [];
        foreach ($entities as $data){
            $selectOptions[] = [
                'value' => $data->getId(),
                'label' => $data->getLabel(),
                'selected' => in_array($data->getId(), explode(',', $settingValues)),
            ];
        }

        return $selectOptions;
    }

    public function getMinDateByTypesSettings(EntityManagerInterface $entityManager,
                                              string                 $entity,
                                              FixedFieldEnum         $field,
                                              DateTime               $init): array {
        $fixedFieldByTypeRepository = $entityManager->getRepository(FixedFieldByType::class);
        return Stream::from($fixedFieldByTypeRepository->getElements($entity, $field->name))
            ->map(static fn(string $delay) => explode(":", $delay))
            ->map(static fn(array $delay) => (
                (clone $init)
                    ->add(new DateInterval("PT$delay[0]H$delay[1]M"))
                    ->format('Y-m-d\TH:i')
            ))
            ->toArray();
    }

}
