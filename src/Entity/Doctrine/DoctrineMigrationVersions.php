<?php

namespace App\Entity\Doctrine;

use DateTime;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Declare migration_versions table to prevent bin/console d:s:u -f --complete to remove it
 */
#[ORM\Entity()]
class DoctrineMigrationVersions
{
    #[ORM\Id]
    #[ORM\Column(type: Types::STRING, length: 191)]
    private ?string $version = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?DateTime $executedAt = null;

    #[ORM\Column(type: Types::INTEGER)]
    private ?string $executionTime = null;

}
