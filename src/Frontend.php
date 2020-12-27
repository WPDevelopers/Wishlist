<?php

namespace BetterWishlist;

// If this file is called directly,  abort.
if (!defined('ABSPATH')) {
    die;
}

class Frontend
{
    public function __construct()
    {
        include_once ABSPATH . 'wp-admin/includes/plugin.php';
        
        if (!is_plugin_active('woocommerce/woocommerce.php')) {
            return;
        }

        add_action('init', [$this, 'init']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('woocommerce_account_better-wishlist_endpoint', array($this, 'menu_content'));
        add_action('woocommerce_after_add_to_cart_button', [$this, 'single_add_to_wishlist_button'], 10);
        add_action('woocommerce_loop_add_to_cart_link', [$this, 'archive_add_to_wishlist_button'], 10, 3);

        // ajax
        add_action('wp_ajax_add_to_wishlist', [$this, 'ajax_add_to_wishlist']);
        add_action('wp_ajax_nopriv_add_to_wishlist', [$this, 'ajax_add_to_wishlist']);

        add_action('wp_ajax_remove_from_wishlist', [$this, 'ajax_remove_from_wishlist']);
        add_action('wp_ajax_nopriv_remove_from_wishlist', [$this, 'ajax_remove_from_wishlist']);

        add_action('wp_ajax_add_to_cart_single', [$this, 'ajax_add_to_cart_single']);
        add_action('wp_ajax_nopriv_add_to_cart_single', [$this, 'ajax_add_to_cart_single']);

        add_action('wp_ajax_add_to_cart_multiple', [$this, 'ajax_add_to_cart_multiple']);
        add_action('wp_ajax_nopriv_add_to_cart_multiple', [$this, 'ajax_add_to_cart_multiple']);

        // filter hooks
        add_filter('body_class', [$this, 'add_body_class']);
        add_filter('woocommerce_account_menu_items', [$this, 'add_menu']);

        // shortcode
        add_shortcode('better_wishlist', [$this, 'shortcode']);
    }

    /**
     * init
     *
     * @return void
     */
    public function init()
    {
        add_rewrite_endpoint('better-wishlist', EP_ROOT | EP_PAGES);

        // flush rewrite rules
        if (get_transient('better_wishlist_flush_rewrite_rules') === true) {
            flush_rewrite_rules();
            delete_transient('better_wishlist_flush_rewrite_rules');
        }
    }

    /**
     * enqueue_scripts
     *
     * @return void
     */
    public function enqueue_scripts()
    {
        $settings = get_option('better_wishlist_settings');
        $localize_scripts = apply_filters('better_wishlist_localize_script', [
            'ajax_url' => admin_url('admin-ajax.php', 'relative'),
            'nonce' => wp_create_nonce('better_wishlist_nonce'),
            'actions' => [
                'add_to_wishlist' => 'add_to_wishlist',
                'remove_from_wishlist' => 'remove_from_wishlist',
                'add_to_cart_multiple' => 'add_to_cart_multiple',
                'add_to_cart_single' => 'add_to_cart_single',
            ],
            'settings' => [
                'redirect_to_wishlist' => isset($settings['redirect_to_wishlist']),
                'remove_from_wishlist' => isset($settings['remove_from_wishlist']),
                'redirect_to_cart' => isset($settings['redirect_to_cart']),
                'cart_page_url' => wc_get_cart_url(),
                'wishlist_page_url' => esc_url(wc_get_account_endpoint_url('better-wishlist')),
            ],
            'strings' => [
                'added_in_cart' => __('added in cart.', 'better-wishlist'),
                'removed_from_cart' => __('removed from cart.', 'better-wishlist'),
                'added_in_wishlist' => __('added in wishlist.', 'better-wishlist'),
                'removed_from_wishlist' => __('removed from wishlist.', 'better-wishlist'),
            ],
        ]);

        // css
        wp_register_style('better-wishlist', BETTER_WISHLIST_PLUGIN_URL . 'public/assets/css/' . 'better-wishlist.css', null, BETTER_WISHLIST_PLUGIN_VERSION, 'all');

        // js
        wp_register_script('better-wishlist', BETTER_WISHLIST_PLUGIN_URL . 'public/assets/js/' . 'better-wishlist.js', ['jquery'], BETTER_WISHLIST_PLUGIN_VERSION, true);
        wp_localize_script('better-wishlist', 'BETTER_WISHLIST', $localize_scripts);

        // if woocommerce page, enqueue styles and scripts
        if (is_woocommerce()) {
            // enqueue styles
            wp_enqueue_style('better-wishlist');

            // enqueue scripts
            wp_enqueue_script('better-wishlist');
        }
    }

    /**
     * add_body_class
     *
     * @param  mixed $classes
     * @return array
     */
    public function add_body_class($classes)
    {
        if (is_page() && has_shortcode(get_the_content(), 'better_wishlist')) {
            return array_merge($classes, ['woocommerce']);
        }

        return $classes;
    }

    /**
     * add_menu
     *
     * @param  mixed $items
     * @return array
     */
    public function add_menu($items)
    {
        $items = array_splice($items, 0, count($items) - 1) + ['better-wishlist' => __('Wishlist', 'better-wishlist')] + $items;

        return $items;
    }

    /**
     * menu_content
     *
     * @return void
     */
    public function menu_content()
    {
        echo do_shortcode('[better_wishlist]');
    }

    /**
     * shortcode
     *
     * @param  array $atts
     * @param  mixed $content
     * @return string
     */
    public function shortcode($atts, $content = null)
    {
        // enqueue styles
        wp_enqueue_style('better-wishlist');

        // enqueue scripts
        wp_enqueue_script('better-wishlist');

        $atts = shortcode_atts([
            'per_page' => 5,
            'current_page' => 1,
            'pagination' => 'no',
            'layout' => '',
        ], $atts);

        $items = Plugin::instance()->model->read_list(Plugin::instance()->model->get_current_user_list());
        $products = [];

        if ($items) {
            foreach ($items as $item) {
                $product = wc_get_product($item->product_id);

                if ($product) {
                    $products[] = [
                        'id' => $product->get_id(),
                        'title' => $product->get_title(),
                        'url' => get_permalink($product->get_id()),
                        'thumbnail_url' => get_the_post_thumbnail_url($product->get_id()),
                        'stock_status' => $product->get_stock_status(),
                    ];
                }
            }
        }

        return Plugin::instance()->twig->render('page.twig', ['ids' => wp_list_pluck($products, 'id'), 'products' => $products]);
    }

    /**
     * add_to_wishlist_button
     *
     * @return string
     */
    public function add_to_wishlist_button()
    {
        global $product;

        if (!$product) {
            return;
        }

        return Plugin::instance()->twig->render('button.twig', ['product_id' => $product->get_id()]);
    }

    /**
     * single_add_to_wishlist_button
     *
     * @return void
     */
    public function single_add_to_wishlist_button()
    {
        echo $this->add_to_wishlist_button();
    }

    /**
     * archive_add_to_wishlist_button
     *
     * @param  mixed $add_to_cart_html
     * @param  mixed $product
     * @param  mixed $args
     * @return string
     */
    public function archive_add_to_wishlist_button($add_to_cart_html, $product, $args)
    {
        return $add_to_cart_html . $this->add_to_wishlist_button();
    }

    /**
     * ajax_add_to_wishlist
     *
     * @return JSON
     */
    public function ajax_add_to_wishlist()
    {
        check_ajax_referer('better_wishlist_nonce', 'security');

        if (empty($_REQUEST['product_id'])) {
            wp_send_json_error([
                'product_title' => '',
                'message' => __('Product ID is should not be empty.', 'better-wishlist'),
            ]);
        }

        $product_id = intval($_POST['product_id']);
        $wishlist_id = Plugin::instance()->model->get_current_user_list() ? Plugin::instance()->model->get_current_user_list() : Plugin::instance()->model->create_list();
        $already_in_wishlist = Plugin::instance()->model->item_in_list($product_id, $wishlist_id);

        if ($already_in_wishlist) {
            wp_send_json_error([
                'product_title' => get_the_title($product_id),
                'message' => __('already exists in wishlist.', 'better-wishlist'),
            ]);
        }

        // add to wishlist
        Plugin::instance()->model->insert_item($product_id, $wishlist_id);

        wp_send_json_success([
            'product_title' => get_the_title($product_id),
            'message' => __('added in wishlist.', 'better-wishlist'),
        ]);
    }

    /**
     * ajax_remove_from_wishlist
     *
     * @return JSON
     */
    public function ajax_remove_from_wishlist()
    {
        check_ajax_referer('better_wishlist_nonce', 'security');

        if (empty($_REQUEST['product_id'])) {
            wp_send_json_error([
                'product_title' => '',
                'message' => __('Product ID is should not be empty.', 'better-wishlist'),
            ]);
        }

        $product_id = intval($_POST['product_id']);
        $removed = Plugin::instance()->model->delete_item($product_id);

        if (!$removed) {
            wp_send_json_error([
                'product_title' => get_the_title($product_id),
                'message' => __('couldn\'t be removed.', 'better-wishlist'),
            ]);
        }

        wp_send_json_success([
            'product_title' => get_the_title($product_id),
            'message' => __('removed from wishlist.', 'better-wishlist'),
        ]);
    }

    /**
     * ajax_add_to_cart_single
     *
     * @return JSON
     */
    public function ajax_add_to_cart_single()
    {
        check_ajax_referer('better_wishlist_nonce', 'security');

        if (empty($_REQUEST['product_id'])) {
            wp_send_json_error([
                'product_title' => '',
                'message' => __('Product ID is should not be empty.', 'better-wishlist'),
            ]);
        }

        $product_id = intval($_REQUEST['product_id']);
        $product = wc_get_product($product_id);
        $settings = get_option('better_wishlist_settings');

        // add to cart
        if ($product->is_type('variable')) {
            $add_to_cart = WC()->cart->add_to_cart($product_id, 1, $product->get_default_attributes());
        } else {
            $add_to_cart = WC()->cart->add_to_cart($product_id, 1);
        }

        if ($add_to_cart) {
            if (isset($settings['remove_from_wishlist'])) {
                Plugin::instance()->model->delete_item($product_id);
            }

            wp_send_json_success([
                'product_title' => get_the_title($product_id),
                'message' => __('added in cart.', 'better-wishlist'),
            ]);
        }

        wp_send_json_error([
            'product_title' => get_the_title($product_id),
            'message' => __('couldn\'t be added in cart.', 'better-wishlist'),
        ]);
    }

    /**
     * ajax_add_to_cart_multiple
     *
     * @return JSON
     */
    public function ajax_add_to_cart_multiple()
    {
        check_ajax_referer('better_wishlist_nonce', 'security');

        if (empty($_REQUEST['products'])) {
            wp_send_json_error([
                'product_title' => '',
                'message' => __('Product ID is should not be empty.', 'better-wishlist'),
            ]);
        }

        $settings = get_option('better_wishlist_settings');

        foreach ($_REQUEST['products'] as $product_id) {
            WC()->cart->add_to_cart($product_id, 1);

            if (isset($settings['remove_from_wishlist'])) {
                Plugin::instance()->model->delete_item($product_id);
            }
        }

        wp_send_json_success([
            'product_title' => __('All items', 'better-wishlist'),
            'message' => __('added in cart.', 'better-wishlist'),
        ]);
    }
}
