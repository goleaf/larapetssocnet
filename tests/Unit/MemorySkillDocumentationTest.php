<?php

declare(strict_types=1);

it('installs the project memory skill and documents its workflow', function (): void {
    $skillPath = base_path('.agents/skills/larapetssocnet-memory-guides/SKILL.md');
    $openAiPath = base_path('.agents/skills/larapetssocnet-memory-guides/agents/openai.yaml');
    $iconPath = base_path('.agents/skills/larapetssocnet-memory-guides/assets/icon.svg');
    $guidePath = base_path('skills/memory.md');

    expect($skillPath)->toBeFile()
        ->and($openAiPath)->toBeFile()
        ->and($iconPath)->toBeFile()
        ->and($guidePath)->toBeFile();

    expect(file_get_contents($skillPath))
        ->toContain('name: larapetssocnet-memory-guides')
        ->toContain('../../../skills/memory.md');

    expect(file_get_contents($openAiPath))
        ->toContain('Project Memory Guides')
        ->toContain('$larapetssocnet-memory-guides');

    expect(file_get_contents($guidePath))
        ->toContain('/Users/andrejprus/.codex/memories/MEMORY.md')
        ->toContain('https://github.com/mem0ai/mem0/tree/main/mem0-plugin')
        ->toContain('/Users/andrejprus/.codex/memories/extensions/ad_hoc/notes/')
        ->toContain('Do not reintroduce dark/light theme switching');
});

it('registers the memory skill in the root agent guidance', function (): void {
    expect(file_get_contents(base_path('AGENTS.md')))
        ->toContain('larapetssocnet-memory-guides')
        ->toContain('skills/memory.md');
});
