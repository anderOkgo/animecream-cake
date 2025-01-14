<?php
/**
 * CakePHP :  Rapid Development Framework (https://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 * @link          https://cakephp.org CakePHP Project
 * @since         3.0.0
 * @license       https://opensource.org/licenses/mit-license.php MIT License
 */
namespace Cake\Test\TestCase\Shell\Task;

use Cake\Core\Plugin;
use Cake\Filesystem\Folder;
use Cake\TestSuite\TestCase;

/**
 * AssetsTaskTest class
 */
class AssetsTaskTest extends TestCase
{

    /**
     * setUp method
     *
     * @return void
     */
    public function setUp()
    {
        parent::setUp();

        $this->skipIf(
            DS === '\\',
            'Skip AssetsTask tests on windows to prevent side effects for UrlHelper tests on AppVeyor.'
        );

        $this->io = $this->getMockBuilder('Cake\Console\ConsoleIo')
            ->disableOriginalConstructor()
            ->getMock();

        $this->Task = $this->getMockBuilder('Cake\Shell\Task\AssetsTask')
            ->setMethods(['in', 'out', 'err', '_stop'])
            ->setConstructorArgs([$this->io])
            ->getMock();
    }

    /**
     * tearDown method
     *
     * @return void
     */
    public function tearDown()
    {
        parent::tearDown();
        unset($this->Task);
        Plugin::unload();
    }

    /**
     * testSymlink method
     *
     * @return void
     */
    public function testSymlink()
    {
        Plugin::load('TestPlugin');
        Plugin::load('Company/TestPluginThree');

        $this->Task->symlink();

        $path = WWW_ROOT . 'test_plugin';
        $link = new \SplFileInfo($path);
        $this->assertFileExists($path . DS . 'root.js');
        if (DS === '\\') {
            $this->assertTrue($link->isDir());
            $folder = new Folder($path);
            $folder->delete();
        } else {
            $this->assertTrue($link->isLink());
            unlink($path);
        }

        $path = WWW_ROOT . 'company' . DS . 'test_plugin_three';
        $link = new \SplFileInfo($path);
        // If "company" directory exists beforehand "test_plugin_three" would
        // be a link. But if the directory is created by the shell itself
        // symlinking fails and the assets folder is copied as fallback.
        $this->assertTrue($link->isDir());
        $this->assertFileExists($path . DS . 'css' . DS . 'company.css');
        $folder = new Folder(WWW_ROOT . 'company');
        $folder->delete();
    }

    /**
     * testSymlinkWhenVendorDirectoryExits
     *
     * @return void
     */
    public function testSymlinkWhenVendorDirectoryExits()
    {
        Plugin::load('Company/TestPluginThree');

        mkdir(WWW_ROOT . 'company');

        $this->Task->symlink();
        $path = WWW_ROOT . 'company' . DS . 'test_plugin_three';
        $link = new \SplFileInfo($path);
        if (DS === '\\') {
            $this->assertTrue($link->isDir());
        } else {
            $this->assertTrue($link->isLink());
        }
        $this->assertFileExists($path . DS . 'css' . DS . 'company.css');
        $folder = new Folder(WWW_ROOT . 'company');
        $folder->delete();
    }

    /**
     * testSymlinkWhenTargetAlreadyExits
     *
     * @return void
     */
    public function testSymlinkWhenTargetAlreadyExits()
    {
        Plugin::load('TestTheme');

        $shell = $this->getMockBuilder('Cake\Shell\Task\AssetsTask')
            ->setMethods(['in', 'out', 'err', '_stop', '_createSymlink', '_copyDirectory'])
            ->setConstructorArgs([$this->io])
            ->getMock();

        $this->assertDirectoryExists(WWW_ROOT . 'test_theme');

        $shell->expects($this->never())->method('_createSymlink');
        $shell->expects($this->never())->method('_copyDirectory');
        $shell->symlink();
    }

    /**
     * test that plugins without webroot are not processed
     *
     * @return void
     */
    public function testForPluginWithoutWebroot()
    {
        Plugin::load('TestPluginTwo');

        $this->Task->symlink();
        $this->assertFileNotExists(WWW_ROOT . 'test_plugin_two');
    }

    /**
     * testSymlinkingSpecifiedPlugin
     *
     * @return void
     */
    public function testSymlinkingSpecifiedPlugin()
    {
        Plugin::load('TestPlugin');
        Plugin::load('Company/TestPluginThree');

        $this->Task->symlink('TestPlugin');

        $path = WWW_ROOT . 'test_plugin';
        $link = new \SplFileInfo($path);
        $this->assertFileExists($path . DS . 'root.js');
        unlink($path);

        $path = WWW_ROOT . 'company' . DS . 'test_plugin_three';
        $link = new \SplFileInfo($path);
        $this->assertFalse($link->isDir());
        $this->assertFalse($link->isLink());
    }

    /**
     * testCopy
     *
     * @return void
     */
    public function testCopy()
    {
        Plugin::load('TestPlugin');
        Plugin::load('Company/TestPluginThree');

        $this->Task->copy();

        $path = WWW_ROOT . 'test_plugin';
        $dir = new \SplFileInfo($path);
        $this->assertTrue($dir->isDir());
        $this->assertFileExists($path . DS . 'root.js');

        $folder = new Folder($path);
        $folder->delete();

        $path = WWW_ROOT . 'company' . DS . 'test_plugin_three';
        $link = new \SplFileInfo($path);
        $this->assertTrue($link->isDir());
        $this->assertFileExists($path . DS . 'css' . DS . 'company.css');

        $folder = new Folder(WWW_ROOT . 'company');
        $folder->delete();
    }
}
