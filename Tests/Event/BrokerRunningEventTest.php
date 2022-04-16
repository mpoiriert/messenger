<?php

namespace Draw\Component\Messenger\Tests\Event;

use Draw\Component\Messenger\Broker;
use Draw\Component\Messenger\Event\BrokerRunningEvent;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @covers \Draw\Component\Messenger\Event\BrokerRunningEvent
 */
class BrokerRunningEventTest extends TestCase
{
    private BrokerRunningEvent $event;

    private Broker $broker;

    public function setUp(): void
    {
        $this->event = new BrokerRunningEvent(
            $this->broker = $this->createMock(Broker::class)
        );
    }

    public function testConstruct(): void
    {
        $this->assertInstanceOf(
            Event::class,
            $this->event
        );
    }

    public function testGetBroker(): void
    {
        $this->assertSame(
            $this->broker,
            $this->event->getBroker()
        );
    }
}
