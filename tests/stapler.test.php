<?php

use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamWrapper;
use org\bovigo\vfs\vfsStreamDirectory;

Bundle::start('stapler');
$_SERVER['DOCUMENT_ROOT'] = path('public');

/**
 * This is a dummy test object that is using the Stapler trait
 * We'll build mocks of this object in order to test the trait itself.
 */
class TestModel extends Eloquent {
    use Stapler\stapler;
    
    public function __construct($attributes = array(), $exists = false){
        parent::__construct($attributes, $exists);

        $this->tmp_file['testing'] = '';

        $this->has_attached_file('testing', [
            'styles' => [
                'thumbnail' => '100x100#'
            ],
            'url' => '/system/:attachment/:id_partition/:style/:filename',
            'default_url' => '/:attachment/:style/missing.jpg'
        ]);
    }
}


class StaplerTest extends PHPUnit_Framework_TestCase
{

    /**
     * $testModel - A dummy model that uses stapler
     * @var Eloquent/Model
     */
    private $testModel;

    public $mockFileUploadDir;

    /**
     * setUp method - Fixture creation
     */
    public function setUp()
    {
        $this->testModel = new TestModel;
        $this->mockFileUploadDir = vfsStream::setup('foo/bar');
    }

    /**
     * tearDown method
     */
    public function tearDown()
    {
        $this->mockFileUploadDir = null;
        $this->mockDefaultDir = null;
    }

    /**
     * makeMethodPublic method
     * 
     * @param  string $name - The name of the method we're making public
     * @return callable
     */
    protected function makeMethodPublic($name) {
        $class = new ReflectionClass('TestModel');
        $method = $class->getMethod($name);
        $method->setAccessible(true);
        
        return $method;
    }

    /**
     * test_remove_files method
     *
     * Test that the files belonging to an object are deleted.
     *
     */
    public function test_remove_files() 
    {
        $mockedModel = $this->getMock('TestModel', ['path', 'get_offset', 'empty_directory']);
        $mockedModel->expects($this->once())
            ->method('path')
            ->will($this->returnValue('foo/bar'));

        $mockedModel->expects($this->once())
            ->method('get_offset')
            ->will($this->returnValue(3));

        $mockedModel->expects($this->once())
            ->method('empty_directory')
            ->with('foo', true);

        $this->testModel->remove_files($mockedModel);
    }

    /**
     * testProcessImage meethod
     * 
     * @param  callable $resizer         
     * @param  string $styleDimensions 
     * @param  boolean $expectedOutput  
     * @return void                  
     * @dataProvider testProcessImageDataProvider
     */
    public function testProcessImage($resizer, $styleDimensions, $expectedOutput)
    {
        IoC::register('Resizer', $resizer);

        $this->assertEquals($expectedOutput, $this->testModel->process_image('testing', '/foo/bar', $styleDimensions));
    }

    /**
     * testProcessImageDataProvider method
     * 
     * @return array 
     */
    public function testProcessImageDataProvider()
    {
        $resizer1 = function() {
            $resizer = $this->getMockBuilder('Resizer')
                ->setMethods(['resize', 'save'])
                ->disableOriginalConstructor()
                ->getMock();

            $resizer->expects($this->once())
                ->method('resize')
                ->will($this->returnValue($resizer));

            $resizer->expects($this->once())
                ->method('save')
                ->will($this->returnValue(true));

            return $resizer;
        };

        return [
            [$resizer1, '100', true],
            [$resizer1, 'x100', true],
            [$resizer1, '100x100#', true],
            [$resizer1, '100x100!', true],
            [$resizer1, '100x100', true],
        ];
    }

    /**
     * test__call method
     * 
     * @param  callable $testModel
     * @param  string $method
     * @param  string $parameters
     * @param  string $expectedOutput
     * @return void
     * @dataProvider callDataProvider                 
     */
    public function test__call($testModel, $method, $parameters, $expectedOutput)
    {
        $actualOutput = $testModel->__call($method, $parameters);
       
        $this->assertEquals($expectedOutput, $actualOutput);
    }

