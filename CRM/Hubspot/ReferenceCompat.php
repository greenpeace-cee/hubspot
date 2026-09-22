<?php
declare(strict_types = 1);

/**
 * Registers foreign-key references for entities declared in schema/*.entityType.php.
 *
 * CiviCRM >= 5.74 derives these from field metadata in
 * CRM_Core_DAO::getReferenceColumns(). On older versions (including the 5.69
 * ESR) that only happens for DAOs which hard-code their own links, which the
 * generated stub DAOs do not. Without them, anything driven by references -
 * Search Kit joins, findReferences(), delete cascades - cannot see our FKs,
 * and Search Kit fatals on bridge entities such as SubscriptionContact.
 *
 * Wired up via the 'links_callback' key of the entityType declarations.
 */
final class CRM_Hubspot_ReferenceCompat {

  /**
   * @param string $className
   *   DAO class of the entity being described.
   * @param CRM_Core_Reference_Interface[] $links
   *   References collected so far, appended to in place.
   */
  public static function links(string $className, array &$links): void {
    $existing = [];
    foreach ($links as $link) {
      $existing[$link->getReferenceKey()] = TRUE;
    }

    foreach ($className::fields() as $field) {
      // Already provided by core (5.74+) or by another callback.
      if (isset($existing[$field['name']])) {
        continue;
      }
      if (!empty($field['DFKEntityColumn'])) {
        $links[] = new CRM_Core_Reference_Dynamic(
          $className::getTableName(),
          $field['name'],
          NULL,
          $field['FKColumnName'] ?? 'id',
          $field['DFKEntityColumn']
        );
      }
      elseif (!empty($field['FKClassName'])) {
        $links[] = new CRM_Core_Reference_Basic(
          $className::getTableName(),
          $field['name'],
          CRM_Core_DAO_AllCoreTables::getTableForClass($field['FKClassName']),
          $field['FKColumnName'] ?? 'id'
        );
      }
    }
  }

}
