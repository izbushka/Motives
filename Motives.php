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
 * Motives plugin
 * @package    MantisPlugin
 * @subpackage MantisPlugin
 * @link       http://www.mantisbt.org
 */

/**
 * requires MantisPlugin.class.php
 */
define('FILTER_PROPERTY_DEPARTMENT', 'department_id');
define('FILTER_WORKERS_ONLY', 'workers_only');
require_once(config_get('class_path') . 'MantisPlugin.class.php');

/**
 * Motives Class
 */
class MotivesPlugin extends MantisPlugin {
    const BASE_NAME = 'Motives';

    /**
     *  A method that populates the plugin information and minimum requirements.
     */
    function register() {
        $this->name = plugin_lang_get('title');
        $this->description = plugin_lang_get('description');
        $this->page = 'config';

        $this->version = '2.6';
        $this->requires = array('MantisCore' => '2.0.0',);

        $this->author = 'Oleg Muraviov';
        $this->contact = 'mirage@izbushka.kiev.ua';
        $this->url = 'https://github.com/izbushka/Motives.git';
    }

    /**
     * Default plugin configuration.
     */
    function hooks() {
        $hooks = array('EVENT_MENU_MAIN'           => 'menu',
                       'EVENT_VIEW_BUGNOTE'        => 'view_note',
                       'EVENT_VIEW_BUGNOTES_START' => 'view_note_start',
                       'EVENT_LAYOUT_RESOURCES'    => 'resources',
                       'EVENT_BUGNOTE_ADD_FORM'    => 'add_note_form',
                       'EVENT_BUGNOTE_ADD'         => 'add_note',
                       'EVENT_BUGNOTE_EDIT_FORM'   => 'edit_note_form',
                       'EVENT_BUGNOTE_EDIT'        => 'edit_note',
                       'EVENT_BUGNOTE_DELETED'     => 'delete_note',
                       'EVENT_UPDATE_BUG'          => 'on_issue_resolve',
                       'EVENT_MANAGE_PROJECT_PAGE' => 'edit_project_form',
                       'EVENT_LAYOUT_CONTENT_END'  => 'update_actiongroup_form',
        );

        return $hooks;
    }

    /**
     * Show any available motives with their associated bugnotes.
     * @param string $p_event Event name
     * @param int $p_bug_id Bug ID
     * @param int $p_bugnote_id Bugnote ID
     * @param boolean $p_private Private note
     */
    function view_note($p_event, $p_bug_id, $p_bugnote_id, $p_private) {
        if (!access_has_bug_level(plugin_config_get('view_threshold'), $p_bug_id)) {
            return;
        }

        if (isset($this->update_cache[$p_bugnote_id])) {
            $t_update = $this->update_cache[$p_bugnote_id];
            $t_css = $p_private ? 'bugnote-private' : 'bugnote-public';
            $t_css2 = $p_private ? 'bugnote-note-private' : 'bugnote-note-public';
            $t_revisions = '';
            if (isset($this->revision_cache[$p_bugnote_id])
                && ((int)$this->revision_cache[$p_bugnote_id] > 1)
            ) {
                $t_revisions = '<a href="' . plugin_page('revision_page') . '&bugnote_id=' . $p_bugnote_id . '">' .
                    sprintf(lang_get('view_num_revisions'), $this->revision_cache[$p_bugnote_id]) . '</a>';
            }

            echo '<tr class="bugnote"><td class="', $t_css, '">',
            plugin_lang_get('bonuses_fines'),
            "<br/>",
            $t_revisions,
            '</td><td class="', $t_css2, '">',
                user_get_name($t_update['user_id']) . ': ' . motives_format_amount($t_update['amount']),
            '</td></tr>';
        }
    }


    /**
     * Generate and cache a dict of TimecardUpdate objects keyed by bugnote ID.
     * @param string $p_event Event name
     * @param int $p_bug_id Bug ID
     */
    function view_note_start($p_event, $p_bug_id) {
        $this->update_cache = array();
        $this->revision_cache = array();

        if (!access_has_bug_level(plugin_config_get('view_threshold'), $p_bug_id)) {
            return;
        }

        $t_updates = motives_get_by_bug($p_bug_id);

        foreach ($t_updates as $t_update) {
            $this->update_cache[$t_update['bugnote_id']] = $t_update;
            $this->revision_cache[$t_update['bugnote_id']] = motives_revision_count($p_bug_id, $t_update['bugnote_id']);
        }
    }

