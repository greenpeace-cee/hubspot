<?php

use Civi\Api4;

/**
 * Excludes contacts with failed or pending syncs from audits, so they don't show up as differences.
 *
 * Checked when queueing and again in the workers, as syncs may run in between.
 */
trait CRM_Hubspot_AuditSyncStateTrait {

  /**
   * @return array
   *   Last sync date by contact ID, for those of $contact_ids whose last sync succeeded.
   */
  private static function successfulSyncDates(array $contact_ids): array {
    return array_column(
      (array) Api4\Contact::get(FALSE)
        ->addSelect('id', 'hubspot_sync.last_sync_date')
        ->addWhere('id', 'IN', $contact_ids)
        ->addWhere('hubspot_sync.sync_status:name', '=', 'successful')
        ->execute(),
      'hubspot_sync.last_sync_date',
      'id'
    );
  }

  /**
   * @return int[]
   *   Those of $contact_ids with local subscription changes after $since.
   */
  private static function contactsWithSubscriptionChangesSince(array $contact_ids, string $since): array {
    return array_unique(
      Api4\SubscriptionContact::get(FALSE)
        ->addSelect('contact_id')
        ->addWhere('contact_id', 'IN', $contact_ids)
        ->addWhere('modified_date', '>', $since)
        ->execute()
        ->column('contact_id')
    );
  }

  /**
   * Drops batch items whose contact is no longer synced successfully, or was synced again since being queued.
   */
  private static function skipChangedSinceQueued(array $batch): array {
    $sync_dates = self::successfulSyncDates(array_column($batch, 'civicrm_id'));

    return array_filter(
      $batch,
      fn ($item) => array_key_exists($item['civicrm_id'], $sync_dates)
        && $sync_dates[$item['civicrm_id']] === $item['last_sync_date']
    );
  }

}
