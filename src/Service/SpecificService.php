<?php
/**
 * Created by VisualStudioCode.
 * User: jv.Sicot
 * Date: 03/04/2019
 * Time: 15:09
 */

namespace App\Service;

class SpecificService
{
	const CLIENT_COLLINS = 'collins';
	const CLIENT_CEA_LETI = 'cea-leti';
	const CLIENT_SAFRAN_CS = 'safran-cs';
	const CLIENT_SAFRAN_ED = 'safran-ed';
	const CLIENT_TEREGA = 'terega';
	const CLIENT_AIA = 'aia';
	const CLIENT_EMERSON = 'emerson';
	const CLIENT_ARCELOR = 'arcelor';

	const ARRIVAGE_SPECIFIQUE_SED_MVT_DEPOSE = 'CHARIOT COLIS';

	public function isCurrentClientNameFunction(string $clientName): bool
	{
		return (isset($_SERVER['APP_CLIENT']) && $_SERVER['APP_CLIENT'] == $clientName);
	}

	public function getAppClient(): string {
		return isset($_SERVER['APP_CLIENT'])
			? $_SERVER['APP_CLIENT']
			: '';
	}

}
