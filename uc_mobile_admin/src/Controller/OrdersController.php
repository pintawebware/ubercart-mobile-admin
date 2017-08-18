<?php

/**
 * @file
 * Contains \Drupal\uc_mobile_admin\Controller\OrdersController.
 */

namespace Drupal\uc_mobile_admin\Controller;

class OrdersController extends OrderOrientedController {

  private $order_id;

  public function router($route) {
    if (!in_array($route, ['statistic', 'orders']) && !($this->order_id = (int) $this->request->get('order_id'))) {
      $this->response['error'] = 'Could not find order with id = 0';
      return;
    }
    switch ($route) {
      case 'statistic';
        $this->getDashboardStatistic();
        break;
      case 'orders';
        $this->getOrders();
        break;
      case 'getorderinfo':
        $this->getOrderInfo();
        break;
      case 'paymentanddelivery':
        $this->getOrderPaymentAndDelivery();
        break;
      case 'orderproducts':
        $this->getOrderProducts();
        break;
      case 'orderhistory':
        $this->getOrderHistory();
        break;
      case 'changestatus':
        $this->changeStatus();
        break;
      case 'delivery':
        $this->changeOrderDelivery();
        break;
      default:
    }
  }

  /**
   *
   * @api {get} ucmob.orders?route=statistic getDashboardStatistic
   * @apiName getDashboardStatistic
   * @apiGroup Statistic
   * @apiVersion 2.0.1
   *
   * @apiParam {String} filter Period for filter(day/week/month/year).
   * @apiParam {Token}  token  Your unique token.
   *
   * @apiSuccess {Array}   xAxis           Period of the selected filter.
   * @apiSuccess {Array}   clients         Clients for the selected period.
   * @apiSuccess {Array}   orders          Orders for the selected period.
   * @apiSuccess {Number}  total_sales     Sum of sales of the shop.
   * @apiSuccess {Number}  sale_year_total Sum of sales of the current year.
   * @apiSuccess {String}  currency_code   Default currency of the shop.
   * @apiSuccess {Number}  orders_total    Total orders of the shop.
   * @apiSuccess {Number}  clients_total   Total clients of the shop.
   * @apiSuccess {Number}  version         Current API version.
   * @apiSuccess {Boolean} status          true.
   *
   * @apiSuccessExample Success-Response:
   * HTTP/1.1 200 OK
   * {
   *     "status": true,
   *     "version": "2.0.1",
   *     "response": {
   *         "xAxis": [
   *             1,
   *             2,
   *             3,
   *             4,
   *             5,
   *             6,
   *             7
   *         ],
   *         "clients": [
   *             0,
   *             0,
   *             1,
   *             1,
   *             0,
   *             0,
   *             0
   *         ],
   *         "orders": [
   *             1,
   *             0,
   *             0,
   *             1,
   *             0,
   *             0,
   *             0
   *         ],
   *         "total_sales": "390.00000",
   *         "sale_year_total": "390.00000",
   *         "currency_code": "USD",
   *         "orders_total": 4,
   *         "clients_total": 4
   *     }
   * }
   *
   * @apiErrorExample Error-Response:
   *
   *     {
   *       "status": false,
   *       "version": "2.0.1",
   *       "error": "Unknown filter set"
   *     }
   *
   */
  private function getDashboardStatistic() {
    $xAxis = [];
    $orders = [];
    $clients = [];
    $filter = $this->request->get('filter', 'day');
    $shift = 0;

    switch ($filter) {
      case 'day':
        $start = 0;
        $stop = 23;
        $where = 'DAY(FROM_UNIXTIME(created)) = DAY(NOW()) AND HOUR(FROM_UNIXTIME(created)) = ';
        break;
      case 'week':
        $start = 0;
        $stop = 6;
        $shift = 1;
        $where = 'WEEKOFYEAR(FROM_UNIXTIME(created)) = WEEKOFYEAR(NOW()) AND WEEKDAY(FROM_UNIXTIME(created)) = ';
        break;
      case 'month':
        $start = 1;
        $stop = date('t');
        $where = 'MONTH(FROM_UNIXTIME(created)) = MONTH(NOW()) AND DAY(FROM_UNIXTIME(created)) = ';
        break;
      case 'year':
        $start = 1;
        $stop = 12;
        $where = 'YEAR(FROM_UNIXTIME(created)) = YEAR(NOW()) AND MONTH(FROM_UNIXTIME(created)) = ';
        break;
      default:
        $this->response['error'] = 'Unknown filter set';
        return;
    }

    for ($i = $start; $i <= $stop; $i++) {
      $xAxis[] = $i + $shift;
      $query = \Drupal::database()->select('uc_orders', 'o');
      $query->where($where . $i);
      $orders[] = (int) $query->countQuery()->execute()->fetchField();

      $query = \Drupal::database()->select('users_field_data', 'u');
      $query->condition('u.uid', 0, '>');
      $query->where($where . $i);
      $clients[] = (int) $query->countQuery()->execute()->fetchField();
    }

    $query = \Drupal::database()->select('users', 'u');
    $query->condition('u.uid', 0, '>');
    $clients_total = (int) $query->countQuery()->execute()->fetchField();

    $this->response['status'] = TRUE;
    $this->response['response'] = [
      'xAxis' => $xAxis,
      'clients' => $clients,
      'orders' => $orders,
      'total_sales' => $this->getTotalOrdersSum(),
      'sale_year_total' => $this->getTotalOrdersSum('YEAR(FROM_UNIXTIME(created)) = YEAR(NOW())'),
      'currency_code' => $this->getCurrency(),
      'orders_total' => $this->getOrdersCount(),
      'clients_total' => $clients_total,
    ];
  }

