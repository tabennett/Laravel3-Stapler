<?php namespace Stapler;

use Laravel\Event;
use Laravel\File;
use Laravel\Str;
use Laravel\IoC;

/**
 * Easy file attachment management for Eloquent.
 * 
 * Credits to the guys at thoughtbot for creating the
 * papclip plugin (rails) from which this bundle is inspired.
 * https://github.com/thoughtbot/paperclip
 * 
 * 
 * @package Stapler
 * @version 1.0
 * @author Travis Bennett <tandrewbennett@hotmail.com>
 * @link 	
 */

\Bundle::start('resizer');

trait Stapler
{
	/**
	 * All of the model's current file attachments.
	 *
	 * @var array
	 */
	protected $attached_files = [];

	/**
	 * Temporary file placeholder file files that are being uploaded.
	 *
	 * @var array
	 */
	protected $tmp_file = [];

	/**
	 * Returns a sorted list of all interpolations.  This list is currently hard coded
	 * (unlike its paperclip counterpart) but can be changed in the future so that
	 * all interpolation methods are broken off into their own class and returned automatically
	 *
	 * @return array
	*/
	protected static function interpolations() 
	{
		return [
			':attachment' => 'attachment',
			':basename' => 'basename',
			':class' => 'get_class',
			':extension' => 'extension',
			':filename' => 'filename',
			':id' => 'id',
			':id_partition' => 'id_partition',
			':laravel_root' => 'laravel_root',
			':style' => 'style'
		];
	}

	/**
	 * Add a new file attachment type to the list of available attachments.
	 *
	 * @param string $attachment
	 * @param array $options
	 * @return void
	*/
	protected function has_attached_file($attachment, $options = [])
	{
		$default_options = [
			'url' => '/system/:class/:attachment/:id_partition/:style/:filename',
			'default_url' => '/:attachment/:style/missing.png',
			'default_style' => 'original',
			'styles' => [],
			'keep_old_files' => false
		];

		// Merge the default_options and the options together so that 
		// options passed in may override the defaults.
		$options = array_merge($default_options, (array) $options);
		
		// Merge the 'original' style into the styles array.
		$options['styles'] = array_merge( (array) $options['styles'], ['original' => '']);

		// Validate the attachment style options.  Basically, we don't want to see a url with   
		// a :style interpolation in it without having corresponding style options to replace it with. 
		if (strpos($options['url'], ':style') === true)
		{
			if (empty($options['style']))
			{
		        trigger_error("Stapler: Invalid style options encountered, please check your Stapler config.", E_USER_NOTICE);
			}
		}

		// Validate the attachment url options.  A url is required to 
		// have either an :id or an :id_partition interpolation.
		if (preg_match("/:id\b/", $options['url']) !== 1 && preg_match("/:id_partition\b/", $options['url']) !== 1)
		{
			trigger_error("Stapler: Invalid file url, an :id or :id_partition is required.", E_USER_NOTICE);
		}

		// Add the attachment to the list of attachments to be processed during saving.
		$this->attached_files[$attachment] = $options;
		
		// Generate the name of the events Laravel will fire while processing 
		// this model and register listeners for them.
		$before_save = 'eloquent.saving: '.get_class($this);
		$after_save = 'eloquent.saved: '.get_class($this);
		$after_delete = 'eloquent.deleted: '.get_class($this);

        if (!Event::listeners($before_save))
        {
        	Event::listen($before_save, [get_class(), 'set_attributes']);
        }
 
        if (!Event::listeners($after_save))
        {
        	Event::listen($after_save, [get_class(), 'upload']);
        }

        if (!Event::listeners($after_delete))
        {
        	Event::listen($after_delete, [get_class(), 'remove_files']);
        }
	}

