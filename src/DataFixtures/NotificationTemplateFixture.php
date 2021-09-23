<?php

namespace App\DataFixtures;

use App\Entity\NotificationTemplate;
use App\Service\NotificationService;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Console\Output\ConsoleOutput;
use WiiCommon\Helper\Stream;

class NotificationTemplateFixture extends Fixture implements FixtureGroupInterface
{

    private const CONTENTS = [
        NotificationTemplate::PREPARATION => "Un ordre de préparation @numordrepreparation à traiter\nType : @typelivraison",
        NotificationTemplate::DELIVERY => "Un ordre de livraison @numordrelivraison à traiter\nType : @typelivraison",
        NotificationTemplate::COLLECT => "Un ordre de collecte @numordrecollecte à traiter\nType : @typecollecte",
        NotificationTemplate::TRANSFER => "Un ordre de transfer @numordretransfert à traiter",
        NotificationTemplate::DISPATCH=> "Une demande d'acheminement @numacheminement à traiter\nType : @typeacheminement",
        NotificationTemplate::HANDLING => "Demande de service @numservice à traiter\nType : @typeservice",
    ];

    public function load(ObjectManager $manager)
    {
        $output = new ConsoleOutput();

        $notificationTemplateRepository = $manager->getRepository(NotificationTemplate::class);
        $existing = Stream::from($notificationTemplateRepository->findAll())
            ->map(fn(NotificationTemplate $template) => $template->getType());

        $keys = Stream::keys(NotificationService::READABLE_TYPES);
        $toCreate = Stream::diff($existing, $keys);

        foreach ($toCreate as $type) {
            $template = (new NotificationTemplate())
                ->setType($type)
                ->setContent(self::CONTENTS[$type]);

            $manager->persist($template);
            $output->writeln("Created notification template \"" . NotificationService::READABLE_TYPES[$type] . "\"");
        }

        $manager->flush();
    }

    public static function getGroups(): array
    {
        return ["fixtures"];
    }

}
