<?php
namespace BCKFC\Tools\Events;

/** Event login requirements; WooCommerce still owns purchases and quantities. */
final class EventLoginRequirement
{
  public static function registerHooks(): void
  {
    // runs on cart add for a given product
    add_filter(
      'woocommerce_add_to_cart_validation',
      [self::class, 'validateTicketAddToCart'],
      10,
      3,
    );

    // runs when validating the cart, including during checkout
    add_action('woocommerce_check_cart_items', [self::class, 'validateCartLogin']);

    // runs when paying for an item which can bypass the cart 
    add_action('woocommerce_before_pay_action', [self::class, 'validateOrderPaymentLogin']);

    // runs after tribe renders the ticket picker, we wrap and blur the picker if not logged in
    add_filter(
      'tribe_template_html:tickets/v2/tickets',
      [self::class, 'renderTicketLoginOverlay'],
      10,
      4,
    );

    // onyl load the CSS if the pages requires login
    add_action('wp_enqueue_scripts', [self::class, 'enqueueRegistrationStyles']);
  }

  public static function requiresLogin(int $eventId): bool
  {
    return (bool) get_post_meta($eventId, '_bckfc_require_login', true);
  }

  private static function getLoginRequiredEvent(int $productId): int
  {
    $eventId = (int) get_post_meta($productId, '_tribe_wooticket_for_event', true);

    return $eventId && self::requiresLogin($eventId) ? $eventId : 0;
  }

  private static function addLoginNotice(string $returnUrl): void
  {
    $message =
      'Please <a href="' .
      esc_url(wp_login_url($returnUrl)) .
      '">log in</a> to purchase tickets for this event.';

    if (!wc_has_notice($message, 'error')) {
      wc_add_notice($message, 'error');
    }
  }

  public static function validateTicketAddToCart(bool $passed, int $productId, $quantity): bool
  {
    if (!$passed || is_user_logged_in()) {
      return $passed;
    }

    $eventId = self::getLoginRequiredEvent($productId);

    if (!$eventId) {
      return $passed;
    }

    self::addLoginNotice(get_permalink($eventId));

    return false;
  }

  public static function validateCartLogin(): void
  {
    if (is_user_logged_in()) {
      return;
    }

    foreach (WC()->cart->get_cart() as $item) {
      if (self::getLoginRequiredEvent((int) $item['product_id'])) {
        self::addLoginNotice(wc_get_checkout_url());

        return;
      }
    }
  }

  // Paying an existing order bypasses cart validation, so check that route too.
  public static function validateOrderPaymentLogin(\WC_Order $order): void
  {
    if (is_user_logged_in()) {
      return;
    }

    foreach ($order->get_items() as $item) {
      if (self::getLoginRequiredEvent($item->get_product_id())) {
        self::addLoginNotice($order->get_checkout_payment_url());

        return;
      }
    }
  }

  public static function renderTicketLoginOverlay(
    string $html,
    $file,
    array $name,
    $template,
  ): string {
    $eventId = (int) $template->get('post_id');

    if ($html === '' || !self::requiresLogin($eventId)) {
      return $html;
    }

    $instructions =
      '<p class="bckfc-registration-instructions">Each angler must be logged in and purchase their own tickets for this event.</p>';

    if (is_user_logged_in()) {
      return $instructions . $html;
    }

    $loginUrl = wp_login_url(get_permalink($eventId));

    return '<div class="bckfc bckfc-registration-gate">' .
      '<div class="bckfc-registration-gate__picker" inert aria-hidden="true">' .
      $html .
      '</div>' .
      '<div class="bckfc-registration-gate__prompt"><div>' .
      $instructions .
      '<a class="bckfc-button" href="' .
      esc_url($loginUrl) .
      '">Log In to Purchase Tickets</a>' .
      '</div></div></div>';
  }

  public static function enqueueRegistrationStyles(): void
  {
    if (!is_singular('tribe_events') || !self::requiresLogin(get_queried_object_id())) {
      return;
    }

    $root = dirname(__DIR__, 2);

    foreach (['design-tokens', 'buttons', 'events/event-registration'] as $asset) {
      $handle = 'bckfc-' . basename($asset);

      wp_enqueue_style(
        $handle,
        plugins_url('assets/' . $asset . '.css', $root . '/bckfc-tools.php'),
        [],
        (string) filemtime($root . '/assets/' . $asset . '.css'),
      );
    }
  }
}
