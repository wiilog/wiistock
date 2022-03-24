<?php


namespace App\Service;

use App\Entity\Nature;
use App\Entity\Transport\TemperatureRange;
use App\Helper\FormatHelper;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\InputBag;
use Twig\Environment as Twig_Environment;
use WiiCommon\Helper\Stream;

class NatureService
{
    /** @Required */
    public Twig_Environment $templating;

    /** @Required */
    public EntityManagerInterface $manager;

    public function getDataForDatatable(InputBag $params) {
        $natureRepository = $this->manager->getRepository(Nature::class);
        $queryResult = $natureRepository->findByParams($params);

        $natures = $queryResult['data'];

        $rows = [];
        foreach ($natures as $nature) {
            $rows[] = $this->dataRowNature($nature);
        }

        return [
            'data' => $rows,
            'recordsTotal' => $queryResult['total'],
            'recordsFiltered' => $queryResult['count'],
        ];
    }

    public function dataRowNature(Nature $nature): array {
        return [
            'label' => $nature->getLabel(),
            'code' => $nature->getCode(),
            'defaultQuantity' => $nature->getDefaultQuantity() ?? 'Non définie',
            'prefix' => $nature->getPrefix() ?? 'Non défini',
            'needsMobileSync' => FormatHelper::bool($nature->getNeedsMobileSync()),
            'displayed' => FormatHelper::bool($nature->getDisplayed()),
            'displayedOnForms' => FormatHelper::bool($nature->getDisplayedOnForms()),
            'color' => $nature->getColor() ? '<div style="background-color:' . $nature->getColor() . ';"><br></div>' : 'Non définie',
            'description' => $nature->getDescription() ?? 'Non définie',
            'temperatures' => Stream::from($nature->getTemperatureRanges())->map(fn(TemperatureRange $temperature) => $temperature->getValue())->join(", "),
            'actions' => $this->templating->render('nature_param/datatableNatureRow.html.twig', [
                'natureId' => $nature->getId(),
            ]),
        ];
    }

    public function serializeNature(Nature $nature): array {
        return [
            'id' => $nature->getId(),
            'label' => $nature->getLabel(),
            'color' => $nature->getColor(),
            'hide' => (bool) !$nature->getNeedsMobileSync()
        ];
    }
}
