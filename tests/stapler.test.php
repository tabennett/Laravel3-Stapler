<?php

Bundle::start('stapler');

class TestObject extends Eloquent {
    use Stapler\stapler;
    
    public function __construct($attributes = array(), $exists = false){
        parent::__construct($attributes, $exists);

        $this->has_attached_file('testing', [
            'styles' => [
                'thumbnail' => '100x100#'
            ],
            'url' => '/system/:attachment/:id_partition/:style/:filename',
            'default_url' => '/:attachment/:style/missing.jpg'
        ]);
    }
}

class StaplerTest extends PHPUnit_Framework_TestCase {

    protected $test_object;

    protected static function getMethod($name) {
        $class = new ReflectionClass('TestObject');
        $method = $class->getMethod($name);
        $method->setAccessible(true);
        return $method;
    }


	/**
	 * setUp method
	 *
	 * @return void
	 */
	public function setUp(){ 
        // Spoof $_SERVER['DOCUMENT_ROOT']
        $_SERVER['DOCUMENT_ROOT'] = path('public');

        // Create a simulated model to attach the file to
        $this->test_object = new TestObject;
	}
  	
	/**
	 * tearDown method
	 *
	 * @return void
	 */
  	public function tearDown(){

  	}

    /**
     * test_remove_files method
     *
     * Test that the files belonging to an object are deleted.
     *
     */
    public function test_remove_files() 
    {
        // Create a fake upload directory.
        mkdir(path('public').'system/testings/000/000/001/thumbnail', 0, true);
        copy(path('bundle').'stapler/tests/test.jpg', path('public').'system/testings/000/000/001/thumbnail/test.jpg');

        // Set some object variables to mock the file upload.
        $this->test_object->id = 1;
        $this->test_object->testing_file_name = 'test.jpg';

        // Attempt to remove the file.
        $this->test_object->remove_files($this->test_object);
        $this->assertFileNotExists(path('bundle').'system/testings/000/000/001/thumbnail/test.jpg');
    
        // Remove the fake file upload directories
        if (file_exists(path('public').'system/testings/000/000/001/thumbnail/test.jpg')) {
             unlink(path('public').'system/testings/000/000/001/thumbnail/test.jpg');
        }

        if (is_dir(path('public').'system/testings/000/000/001/thumbnail')) {
            rmdir(path('public').'system/testings/000/000/001/thumbnail');
        }

        if (is_dir(path('public').'system/testings/000/000/001')) {
            rmdir(path('public').'system/testings/000/000/001');
        }
        
        rmdir(path('public').'system/testings/000/000');
        rmdir(path('public').'system/testings/000');
        rmdir(path('public').'system/testings');
    }

    /**
     * test___call method
     *
     * Test proper file resources are returned form convience methods.
     *
     * @dataProvider test___call_provider
     */
    public function test___call($method, $parameters, $spoof_directory, $expected_output)      
    {
        // Create a fake upload directory.
        mkdir(path('public').'system/testings/000/000/001/thumbnail', 0, true);
        copy(path('bundle').'stapler/tests/test.jpg', path('public').'system/testings/000/000/001/thumbnail/test.jpg');

        // Create a fake default url directory.
        mkdir(path('public').'testings/thumbnail', 0, true);
        copy(path('bundle').'stapler/tests/test.jpg', path('public').'testings/thumbnail/missing.jpg');

        // if spoof directory is set we'll create a quick directory under the default style 
        // (original) with our test file in it so that we can test style delegation for some cases.
        if($spoof_directory) {
            // Create a fake default style directory.
            mkdir(path('public').'system/testings/000/000/001/original', 0, true);
            copy(path('bundle').'stapler/tests/test.jpg', path('public').'system/testings/000/000/001/original/test.jpg');
        }

        // Set some object variables to mock the file upload.
        $this->test_object->id = 1;
        $this->test_object->testing_file_name = 'test.jpg';

        $actual_output = $this->test_object->__call($method, $parameters);
        $this->assertEquals($expected_output, $actual_output);

        // After the test we'll tear the default style directory down if it was spoofed.
        if($spoof_directory) {
            // Remove the fake default style directory.
            unlink(path('public').'system/testings/000/000/001/original/test.jpg');
            rmdir(path('public').'system/testings/000/000/001/original');
        }

        // Remove the fake file upload directories
        unlink(path('public').'system/testings/000/000/001/thumbnail/test.jpg');
        rmdir(path('public').'system/testings/000/000/001/thumbnail');
        rmdir(path('public').'system/testings/000/000/001');
        rmdir(path('public').'system/testings/000/000');
        rmdir(path('public').'system/testings/000');
        rmdir(path('public').'system/testings');

        // Remove the fake default url directories
        unlink(path('public').'testings/thumbnail/missing.jpg');
        rmdir(path('public').'testings/thumbnail');
        rmdir(path('public').'testings');
    }

