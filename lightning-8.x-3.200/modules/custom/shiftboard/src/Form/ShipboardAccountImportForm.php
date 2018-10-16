<?php

namespace Drupal\shipboard\Form;

use Drupal\Core\Database\Database;
use Drupal\Core\Form\FormBase;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Form\FormStateInterface;
use Drupal\shipboard\ShipboardConnection;
use Drupal\shipboard\ShipboardAccount;
use Drupal\node\Entity\Node;


/**
 * Defines a form that triggers batch operations to download and process account
 * data from the Shipboard API.
 * Batch operations are included in this class as methods.
 */
class ShipboardAccountImportForm extends FormBase {

    /**
     * {@inheritdoc}
     */
    public function getFormId() {
        return 'shipboard_account_import_form';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state, Request $request = NULL) {
        $connection = new ShipboardConnection();
        $data       = $connection->queryEndpoint('accountsDetailFull', [
            'limit'     => 1,
            'url_query' => [
                'sort' => 'gid asc',
            ]
        ]);

       //$result = json_decode($data-result);
       //var_dump($data->result->accounts[0]->first_name);
        if (empty($data->result)) {
            $msg  = 'no data found, indicating that there';
            $msg .= ' is a problem with the connection. See ';
            $msg .= '<a href="/admin/config/services/shipboard">the Overview page</a>';
            $msg .= 'for more details.';
            drupal_set_message(t($msg), 'error');
        }

        $form['count_display'] = [
            '#type'  => 'item',
            '#title' => t('Interviewer Found'),
            'markup'  => [
                '#markup' => $data->result->count,
               // '#markup' => $data->result->accounts[0]->first_name. ' '. $data->result->accounts[0]->last_name,
               // '#markup' => $data->result->accounts[1]->first_name. ' '. $data->result->accounts[1]->last_name,
            ]
        ];
      //  $node = \Drupal::routeMatch()->getParameter('interviewer');
        $accounts = $data->result->accounts;
       // var_dump($accounts);
        foreach($accounts as $single)
        {
            $nodes = \Drupal::entityTypeManager()
                ->getStorage('node')
                ->loadByProperties(['field_email' => $single->email]);
            if ($node = reset($nodes)) {
                  //$node= Node::load($entity_ids) ;
                  $node -> field_name->value = $single->first_name. ' '. $single->last_name;
                  $node -> field_email->value =$single->email;
                  $node -> save();
            }
            else {
                $node = Node::create([
                    'type' => 'interviewer',
                    'title' => 'interviewer'.$single->id,
                    'field_name' => $single->first_name. ' '. $single->last_name,
                    'field_id' => $single->id,
                    'field_email' => $single->email
                ]);
                $node -> save();
            }

        };


        $form['count'] = [
            '#type'  => 'value',
            '#value' => $data->result->count,
        ];

        $nums   = [
            5, 10, 25, 50, 75, 100, 150, 200, 250, 300, 400, 500, 600, 700, 800, 900,
        ];
        $limits = array_combine($nums, $nums);
        $desc   = 'This is the number of accounts the API should return each call ' .
            'as the operation pages through the data.';
        $form['download_limit'] = [
            '#type'          => 'select',
            '#title'         => t('API Download Throttle'),
            '#options'       => $limits,
            '#default_value' => 200,
            '#description'   => t($desc),
        ];
        $desc = 'This is the number of accounts to analyze and save to Drupal as ' .
            'the operation pages through the data.<br />This is labor intensive so ' .
            'usually a lower number than the above throttle';
        $form['process_limit'] = [
            '#type'          => 'select',
            '#title'         => t('Node Process Throttle'),
            '#options'       => $limits,
            '#default_value' => 50,
            '#description'   => t($desc),
        ];

        $form['actions']['#type'] = 'actions';

        $form['actions']['submit'] = [
            '#type'     => 'submit',
            '#value'    => t('Import All accounts'),
            '#disabled' => empty($data->pagination->total_count),
        ];

        return $form;
    }

