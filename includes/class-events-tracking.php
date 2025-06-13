<?php
/**
 * Classe per il tracking degli eventi ecommerce TikTok
 *
 * @package TikTokTrackingPro
 * @author Adelmo Infante
 */

// Impedisce accesso diretto
if (!defined('ABSPATH')) {
    exit;
}

class TTP_Events_Tracking {
    
    /**
     * Costruttore
     */
    public function __construct() {
        // Solo se il tracking è abilitato e configurato
        if (!get_option('ttp_enabled', true) || !TTP_Pixel_Core::is_pixel_ready()) {
            return;
        }
        
        // Hook per gli eventi
        add_action('wp_footer', array($this, 'track_view_content'));
        add_action('wp_footer', array($this, 'track_add_to_cart_searchanise'));
        add_action('wp_footer', array($this, 'track_single_product_add_to_cart'));
        add_action('wp_footer', array($this, 'track_search_events'));
        add_action('woocommerce_after_checkout_form', array($this, 'track_initiate_checkout'));
        add_action('woocommerce_thankyou', array($this, 'track_purchase'));
        
        // AJAX handlers
        add_action('wp_ajax_ttp_track_add_to_cart_server', array($this, 'handle_add_to_cart_server'));
        add_action('wp_ajax_nopriv_ttp_track_add_to_cart_server', array($this, 'handle_add_to_cart_server'));
        add_action('wp_ajax_ttp_track_view_content_server', array($this, 'handle_view_content_server'));
        add_action('wp_ajax_nopriv_ttp_track_view_content_server', array($this, 'handle_view_content_server'));
        add_action('wp_ajax_ttp_track_search_server', array($this, 'handle_search_server'));
        add_action('wp_ajax_nopriv_ttp_track_search_server', array($this, 'handle_search_server'));
        add_action('wp_ajax_ttp_track_checkout_server', array($this, 'handle_checkout_server'));
        add_action('wp_ajax_nopriv_ttp_track_checkout_server', array($this, 'handle_checkout_server'));
        
        // Ottimizzazioni
        add_filter('woocommerce_loop_add_to_cart_link', array($this, 'add_product_data_attributes'), 10, 2);
    }
    
    /**
     * Traccia ViewContent sulle pagine prodotto
     */
    public function track_view_content() {
        if (!is_product()) return;
        
        global $product;
        if (!$product) return;
        
        $product_id = $product->get_id();
        $content_name = $product->get_name();
        $price = $product->get_price() ?: 0;
        $currency = get_woocommerce_currency();
        
        $categories = wp_get_post_terms($product_id, 'product_cat');
        $content_category = '';
        if (!empty($categories) && !is_wp_error($categories)) {
            $content_category = $categories[0]->name;
        }
        
        ?>
        <script>
        // TikTok ViewContent - CLEAN VERSION
        (function() {
            var productData = {
                id: '<?php echo $product_id; ?>',
                name: '<?php echo esc_js($content_name); ?>',
                price: <?php echo floatval($price); ?>,
                currency: '<?php echo $currency; ?>',
                category: '<?php echo esc_js($content_category); ?>'
            };
            
            function sendViewContent() {
                console.log('🔄 TikTok ViewContent - Real Product');
                console.log('Product data:', productData);
                
                if (typeof ttq === 'undefined' || typeof ttq.track !== 'function') {
                    console.log('⏳ TikTok not ready, retrying...');
                    setTimeout(sendViewContent, 500);
                    return;
                }
                
                try {
                    var eventData = {
                        contents: [{
                            content_id: productData.id,
                            content_type: 'product',
                            content_name: productData.name
                        }],
                        value: productData.price,
                        currency: productData.currency,
                        content_type: 'product'
                    };
                    
                    console.log('📤 Sending TikTok ViewContent:', eventData);
                    ttq.track('ViewContent', eventData);
                    console.log('✅ TikTok ViewContent sent successfully');
                    
                    // Server-side backup
                    if (typeof jQuery !== 'undefined') {
                        jQuery.post('<?php echo admin_url('admin-ajax.php'); ?>', {
                            action: 'ttp_track_view_content_server',
                            product_id: productData.id,
                            product_price: productData.price,
                            product_name: productData.name,
                            product_category: productData.category,
                            nonce: '<?php echo wp_create_nonce('ttp_tracking_nonce'); ?>'
                        });
                    }
                    
                } catch (error) {
                    console.error('❌ TikTok ViewContent error:', error);
                    setTimeout(sendViewContent, 1000);
                }
            }
            
            // Avvia dopo 4 secondi
            setTimeout(sendViewContent, 4000);
            
        })();
        </script>
        <?php
        
        // Server-side tracking immediato
        TTP_API_Server::send_event('ViewContent', array(
            'content_ids' => array($product_id),
            'content_name' => $content_name,
            'content_category' => $content_category,
            'content_type' => 'product',
            'value' => floatval($price),
            'currency' => $currency
        ));
    }
    
