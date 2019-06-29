<?php

use common\models\User;
use yii\helpers\Html;
use frontend\models\Tz;
use common\components\dez\Tools;

$this->registerJs(
        <<<JS
     
    $('[data-toggle="popover"]').popover({
        container : 'body'
    });
    $('#pjax').on('pjax:success', (e) => {
        $('[data-toggle="popover"]').popover({
            container : 'body'
        });
    });
    function ajax(e) {
        let _self = e.target;
        let _tr = $(_self).parents('tr');
        $.ajax({
            url : '/tz/tz-ajax',
            type : 'post',
            data : 'action=changeState&row=' + $(_tr).data('key'),
            success : (d) => {
                if(!d.error){
                    if($(_self).prop('checked') || d.status){
                        $(_tr).removeClass('warning').addClass('success');
                    } else {
                        $(_tr).removeClass('success').addClass('warning');
                    }
                } else {
                    console.log(d.errors);
                }
                
            },
            error : (d) => {
                console.log(d);
            }
        });
    }
    
    
    $('body').on('input', '.form-filter', (e) => {
        let _self = e.currentTarget;
        if(!$(_self).val())
            $(_self).trigger(jQuery.Event('keyup', {key: 'Enter'}));
    });
    // Поиск, фильтр по дате
    $('body').on('apply.daterangepicker keyup', '.form-filter', (e) => {
        let _self = e.currentTarget;
        if((e.type == 'keyup' && e.key == 'Enter') || e.type == 'apply'){
            if(decodeURIComponent(location.search).indexOf($(_self).attr('name')) > 0){
                let search = decodeURIComponent(location.search).replace(new RegExp($(_self).attr('name').replace('[', '\\\[').replace(']', '\\\]') + '=[^&]*', 'g'),$(_self).attr('name') + '=' + $(_self).val());
                history.pushState('','',location.pathname + search);
            } else {
                history.pushState('', '', location.pathname + (location.search ? location.search + '&' : '?') + $(_self).attr('name') + '=' + $(_self).val());
            }
            $.pjax.reload('#pjax');
        }
    });
    
    
JS
);

$this->title = 'Оплата';


if ($mail) {
    $user_identity;
    $user_identity = User::getuserstoid($user_identity)[0];
    $rate_mail = $user_identity['rate'] / 1000;
    $role = $user_identity['role'];
    $ratedoc = $user_identity['ratedoc'];
} else {
    if (Yii::$app->request->get('user')) {
        $user = Yii::$app->request->get('user');
        echo $user_identity->byname;
    } else {
        $user = Yii::$app->user->identity->id;
    }
    $users = Yii::$app->params['users'];
    $user_identity = array();
    foreach ($users as $user_work) {
        if ($user_work['id'] == $user)
            $user_identity = $user_work;
    }
}
if (Yii::$app->request->get('role')) {
    $user = Yii::$app->request->get('role');
} else {
    $user = Yii::$app->user->identity->role;
}
if ($mail) {
    $user = $role;
}
if (Yii::$app->request->get('rate')) {
    $rate = Yii::$app->request->get('rate') / 1000;
} else {
    $rate = Yii::$app->user->identity->rate / 1000;
}
if (Yii::$app->request->get('ratedoc')) {
    $ratedoc = Yii::$app->request->get('ratedoc');
} else {
    $ratedoc = Yii::$app->user->identity->ratedoc;
}
if ($mail) {
    $rate = $rate_mail;
}
?>

