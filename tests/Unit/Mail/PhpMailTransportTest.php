<?php

use App\Mail\Transport\PhpMailTransport;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

it('hands Symfony email messages to PHP mail without shelling out', function (): void {
    $deliveries = [];
    $transport = new PhpMailTransport(function (string $to, string $subject, string $body, string $headers) use (&$deliveries): bool {
        $deliveries[] = compact('to', 'subject', 'body', 'headers');

        return true;
    });

    $transport->send((new Email)
        ->from(new Address('robot@prus.dev', 'PetSocial'))
        ->to('recipient@example.com')
        ->subject('Verify your email')
        ->text('Open the verification link.')
        ->html('<p>Open the <strong>verification</strong> link.</p>'));

    expect($deliveries)->toHaveCount(1)
        ->and($deliveries[0]['to'])->toContain('recipient@example.com')
        ->and($deliveries[0]['subject'])->toBe('Verify your email')
        ->and($deliveries[0]['headers'])->toContain('From: PetSocial <robot@prus.dev>')
        ->and($deliveries[0]['headers'])->toContain('Content-Type: multipart/alternative')
        ->and($deliveries[0]['headers'])->not->toContain('Subject:')
        ->and($deliveries[0]['headers'])->not->toContain('To:')
        ->and($deliveries[0]['body'])->toContain('Open the verification link.')
        ->and($deliveries[0]['body'])->toContain('<strong>verification</strong>');
});

it('throws a transport exception when PHP mail rejects a message', function (): void {
    $transport = new PhpMailTransport(fn (): bool => false);

    expect(fn (): mixed => $transport->send((new Email)
        ->from('robot@prus.dev')
        ->to('recipient@example.com')
        ->subject('Verify your email')
        ->text('Open the verification link.')))
        ->toThrow(TransportException::class, 'PHP mail() transport failed');
});
