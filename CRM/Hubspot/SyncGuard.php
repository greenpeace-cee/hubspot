<?php

/**
 * Prevents a sync from starting while another run of it is active, or while
 * items from a previous run are still waiting in (or being processed by) its queues.
 * In either case a CRM_Core_Exception is thrown, so that the overlap shows up as a failed run.
 */
final class CRM_Hubspot_SyncGuard {

  /**
   * @param string $name
   *   Name of the sync, used for the lock.
   * @param string[] $queue_names
   *   Queues the sync writes to.
   * @param callable $sync
   *   The actual sync.
   *
   * @throws CRM_Core_Exception
   *   If the sync is already running or has unprocessed queue items.
   */
  public static function run(string $name, array $queue_names, callable $sync): void {
    $lock = Civi::lockManager()->create("worker.hubspot.$name");

    if (!$lock->acquire(1)) {
      throw new CRM_Core_Exception("Cannot start $name: another run is in progress");
    }

    try {
      foreach ($queue_names as $queue_name) {
        if (self::hasPendingItems($queue_name)) {
          throw new CRM_Core_Exception("Cannot start $name: queue $queue_name still has unprocessed items");
        }
      }

      $sync();
    } finally {
      $lock->release();
    }
  }

  // Items stay in the table until they have been processed, including those currently leased.
  private static function hasPendingItems(string $queue_name): bool {
    return \Civi\Api4\QueueItem::get(FALSE)
      ->selectRowCount()
      ->addWhere('queue_name', '=', $queue_name)
      ->execute()
      ->countMatched() > 0;
  }

}
