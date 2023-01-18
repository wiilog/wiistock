<?php


namespace App\Service;


use App\Entity\FiltreSup;
use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Security;
use Symfony\Contracts\Service\Attribute\Required;

class FilterSupService
{

    #[Required]
    public DisputeService $disputeService;

    #[Required]
    public Security $security;

    /**
     * @param string $page
     * @param string $filterName
     * @param string $value
     * @param Utilisateur $user
     * @return FiltreSup
     */
    public function createFiltreSup(string $page, string $filterName, ?string $value, Utilisateur $user): FiltreSup {
        $filter = new FiltreSup();
        $filter->setField($filterName)
            ->setPage($page)
            ->setUser($user);

        if ($value) {
            $filter->setValue($value);
        }

        return $filter;
    }

    public function getFilters(EntityManagerInterface $entityManager, string $page) {
        $filtreSupRepository = $entityManager->getRepository(FiltreSup::class);

        $filters = $filtreSupRepository->getFieldAndValueByPageAndUser($page, $this->security->getUser());
        if ($page === FiltreSup::PAGE_DISPUTE) {
            $translations = $this->disputeService->getLitigeOrigin();
            foreach ($filters as $index => $filter) {
                if (isset($translations[$filter['value']])) {
                    $filters[$index]['value'] = $translations[$filter['value']];
                }
            }
        }

        return $filters;
    }
}
