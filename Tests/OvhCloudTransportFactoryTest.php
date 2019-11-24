<?php

namespace Notifier\Bridge\OvhCloud\Tests;

use Notifier\Bridge\OvhCloud\OvhCloudTransport;
use Notifier\Bridge\OvhCloud\OvhCloudTransportFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Notifier\Exception\UnsupportedSchemeException;
use Symfony\Component\Notifier\Transport\Dsn;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class OvhCloudTransportFactoryTest extends TestCase
{
    /** @var MockObject|HttpClientInterface */
    private $httpClient;

    /** @var OvhCloudTransportFactory */
    private $transportFactory;

    public function setUp(): void
    {
        $this->httpClient = $this->createMock(HttpClientInterface::class);
        $this->transportFactory = new OvhCloudTransportFactory();
    }

    public function test_create_from_string(): void
    {
        $dsn = 'ovhcloud://appKey:appSecret@localhost?consumer_key=test&service_name=test';
        $transport = $this->transportFactory->create(Dsn::fromString($dsn));
        $this->assertInstanceOf(OvhCloudTransport::class, $transport);
        $this->assertSame($dsn, $transport->__toString());
    }

    public function test_create_unsupported(): void
    {
        $this->expectException(UnsupportedSchemeException::class);
        $dsn = 'test://appKey:appSecret@localhost?consumer_key=test&service_name=test';
        $this->transportFactory->create(Dsn::fromString($dsn));
    }

    public function test_supports(): void
    {
        $this->assertTrue($this->transportFactory->supports(Dsn::fromString('ovhcloud://appKey:appSecret@localhost?consumer_key=test&service_name=test')));
        $this->assertFalse($this->transportFactory->supports(Dsn::fromString('test://appKey:appSecret@localhost?consumer_key=test&service_name=test')));
    }
}
