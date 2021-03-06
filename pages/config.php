<?php
auth_reauthenticate();
access_ensure_global_level( config_get( 'manage_plugin_threshold' ) );

layout_page_header( plugin_lang_get( 'title' ) );
layout_page_begin();
print_manage_menu();

?>
    <div class="col-md-12 col-xs-12">
        <div class="space-10"></div>
        <div class="form-container" >

            <form action="<?php echo plugin_page( 'config_edit' ) ?>" method="post">
                <?php echo form_security_field( 'plugin_Motives_config_edit' ) ?>
                <div class="widget-box widget-color-blue2">
                    <div class="widget-header widget-header-small">
                        <h4 class="widget-title lighter">
                            <i class="ace-icon fa fa-text-width"></i>
                            <?php echo plugin_lang_get( 'title' ) . ': ' . plugin_lang_get( 'config' ) ?>
                        </h4>
                    </div>
                    <div class="widget-body">
                        <div class="widget-main no-padding">
                            <div class="table-responsive">
                                <table class="table table-bordered table-condensed table-striped">
                                    <tr <?php echo helper_alternate_class() ?>>
                                        <td class="category">
                                            <?php echo plugin_lang_get( 'lbl_show_avatar' ) ?>
                                        </td>
                                        <td class="center">
                                            <label><input type="radio" class="ace" name="show_avatar"
                                                          value="1" <?php echo (ON == plugin_config_get( 'show_avatar' )) ? 'checked="checked" ' : '' ?>/>
                                                <span class="lbl"><?php echo plugin_lang_get( 'enabled' ) ?></span></label>
                                        </td>
                                        <td class="center">
                                            <label><input type="radio" class="ace" name="show_avatar"
                                                          value="0" <?php echo (OFF == plugin_config_get( 'show_avatar' )) ? 'checked="checked" ' : '' ?>/>
                                                <span class="lbl"><?php echo plugin_lang_get( 'disabled' ) ?></span></label>
                                        </td>
                                    </tr>
                                    <tr <?php echo helper_alternate_class() ?>>
                                        <td class="category">
                                            <?php echo plugin_lang_get( 'lbl_limit_bug_notes' ) ?>
                                        </td>
                                        <td class="center" colspan="2">
                                            <label><input type="text" name="limit_bug_notes" class="input-sm" pattern="[0-9]+"
                                                          value="<?php echo(plugin_config_get( 'limit_bug_notes' )) ?>"/></label>
                                        </td>
                                    </tr>
                                    <tr <?php echo helper_alternate_class() ?>>
                                        <td class="category">
                                            <?php echo plugin_lang_get( 'lbl_day_count' ) ?>
                                        </td>
                                        <td class="center" colspan="2">
                                            <label><input type="text" name="day_count" class="input-sm" pattern="[0-9]+"
                                                          value="<?php echo(plugin_config_get( 'day_count' )) ?>"/></label>
                                        </td>
                                    </tr>
                                    <tr <?php echo helper_alternate_class() ?>>
                                        <td class="category">
                                            <?php echo plugin_lang_get( 'cron_auth_user' ) ?>
                                        </td>
                                        <td class="center" colspan="2">
                                            <label><input type="text" name="cron_user" class="input-sm"
                                                          value="<?php echo(plugin_config_get( 'cron_user' )) ?>"/></label>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                        <div class="widget-toolbox padding-8 clearfix">
                            <input type="submit" class="btn btn-primary btn-white btn-round" value="<?php echo lang_get( 'change_configuration' )?>" />
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
<?php
layout_page_end();
