<?php

namespace Civi\Api4\Action\Hubspot;

use GuzzleHttp\Psr7\Response;
use CRM_Hubspot_UUIDTrait as UUIDTrait;

class MockResponses {

  use UUIDTrait;

  const PORTAL_ID = 1234;

  public static function generateHubspotId(): string {
    return (string) random_int(pow(10, 9), pow(10, 12));
  }

  public static function generateRandomString(int $length): string {
    return substr(strtr(base64_encode(random_bytes($length)), '+/', '-_'), 0, $length);
  }

  public static function toTimestampMilliseconds($datetime): int {
    if (is_string($datetime)) {
      $datetime = new DateTimeImmutable($datetime);
    }

    return $datetime->getTimestamp() * 1000 + ((int) $datetime->format('v'));
  }

  public static function batchCreateContacts(int $status_code, array $parameters = []): Response {
    switch ($status_code) {
      case 201: {
        return new Response(
          201,
          [ 'Content-Type' => 'application/json' ],
          json_encode([
            'results' => array_map(
              fn ($contact) => [
                'id'         => MockResponses::generateHubspotId(),
                'properties' => $contact,
              ],
              $parameters['contacts'] ?? []
            ),
          ])
        );
      }

      case 400: {
        return new Response(
          400,
          [ 'Content-Type' => 'application/json' ],
          json_encode([
            'status'   => 'error',
            'message'  => '...', // @TODO: Insert error message
            'category' => 'VALIDATION_ERROR',
          ])
        );
      }

      case 429: {
        return new Response(
          429,
          [ 'Content-Type' => 'application/json' ],
          json_encode([
            'status'    => 'error',
            'message'   => 'You have reached your ten_secondly_rolling limit.',
            'errorType' => 'RATE_LIMIT',
          ])
        );
      }

      default: {
        return new Response($status_code);
      }
    }
  }

  public static function batchGetContacts(int $status_code, array $parameters = []): Response {
    switch ($status_code) {
      case 200: {
        return new Response(
          200,
          [ 'Content-Type' => 'application/json' ],
          json_encode([
            'status'      => 'COMPLETE',
            'startedAt'   => date('Y-m-d\\TH:i:s.vp'),
            'completedAt' => date('Y-m-d\\TH:i:s.vp'),
            'results'     => array_map(
              fn ($contact) => [
                'id'         => $contact['hubspot_id'],
                'properties' => [
                  'hs_object_id'      => $contact['hubspot_id'],
                  'email'             => $contact['email'],
                  'createdate'        => date('Y-m-d\\TH:i:s.vp', strtotime('1 month ago')),
                  'lastmodified_date' => date('Y-m-d\\TH:i:s.vp', strtotime('2 weeks ago')),
                  'createdAt'         => date('Y-m-d\\TH:i:s.vp', strtotime('1 month ago')),
                  'updatedAt'         => date('Y-m-d\\TH:i:s.vp', strtotime('2 weeks ago')),
                  'archived'          => FALSE,
                  'url'               => "https://api.hubapi.test/148955603/record/0-1/{$contact['hubspot_id']}",
                ],
              ],
              $parameters['contacts']
            ),
          ])
        );
      }

      default: {
        return new Response($status_code);
      }
    }
  }

  public static function batchUpdateContacts(int $status_code, array $parameters = []): Response {
    switch ($status_code) {
      case 200: {
        return new Response(
          200,
          [ 'Content-Type' => 'application/json' ],
          json_encode([
            'results' => array_map(
              fn ($id, $properties) => [ 'id' => $id, 'properties' => $properties ],
              $parameters['ids'] ?? [],
              $parameters['properties'] ?? []
            ),
          ])
        );
      }

      case 207: {
        return new Response(
          207,
          [ 'Content-Type' => 'application/json' ],
          json_encode([
            'status'    => 'COMPLETE',
            'results'   => [],
            'numErrors' => 1,
            'errors'    => [
              [
                'status'   => 'error',
                'category' => 'OBJECT_NOT_FOUND',
                'message'  => 'Could not get some CONTACT objects, they may be deleted or not exist. Check that ids are valid.',
                'context'  => [ 'ids' => $parameters['ids'] ?? [] ],
              ],
            ],
          ])
        );
      }

      case 400: {
        return new Response(
          400,
          [ 'Content-Type' => 'application/json' ],
          json_encode([
            'status'   => 'error',
            'message'  => '...', // @TODO: Insert error message
            'category' => 'VALIDATION_ERROR',
          ])
        );
      }

      default: {
        return new Response($status_code);
      }
    }
  }

