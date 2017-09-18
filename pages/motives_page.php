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

require_api( 'bug_api.php' );
require_api( 'bugnote_api.php' );
require_api( 'icon_api.php' );
require_once('motives_api.php');

if ( !access_has_global_level( plugin_config_get( 'view_report_threshold' ) ) ) {
	access_denied();
}

$t_filter = array();

$t_today     = date( 'd:m:Y' );
$t_day_count = plugin_config_get( 'day_count' );
$t_from_day  = date( 'd:m:Y', strtotime( date( 'Y-m-d' ) ) - SECONDS_PER_DAY * ($t_day_count - 1) );

function format_date_submitted( $p_date_submitted ) {
	global $t_today;
	$c_date   = date( 'd:m:Y', $p_date_submitted );
	$c_format = $t_today == $c_date ? 'H:i:s' : 'd.m.y';
	return date( $c_format, $p_date_submitted );
}

/**
 *  print note reporter field
 */
function print_filter_note_user_id2( $p_name = FILTER_PROPERTY_NOTE_USER_ID ) {
	global $t_select_modifier, $t_filter;
	?>
	<!-- BUGNOTE REPORTER -->
	<select <?php echo $t_select_modifier; ?> name="<?php echo $p_name; ?>[]">
		<option
				value="<?php echo META_FILTER_ANY ?>" <?php check_selected( $t_filter[FILTER_PROPERTY_NOTE_USER_ID], META_FILTER_ANY ); ?>>
			[<?php echo lang_get( 'none' ) ?>]
		</option>
		<?php if ( access_has_project_level( config_get( 'view_handler_threshold' ) ) ) { ?>
			<?php
			if ( access_has_project_level( config_get( 'handle_bug_threshold' ) ) ) {
				echo '<option value="' . META_FILTER_MYSELF . '" ';
				check_selected( $t_filter[FILTER_PROPERTY_NOTE_USER_ID], META_FILTER_MYSELF );
				echo '>[' . lang_get( 'myself' ) . ']</option>';
			}

			print_note_option_list( $t_filter[FILTER_PROPERTY_NOTE_USER_ID] );
		}
		?>
	</select>
	<?php
}

/**
 *  print note reporter field
 */
function print_filter_department( $p_name = FILTER_PROPERTY_DEPARTMENT ) {
	global $t_select_modifier, $t_filter;
    $departments = motives_department_get();
	?>
	<!-- BUGNOTE REPORTER -->
	<select <?php echo $t_select_modifier; ?> name="<?php echo $p_name; ?>">
		<option
				value="<?php echo META_FILTER_ANY ?>" <?php check_selected( $t_filter[FILTER_PROPERTY_DEPARTMENT], META_FILTER_ANY ); ?>>
			[<?php echo lang_get( 'none' ) ?>]
		</option>
		<?php if ( access_has_project_level( config_get( 'view_handler_threshold' ) ) ) {
			foreach($departments as $department) { ?>
                <option <?php check_selected( $t_filter[FILTER_PROPERTY_DEPARTMENT], intval($department['id']) ); ?> value="<?php echo $department['id']?>"><?php echo $department['name'] ?></option>
            <?php }
		}
		?>
	</select>
	<?php
}

function string_get_bugnote_view_link2( $p_bug_id, $p_bugnote_id, $p_user_id = null, $p_detail_info = true, $p_fqdn = false ) {
	$t_bug_id = (int)$p_bug_id;

	if ( bug_exists( $t_bug_id ) && bugnote_exists( $p_bugnote_id ) ) {
		$t_link = '<a href="';
		if ( $p_fqdn ) {
			$t_link .= config_get_global( 'path' );
		} else {
			$t_link .= config_get_global( 'short_path' );
		}

		$t_link .= string_get_bugnote_view_url( $p_bug_id, $p_bugnote_id, $p_user_id ) . '"';
		if ( $p_detail_info ) {
			$t_reporter    = string_attribute( user_get_name( bugnote_get_field( $p_bugnote_id, 'reporter_id' ) ) );
			$t_update_date = string_attribute( date( config_get( 'normal_date_format' ), (bugnote_get_field( $p_bugnote_id, 'last_modified' )) ) );
			$t_link        .= ' title="' . bug_format_id( $t_bug_id ) . ': [' . $t_update_date . '] ' . $t_reporter . '"';
		}

		$t_link .= '>' . bugnote_format_id( $p_bugnote_id ) . '</a>';
	} else {
		$t_link = bugnote_format_id( $p_bugnote_id );
	}

	return $t_link;
}