    /**
     * callDataProvider method
     * 
     * @return array
     */
    public function callDataProvider()
    {
        $testModel1 = $this->getMock('TestModel', ['return_resource']);
        $testModel1->expects($this->once())
            ->method('return_resource')
            ->with('path', 'testing')
            ->will($this->returnValue('testing.path'));

        $testModel2 = $this->getMock('TestModel', ['return_resource']);
        $testModel2->expects($this->once())
            ->method('return_resource')
            ->with('path', 'testing', 'thumbnail')
            ->will($this->returnValue('testing.path.thumbnail'));

        $testModel3 = $this->getMock('TestModel', ['return_resource']);
        $testModel3->expects($this->once())
            ->method('return_resource')
            ->with('url', 'testing')
            ->will($this->returnValue('testing.url'));

        $testModel4 = $this->getMock('TestModel', ['return_resource']);
        $testModel4->expects($this->once())
            ->method('return_resource')
            ->with('url', 'testing', 'thumbnail')
            ->will($this->returnValue('testing.url.thumbnail'));

        return [
            [$testModel1, 'testing_path', null, 'testing.path'],
            [$testModel2, 'testing_path', ['thumbnail'], 'testing.path.thumbnail'],
            [$testModel3, 'testing_url', null, 'testing.url'],
            [$testModel4, 'testing_url', ['thumbnail'], 'testing.url.thumbnail'],
        ];
    }

    /**
     * testReturnResource method
     * 
     * @return void
     * @dataProvider returnResourceProvider
     */
    public function testReturnResource($testModel, $type, $expectedOutput)
    {
        $actualOutput = $testModel->return_resource($type, 'thumbnail');

        $this->assertEquals($expectedOutput, $actualOutput);
    }

    /**
     * test_return_resource method data provider
     *
     * @return array
     */
    public function returnResourceProvider()
    {
        $testModel1 = $this->getMock('TestModel', ['path']);
        $testModel1->expects($this->once())
            ->method('path')
            ->will($this->returnValue(vfsStream::url('foo/bar')));

        $testModel2 = $this->getMock('TestModel', ['path', 'default_path']);
        $testModel2->expects($this->once())
            ->method('path')
            ->will($this->returnValue(''));
        $testModel2->expects($this->once())
            ->method('default_path')
            ->will($this->returnValue(true));

        $testModel3 = $this->getMock('TestModel', ['absolute_url', 'url']);
        $testModel3->expects($this->once())
            ->method('absolute_url')
            ->will($this->returnValue(vfsStream::url('foo/bar')));
        $testModel3->expects($this->once())
            ->method('url')
            ->will($this->returnValue(true));

        $testModel4 = $this->getMock('TestModel', ['absolute_url', 'default_url']);
        $testModel4->expects($this->once())
            ->method('absolute_url')
            ->will($this->returnValue(''));
        $testModel4->expects($this->once())
            ->method('default_url')
            ->will($this->returnValue(true));

        return [
            "File path does exist" => [$testModel1, 'path', 'vfs://foo/bar'],
            "File path doesn't exist" => [$testModel2, 'path', true],
            "File URL does exists" => [$testModel3, 'url', true],
            "File URL doesn't exists" => [$testModel4, 'url', true]
        ];
    }

    /**
     * testPath method
     *
     * Test that a file path is properly formed.
     *
     * @dataProvider testPathDataProvider
     */
    public function testPath($attachment, $style, $expectedOutput)
    {
        // Set some object variables to mock the file upload.
        $this->testModel->id = 1;
        $this->testModel->testing_file_name = 'test.jpg';
        $method = $this->makeMethodPublic('path');
        $actualOutput = $method->invokeArgs($this->testModel, [$attachment, $style]);

        $this->assertEquals($expectedOutput, $actualOutput);
    }

    /**
     * testPathDataProvider method
     *
     * @return array
     */
    public function testPathDataProvider()
    {
        return [
            ['testing', 'thumbnail', path('base')."public/system/testings/000/000/001/thumbnail/test.jpg"],
            ['testing', 'thumbnail', path('base')."public/system/testings/000/000/001/thumbnail/test.jpg"],
            ['testing', '', path('base')."public/system/testings/000/000/001/original/test.jpg"],
            ['testing', '', path('base')."public/system/testings/000/000/001/original/test.jpg"]
        ];
    }

