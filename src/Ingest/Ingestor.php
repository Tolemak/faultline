<?php

declare(strict_types=1);

namespace App\Ingest;

use App\Entity\Project;
use App\Ingest\Envelope\EnvelopeParser;
use App\Message\ProcessEvent;
use Psr\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class Ingestor
{
    public const string ENDPOINT_ENVELOPE = 'envelope';
    public const string ENDPOINT_STORE = 'store';

    public function __construct(
        private BodyDecoder $bodyDecoder,
        private JsonDecoder $json,
        private EnvelopeParser $envelopeParser,
        private SentryKeyExtractor $keys,
        private IngestRateLimiter $rateLimiter,
        private MessageBusInterface $bus,
        private ClockInterface $clock,
    ) {
    }

    public function ingest(Project $project, Request $request, string $endpoint): string
    {
        $this->bodyDecoder->assertDeclaredLength($request->headers->get('Content-Length'));

        $key = $this->keys->fromRequest($request);
        if (null !== $key) {
            $this->authorize($project, $key);
        }

        $body = $this->bodyDecoder->decode($request->getContent(), $request->headers->get('Content-Encoding'));

        return self::ENDPOINT_STORE === $endpoint
            ? $this->ingestStore($project, $body, $key)
            : $this->ingestEnvelope($project, $body, $key);
    }

    private function ingestStore(Project $project, string $body, ?string $key): string
    {
        if (null === $key) {
            throw IngestException::unauthorized();
        }

        $event = $this->json->decodeObject($body);
        $eventId = EventId::normalize($event['event_id'] ?? null) ?? EventId::generate();
        $this->dispatch($project, $eventId, $body);

        return $eventId;
    }

    private function ingestEnvelope(Project $project, string $body, ?string $key): string
    {
        $envelope = $this->envelopeParser->parse($body);

        if (null === $key) {
            $key = $this->keys->fromDsn($envelope->header('dsn')) ?? throw IngestException::unauthorized();
            $this->authorize($project, $key);
        }

        $responseId = EventId::normalize($envelope->header('event_id'));

        foreach ($envelope->eventItems() as $item) {
            $event = $this->json->decodeObject($item->payload);
            $eventId = EventId::normalize($event['event_id'] ?? null) ?? $responseId ?? EventId::generate();
            $this->dispatch($project, $eventId, $item->payload);
            $responseId ??= $eventId;
        }

        return $responseId ?? '';
    }

    private function authorize(Project $project, string $key): void
    {
        if (!hash_equals($project->getPublicKey(), $key)) {
            throw IngestException::unauthorized();
        }

        $this->rateLimiter->consume($project);
    }

    private function dispatch(Project $project, string $eventId, string $payload): void
    {
        $projectId = $project->getId() ?? throw new \LogicException('Project is not persisted.');

        $this->bus->dispatch(new ProcessEvent($projectId, $eventId, $payload, $this->clock->now()));
    }
}
