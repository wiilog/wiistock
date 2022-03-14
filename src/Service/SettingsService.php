<?php

namespace App\Service;

use App\Controller\Settings\StatusController;
use App\Entity\CategorieCL;
use App\Entity\CategorieStatut;
use App\Entity\CategoryType;
use App\Entity\DaysWorked;
use App\Entity\Emplacement;
use App\Entity\FieldsParam;
use App\Entity\InventoryCategory;
use App\Entity\InventoryFrequency;
use App\Entity\FreeField;
use App\Entity\IOT\AlertTemplate;
use App\Entity\IOT\CollectRequestTemplate;
use App\Entity\IOT\DeliveryRequestTemplate;
use App\Entity\IOT\HandlingRequestTemplate;
use App\Entity\IOT\RequestTemplate;
use App\Entity\IOT\RequestTemplateLine;
use App\Entity\MailerServer;
use App\Entity\Setting;
use App\Entity\Reception;
use App\Entity\ReferenceArticle;
use App\Entity\Statut;
use App\Entity\Type;
use App\Entity\VisibilityGroup;
use App\Entity\WorkFreeDay;
use App\Service\IOT\AlertTemplateService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use JetBrains\PhpStorm\ArrayShape;
use ReflectionClass;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\HttpFoundation\FileBag;
use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Yaml;
use WiiCommon\Helper\Stream;

class SettingsService {

    /**  @Required */
    public EntityManagerInterface $manager;

    /** @Required */
    public KernelInterface $kernel;

    /** @Required */
    public AttachmentService $attachmentService;

    /** @Required */
    public RequestTemplateService $requestTemplateService;

    /** @Required */
    public AlertTemplateService $alertTemplateService;

    /** @Required */
    public StatusService $statusService;

    private array $settingsConstants;

    public function __construct() {
        $reflectionClass = new ReflectionClass(Setting::class);
        $this->settingsConstants = Stream::from($reflectionClass->getConstants())
            ->filter(fn ($settingLabel) => is_string($settingLabel))
            ->toArray();
    }

    public function getSetting(array $settings, string $key): ?Setting {
        if (!isset($settings[$key])
            && in_array($key, $this->settingsConstants)) {
            $settingRepository = $this->manager->getRepository(Setting::class);
            $setting = $settingRepository->findOneBy(['label' => $key]);
            if (!$setting) {
                $setting = new Setting();
                $setting->setLabel($key);
            }
            $settings[$key] = $setting;

            $this->manager->persist($setting);
        }

        return $settings[$key] ?? null;
    }

    public function save(Request $request): array {
        $settingRepository = $this->manager->getRepository(Setting::class);

        $settingNames = array_merge(
            array_keys($request->request->all()),
            array_keys($request->files->all()),
        );
        $allFormSettingNames = json_decode($request->request->get('__form_fieldNames', '[]'), true);

        $result = [];

        $settings = $settingRepository->findByLabel(array_merge($settingNames, $allFormSettingNames));
        if($request->request->has("datatables")) {
            $this->saveDatatables(
                json_decode($request->request->get("datatables"), true),
                $request->request->all(),
                $request->files->all(),
                $result
            );
        }

        $updated = [];
        $this->saveCustom($request, $settings, $updated, $result);
        $this->saveStandard($request, $settings, $updated);
        $this->saveFiles($request, $settings, $allFormSettingNames, $updated);

        $settingNamesToClear = array_diff($allFormSettingNames, $settingNames, $updated);
        $settingToClear = !empty($settingNamesToClear) ? $settingRepository->findByLabel($settingNamesToClear) : [];

        $this->clearSettings($settingToClear);

        $this->manager->flush();

        $this->postSaveTreatment($updated);

        $result = $result ?? [];
        if (isset($result['type'])) {
            /** @var Type $type */
            $type = $result['type'];
            $result['entity'] = [
                'id' => $type->getId(),
                'label' => $type->getLabel()
            ];
            unset($result['type']);
        }
        else if (isset($result['template'])) {
            /** @var RequestTemplate $template */
            $template = $result['template'];
            $result['entity'] = [
                'id' => $template->getId(),
                'label' => $template->getName()
            ];
            unset($result['template']);
        }
        return $result;
    }