  /**
   *
   * @api {get} ucmob.orders?route=orders getOrders
   * @apiName getOrders
   * @apiGroup Orders
   * @apiVersion 2.0.1
   *
   * @apiParam {Token}  token           Your unique token.
   * @apiParam {Number} page            Number of the page.
   * @apiParam {Number} limit           Limit of the orders for the page.
   * @apiParam {String} fio             Client first name and last name.
   * @apiParam {Number} order_status_id Unique id of the order.
   * @apiParam {Number} min_price       Min price of order.
   * @apiParam {Number} max_price       Max price of order.
   * @apiParam {Date}   date_min min    Date adding of the order.
   * @apiParam {Date}   date_max max    Date adding of the order.
   *
   * @apiSuccess {Boolean} status                 true.
   * @apiSuccess {Number}  version                Current API version.
   * @apiSuccess {Array}   orders                 Array of the orders.
   * @apiSuccess {Number}  order[order_id]        Unique order ID.
   * @apiSuccess {Number}  order[order_number]    Number of the order.
   * @apiSuccess {String}  order[status]          Status of the order.
   * @apiSuccess {Number}  order[total]           Total sum of the order.
   * @apiSuccess {Date}    order[date_added]      Date added of the order.
   * @apiSuccess {String}  order[currency_code]   Default currency of the shop.
   * @apiSuccess {String}  order[fio]             Client name.
   * @apiSuccess {Array}   statuses               Array of the order statuses.
   * @apiSuccess {Number}  status[order_staus_id] ID of the order status.
   * @apiSuccess {String}  status[name]           Order status name.
   * @apiSuccess {Number}  status[language_id]    ID of the language.
   * @apiSuccess {String}  currency_code          Default currency of the shop.
   * @apiSuccess {Date}    total_quantity         Total quantity of the orders.
   * @apiSuccess {Number}  total_sum              Total sum of the orders.
   * @apiSuccess {Number}  max_price              Maximum sum of the order.
   *
   * @apiSuccessExample Success-Response:
   * HTTP/1.1 200 OK
   * {
   *     "status": true,
   *     "version": "2.0.1",
   *     "response": {
   *         "orders": [
   *             {
   *                 "order_id": "2",
   *                 "order_number": "2",
   *                 "status": "Processing",
   *                 "total": "20.00000",
   *                 "date_added": "2017-07-11 07:40:42",
   *                 "currency_code": "USD",
   *                 "fio": "Cody Drew"
   *             },
   *             {
   *                 "order_id": "1",
   *                 "order_number": "1",
   *                 "status": "Processing",
   *                 "total": "260.00000",
   *                 "date_added": "2017-07-10 16:51:54",
   *                 "currency_code": "USD",
   *                 "fio": "Albert Brown"
   *             }
   *         ]
   *         "statuses": [
   *             {
   *                 "order_status_id": "canceled",
   *                 "name": "Canceled",
   *                 "language_id": "ru"
   *             },
   *             {
   *                 "order_status_id": "completed",
   *                 "name": "Completed",
   *                 "language_id": "ru"
   *             },
   *             {
   *                 "order_status_id": "processing",
   *                 "name": "Processing",
   *                 "language_id": "ru"
   *             }
   *         ],
   *         "currency_code": "USD",
   *         "total_quantity": 4,
   *         "total_sum": "390.00000",
   *         "max_price": "260.00000"
   *     }
   * }
   *
   * @apiErrorExample Error-Response:
   *
   * {
   *     "status": false,
   *     "version": "2.0.1",
   *     "error": "No order found"
   * }
   *
   */
  private function getOrders() {
    $query = \Drupal::database()->select('uc_orders', 'o');
    $query->addField('o', 'order_id', 'order_id');
    $query->addField('o', 'order_id', 'order_number');
    $query->addExpression("CONCAT(u.name, ' (', o.billing_first_name, ' ', o.billing_last_name, ')')", 'fio');
    $query->addField('o', 'order_status', 'status');
    $query->addField('o', 'order_total', 'total');
    $query->addField('o', 'created', 'date_added');
    $query->addField('o', 'currency', 'currency_code');
    $query->join('users_field_data', 'u', 'u.uid = o.uid');
    if ($fio = $this->request->get('fio')) {
      $name = explode(' ', $fio);
      $or = db_or();
      for ($i = 0; $i < count($name); $i++) {
        $condition = '%' . $query->escapeLike(trim($name[$i], ')', '(')) . '%';
        $or
          ->condition('o.billing_first_name', $condition, 'LIKE')
          ->condition('o.billing_last_name', $condition, 'LIKE');
      }
      $or->condition('u.name', '%' . $query->escapeLike($name[0]) . '%', 'LIKE');
      $query->condition($or);
    }
    if ($order_status_id = $this->request->get('order_status_id')) {
      $query->condition('o.order_status', $order_status_id);
    }
    if ($min_price = $this->request->get('min_price')) {
      $query->condition('o.order_total', $min_price, '>=');
    }
    if ($max_price = $this->request->get('max_price')) {
      $query->condition('o.order_total', $max_price, '<=');
    }
    if ($date_min = $this->request->get('date_min')) {
      $query->condition('o.created', date('U', strtotime($date_min)), '>=');
    }
    if ($date_max = $this->request->get('date_max')) {
      $query->condition('o.created', date('U', strtotime($date_max . " + 1 days")), '<=');
    }
    $query->orderBy('order_id', 'DESC');
    $this->addQueryRange($query);
    $orders = $query->execute()->fetchAll();

    if ($orders) {
      for ($i = 0; $i < count($orders); $i++) {
        $orders[$i]->date_added = gmdate("Y-m-d H:i:s", (int) $orders[$i]->date_added);
        $orders[$i]->status = $this->order_statuses->getOrderStatus($orders[$i]->status);
      }

      $this->response['status'] = TRUE;
      $this->response['response'] = [
        'orders' => $orders,
        'statuses' => $this->order_statuses->getOrderStatuses(),
        'currency_code' => $this->getCurrency(),
        'total_quantity' => $this->getOrdersCount(),
        'total_sum' => $this->getTotalOrdersSum(),
        'max_price' => $this->getMaxOrderPrice(),
      ];
    }
    else {
      $this->response['error'] = 'No order found';
    }
  }

