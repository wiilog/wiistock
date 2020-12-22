<?php

namespace App\DataFixtures;

use App\Entity\Dashboard;
use App\Service\SpecificService;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

class DashboardComponentTypesFixtures extends Fixture implements FixtureGroupInterface
{
    private $encoder;
    private $specificService;
    private $output;

    private const COMPONENT_TYPES = [
        'Quantité en cours sur n emplacement(s)' => [
            'template' => 'location_for_outstanding',
            'hint' => 'Nombre de colis en encours sur les emplacements sélectionnés',
            'exampleValues' => [],
        ]
    ];

    public function __construct(UserPasswordEncoderInterface $encoder,
                                SpecificService $specificService)
    {
        $this->encoder = $encoder;
        $this->specificService = $specificService;
        $this->output = new ConsoleOutput();
    }

    public function load(ObjectManager $manager) {
        $componentTypeRepository = $manager->getRepository(Dashboard\ComponentType::class);
        $alreadyExisting = $componentTypeRepository->findAll();
        $alreadyExistingName = [];

        // remove unused ComponentType
        foreach ($alreadyExisting as $componentType) {
            $name = $componentType->getName();
            if (!isset(self::COMPONENT_TYPES[$name])) {
                $manager->remove($componentType);
                $this->output->writeln("Component Type \"$name\" removed");
            } else {
                $alreadyExistingName[$name] = $componentType;
            }
        }

        // we persist new ComponentType
        foreach (self::COMPONENT_TYPES as $name => $config) {
            if (!isset($alreadyExistingName[$name])) {
                $componentType = new Dashboard\ComponentType();
                $componentType
                    ->setName($name)
                    ->setHint($config['hint'])
                    ->setExampleValues($config['exampleValues'])
                    ->setTemplate($config['template']);
                $manager->persist($componentType);
                $this->output->writeln("Component Type \"$name\" persisted");
            } else {
                $alreadyExistingName[$name]
                    ->setHint($config['hint'])
                    ->setExampleValues($config['exampleValues'])
                    ->setTemplate($config['template']);
            }
        }

        $manager->flush();
    }

    public static function getGroups(): array
    {
        return ['fixtures'];
    }
}
