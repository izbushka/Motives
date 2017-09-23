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

function motives_email_send($p_user_id, $p_subject, $p_body) {
    $t_email = user_get_email($p_user_id);

    $t_email_data = new EmailData();
    $t_email_data->email = $t_email;
    $t_email_data->subject = $p_subject;
    $t_email_data->body = $p_body;
    $t_email_data->metadata['charset'] = 'UTF-8';
    motives_sendmail($t_email_data);
}

/**
 * This function sends an email message based on the supplied email data.
 *
 * @param EmailData $p_email_data Email Data object representing the email to send.
 * @return boolean
 */
function motives_sendmail(EmailData $p_email_data) {
    global $g_phpMailer;

    $t_email_data = $p_email_data;

    $t_recipient = trim($t_email_data->email);
    $t_subject = string_email(trim($t_email_data->subject));
    $t_message = string_email_links(trim($t_email_data->body));

    $t_debug_email = config_get_global('debug_email');
    $t_mailer_method = config_get('phpMailer_method');

    $t_log_msg = 'ERROR: Message could not be sent - ';

    if (is_null($g_phpMailer)) {
        if ($t_mailer_method == PHPMAILER_METHOD_SMTP) {
            register_shutdown_function('email_smtp_close');
        }
        $t_mail = new PHPMailer(true);
    } else {
        $t_mail = $g_phpMailer;
    }

    if (isset($t_email_data->metadata['hostname'])) {
        $t_mail->Hostname = $t_email_data->metadata['hostname'];
    }

    # @@@ should this be the current language (for the recipient) or the default one (for the user running the command) (thraxisp)
    $t_lang = config_get('default_language');
    if ('auto' == $t_lang) {
        $t_lang = config_get('fallback_language');
    }
    $t_mail->SetLanguage(lang_get('phpmailer_language', $t_lang));

    # Select the method to send mail
    switch (config_get('phpMailer_method')) {
        case PHPMAILER_METHOD_MAIL:
            $t_mail->IsMail();
            break;

        case PHPMAILER_METHOD_SENDMAIL:
            $t_mail->IsSendmail();
            break;

        case PHPMAILER_METHOD_SMTP:
            $t_mail->IsSMTP();

            # SMTP collection is always kept alive
            $t_mail->SMTPKeepAlive = true;

            if (!is_blank(config_get('smtp_username'))) {
                # Use SMTP Authentication
                $t_mail->SMTPAuth = true;
                $t_mail->Username = config_get('smtp_username');
                $t_mail->Password = config_get('smtp_password');
            }

            if (is_blank(config_get('smtp_connection_mode'))) {
                $t_mail->SMTPAutoTLS = false;
            } else {
                $t_mail->SMTPSecure = config_get('smtp_connection_mode');
            }

            $t_mail->Port = config_get('smtp_port');

            break;
    }

    $t_mail->IsHTML(true);              # set email format to plain text
    $t_mail->WordWrap = 80;              # set word wrap to 80 characters
    $t_mail->CharSet = $t_email_data->metadata['charset'];
    $t_mail->Host = config_get('smtp_host');
    $t_mail->From = config_get('from_email');
    $t_mail->Sender = config_get('return_path_email');
    $t_mail->FromName = config_get('from_name');
    $t_mail->AddCustomHeader('Auto-Submitted:auto-generated');
    $t_mail->AddCustomHeader('X-Auto-Response-Suppress: All');

    # Setup new line and encoding to avoid extra new lines with some smtp gateways like sendgrid.net
    $t_mail->LE = "\r\n";
    $t_mail->Encoding = 'quoted-printable';

    if (isset($t_email_data->metadata['priority'])) {
        $t_mail->Priority = $t_email_data->metadata['priority'];  # Urgent = 1, Not Urgent = 5, Disable = 0
    }

    if (!empty($t_debug_email)) {
        $t_message = 'To: ' . $t_recipient . "\n\n" . $t_message;
        $t_recipient = $t_debug_email;
        log_event(LOG_EMAIL_VERBOSE, "Using debug email '$t_debug_email'");
    }

    try {
        $t_mail->AddAddress($t_recipient, '');
    } catch (phpmailerException $e) {
        log_event(LOG_EMAIL, $t_log_msg . $t_mail->ErrorInfo);
        $t_success = false;
        $t_mail->ClearAllRecipients();
        $t_mail->ClearAttachments();
        $t_mail->ClearReplyTos();
        $t_mail->ClearCustomHeaders();
        return $t_success;
    }

    $t_mail->Subject = $t_subject;
    $t_mail->Body = make_lf_crlf($t_message);

    if (isset($t_email_data->metadata['headers']) && is_array($t_email_data->metadata['headers'])) {
        foreach ($t_email_data->metadata['headers'] as $t_key => $t_value) {
            switch ($t_key) {
                case 'Message-ID':
                    # Note: hostname can never be blank here as we set metadata['hostname']
                    # in email_store() where mail gets queued.
                    if (!strchr($t_value, '@') && !is_blank($t_mail->Hostname)) {
                        $t_value = $t_value . '@' . $t_mail->Hostname;
                    }
                    $t_mail->set('MessageID', '<' . $t_value . '>');
                    break;
                case 'In-Reply-To':
                    $t_mail->AddCustomHeader($t_key . ': <' . $t_value . '@' . $t_mail->Hostname . '>');
                    break;
                default:
                    $t_mail->AddCustomHeader($t_key . ': ' . $t_value);
                    break;
            }
        }
    }

    try {
        $t_success = $t_mail->Send();
        if ($t_success) {
            $t_success = true;

            if ($t_email_data->email_id > 0) {
                email_queue_delete($t_email_data->email_id);
            }
        } else {
            # We should never get here, as an exception is thrown after failures
            log_event(LOG_EMAIL, $t_log_msg . $t_mail->ErrorInfo);
            $t_success = false;
        }
    } catch (phpmailerException $e) {
        log_event(LOG_EMAIL, $t_log_msg . $t_mail->ErrorInfo);
        $t_success = false;
    }

    $t_mail->ClearAllRecipients();
    $t_mail->ClearAttachments();
    $t_mail->ClearReplyTos();
    $t_mail->ClearCustomHeaders();

    return $t_success;
}