  /**
   *
   * @api {get} ucmob.orders?route=getorderinfo getOrderInfo
   * @apiName getOrderInfo
   * @apiGroup Orders
   * @apiVersion 2.0.1
   *
   * @apiParam {Token}  token    Your unique token.
   * @apiParam {Number} order_id Unique order ID.
   *
   * @apiSuccess {Boolean} status                 true.
   * @apiSuccess {Number}  version                Current API version.
   * @apiSuccess {Number}  order_number           Number of the order.
   * @apiSuccess {String}  status                 Status of the order.
   * @apiSuccess {Number}  total                  Total sum of the order.
   * @apiSuccess {Date}    date_added             Date added of the order.
   * @apiSuccess {String}  currency_code          Default currency of the shop.
   * @apiSuccess {String}  email                  Client's email.
   * @apiSuccess {Number}  telephone              Client's phone.
   * @apiSuccess {String}  fio                    Client's FIO.
   * @apiSuccess {Array}   statuses               Array of the order statuses.
   * @apiSuccess {Number}  status[order_staus_id] ID of the order status.
   * @apiSuccess {String}  status[name]           Order status name.
   * @apiSuccess {Number}  status[language_id]    ID of the language.
   *
   * @apiSuccessExample Success-Response:
   * HTTP/1.1 200 OK
   * {
   *     "status": true,
   *     "version": "2.0.1",
   *     "response": {
   *         "order_number": "2",
   *         "status": "Processing",
   *         "total": "20.00000",
   *         "date_added": "1499758842",
   *         "currency_code": "USD",
   *         "email": "t-shop@i.ua",
   *         "telephone": "222-22-22",
   *         "fio": "Cody Drew",
   *         "statuses": [
   *             {
   *                 "order_status_id": "canceled",
   *                 "name": "Canceled",
   *                 "language_id": "ru"
   *             },
   *             {
   *                 "order_status_id": "completed",
   *                 "name": "Completed",
   *                 "language_id": "ru"
   *             },
   *             {
   *                 "order_status_id": "processing",
   *                 "name": "Processing",
   *                 "language_id": "ru"
   *             }
   *         ]
   *     }
   * }
   *
   * @apiErrorExample Error-Response:
   *
   * {
   *     "status": false,
   *     "version": "2.0.1",
   *     "error": "Could not find order with id = 5"
   * }
   *
   */
  private function getOrderInfo() {
    $query = \Drupal::database()->select('uc_orders', 'o');
    $query->addField('o', 'order_id', 'order_number');
    $query->addField('o', 'order_status', 'status');
    $query->addField('o', 'order_total', 'total');
    $query->addField('o', 'created', 'date_added');
    $query->addField('o', 'currency', 'currency_code');
    $query->addField('o', 'primary_email', 'email');
    $query->addField('o', 'billing_phone', 'telephone');
    $query->addExpression("CONCAT(u.name, ' (', o.billing_first_name, ' ', o.billing_last_name, ')')", 'fio');
    $query->join('users_field_data', 'u', 'u.uid = o.uid');
    $query->condition('o.order_id', $this->order_id);
    $order = $query->execute()->fetchAssoc();

    if ($order) {
      $order['status'] = $this->order_statuses->getOrderStatus($order['status']);
      if (empty($order['email'])) {
        $order['email'] = '';
      }
      if (empty($order['telephone'])) {
        $order['telephone'] = '';
      }

      $this->response['status'] = TRUE;
      $this->response['response'] = $order;
      $this->response['response']['statuses'] = $this->order_statuses->getOrderStatuses();
    }
    else {
      $this->response['error'] = 'Could not find order with id = ' . $this->order_id;
    }
  }

