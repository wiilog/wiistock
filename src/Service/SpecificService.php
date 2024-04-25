<?php

namespace App\Service;

use App\Entity\Setting;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Service\Attribute\Required;

class SpecificService
{
    #[Required]
    public EntityManagerInterface $entityManager;

    #[Required]
    public CacheService $cacheService;

    const DEFAULT_CLIENT_LABEL= 'WiiStock';

	const CLIENT_001 = '001';
	const CLIENT_COLLINS_SOA = 'collins-soa';
	const CLIENT_CEA_LETI = 'cea-leti';
	const CLIENT_SAFRAN_CS = 'safran-cs';
    const CLIENT_SAFRAN_ED = 'safran-ed';
    const CLIENT_SAFRAN_NS = 'safran-ns';
    const CLIENT_SAFRAN_MC = 'safran-mc';
	const CLIENT_EMERSON = 'emerson';
	const CLIENT_ARCELOR = 'arcelor';
	const CLIENT_ARKEMA_SERQUIGNY = 'arkema-serquigny';
    const CLIENT_INEO_LAV = 'ineos-lav';
    const CLIENT_CLB = 'clb';
    const CLIENT_AIA_BRETAGNE = 'aia-bretagne';
    const CLIENT_AIA_CUERS = 'aia-cuers';

    public const SPECIFIC_DASHBOARD_REFRESH_RATE = [
        self::CLIENT_SAFRAN_MC => 1,
        self::CLIENT_001=> 1,
        self::CLIENT_SAFRAN_CS=> 1,
    ];

    public const DEFAULT_DASHBOARD_REFRESH_RATE = 5;

	public function isCurrentClientNameFunction(string|array $clientName): bool
	{
	    if(!is_array($clientName)) {
	        $clientName = [$clientName];
        }

        return in_array($this->getAppClient(), $clientName);
	}

	public function getAppClient(): string {
		return $_SERVER['APP_CLIENT'] ?? '';
	}

    public function getAppClientLabel(): string {
        return $this->cacheService->get(
            CacheService::COLLECTION_SETTINGS,
            Setting::APP_CLIENT_LABEL,
            fn() => $this->entityManager->getRepository(Setting::class)->getOneParamByLabel(Setting::APP_CLIENT_LABEL) ?? self::DEFAULT_CLIENT_LABEL
        );
    }

}
