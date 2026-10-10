<?php
/**
 * Plugin Name: HDS Mantenimiento
 * Description: Activa o desactiva la página "Sitio en mantenimiento" de hielosdelsur.cl (sitio Astro en la raíz) con un clic. Crea/borra el archivo mantenimiento.on en la raíz, que el .htaccess detecta.
 * Version: 1.0.0
 * Author: DiseñoPaginas.cl
 */
if (!defined('ABSPATH')) exit;

function hds_mant_root() {
    // WordPress vive en <raiz>/admin/ -> la raíz del sitio es el directorio padre
    return dirname(untrailingslashit(ABSPATH));
}
function hds_mant_flag() { return hds_mant_root() . '/mantenimiento.on'; }
function hds_mant_active() { return file_exists(hds_mant_flag()); }
function hds_mant_page_ok() { return file_exists(hds_mant_root() . '/mantenimiento.html'); }
function hds_mant_rule_ok() {
    $f = hds_mant_root() . '/.htaccess';
    return file_exists($f) && strpos((string) @file_get_contents($f), 'mantenimiento.on') !== false;
}

function hds_mant_set($on) {
    if ($on) {
        return false !== @file_put_contents(hds_mant_flag(), 'activo ' . gmdate('c') . "\n");
    }
    return !file_exists(hds_mant_flag()) || @unlink(hds_mant_flag());
}

// Cookie para que el admin logueado vea el sitio real mientras el mantenimiento está activo
add_action('init', function () {
    if (is_user_logged_in() && current_user_can('manage_options') && hds_mant_active()) {
        if (empty($_COOKIE['hds_preview'])) {
            setcookie('hds_preview', '1', time() + 12 * HOUR_IN_SECONDS, '/', '', true, true);
        }
    }
});

function hds_mant_handle_toggle() {
    if (!current_user_can('manage_options')) wp_die('Sin permisos');
    check_admin_referer('hds_mant_toggle');
    $want = isset($_REQUEST['estado']) && $_REQUEST['estado'] === 'on';
    $ok = hds_mant_set($want);
    if (!$want) { setcookie('hds_preview', '', time() - 3600, '/', '', true, true); }
    $back = wp_get_referer() ?: admin_url('admin.php?page=hds-mantenimiento');
    wp_safe_redirect(add_query_arg('hds_res', $ok ? ($want ? 'on' : 'off') : 'err', $back));
    exit;
}
add_action('admin_post_hds_mant_toggle', 'hds_mant_handle_toggle');

add_action('admin_menu', function () {
    add_menu_page('Mantenimiento', 'Mantenimiento', 'manage_options', 'hds-mantenimiento', 'hds_mant_render', 'dashicons-hammer', 3);
});

function hds_mant_url($estado) {
    return wp_nonce_url(admin_url('admin-post.php?action=hds_mant_toggle&estado=' . $estado), 'hds_mant_toggle');
}

function hds_mant_render() {
    $on = hds_mant_active();
    $res = isset($_GET['hds_res']) ? sanitize_key($_GET['hds_res']) : '';
    echo '<div class="wrap"><h1>Modo mantenimiento — hielosdelsur.cl</h1>';
    if ($res === 'on')  echo '<div class="notice notice-warning is-dismissible"><p>Mantenimiento <strong>ACTIVADO</strong>. Los visitantes ven la página de mantenimiento.</p></div>';
    if ($res === 'off') echo '<div class="notice notice-success is-dismissible"><p>Mantenimiento <strong>DESACTIVADO</strong>. El sitio está online.</p></div>';
    if ($res === 'err') echo '<div class="notice notice-error"><p>No se pudo escribir en la carpeta raíz del sitio (' . esc_html(hds_mant_root()) . '). Revisa permisos.</p></div>';
    if (!hds_mant_page_ok()) echo '<div class="notice notice-error"><p>Falta <code>mantenimiento.html</code> en la raíz. Haz el deploy del sitio primero.</p></div>';
    if (!hds_mant_rule_ok()) echo '<div class="notice notice-error"><p>El <code>.htaccess</code> de la raíz no tiene la regla de mantenimiento. Haz el deploy del sitio primero.</p></div>';

    echo '<div style="background:#fff;border:1px solid #c3c4c7;padding:24px;max-width:560px;margin-top:16px">';
    if ($on) {
        echo '<p style="font-size:16px"><span style="display:inline-block;width:12px;height:12px;border-radius:50%;background:#d63638;margin-right:8px"></span><strong>Estado: ACTIVO</strong> — los visitantes ven "Sitio en mantenimiento".</p>';
        echo '<p><a class="button button-primary button-hero" href="' . esc_url(hds_mant_url('off')) . '">Desactivar mantenimiento (poner sitio online)</a></p>';
    } else {
        echo '<p style="font-size:16px"><span style="display:inline-block;width:12px;height:12px;border-radius:50%;background:#00a32a;margin-right:8px"></span><strong>Estado: INACTIVO</strong> — el sitio está online.</p>';
        echo '<p><a class="button button-primary button-hero" href="' . esc_url(hds_mant_url('on')) . '" onclick="return confirm(\'¿Activar el modo mantenimiento? Los visitantes dejarán de ver el sitio.\')">Activar mantenimiento</a></p>';
    }
    echo '<hr><p class="description">Tú (logueado como administrador) sigues viendo el sitio normal mientras está activo. Los visitantes reciben la página de mantenimiento con código 503, lo que evita que Google desindexe el sitio.</p>';
    echo '<p><a href="' . esc_url(home_url('/')) . '" target="_blank">Ver sitio</a> · <a href="https://hielosdelsur.cl/mantenimiento.html" target="_blank">Ver página de mantenimiento</a></p>';
    echo '</div></div>';
}

// Acceso rápido en la barra superior
add_action('admin_bar_menu', function ($bar) {
    if (!current_user_can('manage_options')) return;
    $on = hds_mant_active();
    $bar->add_node([
        'id'    => 'hds-mant',
        'title' => $on ? '<span style="background:#d63638;color:#fff;padding:2px 8px;border-radius:3px">Mantenimiento: ACTIVO</span>' : 'Mantenimiento: off',
        'href'  => admin_url('admin.php?page=hds-mantenimiento'),
    ]);
}, 100);
