<?php

/**
 * @file
 * Contains \Drupal\uc_mobile_admin\UserToken.
 */

namespace Drupal\uc_mobile_admin;


class UserToken {

  public $token;

  public function __construct($token) {
    $this->token = $token;
  }

  public function getError() {
    $error = FALSE;
    if (empty($this->token)) {
      $error = 'You need to be logged!';
    }
    else {
      $query = \Drupal::database()->select('user_token_mob_api', 't');
      $query->addField('t', 'id');
      $query->condition('t.token', $this->token);
      if (empty($query->execute()->fetchField())) {
        $error = 'Your token is no longer relevant!';
      }
    }
    return $error;
  }

  public function getUserID() {
    $query = \Drupal::database()->select('user_token_mob_api', 't');
    $query->addField('t', 'user_id');
    $query->condition('t.token', $this->token);
    return $query->execute()->fetchField();
  }

}
