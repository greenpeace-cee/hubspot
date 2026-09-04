<?php

namespace Civi\Api4\Action\Hubspot;

use Civi\Api4;
use CRM_Hubspot_BatchEventCreator as BatchEventCreator;
use CRM_Hubspot_UUIDTrait as UUIDTrait;
use DateTimeImmutable;
use Generator;

/**
 * Sync events to HubSpot
 */
class SyncEvents extends Api4\Generic\DAOGetAction {

  use UUIDTrait;

  public function _run(Api4\Generic\Result $result) {
    $batch_processor = new BatchEventCreator([
      'queue_name' => 'hubspot-sync-create-events',
      'batch_size' => 500,
    ]);

    $event_types = self::getEventTypes();

    foreach ($event_types as $event_type) {
      foreach (self::getEvents($event_type) as $event) {
        $batch_processor->add($event);
      }
    }

    $batch_processor->flush();
  }

  private static function getEvents(string $event_type): Generator {
    $custom_group = Api4\CustomGroup::get(FALSE)
      ->addSelect('name')
      ->addWhere('extends', '=', 'HubspotEvent')
      ->addWhere('extends_entity_column_value:name', 'CONTAINS', $event_type)
      ->setLimit(1)
      ->execute()
      ->first()['name'];

    $event_id_offset = 0;

    while (TRUE) {
      $events_result = Api4\HubspotEvent::get(FALSE)
        ->addSelect(
          'contact_id.hubspot_sync.email',
          'contact_id.hubspot_sync.hubspot_id',
          'created_date',
          "$custom_group.*"
        )
        ->addWhere('event_type_id:name', '=', $event_type)
        ->addWhere('hubspot_id', 'IS NULL')
        ->addWhere('contact_id.hubspot_sync.hubspot_id', 'IS NOT NULL')
        ->addWhere('id', '>', $event_id_offset)
        ->addOrderBy('id', 'ASC')
        ->setLimit(10)
        ->execute();

      if ($events_result->countFetched() < 1) return;

      $event_id_offset = (int) $events_result->last()['id'];

      foreach ($events_result as $event) {
        $event_properties = [ 'civicrm_id' => $event['id'] ];

        foreach ($event as $key => $value) {
          if (is_null($value)) continue;

          $matches = [];

          if (!preg_match("/^$custom_group\.(\w+)$/", $key, $matches)) continue;

          $event_properties[$matches[1]] = $value;
        }

        yield [
          'eventName'  => $event_type,
          'properties' => $event_properties,
          'email'      => $event['contact_id.hubspot_sync.email'] ?? "",
          'objectId'   => $event['contact_id.hubspot_sync.hubspot_id'],
          'occurredAt' => (new DateTimeImmutable($event['created_date']))->format('c'),
          'uuid'       => self::generateUUID(),
        ];
      }
    }
  }

  private static function getEventTypes(): array {
    return array_map(
      fn ($event_type) => $event_type['name'],
      (array) Api4\OptionValue::get(FALSE)
        ->addSelect('name')
        ->addWhere('option_group_id:name', '=', 'hubspot_event_type')
        ->execute()
    );
  }

}
