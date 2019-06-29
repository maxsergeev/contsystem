<?php

namespace frontend\models;

use \common\components\dez\CastValueBehavior;
use linslin\yii2\curl\Curl;
use Yii;
use yii\base\ErrorException;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;
use yii\helpers\ArrayHelper;
use yii\web\Response;
use yii\web\UploadedFile;
use yii\base;
use common\models\User;
use common\components\dez\Tools;

/**
 * This is the model class for table "articles".  
 *
 * @property int $id
 * @property string $title
 * @property string $url
 * @property string $isChecked
 * @property integer $hostId
 * @property integer $dateCreate ВНИМАНИЕ - ЭТО ДАТА ПОСЛЕДНЕГО ИЗМЕНЕНИЯ
 * @property string $keysString
 * @property string $keysStringEncode
 * @property integer $host
 * @property integer $dateCreate2 ВНИМАНИЕ - ЭТО ДАТА СОЗДАНИЯ
 *
 * @
 */
/*
 * const statusNew новое ТЗ
 * const statusNazAuthor ТЗ у автора
 * const statusExpertTesting = 1.5;  
 * const statusNapAuthor ТЗ написано автором
 * const statusAdjustment ТЗ у корректировщка
 * const statusReadyForDesign ТЗ готово к публикации
 * const statusDesign ТЗ у публиковщика
 * const statusSEO на проверке у КМ
 * const statusEdit
 * const statusReadyToPublish ТЗ готово к публикации
 * const statusVerification ТЗ на утверждении
 * const statusVerified ТЗ утверждено
 * const statusPublished ТЗ опубликовано
 * const statusCompleted ТЗ завершено
 *  
 * strtegs теги
 * comments комментарии к ТЗ
 * articleId идентификатор ТЗ
 * textArticle текст статьи
 * url ссылка на ТЗ
 * status статус ТЗ
 * returncom возврат комментария для ТЗ
 * dateSend дата создание ТЗ
 * urgently срочность
 * author идентификатор автора
 * admin идентификатор адмиана
 * authordate дата завершения ТЗ автором
 * corrector идентификатор корректора
 * correctordate дата завершения ТЗ корректором
 * publisher идентивикатор публиковщика
 * publisherdate дата завершения ТЗ публиковщиком
 * seo идентификатор SEO исполнтеля
 * doc количество приложенных документов
 * seodate дата завершения ТЗ SEO
 * factorkm коэффициент для расчёта оплаты КМ
 * factorseo коэффициент для расчёта оплаты SEO
 * factorpub коэффициент для расчёта оплаты публиковщика
 * factorcor коэффициент для расчёта оплаты корректора
 * factoraut коэффициент для расчёта оплаты автора
 * сharacters количество символов ТЗ
 * worker исполнитель ТЗ 
 * kmdate дата выполнение ТЗ КМ
 * hidden скрыть ТЗ
 * expertTesting 
 * editorcom комментарии редактора
 * wayMaster 
 * master 
 * editorTesting 
 * needPublisher необходимость публиковщика в ТЗ
 * needCorrector необходимость корректора в ТЗ
 */
class Tz extends \yii\db\ActiveRecord {

    const statusNew = 0;
    const statusNazMaster = 0.5;
    const statusNazAuthor = 1;
    const statusExpertTesting = 1.5;
    const statusNapAuthor = 2;
    const statusAdjustment = 3;
    const statusReadyForDesign = 4;
    const statusDesign = 5;
    const statusSEO = 6;
    const statusEdit = 6.5;
    const statusProverkaMaster = 7.5;
    const statusReadyToPublish = 7;
    const statusVerification = 8;
    const statusVerified = 9;
    const statusPublished = 10;
    const statusCompleted = 11;
    const scenarioNotify = 'notify';

    public $keysList = '';
    public $tegsArr = array();

//    const SCENARIO_SAVE = 'save';
    const SCENARIO_SENDCHECK = 'check';

//    public $uniqueUrl = '';

