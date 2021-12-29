<?php

namespace App\Service;

use App\Entity\MailerServer;
use App\Entity\ParametrageGlobal;
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

        $this->postSaveTreatment($updated);

        $this->manager->flush();
    }

    /**
     * Saves custom settings
     *
     * @param Request $request The request
     * @param ParametrageGlobal[] $settings Existing settings
     * @return array Settings that were processed
     */
    public function customSave(Request $request, array $settings): array {
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

    /**
     * Runs utilities when needed after settings have been saved
     * such as cache clearing translation updates or webpack build
     */
    public function postSaveTreatment(array $updatedSettings) {
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
        } catch (Exception $exception) {
            throw new RuntimeException("Une erreur est survenue lors du changement de client");
        }
    }

    public function generateFontSCSS() {
        $path =  "{$this->kernel->getProjectDir()}/assets/scss/_customFont.scss";

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
