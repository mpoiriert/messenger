<?php

namespace Draw\Component\Messenger\Tests\ManualTrigger\Action;

use Draw\Component\Messenger\Expirable\Stamp\ExpirationStamp;
use Draw\Component\Messenger\ManualTrigger\Action\ClickMessageAction;
use Draw\Component\Messenger\ManualTrigger\Event\MessageLinkErrorEvent;
use Draw\Component\Messenger\Searchable\EnvelopeFinder;
use Draw\Component\Messenger\Searchable\Stamp\FoundFromTransportStamp;
use Draw\Component\Messenger\Searchable\TransportRepository;
use Draw\Contracts\Messenger\Exception\MessageNotFoundException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Stamp\SentToFailureTransportStamp;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use Symfony\Component\Messenger\Transport\TransportInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @covers \Draw\Component\Messenger\ManualTrigger\Action\ClickMessageAction
 */
class ClickMessageActionTest extends TestCase
{
    private ClickMessageAction $action;

    private MessageBusInterface $messageBus;

    private EnvelopeFinder $envelopeFinder;

    private EventDispatcherInterface $eventDispatcher;

    private TranslatorInterface $translator;

    private TransportRepository $transportRepository;

    private Request $request;

    protected function setUp(): void
    {
        $this->request = new Request();
        $this->request->setSession(
            new Session(
                new MockArraySessionStorage(),
            )
        );

        $this->action = new ClickMessageAction(
            $this->messageBus = $this->createMock(MessageBusInterface::class),
            $this->envelopeFinder = $this->createMock(EnvelopeFinder::class),
            $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class),
            $this->translator = $this->createMock(TranslatorInterface::class),
            $this->transportRepository = $this->createMock(TransportRepository::class)
        );
    }

    public function testConstants(): void
    {
        static::assertSame(
            'dMUuid',
            $this->action::MESSAGE_ID_PARAMETER_NAME
        );
    }

    public function provideTestClickEnvelopeError(): iterable
    {
        yield 'not-found' => [
            null,
            MessageNotFoundException::class,
            'link.invalid',
        ];

        yield 'error-queue' => [
            new Envelope((object) [], [new SentToFailureTransportStamp(uniqid())]),
            MessageNotFoundException::class,
            'link.invalid',
        ];

        yield 'expired' => [
            new Envelope((object) [], [new ExpirationStamp(new \DateTimeImmutable('- 1 second'))]),
            MessageNotFoundException::class,
            'link.invalid',
        ];
    }

    /**
     * @dataProvider provideTestClickEnvelopeError
     */
    public function testClickEnvelopeError(
        ?Envelope $returnedEnveloped,
        string $exceptionClass,
        ?string $translatedMessage
    ): void {
        $this->messageBus
            ->expects(static::never())
            ->method('dispatch');

        $this->envelopeFinder
            ->expects(static::once())
            ->method('findById')
            ->with($messageId = uniqid('message-Id'))
            ->willThrowException(new MessageNotFoundException($messageId));

        $this->eventDispatcher
            ->expects(static::once())
            ->method('dispatch')
            ->with(
                static::callback(
                    function (MessageLinkErrorEvent $event) use ($messageId, $exceptionClass) {
                        $this->assertSame(
                            $this->request,
                            $event->getRequest()
                        );

                        $this->assertSame(
                            $messageId,
                            $event->getMessageId()
                        );

                        $error = $event->getError();

                        $this->assertInstanceOf(
                            $exceptionClass,
                            $error
                        );

                        return true;
                    }
                )
            )
            ->willReturnArgument(0);

        if ($translatedMessage) {
            $this->translator
                ->expects(static::once())
                ->method('trans')
                ->with($translatedMessage, [], 'DrawMessenger')
                ->willReturn($message = uniqid('translation-'));
        } else {
            $this->request->setSession($this->createMock(SessionInterface::class));
            $this->translator
                ->expects(static::never())
                ->method('trans');
        }

        $response = \call_user_func($this->action, $messageId, $this->request);

        if ($translatedMessage) {
            static::assertSame(
                [
                    'error' => [$message],
                ],
                $this->request->getSession()->getFlashBag()->all()
            );
        }

        static::assertInstanceOf(
            RedirectResponse::class,
            $response
        );

        static::assertSame(
            '/',
            $response->getTargetUrl()
        );
    }

    public function testClick(): void
    {
        $transportName = uniqid('transport-');

        $this->envelopeFinder
            ->expects(static::once())
            ->method('findById')
            ->with($messageId = uniqid('message-Id'))
            ->willReturn(new Envelope((object) [], [new FoundFromTransportStamp($transportName)]));

        $this->messageBus
            ->expects(static::once())
            ->method('dispatch')
            ->with(
                static::callback(function (Envelope $envelope) use ($transportName) {
                    $this->assertSame(
                        $transportName,
                        $envelope->last(ReceivedStamp::class)->getTransportName()
                    );

                    return true;
                })
            )->willReturn(
                $envelope = new Envelope(
                    (object) [],
                    [new TransportMessageIdStamp($messageId), new HandledStamp(null, uniqid('handler-'))]
                )
            );

        $this->translator
            ->expects(static::once())
            ->method('trans')
            ->with('link.processed', [], 'DrawMessenger')
            ->willReturn($message = uniqid('translation-'));

        $this->transportRepository
            ->expects(static::once())
            ->method('get')
            ->with($transportName)
            ->willReturn($transport = $this->createMock(TransportInterface::class));

        $transport
            ->expects(static::once())
            ->method('ack')
            ->with($envelope);

        $response = \call_user_func($this->action, $messageId, $this->request);

        static::assertSame(
            [
                'success' => [$message],
            ],
            $this->request->getSession()->getFlashBag()->all()
        );

        static::assertInstanceOf(
            RedirectResponse::class,
            $response
        );

        static::assertSame(
            '/',
            $response->getTargetUrl()
        );
    }

    public function testClickWithResponse(): void
    {
        $transportName = uniqid('transport-');

        $this->envelopeFinder
            ->expects(static::once())
            ->method('findById')
            ->with($messageId = uniqid('message-Id'))
            ->willReturn(new Envelope((object) [], [new FoundFromTransportStamp($transportName)]));

        $this->messageBus
            ->expects(static::once())
            ->method('dispatch')
            ->willReturn(
                $envelope = new Envelope(
                    (object) [],
                    [
                        new TransportMessageIdStamp($messageId),
                        new HandledStamp($response = new Response(), uniqid('handler-')),
                    ]
                )
            );

        $this->translator
            ->expects(static::never())
            ->method('trans');

        $this->transportRepository
            ->expects(static::once())
            ->method('get')
            ->with($transportName)
            ->willReturn($transport = $this->createMock(TransportInterface::class));

        $transport
            ->expects(static::once())
            ->method('ack')
            ->with($envelope);

        static::assertSame(
            $response,
            \call_user_func($this->action, $messageId, $this->request)
        );
    }

    public function testClickInvalidHandler(): void
    {
        $transportName = uniqid('transport-');

        $this->envelopeFinder
            ->expects(static::once())
            ->method('findById')
            ->with($messageId = uniqid('message-Id'))
            ->willReturn(
                new Envelope(
                    (object) [],
                    [new FoundFromTransportStamp($transportName)]
                )
            );

        $this->messageBus
            ->expects(static::once())
            ->method('dispatch')
            ->willReturn(
                new Envelope(
                    (object) [],
                    [
                        new TransportMessageIdStamp($messageId),
                        new HandledStamp(null, $handler1 = uniqid('handler-1-')),
                        new HandledStamp(null, $handler2 = uniqid('handler-2-')),
                    ]
                )
            );

        $this->translator
            ->expects(static::never())
            ->method('trans');

        $response = new Response();

        $this->eventDispatcher
            ->expects(static::once())
            ->method('dispatch')
            ->with(
                static::callback(
                    function (MessageLinkErrorEvent $event) use ($response, $handler1, $handler2) {
                        $this->assertInstanceOf(
                            \LogicException::class,
                            $error = $event->getError()
                        );

                        $this->assertSame(
                            'Message of type "stdClass" was handled 2 time(s). Only one handler is expected, got: "'.$handler1.'", "'.$handler2.'".',
                            $error->getMessage()
                        );

                        $event->setResponse($response);

                        return true;
                    }
                )
            )
            ->willReturnArgument(0);

        static::assertSame(
            $response,
            \call_user_func($this->action, $messageId, $this->request)
        );
    }
}
