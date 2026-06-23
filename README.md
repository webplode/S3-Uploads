# 🚀 MUST HAVE Configuration (WebP Enhanced Version)

## ⚠️ IMPORTANT: Add these configurations BEFORE using the plugin!

### 📁 Add to functions.php file in theme editor

```php
function tw_s3_uploads_s3_client_params( $params ) {
    $params["endpoint"] = S3_UPLOADS_ENDPOINT;
    $params["use_path_style_endpoint"] = true;
    return $params;
}

add_filter( "s3_uploads_s3_client_params", "tw_s3_uploads_s3_client_params");
```

### ⚙️ Add to wp-config.php

```php
require_once __DIR__ . '/vendor/autoload.php';

define("S3_UPLOADS_DISABLE_REPLACE_UPLOAD_URL", true );
define("S3_UPLOADS_ENDPOINT", "https://52f4aa07903b661f2425a74c1291016d.r2.cloudflarestorage.com");
define("S3_UPLOADS_BUCKET", "gia-hanoi");
define("S3_UPLOADS_BUCKET_URL", "https://static.gia-hanoi.com");
define("S3_UPLOADS_REGION", "auto");
define("S3_UPLOADS_KEY", "");
define("S3_UPLOADS_SECRET", "");
```

## 🎨 WebP Enhanced Features

This enhanced version of S3 Uploads includes automatic WebP conversion with the following features:

- ✅ **Automatic WebP Conversion**: PNG, JPG, and JPEG images are automatically converted to WebP format
- ✅ **WebP-Only S3 Storage**: Only optimized WebP versions are uploaded to S3 (saves storage costs)
- ✅ **Automatic .htaccess Generation**: Creates redirect rules in wp-content/uploads/ for seamless CDN delivery
- ✅ **Filename Preservation**: Converts `image.jpg` to `image.jpg.webp` (preserves original format info)
- ✅ **Quality Control**: Configurable WebP quality (default: 85%)
- ✅ **Debug Logging**: Comprehensive logging when WP_DEBUG is enabled
- ✅ **Fallback Support**: Falls back to original upload if WebP conversion fails

### 🔧 WebP Quality Configuration

You can adjust the WebP compression quality by adding this to your theme's functions.php:

```php
add_filter( 's3_uploads_webp_quality', function( $quality ) {
    return 90; // Adjust quality (1-100, default: 85)
});
```

### Required PHP extensions & settings (webplode fork)

Enable these for the **same PHP version** your site uses (cPanel: **Select PHP Version → Extensions**, or **MultiPHP INI Editor**).

| Extension / setting | Required for | If missing |
|---------------------|--------------|------------|
| **dom** (`php-xml`) | WordPress reads image metadata after upload (`DOMDocument`) | Upload reaches S3 then **fatal** / generic AJAX error |
| **mbstring** | WordPress + plugins (e.g. TranslatePress) | HTML admin notices break Media Library JSON |
| **imagick** | WebP prefilter + thumbnails on `s3://` | Skip prefilter or *cannot process image* on sizes |
| **fileinfo** | MIME detection | Upload / security rejects |
| **curl**, **openssl**, **json** | AWS SDK (Composer `vendor/`) | S3 API calls fail |
| **exif** (recommended) | EXIF orientation / metadata | Odd rotation or metadata loss |
| **`allow_url_fopen` = On** | S3 stream wrapper | Plugin refuses to load (admin notice) |

**Verify on SSH:**

```bash
php -m | egrep -i 'dom|mbstring|imagick|fileinfo|curl|openssl|json|exif'
php -i | grep allow_url_fopen
```

**Suggested php.ini (cPanel MultiPHP INI Editor):**

```ini
memory_limit = 512M
max_execution_time = 120
upload_max_filesize = 64M
post_max_size = 64M
allow_url_fopen = On
```

### 🩺 Upload errors (plugin enabled only)

If uploads work with the plugin **disabled** but fail when it is **enabled**, check the following:

