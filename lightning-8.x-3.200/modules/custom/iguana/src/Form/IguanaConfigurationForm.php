<?php

namespace Drupal\iguana\Form;

use Drupal\Core\Form\ConfigFormBase;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Form\FormStateInterface;

/**
 * Defines a form that configures forms module settings.
 */
class IguanaConfigurationForm extends ConfigFormBase {

    /**
     * {@inheritdoc}
     */
    public function getFormId() {
        return 'iguana_admin_settings';
    }

    /**
     * {@inheritdoc}
     */
    protected function getEditableConfigNames() {
        return [
            'iguana.settings',
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state, Request $request = NULL) {
        $config = $this->config('iguana.settings');
        $state  = \Drupal::state();
        $form["#attributes"]["autocomplete"] = "off";
        $form['iguana'] = array(
            '#type'  => 'fieldset',
            '#title' => $this->t('Iguana settings'),
        );
        $form['iguana']['url'] = array(
            '#type'          => 'textfield',
            '#title'         => $this->t('Iguana API URL'),
            '#default_value' => $config->get('iguana.url'),
        );
        $form['iguana']['username'] = array(
            '#type'          => 'textfield',
            '#title'         => $this->t('Username'),
            '#default_value' => $config->get('iguana.username'),
        );
        $form['iguana']['password'] = array(
            '#type'          => 'textfield',
            '#title'         => $this->t('Password'),
            '#default_value' => '',
            '#description'   => t('Leave blank to make no changes, use an invalid string to disable if need be.')
        );
        $form['iguana']['public_key'] = array(
            '#type'          => 'textfield',
            '#title'         => $this->t('Public Key'),
            '#default_value' => $config->get('iguana.public_key'),
        );
        $form['iguana']['private_key'] = array(
            '#type'          => 'textfield',
            '#title'         => $this->t('Private Key'),
            '#default_value' => '',
            '#description'   => t('Leave blank to make no changes, use an invalid string to disable if need be.')
        );
        $form['iguana']['division'] = array(
            '#type'          => 'textfield',
            '#title'         => $this->t('Division'),
            '#default_value' => $config->get('iguana.division'),
        );
        $form['iguana']['territory'] = array(
            '#type'          => 'textfield',
            '#title'         => $this->t('Territory'),
            '#default_value' => $config->get('iguana.territory'),
        );
        $nums   = [
            5, 10, 25, 50, 75, 100, 150, 200, 250, 300, 400, 500, 600, 700, 800, 900,
        ];
        $limits = array_combine($nums, $nums);
        $form['cron_download_limit'] = [
            '#type'          => 'select',
            '#title'         => t('Cron API Download Throttle'),
            '#options'       => $limits,
            '#default_value' => $state->get('iguana.cron_download_limit', 100),
        ];
        $form['cron_process_limit'] = [
            '#type'          => 'select',
            '#title'         => t('Cron Queue Node Process Throttle'),
            '#options'       => $limits,
            '#default_value' => $state->get('iguana.cron_process_limit', 25),
        ];
        return parent::buildForm($form, $form_state);
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {
        $values = $form_state->getValues();
        $config = $this->config('iguana.settings');
        $state  = \Drupal::state();
        $config->set('iguana.url', $values['url']);
        $config->set('iguana.username', $values['username']);
        $config->set('iguana.public_key', $values['public_key']);
        $config->set('iguana.division', $values['division']);
        $config->set('iguana.territory', $values['territory']);
        $config->save();
        if (!empty($values['private_key'])) {
            $state->set('iguana.private_key', $values['private_key']);
        }
        if (!empty($values['password'])) {
            $state->set('iguana.password', $values['password']);
        }
        $state->set('iguana.cron_download_limit', $values['cron_download_limit']);
        $state->set('iguana.cron_process_limit', $values['cron_process_limit']);
    }

}