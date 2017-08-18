<?php

/**
 * @file
 * Contains \Drupal\uc_mobile_admin\Controller\AuthController.
 */

namespace Drupal\uc_mobile_admin\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

class AuthController extends MainController {

  /**
   *
   * @api {post} ucmob.login Login
   * @apiName Login
   * @apiVersion 2.0.1
   * @apiGroup Auth
   *
   * @apiParam {String} username     User unique username.
   * @apiParam {String} password     User's  password.
   * @apiParam {String} os_type      User's device's os_type for firebase
   *   notifications.
   * @apiParam {String} device_token User's device's token for firebase
   *   notifications.
   *
   * @apiSuccess {Number} version Current API version.
   * @apiSuccess {String} token   Token.
   *
   * @apiSuccessExample Success-Response:
   * HTTP/1.1 200 OK
   * {
   *     "status": true
   *     "version": "2.0.1",
   *     "response":
   *     {
   *         "token": "e9cf23a55429aa79c3c1651fe698ed7b",
   *     }
   * }
   *
   * @apiErrorExample Error-Response:
   *
   * {
   *     "status": false
   *     "version": "2.0.1",
   *     "error": "Incorrect username or password",
   * }
   *
   */
  public function login(Request $request) {
    $username = $request->get('username');
    $password = $request->get('password');
    $token = $request->get('token');

    if (isset($username) && isset($password) && !isset($token)) {
      if ($user_id = \Drupal::service('user.auth')
        ->authenticate($username, $password)) {
        $query = \Drupal::database()->select('user__roles', 'r');
        $query->fields('r', ['roles_target_id']);
        $query->condition('r.entity_id', $user_id);
        $role = $query->execute()->fetchField();

        if ('administrator' == $role) {
          $query = \Drupal::database()->select('user_token_mob_api', 't');
          $query->fields('t', ['token']);
          $query->condition('t.user_id', $user_id);
          $token = $query->execute()->fetchField();

          if (empty($token)) {
            $token = md5(mt_rand());
            $query = \Drupal::database()->insert('user_token_mob_api');
            $query->fields([
              'user_id',
              'token',
            ]);
            $query->values([
              $user_id,
              $token,
            ]);
            $query->execute();
          }
          $device_token = $request->get('device_token');
          if (isset($device_token)) {
            $os_type = $request->get('os_type', '');
            $query = \Drupal::database()->select('user_device_mob_api', 'd');
            $query->fields('d', ['id']);
            $query->condition('d.device_token', $device_token);
            $device_id = $query->execute()->fetchField();
            if (empty($device_id)) {
              $query = \Drupal::database()->insert('user_device_mob_api');
              $query->fields([
                'user_id',
                'device_token',
                'os_type',
              ]);
              $query->values([
                $user_id,
                $device_token,
                $os_type,
              ]);
              $query->execute();
            }
          }
          $this->response['status'] = TRUE;
          $this->response['response'] = ['token' => $token];
        }
      }

      if (!$this->response['status']) {
        $this->response['error'] = 'Incorrect email or password';
      }
    }
    else {
      $this->response['error'] = 'Parameters error';
    }

    return new JsonResponse($this->response);
  }

  /**
   *
   * @api {post} ucmob.deletedevicetoken deleteUserDeviceToken
   * @apiName deleteUserDeviceToken
   * @apiGroup Auth
   * @apiVersion 2.0.1
   *
   * @apiParam {String} old_token User's device's token for firebase notifications.
   *
   * @apiSuccess {Number}  version Current API version.
   * @apiSuccess {Boolean} status  true.
   *
   * @apiSuccessExample Success-Response:
   * HTTP/1.1 200 OK
   * {
   *     "status": true
   *     "version": "2.0.1",
   *     "response":
   *     {
   *        "status": true
   *        "version": "2.0.1",
   *     }
   * }
   *
   * @apiErrorExample Error-Response:
   *
   * {
   *     "status": false
   *     "version": "2.0.1",
   *     "error": "Parameters error",
   * }
   *
   */
  public function deleteDeviceToken(Request $request) {
    $old_token = $request->get('old_token');

    if (!empty($old_token)) {
      $query = \Drupal::database()->select('user_device_mob_api', 'd');
      $query->fields('d', ['id']);
      $query->condition('d.device_token', $old_token);
      $device_id = $query->execute()->fetchField();
      if (!empty($device_id)) {
        $query = \Drupal::database()->delete('user_device_mob_api');
        $query->condition('device_token', $old_token);
        $query->execute();
        $this->response['status'] = TRUE;
        $this->response['response'] = ['version' => API_VERSION, 'status' => TRUE];
      }
    }
    if (!$this->response['status']) {
      $this->response['error'] = 'Parameters error';
    }

    return new JsonResponse($this->response);
  }

  /**
   *
   * @api {post} ucmob.updatedevicetoken updateUserDeviceToken
   * @apiName updateUserDeviceToken
   * @apiGroup Auth
   * @apiVersion 2.0.1
   *
   * @apiParam {String} new_token User's device's new token for firebase notifications.
   * @apiParam {String} old_token User's device's old token for firebase notifications.
   *
   * @apiSuccess {Number}  version Current API version.
   * @apiSuccess {Boolean} status  true.
   *
   * @apiSuccessExample Success-Response:
   * HTTP/1.1 200 OK
   * {
   *     "status": true
   *     "response":
   *     {
   *        "version": "2.0.1",
   *        "status": true
   *     }
   *     "version": "2.0.1",
   * }
   *
   * @apiErrorExample Error-Response:
   *
   * {
   *     "status": false
   *     "error": "Parameters error",
   *     "version": "2.0.1",
   * }
   *
   */
  public function updateDeviceToken(Request $request) {
    $old_token = $request->get('old_token');
    $new_token = $request->get('new_token');

    if (!empty($old_token) && !empty($new_token)) {
      $query = \Drupal::database()->select('user_device_mob_api', 'd');
      $query->fields('d', ['id']);
      $query->condition('d.device_token', $old_token);
      $device_id = $query->execute()->fetchField();
      if (!empty($device_id)) {
        $query = \Drupal::database()->update('user_device_mob_api');
        $query->fields([
          'device_token' => $new_token,
        ]);
        $query->condition('device_token', $old_token);
        $query->execute();

        $this->response['status'] = TRUE;
        $this->response['response'] = ['status' => TRUE, 'version' => API_VERSION];
      }
    }
    if (!$this->response['status']) {
      $this->response['error'] = 'Parameters error';
    }

    return new JsonResponse($this->response);
  }

}
