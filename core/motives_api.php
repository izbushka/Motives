<?php

function motives_add($p_bug_id, $p_bugnote_id, $p_reporter_id, $p_user_id, $p_amount) {
    $t_update_table = plugin_table('bonus', 'Motives');
    $t_query = "INSERT INTO $t_update_table (
					bug_id,
					bugnote_id,
					reporter_id,
					user_id,
					timestamp,
					amount
				) VALUES (
					" . db_param() . ',
					' . db_param() . ',
					' . db_param() . ',
					' . db_param() . ',
					' . db_param() . ',
					' . db_param() . ' )';
    db_query($t_query, array(
        $p_bug_id, $p_bugnote_id, $p_reporter_id, $p_user_id, date("Y-m-d G:i:s"), $p_amount,
    ));
}

function motives_update($p_bug_id, $p_bugnote_id, $p_reporter_id, $p_user_id, $p_amount) {
    $t_update_table = plugin_table('bonus', 'Motives');
    $t_query = "DELETE FROM $t_update_table WHERE bugnote_id =" . db_param();
    db_query($t_query, array($p_bugnote_id));
    motives_add($p_bug_id, $p_bugnote_id, $p_reporter_id, $p_user_id, $p_amount);
}

function motives_delete($p_bugnote_id) {
    # Remove the bugnote
    db_param_push();
    $t_update_table = plugin_table('bonus', 'Motives');
    $t_query = "DELETE FROM $t_update_table WHERE bugnote_id=" . db_param();
    db_query($t_query, array($p_bugnote_id));
}

function motives_get($p_bugnote_id) {
    $t_update_table = plugin_table('bonus', 'Motives');
    $t_query = "SELECT * FROM $t_update_table WHERE bugnote_id=" . db_param();
    $t_result = db_query($t_query, array($p_bugnote_id));

    if (db_num_rows($t_result) < 1) {
        return null;
    }

    if ($t_row = db_fetch_array($t_result)) {
        return $t_row;
    } else {
        return null;
    }
}

function motives_get_by_bug($p_bug_id) {
    $t_update_table = plugin_table('bonus', 'Motives');
    $t_query = "SELECT * FROM $t_update_table WHERE bug_id=" . db_param();
    $t_result = db_query($t_query, array($p_bug_id));

    if (db_num_rows($t_result) < 1) {
        return null;
    }
    $t_rows = array();
    while ($t_row = db_fetch_array($t_result)) {
        $t_rows[] = $t_row;
    }
    return $t_rows;
}

/**
 * Get latest bug notes for period
 * @param int $p_project_id Project id
 * @param string $p_date_from Start date
 * @param string $p_date_to End date
 * @param int $p_user_id Filter only this user bug notes
 * @param int $p_limit Bug notes limit
 * @return array
 */