  /**
   *
   * @api {get} ucmob.orders?route=paymentanddelivery getOrderPaymentAndDelivery
   * @apiName getOrderPaymentAndDelivery
   * @apiGroup Orders
   * @apiVersion 2.0.1
   *
   * @apiParam {Token}  token    Your unique token.
   * @apiParam {Number} order_id Unique order ID.
   *
   * @apiSuccess {String}  payment_method   Payment method.
   * @apiSuccess {String}  shipping_method  Shipping method.
   * @apiSuccess {String}  shipping_address Shipping address.
   * @apiSuccess {Number}  version          Current API version.
   * @apiSuccess {Boolean} status           true.
   *
   * @apiSuccessExample Success-Response:
   * HTTP/1.1 200 OK
   * {
   *     "status": true,
   *     "version": "2.0.1",
   *     "response": {
   *         "payment_method": "Cash on delivery",
   *         "shipping_method": "SuperPost",
   *         "shipping_address": "Albert Brown, Flower street, 5, Dnipro."
   *     }
   * }
   *
   * @apiErrorExample Error-Response:
   *
   * {
   *     "status": false,
   *     "version": "2.0.1",
   *     "error": "Could not find order with id = 5"
   * }
   *
   */
  private function getOrderPaymentAndDelivery() {
    $query = \Drupal::database()->select('uc_orders', 'o');
    $query->addField('o', 'payment_method', 'payment_method');
    $query->addField('i', 'title', 'shipping_method');
    $query->addExpression("IFNULL(CONCAT(delivery_first_name, ' ', delivery_last_name, ', ', delivery_street1, IF(delivery_street2, ', ' + delivery_street2, ''), ', ', delivery_city, IF(delivery_zone, ', ' + delivery_zone, ''), IF(delivery_country, ', ' + delivery_country, ''), '.'), '')", 'shipping_address');
    $query->join('uc_order_line_items', 'i', 'i.order_id = o.order_id');
    $query->condition('o.order_id', $this->order_id);
    $query->condition('i.type', 'shipping');
    $order = $query->execute()->fetchAssoc();

    if ($order) {
      $order['payment_method'] = $this->getPaymentName($order['payment_method']);

      $this->response['status'] = TRUE;
      $this->response['response'] = $order;
    }
    else {
      $this->response['error'] = 'Could not find order with id = ' . $this->order_id;
    }
  }

