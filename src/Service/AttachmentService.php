<?php

namespace App\Service;

use App\Entity\Attachment;
use App\Entity\Interfaces\AttachmentContainer;
use App\Entity\TrackingMovement;
use Doctrine\ORM\EntityManagerInterface;
use ReflectionClass;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\FileBag;
use Symfony\Component\HttpKernel\KernelInterface;
use WiiCommon\Helper\Stream;


class AttachmentService {

    private string $attachmentDirectory;

    public function __construct(KernelInterface $kernel) {
        $this->attachmentDirectory = "{$kernel->getProjectDir()}/public/uploads/attachments";
    }

    /**
     * @param UploadedFile[]|FileBag $files
     * @return Attachment[]
     */
	public function createAttachments(array|FileBag $files): array {
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
                    ->setFileName($fileName)
                    ->setFullPath("/uploads/attachments/$fileName");
                $attachments[] = $attachment;
			}
		}

        return $attachments;
	}

	public function saveFile(UploadedFile $file, string $wantedName = null): array {
        if (!file_exists($this->attachmentDirectory)) {
            mkdir($this->attachmentDirectory, 0777);
        }
        $filename = ($wantedName ?? uniqid()) . '.' . strtolower($file->getClientOriginalExtension()) ?? '';
        dump($this->attachmentDirectory, $filename);
        dump(new \Exception("PIPI"));
        $file->move($this->attachmentDirectory, $filename);
        return [$file->getClientOriginalName() => $filename];
    }

	public function getServerPath(Attachment $attachment): string {
	    return $this->attachmentDirectory . '/' . $attachment->getFileName();
    }

	public function getAttachmentDirectory(): string {
	    return $this->attachmentDirectory;
    }

	public function putCSVLines($file, array $content, callable $mapper): void {
        foreach ($content as $row) {
            fputcsv($file, $mapper($row), ';');
        }
    }

    public function manageAttachments(EntityManagerInterface $entityManager, $attachmentEntity, FileBag $files): array {
        $reflect = new ReflectionClass($attachmentEntity);
        $dedicatedAttachmentFolder = strtolower($reflect->getShortName()) . '/' . $attachmentEntity->getId();
        $addedAttachments = [];
        foreach ($files as $file) {
            $attachment = $this->saveFileInDedicatedFolder($file, $dedicatedAttachmentFolder);
            $attachmentEntity->addAttachment($attachment);
            $addedAttachments[] = $attachment;
            $entityManager->persist($attachment);
        }
        return $addedAttachments;
    }

    private function saveFileInDedicatedFolder(UploadedFile $uploadedFile, string $dedicatedSubFolder): Attachment {
        $dedicatedFolder = "$this->attachmentDirectory/$dedicatedSubFolder";
        if (!file_exists($dedicatedFolder)) {
            mkdir($dedicatedFolder, 0777, true);
        }

        $filename = $uploadedFile->getClientOriginalName();

        $nameWithoutExtension = pathinfo($filename, PATHINFO_FILENAME);
        $extension = pathinfo($filename, PATHINFO_EXTENSION);

        $i = 1;
        do {
            $filename = "$nameWithoutExtension" . ($i !== 1 ? "_$i" : "") . ".$extension";
            $fullPath = "$dedicatedFolder/$filename";
            $publicPath = Attachment::MAIN_PATH . "/$dedicatedSubFolder/$filename";
            $i++;
        } while (file_exists($fullPath));

        $uploadedFile->move($dedicatedFolder, $filename);

        return $this->createAttachment($filename, $publicPath);
    }

    public function createAttachment(string $fileName, string $fullPath): Attachment {
        return (new Attachment())
            ->setOriginalName($fileName)
            ->setFullPath($fullPath)
            ->setFileName($fileName);
    }

    public function createFile(string $fileName, string $data): string {
        $attachmentsDirectory = $this->attachmentDirectory;
        if (!file_exists($attachmentsDirectory)) {
            mkdir($attachmentsDirectory);
        }

        $filePath = "$attachmentsDirectory/$fileName";
        file_put_contents($filePath, $data);

        return $filePath;
    }

    /**
     * @param FileBag|UploadedFile[] $files
     * @return Attachment[]
     */
    public function persistAttachments(EntityManagerInterface $entityManager,
                                       AttachmentContainer    $attachmentContainer,
                                       FileBag|array          $files,
                                       array                  $options = []): array {

        if ($files instanceof FileBag) {
            $files = $files->all();
        }

        if (!empty($files)) {
            $isAddToDispatch = $options['addToDispatch'] ?? false;
            $attachments = $this->createAttachments($files);
            foreach ($attachments as $attachment) {
                $entityManager->persist($attachment);
                $attachmentContainer->addAttachment($attachment);
                if ($attachmentContainer instanceof TrackingMovement && $isAddToDispatch && $attachmentContainer->getDispatch()) {
                    $attachmentContainer->getDispatch()->addAttachment($attachment);
                }
            }
        }

        return $attachments ?? [];
    }

    /**
     * @param int[] $attachmentIdToKeep
     */
    public function removeAttachments(EntityManagerInterface $entityManager,
                                      AttachmentContainer    $attachmentContainer,
                                      array                  $attachmentIdToKeep = []): void {
        $attachmentsToRemove = Stream::from($attachmentContainer->getAttachments()->toArray())
            ->filter(static fn(Attachment $attachment) => (
                empty($attachmentIdToKeep)
                || !in_array($attachment->getId(), $attachmentIdToKeep)
            ))
            ->toArray();

        foreach ($attachmentsToRemove as $attachment) {
            $attachmentContainer->removeAttachment($attachment);
            $entityManager->remove($attachment);
        }
    }
}
