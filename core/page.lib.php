<?php

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

/**
 *  print note reporter field
 */
function print_filter_department($p_name = FILTER_PROPERTY_DEPARTMENT) {
    global $t_select_modifier, $t_filter;
    $departments = motives_department_get();
    ?>
    <!-- BUGNOTE REPORTER -->
    <select <?php echo $t_select_modifier; ?> name="<?php echo $p_name; ?>">
        <option
            value="<?php echo META_FILTER_ANY ?>" <?php check_selected($t_filter[FILTER_PROPERTY_DEPARTMENT], META_FILTER_ANY); ?>>
            [<?php echo lang_get('none') ?>]
        </option>
        <?php if (access_has_project_level(config_get('view_handler_threshold'))) {
            foreach ($departments as $department) { ?>
                <option <?php check_selected($t_filter[FILTER_PROPERTY_DEPARTMENT], intval($department['id'])); ?>
                    value="<?php echo $department['id'] ?>"><?php echo $department['name'] ?></option>
            <?php }
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


function get_page_data($t_project_ids, $t_from, $t_to, $f_note_user_id, $f_bonus_user_id, $f_category_id, $f_department) {

    $t_limit_bug_notes = (int)plugin_config_get('limit_bug_notes', 1000);

    $datetime_from = new DateTime($t_from);
    $datetime_to = new DateTime($t_to);
    $datetime_month = new DateTime($datetime_to->format('Y-m-01'));

    $real_from = $datetime_month < $datetime_from ? $datetime_month : $datetime_from;

    $t_user_bonuses = array();
    $t_user_fines = array();
    $t_project_bugs = array();
    $t_category_bugs = array();
    $user_bonus_totals = array();
    $user_fine_totals = array();

    $t_total = []; // issues and notes counter

    $chiefs = motives_department_get_chiefs();
    $current_user_id = auth_get_current_user_id();
    $is_admin = access_has_global_level(config_get('admin_site_threshold'));

    foreach ($t_project_ids as $project_id) {
        $t_bug_notes = motives_get_latest_bugnotes($project_id, $real_from->format('Y-m-d'), $t_to, $f_note_user_id, $f_bonus_user_id, $f_category_id, $t_limit_bug_notes, $f_department);
        $t_bug_note_size = count($t_bug_notes);
        if ($t_bug_note_size == 0) continue;

        foreach ($t_bug_notes as $t_bug_note) {
            $t_amount = (int)$t_bug_note['amount'];
            $t_bug_category_id = $t_bug_note['category_id'];
            $user_id = $t_bug_note['bonus_user_id'];
            $bug_id = (int)$t_bug_note['bug_id'];
            // chiefs are not allowed to view other chiefs
            if (!$is_admin && $user_id != $current_user_id && !empty($chiefs[$user_id])) {
                continue;
            }
            $is_bonus = !$is_fine = $t_amount < 0;

            if (!isset($user_bonus_totals[$user_id])) {
                $user_bonus_totals[$user_id] = $user_fine_totals[$user_id] = ['period' => 0, 'month' => 0];
            }

            // count last month totals
            if ($t_bug_note['date_submitted'] >= $datetime_month->getTimestamp()) {
                if ($is_bonus) {
                    $user_bonus_totals[$user_id]['month'] += $t_amount;
                } else {
                    $user_fine_totals[$user_id]['month'] += $t_amount;
                }
            }
            // count period totals
            if ($t_bug_note['date_submitted'] >= $datetime_from->getTimestamp()) {
                if (!isset($t_user_bonuses[$user_id][$project_id][$t_bug_category_id])) {
                    $t_user_bonuses[$user_id][$project_id][$t_bug_category_id] = 0;
                    $t_user_fines[$user_id][$project_id][$t_bug_category_id] = 0;
                }
                if ($is_bonus) {
                    $user_bonus_totals[$user_id]['period'] += $t_amount;
                    { // bonuses by project
                        $t_user_bonuses[$user_id][$project_id][$t_bug_category_id] += $t_amount;
                    }
                } else {
                    $user_fine_totals[$user_id]['period'] += $t_amount;
                    { // fines by project
                        $t_user_fines[$user_id][$project_id][$t_bug_category_id] += $t_amount;
                    }
                }
                $t_category_bugs[$project_id][$t_bug_category_id][$bug_id][] = $t_bug_note;

                $t_project_bugs[$project_id][$bug_id][] = $t_bug_note;
                $t_total[] = $bug_id; // столько багов, сколько ноутов в нем
            }

        }
    }
    $t_total_bonuses = array_sum(array_column($user_bonus_totals, 'period'));
    $t_total_fines = array_sum(array_column($user_fine_totals, 'period'));

    $res = [
        't_project_bugs'       => $t_project_bugs,
        't_category_bugs'      => $t_category_bugs,
        't_total_notes'        => sizeof($t_total),
        't_total_issues'       => sizeof(array_unique($t_total)),
        't_total_bonuses'      => $t_total_bonuses,
        't_total_fines'        => $t_total_fines,
        't_user_bonuses'       => $t_user_bonuses,
        't_user_fines'         => $t_user_fines,
        't_user_bonuses_total' => $user_bonus_totals,
        't_user_fines_total'   => $user_fine_totals,
    ];
    return $res;
}