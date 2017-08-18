<?php

/**
 * @file
 * Contains \Drupal\uc_mobile_admin\Controller\MainController.
 */

namespace Drupal\uc_mobile_admin\Controller;

use Drupal\Core\Controller\ControllerBase;

class MainController extends ControllerBase {

  protected $response = [];

  public function __construct()  {
    $this->response['status'] = FALSE;
    $this->response['version'] = API_VERSION;
  }

}