    /**
     * Traccia AddToCart per Searchanise
     */
    public function track_add_to_cart_searchanise() {
        if (!(is_shop() || is_product_category() || is_product_tag() || is_search() || (isset($_GET['se']) && $_GET['se']))) {
            return;
        }
        
        ?>
        <script>
        jQuery(document).ready(function($) {
            var originalXHR = window.XMLHttpRequest;
            var originalOpen = originalXHR.prototype.open;
            var originalSend = originalXHR.prototype.send;
            
            if (!window.ttpSearchaniseIntercepted) {
                window.ttpSearchaniseIntercepted = true;
                
                originalXHR.prototype.open = function(method, url, async, user, password) {
                    this._url = url;
                    this._method = method;
                    return originalOpen.apply(this, arguments);
                };
                
                originalXHR.prototype.send = function(data) {
                    var self = this;
                    
                    var isSearchaniseAddToCart = this._url && (
                        (this._url.includes('se_ajax_add_to_cart') && this._url.includes('product_id=')) ||
                        this._url.includes('snize') ||
                        this._url.includes('searchanise')
                    ) && 
                    !(this._url && (
                        this._url.includes('get_refreshed_fragments') ||
                        this._url.includes('search-results') ||
                        this._url.includes('se_get_results')
                    ));
                    
                    if (isSearchaniseAddToCart) {
                        try {
                            var product_id = null;
                            var quantity = 1;
                            
                            var urlPatterns = [
                                /[?&]product_id=(\d+)/i,
                                /se_ajax_add_to_cart.*product_id[=:](\d+)/i
                            ];
                            
                            for (var i = 0; i < urlPatterns.length && !product_id; i++) {
                                var match = this._url.match(urlPatterns[i]);
                                if (match && match[1]) {
                                    product_id = match[1];
                                    break;
                                }
                            }
                            
                            var qtyMatch = this._url.match(/[?&](?:quantity|qty)=(\d+)/i);
                            if (qtyMatch && qtyMatch[1]) {
                                quantity = parseInt(qtyMatch[1]);
                            }
                            
                            if (product_id) {
                                self.addEventListener('load', function() {
                                    if (self.status === 200) {
                                        var product_name = '';
                                        var product_price = 0;
                                        
                                        var selectors = [
                                            '#snize-product-' + product_id,
                                            '[data-original-product-id="' + product_id + '"]',
                                            '[data-snize-product-id="' + product_id + '"]',
                                            '.snize-product[data-id="' + product_id + '"]',
                                            '[data-product-id="' + product_id + '"]'
                                        ];
                                        
                                        var $productElement = null;
                                        for (var k = 0; k < selectors.length && !$productElement; k++) {
                                            var elements = $(selectors[k]);
                                            if (elements.length > 0) {
                                                $productElement = elements.first();
                                                break;
                                            }
                                        }
                                        
                                        if ($productElement && $productElement.length > 0) {
                                            var nameElement = $productElement.find('.snize-title, .snize-product-title, .product-title').first();
                                            if (nameElement.length > 0) {
                                                product_name = nameElement.text().trim();
                                            }
                                            
                                            var priceElement = $productElement.find('.snize-price, .price .amount').first();
                                            if (priceElement.length > 0) {
                                                var priceText = priceElement.text().trim();
                                                
                                                if (priceText) {
                                                    var priceMatch = priceText.match(/[\d.,]+/);
                                                    if (priceMatch) {
                                                        var priceString = priceMatch[0];
                                                        
                                                        if (priceString.includes('.') && priceString.includes(',')) {
                                                            priceString = priceString.replace(/\./g, '').replace(',', '.');
                                                        } 
                                                        else if (priceString.includes(',') && !priceString.includes('.')) {
                                                            priceString = priceString.replace(',', '.');
                                                        }
                                                        
                                                        product_price = parseFloat(priceString);
                                                        if (isNaN(product_price)) {
                                                            product_price = 0;
                                                        }
                                                    }
                                                }
                                            }
                                        }
                                        
                                        if (typeof ttq !== 'undefined' && typeof ttq.track === 'function') {
                                            var trackingParams = {
                                                contents: [{
                                                    content_id: String(product_id),
                                                    content_type: 'product',
                                                    content_name: (product_name || '').trim()
                                                }],
                                                value: parseFloat(product_price || 0) * parseInt(quantity),
                                                currency: 'EUR'
                                            };
                                            
                                            ttq.track('AddToCart', trackingParams);
                                            console.log('TikTok Searchanise AddToCart tracked:', trackingParams);
                                            
                                            $.post('<?php echo admin_url('admin-ajax.php'); ?>', {
                                                action: 'ttp_track_add_to_cart_server',
                                                product_id: product_id,
                                                product_price: product_price || 0,
                                                product_name: product_name || '',
                                                quantity: quantity,
                                                source: 'searchanise_ajax',
                                                nonce: '<?php echo wp_create_nonce('ttp_tracking_nonce'); ?>'
                                            });
                                        }
                                    }
                                });
                            }
                            
                        } catch(e) {
                            console.error('TTP Searchanise Error:', e);
                        }
                    }
                    
                    return originalSend.apply(this, arguments);
                };
            }
        });
        </script>
        <?php
    }
    
