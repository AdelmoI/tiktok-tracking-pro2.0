<?php
/**
 * Classe core per l'inizializzazione del TikTok Pixel
 *
 * @package TikTokTrackingPro
 * @author Adelmo Infante
 */

// Impedisce accesso diretto
if (!defined('ABSPATH')) {
    exit;
}

class TTP_Pixel_Core {
    
    /**
     * Costruttore
     */
    public function __construct() {
        // Solo se il tracking è abilitato
        if (!get_option('ttp_enabled', true)) {
            return;
        }
        
        // Verifica che abbiamo le costanti necessarie
        if (!defined('TTP_PIXEL_ID') || !defined('TTP_ACCESS_TOKEN')) {
            return;
        }
        
        // Hook per l'inizializzazione
        add_action('wp_head', array($this, 'define_callback_system'), 1);
        add_action('wp_head', array($this, 'pixel_base_code'), 2);
        add_action('wp_head', array($this, 'block_conflicting_pixels'), 999);
    }
    
    /**
     * Definisce il sistema di callback JavaScript - PRODUCTION CLEAN
     */
    public function define_callback_system() {
        ?>
        <script>
        // Sistema callback TikTok Tracking Pro - PRODUCTION VERSION
        window.ttqPixelCallbacks = window.ttqPixelCallbacks || [];
        window.ttqPixelReady = false;
        
        // Helper performance
        function deferTikTokOps(callback, delay = 100) {
            if (document.readyState === 'loading') {
                window.addEventListener('load', function() {
                    setTimeout(callback, delay);
                });
            } else {
                setTimeout(callback, delay);
            }
        }
        
        // Funzione per callback - CLEAN
        window.onTtqPixelReady = function(callback) {
            if (window.ttqPixelReady && typeof ttq !== 'undefined' && typeof ttq.track === 'function') {
                requestAnimationFrame(function() {
                    try {
                        callback();
                    } catch(e) {
                        console.error('TTP Callback Error:', e);
                    }
                });
            } else {
                window.ttqPixelCallbacks.push(callback);
            }
        };
        
        // Tracking helper - CLEAN
        window.ttpTrack = function(eventName, eventData) {
            if (typeof ttq !== 'undefined' && typeof ttq.track === 'function') {
                requestAnimationFrame(function() {
                    try {
                        ttq.track(eventName, eventData);
                    } catch(e) {
                        console.error('TikTok tracking error:', e);
                    }
                });
                return true;
            }
            return false;
        };
        </script>
        <?php
    }

    /**
     * Codice base del TikTok Pixel - PRODUCTION CLEAN
     */
    public function pixel_base_code() {
        ?>
        <script>
        // TikTok Pixel Base Code - PRODUCTION VERSION
        !function (w, d, t) {
            w.TiktokAnalyticsObject=t;var ttq=w[t]=w[t]||[];ttq.methods=["page","track","identify","instances","debug","on","off","once","ready","alias","group","enableCookie","disableCookie","holdConsent","revokeConsent","grantConsent"],ttq.setAndDefer=function(t,e){t[e]=function(){t.push([e].concat(Array.prototype.slice.call(arguments,0)))}};for(var i=0;i<ttq.methods.length;i++)ttq.setAndDefer(ttq,ttq.methods[i]);ttq.instance=function(t){for(
            var e=ttq._i[t]||[],n=0;n<ttq.methods.length;n++)ttq.setAndDefer(e,ttq.methods[n]);return e},ttq.load=function(e,n){var r="https://analytics.tiktok.com/i18n/pixel/events.js",o=n&&n.partner;ttq._i=ttq._i||{},ttq._i[e]=[],ttq._i[e]._u=r,ttq._t=ttq._t||{},ttq._t[e]=+new Date,ttq._o=ttq._o||{},ttq._o[e]=n||{};n=document.createElement("script")
            ;n.type="text/javascript",n.async=!0,n.src=r+"?sdkid="+e+"&lib="+t;e=document.getElementsByTagName("script")[0];e.parentNode.insertBefore(n,e)};
    
            ttq.load('<?php echo TTP_PIXEL_ID; ?>');
            ttq.page();
            
            // Verifica quando TikTok è pronto - CLEAN
            function checkTikTokReady() {
                if (typeof ttq.track === 'function') {
                    try {
                        ttq.track('PageView');
                    } catch (e) {
                        setTimeout(checkTikTokReady, 500);
                        return;
                    }
                    
                    window.ttqPixelReady = true;
                    
                    // Esegui callback in coda - ASINCRONO
                    if (window.ttqPixelCallbacks && window.ttqPixelCallbacks.length > 0) {
                        var callbacksToExecute = window.ttqPixelCallbacks.slice();
                        window.ttqPixelCallbacks = [];
                        
                        requestAnimationFrame(function() {
                            callbacksToExecute.forEach(function(callback) {
                                try {
                                    callback();
                                } catch(e) {
                                    console.error('TTP Callback Error:', e);
                                }
                            });
                        });
                    }
                    
                } else {
                    setTimeout(checkTikTokReady, 200);
                }
            }
            
            setTimeout(checkTikTokReady, 1000);
            
        }(window, document, 'ttq');
        </script>
        <noscript>
        <img height="1" width="1" style="display:none"
        src="https://analytics.tiktok.com/i18n/pixel/track.png?sdkid=<?php echo TTP_PIXEL_ID; ?>&e=PageView"/>
        </noscript>
        <?php
    }
    
    /**
     * Blocca pixel conflittuali - PRODUCTION CLEAN
     */
    public function block_conflicting_pixels() {
        ?>
        <script>
        // Blocca pixel TikTok conflittuali - PRODUCTION VERSION
        if (typeof wc_tiktok_pixel !== 'undefined') {
            window.wc_tiktok_pixel = null;
        }
        
        if (typeof jQuery !== 'undefined') {
            window.addEventListener('load', function() {
                setTimeout(function() {
                    jQuery(function($) {
                        $('script[src*="tiktok"]').not('[src*="analytics.tiktok.com"]').remove();
                        $('script').filter(function() {
                            var scriptText = $(this).text();
                            return (scriptText.includes('wc_tiktok_pixel') || 
                                   (scriptText.includes('ttq') && scriptText.includes('AddToCart') && !scriptText.includes('TTP')));
                        }).remove();
                    });
                }, 300);
            });
        }
        </script>
        <?php
    }
    
    /**
     * Ottieni il Pixel ID configurato
     */
    public static function get_pixel_id() {
        return defined('TTP_PIXEL_ID') ? TTP_PIXEL_ID : '';
    }
    
    /**
     * Verifica se il pixel è inizializzato
     */
    public static function is_pixel_ready() {
        return defined('TTP_PIXEL_ID') && defined('TTP_ACCESS_TOKEN') && get_option('ttp_enabled', true);
    }
    
    /**
     * Genera un event ID unico
     */
    public static function generate_event_id($event_name, $additional_data = '') {
        return strtolower($event_name) . '_' . time() . '_' . substr(md5($additional_data . uniqid()), 0, 8);
    }
}