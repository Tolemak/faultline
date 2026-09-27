<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Project;
use App\Project\Dsn;
use App\Project\OriginList;
use App\Project\ProjectManager;
use App\Query\ProjectOverviewQuery;
use App\Repository\ProjectRepository;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ProjectController extends AbstractController
{
    use CsrfGuard;

    public function __construct(
        private readonly ProjectManager $manager,
        private readonly ProjectRepository $projects,
        private readonly Dsn $dsn,
    ) {
    }

    #[Route('/', name: 'app_projects', methods: ['GET'])]
    public function index(ProjectOverviewQuery $overview): Response
    {
        return $this->render('project/index.html.twig', ['overviews' => $overview->all()]);
    }

    #[Route('/projects', name: 'app_project_create', methods: ['POST'])]
    public function create(Request $request): Response
    {
        $this->assertCsrf($request, 'project_create');

        try {
            $project = $this->manager->create(self::field($request, 'name'));
        } catch (\InvalidArgumentException) {
            $this->addFlash('error', 'project.create.invalid_name');

            return $this->redirectToRoute('app_projects');
        }

        $this->addFlash('success', 'project.create.done');

        return $this->redirectToRoute('app_project_settings', ['slug' => $project->getSlug()]);
    }

    #[Route('/projects/{slug}/settings', name: 'app_project_settings', methods: ['GET', 'POST'])]
    public function settings(Request $request, #[MapEntity(mapping: ['slug' => 'slug'])] Project $project): Response
    {
        $values = [
            'name' => $project->getName(),
            'origins' => implode("\n", $project->getAllowedOrigins()),
            'retention' => (string) $project->getRetentionDays(),
        ];
        $errors = [];

        if ($request->isMethod('POST')) {
            $this->assertCsrf($request, 'project_settings');
            $values = ['name' => self::field($request, 'name'), 'origins' => self::field($request, 'origins'), 'retention' => self::field($request, 'retention')];
            $errors = $this->apply($project, $values);

            if ([] === $errors) {
                $this->projects->save($project);
                $this->addFlash('success', 'settings.saved');

                return $this->redirectToRoute('app_project_settings', ['slug' => $project->getSlug()]);
            }
        }

        return $this->render('project/settings.html.twig', [
            'project' => $project,
            'dsn' => $this->dsn->for($project),
            'values' => $values,
            'errors' => $errors,
        ], new Response(status: [] === $errors ? Response::HTTP_OK : Response::HTTP_UNPROCESSABLE_ENTITY));
    }

    #[Route('/projects/{slug}/rotate-key', name: 'app_project_rotate_key', methods: ['POST'])]
    public function rotateKey(Request $request, #[MapEntity(mapping: ['slug' => 'slug'])] Project $project): Response
    {
        $this->assertCsrf($request, 'project_rotate_key');
        $this->manager->rotateKey($project);
        $this->addFlash('success', 'settings.key_rotated');

        return $this->redirectToRoute('app_project_settings', ['slug' => $project->getSlug()]);
    }

    /**
     * @param array{name: string, origins: string, retention: string} $values
     *
     * @return array<string, string>
     */
    private function apply(Project $project, array $values): array
    {
        $errors = [];

        try {
            $name = ProjectManager::validName($values['name']);
        } catch (\InvalidArgumentException) {
            $errors['name'] = 'settings.error.name';
        }

        try {
            $origins = OriginList::fromText($values['origins']);
        } catch (\InvalidArgumentException) {
            $errors['origins'] = 'settings.error.origins';
        }

        $retention = ctype_digit($values['retention']) ? (int) $values['retention'] : 0;
        try {
            ProjectManager::assertRetention($retention);
        } catch (\InvalidArgumentException) {
            $errors['retention'] = 'settings.error.retention';
        }

        if ([] === $errors && isset($name, $origins)) {
            $project->rename($name);
            $project->setAllowedOrigins($origins);
            $project->setRetentionDays($retention);
        }

        return $errors;
    }

    private static function field(Request $request, string $name): string
    {
        $value = $request->request->all()[$name] ?? '';

        return \is_string($value) ? mb_substr($value, 0, 4000) : '';
    }
}
