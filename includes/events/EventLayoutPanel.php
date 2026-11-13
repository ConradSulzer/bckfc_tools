<?php
namespace BCKFC\Tools\Events;

final class EventLayoutPanel
{
  // add our callbacks so they alongside these tribe events 
  public static function registerHooks(): void
  {
    add_action('add_meta_boxes_tribe_events', [self::class, 'addLayoutMetaBox']);
    add_action('save_post_tribe_events', [self::class, 'saveLayoutFromRequest']);
  }

  // args order: panel id, panel title, function to populate panel, only in tribe event editors, main editor area, high in list of other panels
  public static function addLayoutMetaBox(): void
  {
    add_meta_box(
      'bckfc-event-layout',
      'BCKFC Event Layout',
      [self::class, 'renderLayoutFields'],
      'tribe_events',
      'normal',
      'high',
    );
  }

  // This is for Save Draft, Update or Publish saves. Not when using Preview/Autosave, that uses a different path
  public static function saveLayoutFromRequest(int $eventId): void
  {
    if (wp_is_post_autosave($eventId) || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)) {
      return;
    }

    $nonce = $_POST['bckfc_layout_nonce'] ?? '';

    if (
      !is_string($nonce) ||
      !wp_verify_nonce($nonce, 'bckfc_layout_' . $eventId) ||
      !current_user_can('edit_post', $eventId)
    ) {
      return;
    }

