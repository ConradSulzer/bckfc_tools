<?php
namespace BCKFC\Tools\Events;

final class EventSettingsPanel
{
  public static function registerHooks(): void
  {
    add_action('add_meta_boxes_tribe_events', [self::class, 'addEventSettingsMetaBox']);
  }

  // args order: panel id, panel title, function to populate panel, only in tribe event editors, main editor area, high in list of other panels
  public static function addEventSettingsMetaBox(): void
  {
    add_meta_box(
      'bckfc-event-settings',
      'BCKFC Event Settings',
      [self::class, 'renderEventSettings'],
      'tribe_events',
      'normal',
      'high',
    );
  }

  public static function renderEventSettings(\WP_Post $post): void
  {
    $tabs = [
      'general' => 'General',
      'locations' => 'Locations',
      'times' => 'Times',
      'registration' => 'Registration',
    ]; ?>
    <div class="bckfc-event-editor">
      <div class="bckfc-event-editor-tabs" role="tablist" aria-label="BCKFC Event Settings">
        <?php foreach ($tabs as $key => $label): ?>
          <?php $selected = $key === 'general'; ?>
          <button type="button" role="tab" id="bckfc-tab-<?= esc_attr($key) ?>"
            aria-controls="bckfc-panel-<?= esc_attr($key) ?>" aria-selected="<?= $selected
                ? 'true'
                : 'false' ?>" tabindex="<?= $selected ? '0' : '-1' ?>">
            <?= esc_html($label) ?>
          </button>
        <?php endforeach; ?>
      </div>
      <div class="bckfc-event-editor-panels">
        <div role="tabpanel" id="bckfc-panel-general" aria-labelledby="bckfc-tab-general">
          <?php TournamentSettingsEditor::renderTournamentGeneralFields($post); ?>
        </div>
        <div role="tabpanel" id="bckfc-panel-locations" aria-labelledby="bckfc-tab-locations" hidden>
          <?php TournamentSettingsEditor::renderTournamentLocations($post->ID); ?>
        </div>
        <div role="tabpanel" id="bckfc-panel-times" aria-labelledby="bckfc-tab-times" hidden>
          <?php TournamentSettingsEditor::renderTournamentTimes($post->ID); ?>
        </div>
        <div role="tabpanel" id="bckfc-panel-registration" aria-labelledby="bckfc-tab-registration" hidden>
          <table class="form-table">
            <tr>
              <th>Require Login</th>
              <td>
                <input type="hidden" name="bckfc_event[require_login]" value="0">
                <label for="bckfc-require-login">
                  <input type="checkbox" id="bckfc-require-login" name="bckfc_event[require_login]" value="1"
                    <?= checked(EventLoginRequirement::requiresLogin($post->ID), true, false) ?>>
                  Require Login to Purchase Tickets
                </label>
                <p class="description">Applies to all tickets for this event.</p>
              </td>
            </tr>
          </table>

        </div>
      </div>
    </div>
    <?php
  }
}
