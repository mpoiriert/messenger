<?php

namespace Draw\Component\Messenger\Tests\ManualTrigger\Message;

use Draw\Component\Messenger\ManualTrigger\Message\RedirectToRouteMessageTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * @internal
 */
#[CoversClass(RedirectToRouteMessageTrait::class)]
class RedirectToRouteMessageTraitTest extends TestCase
{
    use RedirectToRouteMessageTrait;

    public function testGetRedirectResponse(): void
    {
        $this->route = uniqid('route-');
        $this->urlParameters = [uniqid('key-') => uniqid('value-')];

        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);

        $urlGenerator
            ->expects(static::once())
            ->method('generate')
            ->with(
                $this->route,
                $this->urlParameters,
                UrlGeneratorInterface::ABSOLUTE_URL
            )
            ->willReturn($url = uniqid('url-'))
        ;

        $response = $this->getRedirectResponse($urlGenerator);

        static::assertInstanceOf(
            RedirectResponse::class,
            $response
        );

        static::assertSame(
            $url,
            $response->getTargetUrl()
        );
    }
}
