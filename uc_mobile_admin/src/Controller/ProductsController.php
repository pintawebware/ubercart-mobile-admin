<?php

/**
 * @file
 * Contains \Drupal\uc_mobile_admin\Controller\ProductsController.
 */

namespace Drupal\uc_mobile_admin\Controller;

use Drupal\uc_mobile_admin\ProductStatuses;

use Drupal\node\Entity\Node;

class ProductsController extends UcMainController {

  private $product_id;

  public function router($route) {
    if (!in_array($route, ['products', 'getcategories', 'getsubstatus'])) {
      $this->product_id = (int) $this->request->get('product_id');
      if (($route <> 'updateproduct') && !($this->product_id)) {
        $this->response['error'] = 'Can not found product with id = 0';
        return;
      }
    }
    switch ($route) {
      case 'products';
        $this->getProductsList();
        break;
      case 'productinfo';
        $this->getProductInfo();
        break;
      case 'getcategories':
        $this->getCategories();
        break;
      case 'getsubstatus':
        $this->getSubstatus();
        break;
      case 'updateproduct';
        if (0 < $this->product_id) {
          $this->updateProduct();
        }
        else {
          $this->addProduct();
        }
        break;
      case 'mainimage':
        $this->mainImage();
        break;
      case 'deleteimage':
        $this->deleteImage();
        break;
      default:
    }
  }

  /**
   *
   * @api {get} ucmob.products?route=products getProductsList
   * @apiName getProductsList
   * @apiGroup Products
   * @apiVersion 2.0.1
   *
   * @apiParam {Token}  token Your unique token.
   * @apiParam {Number} page  Number of the page.
   * @apiParam {Number} limit Limit of the orders for the page.
   * @apiParam {String} name  Name of the product.
   *
   * @apiSuccess {Array}   products      Array of the order products.
   * @apiSuccess {Number}  product_id     Unique product id.
   * @apiSuccess {String}  name          Name of the product.
   * @apiSuccess {String}  sku           SKU of the product.
   * @apiSuccess {Number}  quantity      Quantity of the product.
   * @apiSuccess {Number}  price         Price of the product.
   * @apiSuccess {Url}     image         Picture of the product.
   * @apiSuccess {String}  category      Category name.
   * @apiSuccess {String}  currency_code Default currency of the shop.
   * @apiSuccess {Number}  version       Current API version.
   * @apiSuccess {Boolean} status        true.
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
   *                 "name": "Bumblebee",
   *                 "sku": "566",
   *                 "quantity": "5",
   *                 "price": "10.00000",
   *                 "image": "http://my.site.com/sites/default/files/4.jpg",
   *                 "category": "transformers",
   *                 "currency_code": "USD"
   *             },
   *             {
   *                 "product_id": "2",
   *                 "name": "Optimus Prime",
   *                 "sku": "234",
   *                 "quantity": "8",
   *                 "price": "12.00000",
   *                 "image": "http://my.site.com/sites/default/files/1.jpg",
   *                 "category": "transformers",
   *                 "currency_code": "USD"
   *             },
   *         ]
   *     }
   * }
   *
   * @apiErrorExample Error-Response:
   *
   * {
   *     "status" : false,
   *     "version": "2.0.1",
   *     "error": "No product found"
   * }
   *
   */
  private function getProductsList() {
    $query = \Drupal::database()->select('node', 'n');
    $query->addField('p', 'nid', 'product_id');
    $query->addField('fd', 'title', 'name');
    $query->addField('p', 'model', 'sku');
    $query->addField('s', 'stock', 'quantity');
    $query->addField('p', 'price', 'price');
    $query->addField('f', 'uri', 'image');
    $query->addExpression('(SELECT c.name FROM node__taxonomy_catalog nc INNER JOIN taxonomy_term_field_data c ON c.tid = nc.taxonomy_catalog_target_id  WHERE nc.entity_id = p.nid LIMIT 1)', 'category');
    $query->join('uc_products', 'p', "p.nid = n.nid and p.vid = n.vid");
    $query->join('node_field_data', 'fd', 'fd.nid = p.nid');
    $query->leftJoin('uc_product_stock', 's', 's.nid = p.nid');
    $query->leftJoin('node__uc_product_image', 'pi', 'pi.entity_id = p.nid and pi.delta = 0');
    $query->leftJoin('file_managed', 'f', 'f.fid = pi.uc_product_image_target_id');
    if ($name = $this->request->get('name')) {
      $query->condition('fd.title', '%' . $name . '%', 'LIKE');
    }
    $query->orderBy('p.nid', 'DESC');
    $this->addQueryRange($query);
    $products = $query->execute()->fetchAll();

    if ($products) {
      for ($i = 0; $i < count($products); $i++) {
        $products[$i]->image = $products[$i]->image ? file_create_url($products[$i]->image) : '';
        if (!$products[$i]->category) {
          $products[$i]->category = '';
        }
        if (!$products[$i]->quantity) {
          $query = \Drupal::database()->select('uc_product_stock', 's');
          $query->addField('s', 'stock');
          $query->condition('s.sku', $products[$i]->sku);
          if (!$products[$i]->quantity = $query->execute()->fetchField()) {
            $products[$i]->quantity = '0';
          }
        }
        $products[$i]->currency_code = $this->getCurrency();
      }

      $this->response['status'] = TRUE;
      $this->response['response'] = [
        'products' => $products,
      ];
    }
    else {
      $this->response['error'] = 'No product found';
    }
  }

