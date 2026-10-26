<?php

namespace Civi\Api4\Action\Hubspot;

use Civi\Api4;
use CRM_Hubspot_BatchContactAuditor as BatchContactAuditor;
use CRM_Hubspot_SyncGuard;

/**
 * Compare a random sample of contacts with their HubSpot records and log differences.
 *
 * Expected values are the payload of each contact's last successful sync, so local changes that are
 * still pending, or properties a sync deliberately left out (e.g. email conflicts), don't show up as differences.
 * `where`/`join` pick the contacts to sample from. `select` limits the check to those properties
 * (matched by alias, so the select of syncContacts can be reused); without it, all synced properties are checked.
 */
class AuditContacts extends Api4\Generic\DAOGetAction {

  use \CRM_Hubspot_AuditSampleTrait;
  use \CRM_Hubspot_AuditSyncStateTrait;

  const QUEUE = 'hubspot-audit-contacts';

  public function _run(Api4\Generic\Result $result) {
    $result['scheduled'] = 0;

    CRM_Hubspot_SyncGuard::run('audit-contacts', [self::QUEUE], fn () => $this->audit($result));
  }

  private function audit(Api4\Generic\Result $result): void {
    $properties = array_map(fn ($field) => preg_replace('/^.* AS /', '', $field), $this->select);

    // HubSpot's contact batch read accepts up to 100 IDs
    $contact_auditor = new BatchContactAuditor(['queue_name' => self::QUEUE, 'batch_size' => 100]);

    $where = array_merge($this->where, [
      ['hubspot_sync.hubspot_id', 'IS NOT EMPTY'],
      ['hubspot_sync.sync_status:name', '=', 'successful'],
    ]);

    foreach ($this->sampleContactIds($where, $this->join) as $contact_ids) {
      $contacts = Api4\Contact::get(FALSE)
        ->addSelect('id', 'hubspot_sync.hubspot_id', 'hubspot_sync.last_sync_date', 'hubspot_sync.last_sync_payload')
        ->addWhere('id', 'IN', $contact_ids)
        ->execute();

      foreach ($contacts as $contact) {
        $payload = json_decode($contact['hubspot_sync.last_sync_payload'] ?? '', TRUE) ?? [];

        $contact_auditor->add([
          'id'             => $contact['hubspot_sync.hubspot_id'],
          'civicrm_id'     => $contact['id'],
          'last_sync_date' => $contact['hubspot_sync.last_sync_date'],
          'properties'     => empty($properties) ? $payload : array_intersect_key($payload, array_flip($properties)),
        ]);

        $result['scheduled']++;
      }
    }

    $contact_auditor->flush();
  }

}
