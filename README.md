#Stapler

Stapler can be used to generate file upload attachments for use with the wonderfully fabulous Laravel PHP Framework, authored by Taylor Otwell.
If you have used ruby on rails' paperclip plugin then you will be familiar with its syntax.  This bundle is inspired entirely from the work done
by the guys at thoughtbot for the Rails Paperclip bundle.  While not an exact duplicate, if you've used Paperclip before then you should be 
somewhat familiar with how this bundle works.

Stapler was created by Travis Bennett and Kelt Dockins, with thanks to Matthew Machuga for help and support.

##Requirements

Stapler currently requires php >= 5.4 (Stapler is implemented via the use of traits) as well as the Resizer bundle by Maikel Daloo.

## Installing the Bundle
Stapler is distributed as a bundle, which is how it should be used in your app.

Install the bundle using Artisan:

```php 
artisan bundle:install stapler
```

Update your `application/bundles.php` file with:

```php
'stapler' => array( 'auto' => true ),
```

## Quickstart
In your model:

```php
class User extends Eloquent {
	use Stapler\stapler;

    public function __construct($attributes = array(), $exists = false){
        parent::__construct($attributes, $exists);

        $this->has_attached_file('avatar', [
            'styles' => [
            	'medium' => '300x300',
                'thumb' => '100x100'
            ]
        ]);
    }
}
```

From the command line, use the migration generator:

php artisan stapler:fasten users logo
php artisan migrate

In your new view:

```php
<?= Form::open_for_files('user/new', 'POST') ?>
	<?= Form::file('avatar') ?>
<?= Form::close() ?>
```
In your controller:

```php
public function post_create()
{
	$user = User::create(['avatar' => Input::file('avatar')]);	
}
```

In your show view:
```php
<?= HTML::image($user->avatar_url() ?>
<?= HTML::image($user->avatar_url('medium') ?>
<?= HTML::image($user->avatar_url('thumb') ?>
```

To detach a file, simply set the attribute to null:
```php
$user->avatar = null;
$user->save();
```

## Configuration

Stapler works by attaching file uploads to records stored within a database table (model).  To accomplish this, four fields
(named after the attachemnt) are created (via stapler:fasten) in the corresponding table for any model containing a file attachment.  
For an attachment named 'avatar' the following fields would be created:

*   avatar_file_name
*   avatar_file_size
*   avatar_content_type
*   avatar_uploaded_at

Stapler can be configured to store files in a variety of ways.  This is done by defining a url string which points to the uploaded file asset.
Currently, the following interpolations are available for use:

*   :attachment - The name of the file attachment as declared in the has_attached_file function, e.g 'avatar'.
*   :class  - The classname of the model contaning the file attachment, e.g User.  Stapler can handle namespacing of classes.
*   :extension - The file extension type of the uploaded file, e.g '.jpg'
*   :filename - The name of the uploaded file, e.g 'some_file.jpg'
*   :id - The id of the corresponding database record for the uploaded file.
*   :id_partition - The partitioned id of the corresponding database record for the uploaded file, e.g an id = 1 is interpolated as 000/000/001.
*   :laravel_root - The path to the root of the laravel project.
*   :style - The resizing style of the file (images only), e.g 'thumbnail' or 'orginal'.

In a minimal configuration, the following settings are enabled by default:

*   'url' => '/system/:class/:attachment/:id_partition/:style/:filename',
*   'default_url' => '/:attachment/:style/missing.png',
*   'default_style' => 'original',
*   'styles' => [],
*   'keep_old_files' => false

**url**: The file system path to the file upload, relative to the public folder (document root) of the project.
**default_url**: The default file returned when no file upload is present for a record.
**default_style**: The default style returned from the Stapler file location helper methods.  An unaltered version of uploaded file
is always stored within the 'original' style, however the default_style can be set to point to any of the defined syles within the styles array.
**styles**: An array of image sizes defined for the file attachment.  Stapler will attempt to use the Resizer bundle to format the file upload
into the defined style.  To enable image cropping, insert a # symbol after the resizing options.  For example:

```php
'styles' => [
    'thumbnail' => '100x100#'
]
```

will create a copy of the file upload, resized and cropped to 100x100.