    public static function tableName() {
        return 'tz';
    }

    public function getWorker() {
        
    }

//
//    public function scenarios() {
////
////        $scenarios = parent::scenarios();
////        $scenarios['SCENARIO_SAVE'] = ['uniqueUrl'];
////        $scenarios['SCENARIO_SENDCHECK'] = ['uniqueUrl', 'textArticle'];
////        return $scenarios;
//
//        return [
////            self::SCENARIO_SAVE => ['uniqueUrl'],
//            self::SCENARIO_SENDCHECK => ['uniqueUrl', 'textArticle'],
//        ];
//    }

    public function rules() {
        return [
            [['title', 'text', 'category', 'ready'], 'required'],
//            [['uniqueUrl'], 'safe', 'on' => self::SCENARIO_SAVE],
            [['uniqueUrl'], 'required', 'on' => self::SCENARIO_SENDCHECK],
            [['uniqueUrl'], 'match', 'pattern' => '/^((https?:\/\/)?(text)\.(ru)\/(antiplagiat)\/([a-z0-9]{10,15})\/?)$/', 'on' => self::SCENARIO_SENDCHECK],
//            [['uniqueUrl'], 'match', 'pattern' => '/^((https?:\/\/)?(text)\.(ru)\/(antiplagiat)\/([a-z0-9]{10,15})\/?)?$/', 'on' => self::SCENARIO_SAVE],
            [['title', 'url', 'hostId'], 'string', 'max' => 255],
            [['textArticle'], 'string', 'min' => 500, 'on' => self::SCENARIO_SENDCHECK],
            [['keysString', 'dateCreate', 'dateCreate2', 'keysStringEncode', 'hostId', 'host', 'сharacters'], 'string'],
            [['keysStringEncode'], 'match', 'pattern' => '/^(?:\s*[\wа-яА-ЯёЁ ]+ *\| *\d+ *[\n\r]*)+$/ui',
                'message' => 'Значение «{attribute}» должно соответсвовать формату: <div style="margin-left: 5px; font-weight: bold;">Ключ1|15<br>Ключ2|5</div>'],
            ['url', 'url'],
            [['url'], 'unique', 'targetAttribute' => ['url', 'hostId'], 'message' => 'Такой URL уже существует'],
            //[['url'], 'match', 'pattern' => '/^[^?&]+$/ui', 'message' => 'В URL запрещены символы ? или &'],
            [['url'], function($attribute) {
                    if (mb_strpos($this->$attribute, explode(':', Yii::$app->params['selectedHost'])[1]) === false) {
                        $this->addError($attribute, 'Доменное имя должно совпадать с сайтом, выбранным на панели');
                    }
                }],
            [['strtegs', 'comments', 'articleId', 'textArticle', 'keysString', 'keysStringEncode', 'url', 'status', 'returncom', 'dateSend', 'urgently', 'author', 'admin',
            'authordate', 'corrector', 'correctordate', 'publisher', 'publisherdate', 'seo', 'doc', 'seodate', 'factorkm', 'factorseo', 'factorpub', 'factorcor', 'factoraut',
            'сharacters', 'worker', 'kmdate', 'hidden', 'expertTesting', 'editorcom', 'wayMaster', 'master', 'editorTesting', 'needPublisher', 'needCorrector', 'uniqueUrl'], 'safe']
        ];
    }

