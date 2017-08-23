<?php

/**
 * @file
 * Contains \Drupal\uc_mobile_admin\Controller\PushController.
 */

namespace Drupal\uc_mobile_admin\Controller;

use Drupal\Core\Controller\ControllerBase;

class PushController extends ControllerBase {

  public function main() {

    $query = \Drupal::database()->select('uc_orders', 'o');
    $query->addExpression('MAX(o.order_id)');
    $order_id = $query->execute()->fetchField();

    $query = \Drupal::database()->select('uc_orders', 'o');
    $query->fields('o', ['order_total', 'currency']);
    $query->condition('o.order_id', $order_id);
    $order = $query->execute()->fetchAssoc();

    $query = \Drupal::database()->select('user_device_mob_api', 'd');
    $query->fields('d', ['device_token', 'os_type']);
    $devices = $query->execute()->fetchAll();

    $ids = ['ios' => [], 'android' => []];
    foreach ($devices as $device){
      if ('ios' == strtolower($device->os_type)) {
        if (!in_array($device->device_token, $ids['ios'])) {
          $ids['ios'][] = $device->device_token;
        }
      }
      else {
        if (!in_array($device->device_token, $ids['android'])) {
          $ids['android'][] = $device->device_token;
        }
      }
    }

    $total = number_format( $order['order_total'], 2, '.', '' );
    $site_url = $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'];

    if (0 < count($order)) {
      $msg = [
        'body'       => $total,
        'title'      => $site_url,
        'vibrate'    => 1,
        'sound'      => 1,
        'priority'   => 'high',
        'new_order'  => [
          'order_id'      => $order_id,
          'total'         => $total,
          'currency_code' => $order['currency'],
          'site_url'      =>$site_url,
        ],
        'event_type' => 'new_order'
      ];
      $msg_android = [
        'new_order'  => [
          'order_id'      => $order_id,
          'total'         => $total,
          'currency_code' => $order['currency'],
          'site_url'      => $site_url,
        ],
        'event_type' => 'new_order'
      ];
      foreach ( $ids as $k => $mas ) {
        if ( $k == 'ios' ) {
          $fields = [
            'registration_ids' => $ids[$k],
            'notification'     => $msg,
          ];
        }
        else {
          $fields = [
            'registration_ids' => $ids[$k],
            'data'             => $msg_android
          ];
        }
        $this->sendCurl($fields);
      }
    }
    exit;
  }

  function sendCurl($fields){
    $API_ACCESS_KEY = 'AAAAlhKCZ7w:APA91bFe6-ynbVuP4ll3XBkdjar_qlW5uSwkT5olDc02HlcsEzCyGCIfqxS9JMPj7QeKPxHXAtgjTY89Pv1vlu7sgtNSWzAFdStA22Ph5uRKIjSLs5z98Y-Z2TCBN3gl2RLPDURtcepk';
    $headers = [
      'Authorization: key=' . $API_ACCESS_KEY,
      'Content-Type: application/json'
    ];
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://fcm.googleapis.com/fcm/send');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fields));
    curl_exec($ch);
    curl_close($ch);
  }

}
