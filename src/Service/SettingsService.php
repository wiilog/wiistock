<?php

namespace App\Service;

use App\Entity\CategorieCL;
use App\Entity\CategorieStatut;
use App\Entity\CategoryType;
use App\Entity\DaysWorked;
use App\Entity\Emplacement;
use App\Entity\FieldsParam;
use App\Entity\InventoryCategory;
use App\Entity\InventoryFrequency;
use App\Entity\FreeField;
use App\Entity\MailerServer;
use App\Entity\Setting;
use App\Entity\Reception;
use App\Entity\Statut;
use App\Entity\Type;
use App\Entity\VisibilityGroup;
use App\Entity\WorkFreeDay;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
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

    private array $settingsConstants;

    public function __construct() {
        $reflectionClass = new ReflectionClass(Setting::class);
        $this->settingsConstants = Stream::from($reflectionClass->getConstants())
            ->filter(fn ($settingLabel) => is_string($settingLabel))
            ->toArray();
    }

    public function getSetting(array $settings, string $key): ?Setting {
        if (!isset($settings[$key]) && in_array($key, $this->settingsConstants)) {
            $setting = new Setting();
            $setting->setLabel($key);
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

        $settings = $settingRepository->findByLabel(array_merge($settingNames, $allFormSettingNames));

        if($request->request->has("datatables")) {
            $result = $this->saveDatatables(json_decode($request->request->get("datatables"), true), $request->request->all());
        }

        $updated = [];
        $this->saveCustom($request, $settings, $updated);
        $this->saveStandard($request, $settings, $updated);
        $this->saveFiles($request, $settings, $updated);

        $settingNamesToClear = array_diff($allFormSettingNames, $settingNames);
        $settingToClear = !empty($settingNamesToClear) ? $settingRepository->findByLabel($settingNamesToClear) : [];

        $this->clearSettings($settingToClear);

        $this->manager->flush();

        $this->postSaveTreatment($updated);

        $result = $result ?? [];
        if (isset($result['type'])) {
            /** @var Type $type */
            $type = $result['type'];
            $result['type'] = [
                'id' => $type->getId(),
                'label' => $type->getLabel()
            ];
        }
        return $result;
    }

    /**
     * Saves custom settings
     *
     * @param Setting[] $settings Existing settings
     */
    private function saveCustom(Request $request, array $settings, array &$updated): void {
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

        $logosToSave = [
            [Setting::WEBSITE_LOGO, Setting::DEFAULT_WEBSITE_LOGO_VALUE],
            [Setting::MOBILE_LOGO_LOGIN, Setting::DEFAULT_MOBILE_LOGO_LOGIN_VALUE],
            [Setting::EMAIL_LOGO, Setting::DEFAULT_EMAIL_LOGO_VALUE],
            [Setting::MOBILE_LOGO_HEADER, Setting::DEFAULT_MOBILE_LOGO_HEADER_VALUE],
        ];
        foreach ($logosToSave as [$settingLabel, $default]) {
            $setting = $this->getSetting($settings, $settingLabel);
            if (isset($setting)
                && $this->saveDefaultImage($data, $request->files, $setting, $default)) {
                $updated[] = $settingLabel;
            }
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

        if ($request->request->has("deliveryType")
            && $request->request->has("deliveryRequestLocation")) {
            $deliveryTypes = Stream::explode(',', $request->request->get("deliveryType", ''))
                ->toArray();
            $deliveryRequestLocations = Stream::explode(',', $request->request->get("deliveryRequestLocation", ''))
                ->toArray();

            $setting = $this->getSetting($settings, Setting::DEFAULT_LOCATION_LIVRAISON);
            $associatedTypesAndLocations = array_combine($deliveryTypes, $deliveryRequestLocations);
            $setting->setValue(json_encode($associatedTypesAndLocations));

            $updated[] = Setting::DEFAULT_LOCATION_LIVRAISON;
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
    private function saveFiles(Request $request, array $settings, array &$updated): void {
        foreach($request->files->all() as $key => $value) {
            $setting = $this->getSetting($settings, $key);
            if(isset($setting)) {
                $fileName = $this->attachmentService->saveFile($value, $key);
                $setting->setValue("uploads/attachements/" . $fileName[array_key_first($fileName)]);
                $updated[] = $key;
            }
        }
    }

//    TODO WIIS-6693 mettre dans des services different ?
    private function saveDatatables(array $tables, array $data): array {
        $result = [];
        if(isset($tables["workingHours"])) {
            $ids = array_map(fn($day) => $day["id"], $tables["workingHours"]);

            $freeFieldRepository = $this->manager->getRepository(DaysWorked::class);
            $daysWorked = Stream::from($freeFieldRepository->findBy(["id" => $ids]))
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
            foreach(array_filter($tables["offDays"]) as $offDay) {
                $day = new WorkFreeDay();
                $day->setDay(DateTime::createFromFormat("Y-m-d", $offDay["day"]));

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
            } else {
                $category = $categoryTypeRepository->findOneBy(["label" => CategoryType::MOUVEMENT_TRACA]);
                $type = $typeRepository->findOneBy([
                    'label' => Type::LABEL_MVT_TRACA,
                    'category' => $category
                ]);
            }

            $freeFieldRepository = $this->manager->getRepository(FreeField::class);
            $freeFields = Stream::from($freeFieldRepository->findBy(["id" => $ids]))
                ->keymap(fn($day) => [$day->getId(), $day])
                ->toArray();

            foreach(array_filter($tables["freeFields"]) as $item) {
                /** @var FreeField $freeField */
                $freeField = isset($item["id"]) ? $freeFields[$item["id"]] : new FreeField();

                $existing = $freeFieldRepository->findOneBy(["label" => $item["label"]]);
                if($existing && $existing->getId() != $freeField->getId()) {
                    throw new RuntimeException("Un champ libre existe déjà avec le libellé {$item["label"]}");
                }

                $freeField->setLabel($item["label"])
                    ->setType($type)
                    ->setTypage($item["type"] ?? $freeField->getTypage())
                    ->setCategorieCL(isset($item["category"]) ? $this->manager->find(CategorieCL::class, $item["category"]) : null)
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

        if(isset($tables["visibilityGroup"])){
            foreach(array_filter($tables["visibilityGroup"]) as $visibilityGroupData) {
                $visibilityGroupRepository = $this->manager->getRepository(VisibilityGroup::class);
                $visibilityGroup = "";
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

        if(isset($tables["disputeStatuses"])){
            foreach(array_filter($tables["disputeStatuses"]) as $statusData){
                $statutRepository = $this->manager->getRepository(Statut::class);
                $categoryRepository = $this->manager->getRepository(CategorieStatut::class);

                if(!in_array($statusData['state'], [Statut::TREATED, Statut::NOT_TREATED])) {
                    throw new RuntimeException("L'état du statut est invalide");
                }

                if (isset($statusData['statusId'])){
                    $statut = $statutRepository->find($statusData['statusId']);
                } else {
                    $statut = new Statut();
                    $categoryName = $statusData['mode'] === 'arrival' ? CategorieStatut::DISPUTE_ARR : CategorieStatut::LITIGE_RECEPT;
                    $statut->setCategorie($categoryRepository->findOneBy(['nom' => $categoryName]));
                }
                $statut->setNom($statusData['label']);
                $statut->setState($statusData['state']);
                $statut->setComment($statusData['comment'] ?? null);
                $statut->setDefaultForCategory($statusData['defaultStatut']);
                $statut->setSendNotifToBuyer($statusData['sendMailBuyers']);
                $statut->setSendNotifToDeclarant($statusData['sendMailRequesters']);
                $statut->setSendNotifToRecipient($statusData['sendMailDest']);
                $statut->setDisplayOrder($statusData['order']);

                $this->manager->persist($statut);
            }
        }
        return $result;
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
        $configPath = "/etc/php7/php-fpm.conf";

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
            exec("kill -USR2 $(pgrep -o php-fpm7)");
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

    private function saveDefaultImage(InputBag $data,
                                      FileBag $fileBag,
                                      Setting $setting,
                                      string $defaultValue): bool {

        $defaultImageSaved = false;
        $settingLabel = $setting->getLabel();
        if(!$data->getBoolean('keep-' . $settingLabel) && !$fileBag->has($settingLabel)) {
            $setting->setValue($defaultValue);
            $defaultImageSaved = true;
        }

        return $defaultImageSaved;
    }

}
