<?php

namespace Drupal\http_client_manager_shiftboard\EventSubscriber;

use Drupal\http_client_manager\Event\HttpClientEvents;
use Drupal\http_client_manager\Event\HttpClientHandlerStackEvent;
use GuzzleHttp\Middleware;
use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\RequestInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Class HttpClientManagershiftboardSubscriber.
 */
class HttpClientManagershiftboardSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  static function getSubscribedEvents() {
    return [
      HttpClientEvents::HANDLER_STACK => ['onHandlerStack'],
    ];
  }

  /**
   * This method is called whenever the http_client.handler_stack event is
   * dispatched.
   *
   * @param \Drupal\http_client_manager\Event\HttpClientHandlerStackEvent $event
   *   The HTTP Client Handler stack event.
   */
  public function onHandlerStack(HttpClientHandlerStackEvent $event) {
    if ($event->getHttpServiceApi() != 'shiftboard_services') {
      return;
    }

    $handler = $event->getHandlerStack();
    $middleware = Middleware::mapRequest([$this, 'addshiftboardServiceHttpHeader']);
    $handler->push($middleware, 'shiftboard_services');
  }

  /**
   * Add shiftboard service HTTP Header.
   *
   * @param \Psr\Http\Message\RequestInterface $request
   *   The current Request object.
   *
   * @return MessageInterface
   *   Return an instance with the provided value for the specified header.
   */
  public function addshiftboardServiceHttpHeader(RequestInterface $request) {
    return $request->withHeader('X-shiftboard-HTTP-HEADER', 'shiftboard_services');
  }

}
