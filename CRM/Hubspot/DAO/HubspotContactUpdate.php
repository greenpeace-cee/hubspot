<?php

/**
 * @package CRM
 * @copyright CiviCRM LLC https://civicrm.org/licensing
 *
 * Generated from hubspot/xml/schema/CRM/Hubspot/003-HubspotContactUpdate.xml
 * DO NOT EDIT.  Generated by CRM_Core_CodeGen
 * (GenCodeChecksum:b459927eff399b110bc186c4e0508807)
 */
use CRM_Hubspot_ExtensionUtil as E;

/**
 * Database access object for the HubspotContactUpdate entity.
 */
class CRM_Hubspot_DAO_HubspotContactUpdate extends CRM_Core_DAO {
  const EXT = E::LONG_NAME;
  const TABLE_ADDED = '';

  /**
   * Static instance to hold the table name.
   *
   * @var string
   */
  public static $_tableName = 'civicrm_hubspot_contact_update';

  /**
   * Should CiviCRM log any modifications to this table in the civicrm_log table.
   *
   * @var bool
   */
  public static $_log = TRUE;

  /**
   * Unique HubspotContactUpdate ID
   *
   * @var int|string|null
   *   (SQL type: int unsigned)
   *   Note that values will be retrieved from the database as a string.
   */
  public $id;

  /**
   * FK to HubSpotPortal
   *
   * @var int|string
   *   (SQL type: int unsigned)
   *   Note that values will be retrieved from the database as a string.
   */
  public $hubspot_portal_id;

  /**
   * FK to HubSpotContact
   *
   * @var int|string|null
   *   (SQL type: int unsigned)
   *   Note that values will be retrieved from the database as a string.
   */
  public $hubspot_contact_id;

  /**
   * Unique identifier of contact in HubSpot. DECIMAL because core does not support BIGINT.
   *
   * @var float|string
   *   (SQL type: decimal(20,0))
   *   Note that values will be retrieved from the database as a string.
   */
  public $hubspot_vid;

  /**
   * Submission timestamp in HubSpot. DECIMAL because core does not support BIGINT.
   *
   * @var float|string
   *   (SQL type: decimal(20,0))
   *   Note that values will be retrieved from the database as a string.
   */
  public $hubspot_timestamp;

  /**
   * ID of update type
   *
   * @var int|string
   *   (SQL type: int unsigned)
   *   Note that values will be retrieved from the database as a string.
   */
  public $update_type_id;

  /**
   * @var string
   *   (SQL type: longtext)
   *   Note that values will be retrieved from the database as a string.
   */
  public $inbound_payload;

  /**
   * @var string
   *   (SQL type: longtext)
   *   Note that values will be retrieved from the database as a string.
   */
  public $update_data;

  /**
   * ID of update status
   *
   * @var int|string
   *   (SQL type: int unsigned)
   *   Note that values will be retrieved from the database as a string.
   */
  public $update_status_id;

