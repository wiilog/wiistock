<?php

namespace App\Controller;

use FOS\RestBundle\Controller\FOSRestController;
use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Routing\ClassResourceInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Test;
use App\Form\TestType;
use App\Repository\TestRepository;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
* @Rest\RouteResource(
*	"Test"
* )
*/

class TestController extends FOSRestController implements ClassResourceInterface
{
	/**
	 * @var EntityManagerInterface
	 */
	private $em;

	/**
	 * @var TestRepository
	 */
	private $testRepository;


	public function __construct(EntityManagerInterface $em, TestRepository $testRepository) {
		$this->em = $em;
		$this->testRepository = $testRepository;
	}

	public function postAction(Request $request) {
		$form = $this->createForm(TestType::class, new Test());

		$form->submit($request->request->all());

		if (false === $form->isValid()) {
			return $this->handleView(
				$this->view($form)
			);
		}

		$this->em->persist($form->getData());
		$this->em->flush();

		return $this->handleView(
			$this->view(
				[
					'status' => 'ok',
				],
				JsonResponse::HTTP_CREATED
			)
		);
	}


	/**
	* @param $id
	*
	* @return Test|null
	* @throws NotFoundHttpException
	*
	*/
	private function findTestById($id) {
		$test = $this->testRepository->find($id);

		if(null === $test) {
			throw new NotFoundHttpException();
		}

		return $test;
	}

	public function getAction(string $id) {
		return $this->view(
			$this->findTestById($id)
		);
	}

	public function cgetAction() {
		return $this->view(
			$this->testRepository->findAll()
		);
	}

	public function putAction(Request $request, string $id) {
		$existingTest = $this->findTestById($id);

		$form = $this->createForm(TestType::class, $existingTest);

		$form->submit($request->request->all());

		if (false === $form->isValid()) {
			return $this->view($form);
		}

		$this->em->flush();

		return $this->view(null, Response::HTTP_NO_CONTENT);
	}

	public function patchAction(Request $request, string $id) {
		$existingTest = $this->findTestById($id);

		$form = $this->createForm(TestType::class, $existingTest);

		$form->submit($request->request->all(), false);

		if (false === $form->isValid()) {
			return $this->view($form);
		}

		$this->em->flush();

		return $this->view(null, Response::HTTP_NO_CONTENT);	
	}

	public function deleteAction(string $id) {
		$test = $this->findTestById($id);

		$this->em->remove($test);
		$this->em->flush();

		return $this->view(null, Response::HTTP_NO_CONTENT);
	}
}
