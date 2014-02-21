Turns Safe Redirect Manager into a Short URL service.

## Setup

Add to `wp-config.php`:

```
define( 'SHORT_URL_API_KEY', 'YOUR-API-KEY' );
```

Generate a short URL:

Send a request to <code>/wp-admin/admin-ajax.php?action=create-short-url&key=YOUR-API-KEY&url=http://longurl.com/example</code>.

## Details

* URL keys are [Hash IDs](http://www.hashids.org/) based on the ID used for the redirect custom post type. 
* Hash IDs are salted, causing them to not be easily guessed.
* Redirects are checked for duplicates. If a duplicate is found, the original Hash ID is returned.
* Hash IDs have no possibility for intersections -- every ID will be unique.
* Hash IDs will be as short as possible for the given post ID and salt.