    /**
     * Saves custom settings
     *
     * @param Setting[] $settings Existing settings
     */
    private function saveCustom(Request $request, array $settings, array &$updated, array &$result): void {
        $data = $request->request;

        if($client = $data->get(Setting::APP_CLIENT)) {
            $this->changeClient($client);
            $updated[] = Setting::APP_CLIENT;
        }

        if($data->has("MAILER_URL")) {
            $mailer = $this->manager->getRepository(MailerServer::class)->findOneBy([]);
            $mailer->setSmtp($data->get("MAILER_URL"));
            $mailer->setUser($data->get("MAILER_USER"));
            $mailer->setPassword($data->get("MAILER_PASSWORD"));
            $mailer->setPort($data->get("MAILER_PORT"));
            $mailer->setProtocol($data->get("MAILER_PROTOCOL"));
            $mailer->setSenderName($data->get("MAILER_SENDER_NAME"));
            $mailer->setSenderMail($data->get("MAILER_SENDER_MAIL"));
        }

        if ($data->has("en_attente_de_réception") && $data->has("réception_partielle") && $data->has("réception_totale") && $data->has("anomalie")) {
            $codes = [
                Reception::STATUT_EN_ATTENTE => $data->get("en_attente_de_réception"),
                Reception::STATUT_RECEPTION_PARTIELLE => $data->get("réception_partielle"),
                Reception::STATUT_RECEPTION_TOTALE => $data->get("réception_totale"),
                Reception::STATUT_ANOMALIE => $data->get("anomalie"),
            ];

            $statuses = $this->manager->getRepository(Statut::class)->findBy(["code" => array_keys($codes)]);
            foreach ($statuses as $status) {
                $status->setNom($codes[$status->getCode()]);
            }
        }

        if ($request->request->has("deliveryType") && $request->request->has("deliveryRequestLocation")) {
            $deliveryTypes = explode(',', $request->request->get("deliveryType"));
            $deliveryRequestLocations = explode(',', $request->request->get("deliveryRequestLocation"));

            $setting = $this->manager->getRepository(Setting::class)->findOneBy(["label" => Setting::DEFAULT_LOCATION_LIVRAISON]);
            $associatedTypesAndLocations = array_combine($deliveryTypes, $deliveryRequestLocations);
            $setting->setValue(json_encode($associatedTypesAndLocations));

            $updated = array_merge($updated, [
                Setting::DEFAULT_LOCATION_LIVRAISON,
                "deliveryType",
                "deliveryRequestLocation",
            ]);
        }

        if ($request->request->getBoolean("alertTemplate")) {
            $alertTemplateRepository = $this->manager->getRepository(AlertTemplate::class);
            if(!$request->request->get("entity")) {
                $template = new AlertTemplate();
                $template->setType($request->request->get('type'));
                $this->manager->persist($template);

                $result['template'] = $template;
            } else {
                $template = $this->manager->find(AlertTemplate::class, $request->request->get("entity"));
            }

            $sameName = $alertTemplateRepository->findOneBy(["name" => $request->request->get("name")]);
            if ($sameName && $sameName->getId() !== $template->getId()) {
                throw new RuntimeException("Un modèle de demande avec le même nom existe déjà");
            }

            $this->alertTemplateService->updateAlertTemplate($request, $this->manager, $template);
        }

        if ($request->request->has("DISPATCH_OVERCONSUMPTION_BILL_TYPE") && $request->request->has("DISPATCH_OVERCONSUMPTION_BILL_STATUS")) {
            $setting = $this->manager->getRepository(Setting::class)->findOneBy(["label" => Setting::DISPATCH_OVERCONSUMPTION_BILL_TYPE_AND_STATUS]);
            $setting->setValue(
                $request->request->get("DISPATCH_OVERCONSUMPTION_BILL_TYPE") . ";" .
                $request->request->get("DISPATCH_OVERCONSUMPTION_BILL_STATUS")
            );

            $updated[] = "DISPATCH_OVERCONSUMPTION_BILL_TYPE";
            $updated[] = "DISPATCH_OVERCONSUMPTION_BILL_STATUS";
        }
    }

    /**
     * @param Setting[] $settings Existing settings
     */
    private function saveStandard(Request $request, array $settings, array &$updated): void {
        foreach($request->request->all() as $key => $value) {
            $setting = $this->getSetting($settings, $key);
            if(isset($setting) && !in_array($key, $updated)) {
                if(is_array($value)) {
                    $value = json_encode($value);
                }

                if($value !== $setting->getValue()) {
                    $setting->setValue($value);
                    $updated[] = $key;
                }
            }
        }
    }

