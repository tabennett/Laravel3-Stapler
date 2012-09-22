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
artisan bundle::install stapler
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

php artisan stapler::fasten users logo
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