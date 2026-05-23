<?php

declare(strict_types=1);

it('deploys with one ftp archive instead of mirror sync state', function (): void {
    $workflow = (string) file_get_contents(base_path('.github/workflows/deploy-ftp.yml'));

    expect($workflow)
        ->toContain('sudo apt-get update && sudo apt-get install --yes --no-install-recommends lftp zip')
        ->toContain('scripts/deploy-ftp-archive.sh')
        ->not->toContain('SamKirkland/FTP-Deploy-Action')
        ->not->toContain('state-name: .ftp-deploy-sync-state.json')
        ->not->toContain('FTP_PARALLEL');
});

it('uploads a single archive and runs a token protected server cleanup', function (): void {
    $script = (string) file_get_contents(base_path('scripts/deploy-ftp-archive.sh'));
    $deployer = (string) file_get_contents(base_path('deploy/shared-hosting/ftp-archive-deployer.php'));

    expect($script)
        ->toContain('ARCHIVE_RELATIVE_PATH="laravel/storage/app/private/__ftp_deploy_package.zip"')
        ->toContain('DEPLOY_TOKEN="$(openssl rand -hex 32)"')
        ->toContain('zip -qr "$ARCHIVE_PATH" .')
        ->toContain('put -O $(lftp_quote "$archive_remote_full_dir")')
        ->toContain('set ftp:use-mdtm no')
        ->toContain('set ftp:ssl-allow no')
        ->toContain('mkdir -f -p')
        ->toContain('FTP_ALLOW_ROOT_DEPLOY')
        ->toContain('curl --fail-with-body --show-error --silent')
        ->toContain('X-Deploy-Token: $DEPLOY_TOKEN');

    expect($deployer)
        ->toContain('hash_equals($tokenHash, hash(\'sha256\', $submittedToken))')
        ->toContain('deploy_clean_directory($targetRoot, $targetRoot, $archiveRelativePath, $preserveSqlite)')
        ->toContain('deploy_extract_archive($archivePath, $targetRoot)')
        ->toContain('laravel/storage')
        ->toContain('storage')
        ->toContain('deploy_is_sqlite_database');
});
