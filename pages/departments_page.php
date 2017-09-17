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

if (!access_has_global_level(plugin_config_get('view_report_threshold'))) {
    access_denied();
}

$t_filter = array();

$t_today = date('d:m:Y');
$t_day_count = plugin_config_get('day_count');
$t_from_day = date('d:m:Y', strtotime(date('Y-m-d')) - SECONDS_PER_DAY * ($t_day_count - 1));

function format_date_submitted($p_date_submitted) {
    global $t_today;
    $c_date = date('d:m:Y', $p_date_submitted);
    $c_format = $t_today == $c_date ? 'H:i:s' : 'd.m.y';
    return date($c_format, $p_date_submitted);
}

/**
 *  print note reporter field
 */
function print_filter_note_user_id2($p_name = FILTER_PROPERTY_NOTE_USER_ID) {
    global $t_select_modifier, $t_filter;
    ?>
    <!-- BUGNOTE REPORTER -->
    <select <?php echo $t_select_modifier; ?> name="<?php echo $p_name; ?>[]">
        <option
            value="<?php echo META_FILTER_ANY ?>" <?php check_selected($t_filter[FILTER_PROPERTY_NOTE_USER_ID], META_FILTER_ANY); ?>>
            [<?php echo lang_get('none') ?>]
        </option>
        <?php if (access_has_project_level(config_get('view_handler_threshold'))) { ?>
            <?php
            if (access_has_project_level(config_get('handle_bug_threshold'))) {
                echo '<option value="' . META_FILTER_MYSELF . '" ';
                check_selected($t_filter[FILTER_PROPERTY_NOTE_USER_ID], META_FILTER_MYSELF);
                echo '>[' . lang_get('myself') . ']</option>';
            }

            print_note_option_list($t_filter[FILTER_PROPERTY_NOTE_USER_ID]);
        }
        ?>
    </select>
    <?php
}

function string_get_bugnote_view_link2($p_bug_id, $p_bugnote_id, $p_user_id = null, $p_detail_info = true, $p_fqdn = false) {
    $t_bug_id = (int)$p_bug_id;

    if (bug_exists($t_bug_id) && bugnote_exists($p_bugnote_id)) {
        $t_link = '<a href="';
        if ($p_fqdn) {
            $t_link .= config_get_global('path');
        } else {
            $t_link .= config_get_global('short_path');
        }

        $t_link .= string_get_bugnote_view_url($p_bug_id, $p_bugnote_id, $p_user_id) . '"';
        if ($p_detail_info) {
            $t_reporter = string_attribute(user_get_name(bugnote_get_field($p_bugnote_id, 'reporter_id')));
            $t_update_date = string_attribute(date(config_get('normal_date_format'), (bugnote_get_field($p_bugnote_id, 'last_modified'))));
            $t_link .= ' title="' . bug_format_id($t_bug_id) . ': [' . $t_update_date . '] ' . $t_reporter . '"';
        }

        $t_link .= '>' . bugnote_format_id($p_bugnote_id) . '</a>';
    } else {
        $t_link = bugnote_format_id($p_bugnote_id);
    }

    return $t_link;
}

/**
 * @param $p_group BugnoteData[]
 * @return bool
 */
function is_empty_group($p_group) {
    foreach ($p_group as $t_bugnote) {
        $t_note = trim($t_bugnote['note']);
        if (!empty($t_note)) return false;
    }
    return true;
}

function motives_count_bugnotes($p_group) {
    $result = 0;
    foreach ($p_group as $t_item) {
        $result += count($t_item);
    }
    return $result;
}

$t_user_id = auth_get_current_user_id();

$f_note_user_id_arr = gpc_get_int_array('note_user_id', array());
$f_note_user_id = empty($f_note_user_id_arr) ? null : $f_note_user_id_arr[0];
if ($f_note_user_id == -1) $f_note_user_id = auth_get_current_user_id();

$f_bonus_user_id_arr = gpc_get_int_array('bonus_user_id', array());
$f_bonus_user_id = empty($f_bonus_user_id_arr) ? NO_USER : $f_bonus_user_id_arr[0];

$f_category_id = gpc_get_int('category_id', -1);

$f_project = gpc_get_string('project', '');
$f_page = gpc_get_string('page', '');

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


$t_show_status_legend = plugin_config_get('show_status_legend');
$t_show_avatar = plugin_config_get('show_avatar', config_get('show_avatar', OFF));
$t_limit_bug_notes = (int)plugin_config_get('limit_bug_notes', 1000);
$t_update_bug_threshold = config_get('update_bug_threshold');
$t_icon_path = config_get('icon_path');
$t_show_priority_text = config_get('show_priority_text');
$t_use_javascript = config_get('use_javascript', ON);