    /**
     * Traccia AddToCart per prodotti singoli WooCommerce
     */
    public function track_single_product_add_to_cart() {
        if (!is_product()) return;
        
        global $product;
        if (!$product) return;
        
        $product_data = array(
            'id' => $product->get_id(),
            'name' => $product->get_name(),
            'price' => floatval($product->get_price() ?: 0),
            'currency' => get_woocommerce_currency(),
            'type' => $product->get_type()
        );
        
        $categories = wp_get_post_terms($product->get_id(), 'product_cat');
        $product_data['category'] = '';
        if (!empty($categories) && !is_wp_error($categories)) {
            $product_data['category'] = $categories[0]->name;
        }
        
        ?>
        <script>
        jQuery(document).ready(function($) {
            var productData = <?php echo json_encode($product_data); ?>;
            
            $(document).on('click', '.single_add_to_cart_button, button[name="add-to-cart"], .single-product .cart button[type="submit"]', function(e) {
                try {
                    var $button = $(this);
                    var product_id = $button.val() || $button.attr('value') || $button.data('product_id') || productData.id;
                    var quantity = 1;
                    
                    var $form = $button.closest('form');
                    if ($form.length > 0) {
                        var qtyInput = $form.find('input[name="quantity"], .qty').val();
                        if (qtyInput) {
                            quantity = parseInt(qtyInput) || 1;
                        }
                    }
                    
                    if (product_id && typeof ttq !== 'undefined' && typeof ttq.track === 'function') {
                        var trackingParams = {
                            contents: [{
                                content_id: String(product_id),
                                content_type: 'product',
                                content_name: productData.name || ''
                            }],
                            value: parseFloat(productData.price || 0) * parseInt(quantity),
                            currency: productData.currency || 'EUR'
                        };
                        
                        ttq.track('AddToCart', trackingParams);
                        console.log('TikTok AddToCart tracked:', trackingParams);
                        
                        $.post('<?php echo admin_url('admin-ajax.php'); ?>', {
                            action: 'ttp_track_add_to_cart_server',
                            product_id: product_id,
                            product_price: productData.price || 0,
                            product_name: productData.name || '',
                            quantity: quantity,
                            source: 'woocommerce_single_product',
                            nonce: '<?php echo wp_create_nonce('ttp_tracking_nonce'); ?>'
                        });
                    }
                    
                } catch(e) {
                    console.error('TTP WooCommerce Single Error:', e);
                }
            });
        });
        </script>
        <?php
    }
    
