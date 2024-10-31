<?php
/**
 * Plugin Name:   Plugin Install Notifier
 * Plugin URI:    https://wisnet.com
 * Description:   Automatically alert your developer when you install/update/remove a plugin.
 * Version:       1.1.0
 * Author:        Michael Dahlke
 * Author URI:    https://www.wisnet.com
 */

define('PIN_OPTION_EMAIL_KEY', 'pin_email');

function pin_send_plugin_notice($plugin, $action = 'activated') {
    $thePlugin = get_plugin_data(WP_PLUGIN_DIR . '/' . $plugin);
    $urlparts = parse_url(site_url());
    $domain = str_replace('www.', '', $urlparts['host']);
    $user = wp_get_current_user();

    ob_start();
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            .section {
                margin-bottom: 40px;
            }

            .footer {
                margin-top: 30px;
                text-align: center;
                color: #666;
            }
        </style>
    </head>
    <body>
    <div class="section">
        <p>The following plugin was recently <strong><?= $action; ?></strong>:</p>
        <strong><?= $thePlugin['Name']; ?></strong>
        <ul style="list-style: none;">
            <li>URL: <a href="<?= $thePlugin['PluginURI']; ?>"><?= $thePlugin['PluginURI']; ?></a></li>
            <li>Version: <?= $thePlugin['Version']; ?></li>
            <li>Author: <?= $thePlugin['Author']; ?></li>
            <li>Description: <?= $thePlugin['Description']; ?></li>
        </ul>

        <p>These actions were performed by:</p>
        <ul>
            <li>Name: <?= $user->display_name; ?></li>
            <li>Email: <?= $user->user_email; ?></li>
            <li>Link: <?= get_edit_user_link($user->ID); ?></li>
        </ul>
    </div>
    <div class="section">
        <a href="<?= admin_url(); ?>open .plugins.php">View all plugins</a>
    </div>
    <div class="footer">
        <p>Plugin notification brought you to by <a href="https://www.wisnet.com">wisnet.com</a></p>
    </div>
    </body>
    </html>
    <?php
    $subject = mb_convert_encoding(get_bloginfo('name'), 'UTF-8', 'HTML-ENTITIES') . ': Plugin ' . ucwords($action);
    $body = ob_get_clean();
    $headers = array('Content-Type: text/html; charset=UTF-8');
    $headers[] .= 'From: ' . mb_convert_encoding(get_bloginfo('name'), 'UTF-8', 'HTML-ENTITIES') . ' <no-reply@'. $domain .'>';

    wp_mail(pin_getEmail(), $subject, $body, $headers);
}

add_action('activated_plugin', function ($plugin, $network_activation) {
    pin_send_plugin_notice($plugin, 'activated');
}, 10, 2);

add_action('upgrader_process_complete', function ($upgrader_object, $options) {

    if ($options['action'] === 'update' && $options['type'] === 'plugin') {
        foreach ($options['plugins'] as $p) {
            pin_send_plugin_notice($p, 'upgraded');
        }
    }
}, 10, 2);

add_action('deactivated_plugin', function ($plugin, $network_activation) {
    pin_send_plugin_notice($plugin, 'deactivated');
}, 10, 2);

add_action('admin_menu', function () {
    add_submenu_page(
        'tools.php',
        'Plugin Notifier',
        'Plugin Notifier',
        'manage_options',
        'plugin-notifier',
        'plugin_notifier_page'
    );
});

function plugin_notifier_page() {
    $email = filter_input(INPUT_POST, 'pin_email', FILTER_VALIDATE_EMAIL);

    if ($email) {
        $currentEmail = get_option(PIN_OPTION_EMAIL_KEY);
        $updated = update_option(PIN_OPTION_EMAIL_KEY, $email);

        if ($updated) {
            ?>
            <div class="notice notice-success is-dismissible">
                <p>Email updated.</p>
            </div>
            <?php
        }
        elseif ($email === $currentEmail) {
            ?>
            <div class="notice notice-warning is-dismissible">
                <p>There's nothing to update you silly goose.</p>
            </div>
            <?php
        }
        else {
            ?>
            <div class="notice notice-error is-dismissible">
                <p>An error occurred.</p>
            </div>
            <?php
        }
    }

    $email = pin_getEmail();
    ?>
    <div id="poststuff">
        <div id="post-body">
            <form id="add-edit-client" method="post" action="<?= $_SERVER["REQUEST_URI"]; ?>">
                <div class="postbox">
                    <h3 class="hndle">Notification Settings</h3>
                    <div class="inside">

                        <?php wp_nonce_field('client_add_edit_nonce_action', 'client_add_edit_nonce_val') ?>
                        <table class="form-table">
                            <tr valign="top">
                                <th scope="row"><label for="pin_email">Send notification to:</label></th>
                                <td>
                                    <input name="pin_email" type="text" id="pin_email"
                                           value="<?= esc_attr($email); ?>">
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <div class="form-group">
                    <button class="button" type="submit" name="add-update" value="go">Save</button>
                </div>
            </form>
        </div>
    </div>
    <?php
}

function pin_getEmail() {

    $email = get_option(PIN_OPTION_EMAIL_KEY);

    if (!$email) {
        $email = get_bloginfo('admin_email');
    }

    return $email;
}