  /**
   *
   * @api {get} ucmob.products?route=productinfo getProductInfo
   * @apiName getProductInfo
   * @apiGroup Products
   * @apiVersion 2.0.1
   *
   * @apiParam {Token}  token     Your unique token.
   * @apiParam {Number} product_id Unique product id.
   *
   * @apiSuccess {Number}  product_id    Unique product id.
   * @apiSuccess {String}  name          Name of the product.
   * @apiSuccess {String}  sku           SKU of the product.
   * @apiSuccess {Number}  quantity      Quantity of the product.
   * @apiSuccess {Number}  price         Price of the product.
   * @apiSuccess {String}  description   Detail description of the product.
   * @apiSuccess {String}  status_name   Status of the product.
   * @apiSuccess {Array}   statuses      Array of the statuses of the product.
   * @apiSuccess {Array}   images        Array of the pictures of the product.
   * @apiSuccess {Array}   categories    Array of the categories of the product.
   * @apiSuccess {String}  currency_code Default currency of the shop.
   * @apiSuccess {Number}  version       Current API version.
   * @apiSuccess {Boolean} status        true.
   *
   * @apiSuccessExample Success-Response:
   * HTTP/1.1 200 OK
   * {
   *     "status": true,
   *     "version": "2.0.1",
   *     "response": {
   *         "product_id": "2,
   *         "name": "Optimus Prime",
   *         "sku": "A12212",
   *         "quantity": "7",
   *         "price": "12.00000",
   *         "description": "Optimus Prime supreme commander legion class",
   *         "status_name": "Published",
   *         "currency_code": "USD",
   *         "images": [
   *             {
   *                 "image_id": "-1",
   *                 "image": "http://my.site.com/sites/default/files/op1.jpg"
   *             },
   *             {
   *                 "image_id": "4",
   *                 "image": "http://my.site.com/sites/default/files/2.jpg"
   *             },
   *             {
   *                 "image_id": "7",
   *                 "image": "http://my.site.com/sites/default/files/legop.png"
   *             }
   *         ],
   *         "categories": [
   *             {
   *                 "category_id": "4",
   *                 "name": "transformers"
   *             },
   *             {
   *                 "category_id": "2",
   *                 "name": "boy"
   *             }
   *         ],
   *         "statuses": [
   *             {
   *                 "status_id": "1",
   *                 "name": "Published"
   *             },
   *             {
   *                 "status_id": "0",
   *                 "name": "Not published"
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
   *     "error": "Could not find product with id = 5"
   * }
   *
   */
  private function getProductInfo() {
    $product_statuses = new ProductStatuses();
    $query = \Drupal::database()->select('node', 'n');
    $query->addField('p', 'nid', 'product_id');
    $query->addField('fd', 'title', 'name');
    $query->addField('p', 'model', 'sku');
    $query->addField('s', 'stock', 'quantity');
    $query->addField('p', 'price', 'price');
    $query->addField('b', 'body_value', 'description');
    $query->addField('fd', 'status', 'status_name');
    $query->join('uc_products', 'p', "p.nid = n.nid and p.vid = n.vid");
    $query->join('node_field_data', 'fd', 'fd.nid = n.nid');
    $query->leftJoin('node__body', 'b', 'b.entity_id = n.nid and b.delta = 0');
    $query->leftJoin('uc_product_stock', 's', 's.nid = n.nid');
    $query->condition('n.nid', $this->product_id);
    $product = $query->execute()->fetchAssoc();

    if (!$product) {
      $this->response['error'] = 'Could not find product with id = ' . $this->product_id;
      return;
    }

    if (!empty($product['description'])) {
      $site = \Drupal::request()->server->get('REQUEST_SCHEME') . '://' . \Drupal::request()->server->get('SERVER_NAME');
      $product['description'] = str_replace('src="/sites/', $site, $product['description']);
    }
    else {
      $product['description'] = '';
    }
    if (!$product['quantity']) {
      $query = \Drupal::database()->select('uc_product_stock', 's');
      $query->addField('s', 'stock');
      $query->condition('s.sku', $product['sku']);
      if (!$product['quantity'] = $query->execute()->fetchField()) {
        $product['quantity'] = '0';
      }
    }

    $product['status_name'] = $product_statuses->getStatusName($product['status_name']);
    $product['currency_code'] = $this->getCurrency();

    $query = \Drupal::database()->select('node__taxonomy_catalog', 'nc');
    $query->addField('c', 'tid', 'category_id');
    $query->addField('c', 'name', 'name');
    $query->leftJoin('taxonomy_term_field_data', 'c', 'c.tid = nc.taxonomy_catalog_target_id');
    $query->condition('nc.entity_id', $this->product_id);
    $query->orderBy('c.tid', 'DESC');
    $categories = $query->execute()->fetchAll();
    if (!$categories) {
      $categories = [];
    }

    $this->response['status'] = TRUE;
    $this->response['response'] = $product;
    $this->response['response']['images'] = $this->getImages();
    $this->response['response']['categories'] = $categories;
    $this->response['response']['statuses'] = $product_statuses->getProductStatuses();

  }

