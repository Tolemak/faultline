<?php

declare(strict_types=1);

namespace App\Notification;

use App\Entity\Issue;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class TelegramNotifier implements IssueNotifierInterface
{
    private const string ENDPOINT = 'https://api.telegram.org/bot%s/sendMessage';

    public function __construct(
        private HttpClientInterface $httpClient,
        private UrlGeneratorInterface $urls,
        private LoggerInterface $logger,
        #[Autowire(env: 'TELEGRAM_BOT_TOKEN')]
        private string $token,
        #[Autowire(env: 'TELEGRAM_CHAT_ID')]
        private string $chatId,
    ) {
    }

    public function isEnabled(): bool
    {
        return 1 === preg_match('/^\d+:[A-Za-z0-9_-]+$/', $this->token) && 1 === preg_match('/^-?\d+$|^@\w+$/', $this->chatId);
    }

    public function notify(Issue $issue, IssueChange $change): void
    {
        if (!$this->isEnabled()) {
            return;
        }

        try {
            $this->httpClient->request('POST', \sprintf(self::ENDPOINT, $this->token), [
                'json' => [
                    'chat_id' => $this->chatId,
                    'text' => $this->message($issue, $change),
                    'parse_mode' => 'HTML',
                    'link_preview_options' => ['is_disabled' => true],
                ],
                'timeout' => 5,
            ])->getStatusCode();
        } catch (ExceptionInterface $e) {
            $this->logger->warning('Telegram notification failed: {reason}', ['reason' => $e->getMessage()]);
        }
    }

    private function message(Issue $issue, IssueChange $change): string
    {
        $url = $this->urls->generate('app_issue', [
            'slug' => $issue->getProject()->getSlug(),
            'id' => $issue->getId(),
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        $lines = [
            \sprintf('<b>%s</b> · %s · M%d', IssueChange::New === $change ? 'New issue' : 'Regression', self::escape($issue->getProject()->getName()), $issue->getLevel()->magnitude()),
            self::escape($issue->getTitle()),
        ];
        if (null !== $issue->getCulprit()) {
            $lines[] = '<code>'.self::escape($issue->getCulprit()).'</code>';
        }
        $lines[] = self::escape($url);

        return implode("\n", $lines);
    }

    private static function escape(string $text): string
    {
        return htmlspecialchars($text, \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8');
    }
}