    /**
     * @param Setting[] $settings Existing settings
     */
    private function saveFiles(Request $request, array $settings, array $allFormSettingNames, array &$updated): void {
        foreach($request->files->all() as $key => $value) {
            $setting = $this->getSetting($settings, $key);
            if(isset($setting)) {
                $fileName = $this->attachmentService->saveFile($value, $key);
                $setting->setValue("uploads/attachements/" . $fileName[array_key_first($fileName)]);
                $updated[] = $key;
            }
        }

        $logosToSave = [
            [Setting::FILE_WEBSITE_LOGO, Setting::DEFAULT_WEBSITE_LOGO_VALUE],
            [Setting::FILE_MOBILE_LOGO_LOGIN, Setting::DEFAULT_MOBILE_LOGO_LOGIN_VALUE],
            [Setting::FILE_EMAIL_LOGO, Setting::DEFAULT_EMAIL_LOGO_VALUE],
            [Setting::FILE_MOBILE_LOGO_HEADER, Setting::DEFAULT_MOBILE_LOGO_HEADER_VALUE],
            [Setting::FILE_WAYBILL_LOGO, null],
            [Setting::FILE_OVERCONSUMPTION_LOGO, null],
        ];

        foreach ($logosToSave as [$settingLabel, $default]) {
            if (in_array($settingLabel, $allFormSettingNames)) {
                $setting = $this->getSetting($settings, $settingLabel);
                if (isset($default)
                    && !$request->request->getBoolean('keep-' . $settingLabel)
                    && !$request->files->has($settingLabel)) {
                    $setting->setValue($default);
                }
            }
            $updated[] = $settingLabel;
        }
    }

//    TODO WIIS-6693 mettre dans des services different ?
    private function saveDatatables(array $tables, array $data, array $files, array &$result): void {
        if(isset($tables["workingHours"])) {
            $ids = array_map(fn($day) => $day["id"], $tables["workingHours"]);

            $requestTemplateLineRepository = $this->manager->getRepository(DaysWorked::class);
            $daysWorked = Stream::from($requestTemplateLineRepository->findBy(["id" => $ids]))
                ->keymap(fn($day) => [$day->getId(), $day])
                ->toArray();

            foreach($tables["workingHours"] as $workingHour) {
                $hours = $workingHour["hours"] ?? null;
                if($hours && !preg_match("/^\d{2,2}:\d{2,2}-\d{2,2}:\d{2,2}(;\d{2,2}:\d{2,2}-\d{2,2}:\d{2,2})?$/", $hours)) {
                    throw new RuntimeException("Le champ horaires doit être au format HH:MM-HH:MM;HH:MM-HH:MM");
                }

                $day = $daysWorked[$workingHour["id"]]
                    ->setTimes($hours)
                    ->setWorked($workingHour["worked"]);

                if($day->isWorked() && !$day->getTimes()) {
                    throw new RuntimeException("Le champ horaires de travail est requis pour les jours travaillés");
                } else if(!$day->isWorked()) {
                    $day->setTimes(null);
                }
            }
        }

        if(isset($tables["offDays"])) {
            $workFreeDayRepository = $this->manager->getRepository(WorkFreeDay::class);
            foreach(array_filter($tables["offDays"]) as $offDay) {
                $date = DateTime::createFromFormat("Y-m-d", $offDay["day"]);
                if($workFreeDayRepository->findBy(["day" => $date])) {
                    throw new RuntimeException("Le jour " . $date->format("d/m/Y") . " est déjà renseigné");
                }

                $day = new WorkFreeDay();
                $day->setDay($date);

                $this->manager->persist($day);
            }
        }

        if(isset($tables["frequencesTable"])){
            foreach(array_filter($tables["frequencesTable"]) as $frequenceData) {
                $frequenceRepository = $this->manager->getRepository(InventoryFrequency::class);
                $frequence = "";
                if (isset($frequenceData['frequenceId'])){
                    $frequence = $frequenceRepository->find($frequenceData['frequenceId']);
                } else {
                    $frequence = new InventoryFrequency();
                }
                $frequence->setLabel($frequenceData['label']);
                $frequence->setNbMonths($frequenceData['nbMonths']);

                $this->manager->persist($frequence);
            }
        }

        if(isset($tables["categoriesTable"])){
            foreach(array_filter($tables["categoriesTable"]) as $categoryData) {
                $frequenceRepository = $this->manager->getRepository(InventoryFrequency::class);
                $categoryRepository = $this->manager->getRepository(InventoryCategory::class);
                $frequence = $frequenceRepository->find($categoryData['frequency']);
                $category = isset($categoryData['categoryId'])
                    ? $categoryRepository->find($categoryData['categoryId'])
                    : new InventoryCategory();
                $category->setLabel($categoryData['label']);
                $category->setFrequency($frequence);

                $this->manager->persist($category);
            }
        }

        if(isset($tables["freeFields"])) {
            $typeRepository = $this->manager->getRepository(Type::class);
            $categoryTypeRepository = $this->manager->getRepository(CategoryType::class);

            $ids = array_map(fn($freeField) => $freeField["id"] ?? null, $tables["freeFields"]);

            if(isset($data["entity"])) {
                if(!is_numeric($data["entity"]) && in_array($data["entity"], CategoryType::ALL)) {
                    $category = $categoryTypeRepository->findOneBy(["label" => $data["entity"]]);

                    $alreadyCreatedType = $typeRepository->count([
                        'label' => $data["label"],
                        'category' => $category
                    ]);

                    if ($alreadyCreatedType > 0) {
                        throw new RuntimeException("Le type existe déjà pour cette categorie");
                    }

                    $type = new Type();
                    $type->setCategory($category);
                    $this->manager->persist($type);

                    $result['type'] = $type;
                } else {
                    $type = $this->manager->find(Type::class, $data["entity"]);
                }

                if (!isset($type)) {
                    throw new RuntimeException("Le type est introuvable");
                }

                if ($type->getCategory()->getLabel() !== CategoryType::SENSOR && empty($data["label"])) {
                    throw new RuntimeException("Vous devez saisir un libellé pour le type");
                }

                $type->setLabel($data["label"] ?? $type->getLabel())
                    ->setDescription($data["description"] ?? null)
                    ->setPickLocation(isset($data["pickLocation"]) ? $this->manager->find(Emplacement::class, $data["pickLocation"]) : null)
                    ->setDropLocation(isset($data["dropLocation"]) ? $this->manager->find(Emplacement::class, $data["dropLocation"]) : null)
                    ->setNotificationsEnabled($data["pushNotifications"] ?? false)
                    ->setNotificationsEmergencies(isset($data["notificationEmergencies"]) ? explode(",", $data["notificationEmergencies"]) : null)
                    ->setSendMail($data["mailRequester"] ?? false)
                    ->setColor($data["color"] ?? null);
            } elseif(isset($tables["category"])) {
                $category = $categoryTypeRepository->findOneBy(["label" => $tables["category"]]);
                $type = $typeRepository->findOneBy([
                    'label' => $tables["category"],
                    'category' => $category
                ]);
            }

            $requestTemplateLineRepository = $this->manager->getRepository(FreeField::class);
            $freeFields = Stream::from($requestTemplateLineRepository->findBy(["id" => $ids]))
                ->keymap(fn($day) => [$day->getId(), $day])
                ->toArray();

            foreach(array_filter($tables["freeFields"]) as $item) {
                /** @var FreeField $freeField */
                $freeField = isset($item["id"]) ? $freeFields[$item["id"]] : new FreeField();

                $existing = $requestTemplateLineRepository->findOneBy(["label" => $item["label"]]);
                if($existing && $existing->getId() != $freeField->getId()) {
                    throw new RuntimeException("Un champ libre existe déjà avec le libellé {$item["label"]}");
                }

                $freeField->setLabel($item["label"])
                    ->setType($type)
                    ->setTypage($item["type"] ?? $freeField->getTypage())
                    ->setCategorieCL(isset($item["category"]) ? $this->manager->find(CategorieCL::class, $item["category"]) : $type->getCategory()->getCategorieCLs()->first())
                    ->setDefaultValue($item["defaultValue"] ?? null)
                    ->setElements(isset($item["elements"]) ? explode(";", $item["elements"]) : null)
                    ->setDisplayedCreate($item["displayedCreate"])
                    ->setRequiredCreate($item["requiredCreate"])
                    ->setRequiredEdit($item["requiredEdit"]);

                $this->manager->persist($freeField);
            }
        }

        if(isset($tables["fixedFields"])) {
            $ids = array_map(fn($freeField) => $freeField["id"] ?? null, $tables["fixedFields"]);

            $fieldsParamRepository = $this->manager->getRepository(FieldsParam::class);
            $fieldsParams = Stream::from($fieldsParamRepository->findBy(["id" => $ids]))
                ->keymap(fn($day) => [$day->getId(), $day])
                ->toArray();

            foreach(array_filter($tables["fixedFields"]) as $item) {
                /** @var FreeField $freeField */
                $fieldsParam = $fieldsParams[$item["id"]] ?? null;

                if($fieldsParam) {
                    $fieldsParam->setDisplayedCreate($item["displayedCreate"])
                        ->setRequiredCreate($item["requiredCreate"])
                        ->setDisplayedEdit($item["displayedEdit"])
                        ->setRequiredEdit($item["requiredEdit"])
                        ->setDisplayedFilters($item["displayedFilters"] ?? null);
                }
            }
        }

        if(isset($tables["visibilityGroup"])) {
            foreach(array_filter($tables["visibilityGroup"]) as $visibilityGroupData) {
                $visibilityGroupRepository = $this->manager->getRepository(VisibilityGroup::class);
                if (isset($visibilityGroupData['visibilityGroupId'])){
                    $visibilityGroup = $visibilityGroupRepository->find($visibilityGroupData['visibilityGroupId']);
                } else {
                    $visibilityGroup = new VisibilityGroup();
                }
                $visibilityGroup->setLabel($visibilityGroupData['label']);
                $visibilityGroup->setDescription($visibilityGroupData['description']);
                $visibilityGroup->setActive($visibilityGroupData['actif']);

                $this->manager->persist($visibilityGroup);
            }
        }

        if(isset($tables["typesLitigeTable"])){
            foreach(array_filter($tables["typesLitigeTable"]) as $typeLitigeData) {
                $typeLitigeRepository = $this->manager->getRepository(Type::class);
                $categoryLitige = $this->manager->getRepository(CategoryType::class)->findOneBy(['label' => CategoryType::DISPUTE]);

                if (isset($typeLitigeData['typeLitigeId'])){
                    $typeLitige = $typeLitigeRepository->find($typeLitigeData['typeLitigeId']);
                } else {
                    $typeLitige = new Type();
                    $typeLitige->setCategory($categoryLitige);
                }

                $typeLitige->setLabel($typeLitigeData['label']);
                $typeLitige->setDescription($typeLitigeData['description'] ?? "");

                $this->manager->persist($typeLitige);
            }
        }

        if(isset($tables["genericStatuses"])){
            $statusesData = array_filter($tables["genericStatuses"]);

            if (!empty($statusesData)) {
                $statusRepository = $this->manager->getRepository(Statut::class);
                $categoryRepository = $this->manager->getRepository(CategorieStatut::class);
                $typeRepository = $this->manager->getRepository(Type::class);

                $categoryName = match ($statusesData[0]['mode']) {
                    StatusController::MODE_ARRIVAL_DISPUTE => CategorieStatut::DISPUTE_ARR,
                    StatusController::MODE_RECEPTION_DISPUTE => CategorieStatut::LITIGE_RECEPT,
                    StatusController::MODE_PURCHASE_REQUEST => CategorieStatut::PURCHASE_REQUEST,
                    StatusController::MODE_ARRIVAL => CategorieStatut::ARRIVAGE,
                    StatusController::MODE_DISPATCH => CategorieStatut::DISPATCH,
                    StatusController::MODE_HANDLING => CategorieStatut::HANDLING
                };

                $category = $categoryRepository->findOneBy(['nom' => $categoryName]);
                $persistedStatuses = $statusRepository->findBy([
                    'categorie' => $category
                ]);

                foreach ($statusesData as $statusData) {
                    if (!in_array($statusData['state'], [Statut::TREATED, Statut::NOT_TREATED, Statut::DRAFT, Statut::IN_PROGRESS, Statut::DISPUTE, Statut::PARTIAL])) {
                        throw new RuntimeException("L'état du statut est invalide");
                    }

                    if (isset($statusData['statusId'])) {
                        $status = Stream::from($persistedStatuses)
                            ->filter(fn (Statut $status) => $status->getId() == $statusData['statusId'])
                            ->first();

                        if (!$status) {
                            $status = $statusRepository->find($statusData['statusId']);
                            $persistedStatuses[] = $status;
                        }
                    }
                    else {
                        $status = new Statut();
                        $statusData['category'] = $categoryName;
                        $status->setCategorie($category);

                        // we set type only on creation
                        if (isset($statusData['type'])) {
                            $status->setType($typeRepository->find($statusData['type']));
                        }
                        $persistedStatuses[] = $status;
                    }

                    $status->setNom($statusData['label']);
                    $status->setState($statusData['state']);
                    $status->setComment($statusData['comment'] ?? null);
                    $status->setDefaultForCategory($statusData['defaultStatut'] ?? false);
                    $status->setSendNotifToBuyer($statusData['sendMailBuyers'] ?? false);
                    $status->setSendNotifToDeclarant($statusData['sendMailRequesters'] ?? false);
                    $status->setSendNotifToRecipient($statusData['sendMailDest'] ?? false);
                    $status->setNeedsMobileSync($statusData['needsMobileSync'] ?? false);
                    $status->setCommentNeeded($statusData['commentNeeded'] ?? false);
                    $status->setAutomaticReceptionCreation($statusData['automaticReceptionCreation'] ?? false);
                    $status->setDisplayOrder($statusData['order'] ?? 0);

                    $this->manager->persist($status);
                }
                $validation = $this->statusService->validateStatusesData($persistedStatuses);

                if (!$validation['success']) {
                    throw new RuntimeException($validation['message']);
                }
            }
        }

        if(isset($tables["requestTemplates"])) {
            $ids = array_map(fn($line) => $line["id"] ?? null, $tables["requestTemplates"]);
            $requestTemplateRepository = $this->manager->getRepository(RequestTemplate::class);
            $typeRepository = $this->manager->getRepository(Type::class);
            if(!is_numeric($data["entity"])) {
                $template = $data["entity"] === Type::LABEL_DELIVERY
                    ? new DeliveryRequestTemplate()
                    : ($data["entity"] === Type::LABEL_COLLECT
                        ? new CollectRequestTemplate()
                        : new HandlingRequestTemplate()
                    );
                $template->setType($typeRepository->findOneByCategoryLabelAndLabel(CategoryType::REQUEST_TEMPLATE, $data["entity"]));

                $this->manager->persist($template);

                $result['template'] = $template;
            } else {
                $template = $this->manager->find(RequestTemplate::class, $data["entity"]);
            }

            $sameName = $requestTemplateRepository->findOneBy(["name" => $data["name"]]);
            if ($sameName && $sameName->getId() !== $template->getId()) {
                throw new RuntimeException("Un modèle de demande avec le même nom existe déjà");
            }

            $this->requestTemplateService->updateRequestTemplate($template, $data, $files);

            $requestTemplateLineRepository = $this->manager->getRepository(RequestTemplateLine::class);
            $lines = Stream::from($requestTemplateLineRepository->findBy(["id" => $ids]))
                ->keymap(fn($line) => [$line->getId(), $line])
                ->toArray();

            foreach(array_filter($tables["requestTemplates"]) as $item) {
                /** @var FreeField $freeField */
                $line = isset($item["id"]) ? $lines[$item["id"]] : new RequestTemplateLine();

                $line->setRequestTemplate($template);

                $this->requestTemplateService->updateRequestTemplateLine($line, $item);

                $this->manager->persist($line);
            }
        }
    }

