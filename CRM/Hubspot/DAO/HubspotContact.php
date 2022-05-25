<?php

/**
 * @package CRM
 * @copyright CiviCRM LLC https://civicrm.org/licensing
 *
 * Generated from hubspot/xml/schema/CRM/Hubspot/002-HubspotContact.xml
 * DO NOT EDIT.  Generated by CRM_Core_CodeGen
 * (GenCodeChecksum:b177af70dfc4f1ad1fe9558db36e90d6)
 */
use CRM_Hubspot_ExtensionUtil as E;

/**
 * Database access object for the HubspotContact entity.
 */
class CRM_Hubspot_DAO_HubspotContact extends CRM_Core_DAO {
  const EXT = E::LONG_NAME;
  const TABLE_ADDED = '';

  /**
   * Static instance to hold the table name.
   *
   * @var string
   */
  public static $_tableName = 'civicrm_hubspot_contact';

  /**
   * Should CiviCRM log any modifications to this table in the civicrm_log table.
   *
   * @var bool
   */
  public static $_log = TRUE;

  /**
   * Unique HubspotContact ID
   *
   * @var int
   */
  public $id;

  /**
   * FK to Contact
   *
   * @var int
   */
  public $contact_id;

  /**
   * FK to HubSpotPortal
   *
   * @var int
   */
  public $hubspot_portal_id;

  /**
   * Unique identifier of contact in HubSpot
   *
   * @var int
   */
  public $hubspot_vid;

  /**
   * @var longtext
   */
  public $last_state;

  /**
   * @var longtext
   */
  public $custom_data;

  /**
   * Is this record dirty?
   *
   * @var bool
   */
  public $is_dirty;

  /**
   * @var timestamp
   */
  public $created_date;

  /**
   * @var timestamp
   */
  public $modified_date;

  /**
   * Class constructor.
   */
  public function __construct() {
    $this->__table = 'civicrm_hubspot_contact';
    parent::__construct();
  }

  /**
   * Returns localized title of this entity.
   *
   * @param bool $plural
   *   Whether to return the plural version of the title.
   */
  public static function getEntityTitle($plural = FALSE) {
    return $plural ? E::ts('Hubspot Contacts') : E::ts('Hubspot Contact');
  }

  /**
   * Returns foreign keys and entity references.
   *
   * @return array
   *   [CRM_Core_Reference_Interface]
   */
  public static function getReferenceColumns() {
    if (!isset(Civi::$statics[__CLASS__]['links'])) {
      Civi::$statics[__CLASS__]['links'] = static::createReferenceColumns(__CLASS__);
      Civi::$statics[__CLASS__]['links'][] = new CRM_Core_Reference_Basic(self::getTableName(), 'contact_id', 'civicrm_contact', 'id');
      Civi::$statics[__CLASS__]['links'][] = new CRM_Core_Reference_Basic(self::getTableName(), 'hubspot_portal_id', 'civicrm_hubspot_portal', 'id');
      CRM_Core_DAO_AllCoreTables::invoke(__CLASS__, 'links_callback', Civi::$statics[__CLASS__]['links']);
    }
    return Civi::$statics[__CLASS__]['links'];
  }

