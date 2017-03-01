<?php
/**
 * R - Ultra lightweight REST server
 *
 * PHP version 5
 *
 * @category   PHP
 * @package    REST
 * @subpackage Core
 * @author     Restya <info@restya.com>
 * @copyright  2014-2017 Restya
 * @license    http://restya.com/ Restya Licence
 * @link       http://restya.com/
 * @todo       Fix code duplication & make it really lightweight
 * @since      2013-08-23
 */
class Auth
{
    public function __invoke($request, $response, $next)
    {
        global $authUser;
        $requestUri = $request->getRequestTarget();
        $queryParams = $request->getQueryParams();
        if (!empty($queryParams['token'])) {
            $conditions = array(
                $queryParams['token']
            );
            $responses = executeQuery("SELECT user_id as username, expires, scope, client_id FROM oauth_access_tokens WHERE access_token = ?", $conditions);
            $expires = strtotime($responses['expires']);
            if (empty($responses) || !empty($responses['error']) || ($responses['client_id'] != 6664115227792148 && $responses['client_id'] != OAUTH_CLIENTID) || ($expires > 0 && $expires < time() && $responses['client_id'] != 7857596005287233 && $responses['client_id'] != 1193674816623028)) {
                $responses['error']['type'] = 'OAuth';
                echo json_encode($responses);
                header($_SERVER['SERVER_PROTOCOL'] . ' 401 Unauthorized', true, 401);
                exit;
            }
            $user = $role_links = array();
            if (!empty($responses['username'])) {
                $qry_val_arr = array(
                    $responses['username']
                );
                $user = executeQuery('SELECT * FROM users WHERE username = ?', $qry_val_arr);
                $qry_val_arr = array(
                    $user['role_id']
                );
                $role_links = executeQuery('SELECT * FROM role_links_listing WHERE id = ?', $qry_val_arr);
            }
            $authUser = array_merge($role_links, $user);
            $response = $next($request, $response);
        } else {
            $response = $next($request, $response);
        }
        return $response;
    }
}