    /**
     * Show appropriate forms for updating time spent.
     * @param string $p_event Event name
     * @param int $p_bug_id Bug ID
     */
    function add_note_form($p_event, $p_bug_id) {
        if (!access_has_bug_level(plugin_config_get('update_threshold'), $p_bug_id)) {
            return;
        }
        echo '<tr ', helper_alternate_class(), '><td class="category">', plugin_lang_get('bonuses_fines'),
            '</td><td><select id="plugin_motives_user" name="plugin_motives_user"><option value="' . META_FILTER_ANY . '">[' . plugin_lang_get('none') . ']</option>';

        print_note_option_list(NO_USER, bug_get_field($p_bug_id, 'project_id'));
        echo '</select> ',
        plugin_lang_get('amount'), '<input name="plugin_motives_amount" pattern="^(-)?[0-9]+$" title="', plugin_lang_get('error_numbers'), '" value="0" /></td></tr>';
    }

    function update_actiongroup_form() {
        $supportedActions = ['EXT_ADD_NOTE', 'RESOLVE', 'CLOSE'];
        if ($_SERVER['REQUEST_METHOD'] == 'POST' && $_SERVER['SCRIPT_NAME'] == '/bug_actiongroup_page.php') {
            $f_bug_arr = gpc_get_int_array('bug_arr', array());
            $p_action = gpc_get_string('action', '');
            if (!empty($f_bug_arr) AND in_array($p_action, $supportedActions)) {
                $t_projects = array();
                $project_id = null;
                foreach ($f_bug_arr as $t_bug_id) {
                    bug_ensure_exists($t_bug_id);
                    $project_id = bug_get_field($t_bug_id, 'project_id');
                    $t_projects[] = $project_id;
                }
                print "<div id='motives_actiongroup_form'>";
                if (sizeof(array_unique($t_projects)) == 1) {
                    $this->add_note_form($p_action, $t_bug_id);
                } elseif ($project_id) {
                    if (access_has_project_level(plugin_config_get('update_threshold'), $project_id)) {
                        print plugin_lang_get('bugs_projects_differs');
                    }
                }
                print "</div>";
                print '<script>motives_extend_actiongroup_form();</script>';
            }
        }
    }

    /**
     * Show appropriate forms for updating time spent.
     * @param string $p_event Event name
     * @param int $p_bug_id Bug ID
     * @param int $p_bugnote_id Bugnote ID
     */
    function edit_note_form($p_event, $p_bug_id, $p_bugnote_id) {
        if (!access_has_bug_level(plugin_config_get('update_threshold'), $p_bug_id)) {
            return;
        }
        $t_update = motives_get($p_bugnote_id);
        $t_user_id = $t_update != null ? (int)$t_update['user_id'] : NO_USER;
        $t_amount = $t_update != null ? (int)$t_update['amount'] : 0;
        $t_bugnote = bugnote_get($p_bugnote_id);
        $readonly = !(motives_is_allowed_to_edit($t_bugnote->date_submitted));
        echo '<tr ', helper_alternate_class(), '><td class="category">', plugin_lang_get('bonuses_fines'),
            '</td><td><select ' . (!$readonly ?: 'disabled') . ' id="plugin_motives_user" name="plugin_motives_user"><option value="' . META_FILTER_ANY . '">[' . plugin_lang_get('none') . ']</option>';

        print_note_option_list($t_user_id);

        echo '</select> ',
        plugin_lang_get('amount'), '<input ' . (!$readonly ?: 'readonly') . ' name="plugin_motives_amount" pattern="^(-)?[0-9]+$" title="', plugin_lang_get('error_numbers')
        , '" value="', $t_amount, '" /></td></tr>';
    }

