<?php

/**
 * @file
 * Contains \Drupal\uc_mobile_admin\Controller\OrderOrientedController.
 */

namespace Drupal\uc_mobile_admin\Controller;

use Drupal\uc_mobile_admin\OrderStatuses;

class OrderOrientedController extends UcMainController {

  protected $blocked_statuses;

  protected $order_statuses;

  public function __construct() {
    parent:: __construct();
    $this->order_statuses = new OrderStatuses();
    $this->blocked_statuses = $this->order_statuses->getBlockedStatusesIDs();
  }

  protected function getTotalOrdersSum($where = FALSE) {
    $query = \Drupal::database()->select('uc_orders', 'o');
    $query->addExpression("SUM(o.order_total)", 'total');
    $query->condition('o.order_status', $this->blocked_statuses, 'NOT IN');
    if ($where) {
      $query->where($where);
    }
    return $query->execute()->fetchField();
  }

  protected function getOrdersCount($where = FALSE) {
    $query = \Drupal::database()->select('uc_orders', 'o');
    $query->addExpression("COUNT(o.order_id)", 'count');
    $query->condition('o.order_status', $this->blocked_statuses, 'NOT IN');
    if ($where) {
      $query->where($where);
    }
    return (int) $query->execute()->fetchField();
  }

}
