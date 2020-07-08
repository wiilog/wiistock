<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\Arrivage;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200616141011 extends AbstractMigration implements ContainerAwareInterface
{
    use ContainerAwareTrait;

    public function getDescription() : string
    {
        return 'Removes SQL doublons for numéro in arrivage';
    }

    public function up(Schema $schema) : void
    {

    }

    public function postUp(Schema $schema): void
    {
        // On récupère les numéros d'arrivage en doublon
        $entityManager = $this->container->get('doctrine.orm.entity_manager');
        $queryBuilder = $entityManager->createQueryBuilder();
        $queryBuilder
            ->select('arrivage.numeroArrivage')
            ->from(Arrivage::class, 'arrivage')
            ->groupBy('arrivage.numeroArrivage')
            ->having('COUNT(arrivage.id) > 1');
        $doublonsNumeroArrivage = array_map(function (array $arrivage) {
            return $arrivage['numeroArrivage'];
        }, $queryBuilder->getQuery()->getResult());

        foreach ($doublonsNumeroArrivage as $doublon) {
            // On récupère les arrivages dont le numéro est en doublon
            $index = 0;
            $arrivalQuery = $entityManager->createQueryBuilder();
            $arrivalQuery
                ->select('arrivage')
                ->from(Arrivage::class, 'arrivage')
                ->where('arrivage.numeroArrivage LIKE :doublon')
                ->setParameter('doublon', $doublon);
            $arrivalsDoublon = $arrivalQuery->getQuery()->getResult();
            foreach ($arrivalsDoublon as $arrival) {
                $arrival
                    ->setNumeroArrivage($doublon . '-' . ($index < 10 ? ('0' . $index) : $index));
                $index++;
            }
        }
        $entityManager->flush();
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