    /**
     * Runs utilities when needed after settings have been saved
     * such as cache clearing translation updates or webpack build
     *
     * @param string[] $updated
     */
    private function postSaveTreatment(array $updated): void {
        if(array_intersect($updated, [Setting::FONT_FAMILY])) {
            $this->generateFontSCSS();
            $this->yarnBuild();
        }

        if(array_intersect($updated, [Setting::MAX_SESSION_TIME])) {
            $this->generateSessionConfig();
            $this->cacheClear();
        }
    }

    public function changeClient(string $client) {
        $configPath = "/etc/php8/php-fpm.conf";

        //if we're not on a kubernetes pod => file doesn't exist => ignore
        if(!file_exists($configPath)) {
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
        } catch(Exception $exception) {
            throw new RuntimeException("Une erreur est survenue lors du changement de client");
        }
    }

    public function generateFontSCSS() {
        $path = "{$this->kernel->getProjectDir()}/assets/scss/_customFont.scss";

        $font = $this->manager->getRepository(Setting::class)
                ->getOneParamByLabel(Setting::FONT_FAMILY) ?? Setting::DEFAULT_FONT_FAMILY;

        file_put_contents($path, "\$mainFont: \"$font\";");
    }

    public function generateSessionConfig() {
        $sessionLifetime = $this->manager->getRepository(Setting::class)
            ->getOneParamByLabel(Setting::MAX_SESSION_TIME);

        $generated = "{$this->kernel->getProjectDir()}/config/generated.yaml";
        $config = [
            "parameters" => [
                "session_lifetime" => $sessionLifetime * 60,
            ],
        ];

        file_put_contents($generated, Yaml::dump($config));
    }