    public function submitForm(array &$form, FormStateInterface $form_state) {
        $connection = Database::getConnection();
        $queue      = \Drupal::queue('shipboard_account_import_worker');
        $class      = 'Drupal\shipboard\Form\ShipboardAccountImportForm';
        $batch      = [
            'title'      => t('Downloading & Processing shipboard account Data'),
            'operations' => [
                [ // Operation to download all of the accounts
                    [$class, 'downloadAccounts'], // Static method notation
                    [
                        $form_state->getValue('count', 0),
                        $form_state->getValue('download_limit', 0),
                    ],
                ],
                [ // Operation to process & save the account data
                    [$class, 'processAccounts'], // Static method notation
                    [
                        $form_state->getValue('process_limit', 0),
                    ],
                ],
            ],
            'finished' => [$class, 'finishedBatch'], // Static method notation
        ];
        batch_set($batch);
        // Lock cron out of processing while these batch operations are being
        // processed
        \Drupal::state()->set('shipboard.account_import_semaphore', TRUE);
        // Delete existing queue
        while ($worker = $queue->claimItem()) {
            $queue->deleteItem($worker);
        }
        // Clear out the staging table for fresh, whole data
        $connection->truncate('shipboard_account_staging')->execute();
    }

    /**
     * Batch operation to download all of the account data from shipboard and store
     * it in the shipboard_account_staging database table.
     *
     * @param int   $api_count
     * @param array $context
     */
    public static function downloadAccounts($api_count, $limit, &$context) {
        $database = Database::getConnection();
        if (!isset($context['sandbox']['progress'])) {
            $context['sandbox'] = [
                'progress' => 0,
                'limit'    => $limit,
                'max'      => $api_count,
            ];
            $context['results']['downloaded'] = 0;
        }
        $sandbox = &$context['sandbox'];

        $shipboard = new ShipboardConnection();
        $data   = $shipboard->queryEndpoint('accountsDetailFull', [
            'limit'     => $sandbox['limit'],
            'url_query' => [
                'offset' => (string) $sandbox['progress'],
                'sort'   => 'gid asc',
            ],
        ]);

        foreach ($data->response_data as $account_data) {
            // Check for empty or non-numeric GIDs
            if (empty($account_data->gid)) {
                $msg = t('Empty GID at progress @p for the data:', [
                    '@p' => $sandbox['progress'],
                ]);
                $msg .= '<br /><pre>' . print_r($account_data, TRUE) . '</pre>';
                \Drupal::logger('shipboard')->warning($msg);
                $sandbox['progress']++;
                continue;
            } elseif (!is_numeric($account_data->gid)) {
                $msg = t('Non-numeric GID at progress progress @p for the data:', [
                    '@p' => $sandbox['progress'],
                ]);
                $msg .= '<br /><pre>' . print_r($account_data, TRUE) . '</pre>';
                \Drupal::logger('shipboard')->warning($msg);
                $sandbox['progress']++;
                continue;
            }
            // Store the data
            $database->merge('shipboard_account_staging')
                ->key(['gid' => (int) $account_data->gid])
                ->insertFields([
                    'gid'  => (int) $account_data->gid,
                    'data' => serialize($account_data),
                ])
                ->updateFields(['data' => serialize($account_data)])
                ->execute()
            ;
            $context['results']['downloaded']++;
            $sandbox['progress']++;
            // Build a message so this isn't entirely boring for admins
            $context['message'] = '<h2>' . t('Downloading API data...') . '</h2>';
            $context['message'] .= t('Queried @c of @t account entries.', [
                '@c' => $sandbox['progress'],
                '@t' => $sandbox['max'],
            ]);
        }

        if ($sandbox['max']) {
            $context['finished'] = $sandbox['progress'] / $sandbox['max'];
        }
        // If completely done downloading, set the last time it was done, so that
        // cron can keep the data up to date with smaller queries
        if ($context['finished'] >= 1) {
            $last_time = \Drupal::time()->getRequestTime();
            \Drupal::state()->set('shipboard.account_import_last', $last_time);
        }
    }

