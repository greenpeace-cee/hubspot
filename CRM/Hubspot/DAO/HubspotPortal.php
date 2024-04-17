<?php

/**
 * @package CRM
 * @copyright CiviCRM LLC https://civicrm.org/licensing
 *
 * Generated from hubspot/xml/schema/CRM/Hubspot/001-HubspotPortal.xml
 * DO NOT EDIT.  Generated by CRM_Core_CodeGen
 * (GenCodeChecksum:73e2d992d53ef63fa3798aefe2965fac)
 */
use CRM_Hubspot_ExtensionUtil as E;

/**
 * Database access object for the HubspotPortal entity.
 */
class CRM_Hubspot_DAO_HubspotPortal extends CRM_Core_DAO {
  const EXT = E::LONG_NAME;
  const TABLE_ADDED = '';

  /**
   * Static instance to hold the table name.
   *
   * @var string
   */
  public static $_tableName = 'civicrm_hubspot_portal';

  /**
   * Should CiviCRM log any modifications to this table in the civicrm_log table.
   *
   * @var bool
   */
  public static $_log = TRUE;

  /**
   * Unique HubspotPortal ID
   *
   * @var int|string|null
   *   (SQL type: int unsigned)
   *   Note that values will be retrieved from the database as a string.
   */
  public $id;

  /**
   * Unique identifier of portal in HubSpot
   *
   * @var int|string
   *   (SQL type: int unsigned)
   *   Note that values will be retrieved from the database as a string.
   */
  public $hubspot_portal_identifier;

  /**
   * Portal name
   *
   * @var string
   *   (SQL type: varchar(255))
   *   Note that values will be retrieved from the database as a string.
   */
  public $name;

  /**
   * API Key
   *
   * @var string
   *   (SQL type: varchar(255))
   *   Note that values will be retrieved from the database as a string.
   */
  public $api_key;

  /**
   * Configuration for this portal
   *
   * @var string|null
   *   (SQL type: text)
   *   Note that values will be retrieved from the database as a string.
   */
  public $config;

  /**
   * @var string
   *   (SQL type: timestamp)
   *   Note that values will be retrieved from the database as a string.
   */
  public $created_date;

  /**
   * @var string|null
   *   (SQL type: timestamp)
   *   Note that values will be retrieved from the database as a string.
   */
  public $modified_date;

  /**
   * Class constructor.
   */
  public function __construct() {
    $this->__table = 'civicrm_hubspot_portal';
    parent::__construct();
  }