    public function attributeLabels() {
        return [
            'сharacters' => 'Кол.во символов',
            'id' => 'ID',
            'isChecked' => 'Добавлен',
            'title' => 'Заголовок',
            'text' => 'Текст ТЗ',
            'category' => 'Категория',
            'textArticle' => 'Текст статьи',
            'url' => 'Ссылка на статью',
            'uniqueUrl' => 'Ссылка на уникальность',
            'urlHome' => 'Ссылка на сайт',
            'status' => 'Статус',
            'dateCreate' => 'Дата изменения',
            'dateCreate2' => 'Дата создания',
            'dateEnd' => 'Дата готовности',
            'tegs' => 'Теги',
            'keysString' => 'Ключи',
            'strtegs' => 'Теги',
            'keysStringEncode' => 'Ключи',
            'ready' => 'ready',
            'comments' => 'Комментарий',
            'KeysStringDecode' => 'Ключи',
            'urgently' => 'Срочно',
            'returncom' => 'Комментарий для возврата',
            'editorcom' => 'Комментарий редактора',
            'doc' => 'Кол-во добавленных документов',
            'hostId' => 'Сайт',
            'host' => 'Сайт',
            'author' => 'Автор',
            'corrector' => 'Корректор',
            'publisher' => 'Публиковщик',
            'hidden' => 'Невидимость',
            'expertTesting' => 'Требуется проверка экспертом',
            'editorTesting' => 'Требуется проверка редактором',
            'wayMaster' => 'Выберите ответственного',
            'master' => 'Мастер текста',
        ];
    }

    /**
     * Функция для возврата типа ТЗ userRole
     * 
     * @param $userRole - роль пользователя 
     * 
     * @return $type
     */
    public function getType($userRole) {
        $type = '';
        switch ($this->status) {
            case Tz::statusNew:
                switch ($userRole) {
                    case [User::ROLE_SUPERADMIN, User::ROLE_KM, User::ROLE_EDITOR]:
                        $type = 'new';
                        break;
                }
                break;
            case Tz::statusNazAuthor :
                switch ($userRole) {
                    case [User::ROLE_SUPERADMIN, User::ROLE_KM, User::ROLE_EDITOR]:
                        $type = 'author';
                        break;
                    case User::ROLE_AUTHOR:
                        $type = 'work';
                        break;
                }
                break;
            case Tz::statusExpertTesting :
                switch ($userRole) {
                    case [User::ROLE_SUPERADMIN, User::ROLE_KM, User::ROLE_EDITOR]:
                        $type = 'expert';
                        break;
                }
                break;
            case Tz::statusAdjustment :
                switch ($userRole) {
                    case [User::ROLE_SUPERADMIN, User::ROLE_KM, User::ROLE_EDITOR]:
                        $type = 'corrector';
                        break;
                    case User::ROLE_AUTHOR:
                        $type = 'complite';
                        break;
                    case User::ROLE_CORRECTOR:
                        $type = 'work';
                        break;
                }
                break;
            case Tz::statusDesign :
                switch ($userRole) {
                    case [User::ROLE_SUPERADMIN, User::ROLE_KM]:
                        $type = 'publisher';
                        break;
                    case User::ROLE_EDITOR:
                        $type = 'work';
                        break;
                    case User::ROLE_PUBLISHER:
                        $type = 'work';
                        break;
                    case User::ROLE_AUTHOR:
                        $type = 'complite';
                }
                break;
            case Tz::statusNapAuthor :
                switch ($userRole) {
                    case [User::ROLE_SUPERADMIN, User::ROLE_EDITOR]:
                        $type = 'checkkm';
                        break;
                    case User::ROLE_KM :
                        $type = 'work';
                        break;
                    case User::ROLE_AUTHOR :
                        $type = 'during';
                        break;
                }
                break;
            case Tz::statusReadyForDesign :
                switch ($userRole) {
                    case [User::ROLE_SUPERADMIN, User::ROLE_EDITOR]:
                        $type = 'checkkm';
                        break;
                    case User::ROLE_KM:
                        $type = 'work';
                        break;
                    case User::ROLE_AUTHOR:
                        if ($model->needCorrector == 0) {
                            $type = 'during';
                        } else {
                            $type = 'complite';
                        }
                        break;
                    case User::ROLE_CORRECTOR :
                        $type = 'during';
                        break;
                }
                break;
            case Tz::statusSEO :
                switch ($userRole) {
                    case [User::ROLE_SUPERADMIN, User::ROLE_EDITOR]:
                        $type = 'checkkm';
                        break;
                    case User::ROLE_KM:
                        $type = 'work';
                        break;
                    case User::ROLE_AUTHOR:
                        if ($model->needCorretor == 0 && $model->needPublisher == 0) {
                            $type = 'during';
                        } else {
                            $type = 'complite';
                        }
                        break;
                    case User::ROLE_CORRECTOR :
                        if ($model->needPublisher == 0 && $model->status == Tz::statusSEO) {
                            $type = 'during';
                        } else {
                            $type = 'complite';
                        }
                        break;
                    case User::ROLE_PUBLISHER:
                        $type = 'during';
                        break;
                }
                break;
            case Tz::statusEdit :
                switch ($userRole) {
                    case User::ROLE_KM:
                        $type = 'verification';
                        break;
                }
                break;
            case Tz::statusReadyToPublish :
                switch ($userRole) {
                    case User::ROLE_KM:
                        $type = 'verification';
                        break;
                    case User::ROLE_AUTHOR:
                        $type = 'complite';
                        break;
                    case User::ROLE_CORRECTOR :
                        $type = 'complite';
                        break;
                    case User::ROLE_PUBLISHER:
                        $type = 'complite';
                        break;
                }
                break;
            case Tz::statusVerification :
                switch ($userRole) {
                    case User::ROLE_SUPERADMIN:
                        $type = 'work';
                        break;
                    case User::ROLE_EDITOR:
                        $type = 'verification';
                        break;
                    case User::ROLE_KM:
                        $type = 'complite';
                        break;
                    case User::ROLE_AUTHOR:
                        $type = 'complite';
                        break;
                    case User::ROLE_CORRECTOR :
                        $type = 'complite';
                        break;
                    case User::ROLE_PUBLISHER:
                        $type = 'complite';
                        break;
                }
                break;
            case Tz::statusVerified :
                switch ($userRole) {
                    case [User::ROLE_SUPERADMIN, User::ROLE_EDITOR]:
                        $type = 'complite';
                        break;
                    case User::ROLE_AUTHOR:
                        $type = 'complite';
                        break;
                    case User::ROLE_CORRECTOR :
                        $type = 'complite';
                        break;
                    case User::ROLE_PUBLISHER:
                        $type = 'complite';
                        break;
                }
                break;
        }
        Yii::info('$type');
        Yii::info($type);
        return $type;
    }

