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

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $department_name = gpc_get_string('department_name');
    $department_id = gpc_get_int('department_id');
    $department_workers = gpc_get_int_array('workers', []);
    $department_chiefs = gpc_get_int_array('chiefs', []);
    if ($department_id > 0) {
        motives_department_change($department_id, $department_name);
    } else {
        $department_id = motives_department_add($department_name);
    }

    motives_department_set_users($department_id, $department_workers, $department_chiefs);
    print_successful_redirect(plugin_page('departments_page', true));
} else {
    layout_page_header(plugin_lang_get('title'));
    layout_page_begin();

    $department_name = '';
    if (gpc_isset('id')) {
        $department_id = gpc_get_int('id');
        $departments = motives_department_get();
        $department_name = $departments[$department_id]['name'];
        $department_users = motives_department_get_users($department_id);
    } else {
        $department_id = 0;
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
                        <?php echo plugin_lang_get('department_name') ?>
                    </h4>
                </div>
                <div class="widget-body">
                    <div class="widget-main motives-editor-form">

                        <form method="POST" name="activity_page_form"
                              action="<?php echo string_attribute(plugin_page('departments_edit')) ?>">
                            <input name="department_id" type="hidden" id="department_id" value="<?php echo $department_id ?>"></td>
                            Name:
                            <input name="department_name" type="text" value="<?php echo $department_name?>">
                            <input type="submit" value="<?php echo plugin_lang_get('save') ?>">
                            <hr>
                            <h3><?php echo plugin_lang_get('department_members') ?></h3>
                            <?php foreach (motives_get_users() as $user) {
                                $checked_worker = !empty($department_users[$user['id']]) ? 'checked' : '';
                                $checked_chief = $checked_worker && $department_users[$user['id']]['role'] == 'chief' ? 'checked' : '';
                                ?>
                                <label title="<?php echo $user['realname'] ?>">
                                    <input type="checkbox" name="workers[]" <?php echo $checked_worker ?> value="<?php echo $user['id'] ?>">
                                    <?php echo $user['username'] ?>
                                    <span><input type="checkbox" name="chiefs[]" <?php echo $checked_chief ?> value="<?php echo $user['id'] ?>"> chief</span>
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