  /**
   * Returns localized title of this entity.
   *
   * @param bool $plural
   *   Whether to return the plural version of the title.
   */
  public static function getEntityTitle($plural = FALSE) {
    return $plural ? E::ts('Hubspot Portals') : E::ts('Hubspot Portal');
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
          'title' => E::ts('ID'),
          'description' => E::ts('Unique HubspotPortal ID'),
          'required' => TRUE,
          'usage' => [
            'import' => FALSE,
            'export' => FALSE,
            'duplicate_matching' => FALSE,
            'token' => FALSE,
          ],
          'where' => 'civicrm_hubspot_portal.id',
          'table_name' => 'civicrm_hubspot_portal',
          'entity' => 'HubspotPortal',
          'bao' => 'CRM_Hubspot_DAO_HubspotPortal',
          'localizable' => 0,
          'html' => [
            'type' => 'Number',
          ],
          'readonly' => TRUE,
          'add' => NULL,
        ],
        'hubspot_portal_identifier' => [
          'name' => 'hubspot_portal_identifier',
          'type' => CRM_Utils_Type::T_INT,
          'title' => E::ts('HubSpot Portal ID'),
          'description' => E::ts('Unique identifier of portal in HubSpot'),
          'required' => TRUE,
          'usage' => [
            'import' => FALSE,
            'export' => FALSE,
            'duplicate_matching' => FALSE,
            'token' => FALSE,
          ],
          'where' => 'civicrm_hubspot_portal.hubspot_portal_identifier',
          'table_name' => 'civicrm_hubspot_portal',
          'entity' => 'HubspotPortal',
          'bao' => 'CRM_Hubspot_DAO_HubspotPortal',
          'localizable' => 0,
          'html' => [
            'type' => 'Number',
          ],
          'add' => NULL,
        ],
        'name' => [
          'name' => 'name',
          'type' => CRM_Utils_Type::T_STRING,
          'title' => E::ts('Portal Name'),
          'description' => E::ts('Portal name'),
          'required' => TRUE,
          'maxlength' => 255,
          'size' => CRM_Utils_Type::HUGE,
          'usage' => [
            'import' => FALSE,
            'export' => FALSE,
            'duplicate_matching' => FALSE,
            'token' => FALSE,
          ],
          'where' => 'civicrm_hubspot_portal.name',
          'table_name' => 'civicrm_hubspot_portal',
          'entity' => 'HubspotPortal',
          'bao' => 'CRM_Hubspot_DAO_HubspotPortal',
          'localizable' => 0,
          'html' => [
            'type' => 'Text',
          ],
          'add' => NULL,
        ],
        'api_key' => [
          'name' => 'api_key',
          'type' => CRM_Utils_Type::T_STRING,
          'title' => E::ts('API Key'),
          'description' => E::ts('API Key'),
          'required' => TRUE,
          'maxlength' => 255,
          'size' => CRM_Utils_Type::HUGE,
          'usage' => [
            'import' => FALSE,
            'export' => FALSE,
            'duplicate_matching' => FALSE,
            'token' => FALSE,
          ],
          'where' => 'civicrm_hubspot_portal.api_key',
          'table_name' => 'civicrm_hubspot_portal',
          'entity' => 'HubspotPortal',
          'bao' => 'CRM_Hubspot_DAO_HubspotPortal',
          'localizable' => 0,
          'html' => [
            'type' => 'Text',
          ],
          'add' => NULL,
        ],
        'config' => [
          'name' => 'config',
          'type' => CRM_Utils_Type::T_TEXT,
          'title' => E::ts('Config (JSON)'),
          'description' => E::ts('Configuration for this portal'),
          'usage' => [
            'import' => FALSE,
            'export' => FALSE,
            'duplicate_matching' => FALSE,
            'token' => FALSE,
          ],
          'where' => 'civicrm_hubspot_portal.config',
          'table_name' => 'civicrm_hubspot_portal',
          'entity' => 'HubspotPortal',
          'bao' => 'CRM_Hubspot_DAO_HubspotPortal',
          'localizable' => 0,
          'serialize' => self::SERIALIZE_JSON,
          'html' => [
            'type' => 'Text',
          ],
          'add' => NULL,
        ],
        'created_date' => [
          'name' => 'created_date',
          'type' => CRM_Utils_Type::T_TIMESTAMP,
          'title' => E::ts('Created Date'),
          'required' => TRUE,
          'usage' => [
            'import' => FALSE,
            'export' => FALSE,
            'duplicate_matching' => FALSE,
            'token' => FALSE,
          ],
          'where' => 'civicrm_hubspot_portal.created_date',
          'default' => 'CURRENT_TIMESTAMP',
          'table_name' => 'civicrm_hubspot_portal',
          'entity' => 'HubspotPortal',
          'bao' => 'CRM_Hubspot_DAO_HubspotPortal',
          'localizable' => 0,
          'readonly' => TRUE,
          'add' => NULL,
        ],
        'modified_date' => [
          'name' => 'modified_date',
          'type' => CRM_Utils_Type::T_TIMESTAMP,
          'title' => E::ts('Modified Date'),
          'usage' => [
            'import' => FALSE,
            'export' => FALSE,
            'duplicate_matching' => FALSE,
            'token' => FALSE,
          ],
          'where' => 'civicrm_hubspot_portal.modified_date',
          'default' => 'CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
          'table_name' => 'civicrm_hubspot_portal',
          'entity' => 'HubspotPortal',
          'bao' => 'CRM_Hubspot_DAO_HubspotPortal',
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
    $r = CRM_Core_DAO_AllCoreTables::getImports(__CLASS__, 'hubspot_portal', $prefix, []);
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
    $r = CRM_Core_DAO_AllCoreTables::getExports(__CLASS__, 'hubspot_portal', $prefix, []);
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
      'UI_hubspot_portal_identifier' => [
        'name' => 'UI_hubspot_portal_identifier',
        'field' => [
          0 => 'hubspot_portal_identifier',
        ],
        'localizable' => FALSE,
        'unique' => TRUE,
        'sig' => 'civicrm_hubspot_portal::1::hubspot_portal_identifier',
      ],
    ];
    return ($localize && !empty($indices)) ? CRM_Core_DAO_AllCoreTables::multilingualize(__CLASS__, $indices) : $indices;
  }

}
