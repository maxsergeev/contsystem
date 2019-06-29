<?php

namespace frontend\controllers;

use Yii;
use common\models\User;
use common\components\dez\Notify;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\web\ForbiddenHttpException;
use frontend\models\Tz;
use yii\data\ArrayDataProvider;
use yii\data\ActiveDataProvider;

class NotifyController extends BaseController {

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
     * Уведомление исполнителя об оплате за неделю
     * 
     * @throws ForbiddenHttpException
     */
    public function actionNotification_payment_worker_week() {
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
    public function actionNotification_payment_worker_month() {
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
    function actionNotification_payment_admin_week() {
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
    function actionNotification_payment_admin_month() {
        if (!Yii::$app->request->get('secret') || Yii::$app->request->get('secret') != Yii::$app->params['notifySecret']) {
            throw new ForbiddenHttpException();
        }
        $this->notification_payment_admin('month', User::ROLE_KM);
    }

    /**
     * Уведомление КМ и исполнителей о новых ТЗ раз в час
     * 
     * @throws ForbiddenHttpException
     */
    public function actionNotification1() {
        if (!Yii::$app->request->get('secret') || Yii::$app->request->get('secret') != Yii::$app->params['notifySecret']) {
            throw new ForbiddenHttpException();
        }
        Notify::notification_km(0, 'Уведомление о новых ТЗ', 'добавлены новые ТЗ');
        Notify::notification_km(2, 'Уведомление о написанных автором ТЗ', 'новые ТЗ написанные автором');
        Notify::notification_km(4, 'Уведомление о готовых к оформлению ТЗ', 'новые ТЗ готовые к оформлению');
        Notify::notification_km(6, 'Уведомление о ТЗ требующих проверки', 'новые ТЗ требующие проверки ');
        Notify::notification_km(9, 'Уведомление о завершенных ТЗ', 'новые завершенные ТЗ');

        Notify::notification_worker(1, 'Уведомление о новых ТЗ ', 'author', 6, 'вам назначены новые ТЗ');
        Notify::notification_worker(3, 'Уведомление о новых ТЗ ', 'corrector', 5, 'вам назначены новые ТЗ');
        Notify::notification_worker(5, 'Уведомление о новых ТЗ ', 'publisher', 4, 'вам назначены новые ТЗ');
        Notify::notification_worker(7, 'Уведомление о новых ТЗ ', 'seo', 3, 'вам назначены новые ТЗ');

        Notify::notification_masters();
    }

    /**
     * Уведомление админа о готовности ТЗ раз в сутки
     * 
     * @throws ForbiddenHttpException
     */
    public function actionNotification24() {
        if (!Yii::$app->request->get('secret') || Yii::$app->request->get('secret') != Yii::$app->params['notifySecret']) {
            throw new ForbiddenHttpException();
        }
        Notify::notification_admin('Уведомление о готовых к публикации ТЗ', 'новые ТЗ готовые к оформлению');
        Notify::notification_editors();
    }

    /**
     * Приглашение в систему RUCAS
     * 
     * @throws ForbiddenHttpException
     */
    function actionInvite() {
        $str = '';
        $users = Yii::$app->params['users'];
        $subject = 'Приглашение в систему RUCAS Content';
        foreach ($users as $user => $value) {
            $text = <<<HTML
<p>Здравствуйте, {$value['byname']}!</p>
<p>Вас приветствует компания RUCAS. С сегодняшнего дня мы начинаем работать через нашу систему управления созданием контента.</p>
<p>Для входа используйте следующие данные:</p>
<p><a href="http://new.content.rucas.ru">http://new.content.rucas.ru</a></p>
<p>Логин: {$value['username']}</p>
<p>Пароль: {$value['password']}</p>
<p>На вашу почту <strong>{$value['email']}</strong> вы будете получать уведомления о поступлении новых заданий.</p>
HTML;
            if ($value['id'] == 19) {
                Notify::sendmail($value['email'], $text, $subject);
            }
        }
    }

    /**
     * Отправка письма о технических работах на сервере
     * 
     * @throws ForbiddenHttpException
     */
    function actionMail() {
        $str = '';
        $users = Yii::$app->params['users'];
        $subject = 'Технические работы завершены';
        foreach ($users as $user => $value) {
            $text = <<<HTML
<p>Здравствуйте, {$value['byname']}!</p>
<p>Технические работы на сайте завершены. Как только у вас обновится DNS и откроется https://content.rucas.ru можно работать с системой.</p>
HTML;
//            if (!in_array($value['role'], [User::ROLE_EDITOR, User::ROLE_KM, User::ROLE_SEO]) && !in_array($value['id'], [2, 101])) {
//if($value['id']==3) {
            Notify::sendmail($value['email'], $text, $subject);
//}                
//            }
        }
    }

    /**
     * Уведомление админа об оплате
     * 
     * @throws ForbiddenHttpException
     */
    function notification_payment_admin($type, $role) {
        $query = Yii::$app->params['users'];
        if ($type == 'week') {
            foreach ($query as $user) {
                if ($user['role'] == User::ROLE_PUBLISHER || $user['role'] == User::ROLE_CORRECTOR || $user['role'] == User::ROLE_AUTHOR)
                    $result[] = $user;
            }
        }else {

            foreach ($query as $user) {
                if ($user['role'] == User::ROLE_SEO || $user['role'] == User::ROLE_KM)
                    $result[] = $user;
            }
        }
        $provider = new ArrayDataProvider([
            'allModels' => $result,
            'pagination' => [
                'pageSize' => 10,
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
        $mail = User::getusers($role);
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
     * @throws ForbiddenHttpException
     */
    function notification_payment_worker($user) {


        $user_identity = User::getuserstoid($user)[0];
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

    public function actionNotifyAjax() {
        return parent::ajaxActiveRecord(Tz::className(), 'row');
    }

}