<?php if (!$mail) { ?>
    <div class="col-lg-12">
        <ul class="nav nav-tabs" style="margin-bottom: 12px; ">
            <li <?= ((Yii::$app->request->get('time') == 'last') ? "class='active'" : "") ?>><a href="<?= Tools::remove_key('time', 'daterange') . (Yii::$app->user->identity->can([User::ROLE_SUPERADMIN, User::ROLE_KM], 'can', FALSE) ? '&time=last' : 'time=last') ?>"><?= (($user_identity['role'] == User::ROLE_AUTHOR ? 'Прошлая неделя' : ($user_identity['role'] == User::ROLE_PUBLISHER ? 'Прошлая неделя' : ($user_identity['role'] == User::ROLE_CORRECTOR ? 'Прошлая неделя' : ($user_identity['role'] == User::ROLE_MASTER ? 'Прошлая неделя' : 'Прошлый месяц'))) )) ?></a></li>
            <li <?= ((Yii::$app->request->get('time') == '') ? "class='active'" : "") ?>><a href="<?= Tools::remove_key('time', 'daterange') ?>"><?= (($user_identity['role'] == User::ROLE_AUTHOR ? 'Текущая неделя' : ($user_identity['role'] == User::ROLE_PUBLISHER ? 'Текущая неделя' : ($user_identity['role'] == User::ROLE_CORRECTOR ? 'Текущая неделя' : ($user_identity['role'] == User::ROLE_MASTER ? 'Текущая неделя' : 'Текущий месяц'))) )) ?></a></li> 
            <?php if (Yii::$app->user->identity->can([User::ROLE_SUPERADMIN], 'can', FALSE)) { ?> 
                <li <?= (Yii::$app->request->get('time') == 'range' ? "class='active'" : "") ?>><a href="<?= Tools::remove_key('time', 'daterange') . '&time=range' ?>">Произвольная дата</a></li>
            <?php } ?>
        </ul>
    </div>  
    <?php if (Yii::$app->user->identity->can([User::ROLE_SUPERADMIN], 'can', FALSE) && (Yii::$app->request->get('time') == 'range')) { ?> 
        <div class="form-inline" style=" padding-bottom: 6px; display: inline-block; float:right; padding-right: 16px; padding-left: 16px"> 
            <?=
            \kartik\daterange\DateRangePicker::widget([
                'name' => 'daterange',
                'convertFormat' => true,
                'value' => Yii::$app->request->get('daterange'),
                'options' => [
                    'placeholder' => 'Фильтр по дате',
                    'class' => 'form-control form-filter',
                    'autocomplete' => "off",
                ],
                'pluginOptions' => array_merge([
                    'ranges' => [
                        'Сегодня' => ["moment().startOf('day')", "moment()"],
                        'Вчера' => ["moment().startOf('day').subtract(1,'days')", "moment().endOf('day').subtract(1,'days')"],
                        'Последние 7 дней' => ["moment().startOf('day').subtract(6, 'days')", "moment()"],
                        'Последние 30 дней' => ["moment().startOf('day').subtract(29, 'days')", "moment()"],
                        'Этот месяц' => ["moment().startOf('month')", "moment().endOf('month')"],
                        'Прошлый месяц' => ["moment().subtract(1, 'month').startOf('month')", "moment().subtract(1, 'month').endOf('month')"],
                    ],
                    'locale' => [
                        'format' => 'd.m.Y',
                        'separator' => '-'
                    ],
                    'opens' => 'left',
                ])
            ])
            ?> 
        </div> 
    <?php } ?>
<?php } else { ?>
    <p>Здравствуйте <?= $user_identity['byname'] ?> , отчет об оплате за:</p>
<?php } ?>