    /**
     * testDefaultPath method
     *
     * Test that the default path is properly formed.
     *
     * @dataProvider testDefaultPathDataProvider
     * @return void
     */
    public function testDefaultPath($attachment, $style, $expectedOutput)
    {
        $method = $this->makeMethodPublic('default_path');
        $actualOutput = $method->invokeArgs($this->testModel, [$attachment, $style]);

        $this->assertEquals($expectedOutput, $actualOutput);
    }

    /**
     * testDefaultPathDataProvider method
     *
     * @return array
     */
    public function testDefaultPathDataProvider()
    {
        return [
            ['testing', 'thumbnail', path('base').'public/testings/thumbnail/missing.jpg'],
            ['testing', '', path('base').'public/testings/original/missing.jpg'],
        ];
    }

    /**
     * testUrl method
     *
     * Test that a file url is properly formed.
     *
     * @dataProvider testUrlDataProvider
     */
    public function testUrl($attachment, $style, $expectedOutput)
    {
        // Set some object variables to mock the file upload.
        $this->testModel->id = 1;
        $this->testModel->testing_file_name = 'test.jpg';
        $method = $this->makeMethodPublic('url');
        $actualOutput = $method->invokeArgs($this->testModel, [$attachment, $style]);

        $this->assertEquals($expectedOutput, $actualOutput);
    }

    /**
     * testUrlDataProvider method
     *
     * @return array
     */
    public function testUrlDataProvider()
    {
        return [
            ['testing', 'thumbnail', '/system/testings/000/000/001/thumbnail/test.jpg'],
            ['testing', 'thumbnail', '/system/testings/000/000/001/thumbnail/test.jpg'],
            ['testing', '', '/system/testings/000/000/001/original/test.jpg'],
            ['testing', '', '/system/testings/000/000/001/original/test.jpg']
        ];
    }

    /**
     * testDefaultUrl method
     *
     * Test that the default url is properly formed.
     *
     * @dataProvider testDefaultUrlDataProvider
     * @return void
     */
    public function testDefaultUrl($attachment, $style, $expectedOutput)
    {
        $method = $this->makeMethodPublic('default_url');
        $actualOutput = $method->invokeArgs($this->testModel, [$attachment, $style]);

        $this->assertEquals($expectedOutput, $actualOutput);
    }

    /**
     * testDefaultUrlDataProvider method
     *
     * @return array
     */
    public function testDefaultUrlDataProvider()
    {
        return [
            ['testing', 'thumbnail', '/testings/thumbnail/missing.jpg'],
            ['testing', '', '/testings/original/missing.jpg'],
        ];
    }

    /**
     * testInterpolateString method 
     *
     * Test that an interpolated string is properly formed.
     *
     * @dataProvider testInterpolateStringDataProvider
     * @return void
     */
    public function testInterpolateString($input, $expectedOutput, $style)
    {
        $this->testModel->id = 1;
        $this->testModel->testing_file_name = 'test.jpg';
        $method = $this->makeMethodPublic('interpolate_string');
        $actualOutput = $method->invokeArgs($this->testModel, ['testing', $input, $style]);

        $this->assertEquals($expectedOutput, $actualOutput);
    }

