<?php

namespace yiiunit\framework\behaviors;

use Yii;
use yiiunit\TestCase;
use yii\db\Connection;
use yii\db\ActiveRecord;
use yii\behaviors\SluggableBehavior;

/**
 * Unit test for [[\yii\behaviors\SluggableBehavior]].
 * @see SluggableBehavior
 *
 * @group behaviors
 */
class SluggableBehaviorTest extends TestCase
{
    /**
     * @var Connection test db connection
     */
    protected $dbConnection;

    public static function setUpBeforeClass()
    {
        if (!extension_loaded('pdo') || !extension_loaded('pdo_sqlite')) {
            static::markTestSkipped('PDO and SQLite extensions are required.');
        }
    }

    public function setUp()
    {
        $this->mockApplication([
            'components' => [
                'db' => [
                    'class' => '\yii\db\Connection',
                    'dsn' => 'sqlite::memory:',
                ]
            ]
        ]);

        $columns = [
            'id' => 'pk',
            'name' => 'string',
            'slug' => 'string',
            'category_id' => 'integer',
        ];
        Yii::$app->getDb()->createCommand()->createTable('test_slug', $columns)->execute();
    }

    public function tearDown()
    {
        Yii::$app->getDb()->close();
        parent::tearDown();
    }

    // Tests :

    public function testSlug()
    {
        $model = new ActiveRecordSluggable();
        $model->name = 'test name';
        $model->validate();

        $this->assertEquals('test-name', $model->slug);
    }

    /**
     * @depends testSlug
     */
    public function testSlugSeveralAttributes()
    {
        $model = new ActiveRecordSluggable();
        $model->getBehavior('sluggable')->attribute = array('name', 'category_id');

        $model->name = 'test';
        $model->category_id = 10;

        $model->validate();
        $this->assertEquals('test-10', $model->slug);
    }

    /**
     * @depends testSlug
     */
    public function testUniqueByIncrement()
    {
        $name = 'test name';

        $model = new ActiveRecordSluggableUnique();
        $model->name = $name;
        $model->save();

        $model = new ActiveRecordSluggableUnique();
        $model->sluggable->uniqueSlugGenerator = 'increment';
        $model->name = $name;
        $model->save();

        $this->assertEquals('test-name-2', $model->slug);
    }

    /**
     * @depends testUniqueByIncrement
     */
    public function testUniqueByCallback()
    {
        $name = 'test name';

        $model = new ActiveRecordSluggableUnique();
        $model->name = $name;
        $model->save();

        $model = new ActiveRecordSluggableUnique();
        $model->sluggable->uniqueSlugGenerator = function($baseSlug, $iteration) {return $baseSlug . '-callback';};
        $model->name = $name;
        $model->save();

        $this->assertEquals('test-name-callback', $model->slug);
    }

    /**
     * @depends testSlug
     */
    public function testUpdateUnique()
    {
        $name = 'test name';

        $model = new ActiveRecordSluggableUnique();
        $model->name = $name;
        $model->save();

        $model->save();
        $this->assertEquals('test-name', $model->slug);

        $model = ActiveRecordSluggableUnique::find()->one();
        $model->save();
        $this->assertEquals('test-name', $model->slug);

        $model->name = 'test-name';
        $model->save();
        $this->assertEquals('test-name', $model->slug);
    }
}

/**
 * Test Active Record class with [[SluggableBehavior]] behavior attached.
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property int $category_id
 *
 * @property SluggableBehavior $sluggable
 */
class ActiveRecordSluggable extends ActiveRecord
{
    public function behaviors()
    {
        return [
            'sluggable' => [
                'class' => SluggableBehavior::class,
                'attribute' => 'name',
            ],
        ];
    }

    public static function tableName()
    {
        return 'test_slug';
    }

    /**
     * @return SluggableBehavior
     */
    public function getSluggable()
    {
        return $this->getBehavior('sluggable');
    }
}

class ActiveRecordSluggableUnique extends ActiveRecordSluggable
{
    public function behaviors()
    {
        return [
            'sluggable' => [
                'class' => SluggableBehavior::class,
                'attribute' => 'name',
                'ensureUnique' => true,
            ],
        ];
    }
}