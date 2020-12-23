<?php

namespace App\DataFixtures;

use App\Entity\Dashboard;
use App\Entity\Type;
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
            'category' => 'Indicateurs'
        ],
        'Nombre d\'arrivages quotidiens' => [
            'template' => 'daily_arrivals',
            'hint' => 'Nombre d\'arrivages créés par jour',
            'exampleValues' => [],
            'category' => 'Graphiques'
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
            $componentType = $alreadyExistingName[$name] ?? null;
            $componentTypeExisted = isset($componentType);
            if (!$componentTypeExisted) {
                $componentType = new Dashboard\ComponentType();
                $componentType->setName($name);
                $manager->persist($componentType);
            }

            $componentType
                ->setHint($config['hint'] ?? '')
                ->setExampleValues($config['exampleValues'] ?? [])
                ->setCategory($config['category'] ?? [])
                ->setTemplate($config['template'] ?? '');

            $action = !$componentTypeExisted ? 'persisted' : 'updated';
            $this->output->writeln("Component Type \"$name\" $action");
        }

        $manager->flush();
    }

    public static function getGroups(): array
    {
        return ['fixtures'];
    }
}