function motives_get_totals_html(&$data, $datetime_from, $datetime_to, $datetime_month) {
    $t_user_bonuses = $data['t_user_bonuses'];
    $t_user_fines = $data['t_user_fines'];
    $t_total_issues = $data['t_total_issues'];
    $t_total_notes = $data['t_total_notes'];
    $t_total_bonuses = $data['t_total_bonuses'];
    $t_total_fines = $data['t_total_fines'];
    $t_user_fines_total = $data['t_user_fines_total'];
    $t_user_bonuses_total = $data['t_user_bonuses_total'];

    $html[] = "<b>" . $datetime_from->format('Y-m-d') . ' - ' . $datetime_to->format('Y-m-d') . '</b><br>';
    if (empty($t_user_bonuses_total)) {
        $html[] = plugin_lang_get('empty_set');
    } else {
        $html[] = '
            <table class="table table-bordered table-condensed2 bonus_fine">
                <thead>
                <tr class="buglist-headers">
                    <th>' . plugin_lang_get('total_issues') . '</th>
                    <th>' . plugin_lang_get('total_notes') . '</th>
                    <th>' . plugin_lang_get('total_amount_bonuses') . '</th>
                    <th>' . plugin_lang_get('total_amount_fines') . '</th>
                    <th>' . plugin_lang_get('balance') . '</th>
                </tr>
                </thead>
                <tr>
                    <th>' . $t_total_issues . ' </th>
                    <th>' . $t_total_notes . ' </th>
                    <td class="bonus">' . $t_total_bonuses . ' </td>
                    <td class="fine">' . $t_total_fines . ' </td>
                    <td class="total">' . ($t_total_fines + $t_total_bonuses) . ' </td>
                </tr>
            </table>
        ';
        if (!empty($t_user_bonuses_total)) {
            $html[] = '
                <table class="table table-bordered table-condensed2 bonus_fine">
                    <thead>
                    <tr class="buglist-headers">
                        <th rowspan="2">' . lang_get('username') . '</th>
                        <th colspan="3" class="t_center">' . $datetime_from->format('Y-m-d') . " - " . $datetime_to->format('Y-m-d') . '</th>
                        <th colspan="3" class="t_center">' . $datetime_month->format('Y-m-d') . " - " . $datetime_to->format('Y-m-d') . '</th>
                    </tr>
                    <tr>
                        <th>' . plugin_lang_get('bonus') . ' </th>
                        <th>' . plugin_lang_get('fine') . ' </th>
                        <th>' . plugin_lang_get('balance') . ' </th>
                        <th>' . plugin_lang_get('bonus') . ' </th>
                        <th>' . plugin_lang_get('fine') . ' </th>
                        <th>' . plugin_lang_get('balance') . ' </th>
                    </tr>
                    </thead>
                    <tbody>
            ';
            foreach ($t_user_bonuses_total as $t_user_id => $bonus) {
                $fine = $t_user_fines_total[$t_user_id];
                $html[] = '
                <tr>
                    <th class="left b">' . user_get_name($t_user_id) . '</th>
                    <td class="bonus">' . motives_format_amount($bonus['period']) . '</td>
                    <td class="fine"> ' . motives_format_amount($fine['period']) . '</td>
                    <td class="total">' . motives_format_amount($fine['period'] + $bonus['period']) . '</td>
                    <td class="bonus">' . motives_format_amount($bonus['month']) . '</td>
                    <td class="fine"> ' . motives_format_amount($fine['month']) . '</td>
                    <td class="total">' . motives_format_amount($fine['month'] + $bonus['month']) . '</td>
                </tr>
                ';
            }
            $html[] = '</tbody>';
            $html[] = '</table>';
        }
        // user by project
        if (!empty($t_user_bonuses)) {
            $html[] = '<span><u>' . plugin_lang_get('total_bonuses_user') . '</u></span>';
            $html[] = '<table class="table table-bordered table-condensed2 bonus_fine">';
            foreach ($t_user_bonuses as $t_user_id => $t_user_projects) {
                $html[] = '
                    <tr>
                        <th class="left b">' . user_get_name($t_user_id) . '</th>
                        <th>' . plugin_lang_get('bonus') . '</th>
                        <th>' . plugin_lang_get('fine') . '</th>
                        <th>' . plugin_lang_get('balance') . '</th>
                    </tr>
                ';
                foreach ($t_user_projects as $t_user_project => $t_user_categories) {
                    foreach ($t_user_categories as $t_user_cat => $bonus) {
                        $fine = $t_user_fines[$t_user_id][$t_user_project][$t_user_cat];
                        $html[] = '
                            <tr>
                                <td>
                                ' . project_get_name($t_user_project) . '/' . category_get_name($t_user_cat) . '</th>
                                <td class="bonus">' . motives_format_amount($bonus) . '</td>
                                <td class="fine">' . motives_format_amount($fine) . '</td>
                                <td class="total">' . motives_format_amount($fine + $bonus) . '</td>
                            </tr>
                        ';
                    }
                }
            }
            $html[] = '</table>';
        }
    }
    return implode("\n", $html);
}