$t_project_bugs = array();
$t_category_bugs = array();
$t_project_size = 0;
$t_total_issues = 0;
$t_total_notes = 0;
$t_total_bonuses = 0;
$t_total_fines = 0;
$t_user_bonuses = array();
$t_user_fines = array();
foreach ($t_project_ids as $t_project_id_item) {
    $t_bug_notes = motives_get_latest_bugnotes($t_project_id_item, $t_from, $t_to, $f_note_user_id, $f_bonus_user_id, $f_category_id, $t_limit_bug_notes);
    $t_bug_note_size = count($t_bug_notes);
    if ($t_bug_note_size == 0) continue;

    $t_bugs = motives_group_by_bug($t_bug_notes);
    $t_bugs_size = count($t_bugs);

    $t_project_bugs[$t_project_id_item]['bugs'] = $t_bugs;
    $t_project_bugs[$t_project_id_item]['note_size'] = $t_bug_note_size;
    $t_project_bugs[$t_project_id_item]['bugs_size'] = $t_bugs_size;
    $t_total_notes += $t_bug_note_size;
    $t_total_issues += $t_bugs_size;
    $t_project_size++;

    foreach ($t_bug_notes as $t_bug_item) {
        $t_amount = (int)$t_bug_item['amount'];
        $t_bug_category_id = $t_bug_item['category_id'];
        if ($t_amount > 0) {
            $t_total_bonuses += $t_amount;
            if (!isset($t_user_bonuses[$t_bug_item['bonus_user_id']][$t_project_id_item][$t_bug_category_id]))
                $t_user_bonuses[$t_bug_item['bonus_user_id']][$t_project_id_item][$t_bug_category_id] = 0;
            $t_user_bonuses[$t_bug_item['bonus_user_id']][$t_project_id_item][$t_bug_category_id] += $t_amount;
        } else {
            $t_total_fines += $t_amount;
            if (!isset($t_user_fines[$t_bug_item['bonus_user_id']][$t_project_id_item][$t_bug_category_id]))
                $t_user_fines[$t_bug_item['bonus_user_id']][$t_project_id_item][$t_bug_category_id] = 0;
            $t_user_fines[$t_bug_item['bonus_user_id']][$t_project_id_item][$t_bug_category_id] += $t_amount;
        }
        $t_category_bugs[$t_project_id_item][$t_bug_category_id][$t_bug_item['bug_id']][] = $t_bug_item;
    }
}

?>
    <div class="col-md-12 col-xs-12">
        <input type="hidden" name="page" value="<?php echo htmlspecialchars($f_page); ?>"/>
        <input type="hidden" id="activity_project_id" name="project_id"
               value="<?php echo htmlspecialchars($f_project_id); ?>"/>

        <div class="filter-box">
            <div class="widget-box widget-color-blue2">
                <div class="widget-header widget-header-small">
                    <h4 class="widget-title lighter">
                        <i class="ace-icon fa fa-filter"></i>
                        <?php
                        echo plugin_lang_get('departments');
                        ?>
                    </h4>
                </div>
                <div class="widget-body">
                    <div class="widget-main no-padding">
                        <table class="table" border="0" cellspacing="0">
                            <tr>
                                <th>ID</th>
                                <th><?php echo plugin_lang_get('department') ?></th>
                                <th><?php echo plugin_lang_get('created') ?></th>
                                <th><?php echo plugin_lang_get('updated') ?></th>
                                <th><?php echo plugin_lang_get('actions') ?></th>
                            </tr>
                            <?php foreach(motives_department_get() as $department) { ?>
                                <tr>
                                    <td><?php echo $department['id'] ?></td>
                                    <td><?php echo $department['name'] ?></td>
                                    <td><?php echo $department['created_at'] ?></td>
                                    <td><?php echo $department['updated_at'] ?></td>
                                    <td>
                                        <a href="<?php echo string_attribute(plugin_page('departments_edit')) ?>&id=<?php echo $department['id'] ?>">
                                            <?php echo plugin_lang_get('edit') ?>
                                        </a>
                                    </td>
                                </tr>
                            <?php } ?>
                        </table>
                        <center>
                            <a href="<?php echo string_attribute(plugin_page('departments_edit')) ?>">
                                <?php echo plugin_lang_get('add_new_department') ?>
                            </a>
                        </center>
                    </div>
                </div>
            </div>
        </div>
    </div>

<?php
layout_page_end();
