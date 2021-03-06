<?php

namespace Drupal\simple_oauth_extras\Controller;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\Url;
use Drupal\simple_oauth\Entities\UserEntity;
use Drupal\simple_oauth\KnownClientsRepositoryInterface;
use Drupal\simple_oauth\Plugin\Oauth2GrantManagerInterface;
use GuzzleHttp\Psr7\Response;
use League\OAuth2\Server\Exception\OAuthServerException;
use Symfony\Bridge\PsrHttpMessage\HttpMessageFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Oauth2AuthorizeController.
 */
class Oauth2AuthorizeController extends ControllerBase {

  /**
   * @var \Symfony\Bridge\PsrHttpMessage\HttpMessageFactoryInterface
   */
  protected $messageFactory;

  /**
   * @var \Drupal\simple_oauth\Plugin\Oauth2GrantManagerInterface
   */
  protected $grantManager;

  /**
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The known client repository service.
   *
   * @var \Drupal\simple_oauth\KnownClientsRepositoryInterface
   */
  protected $knownClientRepository;

  /**
   * Oauth2AuthorizeController construct.
   *
   * @param \Symfony\Bridge\PsrHttpMessage\HttpMessageFactoryInterface $message_factory
   *   The PSR-7 converter.
   * @param \Drupal\simple_oauth\Plugin\Oauth2GrantManagerInterface $grant_manager
   *   The plugin.manager.oauth2_grant.processor service.
   * @param \Drupal\Core\Form\FormBuilderInterface $form_builder
   *   The form builder.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\simple_oauth\KnownClientsRepositoryInterface $known_clients_repository
   *   The known client repository service.
   */
  public function __construct(HttpMessageFactoryInterface $message_factory, Oauth2GrantManagerInterface $grant_manager, FormBuilderInterface $form_builder, MessengerInterface $messenger, ConfigFactoryInterface $config_factory, KnownClientsRepositoryInterface $known_clients_repository) {
    $this->messageFactory = $message_factory;
    $this->grantManager = $grant_manager;
    $this->formBuilder = $form_builder;
    $this->messenger = $messenger;
    $this->configFactory = $config_factory;
    $this->knownClientRepository = $known_clients_repository;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('psr7.http_message_factory'),
      $container->get('plugin.manager.oauth2_grant.processor'),
      $container->get('form_builder'),
      $container->get('messenger'),
      $container->get('config.factory'),
      $container->get('simple_oauth.known_clients')
    );
  }

  /**
   * Authorizes the code generation or prints the confirmation form.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The incoming request.
   *
   * @return mixed
   *   The response.
   */
  public function authorize(Request $request) {
    $client_uuid = $request->get('client_id');
    if (empty($client_uuid)) {
      return OAuthServerException::invalidClient()
        ->generateHttpResponse(new Response());
    }
    try {
      $consumer_storage = $this->entityTypeManager()->getStorage('consumer');
    }
    catch (InvalidPluginDefinitionException $exception) {
      watchdog_exception('simple_oauth_extras', $exception);
      return RedirectResponse::create(Url::fromRoute('<front>')->toString());
    }
    $client_drupal_entities = $consumer_storage
      ->loadByProperties([
        'uuid' => $client_uuid,
      ]);
    if (empty($client_drupal_entities)) {
      return OAuthServerException::invalidClient()
        ->generateHttpResponse(new Response());
    }

    $client_drupal_entity = reset($client_drupal_entities);
    $is_third_party = $client_drupal_entity->get('third_party')->value;

    $scopes = [];
    if ($request->query->get('scope')) {
      $scopes = explode(' ', $request->query->get('scope'));
    }

    // Login user may skip the grant step if the client is not third party or
    // known.
    if ($this->currentUser()->isAuthenticated() && !$is_third_party || $this->isKnownClient($client_uuid, $scopes)) {
      if ($request->get('response_type') == 'code') {
        $grant_type = 'code';
      }
      elseif ($request->get('response_type') == 'token') {
        $grant_type = 'implicit';
      }
      else {
        $grant_type = NULL;
      }
      try {
        $server = $this->grantManager->getAuthorizationServer($grant_type);
        $ps7_request = $this->messageFactory->createRequest($request);
        $auth_request = $server->validateAuthorizationRequest($ps7_request);
      }
      catch (OAuthServerException $exception) {
        $this->messenger->addMessage($this->t('Fatal error. Unable to get the authorization server.'));
        watchdog_exception('simple_oauth_extras', $exception);
        return RedirectResponse::create(Url::fromRoute('<front>')->toString());
      }
      if ($auth_request) {
        $user_entity = new UserEntity();
        $user_entity->setIdentifier($this->currentUser()->id());
        $auth_request->setUser($user_entity);
        $can_grant_codes = $this->currentUser()
          ->hasPermission('grant simple_oauth codes');
        $auth_request->setAuthorizationApproved($can_grant_codes);
        $response = $server->completeAuthorizationRequest($auth_request,
          new Response());
        $redirect_response = TrustedRedirectResponse::create(
          $response->getHeaderLine('location'),
          $response->getStatusCode(),
          $response->getHeaders()
        );

        return $redirect_response;
      }
    }
    return $this->formBuilder->getForm('Drupal\simple_oauth_extras\Controller\Oauth2AuthorizeForm');
  }

  /**
   * Whether the client with the given scopes is known and already authorized.
   *
   * @param string $client_uuid
   *   The client UUID.
   * @param string[] $scopes
   *   The list of scopes.
   *
   * @return bool
   *   TRUE if the client is authorized, FALSE otherwise.
   */
  protected function isKnownClient($client_uuid, array $scopes) {
    if (!$this->configFactory->get('simple_oauth.settings')->get('remember_clients')) {
      return FALSE;
    }
    return $this->knownClientRepository->isAuthorized($this->currentUser()->id(), $client_uuid, $scopes);
  }

}
