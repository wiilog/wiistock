<?php
/**
 * Created by VisualStudioCode.
 * User: jv.Sicot
 * Date: 03/04/2019
 * Time: 15:09
 */

namespace App\Service;



use App\Entity\Action;
use App\Entity\ParamClient;
use App\Entity\Utilisateur;

use App\Repository\ActionRepository;
use App\Repository\ParamClientRepository;
use App\Repository\UtilisateurRepository;
use App\Repository\RoleRepository;
use Symfony\Component\Security\Core\Security;


class SpecificService
{

	/**
	 * @var ParamClientRepository
	 */
	private $paramClientRepository;


	public function __construct(ParamClientRepository $paramClientRepository)
	{
		$this->paramClientRepository = $paramClientRepository;
	}

	public function isCurrentClientNameFunction(string $clientName)
	{
		/** @var ParamClient $currentClient */
		$currentClient = $this->paramClientRepository->findOne();
		return $currentClient->getClient() == $clientName;
	}

}