     /**
     * test__call method data provider
     *
     * @return array
     */
    public function test___call_provider(){
        return [
            ['testing_path', ['thumbnail'], false, path('base').'public/system/testings/000/000/001/thumbnail/test.jpg'],
            ['testing_url', ['thumbnail'], false, '/system/testings/000/000/001/thumbnail/test.jpg'],
            ['testing_path', [''], false, path('base').'public/testings/original/missing.jpg'],
            ['testing_url', [''], false, '/testings/original/missing.jpg'],
            ['testing_path', [''], true, path('base').'public/system/testings/000/000/001/original/test.jpg'],
            ['testing_url', [''], true, '/system/testings/000/000/001/original/test.jpg']
        ];
    }

     /**
     * test_return_resource method
     *
     * Test that a proper file resource is returned.
     *
     * @dataProvider test_return_resource_provider
     */
    public function test_return_resource($type, $attachment, $style, $spoof_directory, $expected_output)
    {
        // Create a fake upload directory.
        mkdir(path('public').'system/testings/000/000/001/thumbnail', 0, true);
        copy(path('bundle').'stapler/tests/test.jpg', path('public').'system/testings/000/000/001/thumbnail/test.jpg');

        // Create a fake default url directory.
        mkdir(path('public').'testings/thumbnail', 0, true);
        copy(path('bundle').'stapler/tests/test.jpg', path('public').'testings/thumbnail/missing.jpg');

        // if spoof directory is set we'll create a quick directory under the default style 
        // (original) with our test file in it so that we can test style delegation for some cases.
        if($spoof_directory) {
            // Create a fake default style directory.
            mkdir(path('public').'system/testings/000/000/001/original', 0, true);
            copy(path('bundle').'stapler/tests/test.jpg', path('public').'system/testings/000/000/001/original/test.jpg');
        }

        // Set some object variables to mock the file upload.
        $this->test_object->id = 1;
        $this->test_object->testing_file_name = 'test.jpg';

        $actual_output = $this->test_object->return_resource($type, $attachment, $style);
        $this->assertEquals($expected_output, $actual_output);

        // After the test we'll tear the default style directory down if it was spoofed.
        if($spoof_directory) {
            // Remove the fake default style directory.
            unlink(path('public').'system/testings/000/000/001/original/test.jpg');
            rmdir(path('public').'system/testings/000/000/001/original');
        }

        // Remove the fake file upload directories
        unlink(path('public').'system/testings/000/000/001/thumbnail/test.jpg');
        rmdir(path('public').'system/testings/000/000/001/thumbnail');
        rmdir(path('public').'system/testings/000/000/001');
        rmdir(path('public').'system/testings/000/000');
        rmdir(path('public').'system/testings/000');
        rmdir(path('public').'system/testings');

        // Remove the fake default url directories
        unlink(path('public').'testings/thumbnail/missing.jpg');
        rmdir(path('public').'testings/thumbnail');
        rmdir(path('public').'testings');
    }