    /**
     * Traccia eventi di ricerca
     */
    public function track_search_events() {
        if (!(is_shop() || is_product_category() || is_search())) {
            return;
        }
        
        ?>
        <script>
        jQuery(document).ready(function($) {
            // Ricerca da URL
            if (window.location.pathname.includes('search-results') || window.location.search.includes('q=')) {
                var urlParams = new URLSearchParams(window.location.search);
                var searchQuery = urlParams.get('q') || urlParams.get('s') || urlParams.get('search');
                
                if (searchQuery && searchQuery.trim() && typeof ttq !== 'undefined' && typeof ttq.track === 'function') {
                    var searchData = {
                        search_string: searchQuery.trim()
                    };
                    
                    ttq.track('Search', searchData);
                    console.log('TikTok Search tracked (URL):', searchData);
                    
                    $.post('<?php echo admin_url('admin-ajax.php'); ?>', {
                        action: 'ttp_track_search_server',
                        search_query: searchQuery.trim(),
                        source: 'url_page_load',
                        nonce: '<?php echo wp_create_nonce('ttp_tracking_nonce'); ?>'
                    });
                }
            }
        });
        </script>
        <?php
    }
    
    /**
     * Traccia InitiateCheckout
     */
    public function track_initiate_checkout() {
        if (!WC()->cart) return;
        
        $cart = WC()->cart;
        $content_ids = array();
        $contents = array();
        $value = 0;
        
        foreach ($cart->get_cart() as $cart_item) {
            $product_id = $cart_item['product_id'];
            $quantity = $cart_item['quantity'];
            $product = wc_get_product($product_id);
            
            if (!$product) continue;
            
            $content_ids[] = $product_id;
            $contents[] = array(
                'content_id' => strval($product_id),
                'content_type' => 'product',
                'content_name' => $product->get_name()
            );
            
            $value += floatval($product->get_price() ?: 0) * intval($quantity);
        }
        
        if (empty($content_ids)) return;
        
        $cart_hash = md5(serialize($content_ids) . $value);
        
        ?>
        <script>
        jQuery(document).ready(function($) {
            if (typeof ttq !== 'undefined' && typeof ttq.track === 'function') {
                var checkoutData = {
                    contents: <?php echo json_encode($contents); ?>,
                    value: <?php echo floatval($value); ?>,
                    currency: '<?php echo get_woocommerce_currency(); ?>',
                    content_type: 'product'
                };
                
                var storageKey = 'ttp_checkout_<?php echo $cart_hash; ?>';
                var tracked = sessionStorage.getItem(storageKey);
                
                if (!tracked) {
                    ttq.track('InitiateCheckout', checkoutData);
                    console.log('TikTok InitiateCheckout tracked:', checkoutData);
                    
                    $.post('<?php echo admin_url('admin-ajax.php'); ?>', {
                        action: 'ttp_track_checkout_server',
                        event_type: 'InitiateCheckout',
                        checkout_data: checkoutData,
                        nonce: '<?php echo wp_create_nonce('ttp_tracking_nonce'); ?>'
                    });
                    
                    sessionStorage.setItem(storageKey, '1');
                    
                    // AddPaymentInfo trigger
                    var paymentTracked = false;
                    $('#billing_email, #billing_first_name').one('focus', function() {
                        if (!paymentTracked) {
                            paymentTracked = true;
                            
                            var paymentData = {
                                contents: checkoutData.contents,
                                value: checkoutData.value,
                                currency: checkoutData.currency,
                                content_type: 'product'
                            };
                            
                            ttq.track('AddPaymentInfo', paymentData);
                            console.log('TikTok AddPaymentInfo tracked:', paymentData);
                            
                            $.post('<?php echo admin_url('admin-ajax.php'); ?>', {
                                action: 'ttp_track_checkout_server',
                                event_type: 'AddPaymentInfo',
                                checkout_data: paymentData,
                                nonce: '<?php echo wp_create_nonce('ttp_tracking_nonce'); ?>'
                            });
                        }
                    });
                }
            }
        });
        </script>
        <?php
        
        // Server-side tracking immediato
        TTP_API_Server::send_event('InitiateCheckout', array(
            'content_ids' => $content_ids,
            'contents' => $contents,
            'content_type' => 'product',
            'value' => floatval($value),
            'currency' => get_woocommerce_currency()
        ));
    }
    
