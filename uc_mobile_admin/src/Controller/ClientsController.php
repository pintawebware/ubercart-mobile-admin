<?php

/**
 * @file
 * Contains \Drupal\uc_mobile_admin\Controller\ClientsController.
 */

namespace Drupal\uc_mobile_admin\Controller;

class ClientsController extends OrderOrientedController {

  private $client_id;

  public function router($route) {
    if (($route <> 'clients') && !($this->client_id = (int) $this->request->get('client_id'))) {
      $this->response['error'] = 'Could not find client with id = 0';
      return;
    }
    switch ($route) {
      case 'clients';
        $this->getClients();
        break;
      case 'clientinfo';
        $this->getClientInfo();
        break;
      case 'clientorders':
        $this->getClientOrders();
        break;
      default:
    }
  }

  /**
   *
   * @api {get} ucmob.clients?route=clients getClients
   * @apiName getClients
   * @apiGroup Clients
   * @apiVersion 2.0.1
   *
   * @apiParam {Token}  token Your unique token.
   * @apiParam {Number} page  Number of the page.
   * @apiParam {Number} limit Limit of the orders for the page.
   * @apiParam {String} fio   Client name.
   * @apiParam {String} sort  Sort parameter (sum|quantity|date_added).
   *
   * @apiSuccess {Boolean} status        true.
   * @apiSuccess {Number}  version       Current API version.
   * @apiSuccess {Array}   clients       Array of the clients.
   * @apiSuccess {Number}  client_id     Unique client ID.
   * @apiSuccess {String}  fio           Client name.
   * @apiSuccess {Number}  total         Total sum of client's orders.
   * @apiSuccess {Number}  quantity      Total quantity of client's orders.
   * @apiSuccess {String}  currency_code Default currency of the shop.
   *
   * @apiSuccessExample Success-Response:
   * HTTP/1.1 200 OK
   * {
   *     "status": true,
   *     "version": "2.0.1",
   *     "response": {
   *         "clients": [
   *             {
   *                 "client_id": "1",
   *                 "fio": "Albert",
   *                 "total": "604.32728",
   *                 "quantity": "4",
   *                 "currency_code": "USD"
   *             },
   *             {
   *                 "client_id": "36",
   *                 "fio": "Cody",
   *                 "total": "200.00000",
   *                 "quantity": "1",
   *                 "currency_code": "USD"
   *             }
   *         ]
   *     }
   * }
   *
   * @apiErrorExample Error-Response:
   *
   * {
   *     "status" : false,
   *     "version": "2.0.1",
   *     "error": "No client found"
   * }
   *
   */
  public function getClients() {
    $query = \Drupal::database()->select('uc_orders', 'o');
    $query->addField('o', 'uid', 'client_id');
    $query->addExpression('(SELECT u.name FROM users_field_data u WHERE u.uid = o.uid)', 'fio');
    $query->addExpression("SUM(o.order_total)", 'total');
    $query->addExpression("COUNT(*)", 'quantity');
    $query->addExpression("(SELECT x.currency FROM uc_orders x WHERE x.uid = o.uid LIMIT 1)", 'currency_code');
    $query->condition('o.order_status', $this->blocked_statuses, 'NOT IN');
    if ($fio = $this->request->get('fio')) {
      $query->join('users_field_data', 'u', 'u.uid = o.uid');
      $name = explode(' ', $fio);
      $query->condition('u.name', '%' . $query->escapeLike($name[0]) . '%', 'LIKE');
    }
    $query->groupBy('o.uid');
    $sort = $this->request->get('sort', '');
    switch ($sort) {
      case 'sum':
        $order_field = 'total';
        break;
      case 'quantity':
        $order_field = 'quantity';
        break;
      default:
        $order_field = 'client_id';
    }
    $query->orderBy($order_field, 'DESC');

    $this->addQueryRange($query);
    $clients = $query->execute()->fetchAll();

    if ($clients) {
      $this->response['status'] = TRUE;
      $this->response['response'] = [
        'clients' => $clients,
      ];
    }
    else {
      $this->response['error'] = 'No client found';
    }
  }