    public function getUrl() {
        return $this->url;
    }

    /**
     * 
     * Функцмя назначение исполнителя для ТЗ
     * 
     * @param type $value id пользователя
     * 
     */
    public function setWorker($value) {
        $name = User::getrolenameforid($value);
        switch ($name) {
            case ('author'):
                $this->author = $value;
                break;
            case ('corrector'):
                $this->corrector = $value;
                break;
            case ('publisher'):
                $this->publisher = $value;
                break;
            case ('seo'):
                $this->seo = $value;
                break;
        }
    }

    public function getKeys() {

        return $this->hasMany(Tzkeys::className(), ['tzid' => 'id']);
    }

    /**
     * ..не используется
     * @return $total
     * 
     */
    public function getTotalFrequency() {
        $total = 0;
        foreach ($this->keys as $key) {
            $total = $total + $key->frequency;
        }
        return $total;
    }

    public function getKeysString($encode = false) {
        // TODO Конкатенация значений и частоток ключей из базы
        return $encode ? str_replace('\n', '&#10;', $this->keysList) : $this->keysList;
    }

    public function getKeysStringEncode() {
        return $this->getKeysString(true);
    }

    /**
     * Этап статуса ТЗ
     * 
     * @return string
     * 
     */
    public function getStage() {
        switch ($this->status) {
            case '0':
                return 'Новое';
                break;
            case '1':
                return 'Назначено автору ';
                break;
            case '2':
                return 'Написано автором';
                break;
            case '3':
                return 'На корректировке ';
                break;
            case '4':
                return 'Готово к оформлению';
                break;
            case '5':
                return 'На оформлении';
                break;
            case '6':
                return 'На проверке у КМ';
                break;
            case '7':
                return 'SEO';
                break;
            case '8':
                return 'Готово к публикации';
                break;
            case '9':
                return 'Завершено';
                break;
        }
    }