function motives_get_latest_bugnotes($p_project_id, $p_date_from, $p_date_to, $p_user_id = null, $p_bonus_user_id, $p_category_id, $p_limit = 500, $p_department) {
    $c_from = strtotime($p_date_from);
    $c_to = strtotime($p_date_to) + SECONDS_PER_DAY - 1;
    $c_user_id = empty($p_user_id) ? 0 : intval($p_user_id, 10);
    $c_bonus_user_id = empty($p_bonus_user_id) ? 0 : intval($p_bonus_user_id, 10);
    $c_category_id = empty($p_category_id) || $p_category_id == -1 ? 0 : intval($p_category_id, 10);
    $c_department = empty($p_department) || $p_department == -1 ? 0 : intval($p_department, 10);
    $c_current_user = auth_get_current_user_id();

    if ($c_to === false || $c_from === false) {
        error_parameters(array($p_date_from, $p_date_to));
        trigger_error(ERROR_GENERIC, ERROR);
    }
    $t_bug_table = db_get_table('mantis_bug_table');
    $t_bugnote_table = db_get_table('mantis_bugnote_table');
    $t_bugnote_text_table = db_get_table('mantis_bugnote_text_table');
    $t_bonus_table = plugin_table('bonus', 'Motives');
    $t_user_departments_table = plugin_table('user_departments', 'Motives');

    $all_departments = access_has_global_level(config_get('admin_site_threshold'));
    $my_departments = [];
    $my_departments = array_keys(motives_department_get());
    if (empty($my_departments)) {
        $my_departments[] = 0;
    }

    $t_query = "SELECT b.id, b.bug_id, b.reporter_id, b.date_submitted, b.last_modified,
                        bt.category_id, t.note, m.amount, m.user_id as bonus_user_id
					FROM      $t_bonus_table m
                    LEFT JOIN $t_bug_table bt ON bt.id = m.bug_id
                    LEFT JOIN $t_bugnote_table b ON b.id = m.bugnote_id
                    LEFT JOIN $t_bugnote_text_table t ON b.bugnote_text_id = t.id
                    INNER JOIN (
                        SELECT DISTINCT `user_id` 
                        FROM $t_user_departments_table WHERE `user_id` = '$c_current_user' OR (1  " .
                        (empty($all_departments) ? ' AND department_id IN (' . implode(',', $my_departments) . ')' : '') .
                        (!empty($p_department) && in_array($p_department, $my_departments) ? ' AND department_id = ' . $c_department : '') .
                    ")) d ON m.user_id = d.user_id
                    WHERE 	bt.project_id=" . db_param() . " AND
                    		b.date_submitted >= $c_from AND b.date_submitted <= $c_to AND
                    		m.bugnote_id IS NOT NULL AND
                    		LENGTH(t.note) > 0
                    " .
        (!empty($c_user_id) ? ' AND b.reporter_id = ' . $c_user_id : '') .
        (!empty($c_bonus_user_id) ? ' AND m.user_id = ' . $c_bonus_user_id : '') .
        (!empty($c_category_id) ? ' AND bt.category_id = ' . $c_category_id : '') .
        ' ORDER BY b.`id` DESC LIMIT ' . $p_limit;

    $t_bugnotes = array();

    $t_result = db_query_bound($t_query, array($p_project_id));

    while ($row = db_fetch_array($t_result)) {
        $t_bugnotes[] = $row;
    }
    return $t_bugnotes;
}

function motives_format_amount($p_amount) {
    return !empty($p_amount) && $p_amount > 0 ? '+' . $p_amount : $p_amount;
}

/**
 * Group bugnotes by bug id
 * @param array $p_bugnotes Bug notes
 * @return array
 */
function motives_group_by_bug($p_bugnotes) {
    $t_group_by_bug = array();
    foreach ($p_bugnotes as $t_bugnote) {
        $bug_id = (int)$t_bugnote['bug_id'];
        if (empty($t_group_by_bug[$bug_id])) $t_group_by_bug[$bug_id] = array();
        $t_group_by_bug[$bug_id][] = $t_bugnote;
    }
    return $t_group_by_bug;
}

/**
 * Retrieve a full list of changes to the bonus's information.
 * @param integer $p_bug_id A bug identifier.
 * @param integer $p_bugnote_id A bugnote identifier.
 * @return array/null Array of Revision rows
 */
function motives_revision_list($p_bug_id, $p_bugnote_id = 0) {
    db_param_push();
    $t_params = array($p_bug_id);
    $t_bonus_revision_table = plugin_table('bonus_revision', 'Motives');
    $t_query = "SELECT * FROM $t_bonus_revision_table WHERE bug_id=" . db_param();

    if ($p_bugnote_id > 0) {
        $t_query .= ' AND bugnote_id=' . db_param();
        $t_params[] = $p_bugnote_id;
    } else {
        $t_query .= ' AND bugnote_id=0';
    }

    $t_query .= ' ORDER BY id DESC';
    $t_result = db_query($t_query, $t_params);

    $t_revisions = array();
    while ($t_row = db_fetch_array($t_result)) {
        $t_revisions[$t_row['id']] = $t_row;
    }

    return $t_revisions;
}

/**
 * Retrieve a list of changes to a bug of the same type as the
 * given revision ID.
 * @param integer $p_rev_id A bonus revision identifier.
 * @return array|null Array of Revision rows
 */
function motives_revision_like($p_rev_id) {
    db_param_push();
    $t_bonus_revision_table = plugin_table('bonus_revision', 'Motives');
    $t_query = "SELECT bug_id, bugnote_id FROM $t_bonus_revision_table WHERE id=" . db_param();
    $t_result = db_query($t_query, array($p_rev_id));

    $t_row = db_fetch_array($t_result);

    if (!$t_row) {
        trigger_error(ERROR_BUG_REVISION_NOT_FOUND, ERROR);
    }

    $t_bug_id = $t_row['bug_id'];
    $t_bugnote_id = $t_row['bugnote_id'];

    db_param_push();
    $t_params = array($t_bug_id);
    $t_query = "SELECT * FROM $t_bonus_revision_table WHERE bug_id=" . db_param();

    if ($t_bugnote_id > 0) {
        $t_query .= ' AND bugnote_id=' . db_param();
        $t_params[] = $t_bugnote_id;
    } else {
        $t_query .= ' AND bugnote_id=0';
    }

    $t_query .= ' ORDER BY id DESC';
    $t_result = db_query($t_query, $t_params);

    $t_revisions = array();
    while ($t_row = db_fetch_array($t_result)) {
        $t_revisions[$t_row['id']] = $t_row;
    }

    return $t_revisions;
}

/**
 * Add new revision for the bonus
 * @return integer last successful insert id
 */
function motives_revision_add($p_bug_id, $p_bugnote_id, $p_reporter_id, $p_user_id, $p_amount) {
    $t_update_table = plugin_table('bonus_revision', 'Motives');
    $t_query = "INSERT INTO $t_update_table (
					bug_id,
					bugnote_id,
					reporter_id,
					user_id,
					timestamp,
					amount
				) VALUES (
					" . db_param() . ',
					' . db_param() . ',
					' . db_param() . ',
					' . db_param() . ',
					' . db_param() . ',
					' . db_param() . ' )';
    db_query($t_query, array(
        $p_bug_id, $p_bugnote_id, $p_reporter_id, $p_user_id, db_now(), $p_amount,
    ));
    return db_insert_id($t_update_table);
}

/**
 * Retrieve a count of revisions to the bonus's information.
 * @param integer $p_bug_id A bug identifier.
 * @param integer $p_bugnote_id A bugnote identifier (optional).
 * @return array|null Array of Revision rows
 */
function motives_revision_count($p_bug_id, $p_bugnote_id = 0) {
    db_param_push();
    $t_revision_table = plugin_table('bonus_revision', 'Motives');
    $t_params = array($p_bug_id);
    $t_query = "SELECT COUNT(id) FROM $t_revision_table WHERE bug_id=" . db_param();

    if ($p_bugnote_id > 0) {
        $t_query .= ' AND bugnote_id=' . db_param();
        $t_params[] = $p_bugnote_id;
    } else {
        $t_query .= ' AND bugnote_id=0';
    }

    $t_result = db_query($t_query, $t_params);

    return db_result($t_result);
}

function motives_department_add($department_name) {
    $t_update_table = plugin_table('departments', 'Motives');
    $t_query = "INSERT INTO $t_update_table(`name`, `created_at`, `updated_at`) VALUES(" . db_param() . ", now(), now())";
    db_query($t_query, array($department_name));
    return db_insert_id($t_update_table);
}

function motives_department_change($id, $department_name) {
    $t_update_table = plugin_table('departments', 'Motives');
    $t_query = "UPDATE $t_update_table SET `name` = " . db_param() . ", `updated_at` = now() WHERE id = " . db_param();
    db_query($t_query, array($department_name, $id));
    return;
}

function motives_department_get($renew = false) {
    static $departments;
    if (!empty($departments) && !$renew) {
        return $departments;
    }
    $t_departments = plugin_table('departments', 'Motives');
    $t_department_users = plugin_table('user_departments', 'Motives');
    //$t_update_table = plugin_table('departments', 'Motives');
    if (access_has_global_level(config_get('admin_site_threshold'))) {
        $t_query = "SELECT * FROM $t_departments";
    } else {
        $current_user = auth_get_current_user_id();
        $t_query = "
            SELECT d.* FROM $t_department_users du
                INNER JOIN $t_departments d ON d.id = du.department_id AND du.role IN ('chief', 'super')
            WHERE du.user_id = '$current_user'";
    }
    $t_result = db_query($t_query);

    if (db_num_rows($t_result) < 1) {
        return null;
    }
    $departments = array();
    while ($t_row = db_fetch_array($t_result)) {
        $departments[$t_row['id']] = $t_row;
    }
    return $departments;
}

function motives_department_get_selector() {
    print '<script>motives_extend_actiongroup_form();</script>';
}

function motives_get_users() {
    $t_current_user = auth_get_current_user_id();
    $t_projects = user_get_accessible_projects($t_current_user);

    # Get list of users having access level for all accessible projects
    $t_users = array();
    foreach ($t_projects as $t_project_id) {
        $t_project_users_list = project_get_all_user_rows($t_project_id);
        # Do a 'smart' merge of the project's user list, into an
        # associative array (to remove duplicates)
        foreach ($t_project_users_list as $t_id => $t_user) {
            $t_users[$t_id] = $t_user;
        }
        # Clear the array to release memory
        unset($t_project_users_list);
    }
    unset($t_projects);
    return $t_users;
}

function motives_department_set_users($department_id, $workers, $chiefs, $supers) {
    $t_update_table = plugin_table('user_departments', 'Motives');
    $t_query = "DELETE FROM $t_update_table WHERE `department_id` = " . db_param();
    db_query($t_query, [$department_id]);

    $t_query = "
        INSERT INTO $t_update_table (`user_id`, `department_id`, `role`,  `created_at`, `updated_at`)
        VALUES (" . db_param() . "," . db_param() . "," . db_param() . ", now(), now())
    ";
    foreach (array_unique(array_merge($workers, $chiefs, $supers)) as $user) {
        $role = in_array($user, $supers)
            ? 'super'
            : (in_array($user, $chiefs)
                ? 'chief'
                : 'worker'
            );
        db_query($t_query, [$user, $department_id, $role]);
    }
}

function motives_get_staff($userRoles = null, $department_ids = null) {
    //db_param_push();
    $t_revision_table = plugin_table('user_departments', 'Motives');

    $where = [1];
    if (!is_null($department_ids)) {
        if (!is_array($department_ids)) {
            $department_ids = [$department_ids];
        }
        $where[] = "`department_id` IN (" . implode(',', $department_ids) . ")";
    }

    if (!is_null($userRoles)) {
        if (!is_array($userRoles)) {
            $userRoles = [$userRoles];
        }
        array_walk($userRoles, function(&$el) { $el = "'$el'";});
        $where[] = "`role` IN (" . implode(',', $userRoles) . ")";
    }

    $t_query = "
        SELECT *, group_concat('', department_id) as `department_id`, group_concat(DISTINCT(`role`)) as `role`
        FROM $t_revision_table 
        WHERE " . implode(' AND ', $where) ." 
        GROUP BY `user_id`
    ";
    $t_result = db_query($t_query);

    if (db_num_rows($t_result) < 1) {
        return null;
    }
    $t_rows = array();
    while ($t_row = db_fetch_array($t_result)) {
        $t_rows[$t_row['user_id']] = $t_row;
    }
    return $t_rows;
}

function motives_department_get_users($department_id) {
    return motives_get_staff(null, $department_id);
}

function motives_department_get_heads() {
    static $heads;
    if (!empty($heads)) return $heads;
    $heads = motives_get_staff(['chief', 'super']);
    return $heads;
}

function motives_department_get_chiefs() {
    static $chiefs;
    if (!empty($chiefs)) return $chiefs;
    $chiefs = motives_get_staff(['chief']);
    return $chiefs;
}

function motives_department_get_supers() {
    static $supers;
    if (!empty($supers)) return $supers;
    $supers = motives_get_staff(['super']);
    return $supers;
}

function motives_category_bonus_get($p_project_id, $p_category_id) {
    $t_category_bonus_table = plugin_table('category_bonus', 'Motives');

    $t_query = "SELECT `amount` FROM $t_category_bonus_table WHERE `category_id` = ". db_param() . " AND `project_id` = " . db_param() . " LIMIT 1";
    $t_result = db_query($t_query, [$p_category_id, $p_project_id]);
    if (db_num_rows($t_result) < 1) {
        return 0;
    }
    return current(db_fetch_array($t_result));
}

function motives_category_bonus_set($p_project_id, $p_categories) {
    $t_update_table = plugin_table('category_bonus', 'Motives');
    $t_query = "DELETE FROM $t_update_table WHERE `project_id` = " . db_param();
    db_query($t_query, [$p_project_id]);

    $t_query = "
        INSERT INTO $t_update_table (`project_id`, `category_id`, `amount`,  `created_at`, `updated_at`)
        VALUES (" . db_param() . "," . db_param() . "," . db_param() . ", now(), now())
    ";
    foreach ($p_categories as $category_id => $amount) {
        db_query($t_query, [$p_project_id, $category_id, $amount]);
    }
}

function motives_is_bug_has_bonus_by_user($p_user_id, $p_bug_id) {
    $t_bonus_table = plugin_table('bonus', 'Motives');

    $t_query = "SELECT * FROM $t_bonus_table WHERE `bug_id` = " . db_param() . " AND `reporter_id` = " . db_param() . ' LIMIT 1';
    $t_result = db_query($t_query, [$p_bug_id, $p_user_id]);

    return (db_num_rows($t_result) > 0) ;
}

function motives_is_allowed_to_edit($note_timestamp) {
    $bonus_date = new DateTime("@$note_timestamp");
    if ((new DateTime())->format('d') > 15) {
        $threshold = new DateTime('first day of this month 00:00');
    } else {
        $threshold = new DateTime('first day of previous month 00:00');
    }
    return $bonus_date >= $threshold;
}

function myDebug($line) {
    $line = json_decode(json_encode($line), true);
    file_put_contents(__DIR__.'/mantis.log', date('Y-m-d H:i:s') . ": " . print_r($line, true) . "\n", FILE_APPEND);
}