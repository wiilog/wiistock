<?php


namespace App\Service;

use App\Entity\Arrivage;
use App\Entity\FiltreSup;
use App\Entity\Group;
use App\Entity\Pack;
use App\Entity\TrackingMovement;
use App\Entity\Nature;
use App\Entity\Utilisateur;
use App\Repository\NatureRepository;
use DateTime;
use DateTimeZone;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\Security\Core\Security;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use Twig\Environment as Twig_Environment;


Class GroupService
{

    private $entityManager;
    private $security;
    private $template;
    private $arrivageDataService;
    private $specificService;

    public function __construct(ArrivageDataService $arrivageDataService,
                                SpecificService $specificService,
                                Security $security,
                                Twig_Environment $template,
                                EntityManagerInterface $entityManager) {
        $this->entityManager = $entityManager;
        $this->specificService = $specificService;
        $this->arrivageDataService = $arrivageDataService;
        $this->security = $security;
        $this->template = $template;
    }
    /**
     * @param array $options Either ['arrival' => Arrivage, 'nature' => Nature] or ['code' => string]
     * @return Group
     */
    public function createGroup(array $options = []): Group {
        $group = $this->createGroupWithCode($options['group']);
        $group
            ->setComment($options['comment'] ?? '')
            ->setIteration(1)
            ->setNature($options['nature'] ?? null)
            ->setVolume($options['volume'] ?? 0)
            ->setWeight($options['weight'] ?? 0);
        return $group;
    }

    /**
     * @param string code
     * @return Group
     */
    public function createGroupWithCode(string $code): Group {
        $group = new Group();
        $group->setCode(str_replace("    ", " ", $code));
        return $group;
    }
}