    /**
     * 
     * Расшифрование ключей
     * 
     * @return $str
     * 
     */
    public function getKeysStringDecode() {
        $keys = $this->getKeys()->all();
        Yii::info($keys);
        $str = '';
        foreach ($keys as $key) {
            $str = $str . $key->value . '|' . $key->frequency . "\r\n";
        }
        return $str;
    }

    /**
     * Присвоение ключей
     * 
     * @param type $value
     */
    public function setKeysString($value) {
        Yii::info($value);
        Tzkeys::deleteAll(['tzid' => $this->id]);
        $this->keysList = preg_replace('/\R+/u', PHP_EOL, trim($value));
        $this->keysList = preg_replace('/^(\R*)|(\R*$)/u', '', $this->keysList);
        $this->keysList = preg_replace('/\s*\|\s*/u', '|', $this->keysList);
        Yii::info($this->keysList);
        $this->on(self::EVENT_AFTER_INSERT, function($e) {
            if (mb_strlen($this->keysList) > 0) {
                Yii::trace('Get raw keys for article ' . json_encode($this->keysList, JSON_UNESCAPED_UNICODE), __METHOD__);
                $keys = preg_split('/\r\n|\r|\n/u', $this->keysList);
                Yii::trace('Get keys for article ' . json_encode($keys, JSON_UNESCAPED_UNICODE), __METHOD__);
                foreach ($keys as $key) {
                    $keyModel = new Tzkeys();
                    $key = explode('|', $key);
                    Yii::trace('Get keys ' . json_encode($key, JSON_UNESCAPED_UNICODE), __METHOD__);
                    $keyModel->tzid = $this->id;
                    $keyModel->value = $key[0];
                    $keyModel->frequency = $key[1];
                    if (!$keyModel->save()) {
                        Yii::trace('errors in save Keys model ' . json_encode($keyModel->getErrors(), JSON_UNESCAPED_UNICODE));
                    };
                }
            }
        });
        //EVENT_AFTER_UPDATE
        $this->on(self::EVENT_AFTER_UPDATE, function($e) {

            if (mb_strlen($this->keysList) > 0) {
                Yii::trace('Get raw keys for article ' . json_encode($this->keysList, JSON_UNESCAPED_UNICODE), __METHOD__);
                $keys = preg_split('/\r\n|\r|\n/u', $this->keysList);
                Yii::trace('Get keys for article ' . json_encode($keys, JSON_UNESCAPED_UNICODE), __METHOD__);
                foreach ($keys as $key) {
                    $keyModel = new Tzkeys();
                    $key = explode('|', $key);
                    Yii::trace('Get keys ' . json_encode($key, JSON_UNESCAPED_UNICODE), __METHOD__);
                    $keyModel->tzid = $this->id;
                    $keyModel->value = $key[0];
                    $keyModel->frequency = $key[1];
                    if (!$keyModel->save()) {
                        Yii::trace('errors in save Keys model ' . json_encode($keyModel->getErrors(), JSON_UNESCAPED_UNICODE));
                    };
                }
            }
        });
    }

    /*
     * 
     * Получение тегов ТЗ
     * 
     */

    public function getTegs() {
        return $this->hasMany(Tztegs::className(), ['id' => 'tegsid'])
                        ->viaTable('tztegs', ['tzid' => 'id']);
    }

