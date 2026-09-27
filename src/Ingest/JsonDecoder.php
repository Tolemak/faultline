<?php

declare(strict_types=1);

namespace App\Ingest;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class JsonDecoder
{
    /**
     * @param positive-int $maxDepth
     */
    public function __construct(
        #[Autowire(param: 'faultline.ingest.json_max_depth')]
        private int $maxDepth,
    ) {
    }

    /**
     * @return array<mixed>
     */
    public function decodeObject(string $json): array
    {
        try {
            $data = json_decode($json, true, $this->maxDepth, \JSON_THROW_ON_ERROR | \JSON_BIGINT_AS_STRING);
        } catch (\JsonException $e) {
            throw IngestException::invalidPayload('Invalid JSON: '.$e->getMessage().'.');
        }

        if (!\is_array($data) || ([] !== $data && array_is_list($data))) {
            throw IngestException::invalidPayload('JSON object expected.');
        }

        return $data;
    }
}
