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

    public $query;

    public $connection;

    public $autoQuote;

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

        $query = new Query($this->connection);
        $prefix = $this->connection->getPrefix();
        $query->setTableNamesSettings([
            'prefix' => $prefix,
            'quoteStrings' => ['', '']
        ]);

        $this->query = $query;
    }

    public function tearDown()
    {
        parent::tearDown();
        unset($this->connection, $this->query, $this->autoQuote);
    }

    /**
     * Tests needsPrefix() methods
     *
     * @return void
     */
    public function testNeedsPrefix()
    {
        $query = $this->query;

        $this->assertFalse($query->needsPrefix('articles'));
        $this->assertFalse($query->needsPrefix('prefix_articles'));
        $this->assertFalse($query->needsPrefix('articles.id'));
        $this->assertFalse($query->needsPrefix('prefix_articles.id'));
        $this->assertFalse($query->needsPrefix('Function(articles.id)'));
        $this->assertFalse($query->needsPrefix('Function(prefix_articles.id)'));
        $this->assertFalse($query->needsPrefix('Function(SubFunction(articles.id))'));
        $this->assertFalse($query->needsPrefix('Function(SubFunction(prefix_articles.id))'));
        $this->assertFalse($query->needsPrefix('articles.author_id = authors.id'));

        $query->setTableNamesSettings([
            'tablesNames' => ['articles' => 'articles']
        ]);
        $this->assertTrue($query->needsPrefix('articles'));
        $this->assertFalse($query->needsPrefix('prefix_articles'));
        $this->assertTrue($query->needsPrefix('articles.id'));
        $this->assertFalse($query->needsPrefix('prefix_articles.id'));
        $this->assertFalse($query->needsPrefix('Articles.id'));
        $this->assertTrue($query->needsPrefix('Function(articles.id)'));
        $this->assertFalse($query->needsPrefix('Function(prefix_articles.id)'));
        $this->assertTrue($query->needsPrefix('Function(SubFunction(articles.id))'));
        $this->assertFalse($query->needsPrefix('Function(SubFunction(prefix_articles.id))'));
    }

    /**
     * Tests prefixTableNames() method with only string passed as
     * arguments
     *
     * @return void
     */
    public function testPrefixTableNamesWithString()
    {
        $query = $this->query;

        $this->assertEquals('prefix_articles', $query->prefixTableNames('articles', true));
        $this->assertEquals('prefix_articles', $query->prefixTableNames('prefix_articles', true));
        $this->assertEquals('prefix_articles', $query->prefixTableNames('articles', false));
        $this->assertEquals('prefix_articles', $query->prefixTableNames('prefix_articles', false));

        $query->setTableNamesSettings([
            'quoteStrings' => ['"', '"']
        ]);

        $this->assertEquals('"prefix_articles"', $query->prefixTableNames('"articles"', true));
        $this->assertEquals('"prefix_articles"', $query->prefixTableNames('"prefix_articles"', true));
        $this->assertEquals('"prefix_articles"', $query->prefixTableNames('"articles"', false));
        $this->assertEquals('"prefix_articles"', $query->prefixTableNames('"prefix_articles"', false));
    }

    /**
     * Tests prefixTableNames() method with only string passed as
     * arguments
     *
     * @return void
     */
    public function testPrefixTableNamesWithArray()
    {
        $query = $this->query;

        $this->assertEquals(['prefix_articles', 'prefix_comments'], $query->prefixTableNames(['articles', 'comments'], true));
        $this->assertEquals(['prefix_articles', 'prefix_comments'], $query->prefixTableNames(['prefix_articles', 'prefix_comments'], true));
        $this->assertEquals(['prefix_articles', 'prefix_comments'], $query->prefixTableNames(['articles', 'comments'], false));
        $this->assertEquals(['prefix_articles', 'prefix_comments'], $query->prefixTableNames(['prefix_articles', 'comments'], false));

        $query->setTableNamesSettings([
            'quoteStrings' => ['"', '"']
        ]);
        $this->assertEquals(['"prefix_articles"', '"prefix_comments"'], $query->prefixTableNames(['"articles"', '"comments"'], true));
        $this->assertEquals(['prefix_articles', 'prefix_comments'], $query->prefixTableNames(['articles', 'comments'], true));
        $this->assertEquals(['"prefix_articles"', '"prefix_comments"'], $query->prefixTableNames(['"prefix_articles"', '"prefix_comments"'], true));
        $this->assertEquals(['prefix_articles', 'prefix_comments'], $query->prefixTableNames(['prefix_articles', 'prefix_comments'], true));

        $this->assertEquals(['"prefix_articles"', '"prefix_comments"'], $query->prefixTableNames(['"articles"', '"comments"'], false));
        $this->assertEquals(['prefix_articles', 'prefix_comments'], $query->prefixTableNames(['articles', 'comments'], false));
        $this->assertEquals(['"prefix_articles"', '"prefix_comments"'], $query->prefixTableNames(['"prefix_articles"', '"prefix_comments"'], false));
        $this->assertEquals(['prefix_articles', 'prefix_comments'], $query->prefixTableNames(['prefix_articles', 'prefix_comments'], false));
    }

    /**
     * Tests prefixFieldName() method
     *
     * @return void
     */
    public function testPrefixFieldName()
    {
        $query = $this->query;

        $this->assertEquals('articles.id', $query->prefixFieldName('articles.id'));
        $this->assertEquals('comments.id', $query->prefixFieldName('comments.id'));
        $this->assertEquals('Articles.id', $query->prefixFieldName('Articles.id'));
        $this->assertEquals('Function(articles.id)', $query->prefixFieldName('Function(articles.id)'));
        $this->assertEquals('Function(prefix_articles.id)', $query->prefixFieldName('Function(prefix_articles.id)'));
        $this->assertEquals('Function(SubFunction(articles.id))', $query->prefixFieldName('Function(SubFunction(articles.id))'));
        $this->assertEquals('Function(SubFunction(prefix_articles.id))', $query->prefixFieldName('Function(SubFunction(prefix_articles.id))'));

        $query->setTableNamesSettings([
            'tablesNames' => ['articles' => 'articles']
        ]);

        $this->assertEquals('prefix_articles.id', $query->prefixFieldName('articles.id'));
        $this->assertEquals('prefix_articles.id', $query->prefixFieldName('prefix_articles.id'));
        $this->assertEquals('comments.id', $query->prefixFieldName('comments.id'));
        $this->assertEquals('Articles.id', $query->prefixFieldName('Articles.id'));
        $this->assertEquals('Function(prefix_articles.id)', $query->prefixFieldName('Function(articles.id)'));
        $this->assertEquals('Function(prefix_articles.id)', $query->prefixFieldName('Function(prefix_articles.id)'));
        $this->assertEquals('Function(SubFunction(prefix_articles.id))', $query->prefixFieldName('Function(SubFunction(articles.id))'));
        $this->assertEquals('Function(SubFunction(Articles.id))', $query->prefixFieldName('Function(SubFunction(Articles.id))'));
        $this->assertEquals('Function(SubFunction(prefix_articles.id))', $query->prefixFieldName('Function(SubFunction(prefix_articles.id))'));
    }

    /**
     * Tests prefixFieldName() method with quotes
     *
     * @return void
     */
    public function testPrefixFieldNameQuotes()
    {
        $query = $this->query;

        $this->assertEquals('"articles"."id"', $query->prefixFieldName('"articles"."id"'));
        $this->assertEquals('"comments"."id"', $query->prefixFieldName('"comments"."id"'));
        $this->assertEquals('"Articles"."id"', $query->prefixFieldName('"Articles"."id"'));
        $this->assertEquals('Function("articles"."id")', $query->prefixFieldName('Function("articles"."id")'));
        $this->assertEquals('Function("prefix_articles"."id")', $query->prefixFieldName('Function("prefix_articles"."id")'));
        $this->assertEquals('Function(SubFunction("articles"."id"))', $query->prefixFieldName('Function(SubFunction("articles"."id"))'));
        $this->assertEquals('Function(SubFunction("Articles"."id"))', $query->prefixFieldName('Function(SubFunction("Articles"."id"))'));
        $this->assertEquals('Function(SubFunction("prefix_articles"."id"))', $query->prefixFieldName('Function(SubFunction("prefix_articles"."id"))'));

        $query->setTableNamesSettings([
            'tablesNames' => ['articles' => 'articles'],
            'quoteStrings' => ['"', '"']
        ]);

        $this->assertEquals('"prefix_articles"."id"', $query->prefixFieldName('"articles"."id"'));
        $this->assertEquals('"comments"."id"', $query->prefixFieldName('"comments"."id"'));
        $this->assertEquals('"Articles"."id"', $query->prefixFieldName('"Articles"."id"'));
        $this->assertEquals('Function("prefix_articles"."id")', $query->prefixFieldName('Function("articles"."id")'));
        $this->assertEquals('Function("prefix_articles"."id")', $query->prefixFieldName('Function("prefix_articles"."id")'));
        $this->assertEquals('Function(SubFunction("prefix_articles"."id"))', $query->prefixFieldName('Function(SubFunction("prefix_articles"."id"))'));
        $this->assertEquals('Function(SubFunction("prefix_articles"."id"))', $query->prefixFieldName('Function(SubFunction("prefix_articles"."id"))'));
    }

    /**
     * Tests rawTableName() method
     *
     * @return void
     */
    public function testRawTableName()
    {
        $query = $this->query;

        $this->assertEquals('articles', $query->rawTableName('articles'));
        $this->assertEquals('articles', $query->rawTableName('prefix_articles'));

        $query->setTableNamesSettings([
            'quoteStrings' => ['"', '"']
        ]);
        $this->assertEquals('"articles"', $query->rawTableName('"articles"'));
        $this->assertEquals('"articles"', $query->rawTableName('"prefix_articles"'));
    }

    /**
     * Tests hasTableName() method
     *
     * @return void
     */
    public function testHasTableName()
    {
        $query = $this->query;

        $this->assertFalse($query->hasTableName('articles.id'));
        $this->assertFalse($query->hasTableName('Articles'));
        $this->assertFalse($query->hasTableName('articles'));

        $query->setTableNamesSettings([
            'tablesNames' => ['articles' => 'articles']
        ]);

        $this->assertTrue($query->hasTableName('articles.id'));
        $this->assertFalse($query->hasTableName('Articles'));
        $this->assertTrue($query->hasTableName('articles'));
    }

    /**
     * Tests isTableNamePrefixed() method
     *
     * @return void
     */
    public function testIsTableNamePrefixed()
    {
        $query = $this->query;

        $this->assertFalse($query->isTableNamePrefixed('articles.id', true));
        $this->assertTrue($query->isTableNamePrefixed('prefix_articles.id', true));
        $this->assertFalse($query->isTableNamePrefixed('articles', true));
        $this->assertFalse($query->isTableNamePrefixed('articles.id', false));
        $this->assertTrue($query->isTableNamePrefixed('prefix_articles.id', false));
        $this->assertFalse($query->isTableNamePrefixed('articles', false));

        $this->assertFalse($query->isTableNamePrefixed('Articles'));
        $this->assertFalse($query->isTableNamePrefixed('Function(SubFunction(articles.id))'));
        $this->assertTrue($query->isTableNamePrefixed('Function(SubFunction(prefix_articles.id))'));

        $query->setTableNamesSettings([
            'quoteStrings' => ['"', '"']
        ]);
        $this->assertFalse($query->isTableNamePrefixed('"articles"."id"', true));
        $this->assertTrue($query->isTableNamePrefixed('"prefix_articles"."id"', true));
        $this->assertFalse($query->isTableNamePrefixed('"articles"', true));
        $this->assertFalse($query->isTableNamePrefixed('"articles"."id"', false));
        $this->assertTrue($query->isTableNamePrefixed('"prefix_articles"."id"', false));
        $this->assertFalse($query->isTableNamePrefixed('"articles"', false));

        $this->assertFalse($query->isTableNamePrefixed('Function(SubFunction("articles"."id"))'));
        $this->assertTrue($query->isTableNamePrefixed('Function(SubFunction("prefix_articles"."id"))'));
    }
}
