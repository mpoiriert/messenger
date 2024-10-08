<?php

namespace Draw\Component\Messenger\Broker;

use Draw\Component\Messenger\Broker\Event\BrokerRunningEvent;
use Draw\Component\Messenger\Broker\Event\BrokerStartedEvent;
use Draw\Component\Messenger\Broker\Event\NewConsumerProcessEvent;
use Draw\Contracts\Process\ProcessFactoryInterface;
use Symfony\Component\Process\Process;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class Broker
{
    private bool $stopped = false;

    private bool $allowFinishingProcess = true;

    public function __construct(
        private string $context,
        private string $consolePath,
        private ProcessFactoryInterface $processFactory,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function getContext(): string
    {
        return $this->context;
    }

    public function start(int $concurrent, int $timeout = 10): void
    {
        $this->eventDispatcher->dispatch(new BrokerStartedEvent($this, $concurrent, $timeout));

        $processes = [];
        while (true) {
            $this->eventDispatcher->dispatch(new BrokerRunningEvent($this));
            if (!$this->stopped) {
                $processes = array_merge(
                    $this->startProcesses($concurrent - \count($processes)),
                    $processes
                );
            }

            foreach ($processes as $key => $process) {
                if (!$process->isRunning()) {
                    unset($processes[$key]);
                }
            }

            switch (true) {
                case !$this->stopped:
                    break;
                case !$processes:
                case !$this->allowFinishingProcess:
                    break 2;
            }

            sleep(1);
        }

        $this->stopProcesses($processes, $timeout);
    }

    public function stop(bool $allowFinishingProcess = true): void
    {
        $this->stopped = true;
        $this->allowFinishingProcess = $allowFinishingProcess;
    }

    /**
     * @param array|Process[] $processes
     */
    private function stopProcesses(array $processes, int $timeout): void
    {
        foreach ($processes as $process) {
            if ($process->isRunning()) {
                // given SIGTERM may not be defined and that "proc_terminate" uses the constant value and not the constant itself, we use the same here
                $process->signal(15); // 15 is SIGTERM
            }
        }

        $timeoutMicro = microtime(true) + $timeout;

        do {
            usleep(1000);
            foreach ($processes as $key => $process) {
                if (!$process->isRunning()) {
                    unset($processes[$key]);
                }
            }
        } while (\count($processes) && microtime(true) < $timeoutMicro);

        foreach ($processes as $process) {
            $process->stop(0);
        }
    }

    /**
     * @return array|Process[]
     */
    private function startProcesses(int $amount): array
    {
        $processes = [];
        for ($i = 0; $i < $amount; ++$i) {
            $this->eventDispatcher->dispatch($event = new NewConsumerProcessEvent($this->context));

            if (!$receivers = $event->getReceivers()) {
                throw new \RuntimeException(\sprintf('You must have at least one receivers. If you do not want to prevent the consumer process to start use the [%s] event method.', NewConsumerProcessEvent::class.'::preventStart'));
            }

            $process = $this->processFactory->create(
                array_merge(
                    [$this->consolePath, 'messenger:consume'],
                    $receivers,
                    $this->buildOptionsFromArray($event->getOptions())
                ),
                null,
                null,
                null,
                null,
            );

            $process->start();

            $processes[] = $process;
        }

        return $processes;
    }

    private function buildOptionsFromArray(array $options): array
    {
        $results = [];
        foreach ($options as $key => $value) {
            if (\is_array($value)) {
                foreach ($value as $oneValue) {
                    $results[] = '--'.$key;
                    $results[] = $oneValue;
                }
                continue;
            }

            $results[] = '--'.$key;

            if (null === $value) {
                continue;
            }

            $results[] = $value;
        }

        return $results;
    }
}