1. **`vendor/autoload.php` in `wp-config.php`** (before WordPress loads) and valid **S3/R2 credentials** (bucket, region, key/secret, endpoint filter for R2).
2. **`allow_url_fopen`** must be enabled in PHP (required by this plugin).
3. **`dom` (XML)** — if the log shows `Class "DOMDocument" not found`, enable the **dom** extension (often packaged as **xml** in cPanel).
4. **Local `wp-content/uploads` must be writable** — the fork still writes `.htaccess` and font copies there even when media goes to S3.
5. **Imagick** — WebP pre-conversion and thumbnails need the PHP Imagick extension; large images can trigger *"server cannot process the image"* if memory/time limits are low. To test, add to `wp-config.php`:
   ```php
   define( 'S3_UPLOADS_DISABLE_WEBP_PREFILTER', true );
   ```
   Thumbnails still convert via the image editor when Imagick is available.
6. **Nginx** — `.htaccess` rules are ignored; use equivalent redirect rules in your server config or `define( 'S3_UPLOADS_DISABLE_HTACCESS', true );`.
7. **Upload succeeds but UI says "An error occurred"** — Another plugin (often **TranslatePress** missing **mbstring**) prints HTML before the upload JSON. Install `php-mbstring`, or rely on this fork’s media AJAX guard (filter `s3_uploads_clean_media_upload_ajax`, constant `S3_UPLOADS_MEDIA_UPLOAD_JSON_BUFFER`).
8. **"The server cannot process the image" (2560px message)** — Usually **thumbnail/metadata** failed after the file reached S3, not the initial upload. Some images fail while others work (corrupt EXIF, memory, many theme sizes, huge file bytes). Enable logging below and retry one failing file.

### Upload debug logging

Add to **`wp-config.php`** (above `/* That's all, stop editing! */`):

```php
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
define( 'WP_DEBUG_DISPLAY', false );
// Always log S3 Uploads upload steps even if WP_DEBUG is off:
define( 'S3_UPLOADS_DEBUG_LOG', true );
```

Log file: **`wp-content/debug.log`**

Reproduce a **failed** upload, then search the log for:

| Log line | Meaning |
|----------|--------|
| `Converting original image to WebP` | Prefilter ran (your JPEG case) |
| `wp_handle_upload OK` | File moved to S3 path |
| `generate_attachment_metadata START` | WordPress began thumbnails |
| `generate_attachment_metadata FAILED` or `Multi-resize failed` | Thumbnail step broke — read next lines |
| `PHP fatal/error on media AJAX shutdown` | Timeout / memory / Imagick crash — check message for `DOMDocument`, `Imagick`, etc. |
| `Class "DOMDocument" not found` | Enable PHP **dom** / **xml** extension (WordPress core, not S3) |
| `peak_memory_mb` | If near your `memory_limit`, raise PHP memory |

Disable verbose WebP line-by-line noise in production: `define( 'S3_UPLOADS_DEBUG_LOG', false );` and turn off `WP_DEBUG_LOG`.

**Quick tests:** upload the same image with `S3_UPLOADS_DISABLE_WEBP_PREFILTER` true; try raising `memory_limit` to `512M`; reduce registered image sizes (themes/plugins add many sizes per upload).

---

<table width="100%">
	<tr>
		<td align="left" width="70">
			<strong>S3 Uploads</strong><br />
			Lightweight "drop-in" for storing WordPress uploads on Amazon S3 instead of the local filesystem.
		</td>
		<td align="right" width="20%">
			<a href="https://shepherd.dev/github/humanmade/S3-Uploads">
				<img src="https://shepherd.dev/github/humanmade/S3-Uploads/coverage.svg" alt="Psalm coverage">
			</a>
			<a href="https://github.com/humanmade/S3-Uploads/actions/workflows/ci.yml">
				<img src="https://github.com/humanmade/S3-Uploads/actions/workflows/ci.yml/badge.svg" alt="CI">
			</a>
			<a href="https://codecov.io/github/humanmade/S3-Uploads" >
				<img src="https://codecov.io/github/humanmade/S3-Uploads/graph/badge.svg?token=JmeqBWddkV"/>
			</a>
		</td>
	</tr>
	<tr>
		<td>
			A <strong><a href="https://hmn.md/">Human Made</a></strong> project. Maintained by @joehoyle.
		</td>
		<td align="center">
			<img src="https://humanmade.com/content/themes/hmnmd/assets/images/hm-logo.svg" width="100" />
		</td>
	</tr>
