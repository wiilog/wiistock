<?php

namespace App\Service;

use App\Entity\CategorieCL;
use App\Entity\CategoryType;
use App\Entity\DaysWorked;
use App\Entity\Emplacement;
use App\Entity\FieldsParam;
use App\Entity\InventoryCategory;
use App\Entity\InventoryFrequency;
use App\Entity\FreeField;
use App\Entity\MailerServer;
use App\Entity\ParametrageGlobal;
use App\Entity\Reception;
use App\Entity\Statut;
use App\Entity\Type;
use App\Entity\VisibilityGroup;
use App\Entity\WorkFreeDay;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
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

    public function createSetting(string $setting): ParametrageGlobal {
        $newSetting = new ParametrageGlobal();
        $newSetting->setLabel($setting);

        $this->manager->persist($newSetting);

        return $newSetting;
    }

    public function save(Request $request) {
        $settingRepository = $this->manager->getRepository(ParametrageGlobal::class);
        $settings = $settingRepository->findByLabel(array_merge(
            array_keys($request->request->all()),
            array_keys($request->files->all()),
        ));

        if($request->request->has("datatables")) {
            $this->saveDatatables(json_decode($request->request->get("datatables"), true), $request->request->all());
            $request->request->remove("datatables");
        }

        $alreadySaved = $this->customSave($request, $settings);
        $updated = [];

        foreach($request->request->all() as $key => $value) {
            if(in_array($key, $alreadySaved)) {
                continue;
            }

            $setting = $settings[$key] ?? null;
            if(!isset($setting)) {
                $settings[$key] = $setting = $this->createSetting($key);
            }

            if(is_array($value)) {
                $value = json_encode($value);
            }

            if($value !== $setting->getValue()) {
                $setting->setValue($value);
                $updated[] = $key;
            }
        }

        foreach($request->files->all() as $key => $value) {
            if(in_array($key, $alreadySaved)) {
                continue;
            }

            $setting = $settings[$key] ?? null;
            if(!isset($setting)) {
                $settings[$key] = $setting = $this->createSetting($key);
            }

            $fileName = $this->attachmentService->saveFile($value, $key);
            $setting->setValue("uploads/attachements/" . $fileName[array_key_first($fileName)]);
            $updated[] = $key;
        }

        $this->manager->flush();

        $this->postSaveTreatment($updated);
    }

    /**
     * Saves custom settings
     *
     * @param Request $request The request
     * @param ParametrageGlobal[] $settings Existing settings
     * @return array Settings that were processed
     */
    private function customSave(Request $request, array $settings): array {
        $saved = [];
        $data = $request->request;

        if($client = $data->get(ParametrageGlobal::APP_CLIENT)) {
            $this->changeClient($client);
            $saved[] = ParametrageGlobal::APP_CLIENT;
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

            $saved = array_merge($saved, [
                "MAILER_URL",
                "MAILER_USER",
                "MAILER_PASSWORD",
                "MAILER_PORT",
                "MAILER_PROTOCOL",
                "MAILER_SENDER_NAME",
                "MAILER_SENDER_MAIL",
            ]);
        }

        if($data->has(ParametrageGlobal::WEBSITE_LOGO) && $data->get(ParametrageGlobal::WEBSITE_LOGO) === "null") {
            $setting = $settings[ParametrageGlobal::WEBSITE_LOGO];
            $setting->setValue(ParametrageGlobal::DEFAULT_WEBSITE_LOGO_VALUE);

            $saved[] = ParametrageGlobal::WEBSITE_LOGO;
        }

        if($data->has(ParametrageGlobal::MOBILE_LOGO_LOGIN) && $data->get(ParametrageGlobal::MOBILE_LOGO_LOGIN) === "null") {
            $setting = $settings[ParametrageGlobal::MOBILE_LOGO_LOGIN];
            $setting->setValue(ParametrageGlobal::DEFAULT_MOBILE_LOGO_LOGIN_VALUE);

            $saved[] = ParametrageGlobal::MOBILE_LOGO_LOGIN;
        }

        if($data->has(ParametrageGlobal::EMAIL_LOGO) && $data->get(ParametrageGlobal::EMAIL_LOGO) === "null") {
            $setting = $settings[ParametrageGlobal::EMAIL_LOGO];
            $setting->setValue(ParametrageGlobal::DEFAULT_EMAIL_LOGO_VALUE);

            $saved[] = ParametrageGlobal::EMAIL_LOGO;
        }

        if($data->has(ParametrageGlobal::MOBILE_LOGO_HEADER) && $data->get(ParametrageGlobal::MOBILE_LOGO_HEADER) === "null") {
            $setting = $settings[ParametrageGlobal::MOBILE_LOGO_HEADER];
            $setting->setValue(ParametrageGlobal::DEFAULT_MOBILE_LOGO_HEADER_VALUE);

            $saved[] = ParametrageGlobal::MOBILE_LOGO_HEADER;
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

        return $saved;
    }

    private function saveDatatables(array $tables, array $data) {
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
                $category = "";
                if (isset($categoryData['categoryId'])){
                    $category = $categoryRepository->find($categoryData['categoryId']);
                } else {
                    $category = new InventoryCategory();
                }
                $category->setLabel($categoryData['label']);
                $category->setFrequency($frequence);
                $category->setPermanent($categoryData['permanent']);

                $this->manager->persist($category);
            }
        }

        if(isset($tables["freeFields"])) {
            $ids = array_map(fn($freeField) => $freeField["id"] ?? null, $tables["freeFields"]);

            if(isset($data["entity"])) {
                if(!is_numeric($data["entity"]) && in_array($data["entity"], CategoryType::ALL)) {
                    $categoryRepository = $this->manager->getRepository(CategoryType::class);

                    $type = new Type();
                    $type->setCategory($categoryRepository->findOneBy(["label" => $data["entity"]]));

                    $this->manager->persist($type);
                } else {
                    $type = $this->manager->find(Type::class, $data["entity"]);
                }

                if (!isset($type)) {
                    throw new RuntimeException("Le type est introuvable");
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
                $type = $this->manager->getRepository(Type::class)->findOneByLabel(Type::LABEL_MVT_TRACA);
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
    }

    /**
     * Runs utilities when needed after settings have been saved
     * such as cache clearing translation updates or webpack build
     */
    private function postSaveTreatment(array $updatedSettings) {
        if(array_intersect($updatedSettings, [ParametrageGlobal::FONT_FAMILY])) {
            $this->generateFontSCSS();
            $this->yarnBuild();
        }

        if(array_intersect($updatedSettings, [ParametrageGlobal::MAX_SESSION_TIME])) {
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

        $font = $this->manager->getRepository(ParametrageGlobal::class)
                ->getOneParamByLabel(ParametrageGlobal::FONT_FAMILY) ?? ParametrageGlobal::DEFAULT_FONT_FAMILY;

        file_put_contents($path, "\$mainFont: \"$font\";");
    }

    public function generateSessionConfig() {
        $sessionLifetime = $this->manager->getRepository(ParametrageGlobal::class)
            ->getOneParamByLabel(ParametrageGlobal::MAX_SESSION_TIME);

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

    public function cacheClear() {
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

}