    /**
     * Batch operation to extra data from the shipboard_account_staging table and
     * save it to a new node or one found via GID.
     *
     * @param array $context
     */
    public static function processAccounts($limit, &$context) {
        $connection = Database::getConnection();
        if (!isset($context['sandbox']['progress'])) {
            $context['sandbox'] = [
                'progress' => 0,
                'limit'    => $limit,
                'max'      => (int)$connection->select('shipboard_account_staging', 'its')
                    ->countQuery()->execute()->fetchField(),
            ];
            $context['results']['accounts'] = 0;
            $context['results']['nodes']  = 0;
            // Count new versus existing
            $context['results']['nodes_inserted'] = 0;
            $context['results']['nodes_updated']  = 0;
        }
        $sandbox = &$context['sandbox'];

        $query = $connection->select('shipboard_account_staging', 'its')
            ->fields('its')
            ->range(0, $sandbox['limit'])
        ;
        $results = $query->execute();

        foreach ($results as $row) {
            $gid        = (int) $row->gid;
            $account_data   = unserialize($row->data);
            $account        = new ShipboardAccount($account_data);
            $node_saved = $account->processAccount(); // Custom data-to-node processing

            $connection->merge('shipboard_account_previous')
                ->key(['gid' => $gid])
                ->insertFields([
                    'gid'  => $gid,
                    'data' => $row->data,
                ])
                ->updateFields(['data' => $row->data])
                ->execute()
            ;

            $query = $connection->delete('shipboard_account_staging');
            $query->condition('gid', $gid);
            $query->execute();

            $sandbox['progress']++;
            $context['results']['accounts']++;
            // Tally only the nodes saved
            if ($node_saved) {
                $context['results']['nodes']++;
                $context['results']['nodes_' . $node_saved]++;
            }

            // Build a message so this isn't entirely boring for admins
            $msg = '<h2>' . t('Processing API data to site content...') . '</h2>';
            $msg .= t('Processed @p of @t accounts, @n new & @u updated', [
                '@p' => $sandbox['progress'],
                '@t' => $sandbox['max'],
                '@n' => $context['results']['nodes_inserted'],
                '@u' => $context['results']['nodes_updated'],
            ]);
            $msg .= '<br />';
            $msg .= t('Last account: %t %g %n', [
                '%t' => $account->getTitle(),
                '%g' => '(GID:' . $gid . ')',
                '%n' => '(node:' . $account->getNode()->id() . ')',
            ]);
            $context['message'] = $msg;
        }

        if ($sandbox['max']) {
            $context['finished'] = $sandbox['progress'] / $sandbox['max'];
        }
    }

    /**
     * Reports the results of the account import operations.
     *
     * @param bool  $success
     * @param array $results
     * @param array $operations
     */
    public static function finishedBatch($success, $results, $operations) {
        // Unlock to allow cron to update the data later
        \Drupal::state()->set('shipboard.account_import_semaphore', FALSE);
        // The 'success' parameter means no fatal PHP errors were detected. All
        // other error management should be handled using 'results'.
        $downloaded = t('Finished with an error.');
        $processed  = FALSE;
        $saved      = FALSE;
        $inserted   = FALSE;
        $updated    = FALSE;
        if ($success) {
            $downloaded = \Drupal::translation()->formatPlural(
                $results['downloaded'],
                'One account downloaded.',
                '@count accounts downloaded.'
            );
            $processed  = \Drupal::translation()->formatPlural(
                $results['accounts'],
                'One account processed.',
                '@count accounts processed.'
            );
            $saved      = \Drupal::translation()->formatPlural(
                $results['nodes'],
                'One node saved.',
                '@count nodes saved.'
            );
            $inserted   = \Drupal::translation()->formatPlural(
                $results['nodes_inserted'],
                'One was created.',
                '@count were created.'
            );
            $updated    = \Drupal::translation()->formatPlural(
                $results['nodes_updated'],
                'One was updated.',
                '@count were updated.'
            );
        }
        drupal_set_message($downloaded);
        if ($processed) {
            drupal_set_message($processed);
        };
        if ($saved) {
            drupal_set_message($saved);
        };
        if ($inserted) {
            drupal_set_message($inserted);
        };
        if ($updated) {
            drupal_set_message($updated);
        };
    }
}