  /**
   *
   * @api {get} ucmob.orders?route=orderproducts getOrderProducts
   * @apiName getOrderProducts
   * @apiGroup Orders
   * @apiVersion 2.0.1
   *
   * @apiParam {Token}  token    Your unique token.
   * @apiParam {Number} order_id Unique order ID.
   *
   * @apiSuccess {Boolean} status            true.
   * @apiSuccess {Number}  version           Current API version.
   * @apiSuccess {Array}   products          Array of the order products.
   * @apiSuccess {Number}  product_id        Unique product id.
   * @apiSuccess {String}  name              Name of the product.
   * @apiSuccess {String}  sku               SKU of product.
   * @apiSuccess {String}  model             Model of the product.
   * @apiSuccess {Number}  quantity          Quantity of the product.
   * @apiSuccess {Number}  price             Price of the product.
   * @apiSuccess {Url}     image             Picture of the product.
   * @apiSuccess {Array}   total_order_price Array of the order totals.
   * @apiSuccess {String}  currency_code     Currency of the order.
   * @apiSuccess {Number}  total             Total order sum.
   * @apiSuccess {Number}  shipping_price    ost of the shipping.
   * @apiSuccess {Number}  total_price       Sum of product's prices.
   *
   * @apiSuccessExample Success-Response:
   * HTTP/1.1 200 OK
   * {
   *     "status": true,
   *     "version": "2.0.1",
   *     "response": {
   *         "products": [
   *             {
   *                 "product_id": "3",
   *                 "name": "уголь",
   *                 "sku": "566",
   *                 "quantity": "2",
   *                 "price": "100.00000",
   *                 "image":
   *   "http://my.site.com/sites/default/files/4.jpg"
   *             },
   *             {
   *                 "product_id": "2",
   *                 "name": "дрова",
   *                 "sku": "234",
   *                 "quantity": "5",
   *                 "price": "10.00000",
   *                 "image":
   *   "http://my.site.com/sites/default/files/1.jpg"
   *             }
   *         ],
   *         "total_order_price": {
   *             "currency_code": "USD",
   *             "total": "260.00000",
   *             "shipping_price": "10.00000",
   *             "total_price": "250.00000",
   *             "total_discount": 0
   *         }
   *     }
   * }
   *
   * @apiErrorExample Error-Response:
   *
   * {
   *     "status": false,
   *     "version": "2.0.1",
   *     "error": "Could not find order with id = 5"
   * }
   *
   */
  private function getOrderProducts() {
    $query = \Drupal::database()->select('uc_order_products', 'p');
    $query->addField('p', 'nid', 'product_id');
    $query->addField('p', 'title', 'name');
    $query->addField('p', 'model', 'sku');
    $query->addField('p', 'qty', 'quantity');
    $query->addField('p', 'price', 'price');
    $query->addField('f', 'uri', 'image');
    $query->leftJoin('node__uc_product_image', 'pi', 'pi.entity_id = p.nid and pi.delta = 0');
    $query->leftJoin('file_managed', 'f', 'f.fid = pi.uc_product_image_target_id');
    $query->condition('p.order_id', $this->order_id);
    $products = $query->execute()->fetchAll();

    if (!$products) {
      $this->response['error'] = 'Could not find order with id = ' . $this->order_id;
    }

    for ($i = 0; $i < count($products); $i++) {
      $products[$i]->image = file_create_url($products[$i]->image);
    }

    $query = \Drupal::database()->select('uc_orders', 'o');
    $query->addField('o', 'currency', 'currency_code');
    $query->addField('o', 'order_total', 'total');
    $query->addField('i', 'amount', 'shipping_price');
    $query->join('uc_order_line_items', 'i', 'i.order_id = o.order_id');
    $query->condition('o.order_id', $this->order_id);
    $query->condition('i.type', 'shipping');
    $order = $query->execute()->fetchAssoc();

    $query = \Drupal::database()->select('uc_order_products', 'p');
    $query->addExpression("SUM(p.price * p.qty)", 'total_price');
    $query->condition('p.order_id', $this->order_id);
    $order['total_price'] = $query->execute()->fetchField();
    $order['total_discount'] = 0;

    $this->response['status'] = TRUE;
    $this->response['response'] = [
      'products' => $products,
      'total_order_price' => $order,
    ];
  }

