<?php

namespace Jmikola\AutoLogin\Http\Firewall;

use Jmikola\AutoLogin\Authentication\Token\AutoLoginToken;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\Log\LoggerInterface;
use Symfony\Component\Security\Core\SecurityContextInterface;
use Symfony\Component\Security\Core\Authentication\AuthenticationManagerInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\SecurityEvents;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symfony\Component\Security\Http\Firewall\ListenerInterface;

class AutoLoginListener implements ListenerInterface
{
    private $authenticationManager;
    private $dispatcher;
    private $logger;
    private $providerKey;
    private $securityContext;
    private $tokenParam;

    /**
     * Constructor
     *
     * @param SecurityContextInterface       $securityContext
     * @param AuthenticationManagerInterface $authenticationManager
     * @param string                         $providerKey
     * @param string                         $tokenParam
     * @param LoggerInterface                $logger
     * @param EventDispatcherInterface       $dispatcher
     */
    public function __construct(SecurityContextInterface $securityContext, AuthenticationManagerInterface $authenticationManager, $providerKey, $tokenParam, LoggerInterface $logger = null, EventDispatcherInterface $dispatcher = null)
    {
        $this->securityContext = $securityContext;
        $this->authenticationManager = $authenticationManager;
        $this->providerKey = $providerKey;
        $this->tokenParam = $tokenParam;
        $this->logger = $logger;
        $this->dispatcher = $dispatcher;
    }

    /**
     * @see Symfony\Component\Security\Http\Firewall\ListenerInterface::handle()
     */
    public function handle(GetResponseEvent $event)
    {
        if (null !== $this->securityContext->getToken()) {
            return;
        }

        $request = $event->getRequest();

        if (!$request->has($this->tokenParam)) {
            return;
        }

        try {
            $token = new AutoLoginToken($this->providerKey, $request->get($this->tokenParam));

            /* TODO: This authentication method should be considered the same as
             * remember-me according to AuthenticationTrustResolver. That will
             * entail either refactoring to extend RememberMeToken or adding a
             * service to compose the existing AuthenticationTrustResolver and
             * implement custom logic to respect our own token class.
             */ 
            if ($authenticatedToken = $this->authenticationManager->authenticate($token)) {
                $this->securityContext->setToken($authenticatedToken);

                if (null !== $this->dispatcher) {
                    $loginEvent = new InteractiveLoginEvent($request, $authenticatedToken);
                    $this->dispatcher->dispatch(SecurityEvents::INTERACTIVE_LOGIN, $loginEvent);
                }
            }
        } catch (AuthenticationException $e) {
            if (null !== $this->logger) {
                $this->logger->warn(
                    'SecurityContext not populated with auto-login token as the '.
                    'AuthenticationManager rejected the auto-login token created '.
                    'by AutoLoginListener: '.$e->getMessage()
                );
            }
        }
    }
}
