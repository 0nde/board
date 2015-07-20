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
 * @copyright  2014 Restya
 * @license    http://www.restya.com/ Restya Licence
 * @link       http://www.restya.com
 * @todo       Fix code duplication & make it really lightweight
 * @since      2013-08-23
 */
$r_debug = '';
$authUser = array();
$_server_protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off') ? 'https' : 'http';
$_server_domain_url = $_server_protocol . '://' . $_SERVER['HTTP_HOST']; // http://localhost
header('Access-Control-Allow-Origin: ' . $_server_domain_url);
header('Access-Control-Allow-Methods: *');
require_once ('config.inc.php');
require_once ('libs/vendors/finediff.php');
require_once ('libs/core.php');
/** * Common method to handle GET method
 *
 * @param  $r_resource_cmd
 * @param  $r_resource_vars
 * @param  $r_resource_filters
 * @return
 */
function r_get($r_resource_cmd, $r_resource_vars, $r_resource_filters)
{
    global $r_debug, $db_lnk, $authUser, $_server_domain_url;
    // switch case.. if taking more length, then associative array...
    $sql = false;
    $response = array();
    $pg_params = array();
    switch ($r_resource_cmd) {
    case '/users/logout':
        $response['user'] = $authUser = array();
        break;

    case '/boards':
        if (!empty($r_resource_filters['type']) && $r_resource_filters['type'] == 'simple') {
            $sql = 'SELECT row_to_json(d) FROM (SELECT * FROM simple_board_listing ul ';
            if (!empty($authUser) && $authUser['role_id'] != 1) {
                $s_result = pg_query_params($db_lnk, 'SELECT board_id FROM board_stars WHERE user_id = $1', array(
                    $authUser['id']
                ));
                $response['starred_boards'] = array();
                while ($row = pg_fetch_assoc($s_result)) {
                    $response['starred_boards'][] = $row['board_id'];
                }
                $s_result = pg_query_params($db_lnk, 'SELECT board_id FROM boards_users WHERE user_id = $1', array(
                    $authUser['id']
                ));
                $response['user_boards'] = array();
                while ($row = pg_fetch_assoc($s_result)) {
                    $response['user_boards'][] = $row['board_id'];
                }
                $board_ids = array_merge($response['starred_boards'], $response['user_boards']);
                $ids = 0;
                if (!empty($board_ids)) {
                    $board_ids = array_unique($board_ids);
                    $ids = '{' . implode($board_ids, ',') . '}';
                }
                $sql.= 'WHERE ul.id =ANY($1)';
                array_push($pg_params, $ids);
            }
            $sql.= ' ORDER BY id DESC) as d ';
            if ($authUser['role_id'] != 1 && empty($board_ids)) {
                $sql = false;
            }
        } else {
            $sql = 'SELECT row_to_json(d) FROM (SELECT * FROM boards_listing ul ';
            if (!empty($authUser) && $authUser['role_id'] != 1) {
                $s_result = pg_query_params($db_lnk, 'SELECT board_id FROM board_subscribers WHERE user_id = $1', array(
                    $authUser['id']
                ));
                $response['starred_boards'] = array();
                while ($row = pg_fetch_assoc($s_result)) {
                    $response['starred_boards'][] = $row['board_id'];
                }
                $s_result = pg_query_params($db_lnk, 'SELECT board_id FROM boards_users WHERE user_id = $1', array(
                    $authUser['id']
                ));
                $response['user_boards'] = array();
                while ($row = pg_fetch_assoc($s_result)) {
                    $response['user_boards'][] = $row['board_id'];
                }
                $board_ids = array_merge($response['starred_boards'], $response['user_boards']);
                $ids = 0;
                if (!empty($board_ids)) {
                    $board_ids = array_unique($board_ids);
                    $ids = '{' . implode($board_ids, ',') . '}';
                }
                $sql.= 'WHERE ul.id = ANY ($1)';
                array_push($pg_params, $ids);
            }
            $sql.= ' ORDER BY id DESC) as d ';
            if ($authUser['role_id'] != 1 && empty($board_ids)) {
                $sql = false;
            }
        }
        break;

    case '/users':
        $response['users'] = array();
        $sql = 'SELECT row_to_json(d) FROM (SELECT * FROM users_listing ul  ORDER BY id DESC) as d ';
        break;

    case '/settings/?':
        $response = array();
        $sql = false;
        $s_sql = 'SELECT id, name, parent_id FROM setting_categories WHERE parent_id IS NULL ORDER BY "order" ASC';
        $s_result = pg_query_params($db_lnk, $s_sql, array());
        while ($row = pg_fetch_assoc($s_result)) {
            if ($row['id'] == $r_resource_vars['settings'] || $row['parent_id'] == $r_resource_vars['settings']) {
                $s_sql = 'SELECT s.*, sc.name as category_name FROM settings s LEFT JOIN setting_categories sc ON sc.id = s.setting_category_id  WHERE  setting_category_id = $1 OR setting_category_parent_id = $2 ORDER BY "order" ASC';
                $ss_result = pg_query_params($db_lnk, $s_sql, array(
                    $row['id'],
                    $row['id']
                ));
                while ($srow = pg_fetch_assoc($ss_result)) {
                    $row['settings'][] = $srow;
                }
            }
            $response[] = $row;
        }
        break;

    case '/email_templates/?':
        $response = array();
        $sql = false;
        $s_sql = 'SELECT id, display_name FROM email_templates ORDER BY id ASC';
        $s_result = pg_query_params($db_lnk, $s_sql, array());
        while ($row = pg_fetch_assoc($s_result)) {
            if ($row['id'] == $r_resource_vars['email_templates']) {
                $s_sql = 'SELECT from_email, reply_to_email, name, description, subject, email_text_content, email_variables, display_name FROM email_templates WHERE  id = $1';
                $ss_result = pg_query_params($db_lnk, $s_sql, array(
                    $row['id']
                ));
                while ($srow = pg_fetch_assoc($ss_result)) {
                    $row['template'] = $srow;
                }
            }
            $response[] = $row;
        }
        break;

    case '/boards/?':
        $s_sql = 'SELECT b.board_visibility, bu.user_id FROM boards AS b LEFT JOIN boards_users AS bu ON bu.board_id = b.id WHERE b.id =  $1';
        $arr[] = $r_resource_vars['boards'];
        if (!empty($authUser) && $authUser['role_id'] != 1) {
            $s_sql.= ' AND (b.board_visibility = 2 OR bu.user_id = $2)';
            $arr[] = $authUser['id'];
        } else if (empty($authUser)) {
            $s_sql.= ' AND b.board_visibility = 2 ';
        }
        $check_visibility = executeQuery($s_sql, $arr);
        if (!empty($check_visibility)) {
            $sql = 'SELECT row_to_json(d) FROM (SELECT * FROM boards_listing ul WHERE id = $1 ORDER BY id DESC) as d ';
            array_push($pg_params, $r_resource_vars['boards']);
        } else {
            $response['error']['type'] = 'visibility';
            $response['error']['message'] = 'Unauthorized';
        }
        break;

    case '/organizations':
        $sql = 'SELECT row_to_json(d) FROM (SELECT * FROM organizations_listing';
        if (!empty($authUser) && $authUser['role_id'] != 1) {
            $sql.= ' WHERE user_id = $1';
            array_push($pg_params, $authUser['id']);
        }
        $sql.= ' ORDER BY id ASC) as d ';
        break;

    case '/organizations/?':
        $s_sql = 'SELECT o.organization_visibility, ou.user_id FROM organizations AS o LEFT JOIN organizations_users AS ou ON ou.organization_id = o.id WHERE o.id =  $1';
        $arr[] = $r_resource_vars['organizations'];
        if (!empty($authUser) && $authUser['role_id'] != 1) {
            $s_sql.= ' AND (o.organization_visibility = 1 OR ou.user_id = $2)';
            $arr[] = $authUser['id'];
        } else if (empty($authUser)) {
            $s_sql.= ' AND o.organization_visibility = 1 ';
        }
        $check_visibility = executeQuery($s_sql, $arr);
        if (!empty($check_visibility)) {
            $sql = 'SELECT row_to_json(d) FROM (SELECT * FROM organizations_listing ul WHERE id = $1 ORDER BY id DESC) as d ';
            array_push($pg_params, $r_resource_vars['organizations']);
        } else {
            $response['error']['type'] = 'visibility';
            $response['error']['message'] = 'Unauthorized';
        }
        break;

    case '/boards/?/activities':
        $condition = '';
        if (isset($r_resource_filters['last_activity_id']) && $r_resource_filters['last_activity_id'] > 0) {
            if (!empty($r_resource_filters['type']) && $r_resource_filters['type'] == 'all') {
                $condition = ' AND al.id < $2';
            } else {
                $condition = ' AND al.id > $2';
            }
        }
        $sql = 'SELECT row_to_json(d) FROM (SELECT al.*, c.name as card_name FROM activities_listing al left join cards c on al.card_id = c.id WHERE al.board_id = $1' . $condition . ' ORDER BY al.id DESC LIMIT ' . PAGING_COUNT . ') as d ';
        array_push($pg_params, $r_resource_vars['boards']);
        if (!empty($condition)) {
            array_push($pg_params, $r_resource_filters['last_activity_id']);
        }
        break;

    case '/users/?/activities':
        $condition = '';
        $condition1 = '';
        if (isset($r_resource_filters['last_activity_id']) && $r_resource_filters['last_activity_id'] > 0) {
            $condition = ' AND al.id > $2';
            $condition1 = ' AND al.id > $3';
            if (!empty($r_resource_filters['type']) && $r_resource_filters['type'] == 'profile') {
                $condition = ' AND al.id < $2';
                $condition1 = ' AND al.id < $3';
            }
        }
        $user = executeQuery('SELECT boards_users FROM users_listing WHERE id = $1', array(
            $r_resource_vars['users']
        ));
        $board_ids = array();
        if (!empty($user['boards_users'])) {
            $boards_users = json_decode($user['boards_users'], true);
            foreach ($boards_users as $boards_user) {
                $board_ids[] = $boards_user['board_id'];
            }
        }
        if (empty($board_ids)) {
            $board_ids[] = 0;
        }
        if (!empty($r_resource_filters['type']) && $r_resource_filters['type'] == 'profile') {
            $sql = 'SELECT row_to_json(d) FROM (SELECT * FROM activities_listing al WHERE user_id = $1 ' . $condition . ' ORDER BY id DESC LIMIT ' . PAGING_COUNT . ') as d';
            array_push($pg_params, $r_resource_vars['users']);
        } else if (!empty($r_resource_filters['organization_id'])) {
            $sql = 'SELECT row_to_json(d) FROM (SELECT * FROM activities_listing al WHERE user_id = $1 AND board_id IN (SELECT id FROM boards WHERE organization_id = $2)' . $condition1 . ' ORDER BY id DESC LIMIT ' . PAGING_COUNT . ') as d';
            array_push($pg_params, $r_resource_vars['users'], $r_resource_filters['organization_id']);
        } else if (!empty($r_resource_filters['type']) && $r_resource_filters['type'] = 'all') {
            $sql = 'SELECT row_to_json(d) FROM (SELECT * FROM activities_listing al WHERE board_id = ANY ( $1 )' . $condition . ' ORDER BY id DESC LIMIT ' . PAGING_COUNT . ') as d';
            array_push($pg_params, '{' . implode(',', $board_ids) . '}');
        } else if (!empty($r_resource_filters['board_id']) && $r_resource_filters['board_id']) {
            $sql = 'SELECT row_to_json(d) FROM (SELECT * FROM activities_listing al WHERE user_id = $1 AND board_id = $2' . $condition1 . ' ORDER BY freshness_ts DESC, materialized_path ASC LIMIT ' . PAGING_COUNT . ') as d';
            array_push($pg_params, $r_resource_vars['users'], $r_resource_filters['board_id']);
        } else {
            $sql = 'SELECT row_to_json(d) FROM (SELECT * FROM activities_listing al WHERE board_id = ANY( $1 )' . $condition . ' ORDER BY id DESC LIMIT ' . PAGING_COUNT . ') as d';
            array_push($pg_params, '{' . implode(',', $board_ids) . '}');
        }
        if (!empty($condition) || !empty($condition1)) {
            array_push($pg_params, $r_resource_filters['last_activity_id']);
        }
        break;

    case '/boards/?/boards_stars':
        $sql = 'SELECT row_to_json(d) FROM (SELECT * FROM board_stars bs WHERE board_id = $1';
        array_push($pg_params, $r_resource_vars['boards']);
        if (!empty($authUser) && $authUser['role_id'] != 1) {
            $sql.= ' and user_id = $2';
            array_push($pg_params, $authUser['id']);
        }
        $sql.= ' ORDER BY id DESC) as d ';
        break;

    case '/boards/?/board_subscribers':
        $sql = 'SELECT row_to_json(d) FROM (SELECT * FROM board_subscribers ul WHERE board_id = $1';
        array_push($pg_params, $r_resource_vars['boards']);
        if (!empty($authUser) && $authUser['role_id'] != 1) {
            $sql.= ' and user_id = $2';
            array_push($pg_params, $authUser['id']);
        }
        $sql.= ' ORDER BY id DESC) as d ';
        break;

    case '/boards/search':
        $sql = 'SELECT row_to_json(d) FROM (SELECT id, name, background_color FROM boards ul WHERE name ILIKE $1 ORDER BY id DESC) as d ';
        array_push($pg_params, '%' . $r_resource_filters['q'] . '%');
        break;

    case '/boards/?/lists/?/cards/?':
        $sql = 'SELECT row_to_json(d) FROM (SELECT * FROM cards_listing cll WHERE id = $1) as d ';
        array_push($pg_params, $r_resource_vars['cards']);
        break;

    case '/boards/?/lists/?/cards/?/activities':
        $sql = 'SELECT row_to_json(d) FROM (SELECT al.*, u.username, u.profile_picture_path, u.initials, c.description, c.name as card_name FROM activities_listing al LEFT JOIN users u ON al.user_id = u.id LEFT JOIN cards c ON  al.card_id = c.id WHERE card_id = $1 ORDER BY freshness_ts DESC, materialized_path ASC) as d ';
        array_push($pg_params, $r_resource_vars['cards']);
        break;

    case '/activities':
        $condition = '';
        if (isset($r_resource_filters['last_activity_id'])) {
            $condition = ' WHERE al.id < $1';
        }
        $sql = 'SELECT row_to_json(d) FROM (SELECT al.*, u.username, u.profile_picture_path, u.initials, c.description FROM activities_listing al LEFT JOIN users u ON al.user_id = u.id LEFT JOIN cards c ON  al.card_id = c.id ' . $condition . ' ORDER BY id DESC limit ' . PAGING_COUNT . ') as d ';
        if (!empty($condition)) {
            array_push($pg_params, $r_resource_filters['last_activity_id']);
        }
        break;

    case '/boards/?/lists/?/cards/?/checklists':
        $sql = 'SELECT row_to_json(d) FROM (SELECT * FROM checklist_add_listing al WHERE board_id = $1) as d ';
        array_push($pg_params, $r_resource_vars['boards']);
        break;

    case '/users/search':
        if (!empty($r_resource_filters['organizations'])) {
            $sql = 'SELECT row_to_json(d) FROM (SELECT u.id, u.username, u.profile_picture_path,u.initials FROM users u LEFT JOIN organizations_users ou ON ou.user_id = u.id WHERE u.is_active = true AND u.is_email_confirmed = true AND ';
            $sql.= '(ou.organization_id != $1 OR ou.user_id IS NULL) AND';
            array_push($pg_params, $r_resource_filters['organizations']);
        } else if (!empty($r_resource_filters['board_id'])) {
            $sql = 'SELECT row_to_json(d) FROM (SELECT u.id, u.username, u.profile_picture_path,u.initials FROM users u JOIN boards_users bu ON bu.user_id = u.id WHERE u.is_active = true AND u.is_email_confirmed = true AND ';
            $sql.= 'bu.board_id = $1 AND';
            array_push($pg_params, $r_resource_filters['board_id']);
        } else {
            $sql = 'SELECT row_to_json(d) FROM (SELECT u.id, u.username, u.profile_picture_path,u.initials FROM users u WHERE  u.is_active = true AND u.is_email_confirmed = true AND ';
        }
        if (empty($pg_params)) {
            $sql.= '(LOWER(u.username) LIKE LOWER($1) OR LOWER(u.email) LIKE LOWER($2))) as d ';
        } else {
            $sql.= '(LOWER(u.username) LIKE LOWER($2) OR LOWER(u.email) LIKE LOWER($3))) as d ';
        }
        array_push($pg_params, $r_resource_filters['q'] . '%', $r_resource_filters['q'] . '%');
        if (empty($r_resource_filters['q'])) {
            $sql = false;
            $response = array();
            $pg_params = array();
        }
        $table = 'users';
        break;

    case '/boards/?/visibility':
        $sql = 'SELECT board_visibility FROM boards bl WHERE bl.id = $1';
        array_push($pg_params, $r_resource_vars['boards']);
        break;

    case '/workflow_templates':
        $files = glob(APP_PATH . '/client/js/workflow_templates/*.json', GLOB_BRACE);
        $i = 0;
        foreach ($files as $file) {
            $file_name = basename($file, '.json');
            $data = file_get_contents($file);
            $json = json_decode($data, true);
            $response[] = array(
                'name' => $json['name'],
                'value' => implode($json['lists'], ', ')
            );
        }
        break;

    case '/search':
        if (isset($_GET['q'])) {
            $q_string = $_GET['q'];
            preg_match_all('/(?P<name>\w+):(?P<search>\w+)/', $q_string, $search);
            if (!empty($search['name'])) {
                foreach ($search['name'] as $key => $name) {
                    $filter['term'][$name . '_name'] = $search['search'][$key];
                    $filter_query['match'][$name . '_name'] = $search['search'][$key];
                }
            }
            preg_match_all('/(.*)@(?P<search>\w+)/', $q_string, $user_search);
            if (!empty($user_search['search'])) {
                foreach ($user_search['search'] as $value) {
                    $filter['term']['user_name'] = $value;
                    $filter_query['match']['user_name'] = $value;
                }
            }
            preg_match_all('/(.*)#(?P<search>\w+)/', $q_string, $label_search);
            if (!empty($label_search['search'])) {
                foreach ($user_search['search'] as $value) {
                    $filter['term']['label_name'] = $value;
                    $filter_query['match']['label_name'] = $value;
                }
            }
            $response = array();
            if (!empty($r_resource_filters['q'])) {
                $elasticsearch_url = ELASTICSEARCH_URL . ELASTICSEARCH_INDEX . '/cards/_search?q=*' . $r_resource_filters['q'] . '*';
                $search_response = doGet($elasticsearch_url);
                $response['result'] = array();
                if (!empty($search_response['hits']['hits'])) {
                    foreach ($search_response['hits']['hits'] as $result) {
                        $s_result = executeQuery('SELECT board_visibility,user_id FROM boards WHERE id = $1', array(
                            $result['_source']['board_id']
                        ));
                        if ($s_result['board_visibility'] == '2' || $s_result['user_id'] == $authUser['id'] || $authUser['role_id'] == 1) {
                            $card['name'] = $result['_source']['card_name'];
                            $card['id'] = $result['_id'];
                            $card['list_name'] = $result['_source']['list_name'];
                            $card['list_id'] = $result['_source']['list_id'];
                            $card['board_name'] = $result['_source']['board_name'];
                            $card['board_id'] = $result['_source']['board_id'];
                            $card['type'] = $result['_type'];
                            $response['result'][] = $card;
                        }
                    }
                }
                $elasticsearch_params['suggest']['text'] = $r_resource_filters['q'];
                $elasticsearch_params['suggest']['card-name-suggest']['term']['size'] = 5;
                $elasticsearch_params['suggest']['card-name-suggest']['term']['field'] = 'card_name';
                $elasticsearch_params['suggest']['card-description-suggest']['term']['size'] = 5;
                $elasticsearch_params['suggest']['card-description-suggest']['term']['field'] = 'card_description';
                $elasticsearch_url = ELASTICSEARCH_URL . ELASTICSEARCH_INDEX . '/_search';
                $result_arr = doPost($elasticsearch_url, $elasticsearch_params, 'json');
                $words = $r_resource_filters['q'];
                $word_count = str_word_count($words);
                $word_arr = explode(' ', $words);
                $tmp_suggested_arr = array();
                $max_suggested_count = 0;
                if (!empty($result_arr['suggest']['card-name-suggest'])) {
                    for ($i = 0; $i < count($result_arr['suggest']['card-name-suggest']); $i++) {
                        for ($j = 0; $j <= 2; $j++) {
                            if (!empty($result_arr['suggest']['card-name-suggest'][$i]['options'][$j]['text'])) {
                                $tmp_suggested_arr[$i][] = $result_arr['suggest']['card-name-suggest'][$i]['options'][$j]['text'];
                            }
                            if (!empty($result_arr['suggest']['card-description-suggest'][$i]['options'][$j]['text'])) {
                                $tmp_suggested_arr[$i][] = $result_arr['suggest']['card-description-suggest'][$i]['options'][$j]['text'];
                            }
                        }
                        if (!empty($tmp_suggested_arr[$i])) {
                            $tmp_suggested_arr[$i] = array_unique($tmp_suggested_arr[$i]);
                            if (count($tmp_suggested_arr[$i]) > $max_suggested_count) {
                                $max_suggested_count = count($tmp_suggested_arr[$i]);
                            }
                        }
                    }
                }
                $response['suggestion'] = array();
                if (!empty($tmp_suggested_arr)) {
                    for ($i = 0; $i < $max_suggested_count; $i++) {
                        $response['suggestion'][$i] = '';
                        for ($j = 0; $j < $word_count; $j++) {
                            if (isset($response[$i])) {
                                $response[$i].= ' ';
                            }
                            $response['suggestion'][$i].= !empty($tmp_suggested_arr[$j][$i]) ? $tmp_suggested_arr[$j][$i] : (!empty($tmp_suggested_arr[$j][0]) ? $tmp_suggested_arr[$j][0] : $word_arr[$j]);
                        }
                    }
                }
                $response['suggestion'] = array_unique($response['suggestion']);
            }
        }
        break;

    case '/users/?':
        $sql = 'SELECT row_to_json(d) FROM (SELECT * FROM users ul WHERE id = $1) as d ';
        array_push($pg_params, $r_resource_vars['users']);
        break;

    case '/users/?/boards':
        if (!empty($authUser)) {
            $s_result = pg_query_params($db_lnk, 'SELECT board_id FROM board_stars WHERE is_starred = true AND user_id = $1', array(
                $authUser['id']
            ));
            $response['starred_boards'] = array();
            while ($row = pg_fetch_assoc($s_result)) {
                $response['starred_boards'][] = $row['board_id'];
            }
            $s_result = pg_query_params($db_lnk, 'SELECT o.id as organization_id, o.name as organization_name, bu.board_id FROM boards_users  bu LEFT JOIN boards b ON b.id = bu.board_id LEFT JOIN organizations o ON o.id = b.organization_id  WHERE bu.user_id = $1', array(
                $authUser['id']
            ));
            $response['user_boards'] = array();
            $user_boards = array();
            while ($row = pg_fetch_assoc($s_result)) {
                $response['user_boards'][] = $row;
            }
        }
        break;

    case '/users/?/cards':
        $sql = 'SELECT row_to_json(d) FROM (SELECT * FROM users_cards_listing ucl WHERE user_id = $1 ORDER BY board_id ASC) as d ';
        array_push($pg_params, $r_resource_vars['users']);
        break;

    case '/users/?/boards':
        $sql = 'SELECT row_to_json(d) FROM (SELECT * FROM boards_users_listing bul WHERE user_id = $1 ORDER BY board_id ASC) as d ';
        array_push($pg_params, $r_resource_vars['users']);
        break;

    case '/boards/?/lists/?/cards/?/search':
        $sql = 'SELECT row_to_json(d) FROM (SELECT bul.id, bul.user_id, bul.username, bul.profile_picture_path,bul.initials FROM boards_users_listing bul WHERE';
        $sql.= '(bul.username LIKE $1 OR bul.email LIKE $2) AND bul.board_id = $3) as d ';
        array_push($pg_params, '%' . $r_resource_filters['q'] . '%', '%' . $r_resource_filters['q'] . '%', $r_resource_vars['boards']);
        if (empty($r_resource_filters['q'])) {
            $sql = false;
            $response = array();
            $pg_params = array();
        }
        $table = 'users';
        break;

    case '/cards/search':
        $user_id = (!empty($authUser['id'])) ? $authUser['id'] : 0;
        $sql = 'SELECT row_to_json(d) FROM (SELECT DISTINCT c.id, c.name, bu.board_id FROM boards_users bu join cards c on c.board_id = bu.board_id WHERE bu.board_id IN (SELECT board_id FROM boards_users WHERE user_id = $1) AND c.name  LIKE $2 ORDER BY id ASC) as d';
        array_push($pg_params, $user_id, '%' . $r_resource_filters['q'] . '%');
        if (empty($r_resource_filters['q'])) {
            $sql = false;
            $response = array();
            $pg_params = array();
        }
        break;

    case '/acl_links':
        $sql = false;
        $s_sql = 'SELECT row_to_json(d) FROM (SELECT acl_links.id,  acl_links.name, acl_links.group_id, ( SELECT array_to_json(array_agg(row_to_json(alr.*))) AS array_to_json FROM ( SELECT acl_links_roles.role_id FROM acl_links_roles acl_links_roles WHERE acl_links_roles.acl_link_id = acl_links.id ORDER BY acl_links_roles.role_id) alr) AS acl_links_roles, acl_links.is_allow_only_to_admin, acl_links.is_allow_only_to_user FROM acl_links acl_links ORDER BY group_id ASC, id ASC) as d';
        $s_result = pg_query_params($db_lnk, $s_sql, array());
        $response['acl_links'] = array();
        while ($row = pg_fetch_assoc($s_result)) {
            $response['acl_links'][] = json_decode($row['row_to_json'], true);
        }
        $s_sql = 'SELECT id, name FROM roles';
        $s_result = pg_query_params($db_lnk, $s_sql, array());
        $response['roles'] = array();
        while ($row = pg_fetch_assoc($s_result)) {
            $response['roles'][] = $row;
        }
        break;

    case '/settings':
        $role_id = (empty($user['role_id'])) ? 3 : $user['role_id'];
        $s_sql = pg_query_params($db_lnk, 'SELECT name, value FROM settings WHERE name = \'SITE_NAME\' OR name = \'SITE_TIMEZONE\' OR name = \'DROPBOX_APPKEY\' OR name = \'LABEL_ICON\' OR name = \'FLICKR_API_KEY\' or name = \'LDAP_LOGIN_ENABLED\'', array());
        while ($row = pg_fetch_assoc($s_sql)) {
            $response[$row['name']] = $row['value'];
        }
        break;

    default:
        header($_SERVER['SERVER_PROTOCOL'] . ' 501 Not Implemented', true, 501);
    }
    if (!empty($sql)) {
        $arrayResponse = array(
            '/boards',
            '/boards/?/lists/?/cards/?/activities',
            '/users/?/activities',
            '/boards/?/activities',
            '/users/?/cards',
            '/cards/search',
            '/boards/?/lists/?/cards/?/search',
            '/users/search',
            '/organizations',
            '/boards/?/activities',
            '/activities'
        );
        if ($result = pg_query_params($db_lnk, $sql, $pg_params)) {
            $data = array();
            $count = pg_num_rows($result);
            $i = 0;
            if (in_array($r_resource_cmd, $arrayResponse) && ($count == 1 || $count == 0)) {
                echo '[';
            }
            while ($row = pg_fetch_row($result)) {
                $obj = json_decode($row[0], true);
                if (isset($obj['board_activities']) && !empty($obj['board_activities'])) {
                    for ($k = 0; $k < count($obj['board_activities']); $k++) {
                        if (!empty($obj['board_activities'][$k]['revisions']) && trim($obj['board_activities'][$k]['revisions']) != '') {
                            $revisions = unserialize($obj['board_activities'][$k]['revisions']);
                            unset($dif);
                            if (!empty($revisions['new_value'])) {
                                foreach ($revisions['new_value'] as $key => $value) {
                                    if ($key != 'is_archived' && $key != 'is_deleted' && $key != 'created' && $key != 'modified' && $obj['type'] != 'moved_card_checklist_item' && $obj['type'] != 'add_card_desc' && $obj['type'] != 'add_card_duedate' && $obj['type'] != 'delete_card_duedate' && $obj['type'] != 'change_visibility' && $obj['type'] != 'add_background' && $obj['type'] != 'change_background') {
                                        $old_val = ($revisions['old_value'][$key] != NULL && $revisions['old_value'][$key] != 'NULL') ? $revisions['old_value'][$key] : '';
                                        $new_val = ($revisions['new_value'][$key] != NULL && $revisions['new_value'][$key] != 'NULL') ? $revisions['new_value'][$key] : '';
                                        $dif[] = nl2br(getRevisiondifference($old_val, $old_val));
                                    }
                                    if ($obj['type'] == 'add_card_desc' || $obj['type'] == 'add_card_desc' || $obj['type'] == '	edit_card_duedate' || $obj['type'] == 'change_visibility' || $obj['type'] == 'add_background' || $obj['type'] == 'change_background') {
                                        $dif[] = $revisions['new_value'][$key];
                                    }
                                }
                                if (isset($dif)) {
                                    $obj['board_activities'][$k]['difference'] = $dif;
                                }
                            }
                        }
                    }
                    $row[0] = json_encode($obj);
                    if ($r_resource_cmd == '/boards/?') {
                        $obj = json_decode($row[0], true);
                        global $_server_domain_url;
                        $md5_hash = md5(SecuritySalt . $r_resource_vars['boards']);
                        $obj['google_syn_url'] = $_server_domain_url . '/ical/' . $r_resource_vars['boards'] . '/' . $md5_hash . '.ics';
                        $row[0] = json_encode($obj);
                    }
                } else if ($r_resource_cmd == '/boards/?/lists/?/cards/?/activities' || $r_resource_cmd == '/users/?/activities' || $r_resource_cmd == '/users/?/notify_count' || $r_resource_cmd == '/boards/?/activities') {
                    if (!empty($obj['revisions']) && trim($obj['revisions']) !== '') {
                        $revisions = unserialize($obj['revisions']);
                        $obj['revisions'] = $revisions;
                        unset($dif);
                        foreach ($revisions['new_value'] as $key => $value) {
                            if ($key != 'is_archived' && $key != 'is_deleted' && $key != 'created' && $key != 'modified' && $key != 'is_offline' && $key != 'uuid' && $key != 'to_date' && $key != 'temp_id' && $obj['type'] != 'moved_card_checklist_item' && $obj['type'] != 'add_card_desc' && $obj['type'] != 'add_card_duedate' && $obj['type'] != 'delete_card_duedate' && $obj['type'] != 'add_background' && $obj['type'] != 'change_background' && $obj['type'] != 'change_visibility') {
                                $old_val = (isset($revisions['old_value'][$key]) && $revisions['old_value'][$key] != NULL && $revisions['old_value'][$key] != 'NULL') ? $revisions['old_value'][$key] : '';
                                $new_val = (isset($revisions['new_value'][$key]) && $revisions['new_value'][$key] != NULL && $revisions['new_value'][$key] != 'NULL') ? $revisions['new_value'][$key] : '';
                                $dif[] = nl2br(getRevisiondifference($old_val, $new_val));
                            }
                            if ($obj['type'] == 'add_card_desc' || $obj['type'] == 'add_card_desc' || $obj['type'] == '	edit_card_duedate' || $obj['type'] == 'add_background' || $obj['type'] == 'change_background' || $obj['type'] == 'change_visibility') {
                                $dif[] = $revisions['new_value'][$key];
                            }
                        }
                        if (isset($dif)) {
                            $obj['difference'] = $dif;
                        }
                    }
                    if ($obj['type'] === 'add_board_user') {
                        $obj['board_user'] = executeQuery('SELECT * FROM boards_users_listing WHERE id = $1', array(
                            $obj['foreign_id']
                        ));
                    } else if ($obj['type'] === 'add_list') {
                        $obj['list'] = executeQuery('SELECT * FROM lists WHERE id = $1', array(
                            $obj['list_id']
                        ));
                    } else if ($obj['type'] === 'change_list_position') {
                        $obj['list'] = executeQuery('SELECT position, board_id FROM lists WHERE id = $1', array(
                            $obj['list_id']
                        ));
                    } else if ($obj['type'] === 'add_card') {
                        $obj['card'] = executeQuery('SELECT * FROM cards WHERE id = $1', array(
                            $obj['card_id']
                        ));
                    } else if ($obj['type'] === 'copy_card') {
                        $obj['card'] = executeQuery('SELECT * FROM cards WHERE id = $1', array(
                            $obj['foreign_id']
                        ));
                    } else if ($obj['type'] === 'add_card_checklist') {
                        $obj['checklist'] = executeQuery('SELECT * FROM checklists_listing WHERE id = $1', array(
                            $obj['foreign_id']
                        ));
                        $obj['checklist']['checklists_items'] = json_decode($obj['checklist']['checklists_items'], true);
                    } else if ($obj['type'] === 'add_card_label') {
                        $s_result = pg_query_params($db_lnk, 'SELECT * FROM cards_labels_listing WHERE  card_id = $1', array(
                            $obj['card_id']
                        ));
                        while ($row = pg_fetch_assoc($s_result)) {
                            $obj['labels'][] = $row;
                        }
                    } else if ($obj['type'] === 'add_card_voter') {
                        $obj['voter'] = executeQuery('SELECT * FROM card_voters_listing WHERE id = $1', array(
                            $obj['foreign_id']
                        ));
                    } else if ($obj['type'] === 'add_card_user') {
                        $obj['user'] = executeQuery('SELECT * FROM cards_users_listing WHERE id = $1', array(
                            $obj['foreign_id']
                        ));
                    } else if ($obj['type'] === 'update_card_checklist') {
                        $obj['checklist'] = executeQuery('SELECT * FROM checklists WHERE id = $1', array(
                            $obj['foreign_id']
                        ));
                    } else if ($obj['type'] === 'add_checklist_item' || $obj['type'] === 'update_card_checklist_item' || $obj['type'] === 'moved_card_checklist_item') {
                        $obj['item'] = executeQuery('SELECT * FROM checklist_items WHERE id = $1', array(
                            $obj['foreign_id']
                        ));
                    } else if ($obj['type'] === 'add_card_attachment') {
                        $obj['attachment'] = executeQuery('SELECT * FROM card_attachments WHERE id = $1', array(
                            $obj['foreign_id']
                        ));
                    } else if ($obj['type'] === 'change_card_position') {
                        $obj['card'] = executeQuery('SELECT position FROM cards WHERE id = $1', array(
                            $obj['card_id']
                        ));
                    }
                    $row[0] = json_encode($obj);
                } else if ($r_resource_cmd == '/boards/?') {
                    $obj = json_decode($row[0], true);
                    global $_server_domain_url;
                    $md5_hash = md5(SecuritySalt . $r_resource_vars['boards']);
                    $obj['google_syn_url'] = $_server_domain_url . '/ical/' . $r_resource_vars['boards'] . '/' . $md5_hash . '.ics';
                    $row[0] = json_encode($obj);
                }
                if ($i == 0 && $count > 1) {
                    echo '[';
                }
                echo $row[0];
                $i++;
                if ($i < $count) {
                    echo ',';
                } else {
                    if ($count > 1) {
                        echo ']';
                    }
                }
            }
            if (in_array($r_resource_cmd, $arrayResponse) && ($count == 1 || $count == 0)) {
                echo ']';
            }
            pg_free_result($result);
        } else {
            $r_debug.= __LINE__ . ': ' . pg_last_error($db_lnk) . '\n';
        }
    } else {
        echo json_encode($response);
    }
}
/**
 * Common method to handle POST method
 *
 * @param  $r_resource_cmd
 * @param  $r_resource_vars
 * @param  $r_resource_filters
 * @param  $r_post
 * @return mixed
 */