  /**
   *
   * @api {get} ucmob.products?route=getcategories getCategories
   * @apiName getCategories
   * @apiGroup Products
   * @apiVersion 2.0.1
   *
   * @apiParam {Token}  token       Your unique token.
   * @apiParam {Number} category_id Unique category ID.
   *
   * @apiSuccess {Array}   categories Array of the child categories of the
   *   category.
   * @apiSuccess {Number}  version    Current API version.
   * @apiSuccess {Boolean} status     true.
   *
   * @apiSuccessExample Success-Response:
   * HTTP/1.1 200 OK
   * {
   *     "status": true,
   *     "version": "2.0.1"
   *     "response": {
   *         "categories": [
   *             {
   *                 "category_id": "1",
   *                 "name": "Hardware",
   *                 "parent": true
   *             },
   *             {
   *                 "category_id": "2",
   *                 "name": "Software",
   *                 "parent": true
   *             },
   *             {
   *                 "category_id": "21",
   *                 "name": "Gadgets",
   *                 "parent": false
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
   *     "error": "Missing some params"
   * }
   *
   */
  private function getCategories() {
    if (!($category_id = (int) $this->request->get('category_id'))) {
      $this->response['error'] = 'Missing some params';
      return;
    }
    if (1 > $category_id) {
      $category_id = 0;
    }
    $query = \Drupal::database()->select('taxonomy_term_hierarchy', 'h');
    $query->addField('f', 'tid', 'category_id');
    $query->addField('f', 'name', 'name');
    $query->leftJoin('taxonomy_term_field_data', 'f', 'f.tid = h.tid');
    $query->condition('h.parent', $category_id);
    $categories = $query->execute()->fetchAll();

    // if the category has no child, then show this category
    if (!$categories) {
      $query = \Drupal::database()->select('taxonomy_term_field_data', 'f');
      $query->addField('f', 'tid', 'category_id');
      $query->addField('f', 'name', 'name');
      $query->condition('f.tid', $category_id);
      $categories = $query->execute()->fetchAll();
    }

    if ($categories) {
      for ($i = 0; $i < count($categories); $i++) {
        $query = \Drupal::database()->select('taxonomy_term_hierarchy', 'h');
        $query->condition('h.parent', $categories[$i]->category_id);
        $childes_count = $query->countQuery()->execute()->fetchField();
        $categories[$i]->parent = (0 < $childes_count) ? TRUE : FALSE;
      }

      $this->response['status'] = TRUE;
      $this->response['response'] = ['categories' => $categories];
    }
    else {
      $this->response['error'] = 'Could not find category with id = ' . $category_id;
    }
  }