     /**
     * test_return_resource method data provider
     *
     * @return array
     */
    public function test_return_resource_provider()
    {
        return [
            ['path', 'testing', 'thumbnail', false, path('base')."public/system/testings/000/000/001/thumbnail/test.jpg"],
            ['url', 'testing', 'thumbnail', false, "/system/testings/000/000/001/thumbnail/test.jpg"],
            ['path', 'testing', '', false, path('base')."public/testings/original/missing.jpg"],
            ['url', 'testing', '', false, "/testings/original/missing.jpg"],
            ['path', 'testing', '', true, path('base')."public/system/testings/000/000/001/original/test.jpg"],
            ['url', 'testing', '', true, "/system/testings/000/000/001/original/test.jpg"]
        ];
    }

    /**
     * test_file_path method
     *
     * Test that a file path is properly formed.
     *
     * @dataProvider test_path_provider
     */
    public function test_path($attachment, $style, $expected_output)
    {
        // Set some object variables to mock the file upload.
        $this->test_object->id = 1;
        $this->test_object->testing_file_name = 'test.jpg';

        $method = self::getMethod('path');
        $actual_output = $method->invokeArgs($this->test_object, [$attachment, $style]);
        $this->assertEquals($expected_output, $actual_output);
    }

    /**
     * test_file_path method data provider
     *
     * @return array
     */
    public function test_path_provider()
    {
        return [
            ['testing', 'thumbnail', path('base')."public/system/testings/000/000/001/thumbnail/test.jpg"],
            ['testing', 'thumbnail', path('base')."public/system/testings/000/000/001/thumbnail/test.jpg"],
            ['testing', '', path('base')."public/system/testings/000/000/001/original/test.jpg"],
            ['testing', '', path('base')."public/system/testings/000/000/001/original/test.jpg"]
        ];
    }

    /**
     * test_default_path method
     *
     * Test that the default path is properly formed.
     *
     * @dataProvider test_default_path_provider
     * @return void
     */
    public function test_default_path($attachment, $style, $expected_output)
    {
        $method = self::getMethod('default_path');
        $actual_output = $method->invokeArgs($this->test_object, [$attachment, $style]);
        $this->assertEquals($expected_output, $actual_output);
    }

    /**
     * test_default_path method data provider
     *
     * @return array
     */
    public function test_default_path_provider()
    {
        return [
            ['testing', 'thumbnail', path('base').'public/testings/thumbnail/missing.jpg'],
            ['testing', '', path('base').'public/testings/original/missing.jpg'],
        ];
    }

    /**
     * test_file_url method
     *
     * Test that a file url is properly formed.
     *
     * @dataProvider test_url_provider
     */
    public function test_url($attachment, $style, $expected_output)
    {
        // Set some object variables to mock the file upload.
        $this->test_object->id = 1;
        $this->test_object->testing_file_name = 'test.jpg';

        $method = self::getMethod('url');
        $actual_output = $method->invokeArgs($this->test_object, [$attachment, $style]);
        $this->assertEquals($expected_output, $actual_output);
    }

    /**
     * test_file_url method data provider
     *
     * @return array
     */
    public function test_url_provider()
    {
        return [
            ['testing', 'thumbnail', '/system/testings/000/000/001/thumbnail/test.jpg'],
            ['testing', 'thumbnail', '/system/testings/000/000/001/thumbnail/test.jpg'],
            ['testing', '', '/system/testings/000/000/001/original/test.jpg'],
            ['testing', '', '/system/testings/000/000/001/original/test.jpg']
        ];
    }

    /**
     * test_default_url method
     *
     * Test that the default url is properly formed.
     *
     * @dataProvider test_default_url_provider
     * @return void
     */
    public function test_default_url($attachment, $style, $expected_output)
    {
        $method = self::getMethod('default_url');
        $actual_output = $method->invokeArgs($this->test_object, [$attachment, $style]);
        $this->assertEquals($expected_output, $actual_output);
    }

