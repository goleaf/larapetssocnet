<?php

use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response as PsrResponse;
use GuzzleHttp\TransferStats;
use Illuminate\Http\Client\Response;
use Symfony\Component\VarDumper\VarDumper;

it('includes request context in http client response dump output', function (): void {
    $response = new Response(new PsrResponse(200, [], '{"ok":true}'));
    $response->transferStats = new TransferStats(
        new Request('POST', 'https://example.test/api/ping?attempt=1'),
        new PsrResponse(200)
    );

    $dumps = [];
    $previousDumpHandler = VarDumper::setHandler(function (mixed $value) use (&$dumps): void {
        $dumps[] = $value;
    });

    try {
        $response->dump();
    } finally {
        VarDumper::setHandler($previousDumpHandler);
    }

    expect($dumps)->toHaveCount(2);
    expect($dumps[0])->toBe('"POST https://example.test/api/ping?attempt=1" 200');
    expect($dumps[1])->toBeObject();
    expect($dumps[1]->ok)->toBeTrue();
});
