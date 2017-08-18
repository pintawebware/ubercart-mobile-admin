<?php

/**
 * @file
 * Contains \Drupal\uc_mobile_admin\Controller\UcMainController.
 */

namespace Drupal\uc_mobile_admin\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\uc_mobile_admin\UserToken;

class UcMainController extends MainController {

  protected $userToken;

  protected $request;

  public function main(Request $request) {
    $this->userToken = new UserToken($request->get('token', ''));
    if ($error = $this->userToken->getError()) {
      $this->response['error'] = $error;
    }
    else {
      $this->request = $request;
      $route = $request->get('route');
      $this->router($route);
    }
    return new JsonResponse($this->response);
  }

  public function router($route) {
  }

  protected function getCurrency() {
    $query = \Drupal::database()->select('config', 'c');
    $query->addField('c', 'data');
    $query->condition('c.name', 'uc_store.settings');
    $data = $query->execute()->fetchField();
    return unserialize($data)['currency']['code'];
  }

  protected function addQueryRange(&$query){
    if (($limit = (int) $this->request->get('limit')) && (0 < $limit)) {
      $page = (int) $this->request->get('page', 1);
      $page = (0 < $page) ? $page : 1;
      $query->range($limit * $page - $limit, $limit);
    }
  }

}