	/**
	 * Set values for the model's file attribute fields before it's saved.
	 *
	 * @param model $model - The instance of the model object that triggering the save event.
	 * @return void
	*/
	public static function set_attributes($model)
	{
		// Loop through each attachment type, if there's a corresponding model attribute
		// containing a file then we'll fill the model attributes for that attachment type.
		foreach($model->attached_files as $attachment => $attachment_options) 
		{
			if (array_key_exists($attachment, $model->attributes))
			{
				if (is_null($model->attributes[$attachment])) 
				{
					// empty the model's file attachment attributes.
					$attrs = [
						"{$attachment}_file_name" => '',
						"{$attachment}_file_size" => '',
						"{$attachment}_content_type" => '',
						"{$attachment}_uploaded_at" => ''
					];

					// Set the file attributes in the model.
					$model->fill($attrs, true);

					// Store the file attachment in the $tmp_file member variable for later use.
					$model->tmp_file[$attachment] = $model->attributes[$attachment];
				}
				elseif (is_array($model->attributes[$attachment]) && $model->attributes[$attachment]['error'] == 0) 
				{
					// Validate the authenticity of the file upload
					if (!is_uploaded_file($model->attributes[$attachment]['tmp_name'])) 
					{
						throw new \Exception("Stapler: File upload hijacking detected!");
					}	
					
					// Create an array of attributes to hold meta information about file.
					$attrs = [
						"{$attachment}_file_name" => $model->attributes[$attachment]['name'],
						"{$attachment}_file_size" => $model->attributes[$attachment]['size'],
						"{$attachment}_content_type" => $model->attributes[$attachment]['type'],
						"{$attachment}_uploaded_at" => date('Y-m-d H:i:s')
					];

					// Set the file attributes in the model.
					$model->fill($attrs, true);

					// Store the file attachment in the $tmp_file member variable for later use.
					$model->tmp_file[$attachment] = $model->attributes[$attachment];
				}

				// Remove the file attachment from the model attributes array.
				// This prevents Eloquent from trying to save the file attachment to the database.
				unset($model->attributes[$attachment]);
			}
		}
	}

	/**
	 * Process the file upload(s).
	 *
	 * @param model $model - The instance of the model object that triggered the save event.
	 * @return void
	*/
	public static function upload($model)
	{
		// Loop through each attachment type, if there's a corresponding model attribute
		// containing a file we'll then we'll attempt to process the file.
		foreach ($model->attached_files as $attachment => $attachment_options)
		{
			if (array_key_exists($attachment, $model->tmp_file) && is_null($model->tmp_file[$attachment])) 
			{
				// Build a path to the id/id_partition directory for this model
				$path = $model->path($attachment);
				$offset = $model->get_offset($path, $attachment);
				$directory = substr($path, 0, $offset);

				// Empty the directory
				$model->empty_directory($directory);
				continue;
			}

			if (array_key_exists($attachment, $model->tmp_file) && !empty($model->tmp_file[$attachment]))
			{
				// Loop through each stye and process the file upload.
				foreach ($attachment_options['styles'] as $style_name => $style_dimensions)
				{
					$file_path = $model->path($attachment, $style_name);

					// Create the directory if it doesn't already exist.
					if (!is_dir(dirname($file_path))) {
						mkdir(dirname($file_path), 0777, true);
					}

					// Remove previous uploads.
					if (!$attachment_options['keep_old_files']) {
						$file_directory = dirname($file_path);
						$model->empty_directory($file_directory);
					}

					// If the file is an image, process and move it, otherwise just move it where it belongs.
					if (!empty($style_dimensions) && $model->is_image($model->tmp_file[$attachment]['tmp_name'])) {
						$success = $model->process_image($attachment, $file_path, $style_dimensions);
					}
					else {
						$success = move_uploaded_file($model->tmp_file[$attachment]['tmp_name'], $file_path);
					}
					
					if (!$success) {
				        throw new \Exception("Stapler: Failed to save file.");
					}
				}
			}
		}

		// Reset the $tmp_file variable
		$model->tmp_file[$attachment] = [];
	}