/**
 * @param $p_group BugnoteData[]
 * @return bool
 */
function is_empty_group( $p_group ) {
	foreach ( $p_group as $t_bugnote ) {
		$t_note = trim( $t_bugnote['note'] );
		if ( !empty( $t_note ) ) return false;
	}
	return true;
}

function motives_count_bugnotes( $p_group ) {
	$result = 0;
	foreach ( $p_group as $t_item ) {
		$result += count( $t_item );
	}
	return $result;
}

$t_user_id = auth_get_current_user_id();

$f_note_user_id_arr = gpc_get_int_array( 'note_user_id', array() );
$f_note_user_id     = empty( $f_note_user_id_arr ) ? null : $f_note_user_id_arr[0];
if ( $f_note_user_id == -1 ) $f_note_user_id = auth_get_current_user_id();

$f_bonus_user_id_arr = gpc_get_int_array( 'bonus_user_id', array() );
$f_bonus_user_id     = empty( $f_bonus_user_id_arr ) ? NO_USER : $f_bonus_user_id_arr[0];

$f_category_id    = gpc_get_int('category_id', -1);

$f_project = gpc_get_string( 'project', '' );
$f_page    = gpc_get_string( 'page', '' );
$f_department    = gpc_get_int( 'department_id', '' );

if ( is_blank( $f_project ) ) {
	$f_project_id = gpc_get_int( 'project_id', -1 );
} else {
	$f_project_id = project_get_id_by_name( $f_project );
	if ( $f_project_id === 0 ) {
		trigger_error( ERROR_PROJECT_NOT_FOUND, ERROR );
	}
}



if ( $f_project_id == -1 ) {
	$t_project_id = helper_get_current_project();
} else {
	$t_project_id = $f_project_id;
}

if ( ALL_PROJECTS == $t_project_id ) {
	$t_topprojects = $t_project_ids = user_get_accessible_projects( $t_user_id );
	foreach ( $t_topprojects as $t_project ) {
		$t_project_ids = array_merge( $t_project_ids, user_get_all_accessible_subprojects( $t_user_id, $t_project ) );
	}

	$t_project_ids_to_check = array_unique( $t_project_ids );
	$t_project_ids          = array();

	foreach ( $t_project_ids_to_check as $t_project_id_item ) {
		$t_changelog_view_access_level = config_get( 'view_changelog_threshold', null, null, $t_project_id_item );
		if ( access_has_project_level( $t_changelog_view_access_level, $t_project_id_item ) ) {
			$t_project_ids[] = $t_project_id_item;
		}
	}
} else {
	//access_ensure_project_level( config_get( 'view_changelog_threshold' ), $t_project_id );
	$t_project_ids = user_get_all_accessible_subprojects( $t_user_id, $t_project_id );
	array_unshift( $t_project_ids, $t_project_id );
}

layout_page_header( plugin_lang_get( 'title' ) );
layout_page_begin();

$t_project_index = 0;

$t_project_ids_size = count( $t_project_ids );
echo '<br/>';

$t_stats_from_def    = $t_from_day;
$t_stats_from_def_ar = explode( ":", $t_stats_from_def );
$t_stats_from_def_d  = $t_stats_from_def_ar[0];
$t_stats_from_def_m  = $t_stats_from_def_ar[1];
$t_stats_from_def_y  = $t_stats_from_def_ar[2];

$t_stats_from_d = gpc_get_int( 'start_day', $t_stats_from_def_d );
$t_stats_from_m = gpc_get_int( 'start_month', $t_stats_from_def_m );
$t_stats_from_y = gpc_get_int( 'start_year', $t_stats_from_def_y );

$t_stats_to_def    = $t_today;
$t_stats_to_def_ar = explode( ":", $t_stats_to_def );
$t_stats_to_def_d  = $t_stats_to_def_ar[0];
$t_stats_to_def_m  = $t_stats_to_def_ar[1];
$t_stats_to_def_y  = $t_stats_to_def_ar[2];

$t_stats_to_d = gpc_get_int( 'end_day', $t_stats_to_def_d );
$t_stats_to_m = gpc_get_int( 'end_month', $t_stats_to_def_m );
$t_stats_to_y = gpc_get_int( 'end_year', $t_stats_to_def_y );