  /**
   *
   * @api {get} ucmob.orders?route=orderhistory getOrderHistory
   * @apiName getOrderHistory
   * @apiGroup Orders
   * @apiVersion 2.0.1
   *
   * @apiParam {Token}  token    Your unique token.
   * @apiParam {Number} order_id Unique order ID.
   *
   * @apiSuccess {Boolean} status     true.
   * @apiSuccess {Number}  version    Current API version.
   * @apiSuccess {Array}   orders                 Array of the orders.
   * @apiSuccess {String}  order[name]            Status of the order.
   * @apiSuccess {Number}  order[order_status_id] ID of the status of the
   *   order.
   * @apiSuccess {Date}    order[date_added]      Date of adding status of the
   *   order.
   * @apiSuccess {String}  order[comment]         Some comment added from
   *   manager.
   * @apiSuccess {Array}   statuses               Array of the order statuses.
   * @apiSuccess {Number}  status[order_staus_id] ID of the order status.
   * @apiSuccess {String}  status[name]           Order status name.
   * @apiSuccess {Number}  status[language_id]    ID of the language.
   *
   * @apiSuccessExample Success-Response:
   * HTTP/1.1 200 OK
   * {
   *     "status": true,
   *     "version": "2.0.1",
   *     "response": {
   *         "orders": [
   *             {
   *                 "name": "In checkout",
   *                 "order_status_id": "in_checkout",
   *                 "date_added": "2017-07-11 06:51:54",
   *                 "comment": ""
   *             },
   *             {
   *                 "name": "Pending",
   *                 "order_status_id": "pending",
   *                 "date_added": "2017-07-11 07:36:58",
   *                 "comment": "this is a comment"
   *             },
   *             {
   *                 "name": "Processing",
   *                 "order_status_id": "processing",
   *                 "date_added": "2017-08-08 13:03:59",
   *                 "comment": ""
   *             },
   *             {
   *                 "name": "Completed",
   *                 "order_status_id": "completed",
   *                 "date_added": "2017-08-08 14:42:30",
   *                 "comment": ""
   *             }
   *         ],
   *         "statuses": [
   *             {
   *                 "order_status_id": "canceled",
   *                 "name": "Canceled",
   *                 "language_id": "ru"
   *             },
   *             {
   *                 "order_status_id": "completed",
   *                 "name": "Completed",
   *                 "language_id": "ru"
   *             },
   *             {
   *                 "order_status_id": "in_checkout",
   *                 "name": "In checkout",
   *                 "language_id": "ru"
   *             },
   *             {
   *                 "order_status_id": "pending",
   *                 "name": "Pending",
   *                 "language_id": "ru"
   *             },
   *             {
   *                 "order_status_id": "processing",
   *                 "name": "Processing",
   *                 "language_id": "ru"
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
   *     "error": "Could not find any statuses for order with id = 5"
   * }
   *
   */
  private function getOrderHistory() {
    $history = [];
    $need_pending_info = TRUE;

    // order existing check; zero status
    $query = \Drupal::database()->select('uc_orders', 'o');
    $query->addField('o', 'created');
    $query->condition('o.order_id', $this->order_id);
    $order_time = $query->execute()->fetchField();

    if (!$order_time) {
      $this->response['error'] = 'Could not find order with id = ' . $this->order_id;
      return;
    }

    $history[] = [
      'name' => $this->order_statuses->getOrderStatus(UC_IN_CHECKOUT_STATUS_ID),
      'order_status_id' => UC_IN_CHECKOUT_STATUS_ID,
      'date_added' => gmdate("Y-m-d H:i:s", (int) $order_time),
      'comment' => '',
    ];

    // statuses with comments
    $query = \Drupal::database()->select('uc_order_comments', 'c');
    $query->addField('c', 'order_status', 'name');
    $query->addField('c', 'order_status', 'order_status_id');
    $query->addField('c', 'created', 'date_added');
    $query->addField('c', 'message', 'comment');
    $query->condition('c.order_id', $this->order_id);
    $comments = $query->execute()->fetchAll();

    if ($comments) {
      for ($i = 0; $i < count($comments); $i++) {
        $comments[$i]->name = $this->order_statuses->getOrderStatus($comments[$i]->order_status_id);
        $comments[$i]->date_added = gmdate("Y-m-d H:i:s", (int) $comments[$i]->date_added);
        if ('-' == $comments[$i]->comment) {
          $comments[$i]->comment = '';
        }
        if (UC_PENDING_STATUS_ID == $comments[$i]->order_status_id) {
          $need_pending_info = FALSE;
        }
      }
    }

    // first status without comment
    if ($need_pending_info) {
      $pending_name = $this->order_statuses->getOrderStatus(UC_PENDING_STATUS_ID);
      $query = \Drupal::database()->select('uc_order_log', 'l');
      $query->addField('l', 'created');
      $query->condition('l.order_id', $this->order_id);
      $query->condition('l.changes', '%em class="placeholder">' . $this->order_statuses->getOrderStatus($pending_name) . '%', 'LIKE');
      $query->orderBy('l.created');
      $query->range(0, 1);
      $pending_time = $query->execute()->fetchField();
      if ($pending_time) {
        $history[] = [
          'name' => $pending_name,
          'order_status_id' => UC_PENDING_STATUS_ID,
          'date_added' => gmdate("Y-m-d H:i:s", (int) $pending_time),
          'comment' => '',
        ];
      }
    }

    foreach ($comments as $c) {
      $history[] = $c;
    }

    if ($history) {
      $this->response['status'] = TRUE;
      $this->response['response'] = [
        'orders' => $history,
        'statuses' => $this->order_statuses->getOrderStatuses(),
      ];
    }
  }

