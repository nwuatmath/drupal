<?php

namespace Drupal\shipboard\Form;

use Drupal\Core\Form\ConfigFormBase;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Form\FormStateInterface;

/**
 * Defines a form that configures forms module settings.
 */
class ShipboardConfigurationForm extends ConfigFormBase {

    /**
     * {@inheritdoc}
     */
    public function getFormId() {
        return 'shipboard_admin_settings';
    }

    /**
     * {@inheritdoc}
     */
    protected function getEditableConfigNames() {
        return [
            'shipboard.settings',
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state, Request $request = NULL) {
        $config = $this->config('shipboard.settings');
        $state  = \Drupal::state();
        $form["#attributes"]["autocomplete"] = "off";
        $form['shipboard'] = array(
            '#type'  => 'fieldset',
            '#title' => $this->t('Shipboard settings'),
        );
        $form['shipboard']['url'] = array(
            '#type'          => 'textfield',
            '#title'         => $this->t('shipboard API URL'),
            '#default_value' => $config->get('shipboard.url'),
        );
        $form['shipboard']['username'] = array(
            '#type'          => 'textfield',
            '#title'         => $this->t('Username'),
            '#default_value' => $config->get('shipboard.username'),
        );
        $form['shipboard']['password'] = array(
            '#type'          => 'textfield',
            '#title'         => $this->t('Password'),
            '#default_value' => '',
            '#description'   => t('Leave blank to make no changes, use an invalid string to disable if need be.')
        );
        $form['shipboard']['public_key'] = array(
            '#type'          => 'textfield',
            '#title'         => $this->t('Public Key'),
            '#default_value' => $config->get('shipboard.public_key'),
        );
        $form['shipboard']['private_key'] = array(
            '#type'          => 'textfield',
            '#title'         => $this->t('Private Key'),
            '#default_value' => '',
            '#description'   => t('Leave blank to make no changes, use an invalid string to disable if need be.')
        );
        $form['shipboard']['division'] = array(
            '#type'          => 'textfield',
            '#title'         => $this->t('Division'),
            '#default_value' => $config->get('shipboard.division'),
        );
        $form['shipboard']['territory'] = array(
            '#type'          => 'textfield',
            '#title'         => $this->t('Territory'),
            '#default_value' => $config->get('shipboard.territory'),
        );
        $nums   = [
            5, 10, 25, 50, 75, 100, 150, 200, 250, 300, 400, 500, 600, 700, 800, 900,
        ];
        $limits = array_combine($nums, $nums);
        $form['cron_download_limit'] = [
            '#type'          => 'select',
            '#title'         => t('Cron API Download Throttle'),
            '#options'       => $limits,
            '#default_value' => $state->get('shipboard.cron_download_limit', 100),
        ];
        $form['cron_process_limit'] = [
            '#type'          => 'select',
            '#title'         => t('Cron Queue Node Process Throttle'),
            '#options'       => $limits,
            '#default_value' => $state->get('shipboard.cron_process_limit', 25),
        ];
        return parent::buildForm($form, $form_state);
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {
        $values = $form_state->getValues();
        $config = $this->config('shipboard.settings');
        $state  = \Drupal::state();
        $config->set('shipboard.url', $values['url']);
        $config->set('shipboard.username', $values['username']);
        $config->set('shipboard.public_key', $values['public_key']);
        $config->set('shipboard.division', $values['division']);
        $config->set('shipboard.territory', $values['territory']);
        $config->save();
        if (!empty($values['private_key'])) {
            $state->set('shipboard.private_key', $values['private_key']);
        }
        if (!empty($values['password'])) {
            $state->set('shipboard.password', $values['password']);
        }
        $state->set('shipboard.cron_download_limit', $values['cron_download_limit']);
        $state->set('shipboard.cron_process_limit', $values['cron_process_limit']);
    }

}