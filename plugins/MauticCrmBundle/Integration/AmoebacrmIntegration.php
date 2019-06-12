<?php

namespace MauticPlugin\MauticCrmBundle\Integration;

use DateTime;
use Doctrine\Common\Proxy\Proxy;
use Doctrine\ORM\ORMException;
use Exception;
use Mautic\LeadBundle\Entity\Lead;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\ConsoleOutput;

/**
 * AmoebaCrm Integration.
 */
class AmoebacrmIntegration extends CrmAbstractIntegration {
  const integrationEntity = 'Contact';
  const internalEntity = 'lead';
  const integration = 'AmoebaCrm';

  /**
   * Request settings array.
   *
   * @var array
   */
  protected $requestSettings = [
    'encode_parameters' => 'json',
    'return_raw' => TRUE,
  ];

  /**
   * {@inheritdoc}
   */
  public function getName() {
    return 'Amoebacrm';
  }

  /**
   * {@inheritdoc}
   */
  public function getDisplayName() {
    return 'AmoebaCrm';
  }

  /**
   * {@inheritdoc}
   */
  public function getAuthenticationType() {
    return 'oauth2';
  }

  /**
   * {@inheritdoc}
   */
  public function getAuthenticationUrl() {
    return $this->keys['instance_url'] . '/oauth2/authorize/';
  }

  /**
   * {@inheritdoc}
   */
  public function getAccessTokenUrl() {
    return $this->keys['instance_url'] . '/oauth2/token/';
  }

