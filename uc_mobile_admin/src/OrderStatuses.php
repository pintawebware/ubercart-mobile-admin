<?php

namespace Drupal\uc_mobile_admin;

class OrderStatuses {

  private $raw_statuses;

  public function __construct() {
    $query = \Drupal::database()->select('config', 'c');
    $query->addField('c', 'data');
    $query->condition('c.name', 'uc_order.status%', 'LIKE');
    $this->raw_statuses = $query->execute()->fetchAll();
  }

  public function getOrderStatuses() {
    $statuses = [];
    foreach ($this->raw_statuses as $row) {
      $tmp = unserialize($row->data);
      $statuses[] = [
        'order_status_id' => $tmp['id'],
        'name' => $tmp['name'],
        'language_id' => $tmp['langcode'],
      ];
    }
    return $statuses;
  }

  public function getOrderStatus($id) {
    foreach ($this->raw_statuses as $row) {
      $tmp = unserialize($row->data);
      if ($id == $tmp['id']) {
        return $tmp['name'];
      }
    }
    return FALSE;
  }

  public function getBlockedStatusesIDs() {
    $blocked_ids = $this->getStatusesIDsByState('canceled');
    $blocked_ids[] = UC_IN_CHECKOUT_STATUS_ID;
    return $blocked_ids;
  }

  public function getStatusesIDsByState($state) {
    $statuses_ids = [];
    foreach ($this->raw_statuses as $row) {
      $tmp = unserialize($row->data);
      if ($state == $tmp['state']) {
        $statuses_ids[] = $tmp['id'];
      }
    }
    return $statuses_ids;
  }


}