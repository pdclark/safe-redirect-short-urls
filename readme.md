Turns Safe Redirect Manager into a Short URL service.

## Setup

1. Activate the plugin. It will auto-activate Safe Redirect Manager or request it be installed if it doesn't exist.
1. Add `define( 'SHORT_URL_API_KEY', 'YOUR-API-KEY' );` to `wp-config.php`.

## Generate a short URL:

Example request:

<code>http://site.com/wp-admin/admin-ajax.php?action=srm-short-url&key=YOUR-API-KEY&url=http://longurl.com/example</code>

Example return:

http://site.com/Qr3

## Options

Override the default domain name. For example, if you have a short domain that also points to your site.

```php
add_filter( 'srsu_site_url', function(){ return 'http://pd.cm'; } );
```

Set the API key using a filter. Useful for setting config in an `mu-plugin` instead of `wp-config.php`

```php
add_filter( 'srsu_api_key',  function(){ return 'kckRse'; } );
```

## Details

* URL keys are [Hash IDs](http://www.hashids.org/) based on the ID used for the redirect custom post type. 
* Hash IDs are salted, causing them to not be easily guessed.
* Redirects are checked for duplicates. If a duplicate is found, the original Hash ID is returned.
* Hash IDs have no possibility for intersections -- every ID will be unique.
* Hash IDs will be as short as possible for the given post ID and salt.