    /**
     * test_default_url method data provider
     *
     * @return array
     */
    public function test_default_url_provider()
    {
        return [
            ['testing', 'thumbnail', '/testings/thumbnail/missing.jpg'],
            ['testing', '', '/testings/original/missing.jpg'],
        ];
    }

    /**
     * test_interpolate_string method 
     *
     * Test that an interpolated string is properly formed.
     *
     * @dataProvider test_interpolate_string_provider
     * @return void
     */
    public function test_interpolate_string($input, $expected_output, $style)
    {
        $this->test_object->id = 1;
        $this->test_object->testing_file_name = 'test.jpg';

        $method = self::getMethod('interpolate_string');
        $actual_output = $method->invokeArgs($this->test_object, ['testing', $input, $style]);
        $this->assertEquals($expected_output, $actual_output);
    }

    /**
     * test_interpolate_string method data provider
     *
     * @return array
     */
    public function test_interpolate_string_provider()
    {
        // All possible interpolation options with id_partitioning
        $uninterpolated_strings[] = '/system/:class/:attachment/:id_partition/:style/:filename';
        $interpolated_strings[] = '/system/TestObject/testings/000/000/001/original/test.jpg';
        $styles[] = '';

        // All possible interpolation options with id_partitioning
        $uninterpolated_strings[] = '/system/:class/:attachment/:id_partition/:style/:filename';
        $interpolated_strings[] = '/system/TestObject/testings/000/000/001/thumbnail/test.jpg';
        $styles[] = 'thumbnail';

        // All possible interpolation options with id_partitioning, excluding class
        $uninterpolated_strings[] = '/system/:attachment/:id_partition/:style/:filename';
        $interpolated_strings[] = '/system/testings/000/000/001/original/test.jpg';
        $styles[] = '';

        // All possible interpolation options with id_partitioning, excluding class
        $uninterpolated_strings[] = '/system/:attachment/:id_partition/:style/:filename';
        $interpolated_strings[] = '/system/testings/000/000/001/thumbnail/test.jpg';
        $styles[] = 'thumbnail';

        // All possible interpolation options with id_partitioning, excluding class and style
        $uninterpolated_strings[] = '/system/:attachment/:id_partition/:filename';
        $interpolated_strings[] = '/system/testings/000/000/001/test.jpg';
        $styles[] = '';

        // All possible interpolation options with id_partitioning, excluding class and style
        $uninterpolated_strings[] = '/system/:attachment/:id_partition/:filename';
        $interpolated_strings[] = '/system/testings/000/000/001/test.jpg';
        $styles[] = 'thumbnail';

        // All possible interpolation options with id_partitioning, excluding class, style, and attachment
        $uninterpolated_strings[] = '/system/:id_partition/:filename';
        $interpolated_strings[] = '/system/000/000/001/test.jpg';
        $styles[] = '';

        // All possible interpolation options with id_partitioning, excluding class, style, and attachment
        $uninterpolated_strings[] = '/system/:id_partition/:filename';
        $interpolated_strings[] = '/system/000/000/001/test.jpg';
        $styles[] = 'thumbnail';

        // All possible interpolation options with id
        $uninterpolated_strings[] = '/system/:class/:attachment/:id/:style/:filename';
        $interpolated_strings[] = '/system/TestObject/testings/1/original/test.jpg';
        $styles[] = '';

        // All possible interpolation options with id
        $uninterpolated_strings[] = '/system/:class/:attachment/:id/:style/:filename';
        $interpolated_strings[] = '/system/TestObject/testings/1/thumbnail/test.jpg';
        $styles[] = 'thumbnail';

        // All possible interpolation options with id, excluding class
        $uninterpolated_strings[] = '/system/:attachment/:id/:style/:filename';
        $interpolated_strings[] = '/system/testings/1/original/test.jpg';
        $styles[] = '';

        // All possible interpolation options with id, excluding class
        $uninterpolated_strings[] = '/system/:attachment/:id/:style/:filename';
        $interpolated_strings[] = '/system/testings/1/thumbnail/test.jpg';
        $styles[] = 'thumbnail';

        // All possible interpolation options with id, excluding class and style
        $uninterpolated_strings[] = '/system/:attachment/:id/:filename';
        $interpolated_strings[] = '/system/testings/1/test.jpg';
        $styles[] = '';

        // All possible interpolation options with id, excluding class and style
        $uninterpolated_strings[] = '/system/:attachment/:id/:filename';
        $interpolated_strings[] = '/system/testings/1/test.jpg';
        $styles[] = 'thumbnail';

        // All possible interpolation options with id, excluding class, style, and attachment
        $uninterpolated_strings[] = '/system/:id/:filename';
        $interpolated_strings[] = '/system/1/test.jpg';
        $styles[] = '';

        // All possible interpolation options with id, excluding class, style, and attachment
        $uninterpolated_strings[] = '/system/:id/:filename';
        $interpolated_strings[] = '/system/1/test.jpg';
        $styles[] = 'thumbnail';

        // Merge the values together into an array of arrays
        $data = [];
        foreach($uninterpolated_strings as $key => $value){
            $data[] = [$uninterpolated_strings[$key], $interpolated_strings[$key], $styles[$key]];
        }
       
       return $data;
    }

