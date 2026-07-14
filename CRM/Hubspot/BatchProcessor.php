<?php

abstract class CRM_Hubspot_BatchProcessor {

  const DEFAULT_BATCH_SIZE = 10;
  const DEFAULT_QUEUE_NAME = 'hubspot-batch-processor';

  private array $batch = [];
  private int $batchCount = 0;
  private CRM_Queue_Queue_SqlParallel $queue;

  abstract public static function processBatch(CRM_Queue_TaskContext $context, array $batch): bool;

  public function __construct(array $options = []) {
    $this->batchSize = $options['batch_size'] ?? self::DEFAULT_BATCH_SIZE;
    $queue_name = $options['queue_name'] ?? self::DEFAULT_QUEUE_NAME;

    $this->queue = Civi::queue($queue_name, [
      'type'           => 'SqlParallel',
      'runner'         => 'task',
      'reset'          => TRUE,
      'retry_interval' => 2,
      'retry_limit'    => 2,
      'error'          => 'delete',
    ]);
  }

  public function add(array $item): void {
    $this->batch[] = $item;

    if (count($this->batch) < $this->batchSize) return;

    $queue_task = $this->createQueueTask($this->batch);
    $this->queue->createItem($queue_task);
    $this->batch = [];
  }

  private function createQueueTask(array $batch): CRM_Queue_Task {
    $this->batchCount++;

    $queue_task = new CRM_Queue_Task(
       [get_class($this), 'processBatch'],
       [$batch],
       "Hubspot Batch #{$this->batchCount}"
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

}