  /**
   *
   * @api {get} ucmob.products?route=getsubstatus getSubstatus
   * @apiName getSubstatus
   * @apiGroup Products
   * @apiVersion 2.0.1
   *
   * @apiParam {Token} token Your unique token.
   *
   * @apiSuccess {Array}   statuses Array of the statuses of the product.
   * @apiSuccess {Number}  version Current API version.
   * @apiSuccess {Boolean} status  true.
   *
   * @apiSuccessExample Success-Response:
   * HTTP/1.1 200 OK
   * {
   *     "status": true,
   *     "version": "2.0.1",
   *     "response": {
   *         "statuses": [
   *             {
   *                 "status_id": "1",
   *                 "name": "Published"
   *             },
   *             {
   *                 "status_id": "0",
   *                 "name": "Not published"
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
   *     "error": "You need to be logged!"
   * }
   *
   */
  private function getSubstatus() {
    $product_statuses = new ProductStatuses();
    $statuses = $product_statuses->getProductStatuses();
    if ($statuses) {
      $this->response['status'] = TRUE;
      $this->response['response'] = ['statuses' => $statuses];
    }
  }

  /**
   *
   * @api {post} api.php?route=updateproduct updateProduct
   * @apiName updateProduct
   * @apiGroup Products
   * @apiVersion 2.0.1
   *
   * @apiParam {Token}  token       Your unique token.
   * @apiParam {Number} product_id  Unique product ID.
   * @apiParam {String} name        Name of the product.
   *                                Required fields for creating new product.
   * @apiParam {String} sku         SKU of the product.
   *                                Required fields for creating new product.
   * @apiParam {Number} price       Price of the product.
   * @apiParam {Number} quantity    Quantity of the product.
   * @apiParam {String} description Description of the product.
   * @apiParam {Number} status      ID of the status of the product (1|0).
   * @apiParam {Array}  categories  Array of categories of the product.
   * @apiParam {Files}  image       Array of the files of the pictures of the
   *   product.
   *
   * @apiSuccess {Number}  product_id Unique product id.
   * @apiSuccess {Array}   images    Array of the pictures of the product.
   * @apiSuccess {Number}  version   Current API version.
   * @apiSuccess {Boolean} status    true.
   *
   * @apiSuccessExample Success-Response:
   * HTTP/1.1 200 OK
   * {
   *     "status": true,
   *     "version": "2.0.1",
   *     "response": {
   *         "product_id": 29,
   *         "images": [
   *             {
   *                 "image_id": "-1",
   *                 "image": "http://my.site.com/sites/default/files/6_2.jpg"
   *             },
   *             {
   *                 "image_id": "93",
   *                 "image": "http://my.site.com/sites/default/files/5_1.jpg"
   *             },
   *             {
   *                 "image_id": "94",
   *                 "image": "http://my.site.com/sites/default/files/6_3.jpg"
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
   *     "error": "Missing some params"
   * }
   *
   */
  private function updateProduct() {
    $product = Node::load($this->product_id);

    if ($title = $this->request->get('name')) {
      $product->title = $title;
    }
    if ($model = $this->request->get('sku')) {
      $product->model = $model;
    }
    if (($status = $this->request->get('status')) || ('0' === $status)) {
      $product->status = $status;
    }
    if ($price = $this->request->get('price')) {
      $product->price = $price;
    }
    if ($body = $this->request->get('description')) {
      $product->body = [['value' => $body]];
    }
    if ($categories = $this->request->get('categories')) {
      $product->taxonomy_catalog = [];
      if (is_array($categories)) {
        foreach ($categories as $id) {
          $product->taxonomy_catalog[] = ['target_id' => $id,];
        }
      }
    }
    if ($files_images = $this->request->files->get('image')) {
      $uid = $this->userToken->getUserID();
      foreach ($files_images as $image) {
        $file = file_save_data(file_get_contents($image), 'public://' . $image->getClientOriginalName(), FILE_EXISTS_RENAME);

        $file_usage = \Drupal::service('file.usage');
        $file_usage->add($file, 'file', 'node', $uid);
        $file->save();

        $product->uc_product_image[] = [
          'target_id' => $file->id(),
          'alt' => pathinfo($file->getFilename())['filename'],
          'title' => '',
        ];
      }
    }

    // update uc_product_stock table
    if (($stock = $this->request->get('quantity'))) {
      $new = TRUE;
      $model = $product->get('model')->getValue()[0]['value'];
      $stock_fields = [];
      $stock_fields['stock'] = $stock;
      $query = \Drupal::database()->select('uc_product_stock', 's');
      $query->condition('s.sku', $model);
      if (0 < $query->countQuery()->execute()->fetchField()) {
        $new = FALSE;
        $query = \Drupal::database()->update('uc_product_stock');
        $query->condition('sku', $model);
      }
      else {
        $query = \Drupal::database()->select('uc_product_stock', 's');
        $query->condition('s.nid', $this->product_id);
        if (0 < $query->countQuery()->execute()->fetchField()) {
          $new = FALSE;
          $query = \Drupal::database()->update('uc_product_stock');
          $query->condition('nid', $this->product_id);
        }
      }
      if ($new) {
        $query = \Drupal::database()->insert('uc_product_stock');
        $stock_fields['sku'] = $model;
        $stock_fields['nid'] = $this->product_id;
        $stock_fields['active'] = 1;   //stock is being tracked for this product
      }
      $query->fields($stock_fields);
      $query->execute();
    }

    if ($product->save()) {
      $this->response['response'] = [
        'product_id' => $this->product_id,
        'images' => $this->getImages(),
      ];
      $this->response['status'] = TRUE;
    }
  }

