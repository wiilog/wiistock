<?php


namespace App\Entity;


use App\Repository\ProjectHistoryRecordRepository;
use Doctrine\ORM\Mapping as ORM;
use DateTime;

#[ORM\Entity(repositoryClass: ProjectHistoryRecordRepository::class)]
class ProjectHistoryRecord {

    #[ORM\Column(type: 'datetime')]
    private ?DateTime $date = null;

    /*#[ORM\Column(targetEntity: Pack::class)]
    private ?Pack $pack = null;*/

    #[ORM\Column(type: 'datetime')]
    private ?Project $project = null;

    #[ORM\Column(type: 'datetime')]
    private ?Article $article = null;

    public function __construct() {

    }
}
