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
}