<?php
\yii\widgets\Pjax::begin([
    'options' => [
        'id' => 'pjax'
    ],
    'timeout' => 8000
]);
?>
<?=
\yii\grid\GridView::widget([
    'dataProvider' => $dataProvider,
    'filterModel' => false,
    'showFooter' => true,
    'summary' => '',
    'filterRowOptions' => [
        'style' => 'display: none;'
    ],
    'columns' => [
        [
            'header' => 'Дата завершения',
            'options' => [
                'style' => [
                    'width' => '11%'
                ]
            ], //Замена на метод в TZ в модели     
            'content' => function($m, $url) use($mail, $role, $user) {
                return $m->getDateEnd($user);
            }
        ],
        [
            'attribute' => 'host',
            'visible' => Yii::$app->request->get('user') ? True : FALSE,
            'options' => [
                'style' => [
                    'width' => '11%'
                ]
            ],
        ],
        [
            'attribute' => 'link',
            'format' => 'raw',
            'options' => [
                'style' => [
                    'width' => '11%'
                ]
            ],
            'footer' => 'Итого'
        ],
        [
            'attribute' => 'doc',
            'visible' => $user_identity['role'] == User::ROLE_PUBLISHER ? TRUE : FALSE,
            'options' => [
                'style' => [
                    'width' => '11%'
                ]
            ],
            'footer' => ' '
        ],
        [
            'attribute' => 'hostName',
            'header' => 'Хост',
            'visible' => $user_identity['role'] == (User::ROLE_AUTHOR ? FALSE : (User::ROLE_CORRECTOR ? FALSE : TRUE)),
            'options' => [
                'style' => [
                    'width' => '11%'
                ]
            ],
        ],
        //Перенести в модель tz
        [
            'header' => 'Кол.во. символов',
            'visible' => !Yii::$app->user->can(\common\models\User::ROLE_SEO),
            'options' => [
                'style' => [
                    'width' => '11%'
                ]
            ],
            'footer' => Tools::totalcount($user_identity, $mail),
            'content' => function($m, $url) use($user) {
                return $m->getCountTz($user);
            }
        ],
        [
            'header' => 'Сумма',
            'options' => [
                'style' => [
                    'width' => '11%'
                ]
            ],
            'footer' => Tools::totalprice($user_identity, $mail) . $user_identity['currency'],
            'content' => function($m, $url) use($rate_mail, $rate, $mail, $ratedoc, $user, $user_identity) {
                return $m->getPrice($user, $rate, $ratedoc) . $user_identity['currency'];
            }
        ]
    ],
])
?>
    <?php
\yii\widgets\Pjax::end();
?>
<?php
if ($user == User::ROLE_KM) {
    $time = Yii::$app->request->get('time');
    if ($time == 'last') {
        $startm = strtotime("first day of previous month"); // первый день прошлого месяца
        $endm = strtotime("last day of previous month") + 86399; // последний день прошлого месяца      
    } else {
        $startm = strtotime(date("Y-m-01"));
        $endm = strtotime(date("Y-m-t")) + 86399;
    }
    $result = Tz::find()->where(['>', 'status', '7'])->andWhere(['>', 'kmdate', $startm])->andWhere(['<', 'kmdate', $endm])->orderBy('kmdate')->all();
    $rez = 0;
    foreach ($result as $tz) {

        if ($tz->hostId == 'Без публикации') {

            $rez = $rez + Tools::units($tz->textArticle) / 2;
        } else {
            $rez = $rez + Tools::units($tz->textArticle);
        }
    }

    //$rez = 500001; 
    if ($rez < 500000) {
        $cash = $rez * $rate;
        echo $rez . ' * ' . $rate . ' = ' . $cash;
    } elseif ($rez > 500000 && $rez < 1500000) {
        $cash = (500000 * $rate ) + ((($rez - 500000) * $rate ) * 0.3);
        echo '(500 000' . ' * ' . $rate . ') + (' . number_format($rez - 500000, 0, '.', ' ') . ' * ' . $rate . ' * ' . '0.3)' . ' = ' . $cash;
    } elseif ($rez > 1500000 && $rez < 2500000) {
        $cash = (500000 * $rate ) + ((($rez - 500000) * $rate ) * 0.4);
        echo '(500 000' . ' * ' . $rate . ') + (' . number_format($rez - 500000, 0, '.', ' ') . ' * ' . $rate . ' * ' . '0.4)' . ' = ' . $cash;
    } elseif ($rez > 2500000) {
        $cash = (500000 * $rate ) + ((($rez - 500000) * $rate ) * 0.5);
        echo '(500 000' . ' * ' . $rate . ') + (' . number_format($rez - 500000, 0, '.', ' ') . ' * ' . $rate . ' * ' . '0.5)' . ' = ' . $cash;
    }
}
?>