    /**
     * testInterpolateStringDataProvider method
     *
     * @return array
     */
    public function testInterpolateStringDataProvider()
    {
        // All possible interpolation options with id_partitioning
        $uninterpolated_strings[] = '/system/:class/:attachment/:id_partition/:style/:filename';
        $interpolated_strings[] = '/system/TestModel/testings/000/000/001/original/test.jpg';
        $styles[] = '';

        // All possible interpolation options with id_partitioning
        $uninterpolated_strings[] = '/system/:class/:attachment/:id_partition/:style/:filename';
        $interpolated_strings[] = '/system/TestModel/testings/000/000/001/thumbnail/test.jpg';
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
        $interpolated_strings[] = '/system/TestModel/testings/1/original/test.jpg';
        $styles[] = '';

        // All possible interpolation options with id
        $uninterpolated_strings[] = '/system/:class/:attachment/:id/:style/:filename';
        $interpolated_strings[] = '/system/TestModel/testings/1/thumbnail/test.jpg';
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
     * testFilename method
     *
     * Test that the attachment filename is properly returned.
     *
     * @dataProvider testFilenameProvider
     * @return void
     */
    public function testFilename($filename)
    {
        $this->testModel->testing_file_name = $filename;
        $method = $this->makeMethodPublic('filename');
        $actualOutput = $method->invokeArgs($this->testModel, ['testing']);

        $this->assertEquals($filename, $actualOutput);
    }

    /**
     * testFilenameProvider method
     *
     * @return array
     */
    public function testFilenameProvider()
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
     * testLaravelRoot method
     *
     * Test that Laravel root directory is properly returned.
     *
     * @return void
     */
    public function testLaravelRoot()
    {
       $expectedOutput = rtrim(path('base'), '/');
        $method = $this->makeMethodPublic('laravel_root');
        $actualOutput = $method->invokeArgs($this->testModel, ['testing']);

        $this->assertEquals($expectedOutput, $actualOutput);
    }

    /**
     * testGetClass method
     *
     * Test that a class name is properly returned.
     *
     * @dataProvider testGetClassDataProvider
     * @return void
     */
    public function testGetClass($expectedOutput)
    {
        $method = $this->makeMethodPublic('get_class');
        $actualOutput = $method->invokeArgs($this->testModel, ['testing']);

        $this->assertEquals($expectedOutput, $actualOutput);
    }

    /**
     * testGetClassDataProvider method
     *
     * @return array
     */
    public function testGetClassDataProvider()
    {
        return [
            ['TestModel']
        ];
    }

    /**
     * testBasename method
     *
     * Test that an basename is properly returned.
     *
     * @dataProvider testBasenameDataProvider
     * @return void
     */
    public function testBasename($input, $expectedOutput)
    {
        $this->testModel->testing_file_name = $input;
        $method = $this->makeMethodPublic('basename');
        $actualOutput = $method->invokeArgs($this->testModel, ['testing', $input]);

        $this->assertEquals($expectedOutput, $actualOutput);
    }

    /**
     * testBasenameDataProvider method
     *
     * @return array
     */
    public function testBasenameDataProvider()
    {
        return [
            ['testing1.2.jpg', 'testing1.2'],
            ['testing2_3.jpeg', 'testing2_3'],
            ['testing3-4.jpeg', 'testing3-4'],
            ['testing3-4.5.jpeg', 'testing3-4.5'],
        ];
    }

    /**
     * testExtension method
     *
     * Test that an extension is properly returned.
     *
     * @dataProvider testExtensionDataProvider
     * @return void
     */
    public function testExtension($input, $expectedOutput)
    {
        $this->testModel->testing_file_name = $input;
        $method = $this->makeMethodPublic('extension');
        $actualOutput = $method->invokeArgs($this->testModel, ['testing', $input]);

        $this->assertEquals($expectedOutput, $actualOutput);
    }

    /**
     * testExtensionDataProvider method
     *
     * @return array
     */
    public function testExtensionDataProvider()
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
     * testId method
     *
     * Test that an id is properly returned.
     *
     * @dataProvider testIdDataProvider
     * @return void
     */
    public function testId($input, $expectedOutput)
    {
        $this->testModel->id = $input;
        $method = $this->makeMethodPublic('id');
        $actualOutput = $method->invokeArgs($this->testModel, ['testing', $input]);

        $this->assertEquals($expectedOutput, $actualOutput);
    }

    /**
     * testIdDataProvider method 
     *
     * @return array
     */
    public function testIdDataProvider()
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
     * testIdPartition method
     *
     * Test that an id partition is properly formed.
     *
     * @dataProvider testIdPartitionDataProvider
     * @return void
     */
    public function testIdPartition($input, $expectedOutput)
    {
        $this->testModel->id = $input;
        $method = $this->makeMethodPublic('id_partition');
        $actualOutput = $method->invokeArgs($this->testModel, ['testing', $input]);

        $this->assertEquals($expectedOutput, $actualOutput);
    }

    /**
     * testIdPartitionDataProvider method
     *
     * @return array
     */
    public function testIdPartitionDataProvider()
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
     * testAttachment method
     *
     * Test that an id partition is properly formed.
     *
     * @dataProvider testAttachmentDataProvider
     * @return void
     */
    public function testAttachment($input, $expectedOutput)
    {
        $method = $this->makeMethodPublic('attachment');
        $actualOutput = $method->invokeArgs($this->testModel, [$input]);

        $this->assertEquals($expectedOutput, $actualOutput);
    }

    /**
     * testAttachmentDataProvider method
     *
     * @return array
     */
    public function testAttachmentDataProvider()
    {
        return [
            ['testing', 'testings'],
            ['photo', 'photos']
        ];
    }

    /**
     * testStyle method
     *
     * Test that an id partition is properly formed.
     *
     * @dataProvider testStyleDataProvider
     * @return void
     */
    public function testStyle($input, $expectedOutput)
    {
        $method = $this->makeMethodPublic('style');
        $actualOutput = $method->invokeArgs($this->testModel, ['testing', $input]);
        
        $this->assertEquals($expectedOutput, $actualOutput);
    }

    /**
     * testStyleDataProvider method
     *
     * @return array
     */
    public function testStyleDataProvider()
    {
        return [
            ['some_style', 'some_style'],
            ['', 'original']
        ];
    }

    /**
     * testGetOffset method
     *
     * Test that an string offset is properly formed.
     *
     * @dataProvider testGetOffsetDataProvider
     * @return void
     */
    public function testGetOffset($id, $input, $expectedOutput)
    {
        $this->testModel->id = $id;
        $method = $this->makeMethodPublic('get_offset');
        $actualOutput = $method->invokeArgs($this->testModel, [$input, 'testing']);

        $this->assertEquals($expectedOutput, $actualOutput);
    }

    /**
     * testGetOffset method data provider
     *
     * @return array
     */
    public function testGetOffsetDataProvider()
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
     * testHandleBackslashes method
     *
     * Test that an string is properly formed.
     *
     * @dataProvider testHandleBackslashesDataProvider
     * @return void
     */
    public function testHandleBackslashes($input, $expectedOutput)
    {
        $method = $this->makeMethodPublic('handle_backslashes');
        $actualOutput = $method->invokeArgs($this->testModel, [$input]);

        $this->assertEquals($expectedOutput, $actualOutput);
    }

    /**
     * testHandleBackslashesDataProvider method 
     *
     * @return array
     */
    public function testHandleBackslashesDataProvider()
    {
        return [
            ['foo\\bar', 'foo/bar'],
            ['\\foo\\bar', 'foo/bar']
        ];
    }

    /**
     * testRemoveDirectory method
     *
     * Test that the empty directory method will remove all files in a directory
     * and the directory itself when the delete_directory flag is set to true.
     * 
     * @return void            
     */
    public function testRemoveDirectory()
    {
        $directory = vfsStream::url('foo');
        $method = $this->makeMethodPublic('empty_directory');
        $method->invokeArgs($this->testModel, [$directory, true]);

        $this->assertFileNotExists(vfsStream::url('foo'));
    }

    /**
     * testEmptyDirectory method
     *
     * Test that the empty directory method will remove all files in a directory,
     * but not the parent directory, when the delete_directory flag is set to false.
     * 
     * @return void
     */
    public function testEmptyDirectory()
    {
        $directory = vfsStream::url('foo');
        $method = $this->makeMethodPublic('empty_directory');
        $method->invokeArgs($this->testModel, [$directory]);
        
        $this->assertFalse($this->mockFileUploadDir->hasChild('bar'));
        $this->assertFileExists(vfsStream::url('foo'));
    }

    /**
     * testArrangeFiles method
     *
     * Test that multiple file uploads are properly re-arranged
     *
     * @dataProvider testArrangeFilesDataProvider
     * @return void
     */
    public function testArrangeFiles($files, $expectedOutput)
    {
        $actualOutput = $this->testModel->arrange_files($files);
        
        $this->assertEquals($expectedOutput, $actualOutput);

    }

    /**
     * testArrangeFilesDataProvider method
     *
     * @return array
     */
    public function testArrangeFilesDataProvider()
    {
        $files = [
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
        ];

        $expectedOutput = [
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

        return [
            [$files, $expectedOutput]
        ];
    }
}
