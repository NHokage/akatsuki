<?php

namespace app\models;

use Yii;
use yii\helpers\ArrayHelper;
use yii\data\Pagination;
use app\models\User;

/**
 * This is the model class for table "article".
 *
 * @property int $id
 * @property string $title
 * @property string $description
 * @property string $content
 * @property string $date
 * @property string $image
 * @property int $viewed
 * @property int $user_id
 * @property int $status
 * @property int $category_id
 *
 * @property ArticleTag[] $articleTags
 * @property Comment[] $comments
 */
class Article extends \yii\db\ActiveRecord
{
    /**
     * @inheritdoc
     */

    const STATUS_ALLOW = 1;
    const STATUS_DISALLOW = 0;


    public static function tableName()
    {
        return 'article';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['title'], 'required'],
            [['title','description','content'], 'string'],
            [['date'], 'date', 'format'=>'php:Y-m-d H:i:s'],
            [['date'], 'default', 'value' => date('Y-m-d H:i:s')],
            [['status'],'default', 'value' => 1],
            [['title'], 'string', 'max' => 255],
            [['category_id'], 'number']
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'title' => 'Title',
            'description' => 'Description',
            'content' => 'Content',
            'date' => 'Date',
            'image' => 'Image',
            'viewed' => 'Viewed',
            'user_id' => 'User ID',
            'status' => 'Status',
            'category_id' => 'Category ID',
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     */

    public function saveImage($filename)
    {
        $this->image = $filename;
        return $this->save(false);
    }

    public function getImage()
    {
        return ($this->image) ? '/uploads/' . $this->image : '/no-image.png';
    }

    public function deleteImage()
    {
        $imageUploadModel = new ImageUpload();
        $imageUploadModel->deleteCurrentImage($this->image);
    }

    public function beforeDelete()
    {
        $this->deleteImage();
        return parent::beforeDelete(); // TODO: Change the autogenerated stub
    }

    public function getCategory()
    {
        return $this->hasOne(Category::className(), ['id' => 'category_id']);
    }

    public function saveCategory($category_id)
    {
        $category = Category::findOne($category_id);
        if($category != null)
        {
            $this->link('category', $category);
            return true;
        }
    }

    public function getTags()
    {
        return $this->hasMany(Tag::className(), ['id' => 'tag_id'])
            ->viaTable('article_tag', ['article_id' => 'id']);
    }


    public function getSelectedTags()
    {
        $selectedIds = $this->getTags()->select('id')->asArray()->all();
        return ArrayHelper::getColumn($selectedIds, 'id');
    }

    public function saveTags($tags)
    {
        if (is_array($tags))
        {
            $this->clearCurrentTags();

            foreach($tags as $tag_id)
            {
                $tag = Tag::findOne($tag_id);
                $this->link('tags', $tag);
            }
        }
    }

    public function clearCurrentTags()
    {
        ArticleTag::deleteAll(['article_id'=>$this->id]);
    }



    public function isAllowed()
    {
        return $this->status;
    }

    public function allow()
    {
        $this->status = self::STATUS_ALLOW;
        return $this->save(false);
    }
    public function disallow()
    {
        $this->status = self::STATUS_DISALLOW;
        return $this->save(false);
    }

    public function getDate()
    {
        return Yii::$app->formatter->asDate($this->date)." at ".Yii::$app->formatter->asTime($this->date.' CEST');
    }

    public static function getAll($pageSize = 5)
    {

        $query = Article::find()->where(['status' =>1]);

        $count = $query->count();

        $pagination = new Pagination(['totalCount' => $count, 'pageSize'=>$pageSize]);

        $articles = $query->offset($pagination->offset)
            ->limit($pagination->limit)
            ->all();

        $data['articles'] = $articles;
        $data['pagination'] = $pagination;

        return $data;
    }


    public static function getPopular()
    {
        return Article::find()->orderBy('viewed desc')->limit(3)->where(['status' =>1])->all();
    }

    public static function getRecent()
    {
        return Article::find()->orderBy('date desc')->limit(3)->where(['status' =>1])->all();
    }

    public  function getRelated()
    {
        if(!empty($this->category_id) and $this->category->getArticlesCount()!=1)
        {
            return Article::find()->where(['category_id' =>$this->category_id,'status' =>1])->andWhere('id != :id', ['id'=>$this->id])->all();
        }
    }


    public function getDescription()
    {
        return mb_strcut($this->description, 0, 160);
    }

    public static function getNeighbors($id)
    {
        $next_id = Article::find()->where('id > :id', ['id'=>$id])->min('id');
        $previous_id = Article::find()->where('id < :id', ['id'=>$id])->max('id');

        $next = Article::find()->where(['id' => $next_id])->one();
        $previous = Article::find()->where(['id' => $previous_id])->one();

        $data['previous'] = $previous;
        $data['next'] = $next;

        return $data;
    }

    public static function getArticleSearch($q)
    {
        if($q!="")
        {
            $query = Article::find()->where(['like','content',$q])->orWhere(['like','title',$q])->orWhere(['like','description',$q]);
        }else
            {
               $query =  Article::find()->where(['id'=>1]);
            }

        $count = $query->count();

        $pagination = new Pagination(['totalCount' => $count, 'pageSize'=>6]);

        $articles = $query->offset($pagination->offset)
            ->limit($pagination->limit)
            ->all();

        $data['articles'] = $articles;
        $data['pagination'] = $pagination;

        return $data;
    }

    public function getComments()
    {
        return $this->hasMany(Comment::className(), ['article_id' => 'id']);
    }

    public function getArticleComments()
    {
        return $this->getComments()->where(['status'=>1])->all();
    }

    public function getAuthor()
    {
        return $this->hasOne(User::className(), ['id'=>'user_id']);
    }

    public function viewedCounter()
    {
        $this->viewed += 1;
        return $this->save(false);
    }

}
