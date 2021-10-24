<?php
namespace OCA\DuplicateFinder\Listener;

use OCP\ILogger;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCA\DuplicateFinder\Db\FileInfo;
use OCA\DuplicateFinder\Event\CalculatedHashEvent;
use OCA\DuplicateFinder\Service\FileInfoService;
use OCA\DuplicateFinder\Service\FileDuplicateService;

/**
 * @template T of Event
 * @implements IEventListener<T>
 */
class NewHashListener implements IEventListener
{

    /** @var FileInfoService */
    private $fileInfoService;
    /** @var FileDuplicateService */
    private $fileDuplicateService;
    /** @var Ilogger */
    private $logger;

    public function __construct(
        FileInfoService $fileInfoService,
        FileDuplicateService $fileDuplicateService,
        ILogger $logger
    ) {
        $this->fileInfoService = $fileInfoService;
        $this->fileDuplicateService = $fileDuplicateService;
        $this->logger = $logger;
    }

    public function handle(Event $event): void
    {
        try {
            if ($event instanceof CalculatedHashEvent && $event->isChanged()) {
                $fileInfo = $event->getFileInfo();
                $this->updateDuplicates($fileInfo, $event->getOldHash());
            }
        } catch (\Throwable $e) {
            $this->logger->error('Failed to handle new hash event .', ['exception'=> $e]);
            $this->logger->logException($e, ['app'=>'duplicatefinder']);
        }
    }

    private function updateDuplicates(FileInfo $fileInfo, ?string $oldHash, string $type = 'file_hash'): void
    {
        $hash = $fileInfo->getFileHash();
        if (is_null($hash)) {
            if (is_null($oldHash)) {
                return;
            }
            $hash = $oldHash;
        }
        $count = $this->fileInfoService->countByHash($hash, $type);
        if ($count > 1) {
            try {
                $this->fileDuplicateService->getOrCreate($hash, $type);
            } catch (\Exception $e) {
                $this->logger->logException($e, ['app' => 'duplicatefinder']);
            }
        } else {
            $this->fileDuplicateService->delete($hash);
        }
    }
}
