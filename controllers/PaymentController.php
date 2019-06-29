<?php

namespace frontend\controllers;

use Yii;
use common\models\User;
use yii\data\ArrayDataProvider;
use yii\data\ActiveDataProvider;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\web\ForbiddenHttpException;
use frontend\models\Tz;
use frontend\models\TzSearch;
use frontend\models\MoneyParams;
use common\components\dez\Notify;
use common\components\dez\Tools;

class PaymentController extends BaseController {

    public function behaviors() {
        return [
            'access' => [
                'class' => AccessControl::className(),
                'rules' => [
                    [
                        'allow' => true,
                        'actions' => ['notification1', 'notification24', 'notification-payment-worker-week', 'notification-payment-worker-month', 'notification-payment-admin-week', 'notification-payment-admin-month']
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
     * 
     * @return рендер раздела оплаты исполнителя
     * 
     */
    public function actionIndex() {
        $moneyParams = new MoneyParams();
        if ($moneyParams->load(Yii::$app->request->post())) {
            $moneyParams->load(Yii::$app->request->post());
        }

        $type = Yii::$app->request->get('type');
        $time = Yii::$app->request->get('time');
        $user = Yii::$app->request->get('user');
        $mod = Yii::$app->request->get('mod');
        $km = Yii::$app->request->get('km');
        $start = Yii::$app->request->get('start', 0);
        $end = Yii::$app->request->get('end', 0);
        $userIdentity = Yii::$app->user->identity;

        $dateRange = Yii::$app->request->get('daterange');
        $resultDate = explode('-', $dateRange);
        $startDate = strtotime($resultDate[0]);
        $finishDate = strtotime($resultDate[1]);
        if ($time == 'range') {
            $startm = $startDate;
            $endm = $finishDate + 86399;
            $startw = $startDate;
            $endw = $finishDate + 86399;
        } else {

            if ($start !== 0 && $end !== 0) {
                $startm = $startw = $start;
                $endm = $endw = $end;
            } elseif ($time == 'last') {
                $startm = strtotime("first day of previous month"); // первый день прошлого месяца
                $endm = strtotime("last day of previous month") + 86399; // последний день прошлого месяца
                $startw = strtotime('Monday previous week') + 3600; //понедельник прошлой недели
                $endw = strtotime('Sunday previous week') + 86399 + 3600; //воскресенье прошлой недели
            } else {
                $startm = strtotime(date("Y-m-01"));
                $endm = strtotime(date("Y-m-t")) + 86399;
                $startw = strtotime('Monday this week') + 3600;
                $endw = strtotime('Sunday this week') + 86399 + 3600;
            }
            if ($mod == '1' && $start == 0 && $end == 0) {
                Yii::info($mod);
                $startw = strtotime('Monday this week');
                $endw = $startw + 3600;
            }
        }
//        Yii::info($startDate);
//        Yii::info($finishDate);
//        Yii::info($endm);
//        Yii::info($startm);
//        Yii::info($time);
//        Yii::info($dateRange);
//        Yii::info('$mod');
//        Yii::info('$mod');
        //понедельник 00 00 1517788800
        //понедельник 01 00 1517792400

        if ((Yii::$app->user->identity->can([User::ROLE_SUPERADMIN], 'can', FALSE) || $km) && !$user) {

            $query = Yii::$app->params['users'];
//            Yii::info($query);
            if ($type == 'week') {
                foreach ($query as $user) {
                    Yii::info($user);
                    if ($user['role'] == User::ROLE_PUBLISHER || $user['role'] == User::ROLE_CORRECTOR || $user['role'] == User::ROLE_AUTHOR || $user['role'] == User::ROLE_MASTER) {
                        if ($user['disabled'] != true) {
//                            
                            $user['pay'] = Tools::getUserPriceAllHosts([$user], $time, $type, 1, false);
                            if ($user['pay'] != 0) {
                                $result[] = $user;
                            }
                        }
                    }
                }
            } else {
                foreach ($query as $user) {
                    if ($user['role'] == User::ROLE_SEO || $user['role'] == User::ROLE_KM) {
                        if ($user['disabled'] != true) {
                            $result[] = $user;
                        }
                    }
                }
            }

            $provider = new ArrayDataProvider([
                'allModels' => $result,
                'pagination' => [
                    'pageSize' => 100,
                ],
                'sort' => [
                    'attributes' => ['id', 'name'],
                ],
            ]);
            return $this->render('/payment/payment', [
                        'dataProvider' => $provider,
                        'type' => $type,
                        'time' => $time,
                        'km' => $km,
                        'moneyParams' => $moneyParams
            ]);
        }
        if ($user) {
            $userIdentity = new User();
            $userIdentity->constructUser($user);
        }
        $result = $userIdentity->getMyTz($startm, $endm, $startw, $endw);
        $provider = new ActiveDataProvider([
            'query' => $result,
            'pagination' => [
                'pageSize' => 100,
            ],
            'sort' => [
                'attributes' => ['id', 'title', 'date'],
            ],
        ]);
        return $this->render('/payment/paymentwork', [
                    'dataProvider' => $provider,
                    'type' => $type,
                    'user_identity' => $userIdentity,
        ]);
    }

    /**
     * Уведомление админа об оплате
     * 
     * @param type $type
     * @param type $role
     * 
     */
    function notification_payment_admin($type, $role) {
        $query = Yii::$app->params['users'];
        if ($type == 'week') {
            foreach ($query as $user) {
                if ($user['role'] == User::ROLE_PUBLISHER || $user['role'] == User::ROLE_CORRECTOR || $user['role'] == User::ROLE_AUTHOR || $user['role'] == User::ROLE_MASTER) {
                    if ($role['disabled'] != true) {
                        $result[] = $user;
                    }
                }
            }
        } else {

            foreach ($query as $user) {
                if ($user['role'] == User::ROLE_SEO || $user['role'] == User::ROLE_KM) {
                    if ($role['disabled'] != true) {
                        $result[] = $user;
                    }
                }
            }
        }
        $provider = new ArrayDataProvider([
            'allModels' => $result,
            'pagination' => [
                'pageSize' => 100,
            ],
            'sort' => [
                'attributes' => ['id', 'name'],
            ],
        ]);
        $this->layout = false;
        $text = $this->render('/payment/payment', [
            'dataProvider' => $provider,
            'type' => $type,
            'time' => 'last',
            'mail' => true,
        ]);
        $mail = User::getusersarr($role);
        foreach ($mail as $user_mail => $valume) {
            Yii::$app->mailer->compose('layouts/payment_worker', ['title' => $text])
                    ->setFrom('content@rucas.ru')
                    ->setTo($valume['email'])
//                    ->setTo('das.agere@gmail.com')
                    ->setSubject('Уведомление об оплате')
                    ->send();
        }
    }

    /**
     * Уведомление исполнителя об оплате
     * 
     * @param type $user
     * 
     */
    function notification_payment_worker($user) {


        $user_identity = User::getuserstoid($user)[0];
        Yii::info('$user_identity');
        Yii::info($user_identity);
        $startm = strtotime("first day of previous month"); // первый день прошлого месяца
        $endm = strtotime("last day of previous month") + 86399; // последний день прошлого месяца
        $startw = strtotime('Monday previous week') + 3600; //понедельник прошлой недели
        $endw = strtotime('Sunday previous week') + 86399 + 3600; //воскресенье прошлой недели

        $dateRange = Yii::$app->request->get('daterange');
        $resultDate = explode('-', $dateRange);
        $start = strtotime($resultDate[0]);
        $finish = strtotime($resultDate[1]);
        if (Yii::$app->request->get('time') == 'range') {
            $startm = $start;
            $endm = $finish + 86399;
            $startw = $start;
            $endw = $finish + 86399;
        }

        $sortField = '';
        if ($user_identity['role'] == User::ROLE_KM) {
            $result = Tz::find()->where(['>', 'status', '8'])->andWhere(['>', 'dateEnd', $startm])->andWhere(['<', 'dateEnd', $endm]);
            $sortField = 'dateEnd';
        } elseif ($user_identity['role'] == User::ROLE_AUTHOR) {
            $result = Tz::find()->where(['author' => $user])->andWhere(['>', 'status', '2'])->andWhere(['>', 'authordate', $startw])->andWhere(['<', 'authordate', $endw]);
            $sortField = 'authordate';
        } elseif ($user_identity['role'] == User::ROLE_CORRECTOR) {
            $result = Tz::find()->where(['corrector' => $user])->andWhere(['>', 'status', '4'])->andWhere(['> ', 'correctordate', $startw])->andWhere(['<', 'correctordate', $endw]);
            $sortField = 'correctordate';
        } elseif ($user_identity['role'] == User::ROLE_PUBLISHER) {
            $result = Tz::find()->where(['publisher' => $user])->andWhere(['>', 'status', '6'])->andWhere(['> ', 'publisherdate', $startw])->andWhere(['<', 'publisherdate', $endw]);
            $sortField = 'publisherdate';
        } elseif ($user_identity['role'] == User::ROLE_SEO) {
            $result = Tz::find()->where(['seo' => $user])->andWhere(['>', 'status', '8'])->andWhere(['>', 'dateEnd', $startm])->andWhere(['<', 'dateEnd', $endm]);
            $sortField = 'dateEnd';
        }

        $provider = new ActiveDataProvider([
            'query' => $result,
            'pagination' => [
                'pageSize' => 100,
            ],
            'sort' => [
                'defaultOrder' => [
                    $sortField => SORT_ASC,
                ]
            ],
        ]);
        $this->layout = false;
        $text = $this->render('/payment/paymentwork', [
            'dataProvider' => $provider,
            'type' => $type,
            'user_identity' => $user,
            'mail' => true,
        ]);
        $mail = $user_identity['email'];
        Yii::$app->mailer->compose('layouts/payment_worker', ['title' => $text])
                ->setFrom('content@rucas.ru')
                ->setTo($mail)
//                ->setTo('das.agere@gmail.com')
                ->setSubject('Уведомление об оплате')
                ->send();
    }

    /**
     * Уведомление исполнителя об оплате за неделю
     * 
     * @throws ForbiddenHttpException
     * 
     */
    public function actionNotificationPaymentWorkerWeek() {
        if (!Yii::$app->request->get('secret') || Yii::$app->request->get('secret') != Yii::$app->params['notifySecret']) {
            throw new ForbiddenHttpException();
        }
        foreach (Yii::$app->params['users'] as $user) {
            if ($user['role'] != User::ROLE_SUPERADMIN && $user['role'] != User::ROLE_EDITOR && $user['role'] != User::ROLE_SEO && $user['role'] != User::ROLE_KM) {
                Yii::info($user['id']);
                $this->notification_payment_worker($user['id']);
            }
        }
    }

    /**
     * Уведомление исполнителя об оплате за месяц
     * 
     * @throws ForbiddenHttpException
     */
    public function actionNotificationPaymentWorkerMonth() {
        if (!Yii::$app->request->get('secret') || Yii::$app->request->get('secret') != Yii::$app->params['notifySecret']) {
            throw new ForbiddenHttpException();
        }
        foreach (Yii::$app->params['users'] as $user) {
            if ($user['role'] != User::ROLE_SUPERADMIN && $user['role'] != User::ROLE_EDITOR && $user['role'] != User::ROLE_AUTHOR && $user['role'] != User::ROLE_PUBLISHER && $user['role'] != User::ROLE_CORRECTOR) {
                Yii::info($user['id']);
                $this->notification_payment_worker($user['id']);
            }
        }
    }

    /**
     * Уведомление админа об оплате за неделю
     * 
     * @throws ForbiddenHttpException
     */
    function actionNotificationPaymentAdminWeek() {
        if (!Yii::$app->request->get('secret') || Yii::$app->request->get('secret') != Yii::$app->params['notifySecret']) {
            throw new ForbiddenHttpException();
        }
        $this->notification_payment_admin('week', User::ROLE_SUPERADMIN);
        $this->notification_payment_admin('week', User::ROLE_KM);
    }

    /**
     * Уведомление админа об оплате за месяц
     * 
     * @throws ForbiddenHttpException
     */
    function actionNotificationPaymentAdminMonth() {
        if (!Yii::$app->request->get('secret') || Yii::$app->request->get('secret') != Yii::$app->params['notifySecret']) {
            throw new ForbiddenHttpException();
        }
        $this->notification_payment_admin('month', User::ROLE_KM);
    }

    public function actionTzAjax() {
        return parent::ajaxActiveRecord(Tz::className(), 'row');
    }

    public static function units($str) {
        //Yii::info(preg_replace('#\&\S+?;#u', '0', ((strip_tags(html_entity_decode('Как анализировать криптовалюту и понимать направление трендов, нужно учиться с самого первого дня на бирже, а ещё лучше &ndash; задолго до своего первого торгового опыта. Благо биржевые торги &mdash; это довольно-таки старый вид деятельности, и различных методов анализа придумано уже немало. Одним из таких методов является технический анализ криптовалют. &nbsp;&nbsp;',ENT_HTML5,'UTF-8'))))));
        preg_match_all('#<ol.+?</ol>#s', $str, $matches);
        $liCount = 0;
        foreach ($matches[0] as $list) {
            $liCount += substr_count($list, '<li>');
        }
        Yii::info('licount');
        Yii::info($matches);
        Yii::info($liCount);
        return mb_strlen(trim(preg_replace('#\s*#su', '', ((html_entity_decode(strip_tags(preg_replace('#<li.*?>#s', '0', $str)))))), "UTF-8")) + $liCount;
    }

    public function actionPaymentinfo() {
        $moneyParams = new MoneyParams();
        if ($moneyParams->load(Yii::$app->request->post())) {
            $moneyParams->load(Yii::$app->request->post());
        }
        $type = Yii::$app->request->get('type');
        $time = Yii::$app->request->get('time');
        $all_sum_user = 0;
        $arrUsers = Yii::$app->params['users'];
//        Yii::info(Yii::$app->params['users'][777]['id']);// массив ключей юзеров 
//        Yii::info($_POST['users'][]); //массив id юзеров
        if ($_POST['users'] != NULL) {
            $arrId = $_POST['users'];
        } else {
            $arrId = array_keys(Yii::$app->params['users']);
        }
        $arrUnique = array();
        foreach ($arrId as $userId) {
            if (!array_key_exists($arrUsers[$userId]['purse'], $arrUnique)) {
                $userPrice = Tools::getUserPriceAllHosts([$arrUsers[$userId]], $time, $type, 1, false);
                if ($userPrice != 0 && !$arrUsers[$userId]['disabled'] && $arrUsers[$userId]['currency'] == '₴') {
                    if ($arrUsers[$userId]['purse'] != '') {
                        $arrUnique[$arrUsers[$userId]['purse']] = array($userId, $arrUsers[$userId]['fioname'], $userPrice);
                    }
                }
            } else {
                $userPrice = Tools::getUserPriceAllHosts([$arrUsers[$userId]], $time, $type, 1, false);
                foreach ($arrUnique as $k => $v) {
                    if ($k == $arrUsers[$userId]['purse']) {
                        $userPrice = $userPrice + $v[2];
                    }
                }
                if ($userPrice != 0 && !$arrUsers[$userId]['disabled'] && $arrUsers[$userId]['currency'] == '₴') {
                    if ($arrUsers[$userId]['purse'] != '') {
                        $arrUnique[$arrUsers[$userId]['purse']] = array($userId, $arrUsers[$userId]['fioname'], $userPrice);
                    }
                }
            }
        }
        foreach ($arrUnique as $purse => $data) {
            print_r('<br />' . $purse);
            print_r('<br />' . $data[1]);
            print_r('<br />' . $data[2] . '<br />');
            $all_sum_user = $all_sum_user + $data[2];
        }
        echo '<br /><br />ИТОГО: ' . $all_sum_user;
//        var_dump($arrUnique);
    }

}

?>