    /**
     * Получение хоста ТЗ
     * 
     * @return string
     * 
     */
    public function getHost() {
        if ($this->hostId == 'Без публикации') {
            return 'Без публикации';
        } else {
            return split(':', $this->hostId)[1];
        }
    }

    /**
     * 
     * Получение даты завершения ТЗ у каждой роли
     * 
     * @param type $role
     * @return type
     */
    public function getDateEnd($role) {
        switch ($role) {
            case User::ROLE_KM:
                return date('d.m.Y G:i:s', $this->kmdate);
            case User::ROLE_SEO:
                return date('d.m.Y G:i:s', $this->dateEnd);
            case User::ROLE_PUBLISHER:
                return date('d.m.Y G:i:s', $this->publisherdate);
            case User::ROLE_CORRECTOR:
                return date('d.m.Y G:i:s', $this->correctordate);
            case User::ROLE_AUTHOR:
                return date('d.m.Y G:i:s', $this->authordate);
            case User::ROLE_MASTER:
                return date('d.m.Y G:i:s', $this->dateEnd);
        }
    }

    public function getHostName() {
        return split(':', $this->hostId)[1];
    }

    /**
     * Получение количества символов ТЗ
     * 
     * @param type $role
     * @return количество символов ТЗ
     */
    public function getCountTz($role) {
        if ($role == User::ROLE_AUTHOR || $role == User::ROLE_CORRECTOR) {
            return $this->сharacters;
        } else {
            return Tools::units($this->textArticle);
        }
    }

    /**
     * Стоимость исполнения ТЗ
     * 
     * @param type $user
     * @param type $rate
     * @param type $ratedoc
     * @return 
     */
    public function getPrice($user, $rate, $ratedoc) {
        if ($user == User::ROLE_AUTHOR) {
            $rez = $this->сharacters * $rate * $this->factoraut;
        } elseif ($user == User::ROLE_MASTER) {
            $rez = (Tools::units($this->textArticle)) * $rate;
        } elseif ($user == User::ROLE_CORRECTOR) {
            $rez = $this->сharacters * $rate * $this->factorcor;
        } elseif ($user == User::ROLE_PUBLISHER) {
            $rez = (Tools::units($this->textArticle) * $rate * $this->factorpub) + ($ratedoc * $this->doc);
        } elseif ($user == User::ROLE_SEO) {
            $rez = $rate * $this->factorseo;
        } elseif ($user == User::ROLE_KM) {
            $rez = Tools::units($this->textArticle) * $rate * $this->factorkm;
        }
        return ceil($rez);
    }

    /**
     * Получение тегов ТЗ
     * 
     * @return type
     * 
     */
    public function getStrtegs() {

        $a = array();
        foreach ($this->getTegs()->all() as $tzteg) {
            array_push($a, $tzteg->name);
        }
        return implode(',', $a);
    }

    /**
     * 
     * Применение тегов ТЗ
     * 
     * @param type $value
     * 
     */
    public function setStrtegs($value) {
        $this->tegsArr = explode(',', $value);
    }

    /**
     * Действия после сохранения ТЗ
     * 
     * @param type $insert
     * @param type $changedAttributes
     */
    public function afterSave($insert, $changedAttributes) {
        if (count($this->tegsArr) > 0) {
            Yii::info('DELETE');
            Yii::$app->db->createCommand("DELETE FROM tztegs  WHERE tzid=" . $this->id)->execute();
            foreach ($this->tegsArr as $tegs) {
                if ($Tztegs = Tztegs::find()
                        ->where(['name' => $tegs])
                        ->one()) {
                    Yii::$app->db->createCommand()->insert('tztegs', [
                        'tzid' => $this->id,
                        'tegsid' => $Tztegs->id,
                    ])->execute();
                } else {
                    $Tztegs = new Tztegs();
                    $Tztegs->name = $tegs;
                    $Tztegs->hostId = $this->hostId;
                    $Tztegs->save();
                    Yii::$app->db->createCommand()->insert('tztegs', [
                        'tzid' => $this->id,
                        'tegsid' => $Tztegs->id,
                    ])->execute();
                }
            }
        }
        parent::afterSave($insert, $changedAttributes);
    }