	/**
	 * Remove file uploads from the file system after record deletion.
	 *
	 * @param model $model - The instance of the model object that triggered the delete event.
	 * @return void
	*/
	public static function remove_files($model)
	{
		foreach ($model->attached_files as $attachment => $attachment_options) 
		{
			// Build a path to the id/id_partition directory for this model
			$path = $model->path($attachment);
			$offset = $model->get_offset($path, $attachment);
			$directory = substr($path, 0, $offset);
			
			// Remove the directory and all the files within it.
			$model->empty_directory($directory, true);
		}
	}

	/**
	 * process_image method 
	 * 
	 * Parse the given style dimensions to extract out the file processing options,
	 * perform any necessary image resizing for a given style using the Resizer bundle.
	 *
	 * @param  string $attachment - The name of the current file attachment being processed.
	 * @param  string $file_path - The location in the file system where the file will be saved after processing.
	 * @param  array $style_dimensions - The given image sizing style (50x50, 10, etc).
	 * @return boolean
	 */
	public function process_image($attachment, $file_path, $style_dimensions)
	{
		$resizer = \Laravel\IoC::resolve('Resizer', [$this->tmp_file[$attachment]]);

		if (strpos($style_dimensions, 'x') === false) 
		{
			// Width given, height automagically selected to preserve aspect ratio (landscape).
			$width = $style_dimensions;
			return $resizer->resize($width, null, 'landscape')
					->save($file_path);
		}
		
		$dimensions = explode('x', $style_dimensions);
		$width = $dimensions[0];
		$height = $dimensions[1];
		
		if (empty($width)) 
		{
			// Height given, width automagically selected to preserve aspect ratio (portrait).
			return $resizer->resize(null, $height, 'portrait')
			->save($file_path);
		}
		
		$resizing_option = substr($height, -1, 1);
		switch ($resizing_option) {
			case '#':
				// Resize, then crop.
				$height = rtrim($height, '#');
				$success = $resizer->resize($width, $height, 'crop')
				->save($file_path);
				break;

			case '!':
				// Resize by exact width/height (does not preserve aspect ratio).
				$height = rtrim($height, '!');
				$success = $resizer->resize($width, $height, 'exact')
				->save($file_path);
				break;
			
			default:
				// Let the script decide the best way to resize.
				$success = $resizer->resize($width, $height, 'auto')
				->save($file_path);
				break;
		}

		return $success;
	}

	/**
	 * Handle dynamic method calls on the model.
	 *
	 * This allows for the creation of our file url/path convenience methods
	 * on the model: {attachment}_file path and {attachment}_file url.  If 
	 * the format of the called function doesn't match these functions we'll 
	 * hand control back over to the __call function of the parent model class.
	 *
	 * @param  string  $method
	 * @param  array   $parameters
	 * @return mixed
	 */
	public function __call($method, $parameters = null)
	{
		foreach($this->attached_files as $key => $value)
		{
			if (starts_with($method, "{$key}_"))
			{
				$pieces = explode('_', $method);
				switch ($pieces[1]) {
					case 'path':
						if (!empty($parameters)){
							return $this->return_resource('path', $pieces[0], $parameters[0]);
						}
						else {
							return $this->return_resource('path', $pieces[0]);
						}
						
						break;
					
					case 'url':
						if (!empty($parameters)){
							return $this->return_resource('url', $pieces[0], $parameters[0]);
						}
						else{
							return $this->return_resource('url', $pieces[0]);
						}
						
						break;

					default:
						break;
				}
			}
		}

		return parent::__call($method, $parameters);
	}

	/**
	 * Returns a file upload resource location (path or url).
	 *
	 * @param string $type
	 * @param string $attachment
	 * @param string $style
	 * @return string
	*/
	public function return_resource($type, $attachment, $style = '')
	{
		// If the given resource doesn't exist we'll fall back to the default
		// url passed in to the has_attached_file options array.
		switch ($type) {
			case 'path':
				$resource = $this->path($attachment, $style);

				if (file_exists($resource)) {
					return $resource;
				}
				else {
					return $this->default_path($attachment, $style);
				}

				break;
			case 'url':
				$resource = $this->absolute_url($attachment, $style);

				if (file_exists($resource)) {
					return $this->url($attachment, $style);
				}
				else {
					return $this->default_url($attachment, $style);
				}

				break;
			default:
				break;
		}

		return '';
	}

