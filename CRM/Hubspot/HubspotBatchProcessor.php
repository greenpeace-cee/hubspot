<?php

use Civi\Api4;
use GuzzleHttp\Psr7\Response;

class CRM_Hubspot_HubspotBatchProcessor extends CRM_Hubspot_HubspotClient {

  const BATCH_SIZE = 10;

  // Batch types
  const CREATE_CONTACTS = 'create-contacts';
  const UPDATE_CONTACTS = 'update-contacts';

  private array $batch = [];
  private int $batchCount = 0;
  private string $batchType;
  private string $onConflict;
  private string $onSuccess;
  private CRM_Queue_Queue_SqlParallel $queue;

  public function __construct(string $batch_type, string $on_success, string $on_conflict) {
    $this->batchType = $batch_type;
    $this->onSuccess = $on_success;
    $this->onConflict = $on_conflict;

    $this->queue = Civi::queue('hubspot-sync-' . $batch_type, [
      'type'           => 'SqlParallel',
      'runner'         => 'task',
      'reset'          => TRUE,
      'retry_interval' => 2,
      'retry_limit'    => 2,
      'error'          => 'delete',
    ]);
  }

  public function add(?string $hubspot_id, array $contact): void {
    $batch_item = [ 'properties' => $contact ];

    if (isset($hubspot_id)) {
      $batch_item['id'] = $hubspot_id;
    }

    $this->batch[] = $batch_item;

    if (count($this->batch) < self::BATCH_SIZE) return;

    $queue_task = $this->createQueueTask($this->batch);
    $this->queue->createItem($queue_task);
    $this->batch = [];
  }

  private function createQueueTask(array $batch): CRM_Queue_Task {
    $this->batchCount++;

    switch ($this->batchType) {
      case self::CREATE_CONTACTS: {
        $request = [
          'method' => 'POST',
          'path'   => "/crm/v3/objects/contacts/batch/create",
          'body'   => [ 'inputs' => $this->batch ],
        ];

        break;
      }

      case self::UPDATE_CONTACTS: {
        $request = [
          'method' => 'POST',
          'path'   => "/crm/v3/objects/contacts/batch/update",
          'body'   => [ 'inputs' => $this->batch ],
        ];

        break;
      }
    }

    $queue_task = new CRM_Queue_Task(
       [__CLASS__, 'sendBatchRequest'],
       [$request, $this->onSuccess, $this->onConflict],
       "Hubspot Sync Batch {$this->batchType}#{$this->batchCount}"
    );

    $queue_task->runAs = [
      'contactId' => CRM_Core_Session::getLoggedInContactID(),
      'domainId'  => 1,
    ];

    return $queue_task;
  }

  public function flush(): void {
    if (empty($this->batch)) return;

    $queue_task = $this->createQueueTask($this->batch);
    $this->queue->createItem($queue_task);
    $this->batch = [];
  }

  public static function sendBatchRequest(
    CRM_Queue_TaskContext $_ctx,
    array $request,
    string $on_success,
    string $on_conflict
  ): bool {
    try {
      $response = self::apiClient()->apiRequest($request);

      if (in_array($response->getStatusCode(), [200, 201, 207])) {
        call_user_func($on_success, $request['body']['inputs'], $response);
      } else {
        Civi::log()->error('Received unexpected response status code: ' . $response->getStatusCode(), [
          'batch'    => $request['body']['inputs'],
          'response' => $response,
        ]);

        return FALSE;
      }
    } catch (GuzzleHttp\Exception\BadResponseException $exception) {
      $response = $exception->getResponse();

      switch ($response->getStatusCode()) {
        case 400 /* Bad Request */ : {
          call_user_func($on_conflict, $request['body']['inputs'], $response);

          return TRUE;
        }

        case 429 /* Too Many Requests */ : {
          Civi::log()->error('Rate limit encountered', [ 'exception' => $exception ]);

          return FALSE;
        }

        default: {
          Civi::log()->error('Batch request failed', [
            'batch'     => $request['body']['inputs'],
            'response'  => json_decode((string) $response->getBody(), TRUE),
            'exception' => $exception,
          ]);

          return FALSE;
        }
      }
    }

    return TRUE;
  }

}