    /**
     * Получения списка статусов ТЗ
     * 
     * @param $list - массив
     * @return $list массив дефолтных статусов ТЗ
     */
    public function getListstatus() {
        $list = array();
        $listdefault = array('Новое', 'В обработке', 'Готово');
        foreach ($listdefault as $status) {
            if ($status != $this->status) {
                array_push($list, $status);
            }
        }
        return $list;
    }

    /**
     * 
     * Установление хоста
     * 
     * @param type $value
     * 
     */
    public function setHost($value) {
        $this->hostId = $value;
    }

    /**
     * Получение тегов 
     * 
     * @param type $dbName
     * @param type $host
     * @return \frontend\models\Tztegs
     */
    public function getAlltags($dbName, $host) {
        Yii::info('SSE');
        Yii::info(Yii::$app->params['selectedHost']);
        $item = Tztegs::find()
                ->where(['hostId' => $host])
                ->all();
        if (in_array($host, array_keys(Yii::$app->params['wpDbs']))) {
            $prefix = Yii::$app->$dbName->tablePrefix;
            $item_tags = Yii::$app->$dbName->createCommand("SELECT name FROM {$prefix}term_taxonomy , {$prefix}terms WHERE taxonomy='post_tag' AND {$prefix}term_taxonomy.term_id = {$prefix}terms.term_id")->queryAll();
            foreach ($item_tags as $item_tags_array) {
                $item[] = new Tztegs($item_tags_array);
            }
        }
        return $item;
    }

    /**
     * Получение термов из других ресурсов
     * 
     * @param type $dbName
     * @return string
     */
    public function getTerms($dbName) {
        $tegs_arr = array();
        $groap_arr = array();
        $prefix = Yii::$app->$dbName->tablePrefix;
        $item = Yii::$app->$dbName->createCommand("SELECT {$prefix}terms.term_id ,name FROM {$prefix}term_taxonomy , {$prefix}terms WHERE taxonomy='category' AND {$prefix}term_taxonomy.term_id = {$prefix}terms.term_id AND parent = '0'")->queryAll(); //Все радители 
        $item_group = Yii::$app->$dbName->createCommand("SELECT {$prefix}terms.term_id ,name,parent FROM {$prefix}term_taxonomy , {$prefix}terms WHERE parent != '0' AND {$prefix}term_taxonomy.term_id = {$prefix}terms.term_id")->queryAll(); //Все дети
        $groap_arr = array_merge($item, $item_group);
        Yii::info($item);
        Yii::info($item_group);
        foreach ($item as &$item_group_first) {
            $tegs_arr[$item_group_first['name']] = $item_group_first['name'];
            foreach ($item_group as &$item_group_secont) {
                if ($item_group_secont['parent'] == $item_group_first['term_id']) {
                    $tegs_arr["{$item_group_first['name']} -> {$item_group_secont['name']}"] = '--' . $item_group_secont['name'];
                    foreach ($item_group as &$item_group_third) {
                        if ($item_group_third['parent'] == $item_group_secont['term_id']) {
                            $tegs_arr["{$item_group_first['name']} -> {$item_group_secont['name']} -> {$item_group_third['name']}"] = '----' . $item_group_third['name'];
                        }
                    }
                }
            }
        }

        return $tegs_arr;
    }

    /**
     * Удаление ТЗ
     * 
     * @param type $params
     * @return type
     */
    public function actionDelete($params = []) {
        if (strtotime('+2 day', strtotime($this->dateCreate)) > time()) {
            foreach ($this->getTegs()->all() as $tag) {
                $tag->delete();
            }
            Yii::$app->db->createCommand()->delete('tztegs', 'tzid=' . $this->id)->execute();

            foreach ($this->getKeys()->all() as $key) {
                $key->delete();
            }
            $this->delete();
            return ['error' => false];
        }
        return ['error' => true, 'errors' => 'Невозможно удалить эту статью.'];
    }