    public function yarnBuild() {
        $env = $_SERVER["APP_ENV"] == "dev" ? "dev" : "production";
        $process = Process::fromShellCommandline("yarn build:only:$env");
        $process->run();
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
    public function getDimensionAndTypeBarcodeArray(): array {
        $settingRepository = $this->manager->getRepository(Setting::class);

        return [
            "logo" => $settingRepository->getOneParamByLabel(Setting::LABEL_LOGO),
            "height" => $settingRepository->getOneParamByLabel(Setting::LABEL_HEIGHT) ?? 0,
            "width" => $settingRepository->getOneParamByLabel(Setting::LABEL_WIDTH) ?? 0,
            "isCode128" => $settingRepository->getOneParamByLabel(Setting::BARCODE_TYPE_IS_128),
        ];
    }

    public function getParamLocation(string $label) {
        $settingRepository = $this->manager->getRepository(Setting::class);
        $emplacementRepository = $this->manager->getRepository(Emplacement::class);

        $locationId = $settingRepository->getOneParamByLabel($label);

        if ($locationId) {
            $location = $emplacementRepository->find($locationId);

            if ($location) {
                $resp = [
                    'id' => $locationId,
                    'text' => $location->getLabel()
                ];
            }
        }

        return $resp ?? null;
    }

    public function generateScssFile(?Setting $font = null) {
        $projectDir = $this->kernel->getProjectDir();
        $scssFile = $projectDir . '/assets/scss/_customFont.scss';

        if(!$font) {
            $settingRepository = $this->manager->getRepository(Setting::class);
            $param = $settingRepository->findOneBy(['label' => Setting::FONT_FAMILY]);
            $font = $param ? $param->getValue() : Setting::DEFAULT_FONT_FAMILY;
        } else {
            $font = $font->getValue();
        }

        file_put_contents($scssFile, "\$mainFont: \"$font\";");
    }

    public function getDefaultDeliveryLocationsByType(EntityManagerInterface $entityManager): array {
        $typeRepository = $entityManager->getRepository(Type::class);
        $locationRepository = $entityManager->getRepository(Emplacement::class);
        $settingRepository = $entityManager->getRepository(Setting::class);

        $defaultDeliveryLocationsParam = $settingRepository->getOneParamByLabel(Setting::DEFAULT_LOCATION_LIVRAISON);
        $defaultDeliveryLocationsIds = json_decode($defaultDeliveryLocationsParam, true) ?: [];

        $defaultDeliveryLocations = [];
        foreach($defaultDeliveryLocationsIds as $typeId => $locationId) {
            if($typeId !== 'all' && $typeId) {
                $type = $typeRepository->find($typeId);
            }
            if($locationId) {
                $location = $locationRepository->find($locationId);
            }

            if (isset($location)) {
                $defaultDeliveryLocations[] = [
                    'location' => [
                        'label' => $location->getLabel(),
                        'id' => $location->getId(),
                    ],
                    'type' => isset($type)
                        ? [
                            'label' => $type->getLabel(),
                            'id' => $type->getId(),
                        ]
                        : null,
                ];
            }
        }
        return $defaultDeliveryLocations;
    }

    public function getDefaultDeliveryLocationsByTypeId(EntityManagerInterface $entityManager): array {
        $locationRepository = $entityManager->getRepository(Emplacement::class);
        $settingRepository = $entityManager->getRepository(Setting::class);

        $defaultDeliveryLocationsParam = $settingRepository->getOneParamByLabel(Setting::DEFAULT_LOCATION_LIVRAISON);
        $defaultDeliveryLocationsIds = json_decode($defaultDeliveryLocationsParam, true) ?: [];

        $defaultDeliveryLocations = [];
        foreach ($defaultDeliveryLocationsIds as $typeId => $locationId) {
            if ($locationId) {
                $location = $locationRepository->find($locationId);
            }

            $defaultDeliveryLocations[$typeId] = isset($location)
                ? [
                    'label' => $location->getLabel(),
                    'id' => $location->getId()
                ]
                : null;
        }
        return $defaultDeliveryLocations;
    }

}
