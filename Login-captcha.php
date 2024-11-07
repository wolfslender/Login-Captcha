<?php
/*
Plugin Name: Login Captcha
Plugin URI: https://www.olivero.com/
Description: Añade reCAPTCHA v2 al formulario de login de WordPress
Version: 1.0
Author: Alexis Olivero
Author URI: https://www.olivero.com/
*/

if (!defined('ABSPATH')) {
    exit;
}

// Añadir menú de configuración
add_action('admin_menu', 'lc_add_admin_menu');
function lc_add_admin_menu() {
    add_options_page(
        'Login Captcha',
        'Login Captcha',
        'manage_options',
        'login-captcha',
        'lc_options_page'
    );
}

// Registrar configuraciones
add_action('admin_init', 'lc_settings_init');
function lc_settings_init() {
    register_setting('login_captcha', 'lc_settings');
    
    add_settings_section(
        'lc_section',
        'Configuración de reCAPTCHA v2',
        'lc_section_callback',
        'login-captcha'
    );

    add_settings_field(
        'site_key',
        'Clave del sitio',
        'lc_site_key_render',
        'login-captcha',
        'lc_section'
    );

    add_settings_field(
        'secret_key',
        'Clave secreta',
        'lc_secret_key_render',
        'login-captcha',
        'lc_section'
    );
}

// Funciones de renderizado
function lc_section_callback() {
    echo 'Ingresa tus credenciales de reCAPTCHA v2';
}

function lc_site_key_render() {
    $options = get_option('lc_settings');
    ?>
    <input type='text' name='lc_settings[site_key]' value='<?php echo isset($options['site_key']) ? esc_attr($options['site_key']) : ''; ?>' style="width: 300px;">
    <?php
}

function lc_secret_key_render() {
    $options = get_option('lc_settings');
    ?>
    <input type='text' name='lc_settings[secret_key]' value='<?php echo isset($options['secret_key']) ? esc_attr($options['secret_key']) : ''; ?>' style="width: 300px;">
    <?php
}

// Página de opciones
function lc_options_page() {
    ?>
    <div class="wrap">
        <h2>Login Captcha (reCAPTCHA v2)</h2>
        <form action='options.php' method='post'>
            <?php
            settings_fields('login_captcha');
            do_settings_sections('login-captcha');
            submit_button();
            ?>
        </form>
    </div>
    <?php
}

// Añadir captcha al formulario de login
add_action('login_enqueue_scripts', 'lc_enqueue_recaptcha');
function lc_enqueue_recaptcha() {
    $options = get_option('lc_settings');
    if (empty($options['site_key'])) return;
    
    wp_enqueue_script('recaptcha', 'https://www.google.com/recaptcha/api.js');
}

// Añadir el widget de reCAPTCHA al formulario
add_action('login_form', 'lc_add_captcha_field');
function lc_add_captcha_field() {
    $options = get_option('lc_settings');
    if (empty($options['site_key'])) return;
    
    ?>
    <div style="margin-bottom: 10px;">
        <div class="g-recaptcha" data-sitekey="<?php echo esc_attr($options['site_key']); ?>"></div>
    </div>
    <?php
}

// Verificar captcha durante el login
add_filter('authenticate', 'lc_verify_captcha', 10, 3);
function lc_verify_captcha($user, $username, $password) {
    if (empty($username) || empty($password)) {
        return $user;
    }

    $options = get_option('lc_settings');
    if (empty($options['secret_key'])) {
        return $user;
    }

    if (!isset($_POST['g-recaptcha-response'])) {
        return new WP_Error('captcha_error', 'Por favor, completa el captcha.');
    }

    $captcha_response = $_POST['g-recaptcha-response'];
    $verify_url = 'https://www.google.com/recaptcha/api/siteverify';
    $response = wp_remote_post($verify_url, array(
        'body' => array(
            'secret' => $options['secret_key'],
            'response' => $captcha_response,
            'remoteip' => $_SERVER['REMOTE_ADDR']
        )
    ));

    if (is_wp_error($response)) {
        return new WP_Error('captcha_error', 'Error al verificar el captcha.');
    }

    $body = wp_remote_retrieve_body($response);
    $result = json_decode($body, true);

    if (!$result['success']) {
        return new WP_Error('captcha_error', 'Verificación de captcha fallida. Por favor, inténtalo de nuevo.');
    }

    return $user;
}