Elgg WebMentions
================

This is an Elgg 1.8 plugin that provides a framework to add #indieweb webmention
support to Elgg.

Plugin was created by Marcus Povey <http://www.marcus-povey.co.uk>

What are webmentions
--------------------

Webmentions are a simpler and more up to date alternative to Pingback using standard
web technologies.

Go here for more details: http://webmention.org/

Usage
-----

Install and activate the plugin in the usual way.

Once activated the plugin will provide the following functionality:

* It will listen to the create events of objects and, if the object's ->description contains URLs, 
it will attempt to send a webmention to it.
* It exposes a webmention endpoint on http://yoursite.url/webmention/ (setting the appropriate header values). 

In order for the webmention endpoint to be useful, plugin authors have to do a little bit
more work:

1) They must implement a listener for the getbyurl/object plugin hook. This hook is passed a permalink, and
the plugin author should parse it, and return the appropriate object if a match is found:

e.g.

```php
elgg_register_plugin_hook_handler('getbyurl', 'object', function($hook, $type, $return, $params) {
    
    global $CONFIG;

    if (preg_match("/".str_replace('/','\/', $CONFIG->wwwroot)."blog\/([0-9]*)/", $params['url'], $match))
        return get_entity((int)$match[1]);

});
```

2) They must implement a handler for webmention/object hook. This hook is passed, among other things, the created object. 
The author must return true if the plugin handles that kind of object, e.g

```php
elgg_register_plugin_hook_handler('webmention', 'object', function($hook, $type, $return, $params) {
    $source_url = $params['source'];
    $target_url = $params['target'];
    $object = $params['entity'];
    $title = $params['source_title'];
    $extract = $params['source_extract'];
    $content_raw = $params['source_content_raw']; 
    $microformat_data = $params['source_content_parsed'];

    if (elgg_instance_of($object, 'object', 'blog'))
    {
        ...
        
        // Handle web mention, perhaps as a blog comment using author info got from the microformat data, or if microformats say this was a "like" perhaps do something with that

        ...

        return true;
    }
});
```

Todo
----

- [ ] Endpoint to parse source URL for microformats, and handle them accordingly.
- [ ] CRUD events - update on dupe, delete on HTTP DELETE

See
---
* Marcus Povey <http://www.marcus-povey.co.uk>
* Webmention Spec <http://webmention.org/>