  /**
   * Returns all the column names of this table
   *
   * @return array
   */
  public static function &fields() {
    if (!isset(Civi::$statics[__CLASS__]['fields'])) {
      Civi::$statics[__CLASS__]['fields'] = [
        'id' => [
          'name' => 'id',
          'type' => CRM_Utils_Type::T_INT,
          'description' => E::ts('Unique HubspotContact ID'),
          'required' => TRUE,
          'where' => 'civicrm_hubspot_contact.id',
          'table_name' => 'civicrm_hubspot_contact',
          'entity' => 'HubspotContact',
          'bao' => 'CRM_Hubspot_DAO_HubspotContact',
          'localizable' => 0,
          'html' => [
            'type' => 'Number',
          ],
          'readonly' => TRUE,
          'add' => NULL,
        ],
        'contact_id' => [
          'name' => 'contact_id',
          'type' => CRM_Utils_Type::T_INT,
          'description' => E::ts('FK to Contact'),
          'required' => TRUE,
          'where' => 'civicrm_hubspot_contact.contact_id',
          'table_name' => 'civicrm_hubspot_contact',
          'entity' => 'HubspotContact',
          'bao' => 'CRM_Hubspot_DAO_HubspotContact',
          'localizable' => 0,
          'FKClassName' => 'CRM_Contact_DAO_Contact',
          'add' => NULL,
        ],
        'hubspot_portal_id' => [
          'name' => 'hubspot_portal_id',
          'type' => CRM_Utils_Type::T_INT,
          'description' => E::ts('FK to HubSpotPortal'),
          'where' => 'civicrm_hubspot_contact.hubspot_portal_id',
          'table_name' => 'civicrm_hubspot_contact',
          'entity' => 'HubspotContact',
          'bao' => 'CRM_Hubspot_DAO_HubspotContact',
          'localizable' => 0,
          'FKClassName' => 'CRM_Hubspot_DAO_HubspotPortal',
          'add' => NULL,
        ],
        'hubspot_vid' => [
          'name' => 'hubspot_vid',
          'type' => CRM_Utils_Type::T_INT,
          'title' => E::ts('HubSpot VID'),
          'description' => E::ts('Unique identifier of contact in HubSpot'),
          'required' => FALSE,
          'where' => 'civicrm_hubspot_contact.hubspot_vid',
          'table_name' => 'civicrm_hubspot_contact',
          'entity' => 'HubspotContact',
          'bao' => 'CRM_Hubspot_DAO_HubspotContact',
          'localizable' => 0,
          'html' => [
            'type' => 'Number',
          ],
          'add' => NULL,
        ],
        'last_state' => [
          'name' => 'last_state',
          'type' => CRM_Utils_Type::T_LONGTEXT,
          'title' => E::ts('Last State (JSON)'),
          'required' => FALSE,
          'where' => 'civicrm_hubspot_contact.last_state',
          'table_name' => 'civicrm_hubspot_contact',
          'entity' => 'HubspotContact',
          'bao' => 'CRM_Hubspot_DAO_HubspotContact',
          'localizable' => 0,
          'serialize' => self::SERIALIZE_JSON,
          'html' => [
            'type' => 'Text',
          ],
          'add' => NULL,
        ],
        'custom_data' => [
          'name' => 'custom_data',
          'type' => CRM_Utils_Type::T_LONGTEXT,
          'title' => E::ts('Custom Data (JSON)'),
          'required' => FALSE,
          'where' => 'civicrm_hubspot_contact.custom_data',
          'table_name' => 'civicrm_hubspot_contact',
          'entity' => 'HubspotContact',
          'bao' => 'CRM_Hubspot_DAO_HubspotContact',
          'localizable' => 0,
          'serialize' => self::SERIALIZE_JSON,
          'html' => [
            'type' => 'Text',
          ],
          'add' => NULL,
        ],
        'is_dirty' => [
          'name' => 'is_dirty',
          'type' => CRM_Utils_Type::T_BOOLEAN,
          'title' => E::ts('Dirty?'),
          'description' => E::ts('Is this record dirty?'),
          'required' => TRUE,
          'where' => 'civicrm_hubspot_contact.is_dirty',
          'default' => '0',
          'table_name' => 'civicrm_hubspot_contact',
          'entity' => 'HubspotContact',
          'bao' => 'CRM_Hubspot_DAO_HubspotContact',
          'localizable' => 0,
          'html' => [
            'type' => 'Checkbox',
          ],
          'add' => NULL,
        ],
        'created_date' => [
          'name' => 'created_date',
          'type' => CRM_Utils_Type::T_TIMESTAMP,
          'title' => E::ts('Created Date'),
          'required' => TRUE,
          'where' => 'civicrm_hubspot_contact.created_date',
          'default' => 'CURRENT_TIMESTAMP',
          'table_name' => 'civicrm_hubspot_contact',
          'entity' => 'HubspotContact',
          'bao' => 'CRM_Hubspot_DAO_HubspotContact',
          'localizable' => 0,
          'readonly' => TRUE,
          'add' => NULL,
        ],
        'modified_date' => [
          'name' => 'modified_date',
          'type' => CRM_Utils_Type::T_TIMESTAMP,
          'title' => E::ts('Modified Date'),
          'where' => 'civicrm_hubspot_contact.modified_date',
          'default' => 'CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
          'table_name' => 'civicrm_hubspot_contact',
          'entity' => 'HubspotContact',
          'bao' => 'CRM_Hubspot_DAO_HubspotContact',
          'localizable' => 0,
          'readonly' => TRUE,
          'add' => NULL,
        ],
      ];
      CRM_Core_DAO_AllCoreTables::invoke(__CLASS__, 'fields_callback', Civi::$statics[__CLASS__]['fields']);
    }
    return Civi::$statics[__CLASS__]['fields'];
  }