$t_from = "$t_stats_from_y-$t_stats_from_m-$t_stats_from_d";
$t_to   = "$t_stats_to_y-$t_stats_to_m-$t_stats_to_d";
$t_to_first_day_pattern   = "$t_stats_to_y-" . ($t_stats_to_m > 10 ? $t_stats_to_m : "0$t_stats_to_m");

$t_show_status_legend   = plugin_config_get( 'show_status_legend' );
$t_show_avatar          = plugin_config_get( 'show_avatar', config_get( 'show_avatar', OFF ) );
$t_limit_bug_notes      = (int)plugin_config_get( 'limit_bug_notes', 1000 );
$t_update_bug_threshold = config_get( 'update_bug_threshold' );
$t_icon_path            = config_get( 'icon_path' );
$t_show_priority_text   = config_get( 'show_priority_text' );
$t_use_javascript       = config_get( 'use_javascript', ON );


$t_project_bugs = array();
$t_category_bugs = array();
$t_project_size  = 0;
$t_total_issues  = 0;
$t_total_notes   = 0;
$t_total_bonuses = 0;
$t_total_fines   = 0;
$t_user_bonuses  = array();
$t_user_fines    = array();
$t_user_bonuses_total  = array();
$t_user_fines_total    = array();

foreach ( $t_project_ids as $t_project_id_item ) {
	$t_bug_notes     = motives_get_latest_bugnotes( $t_project_id_item, $t_from, $t_to, $f_note_user_id, $f_bonus_user_id, $f_category_id, $t_limit_bug_notes , $f_department);
	$t_bug_note_size = count( $t_bug_notes );
	if ( $t_bug_note_size == 0 ) continue;

	$t_bugs      = motives_group_by_bug( $t_bug_notes );
	$t_bugs_size = count( $t_bugs );

	$t_project_bugs[$t_project_id_item]['bugs']      = $t_bugs;
	$t_project_bugs[$t_project_id_item]['note_size'] = $t_bug_note_size;
	$t_project_bugs[$t_project_id_item]['bugs_size'] = $t_bugs_size;
	$t_total_notes                              += $t_bug_note_size;
	$t_total_issues                             += $t_bugs_size;
	$t_project_size++;

	foreach ( $t_bug_notes as $t_bug_item ) {
		$t_amount          = (int)$t_bug_item['amount'];
		$t_bug_category_id = $t_bug_item['category_id'];
		if ( $t_amount > 0 ) {
			$t_total_bonuses += $t_amount;
			if ( !isset( $t_user_bonuses[$t_bug_item['bonus_user_id']][$t_project_id_item][$t_bug_category_id] ) )
				$t_user_bonuses[$t_bug_item['bonus_user_id']][$t_project_id_item][$t_bug_category_id] = 0;
			if ( !isset( $t_user_bonuses_total[$t_bug_item['bonus_user_id']] ) )
				$t_user_bonuses_total[$t_bug_item['bonus_user_id']] = ['all' => 0, 'month' => 0];
			$t_user_bonuses[$t_bug_item['bonus_user_id']][$t_project_id_item][$t_bug_category_id] += $t_amount;
			$t_user_bonuses_total[$t_bug_item['bonus_user_id']]['all'] += $t_amount;
            if (date('Y-m',$t_bug_item['date_submitted']) == $t_to_first_day_pattern) {
                $t_user_bonuses_total[$t_bug_item['bonus_user_id']]['month'] += $t_amount;
            }
		} else {
			$t_total_fines += $t_amount;
			if ( !isset( $t_user_fines[$t_bug_item['bonus_user_id']][$t_project_id_item][$t_bug_category_id] ) )
				$t_user_fines[$t_bug_item['bonus_user_id']][$t_project_id_item][$t_bug_category_id] = 0;
			if ( !isset( $t_user_fines_total[$t_bug_item['bonus_user_id']] ) )
				$t_user_fines_total[$t_bug_item['bonus_user_id']] = ['all' => 0, 'month' => 0];
			$t_user_fines[$t_bug_item['bonus_user_id']][$t_project_id_item][$t_bug_category_id] += $t_amount;
			$t_user_fines_total[$t_bug_item['bonus_user_id']]['all'] += $t_amount;
            if (date('Y-m',$t_bug_item['date_submitted']) == $t_to_first_day_pattern) {
                $t_user_fines_total[$t_bug_item['bonus_user_id']]['month'] += $t_amount;
            }
		}
		$t_category_bugs[$t_project_id_item][$t_bug_category_id][$t_bug_item['bug_id']][] = $t_bug_item;
	}
}

