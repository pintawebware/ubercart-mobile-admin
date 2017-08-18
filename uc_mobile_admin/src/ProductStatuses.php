<?php

namespace Drupal\uc_mobile_admin;

class ProductStatuses {

  private $raw_statuses;

  public function __construct() {
    $query = \Drupal::database()->select('config', 'c');
    $query->addField('c', 'data');
    $query->condition('c.name', 'views.view.content');
    $config = $query->execute()->fetchField();
    $config = unserialize($config);
    $this->raw_statuses = $config['display']['default']['display_options']['filters']['status']['group_info']['group_items'];
  }

  public function getProductStatuses() {
    $stock_statuses = [];
    foreach ($this->raw_statuses as $s) {
      $stock_statuses[] = [
        'status_id' => $s['value'],
        'name' => $s['title'],
      ];
    }
    return $stock_statuses;
  }

  public function getStatusName($id) {
    foreach ($this->raw_statuses as $s) {
      if ($id == $s['value']) {
        return $s['title'];
      }
    }
    return $id;
  }

}