    //traccia evebto aqcuisto
    public function track_purchase($order_id) {
        if (!$order_id) return;
        
        $order = wc_get_order($order_id);
        
        if (!$order || get_post_meta($order_id, '_ttp_tracked', true)) {
            return;
        }
        
        $content_ids = array();
        $contents = array();
        $total_value = 0;
        
        // Estrai PRODOTTI dall'ordine (non order ID!)
        foreach ($order->get_items() as $item) {
            $product_id = $item->get_product_id();
            $quantity = $item->get_quantity();
            $product = $item->get_product();
            
            if (!$product) continue;
            
            // PRODUCT ID, non Order ID!
            $content_ids[] = $product_id;
            $contents[] = array(
                'content_id' => strval($product_id),          // PRODUCT ID
                'content_type' => 'product',                  // product, non product_group
                'content_name' => $product->get_name()
            );
            
            // Aggiungi al valore totale
            $total_value += floatval($item->get_total());
        }
        
        if (empty($content_ids)) return;
        
        // IMPORTANTE: Usa il totale ordine, non un valore casuale
        $order_total = floatval($order->get_total());
        
        ?>
        <script>
        jQuery(document).ready(function($) {
            if (typeof ttq !== 'undefined' && typeof ttq.track === 'function') {
                var purchaseData = {
                    contents: <?php echo json_encode($contents); ?>,
                    value: <?php echo $order_total; ?>,                    // VALORE CORRETTO
                    currency: '<?php echo $order->get_currency(); ?>',
                    content_type: 'product'                                // product, non product_group
                };
                
                console.log('📤 Sending TikTok Purchase:', purchaseData);
                
                // EVENTO CORRETTO: Purchase (non "Place an Order")
                ttq.track('Purchase', purchaseData);
                
                console.log('✅ TikTok Purchase tracked:', purchaseData);
            }
        });
        </script>
        <?php
        
        // Server-side tracking con dati corretti
        $user_data = array(
            'email' => $order->get_billing_email(),
            'phone' => $order->get_billing_phone(),
            'external_id' => $order->get_user_id() ?: 'guest_' . $order_id
        );
        
        // Debug server-side
        error_log('TTP Purchase Debug - Order ID: ' . $order_id . ', Total: ' . $order_total . ', Products: ' . count($content_ids));
        
        TTP_API_Server::send_event('Purchase', array(
            'content_ids' => $content_ids,                    // ARRAY di Product ID
            'contents' => $contents,                          // ARRAY di prodotti
            'content_type' => 'product',                      // product, non product_group
            'value' => $order_total,                          // VALORE TOTALE CORRETTO
            'currency' => $order->get_currency()
        ), $user_data);
        
        // Segna come tracciato
        update_post_meta($order_id, '_ttp_tracked', true);
        
        // Debug aggiuntivo
        error_log('TTP Purchase - Content IDs: ' . implode(',', $content_ids) . ' - Value: ' . $order_total);
    }
    