</table>

S3 Uploads is a WordPress plugin to store uploads on S3. S3 Uploads aims to be a lightweight "drop-in" for storing uploads on Amazon S3 instead of the local filesystem.

It's focused on providing a highly robust S3 interface with no "bells and whistles", WP-Admin UI or much otherwise. It comes with some helpful WP-CLI commands for generating IAM users, listing files on S3 and Migrating your existing library to S3.

## Requirements

- **PHP >= 8.0** (tested with 8.3 / 8.4)
- **WordPress >= 5.3** (6.7+ recommended)
- **Composer** `vendor/` with `aws/aws-sdk-php` (see install below)
- **PHP extensions:** `dom`, `mbstring`, `imagick` (WebP), `fileinfo`, `curl`, `openssl`, `json`; `exif` recommended
- **`allow_url_fopen` enabled**

## Install / update via Composer (webplode fork)

This fork is published as **`webplode/s3-uploads`** on GitHub. In your **WordPress project root** (where `composer.json` lives), add the VCS repository once, then require the package:

```json
{
  "repositories": [
    {
      "type": "vcs",
      "url": "https://github.com/webplode/S3-Uploads"
    }
  ],
  "require": {
    "webplode/s3-uploads": "dev-master"
  },
  "extra": {
    "installer-paths": {
      "wp-content/plugins/{$name}/": ["type:wordpress-plugin"]
    }
  },
  "config": {
    "allow-plugins": {
      "composer/installers": true
    }
  }
}
```

Then run:

```bash
composer update webplode/s3-uploads --with-dependencies
```

Or first-time install:

```bash
composer require webplode/s3-uploads:dev-master
```

**Pin a release** (recommended for production) after we tag on GitHub:

```bash
composer require webplode/s3-uploads:^3.0.12
```

Load Composer before WordPress in **`wp-config.php`**:

```php
require_once __DIR__ . '/vendor/autoload.php';
```

The plugin is installed to `wp-content/plugins/s3-uploads/` (package folder name from Composer). Activate **S3 Uploads** in WP Admin.

**Update to latest GitHub `master`:**

```bash
composer clear-cache
composer update webplode/s3-uploads --with-dependencies
```

If Composer says “nothing to update”, delete `composer.lock` entry or run:

```bash
composer require webplode/s3-uploads:dev-master --update-with-dependencies
```

---

## Getting Set Up (upstream humanmade)

Upstream package name:

```
composer require humanmade/s3-uploads
```