?>
	<div class="col-md-12 col-xs-12">
		<form method="get" name="activity_page_form"
			  action="<?php echo string_attribute( plugin_page( 'activity_page' ) ) ?>">
			<input type="hidden" name="page" value="<?php echo htmlspecialchars( $f_page ); ?>"/>
			<input type="hidden" id="activity_project_id" name="project_id"
				   value="<?php echo htmlspecialchars( $f_project_id ); ?>"/>

			<div class="filter-box">
				<div class="widget-box widget-color-blue2">
					<div class="widget-header widget-header-small">
						<h4 class="widget-title lighter">
							<i class="ace-icon fa fa-filter"></i>
							<?php
							echo lang_get( 'filters' );
							if ( $t_project_size > 1 ) {
								$t_total_issues_html = '<span title="' . plugin_lang_get( 'total_issues' ) . '">' . $t_total_issues . '</span>';
								$t_total_notes_html  = '<span title="' . plugin_lang_get( 'total_notes' ) . '">' . $t_total_notes . '</span>';
								echo '<span class="badge">', $t_total_issues_html, '/', $t_total_notes_html, '</span>';
							}
							?>
                            <div class="widget-toolbar">
                                <a href="<?php echo string_attribute( plugin_page( 'departments_page' ) ) ?>">
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
										$t_filter['start_day']         = $t_stats_from_d;
										$t_filter['start_month']       = $t_stats_from_m;
										$t_filter['start_year']        = $t_stats_from_y;
										$t_filter['end_day']           = $t_stats_to_d;
										$t_filter['end_month']         = $t_stats_to_m;
										$t_filter['end_year']          = $t_stats_to_y;
										print_filter_do_filter_by_date( true, $t_filter );

										echo plugin_lang_get( 'department' ) . ':&nbsp;';
										$t_filter[FILTER_PROPERTY_DEPARTMENT] = $f_department;
										print_filter_department();
										?>
									</td>
									<td class="bold">
										<?php
										echo plugin_lang_get( 'reported_user' ) . ':&nbsp;';
										$t_filter[FILTER_PROPERTY_NOTE_USER_ID] = $f_note_user_id_arr;
										print_filter_note_user_id2();
										?>
									</td>
									<td class="bold">
										<?php
										echo plugin_lang_get( 'bonus_user' ) . ':&nbsp;';
										$t_filter[FILTER_PROPERTY_NOTE_USER_ID] = $f_bonus_user_id_arr;
										print_filter_note_user_id2('bonus_user_id');
										?>
									</td>
									<td class="bold">
										<?php
										echo lang_get( 'category' ) . ':&nbsp;';
										echo '<select ' . helper_get_tab_index() . ' id="category_id" name="category_id" class="input-sm">';
										echo '<option value="-1" ' . check_selected( $f_category_id, -1 ) . '>[' . lang_get( 'none' ) . ']</option>';
										print_category_option_list( $f_category_id, $t_project_id );
										echo '</select>';
										?>
									</td>
								</tr>
							</table>
						</div>
						<div class="widget-toolbox center clearfix">
							<input type="submit" class="btn btn-xs btn-primary btn-white btn-round"
								   value="<?php echo plugin_lang_get( 'get_info_button' ) ?>"
							/>
						</div>
					</div>
				</div>
			</div>
		</form>
	</div>
