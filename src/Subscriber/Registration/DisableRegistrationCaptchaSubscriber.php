<?php declare(strict_types=1);

namespace PhallosanCustomizations\Subscriber\Registration;

use Shopware\Core\PlatformRequest;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class DisableRegistrationCaptchaSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::CONTROLLER => [
                ['onBuildValidation', -19],
            ],
        ];
    }

    public function onBuildValidation(ControllerEvent $event): void
    {
        $captchaAnnotationRoute = $event->getRequest()->attributes->get('_route', false);

        if ($captchaAnnotationRoute === 'frontend.account.register.save') {
            $event->getRequest()->attributes->set(PlatformRequest::ATTRIBUTE_CAPTCHA, false);
        }
    }
}