**Note:** [Composer's autoloader](https://getcomposer.org/doc/01-basic-usage.md#autoloading) must be loaded before S3 Uploads is loaded. We recommend loading it in your `wp-config.php` before `wp-settings.php` is loaded as shown below.

```php
require_once __DIR__ . '/vendor/autoload.php';
```

## Configuration

Once you've installed the plugin, add the following constants to your `wp-config.php`:

```PHP
define( 'S3_UPLOADS_BUCKET', 'my-bucket' );
define( 'S3_UPLOADS_REGION', '' ); // the s3 bucket region (excluding the rest of the URL)

// You can set access key and secret directly:
define( 'S3_UPLOADS_KEY', '' );
define( 'S3_UPLOADS_SECRET', '' );

// Or if using IAM instance profiles, you can use the instance's credentials:
define( 'S3_UPLOADS_USE_INSTANCE_PROFILE', true );
```
Please refer to this [Region list](http://docs.aws.amazon.com/general/latest/gr/rande.html#s3_region) for the S3_UPLOADS_REGION values.

Use of path prefix after the bucket name is allowed and is optional. For example, if you want to upload all files to 'my-folder' inside a bucket called 'my-bucket', you can use:

```PHP
define( 'S3_UPLOADS_BUCKET', 'my-bucket/my-folder' );
```

Please refer to this document outlining [Best Practices for managing AWS access keys](https://docs.aws.amazon.com/IAM/latest/UserGuide/id_credentials_access-keys.html#securing_access-keys)

You must then enable the plugin. To do this via WP-CLI use command:

```
wp plugin activate S3-Uploads
```

The plugin name must match the directory you have cloned S3 Uploads into;
If you're using Composer, use
```
wp plugin activate s3-uploads
```


The next thing that you should do is to verify your setup. You can do this using the `verify` command
like so:

```
wp s3-uploads verify
```

You will need to create your IAM user yourself, or attach the necessary permissions to an existing user, you can output the policy via `wp s3-uploads generate-iam-policy`


## Listing files on S3

S3-Uploads comes with a WP-CLI command for listing files in the S3 bucket for debugging etc.

```
wp s3-uploads ls [<path>]
```

## Uploading files to S3

If you have an existing media library with attachment files, use the below command to copy them all to S3 from local disk.

```
wp s3-uploads upload-directory <from> <to> [--verbose]
```

For example, to migrate your whole uploads directory to S3, you'd run:

```
wp s3-uploads upload-directory /path/to/uploads/ uploads
```

There is also an all purpose `cp` command for arbitrary copying to and from S3.

```
wp s3-uploads cp <from> <to>
```

Note: as either `<from>` or `<to>` can be S3 or local locations, you must specify the full S3 location via `s3://mybucket/mydirectory` for example `cp ./test.txt s3://mybucket/test.txt`.

## Private Uploads

WordPress (and therefore S3 Uploads) default behaviour is that all uploaded media files are publicly accessible. In certain cases which may not be desireable. S3 Uploads supports setting S3 Objects to a `private` ACL and providing temporarily signed URLs for all files that are marked as private.

S3 Uploads does not make assumptions or provide UI for marking attachments as private, instead you should integrate the `s3_uploads_is_attachment_private` WordPress filter to control the behaviour. For example, to mark _all_ attachments as private:

```php
add_filter( 's3_uploads_is_attachment_private', '__return_true' );
```

Private uploads can be transitioned to public by calling `S3_Uploads::set_attachment_files_acl( $id, 'public-read' )` or vica-versa. For example:

```php
S3_Uploads::get_instance()->set_attachment_files_acl( 15, 'public-read' );
```

The default expiry for all private file URLs is 6 hours. You can modify this by using the `s3_uploads_private_attachment_url_expiry` WordPress filter. The value can be any string interpreted by `strtotime`. For example:

```php
add_filter( 's3_uploads_private_attachment_url_expiry', function ( $expiry ) {
	return '+1 hour';
} );
```

If you're using [Stream](https://wordpress.org/plugins/stream/) for audit logs, [S3 Uploads Audit](https://github.com/humanmade/s3-uploads-audit) is an add-on plugin which supports logging some S3 Uploads actions e.g any setting of ACL for files of an attachment. So you can install it for such audit functionality.

## Cache Control

You can define the default HTTP `Cache-Control` header for uploaded media using the
following constant:

```PHP
define( 'S3_UPLOADS_HTTP_CACHE_CONTROL', 30 * 24 * 60 * 60 );
	// will expire in 30 days time
```

You can also configure the `Expires` header using the `S3_UPLOADS_HTTP_EXPIRES` constant
For instance if you wanted to set an asset to effectively not expire, you could
set the Expires header way off in the future.  For example:

```PHP
define( 'S3_UPLOADS_HTTP_EXPIRES', gmdate( 'D, d M Y H:i:s', time() + (10 * 365 * 24 * 60 * 60) ) .' GMT' );
	// will expire in 10 years time
```

## Default Behaviour

As S3 Uploads is a plug and play plugin, activating it will start rewriting image URLs to S3, and also put
new uploads on S3. Sometimes this isn't required behaviour as a site owner may want to upload a large
amount of media to S3 using the `wp-cli` commands before enabling S3 Uploads to direct all uploads requests
to S3. In this case one can define the `S3_UPLOADS_AUTOENABLE` to `false`. For example, place the following
in your `wp-config.php`:

```PHP
define( 'S3_UPLOADS_AUTOENABLE', false );
```

To then enable S3 Uploads rewriting, use the wp-cli command: `wp s3-uploads enable` / `wp s3-uploads disable`
to toggle the behaviour.

## URL Rewrites

By default, S3 Uploads will use the canonical S3 URIs for referencing the uploads, i.e. `[bucket name].s3.amazonaws.com/uploads/[file path]`. If you want to use another URL to serve the images from (for instance, if you [wish to use S3 as an origin for CloudFlare](https://support.cloudflare.com/hc/en-us/articles/200168926-How-do-I-use-CloudFlare-with-Amazon-s-S3-Service-)), you should define `S3_UPLOADS_BUCKET_URL` in your `wp-config.php`:

```PHP
// Define the base bucket URL (without trailing slash)
define( 'S3_UPLOADS_BUCKET_URL', 'https://your.origin.url.example/path' );
```
S3 Uploads' URL rewriting feature can be disabled if the current website does not require it, nginx proxy to s3 etc. In this case the plugin will only upload files to the S3 bucket.
```PHP
// disable URL rewriting alltogether
define( 'S3_UPLOADS_DISABLE_REPLACE_UPLOAD_URL', true );
```

## S3 Object Permissions

The object permission of files uploaded to S3 by this plugin can be controlled by setting the `S3_UPLOADS_OBJECT_ACL`
constant. The default setting if not specified is `public-read` to allow objects to be read by anyone. If you don't
want the uploads to be publicly readable then you can define `S3_UPLOADS_OBJECT_ACL` as one of `private` or `authenticated-read`
in you wp-config file:

```PHP
// Set the S3 object permission to private
define('S3_UPLOADS_OBJECT_ACL', 'private');
```

For more information on S3 permissions please see the Amazon S3 permissions documentation.

## Custom Endpoints

Depending on your requirements you may wish to use an alternative S3 compatible object storage system such as Minio, Ceph,
Digital Ocean Spaces, Scaleway and others.

You can configure the endpoint by adding the following code to a file in the `wp-content/mu-plugins/` directory, for example `wp-content/mu-plugins/s3-endpoint.php`:

```php
<?php
// Filter S3 Uploads params.
add_filter( 's3_uploads_s3_client_params', function ( $params ) {
	$params['endpoint'] = 'https://your.endpoint.com';
	$params['use_path_style_endpoint'] = true;
	$params['debug'] = false; // Set to true if uploads are failing.
	return $params;
} );
```

**Note:** As of AWS SDK 3.337, S3 uses a [new type of integrity protection](https://github.com/aws/aws-sdk-php/issues/3062) which is not supported by all third-party S3-compatible APIs. If you experience errors, you may need to disable the new checksum functionality:

```php
add_filter( 's3_uploads_s3_client_params', function ( $params ) {
	// ...
	$params['request_checksum_calculation'] = 'when_required';
	$params['response_checksum_validation'] = 'when_required';
	return $params;
} );
```

## Temporary Session Tokens

If your S3 access is configured to require a temporary session token in addition to the access key and secret, you should configure the credentials using the following code:

```php
// Filter S3 Uploads params.
add_filter( 's3_uploads_s3_client_params', function ( $params ) {
	$params['credentials']['token'] = 'your session token here';
	return $params;
} );
```

## Offline Development

While it's possible to use S3 Uploads for local development (this is actually a nice way to not have to sync all uploads from production to development),
if you want to develop offline you have a couple of options.

1. Just disable the S3 Uploads plugin in your development environment.
2. Define the `S3_UPLOADS_USE_LOCAL` constant with the plugin active.

Option 2 will allow you to run the S3 Uploads plugin for production parity purposes, it will essentially mock
Amazon S3 with a local stream wrapper and actually store the uploads in your WP Upload Dir `/s3/`.

## Credits

Created by Human Made for high volume and large-scale sites. We run S3 Uploads on sites with millions of monthly page views, and thousands of sites.

Written and maintained by [Joe Hoyle](https://github.com/joehoyle). Thanks to all our [contributors](https://github.com/humanmade/S3-Uploads/graphs/contributors).

Interested in joining in on the fun? [Join us, and become human!](https://hmn.md/is/hiring/)
