<?php

namespace Notifier\Bridge\OvhCloud\Tests;

use Notifier\Bridge\OvhCloud\OvhCloudTransport;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Notifier\Exception\LogicException;
use Symfony\Component\Notifier\Exception\TransportException;
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class OvhCloudTransportTest extends TestCase
{
    /** @var string */
    private $appKey;

    /** @var string */
    private $appSecret;

    /** @var string */
    private $consumerKey;

    /** @var string */
    private $serviceName;

    /** @var MockObject|HttpClientInterface */
    private $httpClient;

    /** @var OvhCloudTransport */
    private $transport;

    public function setUp(): void
    {
        $this->appKey = 'appKey';
        $this->appSecret = 'appSecret';
        $this->consumerKey = 'test';
        $this->serviceName = 'test';

        $this->httpClient = $this->createMock(HttpClientInterface::class);
        $this->transport = new OvhCloudTransport($this->appKey, $this->appSecret, $this->consumerKey, $this->serviceName, $this->httpClient);
    }

    public function test_transport(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $this->httpClient
            ->expects($this->exactly(2))
            ->method('request')
            ->willReturn($response);

        $response->expects($this->once())->method('getStatusCode')->willReturn(200);

        $this->transport->send(new SmsMessage('+33612345678', 'Test Message'));
    }

    public function test_to_string(): void
    {
        $this->assertSame('ovhcloud://' . $this->appKey . ':' . $this->appSecret . '@localhost?consumer_key=' . $this->consumerKey . '&service_name=' . $this->serviceName, $this->transport->__toString());
    }

    public function test_supports(): void
    {
        $this->assertTrue($this->transport->supports(new SmsMessage('+33612345678', 'Test Message')));
        $this->assertFalse($this->transport->supports($this->createMock(MessageInterface::class)));
    }

    public function test_send_fail(): void
    {
        $this->expectException(LogicException::class);
        $this->transport->send($this->createMock(MessageInterface::class));
    }

    public function test_transport_fail(): void
    {
        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Unable to send the SMS: Invalid sender.');
        $response = $this->createMock(ResponseInterface::class);
        $this->httpClient
            ->expects($this->exactly(2))
            ->method('request')
            ->willReturn($response);

        $response->expects($this->once())->method('getStatusCode')->willReturn(400);
        $response->expects($this->once())->method('toArray')->willReturn([
            'message' => 'Invalid sender',
            'status_code' => 400
        ]);

        $this->transport->send(new SmsMessage('+33612345678', 'Test Message'));
    }
}