  private function addProduct() {

    if (!($title = $this->request->get('name')) || !($model = $this->request->get('sku'))) {
      $this->response['error'] = 'Missing some params';
      return;
    }

    $uid = $this->userToken->getUserID();

    $product = [
      'type' => 'product',
      'model' => $model,
      'title' => $title,
      'uid' => $uid,
    ];

    if (($status = $this->request->get('status')) || ('0' === $status)) {
      $product['status'] = $status;
    }
    if ($price = $this->request->get('price')) {
      $product['price'] = $price;
    }
    if ($body = $this->request->get('description')) {
      $product['body'] = [
        [
          'value' => $body,
          'summary' => '',
          'format' => 'basic_html',
        ],
      ];
    }
    if ($categories = $this->request->get('categories')) {
      $product['taxonomy_catalog'] = [];
      foreach ($categories as $id) {
        $product['taxonomy_catalog'][] = ['target_id' => $id,];
      }
    }
    if ($files_images = $this->request->files->get('image')) {
      $product['uc_product_image'] = [];
      foreach ($files_images as $image) {
        $file = file_save_data(file_get_contents($image), 'public://' . $image->getClientOriginalName(), FILE_EXISTS_RENAME);

        $file_usage = \Drupal::service('file.usage');
        $file_usage->add($file, 'file', 'node', $uid);
        $file->save();

        $product['uc_product_image'][] = [
          'target_id' => $file->id(),
          'alt' => pathinfo($file->getFilename())['filename'],
          'title' => '',
        ];
      }
    }

    $node = Node::create($product);
    $node->save();
    $this->product_id = $node->id();

    if ($node) {
      // update uc_product_stock table
      if (($stock = $this->request->get('quantity'))) {
        $stock_fields = [];
        $stock_fields['stock'] = $stock;
        $query = \Drupal::database()->select('uc_product_stock', 's');
        $query->condition('s.sku', $product['model']);
        if (0 < $query->countQuery()->execute()->fetchField()) {
          $query = \Drupal::database()->update('uc_product_stock');
          $query->condition('sku', $product['model']);
        }
        else {
          $query = \Drupal::database()->insert('uc_product_stock');
          $stock_fields['sku'] = $product['model'];
          $stock_fields['nid'] = $this->product_id;
          $stock_fields['active'] = 1;   //stock is being tracked for this product
        }
        $query->fields($stock_fields);
        $query->execute();
      }

      $this->response['response'] = [
        'product_id' => $this->product_id,
        'images' => $this->getImages(),
      ];
      $this->response['status'] = TRUE;
    }
  }