    /**
     * Process form data when bugnotes are added.
     * @param string $p_event Event name
     * @param int $p_bug_id Bug ID
     * @param int $p_bugnote_id Bugnote ID
     */
    function add_note($p_event, $p_bug_id, $p_bugnote_id) {
        if (!access_has_bug_level(plugin_config_get('update_threshold'), $p_bug_id)) {
            return;
        }

        $f_amount = gpc_get_int('plugin_motives_amount', 0);
        $f_user_id = gpc_get_int('plugin_motives_user', 0);
        if ($f_user_id > 0 && $f_amount != 0) {
            $t_reporter_id = auth_get_current_user_id();
            motives_add($p_bug_id, $p_bugnote_id, $t_reporter_id, $f_user_id, $f_amount);
            motives_revision_add($p_bug_id, $p_bugnote_id, $t_reporter_id, $f_user_id, $f_amount);
        }
    }

    /**
     * Process form data when bugnotes are edited.
     * @param string $p_event Event name
     * @param int $p_bug_id Bug ID
     * @param int $p_bugnote_id Bugnote ID
     */
    function edit_note($p_event, $p_bug_id, $p_bugnote_id) {
        if (!access_has_bug_level(plugin_config_get('update_threshold'), $p_bug_id)) {
            return;
        }

        $f_amount = gpc_get_int('plugin_motives_amount', 0);
        $f_user_id = gpc_get_int('plugin_motives_user', 0);

        if ($f_user_id > 0) {
            $t_old = motives_get($p_bug_id);
            $t_bugnote = bugnote_get($p_bugnote_id);
            if (motives_is_allowed_to_edit($t_bugnote->date_submitted)) {
                $t_reporter_id = auth_get_current_user_id();
                motives_update($p_bug_id, $p_bugnote_id, $t_reporter_id, $f_user_id, $f_amount);
                motives_revision_add($p_bug_id, $p_bugnote_id, $t_reporter_id, $f_user_id, $f_amount);
                $t_old_value = '';
                $t_new_value = user_get_name($f_user_id) . ': ' . motives_format_amount($f_amount);
                if (!empty($t_old)) {
                    $t_old_value = user_get_name($t_old['user_id']) . ': ' . motives_format_amount($t_old['amount']);
                }
                plugin_history_log($p_bug_id, 'bonus_edited', $t_old_value, $t_new_value, null, self::BASE_NAME);
            }
        }
    }

    /**
     * Delete a bonuses
     * @param $p_event      Event name
     * @param $p_bug_id     Bug id
     * @param $p_bugnote_id Bug note id
     */
    function delete_note($p_event, $p_bug_id, $p_bugnote_id) {
        motives_delete($p_bugnote_id);
    }