    /**
     * test_filename method
     *
     * Test that the attachment filename is properly returned.
     *
     * @dataProvider test_filename_provider
     * @return void
     */
    public function test_filename($filename)
    {
        $this->test_object->testing_file_name = $filename;

        $method = self::getMethod('filename');
        $actual_output = $method->invokeArgs($this->test_object, ['testing']);
        $this->assertEquals($filename, $actual_output);
    }

    /**
     * test_filename method data provider
     *
     * @return array
     */
    public function test_filename_provider()
    {
        return [
            ['testing.jpg'],
            ['testing.png'],
            ['testing.gif'],
            ['testing.pdf'],
            ['testing.txt'],
            ['testing.doc']
        ];
    }

    /**
     * test_laravel_root method
     *
     * Test that Laravel root directory is properly returned.
     *
     * @return void
     */
    public function test_laravel_root()
    {
        $expected_output = rtrim(path('base'), '/');
        $method = self::getMethod('laravel_root');
        $actual_output = $method->invokeArgs($this->test_object, ['testing']);
        $this->assertEquals($expected_output, $actual_output);
    }

    /**
     * test_class method
     *
     * Test that a class name is properly returned.
     *
     * @dataProvider test_get_class_provider
     * @return void
     */
    public function test_get_class($expected_output)
    {
        $method = self::getMethod('get_class');
        $actual_output = $method->invokeArgs($this->test_object, ['testing']);

        $this->assertEquals($expected_output, $actual_output);
    }

    /**
     * test_class method data provider
     *
     * @return array
     */
    public function test_get_class_provider()
    {
        return [
            ['TestObject']
        ];
    }

    /**
     * test_basename method
     *
     * Test that an basename is properly returned.
     *
     * @dataProvider test_basename_provider
     * @return void
     */
    public function test_basename($input, $expected_output)
    {
        $this->test_object->testing_file_name = $input;

        $method = self::getMethod('basename');
        $actual_output = $method->invokeArgs($this->test_object, ['testing', $input]);
        $this->assertEquals($expected_output, $actual_output);
    }

    /**
     * test_basename method data provider
     *
     * @return array
     */
    public function test_basename_provider()
    {
        return [
            ['testing1.2.jpg', 'testing1.2'],
            ['testing2_3.jpeg', 'testing2_3'],
            ['testing3-4.jpeg', 'testing3-4'],
            ['testing3-4.5.jpeg', 'testing3-4.5'],
        ];
    }

