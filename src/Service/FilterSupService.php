<?php


namespace App\Service;


use App\Entity\FiltreSup;
use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;

class FilterSupService
{
    /**
     * @param string $page
     * @param string $filterName
     * @param string $value
     * @param Utilisateur $user
     * @return FiltreSup
     */
    public function createFiltreSup(string $page, string $filterName, ?string $value, Utilisateur $user): FiltreSup {
        $filter = new FiltreSup();
        $filter
            ->setField($filterName)
            ->setPage($page)
            ->setUser($user);
        if ($value) {
            $filter->setValue($value);
        }
        return $filter;
    }
}
