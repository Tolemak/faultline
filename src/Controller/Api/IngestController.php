<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Ingest\CorsPolicy;
use App\Ingest\IngestException;
use App\Ingest\Ingestor;
use App\Repository\ProjectRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final readonly class IngestController
{
    private const array REQUIREMENTS = ['projectId' => '\d{1,9}', 'endpoint' => 'envelope|store'];

    public function __construct(
        private ProjectRepository $projects,
        private CorsPolicy $cors,
        private Ingestor $ingestor,
    ) {
    }

    #[Route('/api/{projectId}/{endpoint}/', name: 'api_ingest', requirements: self::REQUIREMENTS, methods: ['POST'])]
    public function ingest(Request $request, int $projectId, string $endpoint): JsonResponse
    {
        $project = $this->projects->find($projectId);
        if (null === $project) {
            return new JsonResponse(['detail' => 'Unknown project.'], Response::HTTP_NOT_FOUND);
        }

        try {
            $corsHeaders = $this->cors->responseHeaders($request, $project);
        } catch (IngestException $e) {
            return $this->error($e);
        }

        try {
            $response = new JsonResponse(['id' => $this->ingestor->ingest($project, $request, $endpoint)]);
        } catch (IngestException $e) {
            $response = $this->error($e);
        }

        $response->headers->add($corsHeaders);

        return $response;
    }

    #[Route('/api/{projectId}/{endpoint}/', name: 'api_ingest_preflight', requirements: self::REQUIREMENTS, methods: ['OPTIONS'])]
    public function preflight(Request $request, int $projectId): Response
    {
        $project = $this->projects->find($projectId);
        if (null === $project) {
            return new JsonResponse(['detail' => 'Unknown project.'], Response::HTTP_NOT_FOUND);
        }

        try {
            return new Response(null, Response::HTTP_NO_CONTENT, $this->cors->preflightHeaders($request, $project));
        } catch (IngestException $e) {
            return $this->error($e);
        }
    }

    private function error(IngestException $e): JsonResponse
    {
        return new JsonResponse(['detail' => $e->getMessage()], $e->statusCode, $e->headers);
    }
}