    /**
     * Aggiunge attributi dati ai link prodotti
     */
    public function add_product_data_attributes($link, $product) {
        if (!$product) return $link;
        
        $price = $product->get_price() ?: 0;
        $name = $product->get_name() ?: '';
        
        $search = 'data-product_id';
        $replace = sprintf(
            'data-product_price="%s" data-product_name="%s" data-product_id',
            esc_attr(floatval($price)),
            esc_attr($name)
        );
        
        return str_replace($search, $replace, $link);
    }
    
    /**
     * Handler AJAX ViewContent
     */
    public function handle_view_content_server() {
        if (!wp_verify_nonce($_POST['nonce'], 'ttp_tracking_nonce')) {
            wp_die('Security check failed');
        }
        
        $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
        $product_price = isset($_POST['product_price']) ? floatval($_POST['product_price']) : 0;
        $product_name = isset($_POST['product_name']) ? sanitize_text_field($_POST['product_name']) : '';
        $product_category = isset($_POST['product_category']) ? sanitize_text_field($_POST['product_category']) : '';
        
        if ($product_id) {
            TTP_API_Server::send_event('ViewContent', array(
                'content_ids' => array($product_id),
                'content_name' => $product_name,
                'content_category' => $product_category,
                'content_type' => 'product',
                'value' => $product_price,
                'currency' => get_woocommerce_currency()
            ));
        }
        
        wp_die('OK');
    }
    
    /**
     * Handler AJAX AddToCart
     */
    public function handle_add_to_cart_server() {
        if (!wp_verify_nonce($_POST['nonce'], 'ttp_tracking_nonce')) {
            wp_die('Security check failed');
        }
        
        $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
        $product_price = isset($_POST['product_price']) ? floatval($_POST['product_price']) : 0;
        $product_name = isset($_POST['product_name']) ? sanitize_text_field($_POST['product_name']) : '';
        
        if ($product_id) {
            TTP_API_Server::send_event('AddToCart', array(
                'content_ids' => array($product_id),
                'content_name' => $product_name,
                'content_type' => 'product',
                'value' => $product_price,
                'currency' => get_woocommerce_currency()
            ));
        }
        
        wp_die('OK');
    }
    
    /**
     * Handler AJAX Search
     */
    public function handle_search_server() {
        if (!wp_verify_nonce($_POST['nonce'], 'ttp_tracking_nonce')) {
            wp_die('Security check failed');
        }
        
        $search_query = isset($_POST['search_query']) ? sanitize_text_field($_POST['search_query']) : '';
        
        if (!empty($search_query)) {
            TTP_API_Server::send_event('Search', array(
                'search_string' => $search_query
            ));
        }
        
        wp_die('OK');
    }
    
    /**
     * Handler AJAX Checkout
     */
    public function handle_checkout_server() {
        if (!wp_verify_nonce($_POST['nonce'], 'ttp_tracking_nonce')) {
            wp_die('Security check failed');
        }
        
        $event_type = sanitize_text_field($_POST['event_type']);
        $checkout_data = isset($_POST['checkout_data']) ? $_POST['checkout_data'] : array();
        
        if (!empty($checkout_data['contents']) && in_array($event_type, ['InitiateCheckout', 'AddPaymentInfo'])) {
            
            $user_data = array();
            if (is_user_logged_in()) {
                $user = wp_get_current_user();
                $user_data = array(
                    'email' => $user->user_email,
                    'external_id' => $user->ID
                );
            }
            
            TTP_API_Server::send_event($event_type, array(
                'contents' => $checkout_data['contents'],
                'content_type' => 'product',
                'value' => floatval($checkout_data['value']),
                'currency' => $checkout_data['currency']
            ), $user_data);
        }
        
        wp_die('OK');
    }
}