<?php if (!empty($t_project_bugs)) { ?>
    <div class="col-md-12 col-xs-12">
        <div class="space-10"></div>
        <div class="widget-box widget-color-blue2">
            <div class="widget-header widget-header-small">
                <h4 class="widget-title"><i
                            class="ace-icon fa fa-columns"></i><?php echo plugin_lang_get( 'bonuses_fines' ) ?></h4>
                <div class="widget-toolbar">
                    <a id="filter-toggle" data-action="collapse" href="#">
                        <i class="1 ace-icon fa bigger-125 fa-chevron-up"></i>
                    </a>
                </div>
            </div>
            <div class="widget-body">
                <div class="widget-main">
					<?php echo plugin_lang_get( 'total_issues' ) ?>: <?php echo $t_total_issues ?><br/>
					<?php echo plugin_lang_get( 'total_notes' ) ?>: <?php echo $t_total_notes ?><br/>
					<?php echo plugin_lang_get( 'total_amount_bonuses' ) ?>: <?php echo $t_total_bonuses ?><br/>
					<?php echo plugin_lang_get( 'total_amount_fines' ) ?>: <?php echo $t_total_fines ?><br/>
					<?php
					if ( !empty( $t_user_bonuses_total ) ) {
						echo '<span><u>' . plugin_lang_get('sum_bonuses_user') . " ($t_from - $t_to):</u></span>";
						echo '<div class="motives-bonuses-by-user">';
						foreach ( $t_user_bonuses_total as $t_user_id => $bonus ) {
							echo '<span class="bold">' . user_get_name( $t_user_id ) . '</span>: ' . motives_format_amount( $bonus['all'] );
							echo ' (' . plugin_lang_get('last_month') . ': ' . motives_format_amount( $bonus['month'] ) . ')</br>';
						}
						echo '</div>';
					}
					if ( !empty( $t_user_fines_total ) ) {
						echo '<span><u>' . plugin_lang_get('sum_fines_user') . " ($t_from - $t_to):</u></span>";;
						echo '<div class="motives-fines-by-user">';
						foreach ( $t_user_fines_total as $t_user_id => $bonus ) {
							echo '<span class="bold">' . user_get_name( $t_user_id ) . '</span>: ' . motives_format_amount( $bonus['all'] );
							echo ' (' . plugin_lang_get('last_month') . ': ' . motives_format_amount( $bonus['month'] ) . ')</br>';
						}
						echo '</div>';
					}
					if ( !empty( $t_user_bonuses ) ) {
						echo '<span><u>' . plugin_lang_get('total_bonuses_user') . ':</u></span>';
						echo '<div class="motives-bonuses-by-user">';
						foreach ( $t_user_bonuses as $t_user_id => $t_user_projects ) {
							echo '<span class="bold">' . user_get_name( $t_user_id ) . '</span>:<br/>';
							foreach ( $t_user_projects as $t_user_project => $t_user_categories ) {
								foreach ( $t_user_categories as $t_user_cat => $t_user_amount ) {
									echo project_get_name( $t_user_project ) . '/' . category_get_name( $t_user_cat ) . ': ' . motives_format_amount( $t_user_amount ) . '</br>';
								}
							}
						}
						echo '</div>';
					}
					if (!empty( $t_user_fines )) {
						echo '<span><u>' . plugin_lang_get('total_fines_user') . ':</u></span>';
						echo '<div class="motives-fines-by-user">';
						foreach ( $t_user_fines as $t_user_id => $t_user_projects ) {
							echo '<span class="bold">' . user_get_name( $t_user_id ) . '</span>:<br/>';
							foreach ( $t_user_projects as $t_user_project => $t_user_categories ) {
								foreach ( $t_user_categories as $t_user_cat => $t_user_amount ) {
									echo project_get_name( $t_user_project ) . '/' . category_get_name( $t_user_cat ) . ': ' . motives_format_amount( $t_user_amount ) . '</br>';
								}
							}
						}
						echo '</div>';
					}
					?>
                </div>
            </div>
        </div>
    </div>
	<?php
}