    /**
     * test_extension method
     *
     * Test that an extension is properly returned.
     *
     * @dataProvider test_extension_provider
     * @return void
     */
    public function test_extension($input, $expected_output)
    {
        $this->test_object->testing_file_name = $input;

        $method = self::getMethod('extension');
        $actual_output = $method->invokeArgs($this->test_object, ['testing', $input]);
        $this->assertEquals($expected_output, $actual_output);
    }

    /**
     * test_extension method data provider
     *
     * @return array
     */
    public function test_extension_provider()
    {
        return [
            ['testing.jpg', 'jpg'],
            ['testing.jpeg', 'jpeg'],
            ['testing.png', 'png'],
            ['testing.gif', 'gif'],
            ['testing.pdf', 'pdf'],
            ['testing.txt', 'txt'],
            ['testing.doc', 'doc']
        ];
    }

    /**
     * test_id method
     *
     * Test that an id is properly returned.
     *
     * @dataProvider test_id_provider
     * @return void
     */
    public function test_id($input, $expected_output)
    {
        $this->test_object->id = $input;
        $method = self::getMethod('id');
        $actual_output = $method->invokeArgs($this->test_object, ['testing', $input]);
        $this->assertEquals($expected_output, $actual_output);
    }

    /**
     * test_id_partition method data provider
     *
     * @return array
     */
    public function test_id_provider()
    {
        return [
            ['9', '9'],
            ['99', '99'],
            ['999', '999'],
            ['9999', '9999'],
            ['99999', '99999'],
            ['999999', '999999'],
            ['abcd12345', 'abcd12345'],
            ['abcd12345efghighj', 'abcd12345efghighj']
        ];
    }

    /**
     * test_id_partition method
     *
     * Test that an id partition is properly formed.
     *
     * @dataProvider test_id_partition_provider
     * @return void
     */
    public function test_id_partition($input, $expected_output)
    {
        $this->test_object->id = $input;

        $method = self::getMethod('id_partition');
        $actual_output = $method->invokeArgs($this->test_object, ['testing', $input]);
        $this->assertEquals($expected_output, $actual_output);
    }

    /**
     * test_id_partition method data provider
     *
     * @return array
     */
    public function test_id_partition_provider()
    {
        return [
            ['9', '000/000/009'],
            ['99', '000/000/099'],
            ['999', '000/000/999'],
            ['9999', '000/009/999'],
            ['99999', '000/099/999'],
            ['999999', '000/999/999'],
            ['abcd12345', 'abc/d12/345'],
            ['abcd12345efghighj', 'abc/d12/345']
        ];
    }

    /**
     * test_attachment method
     *
     * Test that an id partition is properly formed.
     *
     * @dataProvider test_attachment_provider
     * @return void
     */
    public function test_attachment($input, $expected_output)
    {
        $method = self::getMethod('attachment');
        $actual_output = $method->invokeArgs($this->test_object, [$input]);
        $this->assertEquals($expected_output, $actual_output);
    }

    /**
     * test_attachment method data provider
     *
     * @return array
     */
    public function test_attachment_provider()
    {
        return [
            ['testing', 'testings'],
            ['photo', 'photos']
        ];
    }

    /**
     * test_style method
     *
     * Test that an id partition is properly formed.
     *
     * @dataProvider test_style_provider
     * @return void
     */
    public function test_style($input, $expected_output)
    {
        $method = self::getMethod('style');
        $actual_output = $method->invokeArgs($this->test_object, ['testing', $input]);
        $this->assertEquals($expected_output, $actual_output);
    }

    /**
     * test_style method data provider
     *
     * @return array
     */
    public function test_style_provider()
    {
        return [
            ['some_style', 'some_style'],
            ['', 'original']
        ];
    }

