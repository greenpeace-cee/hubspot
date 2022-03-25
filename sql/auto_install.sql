-- +--------------------------------------------------------------------+
-- | Copyright CiviCRM LLC. All rights reserved.                        |
-- |                                                                    |
-- | This work is published under the GNU AGPLv3 license with some      |
-- | permitted exceptions and without any warranty. For full license    |
-- | and copyright information, see https://civicrm.org/licensing       |
-- +--------------------------------------------------------------------+
--
-- Generated from schema.tpl
-- DO NOT EDIT.  Generated by CRM_Core_CodeGen
--
-- /*******************************************************
-- *
-- * Clean up the existing tables - this section generated from drop.tpl
-- *
-- *******************************************************/

SET FOREIGN_KEY_CHECKS=0;

DROP TABLE IF EXISTS `civicrm_hubspot_form_submission`;
DROP TABLE IF EXISTS `civicrm_hubspot_contact_update`;
DROP TABLE IF EXISTS `civicrm_hubspot_contact`;
DROP TABLE IF EXISTS `civicrm_hubspot_portal`;

SET FOREIGN_KEY_CHECKS=1;
-- /*******************************************************
-- *
-- * Create new tables
-- *
-- *******************************************************/

-- /*******************************************************
-- *
-- * civicrm_hubspot_portal
-- *
-- *******************************************************/
CREATE TABLE `civicrm_hubspot_portal` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT 'Unique HubspotPortal ID',
  `hubspot_portal_identifier` int unsigned NOT NULL COMMENT 'Unique identifier of portal in HubSpot',
  `name` varchar(255) NOT NULL COMMENT 'Portal name',
  `api_key` varchar(255) NOT NULL COMMENT 'API Key',
  `config` text COMMENT 'Configuration for this portal',
  `created_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified_date` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE INDEX `UI_hubspot_portal_identifier`(hubspot_portal_identifier)
)
ENGINE=InnoDB;

-- /*******************************************************
-- *
-- * civicrm_hubspot_contact
-- *
-- *******************************************************/
CREATE TABLE `civicrm_hubspot_contact` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT 'Unique HubspotContact ID',
  `contact_id` int unsigned NOT NULL COMMENT 'FK to Contact',
  `hubspot_portal_id` int unsigned COMMENT 'FK to HubSpotPortal',
  `hubspot_vid` int unsigned NULL COMMENT 'Unique identifier of contact in HubSpot',
  `last_state` longtext NULL,
  `custom_data` longtext NULL,
  `is_dirty` tinyint NOT NULL DEFAULT 0 COMMENT 'Is this record dirty?',
  `created_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified_date` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE INDEX `UI_contact_id_hubspot_portal_id`(contact_id, hubspot_portal_id),
  UNIQUE INDEX `UI_hubspot_vid_hubspot_portal_id`(hubspot_vid, hubspot_portal_id),
  INDEX `index_hubspot_vid`(hubspot_vid),
  CONSTRAINT FK_civicrm_hubspot_contact_contact_id FOREIGN KEY (`contact_id`) REFERENCES `civicrm_contact`(`id`) ON DELETE CASCADE,
  CONSTRAINT FK_civicrm_hubspot_contact_hubspot_portal_id FOREIGN KEY (`hubspot_portal_id`) REFERENCES `civicrm_hubspot_portal`(`id`) ON DELETE SET NULL
)
ENGINE=InnoDB;

-- /*******************************************************
-- *
-- * civicrm_hubspot_contact_update
-- *
-- *******************************************************/
CREATE TABLE `civicrm_hubspot_contact_update` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT 'Unique HubspotContactUpdate ID',
  `hubspot_portal_id` int unsigned COMMENT 'FK to HubSpotPortal',
  `hubspot_contact_id` int unsigned COMMENT 'FK to HubSpotContact',
  `hubspot_vid` int unsigned NULL COMMENT 'Unique identifier of contact in HubSpot',
  `hubspot_timestamp` decimal(20,0) NULL COMMENT 'Submission timestamp in HubSpot. DECIMAL because core does not support BIGINT.',
  `update_type_id` int unsigned NOT NULL COMMENT 'ID of update type',
  `inbound_payload` longtext NULL,
  `update_data` longtext NULL,
  `update_status_id` int unsigned NOT NULL COMMENT 'ID of update status',
  `status_details` text NULL,
  `created_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified_date` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `index_hubspot_vid`(hubspot_vid),
  INDEX `index_update_status_id`(update_status_id),
  CONSTRAINT FK_civicrm_hubspot_contact_update_hubspot_portal_id FOREIGN KEY (`hubspot_portal_id`) REFERENCES `civicrm_hubspot_portal`(`id`) ON DELETE SET NULL,
  CONSTRAINT FK_civicrm_hubspot_contact_update_hubspot_contact_id FOREIGN KEY (`hubspot_contact_id`) REFERENCES `civicrm_hubspot_contact`(`id`) ON DELETE CASCADE
)
ENGINE=InnoDB;

-- /*******************************************************
-- *
-- * civicrm_hubspot_form_submission
-- *
-- * FIXME
-- *
-- *******************************************************/
CREATE TABLE `civicrm_hubspot_form_submission` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT 'Unique HubspotFormSubmission ID',
  `hubspot_portal_id` int unsigned COMMENT 'FK to HubSpotPortal',
  `hubspot_contact_id` int unsigned COMMENT 'FK to HubSpotContact',
  `guid` varchar(50) NOT NULL,
  `hubspot_timestamp` decimal(20,0) NOT NULL COMMENT 'Submission timestamp in HubSpot. VARCHAR because core does not support BIGINT.',
  `submission_data` longtext NOT NULL,
  `form_submission_status_id` int unsigned NOT NULL COMMENT 'ID of form submission status',
  `status_details` text NULL,
  `created_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified_date` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `index_hubspot_timestamp`(hubspot_timestamp),
  INDEX `index_form_submission_status_id`(form_submission_status_id),
  CONSTRAINT FK_civicrm_hubspot_form_submission_hubspot_portal_id FOREIGN KEY (`hubspot_portal_id`) REFERENCES `civicrm_hubspot_portal`(`id`) ON DELETE SET NULL,
  CONSTRAINT FK_civicrm_hubspot_form_submission_hubspot_contact_id FOREIGN KEY (`hubspot_contact_id`) REFERENCES `civicrm_hubspot_contact`(`id`) ON DELETE CASCADE
)
ENGINE=InnoDB;