  /**
   * Return a mapping from field-name to the corresponding key (as used in fields()).
   *
   * @return array
   *   Array(string $name => string $uniqueName).
   */
  public static function &fieldKeys() {
    if (!isset(Civi::$statics[__CLASS__]['fieldKeys'])) {
      Civi::$statics[__CLASS__]['fieldKeys'] = array_flip(CRM_Utils_Array::collect('name', self::fields()));
    }
    return Civi::$statics[__CLASS__]['fieldKeys'];
  }

  /**
   * Returns the names of this table
   *
   * @return string
   */
  public static function getTableName() {
    return self::$_tableName;
  }

  /**
   * Returns if this table needs to be logged
   *
   * @return bool
   */
  public function getLog() {
    return self::$_log;
  }

  /**
   * Returns the list of fields that can be imported
   *
   * @param bool $prefix
   *
   * @return array
   */
  public static function &import($prefix = FALSE) {
    $r = CRM_Core_DAO_AllCoreTables::getImports(__CLASS__, 'hubspot_contact', $prefix, []);
    return $r;
  }

  /**
   * Returns the list of fields that can be exported
   *
   * @param bool $prefix
   *
   * @return array
   */
  public static function &export($prefix = FALSE) {
    $r = CRM_Core_DAO_AllCoreTables::getExports(__CLASS__, 'hubspot_contact', $prefix, []);
    return $r;
  }

  /**
   * Returns the list of indices
   *
   * @param bool $localize
   *
   * @return array
   */
  public static function indices($localize = TRUE) {
    $indices = [
      'UI_contact_id_hubspot_portal_id' => [
        'name' => 'UI_contact_id_hubspot_portal_id',
        'field' => [
          0 => 'contact_id',
          1 => 'hubspot_portal_id',
        ],
        'localizable' => FALSE,
        'unique' => TRUE,
        'sig' => 'civicrm_hubspot_contact::1::contact_id::hubspot_portal_id',
      ],
      'UI_hubspot_vid_hubspot_portal_id' => [
        'name' => 'UI_hubspot_vid_hubspot_portal_id',
        'field' => [
          0 => 'hubspot_vid',
          1 => 'hubspot_portal_id',
        ],
        'localizable' => FALSE,
        'unique' => TRUE,
        'sig' => 'civicrm_hubspot_contact::1::hubspot_vid::hubspot_portal_id',
      ],
      'index_hubspot_vid' => [
        'name' => 'index_hubspot_vid',
        'field' => [
          0 => 'hubspot_vid',
        ],
        'localizable' => FALSE,
        'sig' => 'civicrm_hubspot_contact::0::hubspot_vid',
      ],
    ];
    return ($localize && !empty($indices)) ? CRM_Core_DAO_AllCoreTables::multilingualize(__CLASS__, $indices) : $indices;
  }

}