<?php
# Make sure this script doesn't run via the webserver
if (php_sapi_name() != 'cli') {
    echo "It is not allowed to run this script through the webserver.\n";
    exit(1);
}
# This page sends an E-mail if a due date is getting near
# includes all due_dates not met
require_once(dirname(dirname(dirname(dirname(__FILE__)))) . DIRECTORY_SEPARATOR . 'core.php');
$t_core_path = config_get('core_path');

require_once($t_core_path . 'bug_api.php');
require_once($t_core_path . 'email_api.php');
require_once($t_core_path . 'bugnote_api.php');
require_once($t_core_path . 'category_api.php');
require_once($t_core_path . 'helper_api.php');

require_once(__DIR__ . '/../core/motives_api.php');
require_once(__DIR__ . '/../core/page_api.php');

$css = '<style>' .  file_get_contents(__DIR__ . '/../files/motives.css') . '</style>';

plugin_push_current('Motives');

$date_from = new DateTime('yesterday');
$date_to = new DateTime('yesterday');

$users = motives_get_staff();

global $g_user_accessible_projects_cache;

foreach ($users as $id => $user) {
    $ok = auth_attempt_script_login(user_get_name($id));
    $t_user_id = auth_get_current_user_id();
    $is_admin = access_has_global_level(config_get('admin_site_threshold'));
    $userRoles = explode(',', $user['role']);

    // get user projects
    $t_topprojects = $t_project_ids = user_get_accessible_projects($t_user_id);

    foreach ($t_topprojects as $t_project) {
        $t_project_ids = array_merge($t_project_ids, user_get_all_accessible_subprojects($t_user_id, $t_project));
    }

    $userDepartments = '0';
    $bonus_user = $t_user_id;

    if (in_array('chief', $userRoles) || in_array('super', $userRoles)) {
        $userDepartments = $user['department_id'];
        $bonus_user = 0;
    }

    $departments = motives_department_get(true);
    foreach (explode(',', $userDepartments) as $f_department) {
        // get report data
        $data = get_page_data($t_project_ids, $date_from->format('Y-m-d'), $date_to->format('Y-m-d'), 0, $bonus_user, 0, $f_department);
        // skip worker with no data
        if($bonus_user > 0 && empty($data['t_user_bonuses_total'][$bonus_user]['month']) && empty($data['t_user_fines_total'][$bonus_user]['month'])) {
            continue;
        }
        $body = [];
        $body[] = '<html><head>' . $css .'</head><body>';
        $body[] = '<h3>' . $departments[$f_department]['name'] . '</h3>';
        $body[] = motives_get_totals_html($data, $date_from, $date_to, new DateTime($date_to->format('Y-m-01')));
        $body[] = motives_get_related_notes_html($data, true);

        $subject = $bonus_user > 0 //worker
            ? plugin_lang_get('title') . ': ' . user_get_realname($t_user_id) . ', ' . $date_from->format('Y-m-d')
            : plugin_lang_get('title') . ': ' . $departments[$f_department]['name'] . ' ' . $date_from->format('Y-m-d');

        motives_email_send($t_user_id, $subject, implode('', $body));

    }
    // logout and reset projects
    $g_user_accessible_projects_cache = null;
    user_clear_cache( $t_user_id );
    current_user_set( null );
}