function r_post($r_resource_cmd, $r_resource_vars, $r_resource_filters, $r_post)
{
    global $r_debug, $db_lnk, $authUser, $thumbsizes, $_server_domain_url;
    $emailFindReplace = $response = array();
    $fields = 'created, modified';
    $values = 'now(), now()';
    $json = $sql = $is_return_vlaue = false;
    $uuid = '';
    if (isset($r_post['uuid'])) {
        $uuid = $r_post['uuid'];
    }
    unset($r_post['temp_id']);
    unset($r_post['uuid']);
    unset($r_post['id']);
    switch ($r_resource_cmd) {
    case '/users/forgotpassword': //users forgot password
        $user = executeQuery('SELECT * FROM users WHERE email = $1', array(
            $r_post['email']
        ));
        if ($user) {
            $password = uniqid();
            pg_query_params($db_lnk, 'UPDATE users SET (password) = ($1) WHERE id = $2', array(
                getCryptHash($password) ,
                $user['id']
            ));
            $emailFindReplace = array(
                'mail' => 'forgetpassword',
                '##USERNAME##' => $user['username'],
                '##PASSWORD##' => $password,
                'to' => $user['email']
            );
            $response = array(
                'success' => 'An email has been sent with your new password.'
            );
            sendMail($emailFindReplace);
        } else {
            $response = array(
                'error' => 'Please enter valid email id.'
            );
        }
        break;

    case '/settings': //settings update
        foreach ($r_post as $key => $value) {
            pg_query_params($db_lnk, 'UPDATE settings SET value = $1 WHERE name = $2', array(
                $value,
                trim($key)
            ));
        }
        $response = array(
            'success' => 'Settings updated successfully.'
        );
        break;

    case '/users/admin_user_add': //Admin user add
        $table_name = 'users';
        $user = executeQuery('SELECT * FROM users WHERE username = $1 OR email = $2', array(
            $r_post['username'],
            $r_post['email']
        ));
        if (!$user) {
            $sql = true;
            $table_name = 'users';
            $r_post['password'] = getCryptHash($r_post['password']);
            $r_post['role_id'] = 2; // user
            $r_post['is_active'] = true;
            $r_post['is_email_confirmed'] = true;
            $r_post['role_id'] = 2; // user
            $r_post['initials'] = strtoupper(substr($r_post['username'], 0, 1));
            $r_post['ip_id'] = saveIp();
        } else {
            $msg = '';
            if ($user['email'] == $r_post['email']) {
                $msg = 'Email address already exists. Your registration process is not completed. Please, try again.';
            } else if ($user['username'] == $r_post['username']) {
                $msg = 'Username already exists. Your registration process is not completed. Please, try again.';
            }
            $response = array(
                'error' => $msg
            );
        }
        break;

    case '/users/register': //users register
        $table_name = 'users';
        $user = executeQuery('SELECT * FROM users WHERE username = $1 OR email = $2', array(
            $r_post['username'],
            $r_post['email']
        ));
        if (!$user) {
            $sql = true;
            $table_name = 'users';
            $r_post['password'] = getCryptHash($r_post['password']);
            $r_post['role_id'] = 2; // user
            $r_post['initials'] = strtoupper(substr($r_post['username'], 0, 1));
            $r_post['ip_id'] = saveIp();
        } else {
            $msg = '';
            if ($user['email'] == $r_post['email']) {
                $msg = 'Email address is already exist. Your registration process is not completed. Please, try again.';
            } else if ($user['username'] == $r_post['username']) {
                $msg = 'Username address is already exist. Your registration process is not completed. Please, try again.';
            }
            $response = array(
                'error' => $msg
            );
        }
        break;

    case '/users/login': //users login
        $is_login = false;
        $user = array();
        $table_name = 'users';
        $log_user = executeQuery('SELECT * FROM users WHERE email = $1 or username = $1', array(
            $r_post['email']
        ));
        if (LDAP_LOGIN_ENABLED && (empty($log_user) || (!empty($log_user) && $log_user['role_id'] != 1))) {
            $check_user = ldapAuthenticate($r_post['email'], $r_post['password']);
            if (!empty($check_user['User']) && $check_user['User']['is_username_exits'] && $check_user['User']['is_password_matched'] && isset($check_user['User']['email']) && !empty($check_user['User']['email'])) {
                $user = executeQuery('SELECT * FROM users_listing WHERE email = $1', array(
                    $check_user['User']['email']
                ));
                if (!$user) {
                    $r_post['password'] = getCryptHash($r_post['password']);
                    $r_post['role_id'] = 2; // user
                    $result = pg_query_params($db_lnk, 'INSERT INTO ' . $table_name . ' (created, modified, role_id, username, email, password, initials, is_active, is_email_confirmed) VALUES (now(), now(), 2, $1, $2, $3, $4, TRUE, TRUE) RETURNING * ', array(
                        $r_post['email'],
                        $check_user['User']['email'],
                        $r_post['password'],
                        strtoupper(substr($r_post['email'], 0, 1))
                    ));
                    $user = pg_fetch_assoc($result);
                    $user = executeQuery('SELECT * FROM users_listing WHERE id = $1', array(
                        $user['id']
                    ));
                }
            }
        } else {
            if ($log_user) {
                $r_post['password'] = crypt($r_post['password'], $log_user['password']);
                $user = executeQuery('SELECT * FROM users_listing WHERE (email = $1 or username = $1) AND password = $2 AND is_active = $3', array(
                    $r_post['email'],
                    $r_post['password'],
                    true
                ));
            }
        }
        if (!empty($user)) {
            if (LDAP_LOGIN_ENABLED) {
                $login_type_id = 1;
            } else {
                $login_type_id = 2;
            }
            $last_login_ip_id = saveIp();
            pg_query_params($db_lnk, 'UPDATE users SET last_login_date = now(), login_type_id = $1, last_login_ip_id = $2 WHERE id = $3', array(
                $login_type_id,
                $last_login_ip_id,
                $user['id']
            ));
            unset($user['password']);
            $user_agent = !empty($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
            pg_query_params($db_lnk, 'INSERT INTO user_logins (created, modified, user_id, ip_id, user_agent) VALUES (now(), now(), $1, $2, $3)', array(
                $user['id'],
                $last_login_ip_id,
                $user_agent
            ));
            $role_links = executeQuery('SELECT * FROM role_links_listing WHERE id = $1', array(
                $user['role_id']
            ));
            $post_url = $_server_domain_url . str_replace('r.php', 'token.php', $_SERVER['PHP_SELF']);
            $response = doPost($post_url, array(
                'grant_type' => 'password',
                'username' => $user['username'],
                'password' => $r_post['password'],
                'client_id' => OAUTH_CLIENTID,
                'client_secret' => OAUTH_CLIENT_SECRET
            ));
            $response = array_merge($role_links, $response);
            $board_ids = array();
            if (!empty($user['boards_users'])) {
                $boards_users = json_decode($user['boards_users'], true);
                foreach ($boards_users as $boards_user) {
                    $board_ids[] = $boards_user['board_id'];
                }
            }
            $notify_count = executeQuery('SELECT count(a.*) AS notify_count FROM activities a  WHERE a.id > $1 AND board_id = ANY ($2) ', array(
                $user['last_activity_id'],
                '{' . implode(',', $board_ids) . '}'
            ));
            $user = array_merge($user, $notify_count);
            $response['user'] = $user;
            $response['user']['organizations'] = json_decode($user['organizations'], true);
        } else {
            $response = array(
                'error' => 'Sorry, login failed. Either your username or password are incorrect or admin deactivated your account.'
            );
        }
        break;

    case '/organizations/?/users/?': //organization users add
        $table_name = 'organizations_users';
        $sql = true;
        $is_return_vlaue = true;
        break;

    case '/organizations': //organizations add
        $sql = true;
        $table_name = 'organizations';
        $r_post['user_id'] = (!empty($authUser['id'])) ? $authUser['id'] : 1;
        $r_post['organization_visibility'] = 2;
        break;

    case '/boards': //boards add
        $is_import_board = false;
        if (!empty($_FILES['board_import'])) {
            if ($_FILES['board_import']['error'] == 0) {
                $imported_board = json_decode(file_get_contents($_FILES['board_import']['tmp_name']) , true);
                if (!empty($imported_board)) {
                    $board = importTrelloBoard($imported_board);
                    $response['id'] = $board['id'];
                } else {
                    $response['error'] = 'Unable to import. please try again.';
                }
            } else {
                $response['error'] = 'Unable to import. please try again.';
            }
        } else {
            $table_name = 'boards';
            $board = executeQuery('SELECT id, name FROM ' . $table_name . ' WHERE name = $1', array(
                $r_post['name']
            ));
            if (isset($r_post['template']) && !empty($r_post['template'])) {
                $lists = explode(',', $r_post['template']);
            }
            unset($r_post['template']);
            $sql = true;
            $r_post['user_id'] = (!empty($authUser['id'])) ? $authUser['id'] : 1;
        }
        break;

    case '/boards/?/boards_stars': //stars add
        $table_name = 'board_stars';
        $subcriber = executeQuery('SELECT id, is_starred FROM ' . $table_name . ' WHERE board_id = $1 and user_id = $2', array(
            $r_resource_vars['boards'],
            $authUser['id']
        ));
        if (!$subcriber) {
            $result = pg_query_params($db_lnk, 'INSERT INTO ' . $table_name . ' (created, modified, board_id, user_id, is_starred) VALUES (now(), now(), $1, $2, TRUE) RETURNING id', array(
                $r_resource_vars['boards'],
                $authUser['id']
            ));
        } else {
            if ($subcriber['is_starred'] == 't') {
                $result = pg_query_params($db_lnk, 'UPDATE ' . $table_name . ' SET is_starred = False Where  board_id = $1 and user_id = $2 RETURNING id', array(
                    $r_resource_vars['boards'],
                    $authUser['id']
                ));
            } else {
                $result = pg_query_params($db_lnk, 'UPDATE ' . $table_name . ' SET is_starred = True Where  board_id = $1 and user_id = $2 RETURNING id', array(
                    $r_resource_vars['boards'],
                    $authUser['id']
                ));
            }
        }
        $star = pg_fetch_assoc($result);
        $response['id'] = $star['id'];
        break;

    case '/boards/?/board_subscribers': //subscriber add
        $table_name = 'board_subscribers';
        $subcriber = executeQuery('SELECT id, is_subscribed FROM ' . $table_name . ' WHERE board_id = $1 and user_id = $2', array(
            $r_resource_vars['boards'],
            $authUser['id']
        ));
        if (!$subcriber) {
            $result = pg_query_params($db_lnk, 'INSERT INTO ' . $table_name . ' (created, modified, board_id, user_id, is_subscribed) VALUES (now(), now(), $1, $2, TRUE) RETURNING *', array(
                $r_resource_vars['boards'],
                $authUser['id']
            ));
        } else {
            if ($subcriber['is_subscribed'] == 't') {
                $result = pg_query_params($db_lnk, 'UPDATE ' . $table_name . ' SET is_subscribed = False Where  board_id = $1 and user_id = $2 RETURNING *', array(
                    $r_resource_vars['boards'],
                    $authUser['id']
                ));
            } else {
                $result = pg_query_params($db_lnk, 'UPDATE ' . $table_name . ' SET is_subscribed = True Where  board_id = $1 and user_id = $2 RETURNING *', array(
                    $r_resource_vars['boards'],
                    $authUser['id']
                ));
            }
        }
        $response = pg_fetch_assoc($result);
        break;

    case '/boards/?/copy': //boards copy
        $table_name = 'boards';
        $sql = true;
        $copied_board_id = $r_resource_vars['boards'];
        $board_visibility = $r_post['board_visibility'];
        if (!empty($r_post['organization_id'])) {
            $organization_id = $r_post['organization_id'];
        }
        $keepcards = false;
        if (!empty($r_post['keepCards'])) {
            $keepcards = true;
            unset($r_post['keepCards']);
        }
        $sresult = pg_query_params($db_lnk, 'SELECT * FROM boards WHERE id = $1', array(
            $copied_board_id
        ));
        $srow = pg_fetch_assoc($sresult);
        unset($srow['id']);
        unset($srow['created']);
        unset($srow['modified']);
        unset($srow['user_id']);
        unset($srow['name']);
        if ($srow['commenting_permissions'] === NULL) {
            $srow['commenting_permissions'] = 0;
        }
        if ($srow['voting_permissions'] === NULL) {
            $srow['voting_permissions'] = 0;
        }
        if ($srow['inivitation_permissions'] === NULL) {
            $srow['inivitation_permissions'] = 0;
        }
        $r_post = array_merge($r_post, $srow);
        $r_post['board_visibility'] = $board_visibility;
        if (!empty($organization_id)) {
            $r_post['organization_id'] = $organization_id;
        }
        break;

    case '/users/?/changepassword':
        $user = executeQuery('SELECT * FROM users WHERE id = $1', array(
            $r_resource_vars['users']
        ));
        if ($user) {
            $cry_old_pass = crypt($r_post['old_password'], $user['password']);
            if ((($authUser['role_id'] == 2) && ($user['password'] == $cry_old_pass)) || ($authUser['role_id'] == 1)) {
                $result = pg_query_params($db_lnk, 'UPDATE users SET (password) = ($1) WHERE id = $2', array(
                    getCryptHash($r_post['password']) ,
                    $r_resource_vars['users']
                ));
                if ($authUser['role_id'] == 1) {
                    $emailFindReplace = array(
                        'to' => $user['email'],
                        'mail' => 'changepassword',
                        '##PASSWORD##' => $r_post['password']
                    );
                    sendMail($emailFindReplace);
                    $response = array(
                        'success' => 'Password change successfully. Please login.'
                    );
                }
            } else {
                $response = array(
                    'error' => 'Invalid old password.'
                );
            }
        } else {
            $response = array(
                'error' => 'Unable to change password. Please try again.'
            );
        }
        break;

    case '/organizations/?/upload_logo': // organizations logo upload
        $sql = false;
        $json = true;
        $organization_id = $r_resource_vars['organizations'];
        if (!empty($_FILES['attachment']) && $_FILES['attachment']['error'] == 0) {
            $mediadir = APP_PATH . DIRECTORY_SEPARATOR . 'media' . DIRECTORY_SEPARATOR . 'Organization' . DIRECTORY_SEPARATOR . $r_resource_vars['organizations'];
            $save_path = 'media' . DIRECTORY_SEPARATOR . 'Organization' . DIRECTORY_SEPARATOR . $r_resource_vars['organizations'];
            if (!file_exists($mediadir)) {
                mkdir($mediadir, 0777, true);
            }
            $file = $_FILES['attachment'];
            $file['name'] = preg_replace('/[^A-Za-z0-9\-.]/', '', $file['name']);
            if (move_uploaded_file($file['tmp_name'], $mediadir . DIRECTORY_SEPARATOR . $file['name'])) {
                $logo_url = $save_path . DIRECTORY_SEPARATOR . $file['name'];
                foreach ($thumbsizes['Organization'] as $key => $value) {
                    $list = glob(APP_PATH . DIRECTORY_SEPARATOR . 'img' . DIRECTORY_SEPARATOR . $key . DIRECTORY_SEPARATOR . 'Organization' . DIRECTORY_SEPARATOR . $r_resource_vars['organizations'] . '.*');
                    @unlink($list[0]);
                }
                foreach ($thumbsizes['Organization'] as $key => $value) {
                    $mediadir = dirname(dirname(dirname(dirname(__FILE__)))) . '/client/img/' . $key . '/Organization/' . $r_resource_vars['organizations'];
                    $list = glob($mediadir . '.*');
                    @unlink($list[0]);
                }
                pg_query_params($db_lnk, 'UPDATE organizations SET logo_url = $1 WHERE id = $2', array(
                    $logo_url,
                    $r_resource_vars['organizations']
                ));
                $response['logo_url'] = $logo_url;
            }
        }
        break;

    case '/boards/?/lists':
        $table_name = 'lists';
        $r_post['board_id'] = $r_resource_vars['boards'];
        $r_post['user_id'] = $authUser['id'];
        $sql = true;
        if (isset($r_post['clone_list_id'])) {
            $clone_list_id = $r_post['clone_list_id'];
            unset($r_post['clone_list_id']);
            unset($r_post['list_cards']);
        }
        break;

    case '/boards/?/lists/?/list_subscribers':
        $table_name = 'list_subscribers';
        $r_post['user_id'] = $authUser['id'];
        $s_result = pg_query_params($db_lnk, 'SELECT is_subscribed FROM list_subscribers WHERE list_id = $1 and user_id = $2', array(
            $r_resource_vars['lists'],
            $r_post['user_id']
        ));
        $check_subscribed = pg_fetch_assoc($s_result);
        if (!empty($check_subscribed)) {
            $is_subscribed = ($r_post['is_subscribed']) ? true : false;
            $s_result = pg_query_params($db_lnk, 'UPDATE list_subscribers SET is_subscribed = $1 WHERE list_id = $2 and user_id = $3', array(
                $is_subscribed,
                $r_resource_vars['lists'],
                $r_post['user_id']
            ));
        } else {
            $r_post['list_id'] = $r_resource_vars['lists'];
            $sql = true;
        }
        break;

    case '/boards/?/lists/?/cards/?/comments':
        $is_return_vlaue = true;
        $table_name = 'activities';
        $sql = true;
        $prev_message = array();
        if (isset($r_post['root']) && !empty($r_post['root'])) {
            $prev_message = executeQuery('SELECT ac.*, u,username, u.profile_picture_path, u.initials FROM activities ac LEFT JOIN users u ON ac.user_id = u.id WHERE ac.id = $1', array(
                $r_post['root']
            ));
        }
        $r_post['freshness_ts'] = date('Y-m-d h:i:s');
        $r_post['type'] = 'add_comment';
        break;

    case '/boards/?/lists/?/cards/?/card_subscribers':
        $table_name = 'card_subscribers';
        $json = true;
        $r_post['user_id'] = $authUser['id'];
        unset($r_post['list_id']);
        unset($r_post['board_id']);
        $s_result = pg_query_params($db_lnk, 'SELECT is_subscribed FROM card_subscribers WHERE card_id = $1 and user_id = $2', array(
            $r_resource_vars['cards'],
            $r_post['user_id']
        ));
        $check_subscribed = pg_fetch_assoc($s_result);
        if (!empty($check_subscribed)) {
            $is_subscribed = ($r_post['is_subscribed']) ? true : false;
            $s_result = pg_query_params($db_lnk, 'UPDATE card_subscribers SET is_subscribed = $1 WHERE card_id = $2 and user_id = $3 RETURNING id', array(
                $is_subscribed,
                $r_resource_vars['cards'],
                $r_post['user_id']
            ));
            $subscribe = pg_fetch_assoc($s_result);
            $response['id'] = $subscribe['id'];
        } else {
            $r_post['card_id'] = $r_resource_vars['cards'];
            $r_post['user_id'] = $r_post['user_id'];
            $sql = true;
        }
        break;

    case '/boards/?/lists/?/cards/?/card_voters':
        $table_name = 'card_voters';
        $r_post['card_id'] = $r_resource_vars['cards'];
        $r_post['user_id'] = $authUser['id'];
        $sql = true;
        break;

    case '/boards/?/lists/?/cards':
        $table_name = 'cards';
        $r_post['user_id'] = $authUser['id'];
        $pos_res = pg_query_params($db_lnk, 'SELECT position FROM cards WHERE board_id = $1 AND list_id = $2 ORDER BY position DESC LIMIT 1', array(
            $r_post['board_id'],
            $r_post['list_id']
        ));
        $position = pg_fetch_array($pos_res);
        if (empty($r_post['due_date'])) {
            unset($r_post['due_date']);
        }
        if (!empty($r_post['user_ids'])) {
            $r_post['members'] = explode(',', $r_post['user_ids']);
        }
        if (!isset($r_post['position'])) {
            $r_post['position'] = $position[0] + 1;
        }
        $sql = true;
        break;

    case '/boards/?/lists/?/cards/?/attachments':
        $is_return_vlaue = true;
        $table_name = 'card_attachments';
        $r_post['card_id'] = $r_resource_vars['cards'];
        $r_post['list_id'] = $r_resource_vars['lists'];
        $r_post['board_id'] = $r_resource_vars['boards'];
        $mediadir = APP_PATH . DIRECTORY_SEPARATOR . 'media' . DIRECTORY_SEPARATOR . 'Card' . DIRECTORY_SEPARATOR . $r_resource_vars['cards'];
        $save_path = 'media' . DIRECTORY_SEPARATOR . 'Card' . DIRECTORY_SEPARATOR . $r_resource_vars['cards'];
        $save_path = str_replace('\\', '/', $save_path);
        if (!empty($_FILES['attachment']) && $_FILES['attachment']['error'] == 0) {
            if (!file_exists($mediadir)) {
                mkdir($mediadir, 0777, true);
            }
            $file = $_FILES['attachment'];
            if (move_uploaded_file($file['tmp_name'], $mediadir . DIRECTORY_SEPARATOR . $file['name'])) {
                $r_post['path'] = $save_path . '/' . $file['name'];
                $r_post['name'] = $file['name'];
                $r_post['mimetype'] = $file['type'];
                $s_result = pg_query_params($db_lnk, 'INSERT INTO card_attachments (created, modified, card_id, name, path, list_id, board_id, mimetype) VALUES (now(), now(), $1, $2, $3, $4, $5, $6) RETURNING *', array(
                    $r_post['card_id'],
                    $r_post['name'],
                    $r_post['path'],
                    $r_post['list_id'],
                    $r_post['board_id'],
                    $r_post['mimetype']
                ));
                $response['card_attachments'][] = pg_fetch_assoc($s_result);
            }
            foreach ($thumbsizes['CardAttachment'] as $key => $value) {
                $mediadir = dirname(dirname(dirname(dirname(__FILE__)))) . '/client/img/' . $key . '/CardAttachment/' . $response['card_attachments'][0]['id'];
                $list = glob($mediadir . '.*');
                @unlink($list[0]);
            }
            $foreign_ids['board_id'] = $r_resource_vars['boards'];
            $foreign_ids['list_id'] = $r_resource_vars['lists'];
            $foreign_ids['card_id'] = $r_resource_vars['cards'];
            $comment = $authUser['username'] . ' added attachment to this card ##CARD_LINK##';
            $response['activity'] = insertActivity($authUser['id'], $comment, 'add_card_attachment', $foreign_ids, NULL, $response['card_attachments'][0]['id']);
        } else if (!empty($_FILES['attachment']) && is_array($_FILES['attachment']['name']) && $_FILES['attachment']['error'][0] == 0) {
            $file = $_FILES['attachment'];
            for ($i = 0; $i < count($file['name']); $i++) {
                if (!file_exists($mediadir)) {
                    mkdir($mediadir, 0777, true);
                }
                if (move_uploaded_file($file['tmp_name'][$i], $mediadir . DIRECTORY_SEPARATOR . $file['name'][$i])) {
                    $r_post[$i]['path'] = $save_path . DIRECTORY_SEPARATOR . $file['name'][$i];
                    $r_post[$i]['name'] = $file['name'][$i];
                    $r_post[$i]['mimetype'] = $file['type'][$i];
                    $s_result = pg_query_params($db_lnk, 'INSERT INTO card_attachments (created, modified, card_id, name, path, list_id, board_id, mimetype) VALUES (now(), now(), $1, $2, $3, $4, $5, $6) RETURNING *', array(
                        $r_post['card_id'],
                        $r_post[$i]['name'],
                        $r_post[$i]['path'],
                        $r_post['list_id'],
                        $r_post['board_id'],
                        $r_post[$i]['mimetype']
                    ));
                    $response['card_attachments'][] = pg_fetch_assoc($s_result);
                    $foreign_ids['board_id'] = $r_resource_vars['boards'];
                    $foreign_ids['list_id'] = $r_resource_vars['lists'];
                    $foreign_ids['card_id'] = $r_resource_vars['cards'];
                    $comment = $authUser['username'] . ' added attachment to this card ##CARD_LINK##';
                    $response['activity'] = insertActivity($authUser['id'], $comment, 'add_card_attachment', $foreign_ids, NULL, $response['card_attachments'][$i]['id']);
                    foreach ($thumbsizes['CardAttachment'] as $key => $value) {
                        $mediadir = dirname(dirname(dirname(dirname(__FILE__)))) . '/client/img/' . $key . '/CardAttachment/' . $response['card_attachments'][$i]['id'];
                        $list = glob($mediadir . '.*');
                        @unlink($list[0]);
                    }
                }
            }
        } else if (isset($r_post['image_link']) && !empty($r_post['image_link'])) {
            $filename = curlExecute($r_post['image_link'], 'get', $mediadir, 'image');
            $sql = true;
            unset($r_post['image_link']);
            $r_post['path'] = $save_path . '/' . $filename;
            $r_post['name'] = $filename;
        }
        break;

    case '/boards/?/lists/?/cards/?/labels':
        $is_return_vlaue = true;
        $table_name = 'cards_labels';
        $r_post['card_id'] = $r_resource_vars['cards'];
        $r_post['list_id'] = $r_resource_vars['lists'];
        $r_post['board_id'] = $r_resource_vars['boards'];
        $delete_labels = pg_query_params($db_lnk, 'DELETE FROM ' . $table_name . ' WHERE card_id = $1', array(
            $r_resource_vars['cards']
        ));
        $delete_labels_count = pg_affected_rows($delete_labels);
        if (!empty($r_post['name'])) {
            $label_names = explode(',', $r_post['name']);
            unset($r_post['name']);
            foreach ($label_names as $label_name) {
                $s_result = pg_query_params($db_lnk, 'SELECT id FROM labels WHERE name = $1', array(
                    $label_name
                ));
                $label = pg_fetch_assoc($s_result);
                if (empty($label)) {
                    $s_result = pg_query_params($db_lnk, 'INSERT INTO labels (created, modified, name) VALUES (now(), now(), $1) RETURNING id', array(
                        $label_name
                    ));
                    $label = pg_fetch_assoc($s_result);
                }
                $r_post['label_id'] = $label['id'];
                pg_query_params($db_lnk, 'INSERT INTO ' . $table_name . ' (created, modified, card_id, label_id, board_id, list_id) VALUES (now(), now(), $1, $2, $3, $4) RETURNING *', array(
                    $r_post['card_id'],
                    $r_post['label_id'],
                    $r_post['board_id'],
                    $r_post['list_id']
                ));
            }
            $s_result = pg_query_params($db_lnk, 'SELECT * FROM cards_labels_listing WHERE card_id = $1', array(
                $r_post['card_id']
            ));
            $cards_labels = pg_fetch_all($s_result);
            $response['cards_labels'] = $cards_labels;
            $comment = $authUser['username'] . ' added label(s) to this card ##CARD_LINK## - ##LABEL_NAME##';
        } else {
            $response['cards_labels'] = array();
            $comment = $authUser['username'] . ' removed label(s) in this card ##CARD_LINK## - ##LABEL_NAME##';
        }
        $foreign_ids['board_id'] = $r_post['board_id'];
        $foreign_ids['list_id'] = $r_post['list_id'];
        $foreign_ids['card_id'] = $r_post['card_id'];
        if (!empty($delete_labels_count)) {
            $response['activity'] = insertActivity($authUser['id'], $comment, 'add_card_label', $foreign_ids, NULL, $r_post['label_id']);
        }
        break;

    case '/boards/?/lists/?/cards/?/checklists':
        $sql = true;
        $table_name = 'checklists';
        $r_post['user_id'] = $authUser['id'];
        $r_post['card_id'] = $r_resource_vars['cards'];
        if (isset($r_post['checklist_id'])) {
            $checklist_id = $r_post['checklist_id'];
            unset($r_post['checklist_id']);
        }
        break;

    case '/boards/?/lists/?/cards/?/checklists/?/items':
        $table_name = 'checklist_items';
        $is_return_vlaue = true;
        $r_post['user_id'] = $authUser['id'];
        $r_post['card_id'] = $r_resource_vars['cards'];
        $r_post['checklist_id'] = $r_resource_vars['checklists'];
        unset($r_post['created']);
        unset($r_post['modified']);
        unset($r_post['is_offline']);
        unset($r_post['list_id']);
        unset($r_post['board_id']);
        $names = explode("\n", $r_post['name']);
        foreach ($names as $name) {
            $r_post['name'] = trim($name);
            if (!empty($r_post['name'])) {
                $position = executeQuery('SELECT max(position) as position FROM checklist_items WHERE checklist_id = $1', array(
                    $r_post['checklist_id']
                ));
                $r_post['position'] = $position['position'];
                if (empty($r_post['position'])) {
                    $r_post['position'] = 0;
                }
                $r_post['position']+= 1;
                $result = pg_execute_insert($table_name, $r_post);
                $item = pg_fetch_assoc($result);
                $response[$table_name][] = $item;
                $foreign_ids['board_id'] = $r_resource_vars['boards'];
                $foreign_ids['list_id'] = $r_resource_vars['lists'];
                $foreign_ids['card_id'] = $r_post['card_id'];
                $comment = '##USER_NAME## added item ##CHECKLIST_ITEM_NAME## in checklist ##CHECKLIST_ITEM_PARENT_NAME## of card ##CARD_LINK##';
                $response['activities'][] = insertActivity($authUser['id'], $comment, 'add_checklist_item', $foreign_ids, '', $item['id']);
            }
        }
        break;

    case '/boards/?/lists/?/cards/?/checklists/?/items/?/convert_to_card':
        $is_return_vlaue = true;
        $table_name = 'cards';
        $result = pg_query_params($db_lnk, 'SELECT name FROM checklist_items WHERE id = $1', array(
            $r_resource_vars['items']
        ));
        $row = pg_fetch_assoc($result);
        $r_post['board_id'] = $r_resource_vars['boards'];
        $r_post['list_id'] = $r_resource_vars['lists'];
        $r_post['name'] = $row['name'];
        $sresult = pg_query_params($db_lnk, 'SELECT max(position) as position FROM cards WHERE list_id = $1', array(
            $r_post['list_id']
        ));
        $srow = pg_fetch_assoc($sresult);
        $r_post['position'] = $srow['position'];
        $r_post['user_id'] = $authUser['id'];
        $sql = true;
        break;

    case '/users/?':
        $is_return_vlaue = true;
        $profile_picture_path = 'NULL';
        $no_error = true;
        if (!empty($_FILES['attachment']['name']) && $_FILES['attachment']['error'] == 0) {
            $mediadir = APP_PATH . DIRECTORY_SEPARATOR . 'media' . DIRECTORY_SEPARATOR . 'User' . DIRECTORY_SEPARATOR . $r_resource_vars['users'];
            $save_path = 'media' . DIRECTORY_SEPARATOR . 'User' . DIRECTORY_SEPARATOR . $r_resource_vars['users'];
            if (!file_exists($mediadir)) {
                mkdir($mediadir, 0777, true);
            }
            $file = $_FILES['attachment'];
            $file['name'] = preg_replace('/[^A-Za-z0-9\-.]/', '', $file['name']);
            if (move_uploaded_file($file['tmp_name'], $mediadir . DIRECTORY_SEPARATOR . $file['name'])) {
                $profile_picture_path = $save_path . DIRECTORY_SEPARATOR . $file['name'];
                foreach ($thumbsizes['User'] as $key => $value) {
                    $mediadir = dirname(dirname(dirname(dirname(__FILE__)))) . '/client/img/' . $key . '/User/' . $r_resource_vars['users'];
                    $list = glob($mediadir . '.*');
                    @unlink($list[0]);
                }
                $authUser['profile_picture_path'] = $profile_picture_path;
                $response['profile_picture_path'] = $profile_picture_path;
            }
            pg_query_params($db_lnk, 'UPDATE users SET profile_picture_path = $1 WHERE id = $2', array(
                $profile_picture_path,
                $r_resource_vars['users']
            ));
        } else {
            if (!empty($_POST['email'])) {
                $user = executeQuery('SELECT * FROM users WHERE email = $1', array(
                    $_POST['email']
                ));
                if ($user['id'] != $r_resource_vars['users'] && $user['email'] == $_POST['email']) {
                    $no_error = false;
                    $msg = 'Email address is already exist. User Profile could not be updated. Please, try again.';
                }
            }
            if ($no_error) {
                $_POST['initials'] = strtoupper($_POST['initials']);
                pg_query_params($db_lnk, 'UPDATE users SET  full_name = $1, about_me = $2, initials = $3 WHERE id = $4', array(
                    $_POST['full_name'],
                    $_POST['about_me'],
                    $_POST['initials'],
                    $r_resource_vars['users']
                ));
                if (!empty($_POST['email'])) {
                    pg_query_params($db_lnk, 'UPDATE users SET email= $1 WHERE id = $2', array(
                        $_POST['email'],
                        $r_resource_vars['users']
                    ));
                }
            }
        }
        if ($no_error) {
            $response['success'] = 'User Profile has been updated.';
        } else {
            $response['error'] = $msg;
        }
        break;

    case '/boards/?/custom_backgrounds':
        $is_return_vlaue = true;
        if (!empty($_FILES['attachment']) && $_FILES['attachment']['error'] == 0) {
            $mediadir = APP_PATH . DIRECTORY_SEPARATOR . 'media' . DIRECTORY_SEPARATOR . 'Board' . DIRECTORY_SEPARATOR . $r_resource_vars['boards'];
            $save_path = 'media' . DIRECTORY_SEPARATOR . 'Board' . DIRECTORY_SEPARATOR . $r_resource_vars['boards'];
            if (!file_exists($mediadir)) {
                mkdir($mediadir, 0777, true);
            }
            $file = $_FILES['attachment'];
            $file['name'] = preg_replace('/[^A-Za-z0-9\-.]/', '', $file['name']);
            if (move_uploaded_file($file['tmp_name'], $mediadir . DIRECTORY_SEPARATOR . $file['name'])) {
                $r_post['name'] = $file['name'];
                foreach ($thumbsizes['Board'] as $key => $value) {
                    $mediadir = dirname(dirname(dirname(dirname(__FILE__)))) . DIRECTORY_SEPARATOR . 'client' . DIRECTORY_SEPARATOR . 'img' . DIRECTORY_SEPARATOR . $key . DIRECTORY_SEPARATOR . 'Board' . DIRECTORY_SEPARATOR . $r_resource_vars['boards'];
                    $list = glob($mediadir . '.*');
                    @unlink($list[0]);
                }
                $hash = md5(SecuritySalt . 'Board' . $r_resource_vars['boards'] . 'jpg' . 'extra_large_thumb' . SITE_NAME);
                $background_picture_url = $_server_domain_url . '/img/extra_large_thumb/Board/' . $r_resource_vars['boards'] . '.' . $hash . '.jpg';
                $r_post['background_picture_path'] = $save_path . DIRECTORY_SEPARATOR . $file['name'];
                $r_post['path'] = $background_picture_url;
                $response['background_picture_url'] = $background_picture_url;
            }
            pg_query_params($db_lnk, 'UPDATE boards SET background_picture_url = $1,background_picture_path = $2 WHERE id = $3', array(
                $r_post['path'],
                $r_post['background_picture_path'],
                $r_resource_vars['boards']
            ));
        }
        break;

    case '/boards/?/lists/?/cards/?/users/?':
        $is_return_vlaue = true;
        $table_name = 'cards_users';
        unset($r_post['board_id']);
        unset($r_post['list_id']);
        unset($r_post['is_offline']);
        unset($r_post['profile_picture_path']);
        unset($r_post['username']);
        unset($r_post['initials']);
        $check_already_added = executeQuery('SELECT * FROM cards_users WHERE card_id = $1 AND user_id = $2', array(
            $r_resource_vars['cards'],
            $r_resource_vars['users']
        ));
        if (!empty($check_already_added)) {
            $response['id'] = $check_already_added['id'];
            $response['cards_users'] = $check_already_added;
        } else {
            $sql = true;
        }
        break;

    case '/boards/?/lists/?/cards/?/copy':
        $is_return_vlaue = true;
        $r_post['user_id'] = $authUser['id'];
        $table_name = 'cards';
        $is_keep_attachment = $is_keep_user = $is_keep_label = $is_keep_activity = $is_keep_checklist = 0;
        if (isset($r_post['keep_attachments'])) {
            $is_keep_attachment = $r_post['keep_attachments'];
            unset($r_post['keep_attachments']);
        }
        if (isset($r_post['keep_activities'])) {
            $is_keep_activity = $r_post['keep_activities'];
            unset($r_post['keep_activities']);
        }
        if (isset($r_post['keep_labels'])) {
            $is_keep_label = $r_post['keep_labels'];
            unset($r_post['keep_labels']);
        }
        if (isset($r_post['keep_users'])) {
            $is_keep_user = $r_post['keep_users'];
            unset($r_post['keep_users']);
        }
        if (isset($r_post['keep_checklists'])) {
            $is_keep_checklist = $r_post['keep_checklists'];
            unset($r_post['keep_checklists']);
        }
        $copied_card_id = $r_resource_vars['cards'];
        unset($r_post['copied_card_id']);
        $sresult = pg_query_params($db_lnk, 'SELECT * FROM cards WHERE id = $1', array(
            $copied_card_id
        ));
        $srow = pg_fetch_assoc($sresult);
        unset($srow['id']);
        $card_name = $r_post['name'];
        $r_post = array_merge($srow, $r_post);
        $r_post['name'] = $card_name;
        $sql = true;
        break;

    case '/acl_links':
        $table_name = 'acl_links_roles';
        $acl = executeQuery('SELECT * FROM ' . $table_name . ' WHERE acl_link_id = $1 AND role_id = $2', array(
            $r_post['acl_link_id'],
            $r_post['role_id']
        ));
        if ($acl) {
            pg_query_params($db_lnk, 'DELETE FROM ' . $table_name . ' WHERE acl_link_id = $1 AND role_id = $2', array(
                $r_post['acl_link_id'],
                $r_post['role_id']
            ));
        } else {
            pg_query_params($db_lnk, 'INSERT INTO ' . $table_name . ' (created, modified, acl_link_id, role_id) VALUES(now(), now(), $1, $2)', array(
                $r_post['acl_link_id'],
                $r_post['role_id']
            ));
        }
        break;

    case '/boards/?/users':
        $is_return_vlaue = true;
        $table_name = 'boards_users';
        $boards_user = executeQuery('SELECT * FROM boards_users WHERE board_id = $1 AND user_id = $2', array(
            $r_resource_vars['boards'],
            $r_post['user_id']
        ));
        if (empty($boards_user)) {
            $sql = true;
        }
        break;

    default:
        header($_SERVER['SERVER_PROTOCOL'] . ' 501 Not Implemented', true, 501);
        break;
    }
    if (!empty($sql)) {
        $post = getbindValues($table_name, $r_post);
        $result = pg_execute_insert($table_name, $post);
        if ($result) {
            $row = pg_fetch_assoc($result);
            $response['id'] = $row['id'];
            if ($is_return_vlaue) {
                $response[$table_name] = $row;
            }
            if (!empty($uuid)) {
                $response['uuid'] = $uuid;
            }
            if ($r_resource_cmd == '/users/register') {
                $emailFindReplace['##USERNAME##'] = $r_post['username'];
                $emailFindReplace['##ACTIVATION_URL##'] = 'http://' . $_SERVER['HTTP_HOST'] . '/#/users/activation/' . $row['id'] . '/' . md5($r_post['username']);
                $emailFindReplace['to'] = $r_post['email'];
                $emailFindReplace['mail'] = 'activation';
                sendMail($emailFindReplace);
            } else if ($r_resource_cmd == '/boards') {
                if (!$is_import_board) {
                    $foreign_id['board_id'] = $response['id'];
                    $comment = $authUser['username'] . ' created board';
                    $response['activity'] = insertActivity($authUser['id'], $comment, 'add_board', $foreign_id);
                    $result = pg_query_params($db_lnk, 'INSERT INTO boards_users (created, modified, board_id , user_id, is_admin) VALUES (now(), now(), $1, $2, true)', array(
                        $row['id'],
                        $r_post['user_id']
                    ));
                    if (isset($lists) && !empty($lists)) {
                        $position = 1;
                        $total_list = count($lists);
                        $s_sql = 'INSERT INTO lists (created, modified, board_id, name, user_id, position) VALUES';
                        foreach ($lists as $list) {
                            $s_sql = 'INSERT INTO lists (created, modified, board_id, name, user_id, position) VALUES';
                            $s_sql.= '(now(), now(), $1, $2, $3, $4)';
                            pg_query_params($db_lnk, $s_sql, array(
                                $response['id'],
                                $list,
                                $authUser['id'],
                                $position
                            ));
                            $position++;
                        }
                    }
                    $response['simple_board'] = executeQuery('SELECT row_to_json(d) FROM (SELECT * FROM simple_board_listing sbl WHERE id = $1 ORDER BY id ASC) as d', array(
                        $row['id']
                    ));
                    $response['simple_board'] = json_decode($response['simple_board']['row_to_json'], true);
                }
            } else if ($r_resource_cmd == '/organizations') {
                $result = pg_query_params($db_lnk, 'INSERT INTO organizations_users (created, modified, organization_id , user_id, is_admin) VALUES (now(), now(), $1, $2, true)', array(
                    $row['id'],
                    $r_post['user_id']
                ));
            } else if ($r_resource_cmd == '/boards/?/lists') {
                $foreign_ids['board_id'] = $r_post['board_id'];
                $foreign_ids['list_id'] = $response['id'];
                $comment = $authUser['username'] . ' added list "' . $r_post['name'] . '".';
                $response['activity'] = insertActivity($authUser['id'], $comment, 'add_list', $foreign_ids);
                $copy_checklists = array();
                $copy_checklists_items = array();
                if (!empty($clone_list_id)) {
                    $s_result = pg_query_params($db_lnk, 'SELECT name, board_id, position FROM lists WHERE id = $1', array(
                        $clone_list_id
                    ));
                    $previous_list = pg_fetch_assoc($s_result);
                    $new_list_id = $response['id'];
                    // Copy cards
                    $card_fields = 'board_id, name, description, position, due_date, is_archived, attachment_count, checklist_count, checklist_item_count, checklist_item_completed_count, label_count, cards_user_count, cards_subscriber_count, card_voter_count, activity_count, user_id, comment_count';
                    $card_fields = 'list_id, ' . $card_fields;
                    $cards = pg_query_params($db_lnk, 'SELECT id, ' . $card_fields . ' FROM cards WHERE list_id = $1 ORDER BY id', array(
                        $clone_list_id
                    ));
                    if (pg_num_rows($cards)) {
                        copyCards($card_fields, $cards, $new_list_id, $post['name'], $foreign_ids['board_id']);
                    }
                }
                $s_result = pg_query_params($db_lnk, 'SELECT * FROM lists_listing WHERE id = $1', array(
                    $foreign_ids['list_id']
                ));
                $list = pg_fetch_assoc($s_result);
                $response['list'] = $list;
                $attachments = pg_query_params($db_lnk, 'SELECT * FROM card_attachments WHERE list_id = $1', array(
                    $foreign_ids['list_id']
                ));
                while ($attachment = pg_fetch_assoc($attachments)) {
                    $response['list']['attachments'][] = $attachment;
                }
                $activities = pg_query_params($db_lnk, 'SELECT * FROM activities_listing WHERE list_id = $1', array(
                    $foreign_ids['list_id']
                ));
                while ($activity = pg_fetch_assoc($activities)) {
                    $response['list']['activities'][] = $activity;
                }
                $response['list']['checklists'] = $copy_checklists;
                $response['list']['checklists_items'] = $copy_checklists_items;
                $labels = pg_query_params($db_lnk, 'SELECT * FROM cards_labels_listing WHERE list_id = $1', array(
                    $foreign_ids['list_id']
                ));
                while ($label = pg_fetch_assoc($labels)) {
                    $response['list']['labels'][] = $label;
                }
                $response['list']['cards'] = json_decode($response['list']['cards'], true);
                $response['list']['lists_subscribers'] = json_decode($response['list']['lists_subscribers'], true);
            } else if ($r_resource_cmd == '/boards/?/lists/?/cards' || $r_resource_cmd == '/boards/?/lists/?/cards/?/checklists/?/items/?/convert_to_card') {
                $s_result = pg_query_params($db_lnk, 'SELECT name FROM lists WHERE id = $1', array(
                    $r_post['list_id']
                ));
                $list = pg_fetch_assoc($s_result);
                $foreign_ids['board_id'] = $r_post['board_id'];
                $foreign_ids['card_id'] = $response['id'];
                $foreign_ids['list_id'] = $r_post['list_id'];
                $comment = $authUser['username'] . ' added card ##CARD_LINK## to list "' . $list['name'] . '".';
                $response['activity'] = insertActivity($authUser['id'], $comment, 'add_card', $foreign_ids);
                if (!empty($r_post['members'])) {
                    $s_usql = '';
                    foreach ($r_post['members'] as $member) {
                        $s_usql = 'INSERT INTO cards_users (created, modified, card_id, user_id) VALUES(now(), now(), ' . $response['id'] . ', ' . $member . ') RETURNING id';
                        $s_result = pg_query_params($db_lnk, $s_usql, array());
                        $card_user = pg_fetch_assoc($s_result);
                        $_user = executeQuery('SELECT username FROM users WHERE id = $1', array(
                            $member
                        ));
                        $comment = $authUser['username'] . ' added "' . $_user['username'] . '" as member to this card ##CARD_LINK##';
                        $response['activity'] = insertActivity($authUser['id'], $comment, 'add_card_user', $foreign_ids, '', $card_user['id']);
                    }
                }
                $cards_users = pg_query_params($db_lnk, 'SELECT * FROM cards_users_listing WHERE card_id = $1', array(
                    $response['id']
                ));
                while ($cards_user = pg_fetch_assoc($cards_users)) {
                    $response['cards_users'][] = $cards_user;
                }
                if (!empty($r_post['labels'])) {
                    $r_post['card_labels'] = $r_post['labels'];
                }
                if (!empty($r_post['card_labels'])) {
                    $label_names = explode(',', $r_post['card_labels']);
                    foreach ($label_names as $label_name) {
                        $s_result = pg_query_params($db_lnk, 'SELECT id FROM labels WHERE name = $1', array(
                            $label_name
                        ));
                        $label = pg_fetch_assoc($s_result);
                        if (empty($label)) {
                            $s_result = pg_query_params($db_lnk, $s_sql = 'INSERT INTO labels (created, modified, name) VALUES (now(), now(), $1) RETURNING id', array(
                                $label_name
                            ));
                            $label = pg_fetch_assoc($s_result);
                        }
                        $r_post['label_id'] = $label['id'];
                        $r_post['card_id'] = $row['id'];
                        $r_post['list_id'] = $row['list_id'];
                        $r_post['board_id'] = $row['board_id'];
                        pg_query_params($db_lnk, 'INSERT INTO cards_labels (created, modified, card_id, label_id, board_id, list_id) VALUES (now(), now(), $1, $2, $3, $4) RETURNING *', array(
                            $r_post['card_id'],
                            $r_post['label_id'],
                            $r_post['board_id'],
                            $r_post['list_id']
                        ));
                    }
                    $comment = $authUser['username'] . ' added label(s) to this card ##CARD_LINK## - ##LABEL_NAME##';
                    insertActivity($authUser['id'], $comment, 'add_card_label', $foreign_ids);
                }
                $cards_labels = pg_query_params($db_lnk, 'SELECT * FROM cards_labels_listing WHERE card_id = $1', array(
                    $response['id']
                ));
                while ($cards_label = pg_fetch_assoc($cards_labels)) {
                    $response['cards_labels'][] = $cards_label;
                }
                if (!empty($clone_card_id)) {
                    pg_query_params($db_lnk, 'INSERT INTO card_attachments (created, modified, card_id, name, path, mimetype) SELECT created, modified, $1, name, path, mimetype FROM card_attachments WHERE card_id = $2', array(
                        $response['id'],
                        $clone_card_id
                    ));
                    $s_result = pg_query_params($db_lnk, 'SELECT name, list_id, board_id, position FROM lists WHERE id = $1', array(
                        $clone_card_id
                    ));
                    $previous_value = pg_fetch_assoc($s_result);
                    $comment = $authUser['username'] . ' copied card "' . $r_post['name'] . '". from "' . $previous_value['name'] . '"';
                    $response['activity'] = insertActivity($authUser['id'], $comment, 'copy_card', $foreign_id);
                }
            } else if ($r_resource_cmd == '/boards/?/copy') {
                $new_board_id = $row['id'];
                //Copy board users
                $boards_user_fields = 'user_id, is_admin';
                $boards_users = pg_query_params($db_lnk, 'SELECT id, ' . $boards_user_fields . ' FROM boards_users WHERE board_id = $1', array(
                    $r_resource_vars['boards']
                ));
                if ($boards_users && pg_num_rows($boards_users)) {
                    $boards_user_fields = 'created, modified, board_id, ' . $boards_user_fields;
                    while ($boards_user = pg_fetch_object($boards_users)) {
                        $boards_user_values = array();
                        array_push($boards_user_values, 'now()', 'now()', $new_board_id);
                        foreach ($boards_user as $key => $value) {
                            if ($key != 'id') {
                                if ($value === false) {
                                    array_push($boards_user_values, 'false');
                                } else if ($value === NULL) {
                                    array_push($boards_user_values, NULL);
                                } else {
                                    array_push($boards_user_values, $value);
                                }
                            }
                        }
                        $boards_user_val = '';
                        for ($i = 1, $len = count($boards_user_values); $i <= $len; $i++) {
                            $boards_user_val.= '$' . $i;
                            $boards_user_val.= ($i != $len) ? ', ' : '';
                        }
                        $boards_user_result = pg_query_params($db_lnk, 'INSERT INTO boards_users (' . $boards_user_fields . ') VALUES (' . $boards_user_val . ') RETURNING id', $boards_user_values);
                    }
                }
                //Copy board subscribers
                $boards_subscriber_fields = 'user_id, is_subscribed';
                $boards_subscribers = pg_query_params($db_lnk, 'SELECT id, ' . $boards_subscriber_fields . ' FROM board_subscribers WHERE board_id = $1', array(
                    $r_resource_vars['boards']
                ));
                if ($boards_subscribers && pg_num_rows($boards_subscribers)) {
                    $boards_subscriber_fields = 'created, modified, board_id, ' . $boards_subscriber_fields;
                    while ($boards_subscriber = pg_fetch_object($boards_subscribers)) {
                        $boards_subscriber_values = array();
                        array_push($boards_subscriber_values, 'now()', 'now()', $new_board_id);
                        foreach ($boards_subscriber as $key => $value) {
                            if ($key != 'id') {
                                if ($value === false) {
                                    array_push($boards_subscriber_values, 'false');
                                } else if ($value === NULL) {
                                    array_push($boards_subscriber_values, NULL);
                                } else {
                                    array_push($boards_subscriber_values, $value);
                                }
                            }
                        }
                        $boards_subscriber_val = '';
                        for ($i = 1, $len = count($boards_subscriber_values); $i <= $len; $i++) {
                            $boards_subscriber_val.= '$' . $i;
                            $boards_subscriber_val.= ($i != $len) ? ', ' : '';
                        }
                        $boards_subscriber_result = pg_query_params($db_lnk, 'INSERT INTO board_subscribers (' . $boards_subscriber_fields . ') VALUES (' . $boards_subscriber_val . ') RETURNING id', $boards_subscriber_values);
                    }
                }
                //Copy board star
                $boards_star_fields = 'user_id, is_starred';
                $boards_stars = pg_query_params($db_lnk, 'SELECT id, ' . $boards_star_fields . ' FROM board_stars WHERE board_id = $1', array(
                    $r_resource_vars['boards']
                ));
                if ($boards_stars && pg_num_rows($boards_stars)) {
                    $boards_star_fields = 'created, modified, board_id, ' . $boards_star_fields;
                    while ($boards_star = pg_fetch_object($boards_stars)) {
                        $boards_star_values = array();
                        array_push($boards_star_values, 'now()', 'now()', $new_board_id);
                        foreach ($boards_star as $key => $value) {
                            if ($key != 'id') {
                                if ($value === false) {
                                    array_push($boards_star_values, 'false');
                                } else if ($value === NULL) {
                                    array_push($boards_star_values, NULL);
                                } else {
                                    array_push($boards_star_values, $value);
                                }
                            }
                        }
                        $boards_star_val = '';
                        for ($i = 1, $len = count($boards_star_values); $i <= $len; $i++) {
                            $boards_star_val.= '$' . $i;
                            $boards_star_val.= ($i != $len) ? ', ' : '';
                        }
                        $boards_star_result = pg_query_params($db_lnk, 'INSERT INTO board_stars (' . $boards_star_fields . ') VALUES (' . $boards_star_val . ') RETURNING id', $boards_star_values);
                    }
                }
                if ($keepcards) {
                    $lists = pg_query_params($db_lnk, 'SELECT id, name, position, is_archived, card_count, lists_subscriber_count FROM lists WHERE board_id = $1', array(
                        $r_resource_vars['boards']
                    ));
                } else {
                    $lists = pg_query_params($db_lnk, 'SELECT id, name, position, is_archived, lists_subscriber_count FROM lists WHERE board_id = $1', array(
                        $r_resource_vars['boards']
                    ));
                }
                if ($lists) {
                    // Copy lists
                    while ($list = pg_fetch_object($lists)) {
                        $list_id = $list->id;
                        $list_fields = 'created, modified, board_id, user_id';
                        $list_values = array();
                        array_push($list_values, 'now()', 'now()', $new_board_id, $authUser['id']);
                        foreach ($list as $key => $value) {
                            if ($key != 'id') {
                                $list_fields.= ', ' . $key;
                                if ($value === false) {
                                    array_push($list_values, 'false');
                                } else {
                                    array_push($list_values, $value);
                                }
                            }
                        }
                        $list_val = '';
                        for ($i = 1, $len = count($list_values); $i <= $len; $i++) {
                            $list_val.= '$' . $i;
                            $list_val.= ($i != $len) ? ', ' : '';
                        }
                        $lists_result = pg_query_params($db_lnk, 'INSERT INTO lists (' . $list_fields . ') VALUES (' . $list_val . ') RETURNING id', $list_values);
                        if ($lists_result) {
                            $list_result = pg_fetch_assoc($lists_result);
                            $new_list_id = $list_result['id'];
                            //Copy list subscribers
                            $lists_subscriber_fields = 'user_id, is_subscribed';
                            $lists_subscribers = pg_query_params($db_lnk, 'SELECT id, ' . $lists_subscriber_fields . ' FROM list_subscribers WHERE list_id = $1', array(
                                $list_id
                            ));
                            if ($lists_subscribers && pg_num_rows($lists_subscribers)) {
                                $lists_subscriber_fields = 'created, modified, list_id, ' . $lists_subscriber_fields;
                                while ($lists_subscriber = pg_fetch_object($lists_subscribers)) {
                                    $lists_subscriber_values = array();
                                    array_push($lists_subscriber_values, 'now()', 'now()', $new_list_id);
                                    foreach ($lists_subscriber as $key => $value) {
                                        if ($key != 'id') {
                                            if ($value === false) {
                                                array_push($lists_subscriber_values, 'false');
                                            } else if ($value === NULL) {
                                                array_push($lists_subscriber_values, NULL);
                                            } else {
                                                array_push($lists_subscriber_values, $value);
                                            }
                                        }
                                    }
                                    $lists_subscriber_val = '';
                                    for ($i = 1, $len = count($lists_subscriber_values); $i <= $len; $i++) {
                                        $lists_subscriber_val.= '$' . $i;
                                        $lists_subscriber_val.= ($i != $len) ? ', ' : '';
                                    }
                                    $lists_subscriber_result = pg_query_params($db_lnk, 'INSERT INTO list_subscribers (' . $lists_subscriber_fields . ') VALUES (' . $lists_subscriber_val . ') RETURNING id', $lists_subscriber_values);
                                }
                            }
                            // Copy cards
                            $card_fields = 'name, description, due_date, position, is_archived, attachment_count, checklist_count, checklist_item_count, checklist_item_completed_count, label_count, cards_user_count, cards_subscriber_count, card_voter_count, activity_count, user_id, comment_count';
                            if ($keepcards) {
                                $cards = pg_query_params($db_lnk, 'SELECT id, ' . $card_fields . ' FROM cards WHERE list_id = $1', array(
                                    $list_id
                                ));
                            }
                            if ($keepcards && pg_num_rows($cards)) {
                                $card_fields = 'created, modified, board_id, list_id, ' . $card_fields;
                                while ($card = pg_fetch_object($cards)) {
                                    $card_id = $card->id;
                                    $card_values = array();
                                    array_push($card_values, 'now()', 'now()', $new_board_id, $new_list_id);
                                    foreach ($card as $key => $value) {
                                        if ($key != 'id') {
                                            if ($value === false) {
                                                array_push($card_values, 'false');
                                            } else if ($value === NULL) {
                                                array_push($card_values, NULL);
                                            } else {
                                                array_push($card_values, $value);
                                            }
                                        }
                                    }
                                    $card_val = '';
                                    for ($i = 1, $len = count($card_values); $i <= $len; $i++) {
                                        $card_val.= '$' . $i;
                                        $card_val.= ($i != $len) ? ', ' : '';
                                    }
                                    $card_result = pg_query_params($db_lnk, 'INSERT INTO cards (' . $card_fields . ') VALUES (' . $card_val . ') RETURNING id', $card_values);
                                    if ($card_result) {
                                        $card_result = pg_fetch_assoc($card_result);
                                        $new_card_id = $card_result['id'];
                                        //Copy card attachments
                                        $attachment_fields = 'name, path, mimetype';
                                        $attachments = pg_query_params($db_lnk, 'SELECT id, ' . $attachment_fields . ' FROM card_attachments WHERE card_id = $1', array(
                                            $card_id
                                        ));
                                        if ($attachments && pg_num_rows($attachments)) {
                                            $attachment_fields = 'created, modified, board_id, list_id, card_id, ' . $attachment_fields;
                                            while ($attachment = pg_fetch_object($attachments)) {
                                                $attachment_values = array();
                                                array_push($attachment_values, 'now()', 'now()', $new_board_id, $new_list_id, $new_card_id);
                                                foreach ($attachment as $key => $value) {
                                                    if ($key != 'id') {
                                                        if ($value === false) {
                                                            array_push($attachment_values, 'false');
                                                        } else if ($value === NULL) {
                                                            array_push($attachment_values, NULL);
                                                        } else {
                                                            array_push($attachment_values, $value);
                                                        }
                                                    }
                                                }
                                                $attachment_val = '';
                                                for ($i = 1, $len = count($attachment_values); $i <= $len; $i++) {
                                                    $attachment_val.= '$' . $i;
                                                    $attachment_val.= ($i != $len) ? ', ' : '';
                                                }
                                                $card_result = pg_query_params($db_lnk, 'INSERT INTO card_attachments (' . $attachment_fields . ') VALUES (' . $attachment_val . ') RETURNING id', $attachment_values);
                                            }
                                        }
                                        //Copy checklists
                                        $checklist_fields = 'user_id, name, checklist_item_count, checklist_item_completed_count, position';
                                        $checklists = pg_query_params($db_lnk, 'SELECT id, ' . $checklist_fields . ' FROM checklists WHERE card_id = $1', array(
                                            $card_id
                                        ));
                                        if ($checklists && pg_num_rows($checklists)) {
                                            $checklist_fields = 'created, modified, card_id, ' . $checklist_fields;
                                            while ($checklist = pg_fetch_object($checklists)) {
                                                $checklist_values = array();
                                                array_push($checklist_values, 'now()', 'now()', $new_card_id);
                                                $checklist_id = $checklist->id;
                                                foreach ($checklist as $key => $value) {
                                                    if ($key != 'id') {
                                                        if ($value === false) {
                                                            array_push($checklist_values, 'false');
                                                        } else if ($value === NULL) {
                                                            array_push($checklist_values, NULL);
                                                        } else {
                                                            array_push($checklist_values, $value);
                                                        }
                                                    }
                                                }
                                                $checklist_val = '';
                                                for ($i = 1, $len = count($checklist_values); $i <= $len; $i++) {
                                                    $checklist_val.= '$' . $i;
                                                    $checklist_val.= ($i != $len) ? ', ' : '';
                                                }
                                                $checklist_result = pg_query_params($db_lnk, 'INSERT INTO checklists (' . $checklist_fields . ') VALUES (' . $checklist_val . ') RETURNING id', $checklist_values);
                                                if ($checklist_result) {
                                                    $checklist_result = pg_fetch_assoc($checklist_result);
                                                    $new_checklist_id = $checklist_result['id'];
                                                    //Copy checklist items
                                                    $checklist_item_fields = 'user_id, name, position';
                                                    $checklist_items = pg_query_params($db_lnk, 'SELECT id, ' . $checklist_item_fields . ' FROM checklist_items WHERE checklist_id = $1', array(
                                                        $checklist_id
                                                    ));
                                                    if ($checklist_items && pg_num_rows($checklist_items)) {
                                                        $checklist_item_fields = 'created, modified, card_id, checklist_id, ' . $checklist_item_fields;
                                                        while ($checklist_item = pg_fetch_object($checklist_items)) {
                                                            $checklist_item_values = array();
                                                            array_push($checklist_item_values, 'now()', 'now()', $new_card_id, $new_checklist_id);
                                                            foreach ($checklist_item as $key => $value) {
                                                                if ($key != 'id') {
                                                                    if ($value === false) {
                                                                        array_push($checklist_item_values, 'false');
                                                                    } else if ($value === NULL) {
                                                                        array_push($checklist_item_values, NULL);
                                                                    } else {
                                                                        array_push($checklist_item_values, $value);
                                                                    }
                                                                }
                                                            }
                                                            $checklist_item_val = '';
                                                            for ($i = 1, $len = count($checklist_item_values); $i <= $len; $i++) {
                                                                $checklist_item_val.= '$' . $i;
                                                                $checklist_item_val.= ($i != $len) ? ', ' : '';
                                                            }
                                                            $checklist_item_result = pg_query_params($db_lnk, 'INSERT INTO checklist_items (' . $checklist_item_fields . ') VALUES (' . $checklist_item_val . ') RETURNING id', $checklist_item_values);
                                                        }
                                                    }
                                                }
                                            }
                                        }
                                        //Copy card voters
                                        $card_voter_fields = 'user_id';
                                        $card_voters = pg_query_params($db_lnk, 'SELECT id, ' . $card_voter_fields . ' FROM card_voters WHERE card_id = $1', array(
                                            $card_id
                                        ));
                                        if ($card_voters && pg_num_rows($card_voters)) {
                                            $card_voter_fields = 'created, modified, card_id, ' . $card_voter_fields;
                                            while ($card_voter = pg_fetch_object($card_voters)) {
                                                $card_voter_values = array();
                                                array_push($card_voter_values, 'now()', 'now()', $new_card_id);
                                                foreach ($card_voter as $key => $value) {
                                                    if ($key != 'id') {
                                                        if ($value === false) {
                                                            array_push($card_voter_values, 'false');
                                                        } else if ($value === NULL) {
                                                            array_push($card_voter_values, NULL);
                                                        } else {
                                                            array_push($card_voter_values, $value);
                                                        }
                                                    }
                                                }
                                                $card_voter_val = '';
                                                for ($i = 1, $len = count($card_voter_values); $i <= $len; $i++) {
                                                    $card_voter_val.= '$' . $i;
                                                    $card_voter_val.= ($i != $len) ? ', ' : '';
                                                }
                                                $card_voter_result = pg_query_params($db_lnk, 'INSERT INTO card_voters (' . $card_voter_fields . ') VALUES (' . $card_voter_val . ') RETURNING id', $card_voter_values);
                                            }
                                        }
                                        //Copy card labels
                                        $cards_label_fields = 'label_id';
                                        $cards_labels = pg_query_params($db_lnk, 'SELECT id, ' . $cards_label_fields . ' FROM cards_labels WHERE card_id = $1', array(
                                            $card_id
                                        ));
                                        if ($cards_labels && pg_num_rows($cards_labels)) {
                                            $cards_label_fields = 'created, modified, board_id, list_id, card_id, ' . $cards_label_fields;
                                            while ($cards_label = pg_fetch_object($cards_labels)) {
                                                $cards_label_values = array();
                                                array_push($cards_label_values, 'now()', 'now()', $new_board_id, $new_list_id, $new_card_id);
                                                foreach ($cards_label as $key => $value) {
                                                    if ($key != 'id') {
                                                        if ($value === false) {
                                                            array_push($cards_label_values, 'false');
                                                        } else if ($value === NULL) {
                                                            array_push($cards_label_values, NULL);
                                                        } else {
                                                            array_push($cards_label_values, $value);
                                                        }
                                                    }
                                                }
                                                $cards_label_val = '';
                                                for ($i = 1, $len = count($cards_label_values); $i <= $len; $i++) {
                                                    $cards_label_val.= '$' . $i;
                                                    $cards_label_val.= ($i != $len) ? ', ' : '';
                                                }
                                                $cards_label_result = pg_query_params($db_lnk, 'INSERT INTO cards_labels (' . $cards_label_fields . ') VALUES (' . $cards_label_val . ') RETURNING id', $cards_label_values);
                                            }
                                        }
                                        //Copy card subscribers
                                        $cards_subscriber_fields = 'user_id, is_subscribed';
                                        $cards_subscribers = pg_query_params($db_lnk, 'SELECT id, ' . $cards_subscriber_fields . ' FROM card_subscribers WHERE card_id = $1', array(
                                            $card_id
                                        ));
                                        if ($cards_subscribers && pg_num_rows($cards_subscribers)) {
                                            $cards_subscriber_fields = 'created, modified, card_id, ' . $cards_subscriber_fields;
                                            while ($cards_subscriber = pg_fetch_object($cards_subscribers)) {
                                                $cards_subscriber_values = array();
                                                array_push($cards_subscriber_values, 'now()', 'now()', $new_card_id);
                                                foreach ($cards_subscriber as $key => $value) {
                                                    if ($key != 'id') {
                                                        if ($value === false) {
                                                            array_push($cards_subscriber_values, 'false');
                                                        } else if ($value === NULL) {
                                                            array_push($cards_subscriber_values, NULL);
                                                        } else {
                                                            array_push($cards_subscriber_values, $value);
                                                        }
                                                    }
                                                }
                                                $cards_subscriber_val = '';
                                                for ($i = 1, $len = count($cards_subscriber_values); $i <= $len; $i++) {
                                                    $cards_subscriber_val.= '$' . $i;
                                                    $cards_subscriber_val.= ($i != $len) ? ', ' : '';
                                                }
                                                $cards_subscriber_result = pg_query_params($db_lnk, 'INSERT INTO card_subscribers (' . $cards_subscriber_fields . ') VALUES (' . $cards_subscriber_val . ') RETURNING id', $cards_subscriber_values);
                                            }
                                        }
                                        //Copy card users
                                        $cards_user_fields = 'user_id';
                                        $cards_users = pg_query_params($db_lnk, 'SELECT id, ' . $cards_user_fields . ' FROM cards_users WHERE card_id = $1', array(
                                            $card_id
                                        ));
                                        if ($cards_users && pg_num_rows($cards_users)) {
                                            $cards_user_fields = 'created, modified, card_id, ' . $cards_user_fields;
                                            while ($cards_user = pg_fetch_object($cards_users)) {
                                                $cards_user_values = array();
                                                array_push($cards_user_values, 'now()', 'now()', $new_card_id);
                                                foreach ($cards_user as $key => $value) {
                                                    if ($key != 'id') {
                                                        if ($value === false) {
                                                            array_push($cards_user_values, 'false');
                                                        } else if ($value === NULL) {
                                                            array_push($cards_user_values, NULL);
                                                        } else {
                                                            array_push($cards_user_values, $value);
                                                        }
                                                    }
                                                }
                                                $cards_user_val = '';
                                                for ($i = 1, $len = count($cards_user_values); $i <= $len; $i++) {
                                                    $cards_user_val.= '$' . $i;
                                                    $cards_user_val.= ($i != $len) ? ', ' : '';
                                                }
                                                $cards_user_result = pg_query_params($db_lnk, 'INSERT INTO cards_users (' . $cards_user_fields . ') VALUES (' . $cards_user_val . ') RETURNING id', $cards_user_values);
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            } else if ($r_resource_cmd == '/boards/?/lists/?/cards/?/checklists') {
                if (isset($checklist_id) && !empty($checklist_id)) {
                    pg_query_params($db_lnk, 'INSERT INTO checklist_items (created, modified, user_id, card_id, checklist_id, name, is_completed, position) SELECT created, modified, $1, card_id, $2, name, false, position FROM checklist_items WHERE checklist_id = $3', array(
                        $r_post['user_id'],
                        $response['id'],
                        $checklist_id
                    ));
                }
                $result = pg_query_params($db_lnk, 'SELECT * FROM checklists_listing WHERE id = $1', array(
                    $response['id']
                ));
                $response['checklist'] = pg_fetch_assoc($result);
                $response['checklist']['checklists_items'] = json_decode($response['checklist']['checklists_items'], true);
                $foreign_ids['board_id'] = $r_resource_vars['boards'];
                $foreign_ids['list_id'] = $r_resource_vars['lists'];
                $foreign_ids['card_id'] = $r_resource_vars['cards'];
                $comment = '##USER_NAME## added checklist ##CHECKLIST_NAME## to this card ##CARD_LINK##';
                $response['activity'] = insertActivity($authUser['id'], $comment, 'add_card_checklist', $foreign_ids, '', $response['id']);
            } else if ($r_resource_cmd == '/boards/?/lists/?/cards/?/comments') {
                $id_converted = base_convert($response['id'], 10, 36);
                $materialized_path = sprintf("%08s", $id_converted);
                if (!empty($prev_message['materialized_path'])) {
                    $materialized_path = $prev_message['materialized_path'] . '-' . $materialized_path;
                }
                if (!empty($prev_message['path'])) {
                    $path = $prev_message['path'] . '.P' . $response['id'];
                    $depth = $prev_message['depth'] + 1;
                    $root = $prev_message['root'];
                    $response['activities']['depth'] = $depth;
                } else {
                    $path = 'P' . $response['id'];
                    $depth = 0;
                    $root = $response['id'];
                }
                pg_query_params($db_lnk, 'UPDATE activities SET materialized_path = $1, path = $2, depth = $3, root = $4 WHERE id = $5', array(
                    $materialized_path,
                    $path,
                    $depth,
                    $root,
                    $response['id']
                ));
                pg_query_params($db_lnk, 'UPDATE activities SET freshness_ts = $1 WHERE root = $2', array(
                    $r_post['freshness_ts'],
                    $root
                ));
                $act_res = pg_query_params($db_lnk, 'SELECT * FROM activities WHERE root = $1', array(
                    $root
                ));
                $response['activity'] = pg_fetch_assoc($act_res);
            } else if ($r_resource_cmd == '/boards/?/lists/?/cards/?/copy') {
                if ($is_keep_attachment) {
                    pg_query_params($db_lnk, 'INSERT INTO card_attachments (created, modified, card_id, name, path, mimetype, list_id, board_id) SELECT created, modified, $1, name, path, mimetype, $2, $3 FROM card_attachments WHERE card_id = $4 ORDER BY id', array(
                        $response['id'],
                        $r_post['list_id'],
                        $r_post['board_id'],
                        $copied_card_id
                    ));
                }
                if ($is_keep_user) {
                    pg_query_params($db_lnk, 'INSERT INTO cards_users (created, modified, card_id, user_id) SELECT created, modified, $1, user_id  FROM cards_users WHERE card_id = $2 ORDER BY id', array(
                        $response['id'],
                        $copied_card_id
                    ));
                }
                if ($is_keep_label) {
                    pg_query_params($db_lnk, 'INSERT INTO cards_labels (created, modified, card_id, label_id, list_id, board_id) SELECT created, modified, $1, label_id, $2, $3 FROM cards_labels WHERE card_id = $4 ORDER BY id', array(
                        $response['id'],
                        $r_post['list_id'],
                        $r_post['board_id'],
                        $copied_card_id
                    ));
                }
                if ($is_keep_activity) {
                    pg_query_params($db_lnk, 'INSERT INTO activities (created, modified, card_id, user_id, list_id, board_id, foreign_id, type, comment, revisions, root, freshness_ts, depth, path, materialized_path) SELECT created, modified, $1, $2, $3, $4, foreign_id, type, comment, revisions, root, freshness_ts, depth, path, materialized_path FROM activities WHERE type = \'add_comment\' AND card_id = $5 ORDER BY id', array(
                        $response['id'],
                        $r_post['user_id'],
                        $r_post['list_id'],
                        $r_post['board_id'],
                        $copied_card_id
                    ));
                }
                if ($is_keep_checklist) {
                    pg_query_params($db_lnk, 'INSERT INTO checklists (created, modified, user_id, card_id, name, checklist_item_count, checklist_item_completed_count, position) SELECT created, modified, user_id, $1, name, checklist_item_count, checklist_item_completed_count, position FROM checklists WHERE card_id = $2 ORDER BY id', array(
                        $response['id'],
                        $copied_card_id
                    ));
                    $checklists = pg_query_params($db_lnk, 'SELECT id FROM checklists WHERE card_id = $1', array(
                        $response['id']
                    ));
                    $prev_checklists = pg_query_params($db_lnk, 'SELECT id FROM checklists WHERE card_id = $1', array(
                        $copied_card_id
                    ));
                    $prev_checklist_ids = array();
                    while ($prev_checklist_id = pg_fetch_assoc($prev_checklists)) {
                        $prev_checklist_ids[] = $prev_checklist_id['id'];
                    }
                    $i = 0;
                    while ($checklist_id = pg_fetch_assoc($checklists)) {
                        pg_query_params($db_lnk, 'INSERT INTO checklist_items (created, modified, user_id, card_id, name, checklist_id, is_completed, position) SELECT created, modified, user_id, $1, name , $2, is_completed, position FROM checklist_items WHERE checklist_id = $3 ORDER BY id', array(
                            $response['id'],
                            $checklist_id['id'],
                            $prev_checklist_ids[$i]
                        ));
                        $i++;
                    }
                }
                $foreign_ids['board_id'] = $r_post['board_id'];
                $foreign_ids['list_id'] = $r_post['list_id'];
                $foreign_ids['card_id'] = $response['id'];
                $comment = $authUser['username'] . ' copied this card "' . $srow['name'] . '" to ##CARD_NAME##';
                $response['activity'] = insertActivity($authUser['id'], $comment, 'copy_card', $foreign_ids, NULL, $response['id']);
                $response['cards'] = executeQuery('SELECT * FROM cards_listing WHERE id = $1', array(
                    $response['id']
                ));
                if (!empty($response['cards']['cards_checklists'])) {
                    $response['cards']['cards_checklists'] = json_decode($response['cards']['cards_checklists'], true);
                }
                if (!empty($response['cards']['cards_users'])) {
                    $response['cards']['cards_users'] = json_decode($response['cards']['cards_users'], true);
                }
                if (!empty($response['cards']['cards_voters'])) {
                    $response['cards']['cards_voters'] = json_decode($response['cards']['cards_voters'], true);
                }
                if (!empty($response['cards']['cards_subscribers'])) {
                    $response['cards']['cards_subscribers'] = json_decode($response['cards']['cards_subscribers'], true);
                }
                if (!empty($response['cards']['cards_labels'])) {
                    $response['cards']['cards_labels'] = json_decode($response['cards']['cards_labels'], true);
                }
                $activities = executeQuery('SELECT ( SELECT array_to_json(array_agg(row_to_json(cl.*))) AS array_to_json  FROM ( SELECT activities_listing.* FROM activities_listing activities_listing WHERE (activities_listing.card_id = cards.id) ORDER BY activities_listing.id DESC) cl) AS activities FROM cards cards WHERE id = $1', array(
                    $response['id']
                ));
                if (!empty($activities)) {
                    $response['cards']['activities'] = json_decode($activities['activities'], true);
                }
                $attachments = pg_query_params($db_lnk, 'SELECT * FROM card_attachments WHERE card_id = $1', array(
                    $response['id']
                ));
                while ($attachment = pg_fetch_assoc($attachments)) {
                    $response['cards']['attachments'][] = $attachment;
                }
            } else if ($r_resource_cmd == '/boards/?/lists/?/cards/?/users/?') {
                $sel_query = 'SELECT cu.card_id, cu.user_id, users.username, c.board_id, c.list_id, b.name as board_name FROM cards_users cu LEFT JOIN cards c ON cu.card_id = c.id LEFT JOIN users ON cu.user_id = users.id LEFT JOIN boards b ON c.board_id = b.id WHERE cu.card_id = $1 AND cu.user_id = $2';
                $get_details = pg_query_params($db_lnk, $sel_query, array(
                    $r_post['card_id'],
                    $r_post['user_id']
                ));
                $sel_details = pg_fetch_assoc($get_details);
                $foreign_ids['board_id'] = $sel_details['board_id'];
                $foreign_ids['list_id'] = $sel_details['list_id'];
                $foreign_ids['card_id'] = $r_post['card_id'];
                $user = executeQuery('SELECT * FROM users WHERE id = $1', array(
                    $r_post['user_id']
                ));
                if ($user) {
                    $emailFindReplace = array(
                        'mail' => 'newprojectuser',
                        '##USERNAME##' => $user['username'],
                        '##CURRENT_USER##' => $authUser['username'],
                        '##BOARD_NAME##' => $sel_details['board_name'],
                        '##BOARD_URL##' => 'http://' . $_SERVER['HTTP_HOST'] . '/#/board/' . $foreign_ids['board_id'] . '/card/' . $foreign_ids['card_id'],
                        'to' => $user['email']
                    );
                    sendMail($emailFindReplace);
                }
                $comment = $authUser['username'] . ' added "' . $sel_details['username'] . '" as member to this card ##CARD_LINK##';
                $response['activity'] = insertActivity($authUser['id'], $comment, 'add_card_user', $foreign_ids, '', $response['id']);
            } else if ($r_resource_cmd == '/boards/?/lists/?/cards/?/attachments') {
                $foreign_ids['board_id'] = $r_post['board_id'];
                $foreign_ids['list_id'] = $r_post['list_id'];
                $foreign_ids['card_id'] = $r_post['card_id'];
                $comment = $authUser['username'] . ' added attachment to this card ##CARD_LINK##';
                $response['activity'] = insertActivity($authUser['id'], $comment, 'add_card_attachment', $foreign_ids, NULL, $response['id']);
                foreach ($thumbsizes['CardAttachment'] as $key => $value) {
                    $mediadir = dirname(dirname(dirname(dirname(__FILE__)))) . '/client/img/' . $key . '/CardAttachment/' . $response['id'];
                    $list = glob($mediadir . '.*');
                    @unlink($list[0]);
                }
            } else if ($r_resource_cmd == '/boards/?/lists/?/cards/?/card_voters') {
                $previous_value = executeQuery('SELECT name FROM cards WHERE id = $1', array(
                    $r_resource_vars['cards']
                ));
                $foreign_ids['board_id'] = $r_resource_vars['boards'];
                $foreign_ids['list_id'] = $r_resource_vars['lists'];
                $foreign_ids['card_id'] = $r_post['card_id'];
                $comment = $authUser['username'] . ' voted on ##CARD_LINK##';
                $response['activity'] = insertActivity($authUser['id'], $comment, 'add_card_voter', $foreign_ids, '', $response['id']);
                $s_result = pg_query_params($db_lnk, 'SELECT * FROM card_voters_listing WHERE id = $1', array(
                    $response['id']
                ));
                $user = pg_fetch_assoc($s_result);
                $response['card_voters'] = $user;
            } else if ($r_resource_cmd == '/boards/?/users') {
                $s_result = pg_query_params($db_lnk, 'SELECT name FROM boards WHERE id = $1', array(
                    $r_post['board_id']
                ));
                $previous_value = pg_fetch_assoc($s_result);
                $foreign_ids['board_id'] = $r_resource_vars['boards'];
                $foreign_ids['board_id'] = $r_post['board_id'];
                $user = executeQuery('SELECT * FROM users WHERE id = $1', array(
                    $r_post['user_id']
                ));
                if ($user) {
                    $emailFindReplace = array(
                        'mail' => 'newprojectuser',
                        '##USERNAME##' => $user['username'],
                        '##CURRENT_USER##' => $authUser['username'],
                        '##BOARD_NAME##' => $previous_value['name'],
                        '##BOARD_URL##' => 'http://' . $_SERVER['HTTP_HOST'] . '/#/board/' . $r_post['board_id'],
                        'to' => $user['email']
                    );
                    sendMail($emailFindReplace);
                }
                $comment = $authUser['username'] . ' added member to board';
                $response['activity'] = insertActivity($authUser['id'], $comment, 'add_board_user', $foreign_ids, '', $response['id']);
            } else if ($r_resource_cmd == '/organizations/?/users/?') {
                $response['organizations_users'] = executeQuery('SELECT * FROM organizations_users_listing WHERE id = $1', array(
                    $response['id']
                ));
                $response['organizations_users']['boards_users'] = json_decode($response['organizations_users']['boards_users'], true);
            }
        }
    }
    // todo: $sql set as true query not execute, so add condition ($sql !== true)
    if ($sql && ($sql !== true) && !empty($json) && !empty($response['id'])) {
        if ($result = pg_query_params($db_lnk, $sql, array())) {
            $data = array();
            $count = pg_num_rows($result);
            $i = 0;
            while ($row = pg_fetch_row($result)) {
                if ($i == 0 && $count > 1) {
                    echo '[';
                }
                echo $row[0];
                $i++;
                if ($i < $count) {
                    echo ',';
                } else {
                    if ($count > 1) {
                        echo ']';
                    }
                }
            }
            pg_free_result($result);
        }
    } else {
        echo json_encode($response);
    }
}
/**
 * Common method to handle PUT method
 *
 * @param  $r_resource_cmd
 * @param  $r_resource_vars
 * @param  $r_resource_filters
 * @param  $r_put
 * @return mixed
 */
function r_put($r_resource_cmd, $r_resource_vars, $r_resource_filters, $r_put)
{
    global $r_debug, $db_lnk, $authUser, $thumbsizes, $_server_domain_url;
    $fields = 'modified';
    $values = array(
        'now()'
    );
    $sfields = '';
    $pg_params = array();
    $emailFindReplace = $response = array();
    $res_status = true;
    $sql = $json = false;
    $table_name = '';
    $id = '';
    unset($r_put['temp_id']);
    switch ($r_resource_cmd) {
    case '/users/activation/?': //users activation
        $user = executeQuery('SELECT * FROM users WHERE id = $1 AND is_email_confirmed = $2', array(
            $r_put['id'],
            'false'
        ));
        if ($user && (md5($user['username']) == $r_put['hash'])) {
            $sql = pg_query_params($db_lnk, "UPDATE users SET is_email_confirmed = $1, is_active = $2 WHERE id = $3", array(
                'true',
                'true',
                $r_put['id']
            ));
            if ($sql) {
                $emailFindReplace = array(
                    'mail' => 'welcome',
                    '##USERNAME##' => $user['username'],
                    'to' => $user['email']
                );
                sendMail($emailFindReplace);
                $response['success'] = 'Your activation has been confirmed . You can now login to the site';
            } else {
                $response['error'] = 'Invalid Activation URL';
            }
        } else {
            $response['error'] = 'Invalid Activation URL';
        }
        break;

    case '/organizations/?':
        $json = true;
        $table_name = 'organizations';
        $id = $r_resource_vars['organizations'];
        if (isset($r_put['logo_url']) && $r_put['logo_url'] == 'NULL') {
            foreach ($thumbsizes['Organization'] as $key => $value) {
                $mediadir = dirname(dirname(dirname(dirname(__FILE__)))) . '/client/img/' . $key . '/Organization/' . $id;
                $list = glob($mediadir . '.*');
                @unlink($list[0]);
            }
        }
        $organization = executeQuery('SELECT id FROM ' . $table_name . ' WHERE id = $1', array(
            $r_resource_vars['organizations']
        ));
        break;

    case '/organizations_users/?':
        $json = true;
        $table_name = 'organizations_users';
        $id = $r_resource_vars['organizations_users'];
        $organizations_user = executeQuery('SELECT id FROM ' . $table_name . ' WHERE id =  $1', array(
            $r_resource_vars['organizations_users']
        ));
        break;

    case '/boards_users/?':
        $json = true;
        $table_name = 'boards_users';
        $id = $r_resource_vars['boards_users'];
        $boards_users = executeQuery('SELECT id FROM ' . $table_name . ' WHERE id =  $1', array(
            $r_resource_vars['boards_users']
        ));
        break;

    case '/boards/?':
        $table_name = 'boards';
        $id = $r_resource_vars['boards'];
        $previous_value = executeQuery('SELECT * FROM ' . $table_name . ' WHERE id = $1', array(
            $r_resource_vars['boards']
        ));
        $board_visibility = array(
            'Private',
            'Organization',
            'Public'
        );
        $foreign_ids['board_id'] = $r_resource_vars['boards'];
        if (isset($r_put['board_visibility'])) {
            $comment = $authUser['username'] . ' changed visibility to ' . $board_visibility[$r_put['board_visibility']];
            $activity_type = 'change_visibility';
        } else if (!empty($r_put['is_closed'])) {
            $comment = $authUser['username'] . ' closed ##BOARD_NAME## board.';
            $activity_type = 'reopen_board';
        } else if (isset($r_put['is_closed'])) {
            $comment = $authUser['username'] . ' reopened ##BOARD_NAME## board.';
            $activity_type = 'reopen_board';
        } else if (isset($r_put['name'])) {
            $comment = $authUser['username'] . ' renamed ##BOARD_NAME## board.';
            $activity_type = 'edit_board';
        } else if (isset($r_put['background_picture_url']) || isset($r_put['background_pattern_url']) || isset($r_put['background_color'])) {
            if (empty($previous_value['background_picture_url']) && empty($previous_value['background_pattern_url']) && empty($previous_value['background_color'])) {
                $comment = $authUser['username'] . ' added background to board "' . $previous_value['name'] . '"';
                $activity_type = 'add_background';
            } else {
                $comment = $authUser['username'] . ' changed backgound to board "' . $previous_value['name'] . '"';
                $activity_type = 'change_background';
            }
        }
        break;

    case '/boards/?/lists/?': //lists update
        $json = true;
        $table_name = 'lists';
        $id = $r_resource_vars['lists'];
        if (isset($r_put['position']) || isset($r_put['is_archived'])) {
            $s_sql = 'SELECT name, board_id, position FROM ' . $table_name . ' WHERE id = $1';
            $s_result = pg_query_params($db_lnk, $s_sql, array(
                $r_resource_vars['lists']
            ));
            $previous_value = pg_fetch_assoc($s_result);
        }
        $foreign_ids['board_id'] = $r_resource_vars['boards'];
        $foreign_ids['list_id'] = $r_resource_vars['lists'];
        if (isset($r_put['board_id']) && !empty($r_put['board_id'])) {
            pg_query_params($db_lnk, 'UPDATE cards SET board_id = $1 WHERE list_id = $2', array(
                $r_put['board_id'],
                $r_resource_vars['lists']
            ));
            pg_query_params($db_lnk, 'UPDATE card_attachments SET board_id = $1 WHERE list_id = $2', array(
                $r_put['board_id'],
                $r_resource_vars['lists']
            ));
        }
        if (isset($r_put['position'])) {
            $comment = $authUser['username'] . ' changed list ' . $previous_value['name'] . ' position.';
            $activity_type = 'change_list_position';
            $start = $end = 0;
            if ($previous_value['position'] > $r_put['position']) {
                $start = $r_put['position'];
                $end = $previous_value['position'];
                $postion = ' position + 1';
            } else {
                $start = $previous_value['position'];
                $end = $r_put['position'];
                $postion = ' position - 1';
            }
        } else if (isset($previous_value) && isset($r_put['is_archived'])) {
            $id = $r_resource_vars['lists'];
            $foreign_ids['board_id'] = $r_resource_vars['boards'];
            $foreign_ids['list_id'] = $r_resource_vars['lists'];
            $comment = $authUser['username'] . ' archived ##LIST_NAME##';
            $activity_type = 'archive_list';
        } else {
            $id = $r_resource_vars['lists'];
            $comment = $authUser['username'] . ' renamed this list.';
            $activity_type = 'edit_list';
        }
        break;

    case '/boards/?/lists/?/cards': //card list_id(move cards all in this list) update
        $json = true;
        $table_name = 'cards';
        $id = $r_resource_vars['lists'];
        $foreign_ids['board_id'] = $r_resource_vars['boards'];
        $foreign_ids['list_id'] = $r_resource_vars['lists'];
        $old_list = executeQuery('SELECT name FROM lists WHERE id = $1', array(
            $foreign_ids['list_id']
        ));
        if (!empty($r_put['list_id'])) {
            pg_query_params($db_lnk, 'UPDATE card_attachments SET list_id = $1 WHERE list_id = $2', array(
                $r_put['list_id'],
                $foreign_ids['list_id']
            ));
            pg_query_params($db_lnk, 'UPDATE cards_labels SET list_id = $1 WHERE list_id = $2', array(
                $r_put['list_id'],
                $foreign_ids['list_id']
            ));
            $new_list = executeQuery('SELECT name FROM lists WHERE id =  $1', array(
                $r_put['list_id']
            ));
            $comment = $authUser['username'] . ' moved cards FROM ' . $old_list['name'] . ' to ' . $new_list['name'];
            $activity_type = 'moved_list_card';
            $revisions['old_value']['list_id'] = $foreign_ids['list_id'];
            $revisions['new_value'] = $r_put;
        } else if (isset($r_put['is_archived']) && !empty($r_put['is_archived'])) {
            $comment = $authUser['username'] . ' archived cards in ' . $old_list['name'];
            $activity_type = 'archived_card';
        } else {
            $comment = $authUser['username'] . ' edited ' . $old_list['name'] . ' card in this board.';
            $activity_type = 'edit_card';
        }
        break;

    case '/boards/?/lists/?/cards/?': //cards update
        $table_name = 'cards';
        $id = $r_resource_vars['cards'];
        $foreign_ids['board_id'] = $r_resource_vars['boards'];
        $foreign_ids['list_id'] = $r_resource_vars['lists'];
        $foreign_ids['card_id'] = $r_resource_vars['cards'];
        $activity_type = 'edit_card';
        $id = $r_resource_vars['cards'];
        $s_result = pg_query_params($db_lnk, 'SELECT name, board_id, list_id, position, description, due_date FROM ' . $table_name . ' WHERE id = $1', array(
            $r_resource_vars['cards']
        ));
        $previous_value = pg_fetch_assoc($s_result);
        if (isset($r_put['position'])) {
            $start = $end = 0;
            if ($previous_value['position'] > $r_put['position']) {
                $start = $r_put['position'];
                $end = $previous_value['position'];
                $postion = ' position + 1';
            } else {
                $start = $previous_value['position'];
                $end = $r_put['position'];
                $postion = ' position - 1';
            }
            if (!empty($r_put['list_id'])) {
                $foreign_ids['list_id'] = $r_put['list_id'];
                pg_query_params($db_lnk, 'UPDATE card_attachments SET list_id = $1 WHERE list_id = $2', array(
                    $r_put['list_id'],
                    $r_resource_vars['lists']
                ));
            }
            $comment = '##USER_NAME## moved this card to different position.';
            $activity_type = 'change_card_position';
        }
        if (isset($previous_value) && isset($r_put['is_archived'])) {
            if ($r_put['is_archived']) {
                $comment = '##USER_NAME## archived ##CARD_LINK##';
            } else {
                $comment = '##USER_NAME## send back ' . $previous_value['name'] . ' to board';
            }
            $foreign_ids['board_id'] = $r_resource_vars['boards'];
            $foreign_ids['list_id'] = $r_resource_vars['lists'];
        }
        if (isset($r_put['due_date']) && ($r_put['due_date'] != 'NULL' && $r_put['due_date'] != '')) {
            if (isset($previous_value['due_date']) && ($previous_value['due_date'] != 'NULL' && $previous_value['due_date'] != '')) {
                $comment = '##USER_NAME## updated due date to this card ##CARD_LINK##';
                $activity_type = 'edit_card_duedate';
            } else {
                $comment = '##USER_NAME## SET due date to this card ##CARD_LINK##';
                $activity_type = 'add_card_duedate';
            }
        } else if (isset($r_put['due_date']) && ($r_put['due_date'] == 'NULL' || $r_put['due_date'] == '')) {
            $comment = '##USER_NAME## deleted due date FROM this card ##CARD_LINK##';
            $activity_type = 'delete_card_duedate';
        }
        if (isset($previous_value['board_id']) && isset($r_put['board_id']) && $r_put['board_id'] != $previous_value['board_id']) {
            $comment = '##USER_NAME## moved this card to different board.';
        }
        if (isset($previous_value['name']) && isset($r_put['name']) && $r_put['name'] != $previous_value['name']) {
            $comment = '##USER_NAME## renamed ##CARD_LINK##';
        }
        if (!isset($previous_value['description']) && isset($r_put['description'])) {
            $comment = '##USER_NAME## added card description in ##CARD_LINK## - ##DESCRIPTION##';
            $activity_type = 'add_card_desc';
        } else if (isset($previous_value) && isset($r_put['description']) && $r_put['description'] != $previous_value['description']) {
            if (empty($r_put['description'])) {
                $comment = '##USER_NAME## removed description from ##CARD_LINK##';
            } else {
                $comment = '##USER_NAME## updated description on ##CARD_LINK## - ##DESCRIPTION##';
            }
            $activity_type = 'edit_card_desc';
        }
        if (isset($previous_value['list_id']) && isset($r_put['list_id']) && $r_put['list_id'] != $previous_value['list_id']) {
            $s_result = pg_query_params($db_lnk, 'SELECT name FROM lists WHERE id = $1', array(
                $r_put['list_id']
            ));
            $list_value = pg_fetch_assoc($s_result);
            $comment = '##USER_NAME## moved this card (' . $previous_value['name'] . ') to different list (' . $list_value['name'] . ').';
        }
        unset($r_put['start']);
        break;

    case '/boards/?/lists/?/cards/?/comments/?': // comment update
        $table_name = 'activities';
        $id = $r_resource_vars['comments'];
        $foreign_ids['board_id'] = $r_resource_vars['boards'];
        $foreign_ids['list_id'] = $r_resource_vars['lists'];
        $foreign_ids['card_id'] = $r_resource_vars['cards'];
        $comment = '##USER_NAME## updated comment to this card ##CARD_LINK##';
        $activity_type = 'update_card_comment';
        break;

    case '/boards/?/lists/?/cards/?/checklists/?':
        $table_name = 'checklists';
        $id = $r_resource_vars['checklists'];
        $foreign_ids['board_id'] = $r_resource_vars['boards'];
        $foreign_ids['list_id'] = $r_resource_vars['lists'];
        $foreign_ids['card_id'] = $r_resource_vars['cards'];
        $comment = '##USER_NAME## updated checklist of card "##CARD_LINK##"';
        unset($r_put['checklists_items']);
        unset($r_put['created']);
        unset($r_put['modified']);
        unset($r_put['checklist_item_completed_count']);
        unset($r_put['checklist_item_count']);
        unset($r_put['is_offline']);
        unset($r_put['list_id']);
        unset($r_put['board_id']);
        if (isset($r_put['position']) && !empty($r_put['position'])) {
            $comment.= ' position';
        }
        $activity_type = 'update_card_checklist';
        break;

    case '/boards/?/lists/?/cards/?/checklists/?/items/?':
        $table_name = 'checklist_items';
        $id = $r_resource_vars['items'];
        $foreign_ids['board_id'] = $r_resource_vars['boards'];
        $foreign_ids['list_id'] = $r_resource_vars['lists'];
        $foreign_ids['card_id'] = $r_resource_vars['cards'];
        unset($r_put['created']);
        unset($r_put['modified']);
        unset($r_put['is_offline']);
        unset($r_put['list_id']);
        unset($r_put['board_id']);
        $prev_value = executeQuery('SELECT * FROM ' . $table_name . ' WHERE id =  $1', array(
            $r_resource_vars['items']
        ));
        $activity_type = 'update_card_checklist_item';
        if (isset($r_put['is_completed']) && $r_put['is_completed'] == 'true') {
            $comment = '##USER_NAME## updated ##CHECKLIST_ITEM_NAME## as completed on card ##CARD_LINK##';
        } else if (isset($r_put['position'])) {
            $comment = $authUser['username'] . ' moved checklist item on card ##CARD_LINK##';
            if (isset($r_put['checklist_id']) && $r_put['checklist_id'] != $prev_value['checklist_id']) {
                $activity_type = 'moved_card_checklist_item';
            }
        } else if (isset($r_put['is_completed']) && $r_put['is_completed'] == 'false') {
            $comment = '##USER_NAME## updated ##CHECKLIST_ITEM_NAME## as incomplete on card ##CARD_LINK##';
        } else {
            $comment = '##USER_NAME## updated item name as ##CHECKLIST_ITEM_NAME## in card ##CARD_LINK##';
        }
        break;

    case '/activities/undo/?':
        $activity = executeQuery('SELECT * FROM activities WHERE id =  $1', array(
            $r_resource_vars['undo']
        ));
        if (!empty($activity['revisions']) && trim($activity['revisions']) != '') {
            $revisions = unserialize($activity['revisions']);
            if ($activity['type'] == 'update_card_checklist_item') {
                $table_name = 'checklist_items';
                $id = $activity['foreign_id'];
                $r_put = $revisions['old_value'];
                $foreign_ids['board_id'] = $activity['board_id'];
                $foreign_ids['list_id'] = $activity['list_id'];
                $foreign_ids['card_id'] = $activity['card_id'];
                $comment = '##USER_NAME## undo this card ##CARD_LINK## checklist item ##CHECKLIST_ITEM_NAME##';
                $activity_type = 'update_card_checklist_item';
                $response['undo']['checklist_item'] = $r_put;
                $response['undo']['checklist_item']['id'] = $id;
            } else if ($activity['type'] == 'update_card_checklist') {
                $table_name = 'checklists';
                $id = $activity['foreign_id'];
                $r_put = $revisions['old_value'];
                $foreign_ids['board_id'] = $activity['board_id'];
                $foreign_ids['list_id'] = $activity['list_id'];
                $foreign_ids['card_id'] = $activity['card_id'];
                $comment = '##USER_NAME## undo this card ##CARD_LINK## checklist ##CHECKLIST_NAME##';
                $activity_type = 'update_card_checklist';
                $response['undo']['checklist'] = $r_put;
                $response['undo']['checklist']['id'] = $id;
            } else if (!empty($activity['card_id'])) {
                $table_name = 'cards';
                $id = $activity['card_id'];
                $r_put = $revisions['old_value'];
                $foreign_ids['board_id'] = $activity['board_id'];
                $foreign_ids['list_id'] = $activity['list_id'];
                $foreign_ids['card_id'] = $activity['card_id'];
                $comment = '##USER_NAME## undo this card ##CARD_LINK##';
                $activity_type = 'edit_card';
                $response['undo']['card'] = $r_put;
                $response['undo']['card']['id'] = $id;
            } else if (!empty($activity['list_id'])) {
                $table_name = 'lists';
                $id = $activity['list_id'];
                $r_put = $revisions['old_value'];
                $foreign_ids['board_id'] = $activity['board_id'];
                $foreign_ids['list_id'] = $activity['list_id'];
                $comment = '##USER_NAME## undo this list.';
                $activity_type = 'edit_list';
                $response['undo']['list'] = $r_put;
                $response['undo']['list']['id'] = $id;
            } else if (!empty($activity['board_id'])) {
                $table_name = 'boards';
                $id = $activity['board_id'];
                $r_put = $revisions['old_value'];
                $foreign_ids['board_id'] = $activity['board_id'];
                $comment = '##USER_NAME## undo this board.';
                $activity_type = 'edit_board';
                $response['undo']['board'] = $r_put;
                $response['undo']['board']['id'] = $id;
            }
        }
        break;

    case '/users/?': //users
        $table_name = 'users';
        $id = $r_resource_vars['users'];
        break;

    case '/email_templates/?': //email template update
        $json = true;
        $table_name = 'email_templates';
        $id = $r_resource_vars['email_templates'];
        $response['success'] = 'Email Template has been updated successfully.';
        break;

    case '/boards/?/board_subscribers/?': //boards subscribers update
        $json = true;
        $table_name = 'board_subscribers';
        $id = $r_resource_vars['board_subscribers'];
        $response['success'] = 'Updated successfully.';
        $response['id'] = $id;
        break;

    case '/boards/?/lists/?/list_subscribers/?': //lists update
        $json = true;
        $table_name = 'list_subscribers';
        $id = $r_resource_vars['list_subscribers'];
        break;

    default:
        header($_SERVER['SERVER_PROTOCOL'] . ' 501 Not Implemented', true, 501);
        break;
    }
    if (!empty($table_name) && !empty($id)) {
        $put = getbindValues($table_name, $r_put);
        if ($table_name == 'users') {
            unset($put['ip_id']);
        }
        foreach ($put as $key => $value) {
            if ($key != 'id') {
                $fields.= ', ' . $key;
                if ($value === false) {
                    array_push($values, 'false');
                } elseif ($value === 'NULL' || $value === 'NULL') {
                    array_push($values, NULL);
                } else {
                    array_push($values, $value);
                }
            }
            if ($key != 'id' && $key != 'position') {
                $sfields.= (empty($sfields)) ? $key : ", " . $key;
            }
        }
        if (!empty($comment)) {
            $revision = '';
            if ($activity_type != 'reopen_board' && $activity_type != 'moved_list_card' && $activity_type != 'moved_card_checklist_item') {
                $revisions['old_value'] = executeQuery('SELECT ' . $sfields . ' FROM ' . $table_name . ' WHERE id =  $1', array(
                    $id
                ));
                unset($r_put['position']);
                unset($r_put['id']);
                $revisions['new_value'] = $r_put;
                $revision = serialize($revisions);
            }
            $foreign_id = $id;
            if ($activity_type == 'moved_list_card') {
                $foreign_id = $r_put['list_id'];
            }
            $response['activity'] = insertActivity($authUser['id'], $comment, $activity_type, $foreign_ids, $revision, $foreign_id);
            if (!empty($response['activity']['revisions']) && trim($response['activity']['revisions']) != '') {
                $revisions = unserialize($response['activity']['revisions']);
            }
            if (!empty($revisions) && !empty($revisions['new_value']) && $response['activity']['type'] != 'moved_card_checklist_item') {
                foreach ($revisions['new_value'] as $key => $value) {
                    if ($key != 'is_archived' && $key != 'is_deleted' && $key != 'created' && $key != 'modified' && $key != 'is_offline' && $key != 'uuid' && $key != 'to_date' && $key != 'temp_id' && $activity_type != 'moved_card_checklist_item' && $activity_type != 'add_card_desc' && $activity_type != 'add_card_duedate' && $activity_type != 'delete_card_duedate' && $activity_type != 'add_background' && $activity_type != 'change_background' && $activity_type != 'change_visibility') {
                        $old_val = (isset($revisions['old_value'][$key])) ? $revisions['old_value'][$key] : '';
                        $new_val = (isset($revisions['new_value'][$key])) ? $revisions['new_value'][$key] : '';
                        $dif[] = nl2br(getRevisiondifference($old_val, $new_val));
                    }
                    if ($activity_type == 'add_card_desc' || $activity_type == 'edit_card_duedate' || $activity_type == 'add_background' || $activity_type == 'change_background' || $activity_type == 'change_visibility') {
                        $dif[] = $revisions['new_value'][$key];
                    }
                }
            }
            if (isset($dif)) {
                $response['activity']['difference'] = $dif;
            }
            if (isset($r_put['description'])) {
                $response['activity']['description'] = $r_put['description'];
            }
        }
        if ($r_resource_cmd == '/users/?') {
            $user = executeQuery('SELECT boards_users FROM users_listing WHERE id = $1', array(
                $r_resource_vars['users']
            ));
            $board_ids = array();
            if (!empty($user['boards_users'])) {
                $boards_users = json_decode($user['boards_users'], true);
                foreach ($boards_users as $boards_user) {
                    $board_ids[] = $boards_user['board_id'];
                }
            }
            $board_id = implode(',', $board_ids);
            $last_activity_status = executeQuery('SELECT * FROM activities_listing al WHERE board_id IN ( $1 ) ORDER BY id DESC LIMIT 1', array(
                $board_id
            ));
        }
        $val = '';
        for ($i = 1, $len = count($values); $i <= $len; $i++) {
            $val.= '$' . $i;
            $val.= ($i != $len) ? ', ' : '';
        }
        array_push($values, $id);
        $query = 'UPDATE ' . $table_name . ' SET (' . $fields . ') = (' . $val . ') WHERE id = ' . '$' . $i;
        if ($r_resource_cmd == '/boards/?/lists/?/cards') {
            $query = 'UPDATE ' . $table_name . ' SET (' . $fields . ') = (' . $val . ') WHERE list_id = ' . '$' . $i;
        }
        $result = pg_query_params($db_lnk, $query, $values);
    }
    if (!empty($sql) && !empty($json)) {
        if ($table_name == 'organizations') {
            $sql = 'SELECT row_to_json(d) FROM (SELECT * FROM organizations_listing ul WHERE id = $1) as d ';
            array_push($pg_params, $r_resource_vars['organizations']);
        } elseif ($table_name == 'organizations_users') {
            $sql = 'SELECT row_to_json(d) FROM (SELECT * FROM organizations_users_listing ul WHERE id = $1) as d ';
            array_push($pg_params, $r_resource_vars['organizations_users']);
        } elseif ($table_name == 'lists') {
            $sql = 'SELECT row_to_json(d) FROM (SELECT * FROM lists_listing WHERE id = $1) as d ';
            array_push($pg_params, $r_resource_vars['lists']);
        } elseif ($table_name == 'cards' && !empty($r_resource_vars['cards'])) {
            $sql = 'SELECT row_to_json(d) FROM (SELECT * FROM cards_listing WHERE id = $1) as d ';
            array_push($pg_params, $r_resource_vars['cards']);
        } elseif ($table_name == 'cards' && !empty($r_resource_vars['lists'])) {
            $sql = 'SELECT row_to_json(d) FROM (SELECT * FROM cards_listing WHERE list_id = $1) as d ';
            array_push($pg_params, $r_resource_vars['lists']);
        }
        if ($result = pg_query_params($db_lnk, $sql, $pg_params)) {
            $data = array();
            $count = pg_num_rows($result);
            $i = 0;
            while ($row = pg_fetch_row($result)) {
                if ($i == 0 && $count > 1) {
                    echo '[';
                }
                echo $row[0];
                $i++;
                if ($i < $count) {
                    echo ',';
                } else {
                    if ($count > 1) {
                        echo ']';
                    }
                }
            }
            pg_free_result($result);
        }
    } else {
        echo json_encode($response);
    }
}
/**
 * Common method to handle DELETE method
 *
 * @param  $r_resource_cmd
 * @param  $r_resource_vars
 * @param  $r_resource_filters
 * @return mixed
 */
function r_delete($r_resource_cmd, $r_resource_vars, $r_resource_filters)
{
    global $r_debug, $db_lnk, $authUser, $_server_domain_url;
    $sql = false;
    $pg_params = array();
    $response = array();
    switch ($r_resource_cmd) {
    case '/organizations/?': //organizations delete
        $sql = 'DELETE FROM organizations WHERE id= $1';
        array_push($pg_params, $r_resource_vars['organizations']);
        pg_query_params($db_lnk, 'UPDATE boards SET organization_id = $1, board_visibility = $2 WHERE organization_id= $3', array(
            0,
            0,
            $r_resource_vars['organizations']
        ));
        break;

    case '/organizations_users/?': //organizations delete
        $sql = 'DELETE FROM organizations_users WHERE id= $1';
        array_push($pg_params, $r_resource_vars['organizations_users']);
        break;

    case '/boards_users/?': //board user delete
        $s_result = pg_query_params($db_lnk, 'SELECT username, board_id, board_name FROM boards_users_listing WHERE id = $1', array(
            $r_resource_vars['boards_users']
        ));
        $previous_value = pg_fetch_assoc($s_result);
        $foreign_ids['board_id'] = $previous_value['board_id'];
        $comment = $authUser['username'] . ' removed member "' . $previous_value['username'] . '" from board';
        $response['activity'] = insertActivity($authUser['id'], $comment, 'delete_board_user', $foreign_ids, '', $r_resource_vars['boards_users']);
        $sql = 'DELETE FROM boards_users WHERE id= $1';
        array_push($pg_params, $r_resource_vars['boards_users']);
        break;

    case '/boards/?/lists/?': //lists delete
        $s_result = pg_query_params($db_lnk, 'SELECT name, board_id, position FROM lists WHERE id = $1', array(
            $r_resource_vars['lists']
        ));
        $previous_value = pg_fetch_assoc($s_result);
        $foreign_id['board_id'] = $r_resource_vars['boards'];
        $foreign_id['list_id'] = $r_resource_vars['lists'];
        $comment = $authUser['username'] . ' deleted "' . $previous_value['name'] . '"';
        $response['activity'] = insertActivity($authUser['id'], $comment, 'delete_list', $foreign_id);
        $sql = 'DELETE FROM lists WHERE id= $1';
        array_push($pg_params, $r_resource_vars['lists']);
        break;

    case '/boards/?/lists/?/cards/?/card_voters/?':
        $sql = 'DELETE FROM card_voters WHERE id = $1';
        array_push($pg_params, $r_resource_vars['card_voters']);
        $previous_value = executeQuery('SELECT name FROM cards WHERE id =  $1', array(
            $r_resource_vars['cards']
        ));
        $foreign_ids['board_id'] = $r_resource_vars['boards'];
        $foreign_ids['list_id'] = $r_resource_vars['lists'];
        $foreign_ids['card_id'] = $r_resource_vars['cards'];
        $comment = $authUser['username'] . ' unvoted this card ##CARD_LINK##';
        $response['activity'] = insertActivity($authUser['id'], $comment, 'unvote_card', $foreign_ids, NULL, $r_resource_vars['card_voters']);
        break;

    case '/boards/?/lists/?/cards/?/comments/?': // comment DELETE
        $sql = 'DELETE FROM activities WHERE id = $1';
        array_push($pg_params, $r_resource_vars['comments']);
        $previous_value = executeQuery('SELECT name FROM cards WHERE id =  $1', array(
            $r_resource_vars['cards']
        ));
        $foreign_ids['board_id'] = $r_resource_vars['boards'];
        $foreign_ids['list_id'] = $r_resource_vars['lists'];
        $foreign_ids['card_id'] = $r_resource_vars['cards'];
        $comment = $authUser['username'] . ' deleted comment in card ##CARD_LINK##';
        $response['activity'] = insertActivity($authUser['id'], $comment, 'delete_card_comment', $foreign_ids, NULL, $r_resource_vars['comments']);
        break;

    case '/boards/?/lists/?/cards/?':
        $s_result = pg_query_params($db_lnk, 'SELECT name, board_id, position FROM cards WHERE id = $1', array(
            $r_resource_vars['cards']
        ));
        $previous_value = pg_fetch_assoc($s_result);
        $foreign_id['board_id'] = $r_resource_vars['boards'];
        $foreign_id['list_id'] = $r_resource_vars['lists'];
        $foreign_id['card_id'] = $r_resource_vars['cards'];
        $comment = $authUser['username'] . ' deleted card ' . $previous_value['name'];
        $response['activity'] = insertActivity($authUser['id'], $comment, 'delete_card', $foreign_id);
        $sql = 'DELETE FROM cards WHERE id = $1';
        array_push($pg_params, $r_resource_vars['cards']);
        break;

    case '/boards/?/lists/?/cards/?/attachments/?': //card view
        $sql = 'DELETE FROM card_attachments WHERE id = $1';
        array_push($pg_params, $r_resource_vars['attachments']);
        $foreign_ids['board_id'] = $r_resource_vars['boards'];
        $foreign_ids['list_id'] = $r_resource_vars['lists'];
        $foreign_ids['card_id'] = $r_resource_vars['cards'];
        $comment = $authUser['username'] . ' deleted attachment from card ##CARD_LINK##';
        $response['activity'] = insertActivity($authUser['id'], $comment, 'delete_card_attachment', $foreign_ids, NULL, $r_resource_vars['attachments']);
        break;

    case '/boards/?/lists/?/cards/?/checklists/?':
        pg_query_params($db_lnk, 'DELETE FROM checklist_items WHERE checklist_id = $1', array(
            $r_resource_vars['checklists']
        ));
        $foreign_ids['board_id'] = $r_resource_vars['boards'];
        $foreign_ids['list_id'] = $r_resource_vars['lists'];
        $foreign_ids['card_id'] = $r_resource_vars['cards'];
        $comment = $authUser['username'] . ' deleted checklist from card ##CARD_LINK##';
        $response['activity'] = insertActivity($authUser['id'], $comment, 'delete_checklist', $foreign_ids, NULL, $r_resource_vars['checklists']);
        $sql = 'DELETE FROM checklists WHERE id = $1';
        array_push($pg_params, $r_resource_vars['checklists']);
        break;

    case '/boards/?/lists/?/cards/?/checklists/?/items/?':
        $foreign_ids['board_id'] = $r_resource_vars['boards'];
        $foreign_ids['list_id'] = $r_resource_vars['lists'];
        $foreign_ids['card_id'] = $r_resource_vars['cards'];
        $comment = $authUser['username'] . ' deleted checklist item from card ##CARD_LINK##';
        $response['activity'] = insertActivity($authUser['id'], $comment, 'delete_checklist_item', $foreign_ids, NULL, $r_resource_vars['items']);
        $sql = 'DELETE FROM checklist_items WHERE id = $1';
        array_push($pg_params, $r_resource_vars['items']);
        break;

    case '/boards/?/lists/?/cards/?/cards_users/?':
        $foreign_ids['board_id'] = $r_resource_vars['boards'];
        $foreign_ids['list_id'] = $r_resource_vars['lists'];
        $foreign_ids['card_id'] = $r_resource_vars['cards'];
        $comment = $authUser['username'] . ' deleted member from card ##CARD_LINK##';
        $response['activity'] = insertActivity($authUser['id'], $comment, 'delete_card_users', $foreign_ids, NULL, $r_resource_vars['cards_users']);
        $sql = 'DELETE FROM cards_users WHERE id = $1';
        array_push($pg_params, $r_resource_vars['cards_users']);
        break;

    case '/users/?': //users delete
        $sql = 'DELETE FROM users WHERE id= $1';
        array_push($pg_params, $r_resource_vars['users']);
        break;

    case '/boards/?/lists/?/cards/?':
        $foreign_id['board_id'] = $r_resource_vars['boards'];
        $foreign_id['list_id'] = $r_resource_vars['lists'];
        $foreign_id['card_id'] = $r_resource_vars['cards'];
        $comment = $authUser['username'] . ' deleted card ##CARD_NAME##';
        $response['activity'] = insertActivity($authUser['id'], $comment, 'delete_card', $foreign_id);
        $sql = 'UPDATE cards SET is_deleted = true WHERE id= $1';
        array_push($pg_params, $r_resource_vars['cards']);
        break;

    default:
        header($_SERVER['SERVER_PROTOCOL'] . ' 501 Not Implemented', true, 501);
        break;
    }
    if (!empty($sql)) {
        $result = pg_query_params($db_lnk, $sql, $pg_params);
        $response['error'] = array(
            'code' => (!$result) ? 1 : 0
        );
    }
    echo json_encode($response);
}
$exception_url = array(
    '/users/forgotpassword',
    '/users/register',
    '/users/login',
    '/users/activation/?',
    '/settings',
    '/boards/?'
);
if (!empty($_GET['_url']) && $db_lnk) {
    $r_debug.= __LINE__ . ': ' . $_GET['_url'] . "\n";
    $url = '/' . $_GET['_url'];
    $url = str_replace('/v' . R_API_VERSION, '', $url);
    // routes...
    // Samples: 1. /products.json
    //          2. /products.json?page=1&key1=val1
    //          3. /users/5/products/10.json
    //          4. /products/10.json
    $_url_parts_with_querystring = explode('?', $url);
    $_url_parts_with_ext = explode('.', $_url_parts_with_querystring[0]);
    $r_resource_type = @$_url_parts_with_ext[1]; // 'json'
    $r_resource_filters = $_GET;
    unset($r_resource_filters['_url']); // page=1&key1=val1
    // /users/5/products/10 -> /users/?/products/? ...
    $r_resource_cmd = preg_replace('/\/\d+/', '/?', $_url_parts_with_ext[0]);
    header('Content-Type: application/json');
    if ($r_resource_cmd != '/users/login') {
        if (!empty($_GET['token'])) {
            // Generate full URL using PHP_SELF server variable
            $post_url = $_server_domain_url . str_replace('r.php', 'resource.php', $_SERVER['PHP_SELF']);
            $response = doPost($post_url, array(
                'access_token' => $_GET['token']
            ));
            if (!empty($response['error'])) {
                $response['error']['type'] = 'OAuth';
                echo json_encode($response);
                exit;
            }
            $notify_count = $user = $role_links = array();
            if (!empty($response['username'])) {
                $user = executeQuery('SELECT * FROM users WHERE username = $1', array(
                    $response['username']
                ));
                $notify_count = executeQuery('SELECT count(a.*) AS notify_count FROM activities a LEFT JOIN boards_users bu ON bu.board_id = a.board_id WHERE bu.user_id = $1', array(
                    $user['id']
                ));
                $role_links = executeQuery('SELECT * FROM role_links_listing WHERE id = $1', array(
                    $user['role_id']
                ));
            }
            $authUser = array_merge($notify_count, $role_links, $user);
        } else if (!empty($_GET['refresh_token'])) {
            // Generate full URL using PHP_SELF server variable
            $post_url = $_server_domain_url . str_replace('r.php', 'token.php', $_SERVER['PHP_SELF']);
            $response = doPost($post_url, array(
                'grant_type' => 'refresh_token',
                'refresh_token' => $_GET['refresh_token'],
                'client_id' => OAUTH_CLIENTID,
                'client_secret' => OAUTH_CLIENT_SECRET
            ));
            echo json_encode($response);
            exit;
        } else if ($r_resource_cmd != '/settings') {
            // Generate full URL using PHP_SELF server variable
            $post_url = $_server_domain_url . str_replace('r.php', 'token.php', $_SERVER['PHP_SELF']);
            $response = doPost($post_url, array(
                'grant_type' => 'client_credentials',
                'client_id' => OAUTH_CLIENTID,
                'client_secret' => OAUTH_CLIENT_SECRET
            ));
            $role_links = executeQuery('SELECT * FROM role_links_listing WHERE id = $1', array(
                3
            ));
            $response = array_merge($response, $role_links);
            echo json_encode($response);
            exit;
        }
    }
    if ($r_resource_cmd == '/users/logout' || checkAclLinks($_SERVER['REQUEST_METHOD'], $r_resource_cmd)) {
        // /users/5/products/10 -> array('users' => 5, 'products' => 10) ...
        $r_resource_vars = array();
        if (preg_match_all('/([^\/]+)\/(\d+)/', $_url_parts_with_ext[0], $matches)) {
            for ($i = 0, $len = count($matches[0]); $i < $len; ++$i) {
                $r_resource_vars[$matches[1][$i]] = $matches[2][$i];
            }
        }
        if ($r_resource_type == 'json') {
            $is_valid_req = false;
            // Server...
            switch ($_SERVER['REQUEST_METHOD']) {
            case 'GET':
                r_get($r_resource_cmd, $r_resource_vars, $r_resource_filters);
                $is_valid_req = true;
                break;

            case 'POST':
                if ((!empty($authUser)) || (in_array($r_resource_cmd, $exception_url) && empty($authUser))) {
                    $r_post = json_decode(file_get_contents('php://input'));
                    $r_post = (array)$r_post;
                    r_post($r_resource_cmd, $r_resource_vars, $r_resource_filters, $r_post);
                    $is_valid_req = true;
                }
                break;

            case 'PUT':
                if ((!empty($authUser)) || (in_array($r_resource_cmd, $exception_url) && empty($authUser))) {
                    $r_put = json_decode(file_get_contents('php://input'));
                    $r_put = (array)$r_put;
                    r_put($r_resource_cmd, $r_resource_vars, $r_resource_filters, $r_put);
                    $is_valid_req = true;
                }
                break;

            case 'DELETE':
                if ((!empty($authUser)) || (in_array($r_resource_cmd, $exception_url) && empty($authUser))) {
                    r_delete($r_resource_cmd, $r_resource_vars, $r_resource_filters);
                    $is_valid_req = true;
                }
                break;

            default:
                header($_SERVER['SERVER_PROTOCOL'] . ' 501 Not Implemented', true, 501);
                break;
            }
        }
    }
} else {
    header($_SERVER['SERVER_PROTOCOL'] . ' 404 Not Found', true, 404);
}
if (R_DEBUG) {
    @header('X-RDebug: ' . $r_debug);
}
