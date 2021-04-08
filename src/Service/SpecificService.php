<?php

namespace App\Service;

class SpecificService
{
	const CLIENT_COLLINS_VERNON = 'collins-vernon';
	const CLIENT_COLLINS_SOA = 'collins-soa';
	const CLIENT_CEA_LETI = 'cea-leti';
	const CLIENT_SAFRAN_CS = 'safran-cs';
	const CLIENT_SAFRAN_ED = 'safran-ed';
	const CLIENT_TEREGA = 'terega';
	const CLIENT_AIA = 'aia';
	const CLIENT_EMERSON = 'emerson';
	const CLIENT_ARCELOR = 'arcelor';
	const CLIENT_ARKEMA_SERQUIGNY = 'arkema-serquigny';
	const CLIENT_WIILOG = 'wiilog';

	const CLIENTS = [
        self::CLIENT_COLLINS_VERNON => 'Collins Vernon',
        self::CLIENT_COLLINS_SOA => 'Collins SOA',
        self::CLIENT_CEA_LETI => 'CEA Leti',
        self::CLIENT_SAFRAN_CS => 'Safran CS',
        self::CLIENT_SAFRAN_ED => 'Safran ED',
        self::CLIENT_TEREGA => 'Terega',
        self::CLIENT_AIA => 'AIA',
        self::CLIENT_EMERSON => 'Emerson',
        self::CLIENT_ARCELOR => 'Arcelor',
        self::CLIENT_ARKEMA_SERQUIGNY => 'Arkema Serquigny',
        self::CLIENT_WIILOG => 'Wiilog',
    ];

	const ARRIVAGE_SPECIFIQUE_SED_MVT_DEPOSE = 'CHARIOT COLIS';

    /**
     * @param string[]|string $clientName
     * @return bool
     */
	public function isCurrentClientNameFunction($clientName): bool
	{
	    if(!is_array($clientName)) {
	        $clientName = [$clientName];
        }

        return in_array($this->getAppClient(), $clientName);
	}

	public function getAppClient(): string {
		return isset($_SERVER['APP_CLIENT'])
			? $_SERVER['APP_CLIENT']
			: '';
	}

}
