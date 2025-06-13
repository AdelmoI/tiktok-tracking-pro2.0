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
     * COSTRUTTORE PULITO (rimuovi il bypass)
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
        
        // Hook per l'inizializzazione - SENZA BYPASS
        add_action('wp_head', array($this, 'define_callback_system'), 1);
        add_action('wp_head', array($this, 'pixel_base_code'), 2); // Torna a 2
        add_action('wp_head', array($this, 'block_conflicting_pixels'), 999);
    }
    
    /**
     * SOSTITUISCI il metodo define_callback_system() con questa versione SILENZIOSA
     */
    public function define_callback_system() {
        ?>
        <script>
        // Sistema callback TikTok Tracking Pro - SILENZIOSO
        window.ttqPixelCallbacks = window.ttqPixelCallbacks || [];
        window.ttqPixelReady = false;
        
        // Funzione per callback - SENZA LOG ECCESSIVI
        window.onTtqPixelReady = function(callback) {
            if (window.ttqPixelReady && typeof ttq !== 'undefined' && typeof ttq.track === 'function') {
                try {
                    callback();
                } catch(e) {
                    console.error('TTP Callback Error:', e);
                }
            } else {
                window.ttqPixelCallbacks.push(callback);
            }
        };
        
        // Funzione helper per tracking diretto - SENZA LOG
        window.ttpTrack = function(eventName, eventData) {
            if (typeof ttq !== 'undefined' && typeof ttq.track === 'function') {
                try {
                    ttq.track(eventName, eventData);
                    return true;
                } catch(e) {
                    console.error('TikTok tracking error:', e);
                    return false;
                }
            }
            return false;
        };
        
        // Solo log iniziale
        console.log('🔧 TikTok system ready');
        </script>
        <?php
    }


    /**
     * SOSTITUISCI il metodo pixel_base_code() con questa versione OTTIMIZZATA
     */
    public function pixel_base_code() {
        ?>
        <script>
        // TikTok Pixel Base Code - OTTIMIZZATO
        !function (w, d, t) {
            w.TiktokAnalyticsObject=t;var ttq=w[t]=w[t]||[];ttq.methods=["page","track","identify","instances","debug","on","off","once","ready","alias","group","enableCookie","disableCookie","holdConsent","revokeConsent","grantConsent"],ttq.setAndDefer=function(t,e){t[e]=function(){t.push([e].concat(Array.prototype.slice.call(arguments,0)))}};for(var i=0;i<ttq.methods.length;i++)ttq.setAndDefer(ttq,ttq.methods[i]);ttq.instance=function(t){for(
            var e=ttq._i[t]||[],n=0;n<ttq.methods.length;n++)ttq.setAndDefer(e,ttq.methods[n]);return e},ttq.load=function(e,n){var r="https://analytics.tiktok.com/i18n/pixel/events.js",o=n&&n.partner;ttq._i=ttq._i||{},ttq._i[e]=[],ttq._i[e]._u=r,ttq._t=ttq._t||{},ttq._t[e]=+new Date,ttq._o=ttq._o||{},ttq._o[e]=n||{};n=document.createElement("script")
            ;n.type="text/javascript",n.async=!0,n.src=r+"?sdkid="+e+"&lib="+t;e=document.getElementsByTagName("script")[0];e.parentNode.insertBefore(n,e)};
    
            // Carica TikTok - SILENZIOSO
            ttq.load('<?php echo TTP_PIXEL_ID; ?>');
            ttq.page();
            
            // Verifica quando TikTok è pronto - MENO LOG
            function checkTikTokReady() {
                if (typeof ttq.track === 'function') {
                    // TikTok pronto - test silenzioso
                    try {
                        ttq.track('PageView');
                        // Solo log di successo, no debug verboso
                        console.log('✅ TikTok Pixel ready');
                    } catch (e) {
                        // Retry silenzioso
                        setTimeout(checkTikTokReady, 500);
                        return;
                    }
                    
                    // Notifica che è pronto
                    window.ttqPixelReady = true;
                    
                    // Esegui callback in coda
                    if (window.ttqPixelCallbacks && window.ttqPixelCallbacks.length > 0) {
                        var callbacksToExecute = window.ttqPixelCallbacks.slice();
                        window.ttqPixelCallbacks = [];
                        
                        callbacksToExecute.forEach(function(callback) {
                            try {
                                callback();
                            } catch(e) {
                                console.error('TTP Callback Error:', e);
                            }
                        });
                    }
                    
                } else {
                    // Retry silenzioso senza log
                    setTimeout(checkTikTokReady, 200);
                }
            }
            
            // Inizia controllo dopo delay
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
     * SOSTITUISCI il metodo block_conflicting_pixels() con questa versione SILENZIOSA
     */
    public function block_conflicting_pixels() {
        ?>
        <script>
        // Blocca pixel TikTok conflittuali - SILENZIOSO
        jQuery(document).ready(function($) {
            // Blocca variabili globali di altri plugin - SENZA LOG
            if (typeof wc_tiktok_pixel !== 'undefined') {
                window.wc_tiktok_pixel = null;
            }
            
            // Rimuovi script di altri plugin TikTok - SILENZIOSO
            $('script[src*="tiktok"]').not('[src*="analytics.tiktok.com"]').remove();
            
            // Rimuovi script inline problematici - SILENZIOSO
            $('script').filter(function() {
                var scriptText = $(this).text();
                return (scriptText.includes('wc_tiktok_pixel') || 
                       (scriptText.includes('ttq') && scriptText.includes('AddToCart') && !scriptText.includes('TTP')));
            }).remove();
            
            // Solo un log di conferma finale
            console.log('🛡️ TikTok conflicts handled');
        });
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