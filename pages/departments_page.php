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

layout_page_header(plugin_lang_get('title'));
layout_page_begin();

?>
    <div class="col-md-12 col-xs-12">
        <div class="filter-box">
            <div class="widget-box widget-color-blue2">
                <div class="widget-header widget-header-small">
                    <h4 class="widget-title lighter">
                        <i class="ace-icon fa fa-filter"></i>
                        <?php
                        echo plugin_lang_get('departments');
                        ?>
                        <div class="widget-toolbar">
                            <a href="<?php echo string_attribute(plugin_page('departments_edit')) ?>">
                                <i class="1 ace-icon fa bigger-125 fa-plus"></i>
                            </a>
                        </div>
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
                    </div>
                </div>
            </div>
        </div>
    </div>

<?php
layout_page_end();