    /**
     * Экспорт ТЗ
     * 
     * @param type $params
     */
    public function export($params = []) {
        Yii::info('Число\n');
        Yii::info(substr_count($this->text, "\n"));
        Yii::$app->response->sendContentAsFile(preg_replace('/\R/u', "\r\n", $this->text), $this->title . '.txt')->send();
    }

    /**
     * 
     * @return type
     */
    public function behaviors() {
        return [
            [
                'class' => CastValueBehavior::className(),
                'patterns' => [
                    [
                        'regExp' => '/title/',
                        'when' => CastValueBehavior::CAST_BEFORE_VALIDATE,
                        'callback' => function ($v, $k, $m) {
                            /* @var $m Article */
                            if (isset($v) && $v) {
                                return $v;
                            }
                            if (isset($m->url) && $m->url) {
                                $ch = new Curl();
                                $response = $ch->setHeaders([
                                            'User-Agent' => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/58.0.3029.81 Safari/537.36'
                                        ])->get($m->url);
                                if ($ch->responseCode != 200) {
                                    throw new ErrorException('В ответ на запрос получен код отличный от 200, проверьте правильность URL');
                                }
                                \phpQuery::newDocument($response);
                                $title = \phpQuery::pq('title')->text();
                                return $title;
                            }
                            return '';
                        }
                    ],
                    [
                        'regExp' => '/hostId/',
                        'when' => CastValueBehavior::CAST_BEFORE_VALIDATE,
                        'callback' => function($v, $k, $m) {
                            /* @var $m Article */
                            if ($m->hostId)
                                return $m->hostId;
                            return Yii::$app->params['selectedHost'];
                        }
                    ],
                    [
                    ],
                    [
                        'regExp' => '/dateCreate2$/',
                        'when' => CastValueBehavior::CAST_AFTER_FIND,
                        'callback' => function($v, $k, $m) {
                            Yii::info('$v');
                            Yii::info($v);
                            return !is_string($v) ? date('d.m.Y G:i:s', $v) : $v;
                        }
                    ],
                    [
                        'regExp' => '/dateCreate2$/',
                        'when' => CastValueBehavior::CAST_BEFORE_SAVE,
                        'callback' => function($v, $k, $m) {
                            return is_integer($v) ? $v : strtotime($v);
                        }
                    ],
                    [
                        'regExp' => '/dateCreate$/',
                        'when' => CastValueBehavior::CAST_AFTER_FIND,
                        'callback' => function($v, $k, $m) {
                            Yii::info('$n');
                            Yii::info($v);
                            return date('d.m.Y G:i:s', $v);
                        }
                    ],
                    [
                        'regExp' => '/dateCreate$/',
                        'when' => CastValueBehavior::CAST_BEFORE_SAVE,
                        'callback' => function($v, $k, $m) {
                            if ($this->scenario != 'notify') {
                                return time();
                            } else {
                                return strtotime($this->dateCreate);
                            }
                        }
                    ]
                ]
            ],
            [
                'class' => TimestampBehavior::className(),
                'attributes' => [
                    ActiveRecord::EVENT_BEFORE_INSERT => 'dateCreate',
                    ActiveRecord::EVENT_BEFORE_INSERT => 'dateCreate2'
                ]
            ]
        ];
    }

    /**
     * Получение символов
     * 
     * @return type
     */
    public function getUnits() {
        return Tools::units($this->textArticle);
    }

    /**
     * Получение ссылки
     * 
     * @return type
     */
    public function getLink() {
        return "<a href=\"/tz/tz_edit?id={$this->id}\">{$this->title}</a>";
    }

}

?>