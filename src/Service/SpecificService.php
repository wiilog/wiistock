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

	const CLIENT_BARBECUE = 'barbecue';
	const CLIENT_BOURGUIGNON = 'bourguignon';
	const CLIENT_RATATOUILLE = 'ratatouille';
	const CLIENT_POTEE = 'potee';
    const CLIENT_PETIT_SALE = 'petit_sale';
    const CLIENT_CHOU_FARCI = 'chou_farci';
    const CLIENT_QUICHE = 'quiche';
	const CLIENT_OMELETTE = 'omelette';
	const CLIENT_GRATIN_DAUPHINOIS = 'gratin_dauphinois';
	const CLIENT_TRUFFADE = 'truffade';
    const CLIENT_ALIGOT = 'aligot';
    const CLIENT_TARTIFLETTE = 'tartiflette';
    const CLIENT_GALETTE_SAUCISSE = 'galette_saucisse';
    const CLIENT_PAELLA = 'paella';

    public const SPECIFIC_DASHBOARD_REFRESH_RATE = [
        self::CLIENT_QUICHE => 1,
        self::CLIENT_BARBECUE=> 1,
        self::CLIENT_POTEE=> 1,
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
