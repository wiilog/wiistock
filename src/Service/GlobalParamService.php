<?php


namespace App\Service;

use App\Entity\Emplacement;
use App\Entity\Setting;
use App\Entity\Type;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Yaml\Yaml;
// TODO WIIS-6693

Class GlobalParamService
{

    /** @Required */
	public KernelInterface $kernel;

	/** @Required */
    public EntityManagerInterface $entityManager;

	public function getDimensionAndTypeBarcodeArray(bool $includeNullDimensions = true) {
        $settingRepository = $this->entityManager->getRepository(Setting::class);

		return [
            "logo" => $settingRepository->getOneParamByLabel(Setting::LABEL_LOGO),
            "height" => $settingRepository->getOneParamByLabel(Setting::LABEL_HEIGHT) ?? 0,
            "width" => $settingRepository->getOneParamByLabel(Setting::LABEL_WIDTH) ?? 0,
            "isCode128" => $settingRepository->getOneParamByLabel(Setting::BARCODE_TYPE_IS_128),
        ];
	}

	public function getParamLocation(string $label) {
        $settingRepository = $this->entityManager->getRepository(Setting::class);
        $emplacementRepository = $this->entityManager->getRepository(Emplacement::class);

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
            $settingRepository = $this->entityManager->getRepository(Setting::class);
            $param = $settingRepository->findOneBy(['label' => Setting::FONT_FAMILY]);
            $font = $param ? $param->getValue() : Setting::DEFAULT_FONT_FAMILY;
        } else {
            $font = $font->getValue();
        }

		file_put_contents($scssFile, "\$mainFont: \"$font\";");
	}

    public function generateSessionConfig(int $sessionLifetime = null) {
        if(!$sessionLifetime) {
            $sessionLifetime = $this->entityManager->getRepository(Setting::class)
                ->getOneParamByLabel(Setting::MAX_SESSION_TIME);
        }

        $generated = "{$this->kernel->getProjectDir()}/config/generated.yaml";
        $config = [
            "parameters" => [
                "session_lifetime" => $sessionLifetime * 60,
            ],
        ];

        file_put_contents($generated, Yaml::dump($config));
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

	public function getDefaultDeliveryLocationsByType(EntityManagerInterface $entityManager): array {

        $typeRepository = $entityManager->getRepository(Type::class);
        $locationRepository = $entityManager->getRepository(Emplacement::class);
        $settingRepository = $entityManager->getRepository(Setting::class);

        $defaultDeliveryLocationsParam = $settingRepository->getOneParamByLabel(Setting::DEFAULT_LOCATION_LIVRAISON);
        $defaultDeliveryLocationsIds = json_decode($defaultDeliveryLocationsParam, true) ?: [];

        $defaultDeliveryLocations = [];
        foreach ($defaultDeliveryLocationsIds as $typeId => $locationId) {
            if ($typeId !== 'all' && $typeId) {
                $type = $typeRepository->find($typeId);
            }
            if ($locationId) {
                $location = $locationRepository->find($locationId);
            }

            $defaultDeliveryLocations[] = [
                'location' => isset($location)
                    ? [
                        'label' => $location->getLabel(),
                        'id' => $location->getId()
                    ]
                    : null,
                'type' => isset($type)
                    ? [
                        'label' => $type->getLabel(),
                        'id' => $type->getId()
                    ]
                    : null,
            ];
        }
        return $defaultDeliveryLocations;
    }

	public function getDefaultDeliveryLocationsByTypeId(?EntityManagerInterface $entityManager = null): array {
        $entityManager = $entityManager ?? $this->entityManager;
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
