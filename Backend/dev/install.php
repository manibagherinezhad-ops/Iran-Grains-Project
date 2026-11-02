<?php
// Noninteractive LOCAL installer, invoked with WP-CLI --skip-wordpress.
// Credentials arrive over stdin, never as command-line arguments or output.
$input = json_decode(file_get_contents('php://stdin'), true, 16, JSON_THROW_ON_ERROR);
if (!is_array($input) || ($input['username'] ?? '') !== 'ig_manager'
    || !is_string($input['password'] ?? null) || strlen($input['password']) < 24) {
    throw new RuntimeException('Invalid local installation input.');
}
$url = (string) ($input['site_url'] ?? '');
if (!preg_match('~^http://localhost:[0-9]{2,5}$~', $url)) throw new RuntimeException('Only a localhost installation is allowed.');
define('WP_INSTALLING', true);
define('WP_SITEURL', $url);
define('WP_HOME', $url);
require '/var/www/html/wp-load.php';
if (wp_get_environment_type() !== 'local') throw new RuntimeException('Local installation only.');
add_filter('pre_wp_mail', '__return_false');
require_once ABSPATH . 'wp-admin/includes/upgrade.php';
if (!is_blog_installed()) {
    $result = wp_install('انجمن غلات ایران', $input['username'], 'manager@example.invalid', false, '', $input['password']);
    if (is_wp_error($result)) throw new RuntimeException('Local WordPress installation failed.');
    update_option('home', $url);
    update_option('siteurl', $url);
    echo "Local WordPress installed successfully.\n";
} elseif (!empty($input['reset'])) {
    $user = get_user_by('login', $input['username']);
    if (!$user || !user_can($user, 'manage_options')) throw new RuntimeException('The expected local manager account was not found.');
    $result = wp_update_user(['ID' => $user->ID, 'user_pass' => $input['password']]);
    if (is_wp_error($result)) throw new RuntimeException('The local password could not be reset.');
    echo "Local manager password reset.\n";
} else {
    throw new RuntimeException('WordPress is already installed; no account changes were made.');
}
