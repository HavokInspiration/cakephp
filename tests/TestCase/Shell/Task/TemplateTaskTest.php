<?php
/**
 * CakePHP(tm) : Rapid Development Framework (http://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 * @link          http://cakephp.org CakePHP(tm) Project
 * @since         1.3.0
 * @license       http://www.opensource.org/licenses/mit-license.php MIT License
 */
namespace Cake\Test\TestCase\Shell\Task;

use Cake\Core\App;
use Cake\Core\Plugin;
use Cake\Shell\Task\TemplateTask;
use Cake\TestSuite\StringCompareTrait;
use Cake\TestSuite\TestCase;

/**
 * TemplateTaskTest class
 */
class TemplateTaskTest extends TestCase {

	use StringCompareTrait;

/**
 * setUp method
 *
 * @return void
 */
	public function setUp() {
		parent::setUp();
		$this->_compareBasePath = CORE_TESTS . 'bake_compare' . DS . 'Template' . DS;
		$io = $this->getMock('Cake\Console\ConsoleIo', [], [], '', false);

		$this->Task = $this->getMock('Cake\Shell\Task\TemplateTask',
			array('in', 'err', 'createFile', '_stop', 'clear'),
			array($io)
		);
	}

/**
 * tearDown method
 *
 * @return void
 */
	public function tearDown() {
		parent::tearDown();
		unset($this->Task);
		Plugin::unload();
	}

/**
 * test generate
 *
 * @return void
 */
	public function testGenerate() {
		$this->Task->expects($this->any())->method('in')->will($this->returnValue(1));

		$result = $this->Task->generate('classes/test_object', array('test' => 'foo'));
		$this->assertSameAsFile(__FUNCTION__ . '.ctp', $result);
	}

/**
 * test generate with an overriden template it gets used
 *
 * @return void
 */
	public function testGenerateWithTemplateOverride() {
		Plugin::load('TestBakeTheme');
		$this->Task->params['theme'] = 'TestBakeTheme';
		$this->Task->set(array(
			'plugin' => 'Special'
		));
		$result = $this->Task->generate('config/routes');
		$this->assertSameAsFile(__FUNCTION__ . '.ctp', $result);
	}
/**
 * test generate with a missing template in the chosen template.
 * ensure fallback to default works.
 *
 * @return void
 */
	public function testGenerateWithTemplateFallbacks() {
		Plugin::load('TestBakeTheme');
		$this->Task->params['theme'] = 'TestBakeTheme';
		$this->Task->set(array(
			'name' => 'Articles',
			'table' => 'articles',
			'import' => false,
			'records' => false,
			'schema' => '',
			'namespace' => ''
		));
		$result = $this->Task->generate('tests/fixture');
		$this->assertSameAsFile(__FUNCTION__ . '.ctp', $result);
	}
}
