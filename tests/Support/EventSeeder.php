<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Entity\Project;
use App\Ingest\EventId;
use App\Message\ProcessEvent;
use App\Processing\ProcessEventHandler;
use Symfony\Component\DependencyInjection\ContainerInterface;

trait EventSeeder
{
    /**
     * @param array<string, mixed> $payload
     */
    private static function seedEvent(ContainerInterface $container, Project $project, array $payload, ?\DateTimeImmutable $receivedAt = null): void
    {
        $handler = $container->get(ProcessEventHandler::class);
        $handler(new ProcessEvent(
            $project->getId() ?? 0,
            EventId::generate(),
            json_encode($payload, \JSON_THROW_ON_ERROR),
            $receivedAt ?? new \DateTimeImmutable(),
        ));
    }

    /**
     * @param array<string, mixed> $extra
     *
     * @return array<string, mixed>
     */
    private static function exceptionEvent(string $type, string $value, string $function, array $extra = []): array
    {
        return $extra + ['exception' => ['values' => [[
            'type' => $type,
            'value' => $value,
            'stacktrace' => ['frames' => [
                ['filename' => 'vendor/framework/Kernel.php', 'function' => 'handle', 'lineno' => 10, 'in_app' => false],
                ['filename' => 'vendor/framework/Router.php', 'function' => 'dispatch', 'lineno' => 20, 'in_app' => false],
                ['filename' => 'src/Checkout.php', 'function' => $function, 'lineno' => 42, 'in_app' => true, 'context_line' => '$order->pay();', 'pre_context' => ['$order = $repo->find($id);'], 'post_context' => ['return $order;'], 'vars' => ['id' => 7, 'token' => 'secret']],
            ]],
        ]]]];
    }
}
