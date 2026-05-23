<?php

namespace App\Mail\Transport;

use Closure;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Message;
use Symfony\Component\Mime\MessageConverter;

class PhpMailTransport extends AbstractTransport
{
    private Closure $mailer;

    public function __construct(?Closure $mailer = null, ?EventDispatcherInterface $dispatcher = null, ?LoggerInterface $logger = null)
    {
        parent::__construct($dispatcher, $logger);

        $this->mailer = $mailer ?? static fn (string $to, string $subject, string $body, string $headers): bool => mail($to, $subject, $body, $headers);
    }

    public function __toString(): string
    {
        return 'phpmail';
    }

    protected function doSend(SentMessage $message): void
    {
        $originalMessage = $message->getOriginalMessage();

        if (! $originalMessage instanceof Message) {
            throw new TransportException('The PHP mail() transport only supports Symfony MIME messages.');
        }

        $email = MessageConverter::toEmail($originalMessage);
        $email->ensureValidity();

        $headers = $email->getPreparedHeaders();
        foreach (['To', 'Cc', 'Bcc', 'Subject'] as $header) {
            $headers->remove($header);
        }

        [$bodyHeaders, $body] = $this->splitBodyHeaders($email->getBody()->toString());
        $headerLines = rtrim($headers->toString().$bodyHeaders, "\r\n");

        $sent = ($this->mailer)(
            implode(', ', $this->stringifyAddresses($message->getEnvelope()->getRecipients())),
            $email->getSubject() ?? '',
            $body,
            $headerLines,
        );

        if (! $sent) {
            throw new TransportException('The PHP mail() transport failed to hand off the message.');
        }
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function splitBodyHeaders(string $bodyPart): array
    {
        foreach (["\r\n\r\n", "\n\n"] as $separator) {
            $position = strpos($bodyPart, $separator);

            if ($position !== false) {
                return [
                    substr($bodyPart, 0, $position)."\r\n",
                    substr($bodyPart, $position + strlen($separator)),
                ];
            }
        }

        return ['', $bodyPart];
    }
}
