<?php

namespace Draw\Component\Messenger\Tests\MessageHandler;

use Draw\Component\Messenger\Message\RedirectToRouteMessageInterface;
use Draw\Component\Messenger\MessageHandler\RedirectToRouteMessageHandler;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class RedirectToRouteMessageHandlerTest extends TestCase
{
    private RedirectToRouteMessageHandler $service;

    private UrlGeneratorInterface $urlGenerator;

    public function setUp(): void
    {
        $this->service = new RedirectToRouteMessageHandler(
            $this->urlGenerator = $this->createMock(UrlGeneratorInterface::class)
        );
    }

    public function testConstruct(): void
    {
        $this->assertInstanceOf(
            MessageHandlerInterface::class,
            $this->service
        );
    }

    public function testInvoke(): void
    {
        $message = $this->createMock(RedirectToRouteMessageInterface::class);

        $message
            ->expects($this->once())
            ->method('getRedirectResponse')
            ->with($this->urlGenerator)
            ->willReturn($response = new RedirectResponse('/'));

        $this->assertSame(
            $response,
            $this->service->__invoke($message)
        );
    }
}