<?php
/**
 * Plugin Name: BCKFC Tools
 * Description: Internal club tools, including Event Tickets exports for Rodeo.
 * Version: 0.1.0
 * Requires PHP: 8.2
 * Author: Conrad Sulzer
 */
defined('ABSPATH') || exit();
require_once __DIR__ . '/includes/RodeoExport.php';
require_once __DIR__ . '/includes/EventSource.php';
require_once __DIR__ . '/includes/Admin.php';
require_once __DIR__ . '/includes/ExportSettings.php';
require_once __DIR__ . '/includes/ExportController.php';
add_action('admin_menu', [BCKFC\Tools\Admin::class, 'menu']);
add_action('admin_enqueue_scripts', [BCKFC\Tools\Admin::class, 'assets']);
add_action('rest_api_init', [BCKFC\Tools\ExportController::class, 'register']);
