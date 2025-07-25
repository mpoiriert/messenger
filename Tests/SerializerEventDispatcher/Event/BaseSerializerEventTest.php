<?php

namespace Draw\Component\Messenger\Tests\SerializerEventDispatcher\Event;

use Draw\Component\Messenger\SerializerEventDispatcher\Event\BaseSerializerEvent;
use Draw\Component\Messenger\SerializerEventDispatcher\Event\PostDecodeEvent;
use Draw\Component\Messenger\SerializerEventDispatcher\Event\PostEncodeEvent;
use Draw\Component\Messenger\SerializerEventDispatcher\Event\PreEncodeEvent;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;

/**
 * @internal
 */
class BaseSerializerEventTest extends TestCase
{
    #[DataProvider('provideConstructCases')]
    public function testConstruct(string $class): void
    {
        $object = new $class($envelope = new Envelope((object) []));

        static::assertInstanceOf(
            BaseSerializerEvent::class,
            $object
        );

        static::assertSame(
            $envelope,
            $object->getEnvelope()
        );
    }

    public static function provideConstructCases(): iterable
    {
        yield [PostDecodeEvent::class];
        yield [PostEncodeEvent::class];
        yield [PreEncodeEvent::class];
    }
}