  /**
   *
   * @api {post} ucmob.products?route=mainimage mainImage
   * @apiName mainImage
   * @apiGroup Products
   * @apiVersion 2.0.1
   *
   * @apiParam {Token}  token       Your unique token.
   * @apiParam {Number} product_id  Unique product ID.
   * @apiParam {Number} image_id    Unique image ID.
   *
   * @apiSuccess {Number}  version   Current API version.
   * @apiSuccess {Boolean} status    true.
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
   *     "status" : false,
   *     "version": "2.0.1",
   *     "error": "Missing some params"
   * }
   *
   */
  private function mainImage() {
    if (!($image_id = (int) $this->request->get('image_id')) || (1 > $image_id)) {
      $this->response['error'] = 'Missing some params';
      return;
    }
    $query = \Drupal::database()->select('node__uc_product_image', 'i');
    $query->addField('i', 'revision_id');
    $query->condition('i.entity_id', $this->product_id);
    $query->condition('i.uc_product_image_target_id', $image_id);
    $revision_id = $query->execute()->fetchField();

    if (!$revision_id) {
      $this->response['error'] = 'Could not find image with id = ' . $image_id . ' for product with id = ' . $this->product_id;
      return;
    }

    $query = \Drupal::database()->select('node__uc_product_image', 'i');
    $query->addField('i', 'uc_product_image_target_id', 'image_id');
    $query->condition('i.entity_id', $this->product_id);
    $query->condition('i.uc_product_image_target_id', $image_id, '<>');
    $query->orderBy('i.delta');
    $images = $query->execute()->fetchAll();

    // reset order of images
    $this->setImageDelta($image_id, $revision_id, 9999);
    for ($i = count($images); $i > 0; $i--) {
      $this->setImageDelta($images[$i - 1]->image_id, $revision_id, $i);
    }
    $this->setImageDelta($image_id, $revision_id, 0);

    // cache cleaning
    $this->cleanProductCache();

    $this->response['status'] = TRUE;
  }

