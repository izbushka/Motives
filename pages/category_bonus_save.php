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
$f_project_id = 0;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $f_categories = gpc_get_int_array('categories');
    $f_project_id = gpc_get_int('project_id');
    $is_local_admin = access_has_project_level(config_get('admin_site_threshold'), $f_project_id);
    if ($is_local_admin) {
        $categories = [];
        foreach ($f_categories as $category_id => $amount) {
            if (is_numeric($category_id) && $amount != 0) {
                $categories[$category_id] = $amount;
            }
        }
        motives_category_bonus_set($f_project_id, $categories);
    }
}
$t_redirect_url = '/manage_proj_edit_page.php?project_id=' . $f_project_id;
header('Location: ' . $t_redirect_url, 302);

