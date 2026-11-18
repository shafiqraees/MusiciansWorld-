<?php

namespace App\Mail\Transport;

use App\Services\MicrosoftGraphMail;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\MessageConverter;
use Throwable;

class MicrosoftGraphTransport extends AbstractTransport
{
    public function __construct(private MicrosoftGraphMail $graph)
    {
        parent::__construct();
    }

    protected function doSend(SentMessage $message): void
    {
        try {
            $this->graph->send(MessageConverter::toEmail($message->getOriginalMessage()));
        } catch (Throwable $exception) {
            throw new TransportException(
                'Microsoft Graph sendMail failed. '.$exception->getMessage(),
                is_int($exception->getCode()) ? $exception->getCode() : 0,
                $exception
            );
        }
    }

    public function __toString(): string
    {
        return 'microsoft-graph';
    }
}
