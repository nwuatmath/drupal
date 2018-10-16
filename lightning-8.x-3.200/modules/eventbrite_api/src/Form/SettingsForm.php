<?php

namespace Drupal\eventbrite_api\Form;

use Drupal\Core\Form\ConfigFormBase;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Component\Utility;

/**
 * Defines a form that configures forms module settings.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'eventbrite_api_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'eventbrite_api.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, Request $request = NULL) {
    $config = $this->config('eventbrite_api.settings');
   //var_dump( $this->t('API Key')); die();
    $form['api_key'] = [
      '#type' => 'key_select',
      '#title' => $this->t('API Key'),
      '#default_value' => $config->get('secret'),
    ];
      $form['account'] = [
          '#type' => 'select',
          '#title' => $this->t('interviewer'),
          '#default_value' => '',
      ];
    $form['cache_duration'] = [
      '#type' => 'select',
      '#title' => $this->t('Cache Duration'),
      '#description' => $this->t('How long should requests to the Eventbrite API be cached? This can be useful to speed up page loads and to fall within Eventbrite\'s API rate limits.'),
      '#options' => [
        0 => $this->t('Disable Caching'),
        60 => $this->t('1 Minute'),
        60 * 5 => $this->t('5 Minutes'),
        60 * 30 => $this->t('30 Minutes'),
        60 * 60 => $this->t('1 Hour'),
        60 * 60 * 6 => $this->t('6 Hours'),
        60 * 60 * 12 => $this->t('12 Hours'),
        60 * 60 * 24 => $this->t('1 Day'),
        60 * 60 * 24 * 7 => $this->t('1 Week'),
      ],
      '#default_value' => $config->get('cache_duration'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

     // $response = http_get("https://api.shiftdata.com/servola/api/api.cgi?&access_key_id=15af4df9-da35-4894-b259-606166e33e90&jsonrpc=2.0&id=1&method=account.list&params=e30K&signature=OLVhDWCiPxprE9i54m4rzGwTSuU%3D", array("timeout"=>1), $info);
      //print_r($info);
    $values = $form_state->getValues();
    $this->config('eventbrite_api.settings')
      ->set('api_key', $values['api_key'])
      ->set('cache_duration', $values['cache_duration'])
      ->save();


  }

}
