<?php

declare(strict_types=1);

namespace App\Tests\Unit\Ingest;

use App\Ingest\Envelope\EnvelopeParser;
use App\Ingest\IngestException;
use App\Ingest\JsonDecoder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class EnvelopeParserTest extends TestCase
{
    private EnvelopeParser $parser;

    protected function setUp(): void
    {
        $this->parser = new EnvelopeParser(new JsonDecoder(16), 3);
    }

    public function testParsesItemsWithAndWithoutLength(): void
    {
        $payload = '{"message":"line\\nbreak"}';
        $body = "{\"event_id\":\"9ec79c33ec9942ab8353589fcb2e04dc\",\"dsn\":\"https://key@example.com/1\"}\n"
            .'{"type":"event","length":'.\strlen($payload)."}\n".$payload."\n"
            ."{\"type\":\"session\"}\n{\"sid\":\"x\"}\n\n";

        $envelope = $this->parser->parse($body);

        self::assertSame('9ec79c33ec9942ab8353589fcb2e04dc', $envelope->header('event_id'));
        self::assertNull($envelope->header('missing'));
        self::assertCount(2, $envelope->items);
        self::assertSame($payload, $envelope->items[0]->payload);
        self::assertSame('session', $envelope->items[1]->type);
        self::assertSame('{"sid":"x"}', $envelope->items[1]->payload);
        self::assertCount(1, $envelope->eventItems());
    }

    public function testParsesLastItemWithoutTrailingNewline(): void
    {
        $envelope = $this->parser->parse("{}\n{\"type\":\"event\",\"length\":2}\n{}");

        self::assertSame('{}', $envelope->eventItems()[0]->payload);
    }

    public function testParsesEnvelopeWithHeaderOnly(): void
    {
        self::assertSame([], $this->parser->parse('{"event_id":"x"}')->items);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function invalidEnvelopes(): iterable
    {
        yield 'empty' => ['', 'Envelope header is missing.'];
        yield 'missing type' => ["{}\n{\"length\":2}\n{}", 'Envelope item type is missing.'];
        yield 'bad length' => ["{}\n{\"type\":\"event\",\"length\":-1}\n{}", 'Envelope item length is invalid.'];
        yield 'string length' => ["{}\n{\"type\":\"event\",\"length\":\"2\"}\n{}", 'Envelope item length is invalid.'];
        yield 'truncated' => ["{}\n{\"type\":\"event\",\"length\":50}\n{}", 'Envelope item is truncated.'];
        yield 'too many items' => ["{}\n".str_repeat("{\"type\":\"session\"}\n{}\n", 4), 'Too many envelope items.'];
        yield 'bad header json' => ["{nope\n", 'Invalid JSON'];
    }

    #[DataProvider('invalidEnvelopes')]
    public function testRejectsInvalidEnvelopes(string $body, string $message): void
    {
        $this->expectException(IngestException::class);
        $this->expectExceptionMessage($message);

        $this->parser->parse($body);
    }
}