  /**
   *
   * @api {post} ucmob.orders?route=changestatus changeStatus
   * @apiName changeStatus
   * @apiGroup Orders
   * @apiVersion 2.0.1
   *
   * @apiParam {Token}   token     Your unique token.
   * @apiParam {Number}  order_id  Unique order ID.
   * @apiParam {Number}  status_id Unique status ID.
   * @apiParam {String}  comment   New comment for order status.
   * @apiParam {Boolean} inform    Status of the informing client.
   *
   * @apiSuccess {Boolean} status     true.
   * @apiSuccess {Number}  version    Current API version.
   * @apiSuccess {String}  name       Name of the new status.
   * @apiSuccess {String}  date_added Date of adding status.
   *
   * @apiSuccessExample Success-Response:
   * HTTP/1.1 200 OK
   * {
   *     "status": true,
   *     "version": "2.0.1",
   *     "response": {
   *         "name": "Processing",
   *         "date_added": "2017-08-09 11:37:20"
   *     }
   * }
   *
   * @apiErrorExample Error-Response:
   *
   * {
   *     "status": false,
   *     "version": "2.0.1",
   *     "error": "Missing some params"
   * }
   *
   */
  private function changeStatus() {
    $new_id = $this->request->get('status_id');

    if (!($new_id && $new = $this->order_statuses->getOrderStatus($new_id))) {
      $this->response['error'] = 'Missing some params';
      return;
    }

    $query = \Drupal::database()->select('uc_orders', o);
    $query->addField('o', 'order_status');
    $query->condition('o.order_id', $this->order_id);
    $old_id = $query->execute()->fetchField();

    if (!$old_id) {
      $this->response['error'] = 'Could not find order with id = ' . $this->order_id;
      return;
    }

    $old = $this->order_statuses->getOrderStatus($old_id);
    $key = 'Order status';
    $time = time();

    $query = \Drupal::database()->update('uc_orders');
    $query->fields([
      'order_status' => $new_id,
      'changed' => $time,
    ]);
    $query->condition('order_id', $this->order_id);
    $updated = $query->execute();

    if (!$updated) {
      $this->response['error'] = 'Database updating error';
      return;
    }

    $query = \Drupal::database()->insert('uc_order_comments');
    $query->fields([
      'order_id' => $this->order_id,
      'uid' => $this->userToken->getUserID(),
      'message' => $this->request->get('comment', '-'),
      'order_status' => $new_id,
      'notified' => $this->request->get('inform', 0),
      'created' => $time,
    ]);
    $query->execute();
    $this->addOrderLog([0 => compact('key', 'old', 'new', 'time')]);

    $this->response['status'] = TRUE;
    $this->response['response'] = [
      'name' => $new,
      'date_added' => gmdate("Y-m-d H:i:s", (int) $time),
    ];
  }

