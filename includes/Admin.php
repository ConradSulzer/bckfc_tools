<?php
namespace BCKFC\Tools;

final class Admin
{
    private static array $hooks = [];

    public static function menu(): void
    {
        self::$hooks[] = add_menu_page(
            'BCKFC Tools',
            'BCKFC Tools',
            'manage_options',
            'bckfc-tools',
            [self::class, 'render'],
            'data:image/svg+xml;base64,' .
                base64_encode(file_get_contents(dirname(__DIR__) . '/assets/pocket-knife.svg')),
            56.01, // Immediately after BCKFC Finances (56).
        );
        self::$hooks[] = add_submenu_page(
            'bckfc-tools',
            'Rodeo Angler Export',
            'Rodeo Angler Export',
            'manage_options',
            'bckfc-rodeo-export',
            [self::class, 'render'],
        );
        remove_submenu_page('bckfc-tools', 'bckfc-tools');
    }

    public static function assets(string $hook): void
    {
        if (!in_array($hook, self::$hooks, true)) {
            return;
        }

        $file = dirname(__DIR__) . '/bckfc-tools.php';

        if (!file_exists(dirname(__DIR__) . '/build/admin.js')) {
            return;
        }

        wp_enqueue_style(
            'bckfc-tools',
            plugins_url('build/admin.css', $file),
            [],
            (string) filemtime(dirname(__DIR__) . '/build/admin.css'),
        );
        wp_add_inline_style(
            'bckfc-tools',
            '
            body #wpcontent { background: #11181b; }
            body #wpbody-content { display:flex; flex-direction:column; min-height:calc(100vh - var(--wp-admin--admin-bar--height, 32px)); padding-bottom:0; float:none; }
            body #wpbody-content > .wrap { display:flex; flex-direction:column; flex:1; margin:0; }
            body #wpfooter { display:none; }
            body #wpbody-content :is(.notice, .updated, .error, .update-nag):not(#bckfc-tools-root *) { display:none !important; }
        ',
        );
        wp_enqueue_script(
            'bckfc-tools',
            plugins_url('build/admin.js', $file),
            [],
            (string) filemtime(dirname(__DIR__) . '/build/admin.js'),
            true,
        );
        wp_add_inline_script(
            'bckfc-tools',
            'window.bckfcToolsConfig = ' .
                wp_json_encode([
                    'root' => rest_url('bckfc-tools/v1/'),
                    'nonce' => wp_create_nonce('wp_rest'),
                ]) .
                ';',
            'before',
        );
    }

    public static function render(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Not permitted.', '', ['response' => 403]);
        }

        echo '<div class="wrap"><hr class="wp-header-end"><div id="bckfc-tools-root"><p>Loading BCKFC Tools…</p><noscript>JavaScript is required for Rodeo Angler Export.</noscript></div></div>';
    }
}