	/**
	 * Utility function for detecing whether a given file upload is an image.
	 *
	 * @param string $file
	 * @return bool
	*/
	protected function is_image($file)
	{
		if (File::is('jpg', $file) || File::is('jpeg', $file) || File::is('gif', $file) || File::is('png', $file))
		{
			return true;
		}

		return false;
	}

	/**
	 * Generates the file system path to an uploaded file.  This is used for saving files, etc.
	 *
	 * @param string $attachment
	 * @param string $style
	 * @return string
	*/
	protected function path($attachment, $style = '')
	{
		return $this->laravel_root($attachment, $style).'/public'.$this->url($attachment, $style);
	}

	/**
	 * Generates the default path if no file attachment is present.
	 *
	 * @param string $attachment
	 * @param string $style
	 * @return string
	*/
	protected function default_path($attachment, $style = '')
	{
		return $this->laravel_root($attachment, $style).'/public'.$this->default_url($attachment, $style);
	}

	/**
	 * Generates the absolute url to an uploaded file.
	 * 
	 * @param  string $attachment 
	 * @param  string $style      
	 * @return string             
	 */
	protected function absolute_url($attachment, $style= '')
	{
		return path('public').$this->url($attachment, $style);
	}

	/**
	 * Generates the url to a file upload.
	 *
	 * @param string $attachment
	 * @param string $style
	 * @return string
	*/
	protected function url($attachment, $style = '')
	{

		$url = $this->attached_files[$attachment]['url'];
		
		return $this->interpolate_string($attachment, $url, $style);
	}

	/**
	 * Generates the default url if no file attachment is present.
	 *
	 * @param string $attachment
	 * @param string $style
	 * @return string
	*/
	protected function default_url($attachment, $style = '')
	{
		if (isset($this->attached_files[$attachment]['default_url']))
		{
			$url = $this->attached_files[$attachment]['default_url'];
			return $this->interpolate_string($attachment, $url, $style);
		}
		
		return '';
	}

	/**
	 * Interpolating a string.
	 *
	 * @return string
	*/
	protected function interpolate_string($attachment, $string, $style = '')
	{
		foreach (static::interpolations() as $key => $value)
		{
			$string = preg_replace("/$key\b/", $this->$value($attachment, $style), $string);
		}

		return $string;
	}

	/**
	 * Returns the file name.
	 *
	 * @return string
	*/
	protected function filename($attachment, $style = '') 
	{
		return $this->get_attribute("{$attachment}_file_name");
	}

	/**
	 * Returns the root of the Laravel project.
	 *
	 * @return string
	*/
	protected function laravel_root($attachment, $style = '') 
	{
		return rtrim(path('base'), '/');
	}

	/**
	 * Returns the current class name, taking into account namespaces, e.g
	 * 'Swingline\Stapler' will become Swingline/Stapler.
	 *
	 * @return string
	*/
    protected function get_class($attachment, $style = '') 
    {
    	return $this->handle_backslashes(get_class($this));
    }

    /**
	 * Returns the basename portion of the attached file, e.g 'file' for file.jpg.
	 *
	 * @return string
	*/
	protected function basename($attachment, $style = '') 
	{
		return pathinfo($this->get_attribute("{$attachment}_file_name"), PATHINFO_FILENAME);
	}

	/**
	 * Returns the extension of the attached file, e.g 'jpg' for file.jpg.
	 *
	 * @return string
	*/
	protected function extension($attachment, $style = '') 
	{
		return File::extension($this->get_attribute("{$attachment}_file_name"));
	}

	/**
	 * Returns the id of the current object instance.
	 *
	 * @return string
	*/
    protected function id ($attachment, $style = '') 
    {
      return $this->get_key();
    }

