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

$css = '<style>' . file_get_contents(__DIR__ . '/../files/motives.css') . '<style>';

plugin_push_current('Motives');

$date_from = new DateTime('yesterday');
$date_to = new DateTime();
$date_from = new DateTime('2017-09-15');
$date_to = new DateTime('2017-09-15');

$chiefs = motives_department_get_chiefs();
foreach ($chiefs as $id => $chief) {
    $ok = auth_attempt_script_login(user_get_name($id));
    $t_user_id = auth_get_current_user_id();
    $is_admin = access_has_global_level(config_get('admin_site_threshold'));

    // get user projects
    $t_topprojects = $t_project_ids = user_get_accessible_projects($t_user_id);
    foreach ($t_topprojects as $t_project) {
        $t_project_ids = array_merge($t_project_ids, user_get_all_accessible_subprojects($t_user_id, $t_project));
    }

    $departments = motives_department_get();
    foreach (explode(',', $chief['department_id']) as $f_department) {
        // get report data
        $data = get_page_data($t_project_ids, $date_from->format('Y-m-d'), $date_to->format('Y-m-d'), 0, 0, 0, $f_department);
        $body = [];
        $body[] = '<html><head>' . $css . '</head><body>';
        $body[] = '<h3>' . $departments[$f_department]['name'] . '</h3>';
        $body[] = motives_get_totals_html($data, $date_from, $date_to, $date_from);
        $body[] = motives_get_related_notes_html($data, true);
        $subject = plugin_lang_get('title') . ': ' . $departments[$f_department]['name'] . ' ' . $date_from->format('Y-m-d');
        motives_email_send($id, $subject, implode('', $body));
    }
}
