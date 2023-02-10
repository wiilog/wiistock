<?php

namespace App\Service;


use App\Entity\StorageRule;
use Symfony\Contracts\Service\Attribute\Required;


class StorageRuleService
{

    #[Required]
    public CSVExportService $CSVExportService;

    #[Required]
    public FormatService $formatService;

    public function putStorageRuleLine($output, array $storageRule): void {
        $line = [
            $storageRule['reference'],
            $storageRule['locationLabel'],
            $storageRule['securityQuantity'],
            $storageRule['conditioningQuantity'],
        ];
        $this->CSVExportService->putLine($output, $line);
    }
}