  /**
   *
   * @api {post} ucmob.products?route=deleteimage deleteImage
   * @apiName deleteImage
   * @apiGroup Products
   * @apiVersion 2.0.1
   *
   * @apiParam {Token}  token       Your unique token.
   * @apiParam {Number} product_id  Unique product ID.
   * @apiParam {Number} image_id    Unique image ID.
   *
   * @apiSuccess {Number}  version   Current API version.
   * @apiSuccess {Boolean} status    true.
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
   *     "status" : false,
   *     "version": "2.0.1",
   *     "error": "Missing some params"
   * }
   *
   */
  private function deleteImage() {
    if (!($image_id = (int) $this->request->get('image_id'))) {
      $this->response['error'] = 'Missing some params';
      return;
    }

    if (1 > $image_id) {
      $query = \Drupal::database()->select('node__uc_product_image', 'i');
      $query->addField('i', 'uc_product_image_target_id');
      $query->condition('i.entity_id', $this->product_id);
      $query->condition('i.delta', 0);
      $real_image_id = $query->execute()->fetchField();

      if (!$real_image_id) {
        $this->response['error'] = 'Could not find image for product with id = ' . $this->product_id;
        return;
      }
      $image_id = $real_image_id;
    }

    $query = \Drupal::database()->select('node__uc_product_image', 'i');
    $query->addField('i', 'revision_id');
    $query->leftJoin('file_managed', 'f', 'f.fid = i.uc_product_image_target_id');
    $query->condition('i.entity_id', $this->product_id);
    $query->condition('i.uc_product_image_target_id', $image_id);
    $revision_id = $query->execute()->fetchField();

    if (!$revision_id) {
      $this->response['error'] = 'Could not find image with id = ' . $image_id . ' for product with id = ' . $this->product_id;
      return;
    }

    $query = \Drupal::database()->delete('node__uc_product_image');
    $query->condition('uc_product_image_target_id', $image_id);
    $query->condition('entity_id', $this->product_id);

    if ($query->execute()) {
      $query = \Drupal::database()->delete('node_revision__uc_product_image');
      $query->condition('uc_product_image_target_id', $image_id);
      $query->condition('entity_id', $this->product_id);
      $query->condition('revision_id', $revision_id);
      $query->execute();

      $query = \Drupal::database()->select('node__uc_product_image', 'i');
      $query->addField('i', 'uc_product_image_target_id', 'image_id');
      $query->condition('i.entity_id', $this->product_id);
      $query->orderBy('i.delta');
      $images = $query->execute()->fetchAll();

      // reset order of images
      for ($i = 0; $i < count($images); $i++) {
        $this->setImageDelta($images[$i]->image_id, $revision_id, $i);
      }

      //   for deleting file from disk, cache and file_managed table:
      //   file_delete($image_id);

      // changing usage count
      $query = \Drupal::database()->select('file_usage', 'u');
      $query->addField('u', 'count');
      $query->condition('u.fid', $image_id);
      $query->condition('u.id', $this->product_id);
      $count = $query->execute()->fetchField();
      if ($count) {
        if (0 < ($count - 1)) {
          $query = \Drupal::database()->update('file_usage');
          $query->fields(['count' => $count - 1]);
        }
        else {
          $query = \Drupal::database()->delete('file_usage');
        }
        $query->condition('fid', $image_id);
        $query->condition('id', $this->product_id);
        $query->execute();
      }

      // cache cleaning
      $this->cleanProductImageCache($image_id);

      $this->response['status'] = TRUE;
    }
  }

  private function setImageDelta($image_id, $revision_id, $delta) {
    $query = \Drupal::database()->update('node__uc_product_image');
    $query->fields(['delta' => $delta]);
    $query->condition('uc_product_image_target_id', $image_id);
    $query->condition('entity_id', $this->product_id);
    $query->execute();

    $query = \Drupal::database()->update('node_revision__uc_product_image');
    $query->fields(['delta' => $delta]);
    $query->condition('uc_product_image_target_id', $image_id);
    $query->condition('entity_id', $this->product_id);
    $query->condition('revision_id', $revision_id);
    $query->execute();
  }

  private function cleanProductCache() {
    $query = \Drupal::database()->delete('cache_entity');
    $group = $query->orConditionGroup();
    $group->condition('cid', '%node%' . $this->product_id, 'LIKE');
    $query->condition($group);
    $query->execute();
  }

  private function cleanProductImageCache($image_id) {
    $query = \Drupal::database()->delete('cache_entity');
    $group = $query->orConditionGroup();
    $group->condition('cid', '%file%' . $image_id, 'LIKE');
    $group->condition('cid', '%node%' . $this->product_id, 'LIKE');
    $query->condition($group);
    $query->execute();
  }

  private function getImages() {
    $query = \Drupal::database()->select('node__uc_product_image', 'i');
    $query->addField('f', 'fid', 'image_id');
    $query->addField('f', 'uri', 'image');
    $query->addField('i', 'delta', 'delta');
    $query->leftJoin('file_managed', 'f', 'f.fid = i.uc_product_image_target_id');
    $query->condition('i.entity_id', $this->product_id);
    $query->orderBy('i.delta');
    $images = $query->execute()->fetchAll();

    if ($images) {
      for ($i = 0; $i < count($images); $i++) {
        $images[$i]->image = file_create_url($images[$i]->image);
        if (0 == $images[$i]->delta) {
          $images[$i]->image_id = '-1';
        }
        unset ($images[$i]->delta);
      }
    }
    else {
      $images = [];
    }
    return $images;
  }
}