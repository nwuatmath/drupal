<?php

namespace Drupal\http_client_manager\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\http_client_manager\HttpClientManagerFactoryInterface;
use Drupal\http_client_manager\HttpServiceApiHandlerInterface;
use Guzzle\Service\Description\Parameter;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Class HttpConfigRequestForm.
 *
 * @package Drupal\http_client_manager\Form
 */
class HttpConfigRequestForm extends EntityForm {

  /**
   * Current Request.
   *
   * @var null|\Symfony\Component\HttpFoundation\Request
   */
  protected $request;

  /**
   * Drupal\http_client_manager\HttpServiceApiHandler definition.
   *
   * @var \Drupal\http_client_manager\HttpServiceApiHandler
   */
  protected $httpServicesApi;

  /**
   * Drupal\http_client_manager\HttpClientManagerFactory definition.
   *
   * @var \Drupal\http_client_manager\HttpClientManagerFactory
   */
  protected $httpClientFactory;

  /**
   * HttpConfigRequestForm constructor.
   *
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The Request Stack Service.
   * @param \Drupal\http_client_manager\HttpServiceApiHandlerInterface $http_services_api
   *   The Http Service Api Handler service.
   * @param \Drupal\http_client_manager\HttpClientManagerFactoryInterface $http_client_manager_factory
   *   The Http Client Factory service.
   */
  public function __construct(
    RequestStack $requestStack,
    HttpServiceApiHandlerInterface $http_services_api,
    HttpClientManagerFactoryInterface $http_client_manager_factory
  ) {
    $this->request = $requestStack->getCurrentRequest();
    $this->httpServicesApi = $http_services_api;
    $this->httpClientFactory = $http_client_manager_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('request_stack'),
      $container->get('http_client_manager.http_services_api'),
      $container->get('http_client_manager.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    $serviceApi = $this->request->get('serviceApi');
    $commandName = $this->request->get('commandName');
    $http_config_request = $this->entity;

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $http_config_request->label(),
      '#description' => $this->t("Label for the Http Config Request."),
      '#required' => TRUE,
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $http_config_request->id(),
      '#machine_name' => [
        'exists' => '\Drupal\http_client_manager\Entity\HttpConfigRequest::load',
      ],
      '#disabled' => !$http_config_request->isNew(),
    ];

    $form['service_api'] = [
      '#type' => 'value',
      '#value' => $serviceApi,
    ];

    $form['command_name'] = [
      '#type' => 'value',
      '#value' => $commandName,
    ];

    $form['parameters'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('parameters'),
      '#tree' => TRUE,
    ];

    $client = $this->httpClientFactory->get($serviceApi);
    $parameters = $http_config_request->get('parameters');

    /** @var \GuzzleHttp\Command\Guzzle\Parameter $param */
    foreach ($client->getCommand($commandName)->getParams() as $param) {
      $name = $param->getName();
      $form['parameters'][$name] = [
        '#command_param' => $param,
        '#title' => $this->t($name),
        '#type' => 'textarea',
        '#rows' => 1,
        '#required' => $param->isRequired(),
        '#default_value' => $parameters[$name] ? $parameters[$name] : $param->getDefault(),
        '#description' => $param->getDescription() ? $this->t($param->getDescription()) : '',
      ];

      switch ($param->getType()) {
        case 'integer':
          $form['parameters'][$name]['#type'] = 'number';
          $form['parameters'][$name]['#value_callback'] = [$this, 'integerValue'];
          break;

        case 'array':
          $form['parameters'][$name]['#type'] = 'textarea';
          $form['parameters'][$name]['#rows'] = 3;
          $placeholder = $this->t('Enter a list of values (one value per row).');
          $form['parameters'][$name]['#attributes']['placeholder'] = $placeholder;
          $form['parameters'][$name]['#value_callback'] = [$this, 'arrayValue'];
          break;
      }
    }

    // Show the token help.
    $form['token_help'] = [
      '#theme' => 'token_tree_link',
    ];

    return $form;
  }

  /**
   * Value callback: casts provided input to integer.
   *
   * @see form
   */
  public function integerValue(&$element, $input, FormStateInterface $form_state) {
    if ($input !== FALSE && $input !== NULL) {
      return (int) $input;
    }
    return NULL;
  }

  /**
   * Value callback: converts strings to array values.
   *
   * @see form
   */
  public function arrayValue(&$element, $input, FormStateInterface $form_state) {
    if ($input !== FALSE && $input !== NULL) {
      $input = trim($input);
      if (empty($input)) {
        return [];
      }

      $value_callback = $this->getValueCallback($element['#command_param']);
      $items = explode("\n", $input);

      foreach ($items as &$item) {
        $item = trim($item);
        if ($value_callback) {
          $item = $this->{$value_callback}($element, $item, $form_state);
        }
      }
      return $items;
    }
    return !empty($element['#default_value']) ? implode("\n", $element['#default_value']) : NULL;
  }

  /**
   * Get value callback.
   *
   * @param \Guzzle\Service\Description\Parameter $param
   *   A command parameter object.
   *
   * @return bool|string
   *   A callback or FALSE.
   */
  protected function getValueCallback(Parameter $param) {
    $callback = $param->getItems()->getType() . 'Value';
    return method_exists($this, $callback) ? $callback : FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $http_config_request = $this->entity;
    $status = $http_config_request->save();
    $messenger = \Drupal::messenger();

    switch ($status) {
      case SAVED_NEW:
        $messenger->addStatus($this->t('Created the %label Http Config Request.', [
          '%label' => $http_config_request->label(),
        ]));
        break;

      default:
        $messenger->addStatus($this->t('Saved the %label Http Config Request.', [
          '%label' => $http_config_request->label(),
        ]));
    }
    $form_state->setRedirectUrl($http_config_request->toUrl('collection'));
  }

}