  /**
   * {@inheritdoc}
   */
  public function getRequiredKeyFields() {
    return [
      'instance_url'  => 'AmoebaCrm URL',
      'client_id'     => 'Client ID',
      'client_secret' => 'Client Secret',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getSupportedFeatures() {
    return ['push_lead', 'get_leads', 'push_leads'];
  }

  /**
   * {@inheritdoc}
   */
  public function getDataPriority() {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getBearerToken($inAuthorization = FALSE) {
    if (!$inAuthorization && isset($this->keys[$this->getAuthTokenKey()])) {
      return $this->keys[$this->getAuthTokenKey()];
    }
    return FALSE;
  }

  /**
   * Returns the endpoint for retrieving all the fields from AmoebaCrm.
   * @return string
   *   A string containing the endpoint for getting the fields.
   */
  public function endpointGetFields() {
    return $this->keys['instance_url'] . '/api/contact/fields?_format=json';
  }

  /**
   * Returns the endpoint for creating contacts on AmoebaCrm.
   *
   * @return string
   *   A string containing the endpoint for creating contacts.
   */
  public function endpointCreateContacts() {
    return $this->keys['instance_url'] . '/api/contact/create?_format=json';
  }

  /**
   * Returns the endpoint for updating a specific contact on AmoebaCrn by the id.
   *
   * @param $contactId
   *   The id of the contact that needs to be updated.
   * @return string
   *   A string containing the endpoint for updating a contact.
   */
  public function endpointUpdateContact($contactId) {
    return $this->keys['instance_url'] . '/contact/' . $contactId . '?_format=json';
  }

  /**
   * Returns the endpoint for getting contacts records from AmoebaCrm.
   *
   * @return string
   *   A string containing the endpoint for retrieving the data.
   */
  public function endpointRetrieveContacts() {
    return $this->keys['instance_url'] . '/api/retrieve/contact?_format=json';
  }

  /**
   * Gets integration fields.
   *
   * @param array $settings
   *   Integration settings.
   *
   * @return array|bool
   *   If the request is successful or the fields exist in cache returns them,
   *   otherwise returns an empty array.
   */
  public function getAvailableLeadFields($settings = []) {
    if (!$this->isAuthorized()) {
      return [];
    }
    // Try to get the fields from cache.
    $settings['cache_suffix'] = '.Contact';
    $fields = parent::getAvailableLeadFields($settings);
    // Take available entity fields from AmoebaCrm.
    if (empty($fields)) {
      try {
        $fields = $this->getApiHelper()->request($this->endpointGetFields(), [], 'GET', $this->requestSettings);
      }
      catch (Exception $e) {
        $this->logIntegrationError($e);
      }
      if (!empty($fields)) {
        $leadFields = [];
        foreach ($fields['fields'] as $key => $label) {
          $leadFields[$key] = [
            'label'    => $label,
            'type'     => 'string',
            'required' => ($key == 'email') ? TRUE : FALSE,
            'group'    => 'Contact',
          ];
        }
        $this->cache->set('leadFields.Contact', $leadFields);
        return $leadFields;
      }
    }

    return $fields;
  }

  /**
   * Pushes leads to integration through a triggered action.
   *
   * @param array|Lead $lead
   *   The lead object or array.
   * @param array $config
   *   The integration configuration.
   *
   * @return array|bool
   *   Returns the request response or FALSE if it was unsuccessful.
   */
  public function pushLead($lead, $config = []) {
    // Get the Mautic config.
    $config = $this->mergeConfigToFeatureSettings($config);

    // Check if there are contact fields in Mautic.
    if (empty($config['leadFields'])) {
      $this->logger->addError('There are no contact fields available.');
      return FALSE;
    }

    // Check if integration exists for this contact.
    $integrationEntity = $this->getIntegrationEntityRepository()->getIntegrationEntity($this->getName(), self::integrationEntity, self::internalEntity, $lead->getId());
    if (!empty($integrationEntity['integration_entity_id'])) {
      // If integration exists try to update the contact.
      $response = $this->syncContactWithIntegration($config, $lead, $this->endpointUpdateContact($integrationEntity['integration_entity_id']), 'PATCH', $this->requestSettings);
    }
    // If entity integration was not found or contact was not updated create it.
    if (empty($integrationEntity['integration_entity_id']) || (!empty($response) && $response !== TRUE)) {
      $response = $this->syncContactWithIntegration($config, $lead, $this->endpointCreateContacts(), 'POST', $this->requestSettings);
      return $response;
    }

    return FALSE;
  }

  /**
   * Gets leads from integration.
   *
   * @param array $params
   *   Sync params.
   * @param string $query
   *   The query string.
   * @param array $executed
   *   Number of processed contacts.
   * @param array $result
   *   Result array.
   * @param string $object
   *   The object type.
   *
   * @return array|null
   *   Return the number of processed contacts.
   */
  public function getLeads($params, $query, &$executed, $result = [], $object = 'Lead') {
    // Initialize the progress status.
    if (!is_array($executed)) {
      $executed = [
        0 => 0,
        1 => 0,
      ];
    }

    try {
      if ($this->isAuthorized()) {
        // Make the request to get contacts.
        $contacts = $this->getApiHelper()->request($this->endpointRetrieveContacts(), [], 'GET', $this->requestSettings);
        $result['totalSize'] = count($contacts);
        $result['records'] = $contacts;

        // Set the progress bar.
        if (isset($params['output']) && !isset($params['progress'])) {
          $progress = new ProgressBar($params['output'], $result['totalSize']);
          $params['progress'] = $progress;
        }

        // Create or update contacts and update the list of the progress.
        list($justUpdated, $justCreated) = $this->amendLeadDataBeforeMauticPopulate($result, self::integrationEntity, $params);

        $executed[0] += $justUpdated;
        $executed[1] += $justCreated;

        if (isset($progress)) {
          $progress->finish();
        }

      }
    }
    catch (Exception $e) {
      $this->logIntegrationError($e);
    }

    return $executed;

  }

  /**
   * Pushes leads to AmoebaCrm integration.
   *
   * @param array $params
   *   Sync params.
   *
   * @return array
   *   Return the number of processed contacts.
   */
  public function pushLeads($params = []) {
    $config = $this->mergeConfigToFeatureSettings($params);
    $integrationEntityRepo = $this->getIntegrationEntityRepository();
    $totalUpdated = 0;
    $totalCreated = 0;
    $totalErrors = 0;
    $mauticLeadFieldString = $this->getApiHelper()->getMauticLeadFieldString($config);
    // Get all the leads that needs to be updated or created.
    $leadsToUpdate = $integrationEntityRepo->findLeadsToUpdate(self::integration, self::internalEntity, $mauticLeadFieldString);
    $leadsToCreate = $integrationEntityRepo->findLeadsToCreate(self::integration, $mauticLeadFieldString);
    // Get the total number of contacts to update.
    $totalToUpdate = count($leadsToUpdate['Contact']);
    // Get the total number of contacts to create.
    $totalToCreate = count($leadsToCreate);
    // Set the total number of contacts to process.
    $totalToProcess = $totalToCreate + $totalToUpdate;

    // Set the console message and the console progress bar.
    if (defined('IN_MAUTIC_CONSOLE')) {
      if ($totalToProcess) {
        $output = new ConsoleOutput();
        $output->writeln("About $totalToUpdate to update and about $totalToCreate to create");
        $progress = new ProgressBar($output, $totalToProcess);
      }
    }
    // Query for contacts to create and update until are all synced.
    while ($totalToProcess - ($totalCreated + $totalUpdated + $totalErrors) > 0) {
      // Make PATCH request to update contacts.
      if (!empty($leadsToUpdate['Contact'])) {
        foreach ($leadsToUpdate['Contact'] as $lead) {
          $response = $this->syncContactWithIntegration($config, $lead, $this->endpointUpdateContact($lead['integration_entity_id']), 'PATCH', $this->requestSettings);
          if ($response === TRUE && !empty($progress)) {
            $progress->advance();
            $totalUpdated++;
            continue;
          }
          // If the contact wasn't updated create it again in integration.
          $leadsToCreate[] = $response;
        }
      }

      // Make POST request to create contacts.
      if (!empty($leadsToCreate)) {
        foreach ($leadsToCreate as $lead) {
          $response = $this->syncContactWithIntegration($config, $lead, $this->endpointCreateContacts(), 'POST', $this->requestSettings);
          if ($response === TRUE && !empty($progress)) {
            $progress->advance();
            $totalCreated++;
            continue;
          }
          $totalErrors++;
        }
      }
    }
    if (!empty($progress) && !empty($output)) {
      $progress->finish();
      $output->writeln('Process finished');
    }
    $totalIgnored = $totalToProcess - ($totalUpdated + $totalCreated + $totalErrors);

    return [$totalUpdated, $totalCreated, $totalErrors, $totalIgnored];
  }

  /**
   * Gets the lead id by the given lead entity.
   *
   * @param $lead
   *   The lead entity to sync.
   * @return int|mixed
   *   Returns the lead id, or 0 if doesn't exists.
   */
  public function getLeadId($lead) {
    $leadId = 0;
    if (is_array($lead)) {
      $leadId = $lead['internal_entity_id'];
    }
    elseif ($lead instanceof Lead) {
      $leadId = $lead->getId();
    }
    return $leadId;
  }

  /**
   * Sync contacts with integration.
   *
   * @param array $config
   *   The integration configuration.
   * @param array|object $lead
   *   The lead to sync.
   * @param string $url
   *   Endpoint url.
   * @param string $method
   *   Request method.
   * @param array $settings
   *   The settings array for request.
   *
   * @return bool|array
   *   If the PATCH request is successful returns true, otherwise returns the
   *   lead. If the POST request is successful returns true, otherwise returns
   *   false.
   */
  public function syncContactWithIntegration($config, $lead, $url, $method, $settings) {
    // Set the lead['id'] to the internal lead id so Mautic can populate
    // fields with data.
    if (is_array($lead)) {
      $lead['id'] = $lead['internal_entity_id'];
      $leadId = $lead['id'];
    }
    elseif ($lead instanceof Lead) {
      $leadId = $lead->getId();
    }
    $populatedLeadFields = $this->populateLeadData($lead, $config);
    // Map the data before making request to amoeba.
    $mappedDataToSync = $this->getApiHelper()->prepareFieldsForPush($populatedLeadFields);
    // Check if the data exists and if the user is authorized in order to make
    // the request to AmoebaCrm.
    if (!empty($mappedDataToSync) && $this->isAuthorized() && !empty($leadId)) {
      try {
        $response = $this->getApiHelper()->request($url, $mappedDataToSync, $method, $settings);
      }
      catch (Exception $e) {
        // Add error on Mautic.
        $e->setContactId($lead['id']);
        $this->logIntegrationError($e);
      }
    }
    if ($method == 'PATCH') {
      // If the entity was not updated, return the lead in order to be created
      // again.
      if (!empty($response['id'])) {
        $isUpdated = $this->updateContacts($response['id'], $leadId);
      }
      return !empty($isUpdated) ? $isUpdated : $lead;
    }
    // If the POST request was successful create a new integration entity
    // and return TRUE otherwise return FALSE.
    elseif ($method == 'POST' && !empty($response['id']) && !empty($leadId)) {
      $this->createIntegrationEntity(self::integrationEntity, $response['id'],self::internalEntity, $leadId);
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Gets the integration object by the lead id given.
   *
   * @param $leadId
   *   The id of the lead.
   * @return bool|Proxy|object|null
   *   Returns the integration entity if exists, FALSE otherwise.
   */
  public function getIntegrationEntityByLeadId($leadId) {
    if (!empty($leadId)) {
      $integrationValues = $this->getIntegrationEntityRepository()->getIntegrationEntity($this->getName(), self::integrationEntity, self::internalEntity, $leadId);
      try {
        return !empty($integrationValues['id']) ? $this->em->getReference('MauticPluginBundle:IntegrationEntity', $integrationValues['id']) : FALSE;
      }
      catch (ORMException $e) {
        $this->logIntegrationError($e);
      }
    }
    return FALSE;
  }

  /**
   * Updates the contact entity.
   *
   * @param $contactIdFromResponse
   *   The contact id returned from the request made.
   * @param $leadId
   *   The id of the lead.
   * @return bool
   *   Returns TRUE if the entity was updated, FALSE otherwise.
   */
  public function updateContacts($contactIdFromResponse, $leadId) {
    $integrationEntity = $this->getIntegrationEntityByLeadId($leadId);
    // Check if the entity was updated successfully.
    if (!empty($contactIdFromResponse) && !empty($integrationEntity)) {
      // Update the sync date on Mautic.
      $integrationEntity->setLastSyncDate($this->getLastSyncDate($integrationEntity, [], false));
      $this->getIntegrationEntityRepository()->saveEntity($integrationEntity);
      return TRUE;
    }
    elseif (!empty($integrationEntity)) {
      // Delete the integration in order to be able to create it afterwards.
      $this->getIntegrationEntityRepository()->deleteEntity($integrationEntity);
      return FALSE;
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  protected function cleanPriorityFields($fieldsToUpdate, $objects = NULL) {
    // If the method is used to get the leadFields return them otherwise return
    // the priority fields array flipped to be intersected with the existing
    // fields.
    return isset($fieldsToUpdate['leadFields']) ? $fieldsToUpdate['leadFields'] : array_flip($fieldsToUpdate);
  }

  /**
   * {@inheritdoc}
   */
  public function amendLeadDataBeforeMauticPopulate($data, $object, $params = []) {
    $updated = 0;
    $created = 0;
    $entity = NULL;
    $currentTime = new DateTime();
    $leadClass = Lead::class;
    if (!empty($data['records'])) {
      foreach ($data['records'] as $record) {
        if (isset($params['progress'])) {
          $params['progress']->advance();
        }
        // Create or update contact in mautic if it exists.
        $entity = $this->getMauticLead($record, TRUE, NULL, NULL, $object);
        $dateModified = !empty($entity) ? $entity->getDateModified() : '';

        if (method_exists($entity, 'isNewlyCreated') && $entity->isNewlyCreated()) {
          ++$created;
        }
        elseif (!empty($dateModified) && $dateModified->getTimestamp() >= $currentTime->getTimestamp()) {
          ++$updated;
        }
        if (!empty($entity)) {
          $integrationMapping[$entity->getId()] = [
            'entity'                => $entity,
            'integration_entity_id' => $record['id'],
          ];
          // Create integration entity or update the last sync date if it exists.
          if (!empty($integrationMapping)) {
            $this->buildIntegrationEntities($integrationMapping, $object, self::internalEntity, $params);
            $this->em->clear($leadClass);
            $integrationMapping = [];
          }
          unset($integrationMapping);
        }
      }
    }
    return [$updated, $created];
  }

  /**
   * {@inheritdoc}
   */
  public function appendToForm(&$builder, $data, $formArea) {
    // Append to form the available entities to sync.
    if ($formArea == 'features') {
      $builder->add(
        'objects',
        'choice',
        [
          'choices' => [
            'Contact' => 'Contact',
          ],
          'expanded' => TRUE,
          'multiple' => TRUE,
          'label' => 'Choose AmoebaCrm objects to pull contacts from',
          'label_attr' => ['class' => ''],
          'empty_value' => FALSE,
          'required' => FALSE,
        ]
      );
    }
    parent::appendToForm($builder, $data, $formArea);
  }

}

