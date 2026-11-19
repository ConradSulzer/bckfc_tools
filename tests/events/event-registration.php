<?php
/** Read-only integration checks: bootstrap local WordPress, then require this file. */
use BCKFC\Tools\Events\EventLoginRequirement;

if (!defined('ABSPATH') || wp_get_environment_type() !== 'local') {
    throw new RuntimeException('This check requires the local WordPress site.');
}

$event = get_posts(['post_type' => 'tribe_events', 'posts_per_page' => 1])[0];
$productId = get_posts(['post_type' => 'product', 'posts_per_page' => 1, 'fields' => 'ids'])[0];
$requireLogin = true;
$originalUser = get_current_user_id();
$originalSession = WC()->session;
$originalCart = WC()->cart;

// Only override reads and in-memory session/cart state. No records are saved.
$metadata = static function ($value, $id, $key) use ($event, $productId, &$requireLogin) {
    if ($id === $event->ID && $key === '_bckfc_require_login') {
        return [$requireLogin ? 1 : 0];
    }

    if ($id === $productId && $key === '_tribe_wooticket_for_event') {
        return [$event->ID];
    }

    return $value;
};
$check = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

add_filter('get_post_metadata', $metadata, 10, 3);
WC()->session = new class extends WC_Session {};
WC()->cart = new class ($productId) {
    public function __construct(private int $productId) {}

    public function get_cart(): array
    {
        return [['product_id' => $this->productId]];
    }
};

try {
    wp_set_current_user(0);
    $check(
        !EventLoginRequirement::validateTicketAddToCart(true, $productId, 1),
        'Guest add must fail.',
    );
    $check(wc_notice_count('error') === 1, 'Guest needs a login notice.');
    wc_clear_notices();

    EventLoginRequirement::validateCartLogin();
    $check(wc_notice_count('error') === 1, 'Guest checkout must fail with an existing cart.');
    wc_clear_notices();

    $order = new WC_Order();
    $item = new WC_Order_Item_Product();
    $item->set_product_id($productId);
    $order->add_item($item);
    EventLoginRequirement::validateOrderPaymentLogin($order);
    $check(wc_notice_count('error') === 1, 'Existing-order payment must require login.');
    wc_clear_notices();

    $template = new Tribe__Tickets__Editor__Template();
    $template->set('post_id', $event->ID);
    $picker = '<form>Native picker</form>';
    $render = static fn() => apply_filters(
        'tribe_template_html:tickets/v2/tickets',
        $picker,
        '',
        ['v2', 'tickets'],
        $template,
    );
    $check(str_contains($render(), 'inert aria-hidden="true"'), 'Guest picker must be inert.');
    $check(str_contains($render(), 'redirect_to='), 'Login must have a return destination.');

    $userId = get_users(['number' => 1, 'fields' => 'ID'])[0];
    wp_set_current_user($userId);
    $check(
        EventLoginRequirement::validateTicketAddToCart(true, $productId, 1),
        'Logged-in add must pass.',
    );
    $check(
        !EventLoginRequirement::validateTicketAddToCart(false, $productId, 1),
        'Preserve other validation failures.',
    );
    EventLoginRequirement::validateCartLogin();
    $check(wc_notice_count('error') === 0, 'Logged-in cart must pass.');
    $check(!str_contains($render(), 'inert'), 'Logged-in picker must stay interactive.');

    wp_set_current_user(0);
    $requireLogin = false;
    $check(
        EventLoginRequirement::validateTicketAddToCart(true, $productId, 1),
        'Opted-out event must allow guest add.',
    );
    EventLoginRequirement::validateCartLogin();
    $check(wc_notice_count('error') === 0, 'Opted-out cart must be unchanged.');
    $check($render() === $picker, 'Opted-out markup must be unchanged.');

    echo "Event registration read-only checks passed.\n";
} finally {
    remove_filter('get_post_metadata', $metadata, 10);
    wp_set_current_user($originalUser);
    WC()->session = $originalSession;
    WC()->cart = $originalCart;
}
