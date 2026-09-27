<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Issue;
use App\Entity\Project;
use App\Query\EventQuery;
use App\Query\IssueCriteria;
use App\Query\IssueQuery;
use App\Repository\IssueRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\UX\Turbo\TurboBundle;

final class IssueController extends AbstractController
{
    use CsrfGuard;

    public function __construct(
        private readonly IssueRepository $issues,
        private readonly EventQuery $events,
    ) {
    }

    #[Route('/projects/{slug}/issues', name: 'app_issues', methods: ['GET'])]
    public function index(Request $request, #[MapEntity(mapping: ['slug' => 'slug'])] Project $project, IssueQuery $query): Response
    {
        $criteria = IssueCriteria::fromRequest($request);

        return $this->render('issue/index.html.twig', [
            'project' => $project,
            'criteria' => $criteria,
            'page' => $query->search($project, $criteria),
            'options' => $query->filterOptions($project),
        ]);
    }

    #[Route('/projects/{slug}/issues/{id}', name: 'app_issue', requirements: ['id' => '\d{1,9}'], methods: ['GET'])]
    public function show(Request $request, #[MapEntity(mapping: ['slug' => 'slug'])] Project $project, int $id): Response
    {
        $issue = $this->find($project, $id);
        $count = $this->events->count($issue);
        $offset = max(0, min($count - 1, $request->query->getInt('event')));

        return $this->render('issue/show.html.twig', [
            'project' => $project,
            'issue' => $issue,
            'event' => $this->events->nth($issue, $offset),
            'offset' => $offset,
            'count' => $count,
            'tags' => $this->events->tagBreakdown($issue),
        ]);
    }

    #[Route('/projects/{slug}/issues/{id}/status', name: 'app_issue_status', requirements: ['id' => '\d{1,9}'], methods: ['POST'])]
    public function status(Request $request, #[MapEntity(mapping: ['slug' => 'slug'])] Project $project, int $id, EntityManagerInterface $entityManager): Response
    {
        $issue = $this->find($project, $id);
        $this->assertCsrf($request, 'issue_status_'.$id);

        match ($request->request->all()['action'] ?? null) {
            'resolve' => $issue->resolve(),
            'ignore' => $issue->ignore(),
            'reopen' => $issue->reopen(),
            default => throw new BadRequestHttpException('Unknown action.'),
        };
        $entityManager->flush();

        if (TurboBundle::STREAM_FORMAT === $request->getPreferredFormat()) {
            $request->setRequestFormat(TurboBundle::STREAM_FORMAT);

            return $this->render('issue/status.stream.html.twig', ['project' => $project, 'issue' => $issue]);
        }

        return $this->redirectToRoute('app_issue', ['slug' => $project->getSlug(), 'id' => $id]);
    }

    private function find(Project $project, int $id): Issue
    {
        $issue = $this->issues->find($id);
        if (null === $issue || $issue->getProject() !== $project) {
            throw $this->createNotFoundException();
        }

        return $issue;
    }
}