    /**
     * test_get_offset method
     *
     * Test that an string offset is properly formed.
     *
     * @dataProvider test_get_offset_provider
     * @return void
     */
    public function test_get_offset($id, $input, $expected_output)
    {
        $this->test_object->id = $id;

        $method = self::getMethod('get_offset');
        $actual_output = $method->invokeArgs($this->test_object, [$input, 'testing']);
        $this->assertEquals($expected_output, $actual_output);
    }

    /**
     * test_get_offset method data provider
     *
     * @return array
     */
    public function test_get_offset_provider()
    {
        return [
            [1, '/some/file/path/000/000/001/some_style/some_file.jpg', 27],
            [1, '/some/file/path/1/some_style/some_file.jpg', 17],
            [200, '/some/file/path/000/000/200/some_style/some_file.jpg', 27],
            [200, '/some/file/path/200/some_style/some_file.jpg', 19],
            ['abcdef123', '/some/file/path/abc/def/123/some_style/some_file.jpg', 27],
            ['abcdef123', '/some/file/path/abcdef123/some_style/some_file.jpg', 25]
        ];
    }

    /**
     * test_handle_backslashes method
     *
     * Test that an string is properly formed.
     *
     * @dataProvider test_handle_backslashes_provider
     * @return void
     */
    public function test_handle_backslashes($input, $expected_output)
    {
        $method = self::getMethod('handle_backslashes');
        $actual_output = $method->invokeArgs($this->test_object, [$input]);
        $this->assertEquals($expected_output, $actual_output);
    }

    /**
     * test_handle_backslashes method data provider
     *
     * @return array
     */
    public function test_handle_backslashes_provider()
    {
        return [
            ['foo\\bar', 'foo/bar'],
            ['\\foo\\bar', 'foo/bar']
        ];
    }

    /**
     * test_arrange_files method
     *
     * Test that multiple file uploads are properly re-arranged
     *
     * @dataProvider test_arrange_files_provider
     * @return void
     */
    public function test_arrange_files($files)
    {
        $expected_output = [
            0 => [
                'name' => 'test1.jpg',
                'type' => 'image/jpeg',
                'tmp_name' => '/tmp/nsl51Gs',
                'error' => '0',
                'size' => '1715'
            ],
            1 => [
                'name' => 'test2.jpg',
                'type' => 'image/jpeg',
                'tmp_name' => '/tmp/nsl52Gs',
                'error' => '0',
                'size' => '1715'
            ],
            2 => [
                'name' => 'test3.jpg',
                'type' => 'image/jpeg',
                'tmp_name' => '/tmp/nsl53Gs',
                'error' => '0',
                'size' => '1715'
            ],
            3 => [
                'name' => 'test4.jpg',
                'type' => 'image/jpeg',
                'tmp_name' => '/tmp/nsl54Gs',
                'error' => '0',
                'size' => '1715'
            ]
        ];

        $actual_output = $this->test_object->arrange_files($files);
        $this->assertEquals($expected_output, $actual_output);

    }

    /**
     * test_arrange_files method data provider
     *
     * @return array
     */
    public function test_arrange_files_provider()
    {
        return [
            [
                [
                    'name' => [
                        0 => 'test1.jpg',
                        1 => 'test2.jpg',
                        2 => 'test3.jpg',
                        3 => 'test4.jpg'
                    ],
                    'type' => [
                        0 => 'image/jpeg',
                        1 => 'image/jpeg',
                        2 => 'image/jpeg',
                        3 => 'image/jpeg'
                    ],
                    'tmp_name' => [
                        0 => '/tmp/nsl51Gs',
                        1 => '/tmp/nsl52Gs',
                        2 => '/tmp/nsl53Gs',
                        3 => '/tmp/nsl54Gs'
                    ],
                    'error' => [
                        0 => 0,
                        1 => 0,
                        2 => 0,
                        3 => 0,
                    ],
                    'size' => [
                        0 => 1715,
                        1 => 1715,
                        2 => 1715,
                        3 => 1715
                   ]
                ]
            ]
        ];
    }

}