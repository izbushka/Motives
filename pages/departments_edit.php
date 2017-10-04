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

if (!access_has_global_level(plugin_config_get('update_threshold'))) {
    access_denied();
}
$is_admin = access_has_global_level(config_get('admin_site_threshold'));
$chiefs = motives_department_get_chiefs();
$supers = motives_department_get_supers();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $department_name = gpc_get_string('department_name', '');
    $department_id = gpc_get_int('department_id');
    $department_workers = gpc_get_int_array('workers', []);
    $department_chiefs = gpc_get_int_array('chiefs', []);
    $department_supers = gpc_get_int_array('supers', []);


    if ($is_admin) { // only admin can modify departments
        if ($department_id > 0) {
            motives_department_change($department_id, $department_name);
        } else {
            $department_id = motives_department_add($department_name);
        }
    } else { // don't allow managers to change chiefs. Replacing chiefs by DB data here
        $department_workers = array_filter($department_workers, function ($worker) use ($chiefs, $supers) { return (!isset($chiefs[$worker]) && !isset($supers[$worker])); });
        $department_chiefs = [];
        foreach (motives_department_get_users($department_id) as $user_id => $user) {
            if ($user['role'] == 'chief') {
                $department_chiefs[] = $user_id;
            } elseif ($user['role'] == 'super') {
                $department_supers[] = $user_id;
            }
        }
    }

    motives_department_set_users($department_id, $department_workers, $department_chiefs, $department_supers);
    print_successful_redirect(plugin_page('departments_page', true));
} else {
    layout_page_header(plugin_lang_get('title'));
    layout_page_begin();

    $department_name = '';
    $departments = [];
    if (gpc_isset('id')) {
        $department_id = gpc_get_int('id');
        $departments = motives_department_get();
        $department_name = $departments[$department_id]['name'];
        $department_users = motives_department_get_users($department_id);
    } else {
        $department_id = 0;
    }
    if (!$is_admin && empty($departments[$department_id])) {
        access_denied();
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
                        <i class="ace-icon fa fa-edit"></i>
                        <?php echo plugin_lang_get('department') ?>
                    </h4>
                </div>
                <div class="widget-body">
                    <div class="widget-main motives-editor-form">

                        <form method="POST" name="activity_page_form"
                              action="<?php echo string_attribute(plugin_page('departments_edit')) ?>">
                            <?php if($is_admin) { ?>
                                <h3><?php echo plugin_lang_get('department_name') ?></h3>
                                <input name="department_name" type="text" value="<?php echo $department_name?>">
                                <input type="submit" value="<?php echo plugin_lang_get('save') ?>">
                            <?php } ?>
                            <input name="department_id" type="hidden" id="department_id" value="<?php echo $department_id ?>">
                            <h3><?php echo plugin_lang_get('department_members') ?></h3>
                            <?php foreach (motives_get_users() as $user) {
                                $disabled = !$is_admin && (!empty($chiefs[$user['id']]) || !empty($supers[$user['id']])) ? 'disabled="disabled"' : '';
                                $checked_worker = !empty($department_users[$user['id']]) ? 'checked' : '';
                                $checked_chief = $checked_worker && $department_users[$user['id']]['role'] == 'chief' ? 'checked' : '';
                                $checked_super = $checked_worker && $department_users[$user['id']]['role'] == 'super' ? 'checked' : '';
                                ?>
                                <label title="<?php echo $user['realname'] ?>">
                                    <input <?php echo $disabled ?> type="checkbox" name="workers[]" <?php echo $checked_worker ?> value="<?php echo $user['id'] ?>">
                                    <?php echo $user['username'] ?>
                                    <?php if ($is_admin) { ?>
                                    <span><input type="checkbox" name="supers[]" <?php echo $checked_super ?> value="<?php echo $user['id'] ?>"> super</span>
                                    <span><input type="checkbox" name="chiefs[]" <?php echo $checked_chief ?> value="<?php echo $user['id'] ?>"> chief</span>
                                    <?php } ?>
                                </label>
                            <?php } ?>
                            <br><input type="submit" value="<?php echo plugin_lang_get('save') ?>">
                            <input type="button" value="<?php echo plugin_lang_get('cancel') ?>" onclick="window.location.href='<?php echo string_attribute(plugin_page('departments_page')) ?>'">
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>


<?php }

layout_page_end();