  /**
   *
   * @api {post} ucmob.orders?route=delivery changeOrderDelivery
   * @apiName changeOrderDelivery
   * @apiGroup Orders
   * @apiVersion 2.0.1
   *
   * @apiParam {Token}  token    Your unique token.
   * @apiParam {Number} order_id Unique order ID.
   * @apiParam {String} address  New shipping address.
   * @apiParam {String} city     New shipping city.
   *
   * @apiSuccess {Number}  version Current API version.
   * @apiSuccess {Boolean} status  true.
   *
   * @apiSuccessExample Success-Response:
   * HTTP/1.1 200 OK
   * {
   *     "status": true,
   *     "version": "2.0.1"
   * }
   *
   * @apiErrorExample Error-Response:
   *
   * {
   *     "status": false,
   *     "version": "2.0.1",
   *     "error": "Missing some params"
   * }
   *
   */
  private function changeOrderDelivery() {
    $new_address = $this->request->get('address');
    $new_city = $this->request->get('city');

    if (!($new_address || $new_city)) {
      $this->response['error'] = 'Missing some params';
      return;
    }

    $query = \Drupal::database()->select('uc_orders', o);
    if ($new_address) {
      $query->addField('o', 'delivery_street1');
    }
    if ($new_city) {
      $query->addField('o', 'delivery_city');
    }
    $query->condition('o.order_id', $this->order_id);
    $order = $query->execute()->fetchAssoc();

    if (!$order) {
      $this->response['error'] = 'Could not find order with id = ' . $this->order_id;
      return;
    }

    $time = time();
    $change_fields = [];
    $log_records = [];
    foreach ($order as $key => $old) {
      $new = ('delivery_city' == $key) ? $new_city : $new_address;
      $change_fields[$key] = $new;
      $log_records[] = compact('key', 'old', 'new', 'time');
    }
    $change_fields['changed'] = $time;

    $query = \Drupal::database()->update('uc_orders');
    $query->fields($change_fields);
    $query->condition('order_id', $this->order_id);
    $updated = $query->execute();

    if ($updated) {
      $this->addOrderLog($log_records);
      $this->response['status'] = TRUE;
    }
    else {
      $this->response['error'] = 'Database updating error';
    }
  }

  private function getMaxOrderPrice() {
    $query = \Drupal::database()->select('uc_orders', 'o');
    $query->addExpression("MAX(o.order_total)", 'max');
    $query->condition('o.order_status', $this->blocked_statuses, 'NOT IN');
    return $query->execute()->fetchField();
  }

  private function getPaymentName($payment_method) {
    $query = \Drupal::database()->select('config', 'c');
    $query->addField('c', 'data');
    $query->condition('c.name', 'uc_payment.method%', 'LIKE');
    $raw_statuses = $query->execute()->fetchAll();

    foreach ($raw_statuses as $row) {
      $tmp = unserialize($row->data);
      if ($payment_method == $tmp['id']) {
        return $tmp['label'];
      }
    }
    return FALSE;
  }

  private function addOrderLog($records) {
    $user_id = $this->userToken->getUserID();

    foreach ($records as $record) {
      $key = $record['key'];
      $old = $record['old'];
      $new = $record['new'];
      $entry = t('@key changed from %old to %new.', [
        '@key' => $key,
        '%old' => $old,
        '%new' => $new,
      ]);
      $markup = ['#markup' => $entry];
      $changes = \Drupal::service('renderer')->renderPlain($markup);

      $query = \Drupal::database()->insert('uc_order_log');
      $query->fields([
        'order_id' => $this->order_id,
        'uid' => $user_id,
        'changes' => $changes,
        'created' => $record['time'],
      ]);
      $query->execute();
    }
  }

}
