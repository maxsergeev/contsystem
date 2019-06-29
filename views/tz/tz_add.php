<?php

use common\widgets\Alert;
use jlorente\remainingcharacters\RemainingCharacters;
use app\models\ProductCategory;
use yii\helpers\Html;
use \yii2mod\selectize\Selectize;
use yii\widgets\ActiveForm;
use \vova07\imperavi\Widget;
use \kartik\file\FileInput;
use \yii\helpers\Url;
use common\models\User;

/* @var $this yii\web\View */
/* @var $dataProvider \yii\data\ArrayDataProvider */ 
/* @var $tz \frontend\models\tz */

$this->title = 'RUCAS Content';

$this->registerJs(<<<JS

// add tz form
    $('body').off('beforeSubmit').on('beforeSubmit', '#host-form', function(e) {
        if(!Number($('#hosts').data('val')) && !Number($('#hosts').attr('data-host_id'))){
            $('#hosts i').attr('class', 'fa fa-warning')
            .parents('a').addClass('text-danger');
            $('#host-form').addClass('has-error').find('.help-block').text('Необходимо выбрать сайт на панели!');
            return false;
        } else {
            $('#hosts i').attr('class', 'fa fa-check')
            .parents('a').removeClass('text-danger');
            $('#host-form').removeClass('has-error').find('.help-block').text('');
            $('button[type=submit] i').attr('class', 'fa fa-spinner fa-spin');
        }
    });
    
    $('body').off('afterValidate').on('afterValidate', '#host-form', function(e,a,m) {
        if(m && m.length > 0){
            $('button[type=submit] i').attr('class', 'fa fa-cloud-upload');
        }
    });
    
    $('body').on('beforeSubmit', '#host-form', function(e) {
        let _self = e.currentTarget;
        if($(_self).hasClass('has-error'))
            return false;
        $.ajax({
            url : $(_self).attr('action'),
            method : 'post',
            data : $(_self).serialize(),
            success : (d) => {
                if(!d.error)
                $.pjax.reload('#pjax', {
                    timeout: 8000
                }); 
                $('#container-add-tz').html(d.body || d);
            },
            error : (d) => {
                $('button[type=submit] i').attr('class', 'fa fa-cloud-upload');
                $(_self).addClass('has-error').find('.help-block').first().text(d.responseText.match(/with message +&#039;([^&]+)&#039;/i)[1]);
                console.log(d);
            }
        })
    });
    $('body').on('submit', '#host-form', (e) => {
        e.preventDefault();
        return false;
        }
    );
JS
);
?>

<div class="col-lg-12 form-group well">
    <?php
    $tz->wayMaster = 0;
    $tz->editorTesting=0;
    $tz->needCorrector = 1;
    if (Yii::$app->params['selectedHost'] == 'Без публикации') {
        $tz->needPublisher = 0;
    } else {
        $tz->needPublisher = 1;
    }
    $form = \yii\widgets\ActiveForm::begin([ 
                'id' => 'host-form',
                'validateOnBlur' => false,
                'options' => [
                    'data-pjax' => false
                ],
                'action' => ['/tz/tzadd', 'host' => Yii::$app->request->get('host', 0)]
    ]);
    ?>


    <?=
    $form->field($tz, 'title')->input('text', [
        'placeholder' => 'Заголовок',
        'options' => [
            'maxlength' => 255
        ]
    ])
    ?>

    <?php
    if (Yii::$app->params['selectedHost'] != 'Без публикации') {
        if (in_array(Yii::$app->params['selectedHost'], array_keys(Yii::$app->params['wpDbs']))) {
            Yii::info('das');
            Yii::info($items = $tz->getTerms(Yii::$app->params['wpDbs'][Yii::$app->params['selectedHost']]));
            //natcasesort($items); 
            echo $form->field($tz, 'category')->dropDownList($items);
        } else {
            echo $form->field($tz, 'category')->input('text', [
                'placeholder' => 'Категория',]);
        }
    }
    ?>

    <?=
    $form->field($tz, 'text')->textarea(['placeholder' => 'Текст ТЗ',
        'rows' => 15,])
    ?>
    <?php if (Yii::$app->params['selectedHost'] != 'Без публикации') { ?>
        <?=
        $form->field($tz, 'strtegs')->widget(Selectize::className(), [
            'pluginOptions' => [
                'valueField' => 'name',
                'labelField' => 'name',
                'searchField' => ['name'],
                'options' => $tz->getAlltags(Yii::$app->params['wpDbs'][Yii::$app->params['selectedHost']], Yii::$app->params['selectedHost']),
                // define list of plugins
                'plugins' => ['remove_button'],
                'persist' => false,
                'createOnBlur' => true,
                'create' => true
            ]
        ]);
        ?>
    <?php } ?>

    <?php if ($tz->hostId != 'Без публикации') { ?>
        <?php if (Yii::$app->user->identity->can([User::ROLE_SUPERADMIN, User::ROLE_KM, User::ROLE_PUBLISHER], 'can', FALSE)) { ?>
            <?=
            //readonly

            $form->field($tz, 'url')->input('text', [
                'placeholder' => 'Ссылка на статью',
                'readonly' => !Yii::$app->user->identity->can([User::ROLE_SUPERADMIN, User::ROLE_KM, User::ROLE_PUBLISHER], 'can', FALSE),
                'options' => [
                    'maxlength' => 255
                ]
            ])
            ?>
        <?php } ?>
    <?php } ?>


    <?php if (Yii::$app->params['selectedHost'] != 'Без публикации') { ?>
        <?=
        $form->field($tz, 'keysStringEncode', [
            'errorOptions' => ['encode' => false, 'class' => 'help-block']
        ])->textarea([
            'placeholder' => 'Ключ|Частота',
            'rows' => 8,
            'name' => \yii\helpers\Html::getInputName($tz, 'keysString'),
            'doubleEncode' => false
        ])
        ?>
    <?php } ?>
    <?=
    $form->field($tz, 'comments')->textarea([
        'placeholder' => 'Текст комментария',
        'rows' => 3,
    ])
    ?>
    <?=
            $form->field($tz, 'wayMaster')
            ->radioList([
                '0' => 'КМ',
                '1' => 'Мастер',
    ]);
    ?>
    <?php
    $rez = [];
    foreach (Yii::$app->params['users'] as $user => $role) {
        if ($role['disabled'] != true) {
            if (($role['role'] == User::ROLE_AUTHOR) && in_array(Yii::$app->params['selectedHost'], $role['hosts'])) {
                $rez[$role['id']] = $role['byname'] . ' (' . User::getquantitywork($role['id'], 'author') . ')';
            }
        }
    }
    echo $form->field($tz, 'author')
            ->dropDownList($rez, [
                'prompt' => 'Выберите автора'
    ]);
    ?>
    <?php
    $master = [];
    foreach (Yii::$app->params['users'] as $user => $role) {
        if ($role['disabled'] != true) {
            if (($role['role'] == User::ROLE_MASTER) && in_array(Yii::$app->params['selectedHost'], $role['hosts'])) {
                $master[$role['id']] = $role['byname'] . ' (' . User::getquantitywork($role['id'], 'master') . ')';
            }
        }
    }
    echo $form->field($tz, 'master')
            ->dropDownList($master, [
                'prompt' => 'Выберите Мастера',
    ]);
    ?> 

    <?=
    $form->field($tz, 'urgently')->checkbox(['label' => 'Срочно'])
    ?>
    <?=
    $form->field($tz, 'expertTesting')->checkbox(['label' => 'Требуется проверка экспертом'])
    ?>
    <?=
    $form->field($tz, 'needCorrector')->checkbox(['label' => 'Требуется корректировка'])
    ?>
    <?php if (Yii::$app->params['selectedHost'] != 'Без публикации') { ?>
    <?=
    $form->field($tz, 'needPublisher')->checkbox(['label' => 'Требуется оформление'])
    ?>
    <?php } ?>
    <?php if(Yii::$app->user->identity->role== User::ROLE_SUPERADMIN){ ?>
    <?=
    $form->field($tz, 'editorTesting')->checkbox(['label' => 'Требуется проверка редактором'])
    ?>
    <?php } ?>
    <?= Alert::widget() ?>
    <div class="form-inline">
        <?=
        \yii\helpers\Html::submitButton('<i class="fa fa-cloud-upload" style="vertical-align: middle"></i> Отправить', [
            'class' => 'btn btn-success'
        ])
        ?>

    </div>
    <?php
    $form::end(); 
    ?>
</div>

