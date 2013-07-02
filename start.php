<?php

/**
 * Webmentions for Elgg
 * Provides #indieweb Webmention support for Elgg.
 *
 * Devs should listen to the getbyurl/object plugin hook and return an appropriate object based on URL lookup.
 * They should listen to webmention/object plugin hook in order to handle the plugin hook in a way meaningful to them.
 * 
 * @licence GNU Public License version 2
 * @link https://github.com/mapkyca/elgg-webmention
 * @link http://www.marcus-povey.co.uk
 * @link http://webmention.org/
 * @author Marcus Povey <marcus@marcus-povey.co.uk>
 */
	

elgg_register_event_handler('init', 'system', function() {
    
    // Register a webmention endpoint
    elgg_register_page_handler('webmention', function($pages) {
    
        $return = array();
        $source_url = $_POST['source'];
        $target_url = $_POST['target'];
        
        // Do we have a source and target URL?
        if ($source_url && $target_url) {
            
            elgg_log("WEBMENTION: Webmention Endpoint triggered: $source_url => $target_url");
        
            // Try and get an object from target. This is surprisingly hard, so we leave it to developers to extend. 
            // You should parse the url and see if it matches, and if it does, return an object... e.g.
            // 
            //      if (preg_match("/".str_replace('/','\/', $CONFIG->wwwroot)."blog\/([0-9]*)/", $params['url'], $match))
            //          return get_entity((int)$match[1]);
            //          
            // Were I to write Elgg again, I'd have reverse lookups as standard.
            if ($object = elgg_trigger_plugin_hook('getbyurl', 'object', array('url' => $target_url), false))
            {
                // We have a valid target, so now lets fetch it and process it for links and microformats
                if ($source = file_get_contents($source_url)) {
                
                    // Find URLs
                    preg_match_all('/(?<!=["\'])((ht|f)tps?:\/\/[^\s\r\n\t<>"\'\!\(\)]+)/i', $source, $matches);
                    if ((in_array($target_url, $matches[0])) && (strpos($http_response_header[0], '4') === false)) {
                     
                        // Now, parse the response for microformats TODO
                        
                        
                        
                        // Mean time, lets see if we can parse some generic bits from the page (similar to pingback)
                        
                        // Get title
                        if (preg_match("/<title>(.*)<\/title>/imsU", $source, $m)) 
                            $title = $m[1];
                        
                        // Get extract (TODO: Do this nicer)
                        $strpos = strpos($source, $target_url);
                        if ($strpos!==false)
                        {
                            $a = 0;
                            if ($strpos>300) $a=$strpos-300;

                            $extract = strip_tags(substr($source, $a, 600));

                            if ($extract) {
                                    $hwp = strlen($extract) / 2;
                                    $extract = substr($extract, $hwp - 75, 150);

                                    $extract = "..." . trim($extract) . "...";
                            }
                        }
                        
                        
                        // Finally, we let plugins decide what to do with the webmention
                        if (elgg_trigger_plugin_hook('webmention', 'object', array(
                            'source' => $source_url,
                            'target' => $target_url,
                            'entity' => $object,
                            'source_title' => $title,
                            'source_extract' => $extract,
                            'source_content_raw' => $source,
                            'source_content_parsed' => elgg_trigger_plugin_hook('parse', 'webmention', array('source' => $source), false)
                        ), false)) {
                            header('HTTP/1.1 202 Accepted');
                            $return['result'] = 'Webmention was successful!';
                        }
                        else
                        {
                            header('HTTP/1.1 400 Bad Request');
                            $return['error'] = 'target_not_supported';
                        }
                    }
                    else
                    {
                        header('HTTP/1.1 400 Bad Request');
                        $return['error'] = 'no_link_found';
                    }
                }
            }
            else
            {
                header('HTTP/1.1 400 Bad Request');
                $return['error'] = 'target_not_found';
            }
        }
        
        // Process response, lets just assume JSON encoding
        header('Content-Type: application/json');
        elgg_log("WEBMENTION: Webmention Endpoint returned: " . print_r($return , true));
        echo json_encode($return);
        
    });
    
    // Now, listen to object create events and see if we can send webmentions. Currently looks for urls in ->description
    elgg_register_event_handler('create', 'object', function($event, $type, $object){
        
        if ( ($object) /*&& ($object->access_id == ACCESS_PUBLIC) */ && ($description = $object->description)) {
            
            // Find URLs
            preg_match_all('/(?<!=["\'])((ht|f)tps?:\/\/[^\s\r\n\t<>"\'\!\(\)]+)/i', $description, $matches);
            
            if ($matches) {
                $urls = array_unique($matches[0]);
                
                if ($urls) {

                    foreach ($urls as $url) {
                        
                        if (webmention_send($url, $object->getUrl()))
                            system_message (elgg_echo('elgg_webmention:send:success', array($url)));
                        
                    }
                    
                }
            }
        }
    });
    
    // Tell people that we're running a webmention endpoint (and where it is)
    elgg_extend_view('page/elements/head', 'webmention/head');
    header('Link: <' . elgg_get_site_url() . 'webmention/>; rel="http://webmention.org/"');
    
    // Make sure we can always see the endpoint when running walled garden
    elgg_register_plugin_hook_handler('public_pages', 'walled_garden', function ($hook, $handler, $return, $params){
	$pages = array('webmention/');
	return array_merge($pages, $return);
    });
    
});

/**
 * Send a webmention to a page.
 * @param type $target_url
 * @param type $source_url
 */
function webmention_send($target_url, $source_url) {
    
    // Get target
    if ($page = file_get_contents($target_url)) {
    
        $endpoint_url = null;
        
        // Get headers from request
        $headers = $http_response_header;
        
        // Look for webmention in header
        foreach ($headers as $header) {
            if ((preg_match('~<(https?://[^>]+)>; rel="http://webmention.org/"~', $header, $match)) && (!$endpoint_url)) {
                $endpoint_url = $match[1];
            }
        }
        
        // If not there, look for webmention in body
        if (!$endpoint_url) {
            if (preg_match('/<link href="([^"]+)" rel="http:\/\/webmention.org\/" ?\/?>/i', $page, $match)) {
                $endpoint_url = $match[1];
            }
        }

        if ($endpoint_url) {
            
            elgg_log("WEBMENTION: Sending webhook to endpoint $endpoint_url");
            
            // Send webmention
            $data = array('source' => $source_url, 'target' => $target_url);

            $options = array(
                'http' => array(
                    'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
                    'method'  => 'POST',
                    'content' => http_build_query($data),
                ),
            );
            $context  = stream_context_create($options);
            $result = file_get_contents($endpoint_url, false, $context);

            // Return success
            return strpos($http_response_header[0], '202'); // Accepted error code in response?
        }
    }
    
    elgg_log("WEBMENTION: No endpoint found at $target_url");
    return false;
}