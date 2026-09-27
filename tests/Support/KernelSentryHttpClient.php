<?php

declare(strict_types=1);

namespace App\Tests\Support;

use Sentry\HttpClient\HttpClientInterface;
use Sentry\HttpClient\Request;
use Sentry\HttpClient\Response;
use Sentry\Options;
use Sentry\Util\Http;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

final class KernelSentryHttpClient implements HttpClientInterface
{
    public function __construct(private readonly KernelBrowser $browser)
    {
    }

    public function sendRequest(Request $request, Options $options): Response
    {
        $dsn = $options->getDsn() ?? throw new \LogicException('The Sentry client has no DSN.');
        $body = (string) $request->getStringBody();

        $server = [];
        foreach (Http::getRequestHeaders($dsn, 'sentry.php', '4.x') as $header) {
            [$name, $value] = explode(': ', $header, 2);
            $server['HTTP_'.strtoupper(str_replace('-', '_', $name))] = $value;
        }

        if ($options->isHttpCompressionEnabled()) {
            $body = (string) gzcompress($body, -1, \ZLIB_ENCODING_GZIP);
            $server['HTTP_CONTENT_ENCODING'] = 'gzip';
        }

        $this->browser->request('POST', $dsn->getEnvelopeApiEndpointUrl(), server: $server, content: $body);
        $response = $this->browser->getResponse();

        return new Response($response->getStatusCode(), [], $response->isSuccessful() ? '' : (string) $response->getContent());
    }
}
