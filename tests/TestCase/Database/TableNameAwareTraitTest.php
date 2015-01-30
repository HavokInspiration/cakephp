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
 * @since         3.0.0
 * @license       http://www.opensource.org/licenses/mit-license.php MIT License
 */
namespace Cake\Test\TestCase\Database;

use Cake\Database\Query;
use Cake\Datasource\ConnectionManager;
use Cake\TestSuite\TestCase;

/**
 * Tests for the TableNameAwareTrait
 */
class TableNameAwareTraitTest extends TestCase
{

    public function setUp()
    {
        parent::setUp();
        $this->connection = ConnectionManager::get('test');
        $this->autoQuote = $this->connection->driver()->autoQuoting();

        if ($this->connection->getPrefix() === '') {
            $this->markTestSkipped(
                'No connection prefix configured.'
            );
        }
    }

    public function tearDown()
    {
        parent::tearDown();
        $this->connection->driver()->autoQuoting($this->autoQuote);
        unset($this->connection);
    }

    /**
     * Tests methods from the TableNameAwareTrait which is implemented by
     * Query
     *
     * @return void
     */
    public function testNeedsPrefix()
    {
        $query = new Query($this->connection);
        $prefix = $this->connection->getPrefix();
        $quoteStrings = $this->connection->driver()->getQuoteStrings();
        $startQuote = $quoteStrings[0];
        $endQuote = $quoteStrings[1];
        $query->setTableNamesSettings([
            'prefix' => $prefix,
            'quoteStrings' => $quoteStrings
        ]);

        $this->assertFalse($query->needsPrefix('articles'));
        $this->assertFalse($query->needsPrefix('prefix_articles'));
        $this->assertFalse($query->needsPrefix('articles.id'));
        $this->assertFalse($query->needsPrefix('prefix_articles.id'));
        $this->assertFalse($query->needsPrefix('Function(articles.id)'));
        $this->assertFalse($query->needsPrefix('Function(prefix_articles.id)'));
        $this->assertFalse($query->needsPrefix('Function(SubFunction(articles.id))'));
        $this->assertFalse($query->needsPrefix('Function(SubFunction(prefix_articles.id))'));
        $this->assertFalse($query->needsPrefix('SELECT * FROM articles'));
        $this->assertFalse($query->needsPrefix('SELECT * FROM prefix_articles'));
        $this->assertFalse($query->needsPrefix('articles.author_id = authors.id'));

        $query->setTableNamesSettings([
            'tablesNames' => ['articles' => 'articles']
        ]);
        $this->assertTrue($query->needsPrefix('articles'));
        $this->assertFalse($query->needsPrefix('prefix_articles'));
        $this->assertTrue($query->needsPrefix('articles.id'));
        $this->assertFalse($query->needsPrefix('prefix_articles.id'));
        $this->assertFalse($query->needsPrefix('Articles.id'));
        $this->assertTrue($query->needsPrefix('articles.author_id = authors.id'));
        $this->assertTrue($query->needsPrefix('articles.author_id = prefix_authors.id'));
        $this->assertTrue($query->needsPrefix('Function(articles.id)'));
        $this->assertFalse($query->needsPrefix('Function(prefix_articles.id)'));
        $this->assertTrue($query->needsPrefix('Function(SubFunction(articles.id))'));
        $this->assertFalse($query->needsPrefix('Function(SubFunction(prefix_articles.id))'));
        $this->assertTrue($query->needsPrefix('SELECT * FROM articles'));
        $this->assertFalse($query->needsPrefix('SELECT * FROM prefix_articles'));

        $query->setTableNamesSettings([
            'tablesNames' => ['authors' => 'authors']
        ]);
        $this->assertTrue($query->needsPrefix('articles.author_id = authors.id'));
        $this->assertTrue($query->needsPrefix('prefix_articles.author_id = authors.id'));

        $query->setTableNamesSettings([
            'tablesNames' => ['articles' => 'articles', 'authors' => 'authors']
        ]);
        $this->assertTrue($query->needsPrefix('articles.author_id = authors.id'));
        $this->assertFalse($query->needsPrefix('prefix_articles.author_id = prefix_authors.id'));
        $this->assertTrue($query->needsPrefix('articles.author_id = authors.id'));
    }
}
