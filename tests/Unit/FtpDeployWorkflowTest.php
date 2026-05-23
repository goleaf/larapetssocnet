<?php

declare(strict_types=1);

it('deploys with the project lftp mirror script instead of the sync-state action', function (): void {
    $workflow = (string) file_get_contents(base_path('.github/workflows/deploy-ftp.yml'));

    expect($workflow)
        ->toContain('sudo apt-get update && sudo apt-get install --yes --no-install-recommends lftp')
        ->toContain('scripts/deploy-ftp-lftp.sh')
        ->toContain("FTP_PARALLEL: \${{ vars.FTP_PARALLEL || '4' }}")
        ->not->toContain('SamKirkland/FTP-Deploy-Action')
        ->not->toContain('state-name: .ftp-deploy-sync-state.json');
});

it('cleans stale ftp files before a parallel mirror upload while preserving runtime data', function (): void {
    $script = (string) file_get_contents(base_path('scripts/deploy-ftp-lftp.sh'));

    expect($script)
        ->toContain('FTP_PARALLEL="${FTP_PARALLEL:-4}"')
        ->toContain('mirror --reverse --delete --delete-first --no-perms --parallel=%s --verbose=1')
        ->not->toContain('--scan-all-first')
        ->toContain('mkdir -f -p')
        ->toContain('set mirror:parallel-directories yes')
        ->toContain('laravel/database/*.sqlite*')
        ->toContain('laravel/storage/app/public/**')
        ->toContain('storage/**')
        ->toContain('FTP_ALLOW_ROOT_DEPLOY')
        ->toContain('LFTP_PASSWORD="$FTP_PASSWORD" lftp --norc -f "$commands_file"');
});
