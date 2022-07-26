<?php

namespace App\Controller;

use App\Entity\Role;
use App\Entity\Utilisateur;
use App\Kernel;
use PhpOffice\PhpWord\Element\Table;
use PhpOffice\PhpWord\Element\TextRun;
use PhpOffice\PhpWord\TemplateProcessor;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Annotation\Route;


#[Route("/")]
class AppController extends AbstractController {

    #[Route("/accueil", name: "app_index")]
    public function index(): Response {
        /** @var Utilisateur $user */
        $user = $this->getUser();

        $landingPageController = match ($user?->getRole()?->getLandingPage()) {
            Role::LANDING_PAGE_TRANSPORT_PLANNING => Transport\PlanningController::class . '::index',
            Role::LANDING_PAGE_TRANSPORT_REQUEST => Transport\RequestController::class . '::index',
            // Role::LANDING_PAGE_DASHBOARD
            default => DashboardController::class . '::index'
        };

        return $this->render('index.html.twig', ['landingPageController' => $landingPageController]);
    }

    #[Route("/qqq", name: "app_qqq")]
    public function qqq(KernelInterface $kernel): Response {

        $templateProcessor = new TemplateProcessor($kernel->getProjectDir() . '/Hello.docx');
        $inline = new TextRun();
        $inline->addText('by a red italic text', array('italic' => true, 'color' => 'red'));



        $table = new Table([]);

        $row = $table->addRow();
        $row->addCell(150)->addText('AAA');
        $row->addCell(150)->addText('AAA222');

        $row = $table->addRow();
        $row->addCell(150)->addText('BB');
        $row->addCell(150)->addText('BBB');

        $values = [
            ['userId' => 'huu', 'user1' => 'Batman', 'user2' => 'toto'],
            ['userId' => 'hii', 'user1' => 'Batman', 'user2' => $inline],
            ['userId' => 'haa', 'user1' => 'Superman', 'user2' => $table],
            ['userId' => 'haa', 'user1' => 'Superman', 'user2' => $table],
            ['userId' => 'haa', 'user1' => 'Superman', 'user2' => $table],
            ['userId' => 'haa', 'user1' => 'Superman', 'user2' => $table],
            ['userId' => 'haa', 'user1' => 'Superman', 'user2' => $table],
            ['userId' => 'haa', 'user1' => 'Superman', 'user2' => $table],
            ['userId' => 'haa', 'user1' => 'Superman', 'user2' => $table],
            ['userId' => 'haa', 'user1' => 'Superman', 'user2' => $table],
            ['userId' => 'haa', 'user1' => 'Superman', 'user2' => $table],
            ['userId' => 'haa', 'user1' => 'Superman', 'user2' => $table],
            ['userId' => 'haa', 'user1' => 'Superman', 'user2' => $table],
            ['userId' => 'haa', 'user1' => 'Superman', 'user2' => $table],
            ['userId' => 'haa', 'user1' => 'Superman', 'user2' => $table],
            ['userId' => 'haa', 'user1' => 'Superman', 'user2' => $table],
            ['userId' => 'haa', 'user1' => 'Superman', 'user2' => $table],
        ];
        $templateProcessor->cloneRow('userId', count($values));

        foreach ($values as $rowKey => $rowData) {
            $rowNumber = $rowKey + 1;
            foreach ($rowData as $macro => $replace) {
                if (is_object($replace)) {
                    $templateProcessor->setComplexBlock($macro . '#' . $rowNumber, $replace);
                }
                else {
                    $templateProcessor->setValue($macro . '#' . $rowNumber, $replace);
                }

            }
        }






        $templateProcessor->setValue('header', 'HEADER 369');
        $templateProcessor->setValue('firstname', 'Matteo');
        $templateProcessor->setValue('lastname', 'Marwane');
        $templateProcessor->setValue('block_name', 'Texte par défauttt EDITED');
        $templateProcessor->setImageValue('image', $kernel->getProjectDir() . '/public/uploads/attachements/5f5d4f8abfc9a.jpeg');
        $templateProcessor->setImageValue('photoZonText', $kernel->getProjectDir() . '/public/uploads/attachements/5f5d4f8abfc9a.jpeg');
        $templateProcessor->saveAs($kernel->getProjectDir() . '/Hello1.docx');
        $response = new BinaryFileResponse($kernel->getProjectDir() . '/Hello1.docx');
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, 'Hello1.docx');
        $response->deleteFileAfterSend(true);
        return $response;
    }
}
