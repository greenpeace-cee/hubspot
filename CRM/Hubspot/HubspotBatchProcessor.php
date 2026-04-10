<?php

use Civi\Api4;
use CRM_Hubspot_HubspotContact as HubspotContact;
use GuzzleHttp\Psr7\Response;

class CRM_Hubspot_HubspotBatchProcessor {

  const BATCH_SIZE = 10;

  private static $_apiClient;

  private $successCallback;
  private $failureCallback;
  private $createBatch = [];
  private $updateBatch = [];

  private static function apiClient() {
    if (!isset(self::$_apiClient)) {
      $hubspot_account = Api4\HubspotAccount::get(FALSE)
        ->addSelect('api_key')
        ->execute()
        ->first();

      self::$_apiClient = HubSpot\Factory::createWithAccessToken($hubspot_account['api_key']);
    }

    return self::$_apiClient;
  }

  public static function createContact(HubspotContact $contact): HubspotContact {
    $response = self::apiClient()->apiRequest([
      'method' => 'POST',
      'path'   => "/crm/v3/objects/contacts",
      'body'   => [
        'properties' => $contact->toHubspotProperties(),
      ],
    ]);

    $response_body = json_decode((string) $response->getBody(), TRUE);

    return HubspotContact::fromHubspotProperties($response_body['properties']);
  }

  public function enqueueForCreate(HubspotContact $contact): void {
    $this->createBatch[] = [ 'properties' => $contact->toHubspotProperties() ];

    if (count($this->createBatch) === self::BATCH_SIZE) {
      $this->processBatch($this->createBatch, 'create');
    }
  }

  public function enqueueForUpdate(HubspotContact $contact): void {
    $this->updateBatch[] = [
      'id' => $contact->id,
      'properties' => $contact->toHubspotProperties(),
    ];

    if (count($this->updateBatch) === self::BATCH_SIZE) {
      $this->processBatch($this->updateBatch, 'update');
    }
  }

  public function flush(): void {
    if (!empty($this->createBatch)) {
      $this->processBatch($this->createBatch, 'create');
    }

    if (!empty($this->updateBatch)) {
      $this->processBatch($this->updateBatch, 'update');
    }
  }

  public static function getContactByEmail(string $email): ?HubspotContact {
    try {
      $response = self::apiClient()->apiRequest([
        'method' => 'GET',
        'path'   => "/crm/v3/objects/contacts/$email?idProperty=email&properties=" . implode(',', [
          'firstname',
          'lastname',
          'email',
          'civicrm_id',
          'owned_by',
          'ownership_score',
        ]),
      ]);

      $response_body = json_decode((string) $response->getBody(), TRUE);

      return HubspotContact::fromHubspotProperties($response_body['properties']);
    } catch (GuzzleHttp\Exception\BadResponseException $exception) {
      if ($exception->getResponse()->getStatusCode() === 404) return NULL;

      throw $exception;
    }
  }

  private static function parseApiResponse(Response $response): array {
    $body = (string) $response->getBody();

    if (str_starts_with($response->getHeader('Content-Type')[0], 'application/json')) {
      $body = json_decode($body, TRUE);
    }

    return [
      'statusCode' => $response->getStatusCode(),
      'body' => $body,
    ];
  }

  private function processBatch(array &$batch, string $endpoint): void {
    $request = [
      'method' => 'POST',
      'path'   => "/crm/v3/objects/contacts/batch/$endpoint",
      'body'   => [
        'inputs' => $batch,
      ],
    ];

    try {
      $response = self::parseApiResponse(self::apiClient()->apiRequest($request));

      if (in_array($response['statusCode'], [200, 201]) && isset($this->successCallback)) {
        call_user_func($this->successCallback, $request, $response);
      } elseif (isset($this->failureCallback)) {
        call_user_func($this->failureCallback, $request, $response);
      }
    } catch (GuzzleHttp\Exception\BadResponseException $exception) {
      $response = self::parseApiResponse($exception->getResponse());

      if (isset($this->failureCallback)) {
        call_user_func($this->failureCallback, $request, $response);
      }
    } finally {
      $batch = [];
    }
  }

  public function setBatchResponseCallbacks(array $callbacks) {
    $this->successCallback = $callbacks['success'] ?? NULL;
    $this->failureCallback = $callbacks['failure'] ?? NULL;
  }

  public static function updateContact(HubspotContact $contact): HubspotContact {
    $response = self::apiClient()->apiRequest([
      'method' => 'PATCH',
      'path'   => "/crm/v3/objects/contacts/{$contact->id}",
      'body'   => [
        'properties' => $contact->toHubspotProperties(),
      ],
    ]);

    $response_body = json_decode((string) $response->getBody(), TRUE);

    return HubspotContact::fromHubspotProperties($response_body['properties']);
  }

}
