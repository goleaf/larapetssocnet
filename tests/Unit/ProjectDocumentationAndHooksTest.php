<?php

declare(strict_types=1);

it('documents the project design, architecture, skills, hooks, and controller testing workflow', function (): void {
    $requiredDocs = [
        'design.md' => ['Open Design Warm Editorial', 'Do not add dark/light switching'],
        'architecture.md' => ['Laravel 13', 'Application pages are private by default'],
        'skills.md' => ['larapetssocnet-design-guides', 'larapetssocnet-test-hooks-guides'],
        'hooks.md' => ['scripts/install-git-hooks.sh', 'SKIP_PROJECT_HOOKS=1'],
        'controller-testing.md' => ['70 concrete controllers', 'scripts/controller-test-map.php'],
        'skills/design.md' => ['Warm Editorial', 'Playwright'],
        'skills/hooks.md' => ['.githooks/pre-commit', 'SKIP_PROJECT_HOOKS=1'],
        'skills/controller-testing.md' => ['Success path', 'scripts/controller-test-map.php'],
        'skills/skill-map.md' => ['UI And Design', 'Workflow And Quality'],
    ];

    foreach ($requiredDocs as $path => $needles) {
        $contents = file_get_contents(base_path($path));

        expect($contents)->not->toBeFalse();

        foreach ($needles as $needle) {
            expect($contents)->toContain($needle);
        }
    }
});

it('installs local design and test hook router skills', function (): void {
    $skillPaths = [
        '.agents/skills/larapetssocnet-design-guides/SKILL.md',
        '.agents/skills/larapetssocnet-design-guides/agents/openai.yaml',
        '.agents/skills/larapetssocnet-design-guides/assets/icon.svg',
        '.agents/skills/larapetssocnet-test-hooks-guides/SKILL.md',
        '.agents/skills/larapetssocnet-test-hooks-guides/agents/openai.yaml',
        '.agents/skills/larapetssocnet-test-hooks-guides/assets/icon.svg',
    ];

    foreach ($skillPaths as $path) {
        expect(base_path($path))->toBeFile();
    }

    expect(file_get_contents(base_path('AGENTS.md')))
        ->toContain('larapetssocnet-design-guides')
        ->toContain('larapetssocnet-test-hooks-guides');
});

it('ships executable hooks and a controller test map guard', function (): void {
    $hookPaths = [
        '.githooks/pre-commit',
        '.githooks/pre-push',
        'scripts/install-git-hooks.sh',
        'scripts/controller-test-map.php',
    ];

    foreach ($hookPaths as $path) {
        expect(base_path($path))->toBeFile()
            ->and(is_executable(base_path($path)))->toBeTrue();
    }

    expect(file_get_contents(base_path('.githooks/pre-commit')))
        ->toContain('SKIP_PROJECT_HOOKS')
        ->toContain('controller-test-map.php --changed');

    exec('php '.escapeshellarg(base_path('scripts/controller-test-map.php')).' --all --format=json', $output, $exitCode);

    expect($exitCode)->toBe(0);

    $result = json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR);

    expect($result['controllers'])->toBeGreaterThan(0)
        ->and($result['missing'])->toBe(0);
});
