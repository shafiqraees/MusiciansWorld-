<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Throwable;

class MicrosoftGraphMail
{
    public function configured(): bool
    {
        return filled($this->tenantId())
            && filled($this->clientId())
            && filled($this->clientSecret())
            && filled($this->mailbox());
    }

    public function send(Email $email): void
    {
        if (! $this->configured()) {
            throw new RuntimeException('Microsoft Graph mail is not configured. Set the booking mailbox client secret before sending.');
        }

        $html = $email->getHtmlBody();
        $text = $email->getTextBody();
        $body = is_string($html) && $html !== '' ? $html : (is_string($text) ? $text : '');
        if ($body === '') {
            throw new RuntimeException('Microsoft Graph cannot send an empty email.');
        }

        $message = [
            'subject' => (string) $email->getSubject(),
            'body' => [
                'contentType' => is_string($html) && $html !== '' ? 'HTML' : 'Text',
                'content' => $body,
            ],
            'from' => [
                'emailAddress' => [
                    'address' => $this->mailbox(),
                    'name' => (string) config('mail.from.name'),
                ],
            ],
            'toRecipients' => $this->recipients($email->getTo()),
        ];
        $replyTo = $this->recipients($email->getReplyTo());
        $attachments = $this->attachments($email);
        if ($replyTo !== []) {
            $message['replyTo'] = $replyTo;
        }
        if ($attachments !== []) {
            $message['attachments'] = $attachments;
        }

        $cc = $this->recipients($email->getCc());
        $bcc = $this->recipients($email->getBcc());
        if ($cc !== []) {
            $message['ccRecipients'] = $cc;
        }
        if ($bcc !== []) {
            $message['bccRecipients'] = $bcc;
        }

        if ($message['toRecipients'] === []) {
            throw new RuntimeException('Microsoft Graph cannot send an email without a recipient.');
        }

        Http::withToken($this->accessToken())
            ->acceptJson()
            ->connectTimeout(5)
            ->timeout(20)
            ->post($this->sendMailUrl(), [
                'message' => $message,
                'saveToSentItems' => true,
            ])
            ->throw();
    }

    public function sendMailUrl(): string
    {
        return 'https://graph.microsoft.com/v1.0/users/'.rawurlencode($this->mailbox()).'/sendMail';
    }

    private function accessToken(): string
    {
        $tenantId = $this->tenantId();
        $clientId = $this->clientId();
        $cacheKey = 'microsoft-graph-mail-token:'.hash('sha256', $tenantId.'|'.$clientId);

        return Cache::remember($cacheKey, now()->addMinutes(50), function () use ($tenantId, $clientId): string {
            $response = Http::asForm()
                ->connectTimeout(5)
                ->timeout(15)
                ->retry(2, 250, function (Throwable $exception): bool {
                    return $exception instanceof ConnectionException
                        || ($exception instanceof RequestException
                            && ($exception->response->serverError() || $exception->response->status() === 429));
                })
                ->post('https://login.microsoftonline.com/'.$tenantId.'/oauth2/v2.0/token', [
                    'client_id' => $clientId,
                    'client_secret' => $this->clientSecret(),
                    'scope' => 'https://graph.microsoft.com/.default',
                    'grant_type' => 'client_credentials',
                ])
                ->throw();

            $token = (string) $response->json('access_token');
            if ($token === '') {
                throw new RuntimeException('Microsoft Graph did not return an access token.');
            }

            return $token;
        });
    }

    /**
     * @param  array<int, Address>  $addresses
     * @return list<array{emailAddress: array{address: string, name?: string}}>
     */
    private function recipients(array $addresses): array
    {
        return array_values(array_map(function (Address $address): array {
            $email = ['address' => $address->getAddress()];
            if ($address->getName() !== '') {
                $email['name'] = $address->getName();
            }

            return ['emailAddress' => $email];
        }, $addresses));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function attachments(Email $email): array
    {
        $attachments = [];
        foreach ($email->getAttachments() as $attachment) {
            $filename = $attachment->getFilename() ?: 'attachment';
            $contentType = trim(explode(';', $attachment->getContentType(), 2)[0]) ?: 'application/octet-stream';
            $item = [
                '@odata.type' => '#microsoft.graph.fileAttachment',
                'name' => $filename,
                'contentType' => $contentType,
                'contentBytes' => base64_encode($attachment->getBody()),
            ];
            if ($attachment->getDisposition() === 'inline' && $attachment->hasContentId()) {
                $item['isInline'] = true;
                $item['contentId'] = trim($attachment->getContentId(), '<>');
            }
            $attachments[] = $item;
        }

        return $attachments;
    }

    private function tenantId(): string
    {
        return (string) config('services.microsoft.mail.tenant_id');
    }

    private function clientId(): string
    {
        return (string) config('services.microsoft.mail.client_id');
    }

    private function clientSecret(): string
    {
        return (string) config('services.microsoft.mail.client_secret');
    }

    private function mailbox(): string
    {
        return (string) config('services.microsoft.mail.mailbox');
    }
}