  /**
   * @var string
   *   (SQL type: text)
   *   Note that values will be retrieved from the database as a string.
   */
  public $status_details;

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
    $this->__table = 'civicrm_hubspot_contact_update';
    parent::__construct();
  }

  /**
   * Returns localized title of this entity.
   *
   * @param bool $plural
   *   Whether to return the plural version of the title.
   */
  public static function getEntityTitle($plural = FALSE) {
    return $plural ? E::ts('Hubspot Contact Updates') : E::ts('Hubspot Contact Update');
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
      Civi::$statics[__CLASS__]['links'][] = new CRM_Core_Reference_Basic(self::getTableName(), 'hubspot_portal_id', 'civicrm_hubspot_portal', 'id');
      Civi::$statics[__CLASS__]['links'][] = new CRM_Core_Reference_Basic(self::getTableName(), 'hubspot_contact_id', 'civicrm_hubspot_contact', 'id');
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
          'title' => E::ts('ID'),
          'description' => E::ts('Unique HubspotContactUpdate ID'),
          'required' => TRUE,
          'usage' => [
            'import' => FALSE,
            'export' => FALSE,
            'duplicate_matching' => FALSE,
            'token' => FALSE,
          ],
          'where' => 'civicrm_hubspot_contact_update.id',
          'table_name' => 'civicrm_hubspot_contact_update',
          'entity' => 'HubspotContactUpdate',
          'bao' => 'CRM_Hubspot_DAO_HubspotContactUpdate',
          'localizable' => 0,
          'html' => [
            'type' => 'Number',
          ],
          'readonly' => TRUE,
          'add' => NULL,
        ],
        'hubspot_portal_id' => [
          'name' => 'hubspot_portal_id',
          'type' => CRM_Utils_Type::T_INT,
          'title' => E::ts('Hubspot Portal ID'),
          'description' => E::ts('FK to HubSpotPortal'),
          'required' => FALSE,
          'usage' => [
            'import' => FALSE,
            'export' => FALSE,
            'duplicate_matching' => FALSE,
            'token' => FALSE,
          ],
          'where' => 'civicrm_hubspot_contact_update.hubspot_portal_id',
          'table_name' => 'civicrm_hubspot_contact_update',
          'entity' => 'HubspotContactUpdate',
          'bao' => 'CRM_Hubspot_DAO_HubspotContactUpdate',
          'localizable' => 0,
          'FKClassName' => 'CRM_Hubspot_DAO_HubspotPortal',
          'add' => NULL,
        ],
        'hubspot_contact_id' => [
          'name' => 'hubspot_contact_id',
          'type' => CRM_Utils_Type::T_INT,
          'title' => E::ts('Hubspot Contact ID'),
          'description' => E::ts('FK to HubSpotContact'),
          'usage' => [
            'import' => FALSE,
            'export' => FALSE,
            'duplicate_matching' => FALSE,
            'token' => FALSE,
          ],
          'where' => 'civicrm_hubspot_contact_update.hubspot_contact_id',
          'table_name' => 'civicrm_hubspot_contact_update',
          'entity' => 'HubspotContactUpdate',
          'bao' => 'CRM_Hubspot_DAO_HubspotContactUpdate',
          'localizable' => 0,
          'FKClassName' => 'CRM_Hubspot_DAO_HubspotContact',
          'add' => NULL,
        ],
        'hubspot_vid' => [
          'name' => 'hubspot_vid',
          'type' => CRM_Utils_Type::T_MONEY,
          'title' => E::ts('HubSpot VID'),
          'description' => E::ts('Unique identifier of contact in HubSpot. DECIMAL because core does not support BIGINT.'),
          'required' => FALSE,
          'precision' => [
            20,
            0,
          ],
          'usage' => [
            'import' => FALSE,
            'export' => FALSE,
            'duplicate_matching' => FALSE,
            'token' => FALSE,
          ],
          'where' => 'civicrm_hubspot_contact_update.hubspot_vid',
          'table_name' => 'civicrm_hubspot_contact_update',
          'entity' => 'HubspotContactUpdate',
          'bao' => 'CRM_Hubspot_DAO_HubspotContactUpdate',
          'localizable' => 0,
          'html' => [
            'type' => 'Number',
          ],
          'add' => NULL,
        ],
        'hubspot_timestamp' => [
          'name' => 'hubspot_timestamp',
          'type' => CRM_Utils_Type::T_MONEY,
          'title' => E::ts('HubSpot Timestamp'),
          'description' => E::ts('Submission timestamp in HubSpot. DECIMAL because core does not support BIGINT.'),
          'required' => FALSE,
          'precision' => [
            20,
            0,
          ],
          'usage' => [
            'import' => FALSE,
            'export' => FALSE,
            'duplicate_matching' => FALSE,
            'token' => FALSE,
          ],
          'where' => 'civicrm_hubspot_contact_update.hubspot_timestamp',
          'table_name' => 'civicrm_hubspot_contact_update',
          'entity' => 'HubspotContactUpdate',
          'bao' => 'CRM_Hubspot_DAO_HubspotContactUpdate',
          'localizable' => 0,
          'html' => [
            'type' => 'Number',
          ],
          'add' => NULL,
        ],
        'update_type_id' => [
          'name' => 'update_type_id',
          'type' => CRM_Utils_Type::T_INT,
          'title' => E::ts('Update Type'),
          'description' => E::ts('ID of update type'),
          'required' => TRUE,
          'usage' => [
            'import' => FALSE,
            'export' => FALSE,
            'duplicate_matching' => FALSE,
            'token' => FALSE,
          ],
          'where' => 'civicrm_hubspot_contact_update.update_type_id',
          'table_name' => 'civicrm_hubspot_contact_update',
          'entity' => 'HubspotContactUpdate',
          'bao' => 'CRM_Hubspot_DAO_HubspotContactUpdate',
          'localizable' => 0,
          'html' => [
            'type' => 'Select',
          ],
          'pseudoconstant' => [
            'optionGroupName' => 'hubspot_update_type',
            'optionEditPath' => 'civicrm/admin/options/hubspot_update_type',
          ],
          'add' => NULL,
        ],
        'inbound_payload' => [
          'name' => 'inbound_payload',
          'type' => CRM_Utils_Type::T_LONGTEXT,
          'title' => E::ts('Inbound Payload (JSON)'),
          'required' => FALSE,
          'usage' => [
            'import' => FALSE,
            'export' => FALSE,
            'duplicate_matching' => FALSE,
            'token' => FALSE,
          ],
          'where' => 'civicrm_hubspot_contact_update.inbound_payload',
          'table_name' => 'civicrm_hubspot_contact_update',
          'entity' => 'HubspotContactUpdate',
          'bao' => 'CRM_Hubspot_DAO_HubspotContactUpdate',
          'localizable' => 0,
          'serialize' => self::SERIALIZE_JSON,
          'html' => [
            'type' => 'Text',
          ],
          'add' => NULL,
        ],
        'update_data' => [
          'name' => 'update_data',
          'type' => CRM_Utils_Type::T_LONGTEXT,
          'title' => E::ts('Update Data (JSON)'),
          'required' => FALSE,
          'usage' => [
            'import' => FALSE,
            'export' => FALSE,
            'duplicate_matching' => FALSE,
            'token' => FALSE,
          ],
          'where' => 'civicrm_hubspot_contact_update.update_data',
          'table_name' => 'civicrm_hubspot_contact_update',
          'entity' => 'HubspotContactUpdate',
          'bao' => 'CRM_Hubspot_DAO_HubspotContactUpdate',
          'localizable' => 0,
          'serialize' => self::SERIALIZE_JSON,
          'html' => [
            'type' => 'Text',
          ],
          'add' => NULL,
        ],
        'update_status_id' => [
          'name' => 'update_status_id',
          'type' => CRM_Utils_Type::T_INT,
          'title' => E::ts('Update Status'),
          'description' => E::ts('ID of update status'),
          'required' => TRUE,
          'usage' => [
            'import' => FALSE,
            'export' => FALSE,
            'duplicate_matching' => FALSE,
            'token' => FALSE,
          ],
          'where' => 'civicrm_hubspot_contact_update.update_status_id',
          'table_name' => 'civicrm_hubspot_contact_update',
          'entity' => 'HubspotContactUpdate',
          'bao' => 'CRM_Hubspot_DAO_HubspotContactUpdate',
          'localizable' => 0,
          'html' => [
            'type' => 'Select',
          ],
          'pseudoconstant' => [
            'optionGroupName' => 'hubspot_update_status',
            'optionEditPath' => 'civicrm/admin/options/hubspot_update_status',
          ],
          'add' => NULL,
        ],
        'status_details' => [
          'name' => 'status_details',
          'type' => CRM_Utils_Type::T_TEXT,
          'title' => E::ts('Status Details'),
          'required' => FALSE,
          'usage' => [
            'import' => FALSE,
            'export' => FALSE,
            'duplicate_matching' => FALSE,
            'token' => FALSE,
          ],
          'where' => 'civicrm_hubspot_contact_update.status_details',
          'table_name' => 'civicrm_hubspot_contact_update',
          'entity' => 'HubspotContactUpdate',
          'bao' => 'CRM_Hubspot_DAO_HubspotContactUpdate',
          'localizable' => 0,
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
          'where' => 'civicrm_hubspot_contact_update.created_date',
          'default' => 'CURRENT_TIMESTAMP',
          'table_name' => 'civicrm_hubspot_contact_update',
          'entity' => 'HubspotContactUpdate',
          'bao' => 'CRM_Hubspot_DAO_HubspotContactUpdate',
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
          'where' => 'civicrm_hubspot_contact_update.modified_date',
          'default' => 'CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
          'table_name' => 'civicrm_hubspot_contact_update',
          'entity' => 'HubspotContactUpdate',
          'bao' => 'CRM_Hubspot_DAO_HubspotContactUpdate',
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
    $r = CRM_Core_DAO_AllCoreTables::getImports(__CLASS__, 'hubspot_contact_update', $prefix, []);
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
    $r = CRM_Core_DAO_AllCoreTables::getExports(__CLASS__, 'hubspot_contact_update', $prefix, []);
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
      'index_hubspot_vid' => [
        'name' => 'index_hubspot_vid',
        'field' => [
          0 => 'hubspot_vid',
        ],
        'localizable' => FALSE,
        'sig' => 'civicrm_hubspot_contact_update::0::hubspot_vid',
      ],
      'index_update_status_id' => [
        'name' => 'index_update_status_id',
        'field' => [
          0 => 'update_status_id',
        ],
        'localizable' => FALSE,
        'sig' => 'civicrm_hubspot_contact_update::0::update_status_id',
      ],
    ];
    return ($localize && !empty($indices)) ? CRM_Core_DAO_AllCoreTables::multilingualize(__CLASS__, $indices) : $indices;
  }

}
