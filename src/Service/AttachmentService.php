<?php

namespace App\Service;

use App\Entity\Dispatch;
use App\Entity\Arrivage;
use App\Entity\Handling;
use App\Entity\Litige;
use App\Entity\TrackingMovement;
use App\Entity\Attachment;
use Doctrine\ORM\EntityManagerInterface;
use ReflectionClass;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\FileBag;
use Symfony\Component\HttpKernel\KernelInterface;


class AttachmentService
{
    const LABEL_LOGO = 'logo_for_label';
    const DELIVERY_NOTE_LOGO = 'logo_for_delivery_note';
    const WAYBILL_LOGO = 'logo_for_waybill';

    private $attachmentDirectory;
	private $em;

    public function __construct(EntityManagerInterface $em,
                                KernelInterface $kernel) {
        $this->attachmentDirectory = $kernel->getProjectDir() . '/public/uploads/attachements';
    	$this->em = $em;
    }

	/**
	 * @param FileBag|UploadedFile[]|array $files if array it's an assoc array between originalFileName and serverFileName
	 * @return Attachment[]
	 */
	public function createAttachements($files) {
		$attachments = [];

        if ($files instanceof FileBag) {
            $files = $files->all();
        }

        $isFileName = count($files) > 0 && is_string($files[array_key_first($files)]);
        foreach ($files as $fileIndex => $file) {
			if ($file) {
                if ($isFileName) {
                    $originalFileName = $fileIndex;
                    $fileName = $file;
                } else {
                    $fileArray = $this->saveFile($file);
                    $originalFileName = $file->getClientOriginalName();
                    $fileName = $fileArray[$file->getClientOriginalName()];
                }
                $attachment = new Attachment();
                $attachment
                    ->setOriginalName($originalFileName)
                    ->setFileName($fileName);
                $attachments[] = $attachment;
			}
		}

        return $attachments;
	}

    /**
     * @param UploadedFile $file
     * @param string|null $wantedName
     * @return array [originalName (string) => filename (string)]
     */
	public function saveFile(UploadedFile $file, string $wantedName = null): array {
        if (!file_exists($this->attachmentDirectory)) {
            mkdir($this->attachmentDirectory, 0777);
        }

        $filename = ($wantedName ?? uniqid()) . '.' . $file->getClientOriginalExtension() ?? '';
        $file->move($this->attachmentDirectory, $filename);
        return [$file->getClientOriginalName() => $filename];
    }

	/**
	 * @param Attachment $attachment
	 * @param Arrivage|Litige|Dispatch|TrackingMovement|Handling $entity
	 */
	public function removeAndDeleteAttachment(Attachment $attachment,
                                              $entity)
	{
		if ($entity) {
            $entity->removeAttachment($attachment);
		}

        $attachmentRepository = $this->em->getRepository(Attachment::class);
        $pieceJointeAlreadyInDB = $attachmentRepository->findOneByFileName($attachment->getFileName());
        if (count($pieceJointeAlreadyInDB) === 1) {
            $path = $this->getServerPath($attachment);
            unlink($path);
        }

        $this->em->remove($attachment);
        $this->em->flush();
	}

    /**
     * @param Attachment $attachment
     * @return string
     */
	public function getServerPath(Attachment $attachment): string {
	    return $this->attachmentDirectory . '/' . $attachment->getFileName();
    }

    /**
     * @param string $fileName
     * @param array $content
     * @param callable $mapper
     * @return void
     */
	public function saveCSVFile(string $fileName, array $content, callable $mapper): void {
        $csvFilePath = $this->attachmentDirectory . '/' . $fileName;

        $logCsvFilePathOpened = fopen($csvFilePath, 'w');

        foreach ($content as $row) {
            fputcsv($logCsvFilePathOpened, $mapper($row), ';');
        }

        fclose($logCsvFilePathOpened);
    }

    /**
     * @param UploadedFile $file
     * @param Arrivage|Litige $link
     * @return Attachment
     */
    public function createPieceJointe(UploadedFile $file, $link): Attachment {
        if ($file->getClientOriginalExtension()) {
            $filename = uniqid() . "." . $file->getClientOriginalExtension();
        } else {
            $filename = uniqid();
        }
        $file->move($this->attachmentDirectory, $filename);

        $attachment = new Attachment();
        $attachment
            ->setFileName($filename)
            ->setOriginalName($file->getClientOriginalName());

        if ($link instanceof Arrivage) {
            $attachment->setArrivage($link);
        }
        else if($link instanceof Litige) {
            $attachment->setLitige($link);
        }

        return $attachment;
    }

    /**
     * @param EntityManagerInterface $entityManager
     * @param $attachmentEntity
     * @param FileBag $files
     * @throws \ReflectionException
     */
    public function manageAttachments(EntityManagerInterface $entityManager, $attachmentEntity, FileBag $files) {
        $reflect = new ReflectionClass($attachmentEntity);
        $dedicatedAttachmentFolder = strtolower($reflect->getShortName()) . '/' . $attachmentEntity->getId();
        foreach ($files as $file) {
            $attachment = $this->saveFileInDedicatedFolder($file, $dedicatedAttachmentFolder);
            $attachmentEntity->addAttachment($attachment);
            $entityManager->persist($attachment);
        }
    }

    private function saveFileInDedicatedFolder(UploadedFile $uploadedFile, string $dedicatedSubFolder): Attachment {
        $dedicatedFolder = $this->attachmentDirectory . '/' . $dedicatedSubFolder;
        if (!file_exists($dedicatedSubFolder)) {
            mkdir($dedicatedSubFolder, 0777, true);
        }

        $filename = $uploadedFile->getClientOriginalName();

        $fileNameWithoutExtension = pathinfo($filename, PATHINFO_FILENAME);
        $fileNameExtension = pathinfo($filename, PATHINFO_EXTENSION);

        $filePath = $dedicatedFolder . '/' . $filename;

        $relativePath = Attachment::MAIN_PATH . '/' . $dedicatedSubFolder . '/' . $filename;

        while (file_exists($filePath)) {
            $fileNameWithoutExtension .= '_BIS';
            $filename = $fileNameWithoutExtension . '.' . $fileNameExtension;
            $filePath = $dedicatedFolder . '/' . $filename;
        }

        $uploadedFile->move($dedicatedFolder, $filename);
        return $this->createAttachment($filename, $relativePath);
    }

    private function createAttachment(string $fileName, string $fullPath): Attachment {
        $attachment = new Attachment();
        $attachment
            ->setOriginalName($fileName)
            ->setFullPath($fullPath)
            ->setFileName($fileName);
        return $attachment;
    }
}
