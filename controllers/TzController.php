<?php

namespace frontend\controllers;

use yii\web\Controller;
use Yii;
use ZipArchive;
use common\models\User;
use yii\data\ArrayDataProvider;
use yii\data\ActiveDataProvider;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\web\ForbiddenHttpException;
use yii\web\Response;
use frontend\models\YapiHost;
use frontend\models\YapiOriginalText;
use frontend\models\Tz;
use frontend\models\Info;
use frontend\models\TzSearch;
use frontend\models\UploadForm;
use yii\web\UploadedFile;
use common\components\dez\Tools;
use common\components\dez\Notify;

class TzController extends BaseController {

    public function behaviors() {
        return [
            'access' => [
                'class' => AccessControl::className(),
                'rules' => [
                    [
                        'allow' => true,
                        'actions' => ['notification1', 'notification24', 'notification_payment_worker_week', 'notification_payment_worker_month', 'notification_payment_admin_week', 'notification_payment_admin_month']
                    ],
                    [
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                    [
                        'allow' => false,
                        'roles' => ['?']
                    ]
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                    'text-add' => ['post', 'get'],
                    'tz-ajax' => ['post'],
                    'tz-add' => ['post', 'get']
                ],
            ],
        ];
    }

    /**
     * Отправка ТЗ на доработку
     * 
     * @return редирект на соответствующий хост ТЗ
     * 
     */
    public function actionSendreturn() {
        $user = Yii::$app->user->identity->role;
        $data = Yii::$app->request->get('id');
        $tz = Tz::findOne($data);
        $returncom = Yii::$app->request->post('Tz')['returncom'];
        $returnaddres = Yii::$app->request->post('addres');

        if (Yii::$app->user->identity->can([User::ROLE_SUPERADMIN, User::ROLE_EDITOR], 'can', FALSE) && $tz->wayMaster == 0 && !(in_array($tz->status, [Tz::statusExpertTesting, Tz::statusNapAuthor, Tz::statusReadyForDesign, Tz::statusSEO]))) {
            Yii::info('$returnaddres switch');
//            switch ($returnaddres) {
//                 
//                case 'km' :
//                    $tz->status = Tz::statusSEO;
//                    break;
//                case 'seo' :
//                    $tz->status = Tz::statusReadyToPublish;
//                    break;
//            }

            if ($tz->status == Tz::statusVerification) {
                $tz->status = Tz::statusSEO;
            }
        } else {
            if ($tz->wayMaster == 1) {
                Yii::info(time());
                switch ($tz->status) {
                    case Tz::statusEdit:
                        $tz->status = Tz::statusNazMaster;
                        break;
                    case Tz::statusVerification:
                        $tz->status = Tz::statusNazMaster;
                        break;
                    case Tz::statusProverkaMaster:
                        $tz->status = Tz::statusNazMaster;
                        break;
                }
            } else {
                Yii::info('tz > status');
                switch ($tz->status) {

                    case 1.5 :
                    case 2 :
                        $tz->status = Tz::statusNazAuthor;
                        $workerid = $tz->author;
                        break;
                    case 4 :
                        $tz->сharacters = Tools::units($tz->textArticle);
                        $tz->status = Tz::statusAdjustment;
                        $workerid = $tz->corrector;
                        break;
                    case 6 :
                        $tz->status = Tz::statusDesign;
                        $workerid = $tz->publisher;
                        break;
                }
            }
        }
        $tz->ready = 0;
        $tz->returncom = $returncom;
//        Yii::info($tz->returncom);
        $tz->save(false);
        Notify::notification_workers($tz);
        if (Yii::$app->request->isAjax) {
            Yii::$app->response->format = Response::FORMAT_JSON;
            return ['error' => false];
        } else {
            return Yii::$app->response->redirect(['tz?host=' . Yii::$app->params['selectedHostId']]);
        }
    }

    /**
     * Загрузка импортируемого ТЗ
     * 
     * @return boolean
     */
    public function actionUpload() {
        if (!Yii::$app->user->identity->can([User::ROLE_SUPERADMIN, User::ROLE_EDITOR], 'can', FALSE)) {
            return FALSE;
        } else {
            $model = new UploadForm();
            if (Yii::$app->request->isPost) {
                $model->textFile = UploadedFile::getInstances($model, 'textFile');
                Yii::info($model->textFile);
                Yii::info($model->textFile[0]->name);
                $str = '';
                $index = 1;
                foreach ($model->textFile as $file) {
                    $fileste = file_get_contents($file->tempName, true);
                    $code = mb_detect_encoding($fileste);
                    $str .= $this->renderAjax('tz_import', array(
                        'import' => $file->name,
                        'tz' => new Tz(),
                        'text' => iconv($code, 'utf-8', $fileste),
                        'index' => $index++
                    ));
                }

                return $str . '<button class="col-lg-12 import form-group well import_tz_buttom" id="import_all"><img src="js/gif-load.gif" style="padding-right: 4px;">Импорт</button>';
            }
        }
//        return $this->render('tz_load', ['model' => $model]);
    }

    /**
     * Отправка ТЗ на проверку
     * 
     * @return false если ТЗ сохранено
     */
    public function actionSendforreview() {
        $user = Yii::$app->user->identity->role;
        $data = Yii::$app->request->get('id');
        $tz = Tz::findOne($data);

        $tz->load(Yii::$app->request->post());
        switch ($user) {
            case User::ROLE_MASTER :
                $tz->load(Yii::$app->request->post());
                //if (User::getrolenameforid($tz->admin) == 'admin') {
                if ($tz->needPublisher) {
                    $publishers = User::getusersarr(User::ROLE_PUBLISHER);
                    foreach ($publishers as $publisher) {
                        foreach ($publisher['hosts'] as $host)
                            if ($tz->hostId == $host) {
                                $mypublisher = $publisher;
                            }
                    }
                    $tz->publisher = $mypublisher['id'];
                    $tz->status = Tz::statusProverkaMaster;
                } else {
                    if ($tz->editorTesting = FALSE) {
                        $tz->status = Tz::statusVerification;
                    } else {
                        $editors = User::getusersarr(2);
                        $myeditor = NULL;
                        foreach ($editors as $editor) {
                            Yii::info($editor);
                            foreach ($editor['hosts'] as $host) {
                                if ($tz->hostId == $host) {
                                    $myeditor = $editor;
                                }
                            }
                        }
                        $tz->status = Tz::statusEdit;
                        Notify::notification_editor($myeditor, $tz);
                    }
                }
                break;
            case User::ROLE_AUTHOR :
//                $tz = new Tz($data);
                $tz = Tz::find($data);
                $tz->scenario = Tz::SCENARIO_SENDCHECK;
                $tz->сharacters = Tools::units($tz->textArticle);
                $tz->authordate = time();
                if ($tz->expertTesting) {
                    $tz->status = Tz::statusExpertTesting;
                    Notify::notification_experts($tz);
                } else {
                    $tz->status = Tz::statusNapAuthor;
                }

//                } elseif (($tz->needCorrector && $tz->needPublisher) || ($tz->needCorrector && !$tz->needPublisher)) {
//                    $tz->status = Tz::statusNapAuthor;
//                    $tz->authordate = time();
//                } elseif (!$tz->needCorrector && $tz->needPublisher) {
//                    $tz->status = Tz::statusNapAuthor;
//                    $tz->authordate = time();
//                } elseif (!$tz->needCorrector && !$tz->needPublisher) {
//                    $tz->status = Tz::statusSEO;
//                    $tz->authordate = time();
//                }
//              
//                if ($tz->expertTesting) {
//                    $tz->status = Tz::statusExpertTesting;
//                    Notify::notification_experts($tz);
//                    $tz->authordate = time();
//                    $tz->сharacters = Tools::units($tz->textArticle);
//                } 
//                if ($tz->needCorrector){
//                    $tz->status = Tz::statusNapAuthor;
//                    $tz->authordate = time();
//                } 
//                if ($tz->needPublisher){
//                    $tz->status = Tz::statusReadyForDesign;
//                    $tz->authordate = time();
//                } else {
//                    $tz->status = Tz::statusSEO;
//                }
                break;
            case User::ROLE_EXPERT :
                $tz->load(Yii::$app->request->post());
                $tz->status = Tz::statusNapAuthor;
                break;
            case User::ROLE_CORRECTOR :
                $tz->correctordate = time();
                $tz->status = Tz::statusReadyForDesign;

                break;
            case User::ROLE_PUBLISHER :
                if ($tz->wayMaster) {
                    $editors = User::getusersarr(2);
                    $myeditor = NULL;
                    foreach ($editors as $editor) {
                        Yii::info($editor);
                        foreach ($editor['hosts'] as $host) {
                            if ($tz->hostId == $host) {
                                $myeditor = $editor;
                            }
                        }
                    }
                    if ($myeditor != NULL) {
                        $tz->status = Tz::statusEdit;
                    } else {
                        $tz->status = Tz::statusReadyToPublish;
                    }
                } else {
                    $tz->status = Tz::statusSEO;
                }
                $tz->publisherdate = time();
                break;
            case User::ROLE_KM :
                $seo = User::getusersarr(3)[0];
                $editors = User::getusersarr(2);
                $myeditor = NULL;
                foreach ($editors as $editor) {
                    Yii::info($editor);
                    foreach ($editor['hosts'] as $host) {
                        if ($tz->hostId == $host) {
                            $myeditor = $editor;
                        }
                    }
                }

                if (($tz->status == Tz::statusSEO) || ($tz->status == Tz::statusReadyForDesign && $tz->hostId == 'Без публикации')) {
                    if (count($seo->hosts) == 0) {
                        if ($myeditor != NULL && $tz->status = Tz::statusSEO && $tz->editorTesting = TRUE) {
                            $tz->status = Tz::statusEdit;
                            Notify::notification_editor($myeditor, $tz);
                        } else {
                            $tz->status = Tz::statusVerification;
                            $tz->kmdate = time();
                        }
                    } else {
                        foreach ($seo->hosts as $host) {
                            if ($host == $tz->hostId) {
                                $tz->status = Tz::statusReadyToPublish;
                                break;
                            } else {
                                if ($myeditor != NULL && $tz->status = Tz::statusSEO && $tz->editorTesting = TRUE) {

                                    $tz->status = Tz::statusEdit;
                                    Notify::notification_editor($myeditor, $tz);
                                } else {
                                    $tz->status = Tz::statusVerification;
                                    $tz->kmdate = time();
                                }
                            }
                        }
                    }
                } elseif ($tz->status == Tz::statusReadyForDesign && $tz->hostId == 'Без публикации') {
                    $tz->status = Tz::statusVerification;
                    $tz->kmdate = time();
                } elseif ($tz->status == Tz::statusNapAuthor) {
                    $tz->status = Tz::statusVerification;
                    $tz->kmdate = time();
                }
                break;
            case User::ROLE_SEO :
                if ($myeditor != NULL && $tz->status = Tz::statusSEO && $tz->editorTesting = TRUE) {
                    $tz->status = Tz::statusEdit;
                    Notify::notification_editor($myeditor, $tz);
                } else {
                    $tz->status = Tz::statusVerification;
                    $tz->seodate = time();
                }

                break;
            case User::ROLE_EDITOR :
//                if ($tz->status >= 6 && !$tz->needCorrector && !$tz->needPublisher) {
//                    $tz->authordate = time();
//                }
                //$tz->load(Yii::$app->request->post());
                if ($tz->status == Tz::statusExpertTesting) {
                    $tz->status = Tz::statusNapAuthor;
                } else {
                    if (!$tz->needPublisher) {
                        if (Yii::$app->params['selectedHost'] == 'Выберите сайт') {
                            foreach ($this->hosts as $onehost) {
                                if ($onehost->id == $tz->hostId) {
                                    $host = $onehost;
                                    break;
                                }
                            }
                        } else {
                            $host = $this->hosts[$this->host];
                        }
//                $cleanText = trim(str_replace("&nbsp;", '', (strip_tags($tz->textArticle))));
                        $cleanText = trim(html_entity_decode(strip_tags($tz->textArticle)));
                        $text = new YapiOriginalText([
                            'host' => $host,
                            'content' => $cleanText]);
                        $text->save(false);
                        $tz->articleId = $text->id;
                    }
                    $tz->status = Tz::statusVerification;
                }
//                if ($tz->needPublisher == 0 && $tz->status > 6) {
//                    $tz->correctordate = time();
//                    $tz->сharacters = Tools::units($tz->textArticle);
//                }
//                if ($tz->needPublisher) {
//                    $tz->publisherdate = time();
//                    $tz->сharacters = Tools::units($tz->textArticle);
//                }
//                if ($tz->status >= 6 && !$tz->needCorrector && !$tz->needPublisher) {
//                    $tz->сharacters = Tools::units($tz->textArticle);
//                    $tz->authordate = time(); 
//                }
                break;
            case User::ROLE_SUPERADMIN :
                $tz->load(Yii::$app->request->post());
                $seo = User::getusersarr(3)[0];
                $editors = User::getusersarr(2);
                $myeditor = NULL;
                if ($tz->status == Tz::statusProverkaMaster) {
                    $tz->status = Tz::statusDesign;
                    break;
                }
                foreach ($editors as $editor) {
                    Yii::info($editor);
                    foreach ($editor['hosts'] as $host) {
                        if ($tz->hostId == $host) {
                            $myeditor = $editor;
                        }
                    }
                }
                if (($tz->status == Tz::statusSEO) || ($tz->status == Tz::statusReadyForDesign && $tz->hostId == 'Без публикации')) {
                    if (count($seo->hosts) == 0) {
                        if ($myeditor != NULL && $tz->status = Tz::statusSEO && $tz->editorTesting = TRUE) {
                            $tz->status = Tz::statusEdit;
                            Notify::notification_editor($myeditor, $tz);
                        } else {
                            $tz->status = Tz::statusVerification;
                            $tz->kmdate = time();
                        }
                    } else {
                        foreach ($seo->hosts as $host) {
                            if ($host == $tz->hostId) {
                                $tz->status = Tz::statusReadyToPublish;
                                break;
                            } else {
                                if ($myeditor != NULL && $tz->status = Tz::statusSEO && $tz->editorTesting = TRUE) {

                                    $tz->status = Tz::statusEdit;
                                    Notify::notification_editor($myeditor, $tz);
                                } else {
                                    $tz->status = Tz::statusVerification;
                                    $tz->kmdate = time();
                                }
                            }
                        }
                    }
                } elseif ($tz->status == Tz::statusReadyForDesign && $tz->hostId == 'Без публикации') {
                    $tz->status = Tz::statusVerification;
                    $tz->kmdate = time();
                } elseif ($tz->status == Tz::statusNapAuthor) {
                    $tz->status = Tz::statusVerification;
                    $tz->kmdate = time();
                }
                //
                if ($tz->status == Tz::statusExpertTesting) {
                    $tz->status = Tz::statusNapAuthor;
                } else {
                    $tz->dateEnd = time();
                    if ($tz->kmdate == NULL) {
                        $tz->kmdate = time();
                    }
                    $tz->status = Tz::statusVerified;
                }

                if ($tz->wayMaster) {
//                    $cleanText = trim(html_entity_decode(strip_tags($tz->textArticle)));
//                    $text = new YapiOriginalText([
//                        'host' => $host,
//                        'content' => $cleanText]);
//                    $text->save(false);
                }
                break;
        }
        $tz->ready = 0;
        $tz->save(FALSE);
        Notify::notification_km_multi($tz);
        // Если запрос не аякс, то обычный редирект
        $dataKey = Yii::$app->request->get('id');
        if (Yii::$app->request->isAjax) {
            Yii::$app->response->format = Response::FORMAT_JSON;
            return ['error' => false, 'dataKey' => $dataKey];
        } else {
            //Дальше шагает в Tzedit !
            return Yii::$app->response->redirect(['tz?host=' . Yii::$app->params['selectedHostId'] . '&type=' . $tz->getType($user)]);
        }
    }

    /**
     * 
     * Подсчет количества ТЗ во вкладках
     * 
     * @return обновленное значение количество ТЗ во вкладках
     * 
     */
    public function actionTabcounttz() { //Вкладки ТЗ
        $searchModel = new TzSearch();
        $type = Yii::$app->request->get('type');
        Yii::info($type);
        //достать сам type
        //проверить исправность данных
        //проверить какой хост
        $dataProvider = $searchModel->search(Yii::$app->request->get(), $type)->getTotalCount();
        Yii::info($dataProvider);
        Yii::$app->response->format = Response::FORMAT_JSON;
        return ['dataCount' => $dataProvider, 'type' => $type];

//        $user = Yii::$app->user->identity->role;
//        $data = Yii::$app->request->get('id');
//        $tz = Tz::findOne($data);
//        return Yii::$app->response->redirect(['tz?host=' . Yii::$app->params['selectedHostId'] . '&type=' . $tz->getType($user)]);
//        
    }

    /**
     * 
     * @return AJAX запрос на рендер 
     * 
     */
    public function actionIndex() {
        if ($this->host == '0') {
            end(Yii::$app->params['hosts']);
            $this->redirect(['tz/index', 'host' => key(Yii::$app->params['hosts'])]);
        }
        $thisHost = Yii::$app->params['selectedHostId'];
        $arrHostId = array_keys(Yii::$app->params['hosts']);
        $lastHostIdPop = array_pop($arrHostId);
        if ($lastHostIdPop != $thisHost && (Yii::$app->user->identity->can([User::ROLE_AUTHOR, User::ROLE_CORRECTOR], 'can', false))) {
            $this->redirect(['tz/index', 'host' => $lastHostIdPop]);
        }
        $type = Yii::$app->request->get('type');
        $searchModel = new TzSearch();
        $dataProvider = $searchModel->search(Yii::$app->request->get(), $type);
        $dataProvider->sort->defaultOrder = [
            'dateCreate2' => SORT_DESC
        ];
//        Yii::info($dataProvider->models[0]->title);
//        Yii::info($dataProvider->models[0]->dateCreate2);
        $dataProvider->pagination->pageSize = 20;
        $dataProvider->pagination->pageSizeParam = false;

        if (Yii::$app->request->isAjax) {
            return $this->renderAjax('tz', [
                        'searchModel' => $searchModel,
                        'dataProvider' => $dataProvider
            ]);
        }
        return $this->render('tz', [
                    'searchModel' => $searchModel,
                    'dataProvider' => $dataProvider
        ]);
    }

    public function actionTz_edit() {
        return $this->render('tz_edit');
    }

    public function actionNotify() {
        if (!Yii::$app->request->get('secret') || Yii::$app->request->get('secret') != Yii::$app->params['notifySecret']) {
            throw new ForbiddenHttpException();
        }
        return json_encode(Article::actionEmailNotification([
//            'range' => !Yii::$app->request->get('period') ? null : '-' . Yii::$app->request->get('period') . ' day',
                    'emails' => Yii::$app->params['adminEmails'],
//            'hosts' => $this->hosts
                ]), JSON_UNESCAPED_UNICODE);
    }

    /**
     * Редактирование ТЗ
     * 
     * @return false если ТЗ сохранено
     * 
     */
    public function actionTzedit() {
        $data = Yii::$app->request->get('id');
        $tz = Tz::findOne($data);
//        $tz->scenario = Tz::SCENARIO_SAVE;
//        $tz = new Tz($data, ['scenario' => Tz::SCENARIO_SAVE]);
        $tz_old_text = $tz->textArticle;
        $tz->load(Yii::$app->request->post());
        
        if (Yii::$app->params['selectedHost'] == 'Все сайты') {
            foreach ($this->hosts as $onehost) {
                if ($onehost->id == $tz->hostId) {
                    $host = $onehost;
                    break;
                }
            }
        } else {
            $host = $this->hosts[$this->host];
        }
        $cleanText = trim(preg_replace('~\s+~s', '', str_replace("&nbsp;", '', (strip_tags($tz->textArticle)))));
//        if ($tz_old_text != $tz->textArticle && $tz->hostId != 'Без публикации') {
//            if (!$tz_old_text) {
//
//                $text = new YapiOriginalText([
//                    'host' => $host,
//                    'content' => $tz->textArticle,]);
//                $text->save(false);
//                $tz->articleId = $text->id;
//            } elseif ($tz->textArticle) {
//                $text = new YapiOriginalText([
//                    'host' => $host,
//                    'id' => $tz->articleId,
//                ]);
//                try {
//                    $text->delete($text->id);
//                } catch (yii\base\ErrorException $x) {
//                    
//                }
//            } elseif (!$tz->textArticle) {
//                $text = new YapiOriginalText([
//                    'host' => $host,
//                    'id' => $tz->articleId,
//                ]);
//                $text->delete($text->id);
//                $tz->save(false);
//                return Yii::$app->response->redirect(['tz?host=' . Yii::$app->params['selectedHostId']]);
//            }
//
//
//            $text_new = new YapiOriginalText([
//                'host' => $host,
//                'content' => $tz->textArticle,
//            ]);
//            $text_new->save(false);
//        } else {
//            
//        }
        if ($tz->author && $tz->status == 0) {
            $tz->status = 1;
        }

        if (!$tz->validate()) {
            // проверка не удалась:  $errors - это массив содержащий сообщения об ошибках
            $errors = $tz->errors;
            Yii::info($errors);
            $save = true;
            foreach ($errors as $attr => $value) {

                if ($attr == 'uniqueUrl') {
                    $save = false;
                    Yii::info($attr);
                }
            }
            if ($save) {
                $tz->save(false);
            }
        } else {
            $tz->save(false);
        }







        if (Yii::$app->request->isAjax) {
            Yii::$app->response->format = Response::FORMAT_JSON;
            return ['error' => false, 'oldText' => $tz_old_text];
        }
        return Yii::$app->response->redirect(['tz?host=' . Yii::$app->params['selectedHostId'] . '&type=' . $tz->getType(Yii::$app->user->identity->role)]);
    }

    /**
     * 
     * @return редирект на соответствующуй хост ТЗ
     * 
     */
    public function actionTzvisible() {
        $id = Yii::$app->request->get('id');
        $tz = Tz::findOne($id);
        $tz->hidden = ($tz->hidden + 1) % 2;
        Yii::info($tz);
        $tz->save(false);
        return Yii::$app->response->redirect(['tz?host=' . Yii::$app->params['selectedHostId']]);
    }

    /**
     * Добавление ТЗ
     * 
     * @return если ТЗ сохранено - рендер нового ТЗ, иначе JSON массив с error и телом ТЗ
     * 
     */
    public function actionTzadd() {
        $id = Yii::$app->request->post('id');
        Yii::info($id);
        Yii::$app->user->identity->can([User::ROLE_SUPERADMIN, User::ROLE_EDITOR], 'can');
        if ($this->host == '0') {
            return $this->render('select-error');
        }
        $tz = new Tz();
        $tz->isChecked = 1;
        $tz->ready = '0';
        $tz->admin = Yii::$app->user->identity->id;
        $tz->factorkm = 1;
        $tz->factorseo = 1;
        $tz->factorpub = 1;
        $tz->factorcor = 1;
        $tz->factoraut = 1;
        if ($tz->load(Yii::$app->request->post())) {

            if ($tz->wayMaster) {
                $tz->status = 0.5;
            } else {
                if ($tz->author) {
                    $tz->status = 1;
                } else {
                    $tz->status = 0;
                    Notify::notification_km_multi($tz);
                }
            }
            if ($tz->save(false)) {
                if ($tz->author) {
                    Notify::notification_workers($tz);
                }
                return $this->renderAjax('tz_add', ['tz' => new Tz()]);
            }
        } else {
            Yii::trace(
                    'Validation errors occurs method save in Tz model '
                    . json_encode($tz->getErrors(), JSON_UNESCAPED_UNICODE) . ' value: ' . $tz->keysString, 'Tz'
            );
            Yii::$app->response->format = Response::FORMAT_JSON;
            return ['error' => true, 'body' => $this->renderAjax('tz_add', ['tz' => $tz])];
        }
    }

    /**
     * Загрузка ТЗ
     * 
     * @return рендер текущего ТЗ
     */
    public function actionLoad() {
        $tz = new Tz();
        return $this->render('tz_load', ['tz' => $tz]);
    }

    /**
     * Экспортирование ТЗ
     * 
     * @return  экспорт ТЗ
     * 
     */
    public function actionExport() {
        Yii::$app->user->identity->can([User::ROLE_KM, User::ROLE_SUPERADMIN, User::ROLE_EDITOR], 'can');
        $tz = new Tz();
        $tz = Tz::find()
                ->where(['id' => Yii::$app->request->get('tzid')])
                ->one();
        return $tz->export();
    }

    /**
     * Экспорт нескольких ТЗ
     * 
     * @return type
     */
    public function actionMulti_export() {
        Yii::$app->user->identity->can([User::ROLE_KM, User::ROLE_SUPERADMIN, User::ROLE_EDITOR], 'can');
        $data = Yii::$app->request->get('id');
        $tz_arr = Tz::findAll($data);
        if (count($tz_arr) == 1) {
            return $tz_arr[0]->export();
        }
        $zip = new ZipArchive();
        $res = $zip->open('export-' . date("m.d.y") . '.zip', ZipArchive::OVERWRITE);
        foreach ($tz_arr as $tz) {
            Yii::info('Число\n');
            Yii::info(substr_count($tz->text, "\n"));
            $zip->addFromString(iconv('utf-8', 'CP866//TRANSLIT//IGNORE', $tz->title . '.txt'), preg_replace('/\R/u', "\r\n", $tz->text));
        }
        $zip->close();
        Yii::$app->response->sendFile('export-' . date("m.d.y") . '.zip')->send();
        unlink('export-' . date("m.d.y") . '.zip');
    }

    /**
     * Отправка ТЗ на email (УСТАРЕЛО, НЕ ИСПОЛЬЗУЕТСЯ)
     * 
     */
    public function actionSendtz() {
        Yii::$app->user->identity->can([User::ROLE_KM, User::ROLE_SUPERADMIN, User::ROLE_EDITOR], 'can');
        $data_id = Yii::$app->request->get('id');
        $tz_arr = Tz::findAll($data_id);
        $email = Yii::$app->request->post('email');
        if (count($tz_arr) == 1) {
            Yii::info(substr_count($tz_arr[0]->text, "/n"));
            $res = Yii::$app->mailer->compose()
                    ->setTo($email)
                    ->setFrom('content@rucas.ru')
                    ->setSubject('ТЗ с content.ru')
                    ->setTextBody('ТЗ с content.ru')
                    ->attachContent(preg_replace('/\R/u', "\r\n", $tz_arr[0]->text), ['fileName' => $tz_arr[0]->title . '.txt', 'contentType' => 'text/plain'])
                    ->send();
            $tz_arr[0]->email = $email;
            $tz_arr[0]->dateSend = time();
            $tz_arr[0]->save(false);
        } else {
//            $zip = new ZipArchive();
//            $res = $zip->open('export-' . date("m.d.y") . '.zip', ZipArchive::OVERWRITE);
            foreach ($tz_arr as $tz) {
                Yii::info('Число\n');
                Yii::info(substr_count($tz->text, "\n"));
                $zip->addFromString(iconv('utf-8', 'CP866//TRANSLIT//IGNORE', $tz->title . '.txt'), preg_replace('/\R/u', "\r\n", $tz->text));
                $tz->email = $email;
                $tz->dateSend = time();
                $tz->save(false);
            }
            $zip->close();
            $res = Yii::$app->mailer->compose()
                    ->setTo($email)
                    ->setFrom('content@rucas.ru')
                    ->setSubject('ТЗ с content.ru ')
                    ->setTextBody('Архив ТЗ с content.ru')
                    ->attach('export-' . date("m.d.y") . '.zip')
                    ->send();
        }
    }

    /**
     * Пропуск исполнтеля ТЗ
     * 
     * @return JSON массив с error, workerid, статус ТЗ, необходоимость в корректоре и публиковщике
     * 
     */
    public function actionSkipworker() {
        $id = Yii::$app->request->post('id');
        $workerid = Yii::$app->request->post('worker');
        $tz = Tz::findOne($id);
        yii::info($workerid);
        switch ($tz->status) {
            case '0':
            case '1':
            case '2':
            case '3':
                if ($workerid == 'skip') {

                    if ($tz->needCorrector == 1) {
                        Yii::info($tz->needCorrector);
                        $tz->needCorrector = 0;
                        Yii::info($tz->needCorrector);
                    } else {
                        $tz->needPublisher = 0;
                    }
                }
                Yii::info($tz->needCorrector);
                break;
            case '4':
            case '5':
                if ($workerid == 'skip') {
                    $tz->needPublisher = 0;
                }
                break;
        }
        $tz->save(false);
        if (Yii::$app->request->isAjax) {
            Yii::$app->response->format = Response::FORMAT_JSON;
            return ['error' => false, 'worker' => $workerid, 'needCorrector' => $needCor, 'needPublisher' => $needPub, 'status' => $tz->status];
        }
    }

    /**
     * 
     * Назначение исполнетеля для ТЗ
     * 
     * @return JSON массив с error, workerid, статус ТЗ, необходоимость в корректоре и публиковщике
     * 
     */
    public function actionSetworker() {
        $id = Yii::$app->request->post('id');
        $workerid = Yii::$app->request->post('worker');
        Yii::info($workerid);
        $tz = Tz::findOne($id);
        if ($tz->status == Tz::statusReadyForDesign && $status == Tz::statusAdjustment) {
            $tz->ready = 0;
        }
        switch ($tz->status) {
            case '0':
            case '1':

                $tz->author = $workerid;
//                $tz->authordate = time();
                $tz->status = Tz::statusNazAuthor;
                break;

            case '2':
            case '3':
                if ($workerid == 'skippublisher') {
                    $tz->needPublisher = 0;
                    break;
                } elseif ($workerid == 'skipcorrector') {
                    $tz->needCorrector = 0;
                    break;
                } else {
                    if ($tz->needCorrector == 0) {
                        $tz->status = Tz::statusDesign;
                        $tz->publisher = $workerid;
                        if (Yii::$app->params['selectedHost'] == 'Выберите сайт') {
                            foreach ($this->hosts as $onehost) {
                                if ($onehost->id == $tz->hostId) {
                                    $host = $onehost;
                                    break;
                                }
                            }
                        } else {
                            $host = $this->hosts[$this->host];
                        }
//                $cleanText = trim(str_replace("&nbsp;", '', (strip_tags($tz->textArticle))));
                        $cleanText = trim(html_entity_decode(strip_tags($tz->textArticle)));
                        $text = new YapiOriginalText([
                            'host' => $host,
                            'content' => $cleanText]);
                        $text->save(false);
                        $tz->articleId = $text->id;
                        break;
                    } else {
                        $tz->сharacters = Tools::units($tz->textArticle);
                        $tz->corrector = $workerid;
                        $tz->status = Tz::statusAdjustment;
                        break;
                    }
                }
            case '4':
            case '5':
                if ($workerid == 'skippublisher') {
                    $tz->needPublisher = 0;
                    break;
                } else {
                    $tz->сharacters = Tools::units($tz->textArticle);
                    $tz->publisher = $workerid;
                    $tz->status = Tz::statusDesign;
                    if (Yii::$app->params['selectedHost'] == 'Выберите сайт') {
                        foreach ($this->hosts as $onehost) {
                            if ($onehost->id == $tz->hostId) {
                                $host = $onehost;
                                break;
                            }
                        }
                    } else {
                        $host = $this->hosts[$this->host];
                    }
//                $cleanText = trim(str_replace("&nbsp;", '', (strip_tags($tz->textArticle))));
                    $cleanText = trim(html_entity_decode(strip_tags($tz->textArticle)));
                    $text = new YapiOriginalText([
                        'host' => $host,
                        'content' => $cleanText]);
                    $text->save(false);
                    $tz->articleId = $text->id;
                    break;
                }

            case '6':
            case '7':
                $tz->seo = $workerid;
                $tz->status = Tz::statusReadyToPublish;
                break;
        }
        $tz->ready = 0;
        $tz->returncom = '';
        $tz->save(false);
//        Yii::info($tz->needCorrector);
        Notify::notification_workers($tz);
        if (Yii::$app->request->isAjax) {
            Yii::$app->response->format = Response::FORMAT_JSON;
            return ['error' => false, 'worker' => $workerid, 'needCorrector' => $needCor, 'needPublisher' => $needPub, 'status' => $tz->status];
        }
    }

    public function actionTzAjax() {
        return parent::ajaxActiveRecord(Tz::className(), 'row');
    }

//    public function actionGetrolename() {
//        Yii::info(Yii::$app->user->identity->role);
//        $data = Yii::$app->request->get('id');
//        $tz = Tz::findOne($data);
//        if (Yii::$app->request->isAjax) {
//            Yii::$app->response->format = Response::FORMAT_JSON;
//            return ['error' => false, 'user' => Yii::$app->user->identity->role, 'status' => $tz->status];
//        }
//    } 
}

?>