foreach ( $t_project_bugs as $t_project_id => $t_project_data ) {
	foreach ( $t_category_bugs[$t_project_id] as $t_bugnote_category_id => $t_category_bugs_array ) {
		$t_bug_note_size     = motives_count_bugnotes ( $t_category_bugs_array );
		$t_project_name_link = '';
		$t_project_html      = '';

		if ( $t_bug_note_size == 0 ) continue;

		$t_project_name   = project_get_field( $t_project_id, 'name' );
		$t_category_title = category_get_name( $t_bugnote_category_id );
		if ( $t_use_javascript && $t_project_ids_size > 1 ) {
			$t_project_name_link = '<span style="cursor:pointer;" class="motives-project-link" data-project="' . $t_project_id . '">' . $t_project_name . '/' . $t_category_title . '</span>';
		} else {
			$t_project_name_link = $t_project_name . '/' . $t_category_title;
		}

		$t_bugs              = $t_category_bugs_array;
		$t_issue_size        = count( $t_category_bugs );
		$t_issue_size_html   = '<span title="' . plugin_lang_get( 'issues' ) . '">' . $t_issue_size . '</span>';
		$t_bugnote_size_html = '<span title="' . plugin_lang_get( 'notes' ) . '">' . $t_bug_note_size . '</span>';

		echo '<div class="col-md-12 col-xs-12 motives-project">',
		'<div class="widget-box widget-color-blue2">',
		'<div class="widget-header widget-header-small">',
			'<h4 class="widget-title"><i class="ace-icon fa fa-columns"></i>' . $t_project_name_link . '<span class="badge"> ' . $t_issue_size_html . '/' . $t_bugnote_size_html . '</span></h4>',
		'<div class="widget-toolbar">
                            <a id="filter-toggle" data-action="collapse" href="#">
                                <i class="1 ace-icon fa bigger-125 fa-chevron-up"></i>
                            </a>
			         </div>',
		'</div>',
		'<div class="widget-body"><div class="widget-main no-padding">';
		foreach ( $t_bugs as $t_bug_id => $t_group ) {
			if ( !empty( $t_group ) && !is_empty_group( $t_group ) ) {
				$t_bug              = bug_get( $t_bug_id );
				$t_summary          = $t_bug->summary;
				$t_status_color     = get_status_color( $t_bug->status, $t_user_id, $t_project_id );
				$t_date_submitted   = date( config_get( 'complete_date_format' ), $t_bug->date_submitted );
				$t_background_color = 'background-color: ' . $t_status_color;

				echo '<table cellspacing="0" class="table motives-table"><tbody>', '<tr><td class="news-heading-public motives-center" width="65px" style="' . $t_background_color . '">';
				print_bug_link( $t_bug_id, true );
				echo '<br/>';

				if ( !bug_is_readonly( $t_bug_id ) && access_has_bug_level( $t_update_bug_threshold, $t_bug_id ) ) {
					echo '<a href="' . string_get_bug_update_url( $t_bug_id ) . '">',
						'<i class="fa fa-pencil bigger-130 padding-2 grey" title="' . lang_get( 'update_bug_button' ) . '"></i>',
					'</a>';
				}

				if ( ON == $t_show_priority_text ) {
					print_formatted_priority_string( $t_bug );
				} else {
					print_status_icon( $t_bug->priority );
				}

				echo '</td><td class="news-heading-public" style="' . $t_background_color . '"><span class="bold">' . $t_summary . '</span> - <span class="italic-small">' . $t_date_submitted . '</span>', '</td></tr>';
				foreach ( $t_group as $t_bugnote ) {

					$t_date_submitted  = format_date_submitted( $t_bugnote['date_submitted'] );
					$t_user_id         = VS_PRIVATE == $t_bugnote['view_state'] ? null : $t_bugnote['reporter_id'];
					$t_user_name       = $t_user_id != null ? user_get_name( $t_user_id ) : lang_get( 'private' );
					$t_user_link       = $t_user_id != null ? '<a href="view_user_page.php?id=' . $t_user_id . '">' . $t_user_name . '</a>' : $t_user_name;
					$t_bonus_user_name = !empty( $t_bugnote['bonus_user_id'] ) ? user_get_name( $t_bugnote['bonus_user_id'] ) : lang_get( 'private' );
					$t_bonus_user_link = !empty( $t_bugnote['bonus_user_id'] ) ? '<a href="view_user_page.php?id=' . $t_bugnote['bonus_user_id'] . '">' . $t_bonus_user_name . '</a>' : $t_bonus_user_name;
					$t_note            = string_display_links( trim( $t_bugnote['note'] ) );
					$t_bugnote_link    = string_get_bugnote_view_link2( $t_bugnote['bug_id'], $t_bugnote['id'], $t_user_id );
					$t_amount          = motives_format_amount( $t_bugnote['amount'] );
					if ( !empty( $t_note ) ) {
						echo '<tr><td align="center" style="vertical-align: top; text-align: center;"><div class="motives-date">', $t_date_submitted, '</div>', '';
						if ( $t_show_avatar && !empty( $t_user_id ) ) print_avatar( $t_user_id, 60 );
						echo '</td>';
						echo '<td style="vertical-align: top;"><div class="motives-item">',
						'<span class="bold">', $t_user_link, '</span> (', $t_bugnote_link, ') - ', $t_bonus_user_link, ' ', $t_amount, '</div>',
						'<div class="motives-note">', $t_note, '</div>', '</div></td></tr>';
					}
				}

				echo '</table>';
			}
		}
		echo '</div></div></div></div>';
	}
}

layout_page_end();