function motives_get_related_notes_html(&$data, $forEmail = false) {
    $t_user_id = auth_get_current_user_id();

    $t_project_bugs = $data['t_project_bugs'];
    $t_category_bugs = $data['t_category_bugs'];

    $html = [];

    foreach ($t_project_bugs as $t_project_id => $t_project_data) {
        foreach ($t_category_bugs[$t_project_id] as $t_bugnote_category_id => $t_category_bugs_array) {
            $t_bug_note_size = motives_count_bugnotes($t_category_bugs_array);

            if ($t_bug_note_size == 0) continue;

            $t_project_name = project_get_field($t_project_id, 'name');
            $t_category_title = category_get_name($t_bugnote_category_id);
            $t_project_name_link = $t_project_name . '/' . $t_category_title;

            $t_bugs = $t_category_bugs_array;
            $t_issue_size = count($t_category_bugs);
            $t_issue_size_html = '<span title="' . plugin_lang_get('issues') . '">' . $t_issue_size . '</span>';
            $t_bugnote_size_html = '<span title="' . plugin_lang_get('notes') . '">' . $t_bug_note_size . '</span>';

            $html[] = '<div class="col-md-12 col-xs-12 motives-project">' .
                '<div class="widget-box widget-color-blue2">' .
                '<div class="widget-header widget-header-small">' .
                '<h4 class="widget-title"><i class="ace-icon fa fa-columns"></i>' . $t_project_name_link . '<span class="badge"> ' . $t_issue_size_html . '/' . $t_bugnote_size_html . '</span></h4>' .
                '<div class="widget-toolbar">
                                <a id="filter-toggle" data-action="collapse" href="#">
                                    <i class="1 ace-icon fa bigger-125 fa-chevron-up"></i>
                                </a>
                         </div>' .
                '</div>' .
                '<div class="widget-body"><div class="widget-main no-padding">';
            foreach ($t_bugs as $t_bug_id => $t_group) {
                if (!empty($t_group) && !is_empty_group($t_group)) {
                    $t_bug = bug_get($t_bug_id);
                    $t_summary = $t_bug->summary;
                    $t_status_color = get_status_color($t_bug->status, $t_user_id, $t_project_id);
                    $t_date_submitted = date(config_get('complete_date_format'), $t_bug->date_submitted);
                    $t_background_color = 'background-color: ' . $t_status_color;

                    $html[] = '<table cellspacing="0" class="table motives-table"><tbody> <tr><td class="news-heading-public motives-center" width="65px" style="' . $t_background_color . '">';
                    $html[] = $forEmail ? "# $t_bug_id" : string_get_bug_view_link($t_bug_id, true);
                    $html[] = '<br/>';

                    $html[] = $forEmail
                        ? get_enum_element( 'priority', $t_bug->priority, auth_get_current_user_id(), $t_bug->project_id )
                        : icon_get_status_icon($t_bug->priority);

                    $html[] = '</td><td class="news-heading-public" style="' . $t_background_color . '"><span class="bold">' . $t_summary . '</span> - <span class="italic-small">' . $t_date_submitted . '</span></td></tr>';
                    foreach ($t_group as $t_bugnote) {

                        $t_date_submitted = format_date_submitted($t_bugnote['date_submitted']);
                        $t_user_id = VS_PRIVATE == $t_bugnote['view_state'] ? null : $t_bugnote['reporter_id'];
                        $t_user_name = $t_user_id != null ? user_get_name($t_user_id) : lang_get('private');
                        $t_user_link = $t_user_id != null && !$forEmail ? '<a href="view_user_page.php?id=' . $t_user_id . '">' . $t_user_name . '</a>' : $t_user_name;
                        $t_bonus_user_name = !empty($t_bugnote['bonus_user_id']) ? user_get_name($t_bugnote['bonus_user_id']) : lang_get('private');
                        $t_bonus_user_link = !empty($t_bugnote['bonus_user_id']) && !$forEmail ? '<a href="view_user_page.php?id=' . $t_bugnote['bonus_user_id'] . '">' . $t_bonus_user_name . '</a>' : $t_bonus_user_name;
                        $t_note = string_display_links(trim($t_bugnote['note']));
                        $t_bugnote_link = $forEmail ? '' : string_get_bugnote_view_link2($t_bugnote['bug_id'], $t_bugnote['id'], $t_user_id);
                        $t_amount = motives_format_amount($t_bugnote['amount']);
                        $amount_class = $t_bugnote['amount'] > 0 ? 'note_bonus' : 'note_fine';
                        if (!empty($t_note)) {
                            $html[] = "
                                <tr>
                                    <td align='center' style='vertical-align: top; text-align: center;'>
                                        <div class='motives-date'>$t_date_submitted</div>
                                    </td>
                                    <td style='vertical-align: top;'>
                                        <div class='motives-item'>
                                            <span class='bold'>$t_user_link</span>
                                            ($t_bugnote_link) - $t_bonus_user_link <span class='$amount_class'>$t_amount</span>
                                        </div>
                                        <div class='motives-note'>$t_note</div>
                                    </td>
                                </tr>
                            ";
                        }
                    }

                    $html[] = '</table>';
                }
            }
            $html[] = '</div></div></div></div>';
        }
    }
    return implode("\n", $html);
}
