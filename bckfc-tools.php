<?php
/**
 * Plugin Name: BCKFC Tools
 * Description: Internal club tools, including Event Tickets exports for Rodeo.
 * Version: 0.1.0
 * Requires PHP: 8.2
 * Author: Conrad Sulzer
 */
defined('ABSPATH') || exit();

require_once __DIR__ . '/includes/Admin.php';

add_action('admin_menu', [BCKFC\Tools\Admin::class, 'menu']);
add_action('admin_enqueue_scripts', [BCKFC\Tools\Admin::class, 'assets']);

require_once __DIR__ . '/includes/rodeo-export/RodeoExport.php';
require_once __DIR__ . '/includes/rodeo-export/EventSource.php';
require_once __DIR__ . '/includes/rodeo-export/ExportSettings.php';
require_once __DIR__ . '/includes/rodeo-export/ExportController.php';

add_action('rest_api_init', [BCKFC\Tools\RodeoExport\ExportController::class, 'register']);

require_once __DIR__ . '/includes/events/TournamentSettings.php';
require_once __DIR__ . '/includes/events/TournamentSettingsPanel.php';
require_once __DIR__ . '/includes/events/TicketSettings.php';
require_once __DIR__ . '/includes/events/TicketEditor.php';

BCKFC\Tools\Events\TournamentSettingsPanel::registerHooks();
BCKFC\Tools\Events\TicketEditor::registerHooks();

require_once __DIR__ . '/includes/events/BckfcEventLayout.php';
require_once __DIR__ . '/includes/events/EventLayoutPanel.php';

BCKFC\Tools\Events\BckfcEventLayout::registerHooks();
BCKFC\Tools\Events\EventLayoutPanel::registerHooks();

require_once __DIR__ . '/includes/events/TournamentTimeline.php';
BCKFC\Tools\Events\TournamentTimeline::registerHooks();