  public static function batchUpdateSubscriptionStatus(int $status_code): Response {
    switch ($status_code) {
      default: {
        return new Response($status_code);
      }
    }
  }

  public static function createContact(int $status_code, array $parameters = []): Response {
    switch ($status_code) {
      case 201: {
        return new Response(
          201,
          [ 'Content-Type' => 'application/json' ],
          json_encode([
            'id'         => $parameters['id'],
            'properties' => $parameters['properties'],
          ])
        );
      }

      default: {
        return new Response($status_code);
      }
    }
  }

  public static function getContactByEmail(int $status_code, array $parameters = []): Response {
    switch ($status_code) {
      case 200: {
        return new Response(
          200,
          [ 'Content-Type' => 'application/json' ],
          json_encode([
            'id'         => $parameters['id'],
            'properties' => $parameters['properties'],
          ])
        );
      }

      default: {
        return new Response($status_code);
      }
    }
  }

  public static function getEvents(int $status_code, array $parameters = []): Response {
    switch ($status_code) {
      case 200: {
        return new Response(
          200,
          [ 'Content-Type' => 'application/json' ],
          json_encode([
            'paging'  => [],
            'results' => array_map(
              fn ($event) => [
                'id'         => $event['id'] ?? self::generateUUID(),
                'eventType'  => $event['type'],
                'objectType' => 'Contact',
                'objectId'   => $event['object_id'],
                'occurredAt' => date('Y-m-d\\TH:i:s.vp', $event['timestamp']),
                'properties' => [
                  'hs_user_agent'                        => '',
                  'hs_recipient_type'                    => 'UNKNOWN',
                  'hs_historical_contact_lifecyclestage' => 'lead',
                  'hs_device_type'                       => '',
                  'hs_app_id'                            => '0',
                  'hs_type'                              => 'UPDATED_SUBSCRIPTION',
                  'hs_is_marketing_email'                => 'false',
                  'hs_app_name'                          => '',
                  'hs_email_campaign_id'                 => '0',
                  'hs_subscription_unsubscribe'          => $event['change'] === 'SUBSCRIBED' ? 'false' : 'true',
                ],
              ],
              $parameters['events'] ?? []
            ),
          ]),
        );
      }

      default: {
        return new Response($status_code);
      }
    }
  }

  public static function getSubscriptionsTimeline(int $status_code, array $parameters = []): Response {
    switch ($status_code) {
      case 200: {
        return new Response(
          200,
          [ 'Content-Type' => 'application/json' ],
          json_encode([
            'hasMore' => FALSE,
            'offset' => self::generateRandomString(20),
            'timeline' => array_map(
              fn ($item) => [
                'timestamp'         => (float) date('U.v', $item['timestamp']) * 1000,
                'recipient'         => $item['email'],
                'normalizedEmailId' => self::generateUUID(),
                'portalId'          => self::PORTAL_ID,
                'changes' => [
                  [
                    'subscriptionId' => $item['subscription_id'],
                    'changeType'     => 'SUBSCRIPTION_STATUS',
                    'source'         => 'SOURCE_PUBLIC_API',
                    'change'         => $item['change'],
                    'causedByEvent'  => [
                      'id'      => $item['event_id'],
                      'created' => (float) date('U.v', $item['timestamp']) * 1000,
                    ],
                  ],
                ],
              ],
              $parameters['items'] ?? []
            )
          ]),
        );
      }

      default: {
        return new Response($status_code);
      }
    }
  }

  public static function updateContact(int $status_code, array $parameters = []): Response {
    switch ($status_code) {
      case 200: {
        return new Response(
          200,
          [ 'Content-Type' => 'application/json' ],
          json_encode([
            'id'         => $parameters['id'],
            'properties' => $parameters['properties'],
          ])
        );
      }

      default: {
        return new Response($status_code);
      }
    }
  }

}
