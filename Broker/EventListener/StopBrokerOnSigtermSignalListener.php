<?php

namespace Draw\Component\Messenger\Broker\EventListener;

use Draw\Component\Messenger\Broker\Event\BrokerStartedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class StopBrokerOnSigtermSignalListener implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        $result = [];
        if (\function_exists('pcntl_signal')) {
            $result[BrokerStartedEvent::class] = ['onBrokerStarted', 100];
        }

        return $result;
    }

    public function onBrokerStarted(BrokerStartedEvent $event): void
    {
        $broker = $event->getBroker();
        pcntl_signal(\SIGTERM, static function () use ($broker): void {
            $broker->stop();
        });
    }
}
