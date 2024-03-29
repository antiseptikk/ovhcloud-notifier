<?php

namespace Notifier\Bridge\OvhCloud;

use Symfony\Component\Notifier\Exception\LogicException;
use Symfony\Component\Notifier\Exception\TransportException;
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Transport\AbstractTransport;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Thomas Ferney <thomas.ferney@gmail.com>
 *
 * @experimental in 5.0
 */
final class OvhCloudTransport extends AbstractTransport
{
    private $endpoints = [
        'ovh-eu' => 'https://eu.api.ovh.com/1.0',
        'ovh-ca' => 'https://ca.api.ovh.com/1.0',
        'ovh-us' => 'https://api.us.ovhcloud.com/1.0',
        'kimsufi-eu' => 'https://eu.api.kimsufi.com/1.0',
        'kimsufi-ca' => 'https://ca.api.kimsufi.com/1.0',
        'soyoustart-eu' => 'https://eu.api.soyoustart.com/1.0',
        'soyoustart-ca' => 'https://ca.api.soyoustart.com/1.0',
        'runabove-ca' => 'https://api.runabove.com/1.0',
    ];

    private $applicationKey;
    private $applicationSecret;
    private $consumerKey;
    private $serviceName;
    private $timeDelta;

    public function __construct(string $applicationKey, string $applicationSecret, string $consumerKey, string $serviceName, HttpClientInterface $client = null, EventDispatcherInterface $dispatcher = null)
    {
        $this->applicationKey = $applicationKey;
        $this->applicationSecret = $applicationSecret;
        $this->consumerKey = $consumerKey;
        $this->serviceName = $serviceName;

        parent::__construct($client, $dispatcher);
    }

    public function setHostByEndpoint(?string $endpoint): self
    {
        $this->host = $this->endpoints[$endpoint] ?? self::HOST;

        return $this;
    }

    public function __toString(): string
    {
        return sprintf('ovhcloud://%s:%s@%s?consumer_key=%s&service_name=%s', $this->applicationKey, $this->applicationSecret, $this->getEndpoint(), $this->consumerKey, $this->serviceName);
    }

    public function supports(MessageInterface $message): bool
    {
        return $message instanceof SmsMessage;
    }

    protected function doSend(MessageInterface $message): void
    {
        if (!$message instanceof SmsMessage) {
            throw new LogicException(sprintf('The "%s" transport only supports instances of "%s" (instance of "%s" given).', __CLASS__, SmsMessage::class, \get_class($message)));
        }

        $endpoint = sprintf('%s/sms/%s/jobs', $this->getEndpoint(), $this->serviceName);

        $content = [
            'charset' => 'UTF-8',
            'class' => 'flash',
            'coding' => '8bit',
            'message' => $message->getSubject(),
            'receivers' => [$message->getPhone()],
            'noStopClause' => false,
            'priority' => 'medium',
            'senderForResponse' => true,
        ];

        $headers['X-Ovh-Application'] = $this->applicationKey;

        if (!isset($this->timeDelta)) {
            $this->calculateTimeDelta();
        }

        $now = time() + $this->timeDelta;
        $headers['X-Ovh-Timestamp'] = $now;

        if (isset($this->consumerKey)) {
            $toSign = $this->applicationSecret.'+'.$this->consumerKey.'+POST+'.$endpoint.'+'.json_encode($content, JSON_UNESCAPED_SLASHES).'+'.$now;
            $headers['X-Ovh-Consumer'] = $this->consumerKey;
            $headers['X-Ovh-Signature'] = '$1$'.sha1($toSign);
        }

        $response = $this->client->request('POST', $endpoint, [
            'headers' => $headers,
            'json' => $content,
        ]);

        if (200 !== $response->getStatusCode()) {
            $error = $response->toArray(false);

            throw new TransportException(sprintf('Unable to send the SMS: %s.', $error['message']), $response);
        }
    }

    /**
     * Calculate time delta between local machine and API's server.
     *
     * @throws \Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     */
    private function calculateTimeDelta(): int
    {
        if (!isset($this->timeDelta)) {
            $endpoint = sprintf('%s/auth/time', $this->getEndpoint());

            $response = $this->client->request(
                'GET',
                $endpoint
            );

            $serverTimestamp = (int) (string) $response->getContent();
            $this->timeDelta = $serverTimestamp - (int) time();
        }

        return $this->timeDelta;
    }
}
