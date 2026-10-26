<?php

/**
 * Parameters and contact sampling shared by the audit actions.
 */
trait CRM_Hubspot_AuditSampleTrait {

  /**
   * Number of random contacts to audit. Use either this or samplePercentage.
   *
   * @var int
   */
  protected $sampleSize;

  /**
   * Percentage (0-100) of contacts to audit. Use either this or sampleSize.
   *
   * @var float
   */
  protected $samplePercentage;

  /**
   * Picks random contacts matching $where and returns their IDs in chunks of 100.
   */
  private function sampleContactIds(array $where, array $join = []): array {
    if (isset($this->sampleSize) === isset($this->samplePercentage)) {
      throw new CRM_Core_Exception('Specify either sampleSize or samplePercentage');
    }

    $query = [
      'where'            => $where,
      'join'             => $join,
      'checkPermissions' => FALSE,
    ];

    $limit = (int) $this->sampleSize;

    if (isset($this->samplePercentage)) {
      $total = civicrm_api4('Contact', 'get', $query + ['select' => ['row_count']])->countMatched();
      $limit = (int) ceil($total * $this->samplePercentage / 100);
    }

    // A limit of 0 would mean no limit
    if ($limit < 1) {
      return [];
    }

    $contact_ids = civicrm_api4('Contact', 'get', $query + [
      'select'  => ['id'],
      'orderBy' => ['RAND()' => 'ASC'],
      'limit'   => $limit,
    ])->column('id');

    return array_chunk(array_unique($contact_ids), 100);
  }

}