    /**
	 * Generates the id partition of a record, e.g
	 * return /000/001/234 for an id of 1234.
	 *
	 * @return mixed
	*/
	protected function id_partition($attachment, $style = '')
	{
		$id = $this->get_key();

		if (is_numeric($id))
		{
			return implode('/', str_split(sprintf('%09d', $id), 3));
		}
		elseif (is_string($id))
		{
			return implode('/', array_slice(str_split($id, 3), 0, 3));
		}
		else
		{
			return null;
		}
	}

    /**
	 * Returns the pluralized form of the attachment name. e.g.
     * "avatars" for an attachment of :avatar.
	 *
	 * @return string
	*/
	protected function attachment($attachment, $style = '') 
	{
		return Str::plural($attachment);
	}

	/**
	 * Returns the style, or the default style if an empty style is supplied.
	 *
	 * @return string
	*/
	protected function style($attachment, $style = '') 
	{
		return empty($style) ? $this->attached_files[$attachment]['default_style'] : $style;
	}

	/**
	 * Utility function to return the string offset of the directory
	 * portion of a file path with an :id or :id_partition interpolation.
	 *
	 * <code>
	 *		// Returns an offset of '27'.
	 *      $directory = '/some_directory/000/000/001/some_file.jpg'
	 *		return $this->get_offset($directory, $attachment);
	 * </code>
	 *
	 * @param string $string
	 * @return string
	 */
	protected function get_offset($string, $attachment, $style = '') 
	{
		// Get the partition of the id
		$id_partition = $this->id_partition($attachment, $style);
		$match = strpos($string, $id_partition);
		
		if ($match !== false)
		{
			// Id partitioning is being used, so we're looking for a
			// directory that has the pattern /000/000/001 at the end,
			// so we know we'll need to add 11 spaces to the string offset.
			$offset = $match + 11;
		}
		else
		{
			// Id partitioning is not being used, so we're looking for
			// a directory that has the pattern /1 at the end, so we'll
			// need to add the length of the record id + 1 to the string offset.
			$match = strpos($string, (string) $this->get_key());
			$offset = $match + strlen($this->get_key());
		}

		return $offset;
	}

	/**
	 * Utitlity function to turn a backslashed string into a string
	 * suitable for use in a file path, e.g '\foo\bar' becomes 'foo/bar'.
	 *
	 * @param string $string
	 * @return string
	 */
	protected function handle_backslashes($string) 
	{
		return str_replace('\\', '/', ltrim($string, '\\'));
	}

	/**
	 * Utitlity function to recursively delete the files in a directory.
	 *
	 * @desc Recursively loops through each file in the directory and deletes it.
	 * @param string $directory
	 * @param boolean $delete_directory
	 * @return void
	 */
	protected function empty_directory($directory, $delete_directory = false)
	{
		if (!$directory_handle = opendir($directory)) {
			return;
		}
		
		while (false !== ($object = readdir($directory_handle))) 
		{
			if ($object == '.' || $object == '..') {
				continue;
			}

			if (!is_dir($directory.'/'.$object)) {
				unlink($directory.'/'.$object);
			}
			else {
				$this->empty_directory($directory.'/'.$object, true);	// The object is a folder, recurse through it.
			}
		}
		
		if ($delete_directory)
		{
			closedir($directory_handle);
			rmdir($directory);
		}
	}

	/**
	 * Utility function for arranging the global $_FILE uploades array so that each file
	 * is stored in its own array as opposed to having its values spread across child arrays.
	 *
	 * @param array $files
	 * @return array $arranged_files;
	 */
    public static function arrange_files($files = []) 
    {
        $arranged_files = [];

		foreach ($files['error'] as $key => $error) 
		{
		    $arranged_files[$key]['name'] = $files['name'][$key];
		    $arranged_files[$key]['type'] = $files['type'][$key];
		    $arranged_files[$key]['tmp_name'] = $files['tmp_name'][$key];
		    $arranged_files[$key]['error'] = $files['error'][$key];
		    $arranged_files[$key]['size'] = $files['size'][$key];
		}

		return $arranged_files;
    }
}