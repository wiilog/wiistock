<?php

namespace App\Service;

use App\Entity\CategorieCL;
use App\Entity\DaysWorked;
use App\Entity\FieldsParam;
use App\Entity\FreeField;
use App\Entity\MailerServer;
use App\Entity\ParametrageGlobal;
use App\Entity\Type;
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

    /**
     * @Required
     */
    public EntityManagerInterface $manager;

    /**
     * @Required
     */
    public KernelInterface $kernel;

    /**
     * @Required
     */
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

        if($client = $request->request->get(ParametrageGlobal::APP_CLIENT)) {
            $this->changeClient($client);
            $saved[] = ParametrageGlobal::APP_CLIENT;
        }

        if($request->request->has("MAILER_URL")) {
            $mailer = $this->manager->getRepository(MailerServer::class)->findOneBy([]);
            $mailer->setSmtp($request->request->get("MAILER_URL"));
            $mailer->setUser($request->request->get("MAILER_USER"));
            $mailer->setPassword($request->request->get("MAILER_PASSWORD"));
            $mailer->setPort($request->request->get("MAILER_PORT"));
            $mailer->setProtocol($request->request->get("MAILER_PROTOCOL"));
            $mailer->setSenderName($request->request->get("MAILER_SENDER_NAME"));
            $mailer->setSenderMail($request->request->get("MAILER_SENDER_MAIL"));

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

        if(isset($tables["articlesFreeFields"])) {
            $ids = array_map(fn($freeField) => $freeField["id"] ?? null, $tables["articlesFreeFields"]);

            $type = $this->manager->find(Type::class, $data["entity"]);
            $type->setDescription($data["description"] ?? null)
                ->setColor($data["color"] ?? null);

            $freeFieldRepository = $this->manager->getRepository(FreeField::class);
            $freeFields = Stream::from($freeFieldRepository->findBy(["id" => $ids]))
                ->keymap(fn($day) => [$day->getId(), $day])
                ->toArray();

            foreach(array_filter($tables["articlesFreeFields"]) as $item) {
                /** @var FreeField $freeField */
                $freeField = isset($item["id"]) ? $freeFields[$item["id"]] : new FreeField();

                $freeField->setLabel($item["label"])
                    ->setType($type)
                    ->setTypage($item["type"] ?? $freeField->getTypage())
                    ->setCategorieCL($this->manager->find(CategorieCL::class, $item["category"]))
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
