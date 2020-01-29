<?php
/**
 * Created by VisualStudioCode.
 * User: jv.Sicot
 * Date: 03/04/2019
 * Time: 15:09
 */

namespace App\Service;

use App\Repository\MenuConfigRepository;

class SpecificService
{
	const CLIENT_COLLINS = 'collins';
	const CLIENT_CEA_LETI = 'cea-leti';
	const CLIENT_SAFRAN_CS = 'safran-cs';
	const CLIENT_SAFRAN_ED = 'safran-ed';

	private $menuConfigRepository;

	public function __construct(MenuConfigRepository $menuConfigRepository)
	{
		$this->menuConfigRepository = $menuConfigRepository;
	}

	public function isCurrentClientNameFunction(string $clientName)
	{
		return (isset($_SERVER['APP_CLIENT']) && $_SERVER['APP_CLIENT'] == $clientName);
	}

	public function getAppClient(): string {
		return isset($_SERVER['APP_CLIENT'])
			? $_SERVER['APP_CLIENT']
			: '';
	}

	public function displaySubmenuFunction($menu, $submenu)
	{
		return $this->menuConfigRepository->getOneDisplayByMenuAndSubmenu($menu, $submenu) == '1';
	}

	public function displayMenuFunction($menu)
	{
		return (int)$this->menuConfigRepository->countDisplayedByMenu($menu) > 0;
	}
}
