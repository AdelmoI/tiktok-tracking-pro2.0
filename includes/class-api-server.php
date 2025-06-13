<?php
/**
 * Classe per TikTok Events API server-side
 *
 * @package TikTokTrackingPro
 * @author Adelmo Infante
 */

// Impedisce accesso diretto
if (!defined('ABSPATH')) {
    exit;
}

class TTP_API_Server {
    
    /**
     * Costruttore
     */
    public function __construct() {
        // La classe è principalmente statica per facilità d'uso
    }
    
    /**
     * Invia evento tramite TikTok Events API
     *
     * @param string $event_name Nome dell'evento
     * @param array $properties Proprietà personalizzate dell'evento
     * @param array $user_data Dati utente (opzionale)
     * @return bool Success status
     */
    public static function send_event($event_name, $properties = array(), $user_data = array()) {
        // Verifica configurazione
        if (!defined('TTP_PIXEL_ID') || !defined('TTP_ACCESS_TOKEN') || !defined('TTP_API_VERSION')) {
            error_log('TTP: Costanti API non definite per evento ' . $event_name);
            return false;
        }
        
        // Verifica che il tracking sia abilitato
        if (!get_option('ttp_enabled', true)) {
            return false;
        }
        
        try {
            $url = 'https://business-api.tiktok.com/open_api/' . TTP_API_VERSION . '/pixel/track/';
            
            // Prepara dati utente formattati
            $user_data_formatted = self::format_user_data($user_data);
            
            // Prepara le proprietà dell'evento
            $properties_formatted = self::format_properties($properties);
            
            // Prepara l'evento
            $event = array(
                'event' => $event_name,
                'event_time' => time(),
                'user' => $user_data_formatted,
                'properties' => $properties_formatted,
                'page' => array(
                    'url' => self::get_current_url(),
                    'referrer' => isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : ''
                )
            );
            
            // Prepara la richiesta
            $data = array(
                'event_source' => 'web',
                'event_source_id' => TTP_PIXEL_ID,
                'data' => array($event)
            );
            
            // Aggiunge test event code se configurato
            if (defined('TTP_TEST_EVENT_CODE') && !empty(TTP_TEST_EVENT_CODE)) {
                $data['test_event_code'] = TTP_TEST_EVENT_CODE;
            }
            
            // Invia la richiesta (non bloccante per performance)
            $response = wp_remote_post($url, array(
                'body' => json_encode($data),
                'timeout' => 3,
                'blocking' => false, // Non blocca la pagina
                'sslverify' => true,
                'headers' => array(
                    'Content-Type' => 'application/json',
                    'Access-Token' => TTP_ACCESS_TOKEN,
                    'User-Agent' => 'TikTokTrackingPro/' . TTP_VERSION . ' (WordPress)'
                )
            ));
            
            // Log errori solo se necessario
            if (is_wp_error($response)) {
                error_log('TTP API Error: ' . $response->get_error_message());
                return false;
            }
            
            return true;
            
        } catch (Exception $e) {
            error_log('TTP Exception: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Formatta i dati utente per l'API TikTok
     */
    private static function format_user_data($user_data = array()) {
        $formatted = array();
        
        // Email (hashata)
        if (!empty($user_data['email']) && is_email($user_data['email'])) {
            $formatted['email'] = hash('sha256', strtolower(trim($user_data['email'])));
        }
        
        // Telefono (hashato)
        if (!empty($user_data['phone'])) {
            $phone = preg_replace('/[^0-9]/', '', $user_data['phone']);
            if (strlen($phone) >= 7) {
                $formatted['phone'] = hash('sha256', $phone);
            }
        }
        
        // External ID (per utenti loggati)
        if (is_user_logged_in()) {
            $formatted['external_id'] = hash('sha256', get_current_user_id());
        } elseif (!empty($user_data['external_id'])) {
            $formatted['external_id'] = hash('sha256', $user_data['external_id']);
        }
        
        return $formatted;
    }
    
    /**
     * Formatta le proprietà dell'evento per TikTok
     */
    private static function format_properties($properties = array()) {
        $formatted = array();
        
        // Mappa le proprietà standard
        $property_mapping = array(
            'content_ids' => 'content_id',
            'content_name' => 'content_name',
            'content_category' => 'content_category',
            'content_type' => 'content_type',
            'value' => 'value',
            'currency' => 'currency',
            'search_string' => 'search_string',
            'num_items' => 'num_items'
        );
        
        foreach ($property_mapping as $original => $tiktok_key) {
            if (isset($properties[$original])) {
                $value = $properties[$original];
                
                // Tratta content_ids come array per TikTok
                if ($original === 'content_ids' && is_array($value)) {
                    $formatted[$tiktok_key] = implode(',', $value);
                } elseif ($original === 'value') {
                    // Assicura che value sia numerico
                    $formatted[$tiktok_key] = floatval($value);
                } else {
                    $formatted[$tiktok_key] = $value;
                }
            }
        }
        
        // Aggiungi contents se presente (per eventi come Purchase)
        if (isset($properties['contents']) && is_array($properties['contents'])) {
            $formatted['contents'] = array();
            foreach ($properties['contents'] as $content) {
                $formatted_content = array();
                if (isset($content['id'])) {
                    $formatted_content['content_id'] = strval($content['id']);
                }
                if (isset($content['content_id'])) {
                    $formatted_content['content_id'] = strval($content['content_id']);
                }
                if (isset($content['content_name'])) {
                    $formatted_content['content_name'] = strval($content['content_name']);
                }
                if (isset($content['content_type'])) {
                    $formatted_content['content_type'] = strval($content['content_type']);
                }
                $formatted['contents'][] = $formatted_content;
            }
        }
        
        return $formatted;
    }
    
    /**
     * Ottiene l'URL corrente
     */
    private static function get_current_url() {
        if (isset($_SERVER['REQUEST_URI'])) {
            return home_url($_SERVER['REQUEST_URI']);
        }
        return home_url();
    }
    
    /**
     * Testa la connessione API
     */
    public static function test_connection() {
        if (!defined('TTP_PIXEL_ID') || !defined('TTP_ACCESS_TOKEN') || !defined('TTP_API_VERSION')) {
            return array(
                'success' => false,
                'message' => 'Configurazione incompleta'
            );
        }
        
        $url = 'https://business-api.tiktok.com/open_api/' . TTP_API_VERSION . '/pixel/track/';
        
        $test_data = array(
            'event_source' => 'web',
            'event_source_id' => TTP_PIXEL_ID,
            'data' => array(array(
                'event' => 'ViewContent',
                'event_time' => time(),
                'user' => array(
                    'external_id' => hash('sha256', 'test_user_' . time())
                ),
                'properties' => array(
                    'content_type' => 'product',
                    'currency' => 'EUR',
                    'value' => 10.00
                ),
                'page' => array(
                    'url' => home_url(),
                    'referrer' => ''
                )
            ))
        );
        
        $response = wp_remote_post($url, array(
            'timeout' => 10,
            'sslverify' => true,
            'body' => json_encode($test_data),
            'headers' => array(
                'Content-Type' => 'application/json',
                'Access-Token' => TTP_ACCESS_TOKEN,
                'User-Agent' => 'TikTokTrackingPro/' . TTP_VERSION . ' (WordPress)'
            )
        ));
        
        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'message' => 'Errore connessione: ' . $response->get_error_message()
            );
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (isset($data['code']) && $data['code'] == 0) {
            return array(
                'success' => true,
                'message' => 'Connessione riuscita! TikTok Events API operativo.'
            );
        }
        
        if (isset($data['message'])) {
            return array(
                'success' => false,
                'message' => 'Errore API: ' . $data['message']
            );
        }
        
        return array(
            'success' => false,
            'message' => 'Risposta API non valida'
        );
    }
}