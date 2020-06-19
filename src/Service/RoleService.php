<?php


namespace App\Service;

use App\Entity\Action;
use App\Entity\Menu;
use App\Entity\Parametre;
use App\Entity\ParametreRole;
use App\Entity\Role;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;

class RoleService
{

    private $entityManager;

    public function __construct(EntityManagerInterface $entityManager) {
        $this->entityManager = $entityManager;
    }

    /**
     * @param Role $role
     * @param array $data
     * @throws NonUniqueResultException
     */
    public function parseParameters(Role $role, array $data) {
        $actionRepository = $this->entityManager->getRepository(Action::class);

        // on traite les actions
        $dashboardsVisible = [];
        foreach ($data as $menuAction => $isChecked) {
            $menuActionArray = explode('/', $menuAction);
            if (count($menuActionArray) > 1) {
                $menuLabel = $menuActionArray[0];
                $actionLabel = $menuActionArray[1];

                $action = $actionRepository->findOneByMenuLabelAndActionLabel($menuLabel, $actionLabel);
                if ($action && $isChecked) {
                    $role->addAction($action);
                } else {
                    $role->removeAction($action);
                }
            } else {
                if ($isChecked && is_string($menuAction)) {
                    $dashboardsVisible[] = $menuAction;
                }
            }
        }
        $role->setDashboardsVisible($dashboardsVisible);
    }

    public function createFormTemplateParameters(Role $role = null): array {
        $parametreRepository = $this->entityManager->getRepository(Parametre::class);
        $menuRepository = $this->entityManager->getRepository(Menu::class);
        $parametreRoleRepository = $this->entityManager->getRepository(ParametreRole::class);

        $menus = $menuRepository->findAll();

        $params = array_map(
            function (Parametre $param) use ($role, $parametreRoleRepository) {
                $paramArray = [
                    'id' => $param->getId(),
                    'label' => $param->getLabel(),
                    'typage' => $param->getTypage(),
                    'elements' => $param->getElements(),
                    'default' => $param->getDefaultValue()
                ];

                if (isset($role)) {
                    $paramArray['value'] = $parametreRoleRepository->getValueByRoleAndParam($role, $param);
                }

                return $paramArray;
            },
            $parametreRepository->findAll()
        );

        return [
            'menus' => $menus,
            'params' => $params,
            'dashboards' => [
                [
                    "id" => 'arrivage',
                    'label' => "Dashboard Réception Arrivage"
                ],
                [
                    "id" => 'quai',
                    'label' => "Dashboard Réception Quai"
                ],
                [
                    "id" => 'admin',
                    'label' => "Dashboard Réception Administrative"
                ],
                [
                    "id" => 'emballage',
                    'label' => "Dashboard Monitoring Emballage"
                ],
            ]
        ];
    }
}
