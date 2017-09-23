<?php

# MantisBT - a php based bugtracking system

# MantisBT is free software: you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation, either version 2 of the License, or
# (at your option) any later version.
#
# MantisBT is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with MantisBT.  If not, see <http://www.gnu.org/licenses/>.

/**
 * @package   MantisBT
 * @link      http://www.mantisbt.org
 */
/**
 * MantisBT Core API's
 */
require_once('core.php');

require_api('bug_api.php');
require_api('bugnote_api.php');
require_api('icon_api.php');
require_once('motives_api.php');
require_once('page_api.php');

if (!access_has_global_level(plugin_config_get('view_report_threshold'))) {
    access_denied();
}

$t_filter = array();

$t_today = date('d:m:Y');
$t_day_count = plugin_config_get('day_count');
$t_from_day = date('d:m:Y', strtotime(date('Y-m-d')) - SECONDS_PER_DAY * ($t_day_count - 1));

$t_user_id = auth_get_current_user_id();

$f_note_user_id_arr = gpc_get_int_array('note_user_id', array());
$f_note_user_id = empty($f_note_user_id_arr) ? null : $f_note_user_id_arr[0];
if ($f_note_user_id == -1) $f_note_user_id = auth_get_current_user_id();

$f_bonus_user_id_arr = gpc_get_int_array('bonus_user_id', array());
$f_bonus_user_id = empty($f_bonus_user_id_arr) ? NO_USER : $f_bonus_user_id_arr[0];

$f_category_id = gpc_get_int('category_id', -1);

$f_project = gpc_get_string('project', '');
$f_page = gpc_get_string('page', '');
$f_department = gpc_get_int('department_id', '');

if (is_blank($f_project)) {
    $f_project_id = gpc_get_int('project_id', -1);
} else {
    $f_project_id = project_get_id_by_name($f_project);
    if ($f_project_id === 0) {
        trigger_error(ERROR_PROJECT_NOT_FOUND, ERROR);
    }
}


if ($f_project_id == -1) {
    $t_project_id = helper_get_current_project();
} else {
    $t_project_id = $f_project_id;
}

if (ALL_PROJECTS == $t_project_id) {
    $t_topprojects = $t_project_ids = user_get_accessible_projects($t_user_id);
    foreach ($t_topprojects as $t_project) {
        $t_project_ids = array_merge($t_project_ids, user_get_all_accessible_subprojects($t_user_id, $t_project));
    }

    $t_project_ids_to_check = array_unique($t_project_ids);
    $t_project_ids = array();

    foreach ($t_project_ids_to_check as $t_project_id_item) {
        $t_changelog_view_access_level = config_get('view_changelog_threshold', null, null, $t_project_id_item);
        if (access_has_project_level($t_changelog_view_access_level, $t_project_id_item)) {
            $t_project_ids[] = $t_project_id_item;
        }
    }
} else {
    //access_ensure_project_level( config_get( 'view_changelog_threshold' ), $t_project_id );
    $t_project_ids = user_get_all_accessible_subprojects($t_user_id, $t_project_id);
    array_unshift($t_project_ids, $t_project_id);
}

layout_page_header(plugin_lang_get('title'));
layout_page_begin();

$t_project_index = 0;

$t_project_ids_size = count($t_project_ids);
echo '<br/>';

$t_stats_from_def = $t_from_day;
$t_stats_from_def_ar = explode(":", $t_stats_from_def);
$t_stats_from_def_d = $t_stats_from_def_ar[0];
$t_stats_from_def_m = $t_stats_from_def_ar[1];
$t_stats_from_def_y = $t_stats_from_def_ar[2];

$t_stats_from_d = gpc_get_int('start_day', $t_stats_from_def_d);
$t_stats_from_m = gpc_get_int('start_month', $t_stats_from_def_m);
$t_stats_from_y = gpc_get_int('start_year', $t_stats_from_def_y);

$t_stats_to_def = $t_today;
$t_stats_to_def_ar = explode(":", $t_stats_to_def);
$t_stats_to_def_d = $t_stats_to_def_ar[0];
$t_stats_to_def_m = $t_stats_to_def_ar[1];
$t_stats_to_def_y = $t_stats_to_def_ar[2];

$t_stats_to_d = gpc_get_int('end_day', $t_stats_to_def_d);
$t_stats_to_m = gpc_get_int('end_month', $t_stats_to_def_m);
$t_stats_to_y = gpc_get_int('end_year', $t_stats_to_def_y);

$t_from = "$t_stats_from_y-$t_stats_from_m-$t_stats_from_d";
$t_to = "$t_stats_to_y-$t_stats_to_m-$t_stats_to_d";

$datetime_from = new DateTime($t_from);
$datetime_to = new DateTime($t_to);
$datetime_month = new DateTime($datetime_to->format('Y-m-01'));

$t_show_status_legend = plugin_config_get('show_status_legend');
$t_show_avatar = plugin_config_get('show_avatar', config_get('show_avatar', OFF));
$t_update_bug_threshold = config_get('update_bug_threshold');
$t_icon_path = config_get('icon_path');
$t_show_priority_text = config_get('show_priority_text');
$t_use_javascript = config_get('use_javascript', ON);

