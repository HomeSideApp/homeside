# Storage on S3 (Laravel Cloud and any S3-compatible provider)

The app stores files through the **default disk**. There is no explicit `Storage::disk('s3')`
anywhere in `app/`, so switching to a bucket is an environment change, not a code change.

## Requirements

`league/flysystem-aws-s3-v3` is a direct dependency (`^3.0`, installed 3.35.3). Without it the
`s3` driver cannot build its adapter, so nothing that writes files would work with
`FILESYSTEM_DISK=s3`. `aws/aws-sdk-php` was already present.

`config/filesystems.php` already declares the full disk, including the two keys that
S3-compatible providers need:

```php
's3' => [
    'driver' => 's3',
    'key' => env('AWS_ACCESS_KEY_ID'),
    'secret' => env('AWS_SECRET_ACCESS_KEY'),
    'region' => env('AWS_DEFAULT_REGION'),
    'bucket' => env('AWS_BUCKET'),
    'url' => env('AWS_URL'),
    'endpoint' => env('AWS_ENDPOINT'),
    'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
    'throw' => false,
    'report' => false,
],
```

## Environment

Add the two missing keys to `.env.example`, and set the real values in `.env` (or in the Laravel
Cloud dashboard, which injects them for you):

```dotenv
FILESYSTEM_DISK=s3
AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=
AWS_URL=
# Only for S3-compatible providers (Laravel Cloud buckets, R2, MinIO): point the
# client at the provider endpoint instead of the AWS default one.
AWS_ENDPOINT=
AWS_USE_PATH_STYLE_ENDPOINT=false
```

Notes:

- `FILESYSTEM_DISK` stays `local` by default; nothing breaks until you flip it.
- `AWS_URL` is only needed when you serve public URLs from the bucket.
- `AWS_ENDPOINT` is what makes non-AWS providers work. Set
  `AWS_USE_PATH_STYLE_ENDPOINT=true` when the provider does not support virtual-hosted style
  (`https://bucket.host/key`) and needs `https://host/bucket/key` instead.
- The `.env.example` file itself is protected by the agent safety policy, which blocks writes to
  `.env*` paths regardless of intent. Edit it by hand from the block above.

## Verifying without network access

The adapter can be resolved with dummy credentials; nothing is uploaded until a real operation:

```bash
vendor/bin/sail exec -T laravel.test php artisan tinker --execute="
config(['filesystems.disks.s3' => array_merge(config('filesystems.disks.s3'), [
  'key' => 'test-key', 'secret' => 'test-secret', 'region' => 'us-east-1',
  'bucket' => 'test-bucket', 'endpoint' => 'https://example.r2.cloudflarestorage.com',
  'use_path_style_endpoint' => true,
])]);
\$disk = Illuminate\Support\Facades\Storage::disk('s3');
echo get_class(\$disk->getAdapter()).PHP_EOL;"
```

Expected: `League\Flysystem\AwsS3V3\AwsS3V3Adapter`.

## Composer gotcha in this repository

Any `composer require` or `composer update` fails with

```
The `url` supplied for the path (../saas) repository does not exist
```

because `composer.json` declares a `path` repository at `../saas` (it backs `make install-saas`)
and the Sail container does not mount it by default. Do not remove that repository; mount it:

```bash
export SAIL_FILES=./compose.yaml:../saas/docker/saas-sail.yml
vendor/bin/sail up -d
vendor/bin/sail exec -u root -T laravel.test composer require <package>
```