  /**
   *
   * @api {get} ucmob.clients?route=clientinfo getClientInfo
   * @apiName getClientInfo
   * @apiGroup Clients
   * @apiVersion 2.0.1
   *
   * @apiParam {Token}  token     Your unique token.
   * @apiParam {Number} client_id Unique client ID.
   *
   * @apiSuccess {Boolean} status        true.
   * @apiSuccess {Number}  version       Current API version.
   * @apiSuccess {Number}  client_id     Unique client ID.
   * @apiSuccess {String}  fio           Client name.
   * @apiSuccess {String}  email         Client's email.
   * @apiSuccess {Number}  telephone     Client's phone.
   * @apiSuccess {Number}  total         Total sum of client's orders.
   * @apiSuccess {Number}  quantity      Total quantity of client's orders.
   * @apiSuccess {Number}  completed     Total quantity of completed orders.
   * @apiSuccess {String}  currency_code Default currency of the shop.
   *
   * @apiSuccessExample Success-Response:
   * HTTP/1.1 200 OK
   * {
   *     "status": true,
   *     "version": "2.0.1",
   *     "response": {
   *         "client_id": "1",
   *         "fio": "Albert",
   *         "email": "albert.brown@gmail.com",
   *         "telephone": "",
   *         "total": 604.32728,
   *         "quantity": 4,
   *         "completed": 1,
   *         "currency_code": "USD"
   *     }
   * }
   *
   * @apiErrorExample Error-Response:
   *
   * {
   *     "status" : false,
   *     "version": "2.0.1",
   *     "error": "Could not find client with id = 5"
   * }
   *
   */
  public function getClientInfo() {
    $query = \Drupal::database()->select('users_field_data', 'u');
    $query->addField('u', 'uid', 'client_id');
    $query->addField('u', 'name', 'fio');
    $query->addField('u', 'mail', 'email');
    $query->condition('u.uid', $this->client_id);
    $client = $query->execute()->fetchAssoc();

    if ($client) {
      if (!$client['telephone']) {
        $client['telephone'] = '';
      }
      $query = \Drupal::database()->select('uc_orders', 'o');
      $query->addField('o', 'billing_phone');
      $query->condition('o.uid', $this->client_id);
      $query->condition('o.billing_phone');
      $query->orderBy('order_id', 'DESC');
      $query->range(0, 1);
      $phone = $query->execute()->fetchField();

      $completed_statuses = $this->order_statuses->getStatusesIDsByState('completed');
      $completed_statuses = join("', '", $completed_statuses);
      $completed_where = "(uid = " . $this->client_id . ") AND (order_status IN ('" . $completed_statuses . "'))";

      $this->response['response'] = $client;
      $this->response['response']['telephone'] = $phone ? $phone : '';
      $this->response['response']['total'] = (real) $this->getTotalOrdersSum('uid = ' . $this->client_id);
      $this->response['response']['quantity'] = $this->getOrdersCount('uid = ' . $this->client_id);
      $this->response['response']['completed'] = $this->getOrdersCount($completed_where);
      $this->response['response']['currency_code'] = $this->getCurrency();
      $this->response['status'] = TRUE;
    }
    else {
      $this->response['error'] = 'Could not find client with id = ' . $this->client_id;
    }

  }

  /**
   *
   * @api {get} ucmob.clients?route=clientorders getClientOrders
   * @apiName getClientOrders
   * @apiGroup Clients
   * @apiVersion 2.0.1
   *
   * @apiParam {Token}  token     Your unique token.
   * @apiParam {Number} client_id Unique client ID.
   * @apiParam {String} sort      Sort parameter (total|date_added|completed).
   *
   * @apiSuccess {Boolean} status        true.
   * @apiSuccess {Number}  version       Current API version.
   * @apiSuccess {Number}  order_id      Unique order ID.
   * @apiSuccess {Number}  order_number  Number of the order.
   * @apiSuccess {String}  status        Status of the order.
   * @apiSuccess {Number}  total         Total sum of the order.
   * @apiSuccess {Date}    date_added    Date added of the order.
   * @apiSuccess {String}  currency_code Default currency of the shop.
   *
   * @apiSuccessExample Success-Response:
   * HTTP/1.1 200 OK
   * {
   *     "status": true,
   *     "version": "2.0.1",
   *     "response": {
   *         "orders": [
   *             {
   *                 "order_id": "10",
   *                 "order_number": "10",
   *                 "status": "Pending",
   *                 "total": "124.32728",
   *                 "date_added": "2017-08-10 14:22:56",
   *                 "currency_code": "USD"
   *             },
   *             {
   *                 "order_id": "8",
   *                 "order_number": "8",
   *                 "status": "Processing",
   *                 "total": "15.00000",
   *                 "date_added": "2017-08-08 15:13:40",
   *                 "currency_code": "USD"
   *             }
   *         ]
   *     }
   *
   * @apiErrorExample Error-Response:
   *
   * {
   *     "status" : false,
   *     "version": "2.0.1",
   *     "error": "Could not find client with id = 5"
   * }
   *
   */
  public function getClientOrders() {
    $query = \Drupal::database()->select('uc_orders', 'o');
    $query->addField('o', 'order_id', 'order_id');
    $query->addField('o', 'order_id', 'order_number');
    $query->addField('o', 'order_status', 'status');
    $query->addField('o', 'order_total', 'total');
    $query->addField('o', 'created', 'date_added');
    $query->addField('o', 'currency', 'currency_code');
    $query->condition('o.uid', $this->client_id);
    $sort = $this->request->get('sort', '');
    switch ($sort) {
      case 'sum':
        $order_field = 'total';
        break;
      case 'completed':
        $completed_statuses = $this->order_statuses->getStatusesIDsByState('completed');
        $completed_statuses = join("', '", $completed_statuses);
        $query->addExpression("(CASE WHEN o.order_status IN ('" . $completed_statuses . "') THEN 1 ELSE 0 END)", 'order_field');
        $order_field = 'order_field';
        break;
      default:
        $order_field = 'order_id';
    }
    $query->orderBy($order_field, 'DESC');
    $orders = $query->execute()->fetchAll();

    if ($orders) {
      for ($i = 0; $i < count($orders); $i++) {
        $orders[$i]->date_added = gmdate("Y-m-d H:i:s", (int) $orders[$i]->date_added);
        $orders[$i]->status = $this->order_statuses->getOrderStatus($orders[$i]->status);
      }

      $this->response['response'] = ['orders' => $orders];
      $this->response['status'] = TRUE;
    }
    else {
      $this->response['error'] = 'Could not find client with id = ' . $this->client_id;
    }
  }

}