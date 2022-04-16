<?php

namespace Draw\Component\Messenger\MessageHandler;

use Draw\Component\Messenger\Message\RedirectToRouteMessageInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class RedirectToRouteMessageHandler implements MessageHandlerInterface
{
    private UrlGeneratorInterface $urlGenerator;

    public function __construct(UrlGeneratorInterface $urlGenerator)
    {
        $this->urlGenerator = $urlGenerator;
    }

    public function __invoke(RedirectToRouteMessageInterface $message): RedirectResponse
    {
        return $message->getRedirectResponse($this->urlGenerator);
    }
}