    /**
     * Plugin schema.
     */
    function schema() {
        return array(
            array('CreateTableSQL', array(plugin_table('bonus'), "
				bug_id			I		NOTNULL UNSIGNED,
				bugnote_id		I		NOTNULL UNSIGNED,
				reporter_id		I		NOTNULL UNSIGNED,
				user_id			I		NOTNULL UNSIGNED,
				timestamp		T		NOTNULL,
				amount			I		NOTNULL
				")),
            array('CreateTableSQL', array(plugin_table('bonus_revision'), "
				id              I       NOTNULL UNSIGNED AUTOINCREMENT PRIMARY,
				bug_id			I		NOTNULL UNSIGNED,
				bugnote_id		I		NOTNULL UNSIGNED,
				reporter_id		I		NOTNULL UNSIGNED,
				user_id			I		NOTNULL UNSIGNED,
				timestamp		I		UNSIGNED,
				amount			I		NOTNULL
				")),
            array('CreateTableSQL', array(plugin_table('departments'), "
				id              I       NOTNULL UNSIGNED AUTOINCREMENT PRIMARY,
				name			C(250)	NOTNULL DEFAULT \" '' \",
				created_at		T		NOTNULL,
				updated_at		T		NOTNULL
				")),
            array('CreateTableSQL', array(plugin_table('user_departments'), "
				user_id         I       NOTNULL UNSIGNED,
				department_id   I       NOTNULL UNSIGNED,
				role			C(6)	NOTNULL DEFAULT \" 'worker' \",
				created_at		T		NOTNULL,
				updated_at		T		NOTNULL
				")),
            array('CreateTableSQL', array(plugin_table('category_bonus'), "
				project_id      I       NOTNULL UNSIGNED,
				category_id     I       NOTNULL UNSIGNED,
				amount			I   	NOTNULL,
				created_at		T		NOTNULL,
				updated_at		T		NOTNULL
				")),

        );
    }

    function menu() {
        if (!access_has_global_level(plugin_config_get('view_report_threshold'))) {
            return array();
        }

        $links = array();
        $links[] = array(
            'title' => plugin_lang_get('menu'),
            'url'   => plugin_page('motives_page'),
            'icon'  => 'fa-money',
        );
        return $links;
    }

    function init() {
        $t_path = config_get_global('plugin_path') . plugin_get_current() . DIRECTORY_SEPARATOR . 'core' . DIRECTORY_SEPARATOR;
        set_include_path(get_include_path() . PATH_SEPARATOR . $t_path);
        require_once('motives_api.php');
    }

    function config() {
        return array(
            'view_threshold'        => VIEWER,
            'update_threshold'      => MANAGER,
            'view_report_threshold' => VIEWER,
            'day_count'             => 3,
            'show_avatar'           => ON,
            'limit_bug_notes'       => 100000,
        );
    }

    /**
     * Create the resource link
     */
    function resources($p_event) {
        return '<link rel="stylesheet" type="text/css" href="' . plugin_file('motives.css') . '"/>' .
        '<script src="' . plugin_file('motives.js') . '"></script>';
    }

    function on_issue_resolve($p_event, $p_old_bug, $p_bug) {
        if ($p_old_bug->status != $p_bug->status && $p_bug->status == RESOLVED) {
            $t_reporter_id = config_get('plugin_Motives_cron_user');
            if (empty($t_reporter_id) || !user_exists($p_bug->handler_id)) { // unable to add bugnote without reporter. Set in config
                return;
            }
            $t_reporter_id = user_get_id_by_name($t_reporter_id);

            $f_amount = motives_category_bonus_get($p_bug->project_id, $p_bug->category_id);

            if ($f_amount != 0 && !motives_is_bug_has_bonus_by_user($t_reporter_id, $p_bug->id)) {
                $note_text = plugin_lang_get('automatic_bonus');
                $t_bugnote_id = bugnote_add($p_bug->id, $note_text, '0:00', false, BUGNOTE, '', $t_reporter_id, false);
                motives_add($p_bug->id, $t_bugnote_id, $t_reporter_id, $p_bug->handler_id, $f_amount);
                motives_revision_add($p_bug->id, $t_bugnote_id, $t_reporter_id, $p_bug->handler_id, $f_amount);
            }

        }
    }

    /**
     * Add category bonus setup form to project editor page
     * @param $p_event
     * @param $p_project_id
     */
    function edit_project_form($p_event, $p_project_id) {
        $categories = [];
        foreach (category_get_all_rows($p_project_id) as $t_category) {
            $t_category['bonus'] = motives_category_bonus_get($p_project_id, $t_category['id']);
            $categories[] = $t_category;
        }
        print '<form method="POST" name="activity_page_form" action="'
            . string_attribute(plugin_page('category_bonus_save')) . '">';
        print  '
            <div class="col-md-12 col-xs-12">
                <div class="space-10"></div>
                <div id="project-versions-div" class="form-container">
                    <div class="widget-box widget-color-blue2">
                        <div class="widget-header widget-header-small">
                            <h4 class="widget-title lighter">
                                <i class="ace-icon fa fa-money"></i>
                                ' . plugin_lang_get('title') . '     
                            </h4>
                        </div>
                        <!-- body -->
                        <div class="widget-body">
                            <div class="widget-main no-padding">
                                <div class="table-responsive">
                                    <table class="table table-striped table-bordered table-condensed">
                                        <thead>
                                            <tr>
                                                <th>Category</th>
                                                <th>Bonus</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <input type="hidden" name="project_id" value="' . $p_project_id . '">
                                        ' . call_user_func(function ($categories) {
                $html = [];
                foreach ($categories as $category) {
                    $html[] = "<tr><td>{$category['name']}</td><td><input name='categories[{$category['id']}]' value='{$category['bonus']}'></td></tr>";
                }
                return implode('', $html);
            }, $categories) . '
                                        </tbody>
                                    </table>
	                            </div>
                            </div>
                        </div>
                        <!-- footer -->
                        <div class="widget-toolbox padding-8 clearfix">
                            <input type="submit" name="add_version" class="btn btn-sm btn-primary btn-white btn-round" value="' . plugin_lang_get('save') . '">
                        </div>
                    </div>
                </div>
            </div>
        ';
        print "</form>";
    }

}
