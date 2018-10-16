<?php

namespace Drupal\eventbrite_api\Client;

use Drupal\eventbrite_api\eventbrite;
use Drupal\eventbrite_api\HttpClientInterface;

class HttpClient extends eventbrite\Eventbrite implements HttpClientInterface {

  /**
   * Create a new client instance.
   *
   * @param string $token
   *   The Eventbrite OAuth token.
   */
  public static function create($token) {
    return new static($token);
  }

}