$data = get_page_data($t_project_ids, $t_from, $t_to, $f_note_user_id, $f_bonus_user_id, $f_category_id, $f_department);
extract($data);

?>
    <div class="col-md-12 col-xs-12">
        <form method="get" name="activity_page_form"
              action="<?php echo string_attribute(plugin_page('activity_page')) ?>">
            <input type="hidden" name="page" value="<?php echo htmlspecialchars($f_page); ?>"/>
            <input type="hidden" id="activity_project_id" name="project_id"
                   value="<?php echo htmlspecialchars($f_project_id); ?>"/>

            <div class="filter-box">
                <div class="widget-box widget-color-blue2">
                    <div class="widget-header widget-header-small">
                        <h4 class="widget-title lighter">
                            <i class="ace-icon fa fa-filter"></i>
                            <?php
                            echo lang_get('filters');
                            if ($t_project_size > 1) {
                                $t_total_issues_html = '<span title="' . plugin_lang_get('total_issues') . '">' . $t_total_issues . '</span>';
                                $t_total_notes_html = '<span title="' . plugin_lang_get('total_notes') . '">' . $t_total_notes . '</span>';
                                echo '<span class="badge">', $t_total_issues_html, '/', $t_total_notes_html, '</span>';
                            }
                            ?>
                            <div class="widget-toolbar">
                                <a href="<?php echo string_attribute(plugin_page('departments_page')) ?>">
                                    <i class="1 ace-icon fa bigger-125 fa-cogs"></i>
                                </a>
                            </div>
                        </h4>
                    </div>
                    <div class="widget-body">
                        <div class="widget-main no-padding">
                            <table class="table" border="0" cellspacing="0">
                                <tr>
                                    <td class="bold">
                                        <?php
                                        $t_filter['do_filter_by_date'] = 'on';
                                        $t_filter['start_day'] = $t_stats_from_d;
                                        $t_filter['start_month'] = $t_stats_from_m;
                                        $t_filter['start_year'] = $t_stats_from_y;
                                        $t_filter['end_day'] = $t_stats_to_d;
                                        $t_filter['end_month'] = $t_stats_to_m;
                                        $t_filter['end_year'] = $t_stats_to_y;
                                        print_filter_do_filter_by_date(true, $t_filter);

                                        echo plugin_lang_get('department') . ':&nbsp;';
                                        $t_filter[FILTER_PROPERTY_DEPARTMENT] = $f_department;
                                        print_filter_department();
                                        ?>
                                    </td>
                                    <td class="bold">
                                        <?php
                                        echo plugin_lang_get('reported_user') . ':&nbsp;';
                                        $t_filter[FILTER_PROPERTY_NOTE_USER_ID] = $f_note_user_id_arr;
                                        print_filter_note_user_id2();
                                        ?>
                                    </td>
                                    <td class="bold">
                                        <?php
                                        echo plugin_lang_get('bonus_user') . ':&nbsp;';
                                        $t_filter[FILTER_PROPERTY_NOTE_USER_ID] = $f_bonus_user_id_arr;
                                        print_filter_note_user_id2('bonus_user_id');
                                        ?>
                                    </td>
                                    <td class="bold">
                                        <?php
                                        echo lang_get('category') . ':&nbsp;';
                                        echo '<select ' . helper_get_tab_index() . ' id="category_id" name="category_id" class="input-sm">';
                                        echo '<option value="-1" ' . check_selected($f_category_id, -1) . '>[' . lang_get('none') . ']</option>';
                                        print_category_option_list($f_category_id, $t_project_id);
                                        echo '</select>';
                                        ?>
                                    </td>
                                </tr>
                            </table>
                        </div>
                        <div class="widget-toolbox center clearfix">
                            <input type="submit" class="btn btn-xs btn-primary btn-white btn-round"
                                   value="<?php echo plugin_lang_get('get_info_button') ?>"
                            />
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
    <div class="col-md-12 col-xs-12">
        <div class="space-10"></div>
        <div class="widget-box widget-color-blue2">
            <div class="widget-header widget-header-small">
                <h4 class="widget-title"><i
                        class="ace-icon fa fa-columns"></i><?php echo plugin_lang_get('bonuses_fines') ?></h4>
                <div class="widget-toolbar">
                    <a id="filter-toggle" data-action="collapse" href="#">
                        <i class="1 ace-icon fa bigger-125 fa-chevron-up"></i>
                    </a>
                </div>
            </div>
            <div class="widget-body">
                <div class="widget-main">
                    <?php
                    // Get totals tables
                    echo motives_get_totals_html($data, $datetime_from, $datetime_to, $datetime_month);
                    ?>
                </div>
            </div>
        </div>
    </div>
<?php

if (!empty($f_note_user_id) || !empty($f_bonus_user_id) || !empty($f_department)) {
    echo motives_get_related_notes_html($data);
}

layout_page_end();
