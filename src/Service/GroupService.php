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
use Twig\Environment;



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

    public function getDataForDatatable($params = null) {
        $filtreSupRepository = $this->manager->getRepository(FiltreSup::class);
        $groupRepository = $this->manager->getRepository(Group::class);

        $filters = $filtreSupRepository->getFieldAndValueByPageAndUser(FiltreSup::PAGE_PACK, $this->security->getUser());
        $queryResult = $groupRepository->findByParamsAndFilters($params, $filters);

        $packs = $queryResult['data'];

        $rows = [];
        foreach ($packs as $pack) {
            $rows[] = $this->dataRowGroup($pack);
        }

        return [
            'data' => $rows,
            'recordsFiltered' => $queryResult['count'],
            'recordsTotal' => $queryResult['total'],
        ];
    }

    public function dataRowGroup(Group $group) {
        return [
            "actions" => $this->template->render('group/table/actions.html.twig', [
                "group" => $group
            ]),
            "details" => $this->template->render("group/table/details.html.twig", [
                "group" => $group,
                "last_movement" => $group->getLastTracking(),
            ]),
        ];
    }
}