    // Check the form data to see if any of the changes belong to the bckfc event layout settings.
    // WP doesn't know about our fields so we have to handle saving them to the event.
    if (isset($_POST[BckfcEventLayout::LAYOUT_SETTINGS_KEY]) && is_array($_POST[BckfcEventLayout::LAYOUT_SETTINGS_KEY])) {
      update_post_meta(
        $eventId,
        BckfcEventLayout::LAYOUT_SETTINGS_KEY,
        BckfcEventLayout::sanitizeLayoutSettings(wp_unslash($_POST[BckfcEventLayout::LAYOUT_SETTINGS_KEY])),
      );
    }
  }

  public static function renderLayoutFields(\WP_Post $post): void
  {
    $settings = BckfcEventLayout::getLayoutSettings($post->ID);
    $tabs = [
      'layout' => 'Layout',
      'header' => 'Event Header',
      'highlights' => 'Highlights',
      'registration' => 'Registration',
    ];

    wp_nonce_field('bckfc_layout_' . $post->ID, 'bckfc_layout_nonce');
    ?>
    <div class="bckfc-event-editor">
      <div class="bckfc-event-editor-tabs" role="tablist" aria-label="Event Layout">
        <?php foreach ($tabs as $key => $label): ?>
          <?php $selected = $key === 'layout'; ?>
          <button type="button" role="tab" id="bckfc-tab-<?= esc_attr($key) ?>"
            aria-controls="bckfc-panel-<?= esc_attr($key) ?>" aria-selected="<?= $selected ? 'true' : 'false' ?>"
            tabindex="<?= $selected ? '0' : '-1' ?>">
            <?= esc_html($label) ?>
          </button>
        <?php endforeach; ?>
      </div>
      <div class="bckfc-event-editor-panels">
        <div role="tabpanel" id="bckfc-panel-layout" aria-labelledby="bckfc-tab-layout">
          <table class="form-table">
            <tbody>
              <tr>
                <th><label for="bckfc-layout-template">Event Template</label></th>
                <td>
                  <select id="bckfc-layout-template" name="_bckfc_event_layout[template]">
                    <?php
                    TournamentSettingsPanel::renderSelectOption(
                      '',
                      'Default',
                      $settings['template'],
                    );
                    TournamentSettingsPanel::renderSelectOption(
                      'bckfc',
                      'BCKFC Event',
                      $settings['template'],
                    );
                    ?>
                  </select>
                  <p class="description">BCKFC Event uses a shared header and ticket section. Edit the content between them
                    with Avada Builder or the existing editor.</p>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <div role="tabpanel" id="bckfc-panel-header" aria-labelledby="bckfc-tab-header" hidden>
          <p>The title and date come from this event’s details. Location comes from Tournament Settings → Locations →
            Fishing / Launch.</p>
          <table class="form-table">
            <tbody>
              <?php
              self::renderImageField('image', 'Featured Image', $settings['image']);
              self::renderImageField(
                'background',
                'Background Image',
                $settings['background'],
              );
              self::renderTextField('eyebrow', 'Text Above Title', $settings);
              self::renderTextField('intro', 'Introduction', $settings, true);
              self::renderTextField(
                'alert',
                'Alert Message',
                $settings,
                true,
                'Optional. Appears with a warning icon.',
              );
              ?>
            </tbody>
          </table>
        </div>
        <div role="tabpanel" id="bckfc-panel-highlights" aria-labelledby="bckfc-tab-highlights" hidden>
          <p>Registration Closes comes from Tournament Settings → General. Leave other fields blank to omit them.</p>
          <table class="form-table">
            <tbody>
              <?php
              self::renderTextField('entry_fee', 'Entry Fee', $settings, false, 'For example: $35, From $35, or Free.');
              self::renderTextField('format_label', 'Format', $settings, false, 'For example: Live Weigh-In or Catch, Photo, Submit.');
              self::renderTextField('rules_url', 'Rules Link', $settings, false, 'Enter a URL or #rules. For #rules, give your rules section the CSS ID rules in Avada Builder.');
              ?>
            </tbody>
          </table>
        </div>
        <div role="tabpanel" id="bckfc-panel-registration" aria-labelledby="bckfc-tab-registration" hidden>
          <table class="form-table">
            <tbody>
              <?php
              self::renderTextField(
                'primary_label',
                'Primary Button Text',
                $settings,
                false,
                'Defaults to Register.',
              );
              self::renderTextField(
                'primary_url',
                'Primary Button Link',
                $settings,
                false,
                'Leave empty to jump to tickets, or enter an external registration URL.',
              );
              self::renderTextField('secondary_label', 'Second Button Text', $settings);
              self::renderTextField(
                'secondary_url',
                'Second Button Link',
                $settings,
                false,
                'Optional. Use a URL or a section anchor such as #rules.',
              );
              self::renderTextField(
                'ticket_notice',
                'Ticket Notice',
                $settings,
                true,
                'Optional. Explain who should purchase tickets here.',
              ); ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
    <?php
  }

  private static function renderTextField(
    string $key,
    string $label,
    array $settings,
    bool $multiline = false,
    string $help = '',
  ): void {
    $id = 'bckfc-layout-' . $key;
    $name = BckfcEventLayout::LAYOUT_SETTINGS_KEY . '[' . $key . ']';
    ?>
    <tr>
      <th><label for="<?= esc_attr($id) ?>"><?= esc_html($label) ?></label></th>
      <td>
        <?php if ($multiline): ?>
          <textarea class="large-text" rows="3" id="<?= esc_attr($id) ?>"
            name="<?= esc_attr($name) ?>"><?= esc_textarea($settings[$key]) ?></textarea>
        <?php else: ?>
          <input class="regular-text" type="text" id="<?= esc_attr($id) ?>" name="<?= esc_attr($name) ?>"
            value="<?= esc_attr($settings[$key]) ?>">
        <?php endif; ?>

        <?php if ($help): ?>
          <p class="description"><?= esc_html($help) ?></p>
        <?php endif; ?>
      </td>
    </tr>
    <?php
  }

  private static function renderImageField(string $key, string $label, int $imageId): void
  {
    $name = BckfcEventLayout::LAYOUT_SETTINGS_KEY . '[' . $key . ']'; ?>
    <tr>
      <th><?= esc_html($label) ?></th>
      <td data-bckfc-image>
        <input type="hidden" name="<?= esc_attr($name) ?>" value="<?= esc_attr($imageId) ?>">
        <div data-bckfc-image-preview>
          <?php if ($imageId): ?>
            <?= wp_get_attachment_image($imageId, 'thumbnail') ?>
          <?php endif; ?>
        </div>
        <button type="button" class="button" data-bckfc-image-select>
          Select <?= esc_html($label) ?>
        </button>
        <button type="button" class="button-link-delete" data-bckfc-image-remove <?= !$imageId ? 'hidden' : '' ?>>
          Remove
        </button>
      </td>
    </tr>
    <?php
  }
}
