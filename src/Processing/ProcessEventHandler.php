<?php

declare(strict_types=1);

namespace App\Processing;

use App\Ingest\IngestException;
use App\Ingest\JsonDecoder;
use App\Message\ProcessEvent;
use App\Notification\IssueNotifierInterface;
use App\Repository\ProjectRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ProcessEventHandler
{
    public function __construct(
        private ProjectRepository $projects,
        private JsonDecoder $json,
        private EventNormalizer $normalizer,
        private Scrubber $scrubber,
        private Grouper $grouper,
        private EventRecorder $recorder,
        private IssueNotifierInterface $notifier,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(ProcessEvent $message): void
    {
        $project = $this->projects->find($message->projectId);
        if (null === $project) {
            return;
        }

        try {
            $payload = $this->json->decodeObject($message->payload);
        } catch (IngestException $e) {
            $this->logger->warning('Dropped undecodable event {event_id}: {reason}', ['event_id' => $message->eventId, 'reason' => $e->getMessage()]);

            return;
        }

        $event = $this->scrubber->scrubEvent($this->normalizer->normalize($message->eventId, $payload, $message->receivedAt));
        $recorded = $this->recorder->record($project, $event, $this->grouper->fingerprint($event), IssueSummary::of($event), $message->receivedAt);

        if (null !== $recorded && null !== $recorded->change) {
            $this->notifier->notify($recorded->issue, $recorded->change);
        }
    }
}
