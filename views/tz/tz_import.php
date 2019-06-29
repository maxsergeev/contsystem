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
use frontend\models\YapiHost;
use common\models\User;

/* @var $this yii\web\View */
/* @var $dataProvider \yii\data\ArrayDataProvider */
/* @var $tz \frontend\models\tz */

$this->title = 'Добавление статьи';

$this->registerJs("


"
);
?>

<div class="col-lg-12 import form-group well ready">
    <div class="drop_import_tz"><?= str_replace('.txt', '', $import) ?></div>
    <?php
    $tz->wayMaster = 0;
    $tz->editorTesting = 0;
    $tz->needCorrector = 1;
    if (Yii::$app->params['selectedHost'] == 'Без публикации') {
        $tz->needPublisher = 0;
    } else {
        $tz->needPublisher = 1;
    }
    $form = \yii\widgets\ActiveForm::begin([
                'id' => 'host-form' . $index,
                'options' => [
                    'data-pjax' => TRUE,
                ],
                'action' => ['/tz/tzadd', 'host' => Yii::$app->request->get('host', 0)]
    ]);
    ?>


    <?=
    $form->field($tz, 'title')->input('text', [
        'placeholder' => 'Заголовок',
        'value' => str_replace(".txt", "", $import),
        'options' => [
            'maxlength' => 255
        ]
    ])
    ?>
    <?php if (Yii::$app->params['selectedHost'] != 'Без публикации') { ?>
        <?php
        if (in_array(Yii::$app->params['selectedHost'], array_keys(Yii::$app->params['wpDbs']))) {
            Yii::info($items = $tz->getTerms(Yii::$app->params['wpDbs'][Yii::$app->params['selectedHost']]));

            echo $form->field($tz, 'category')->dropDownList($items);
        } else {
            echo $form->field($tz, 'category')->input('text', [
                'placeholder' => 'Категория',]);
        }
        ?>
    <?php } ?>
    <?php if (Yii::$app->params['selectedHost'] == 'Все сайты') { ?>
        <?=
        $form->field($tz, 'hostId')->dropDownList(Yii::$app->user->identity->hosts); //Возможны только админы
        ?>
    <?php } ?>
    <?=
    $form->field($tz, 'text')->textarea(['placeholder' => 'Текст ТЗ',
        'rows' => 15,
        'value' => $text,])
    ?>
    <?php if (Yii::$app->params['selectedHost'] != 'Без публикации') { ?>
        <?=
        $form->field($tz, 'strtegs', ['inputOptions' => ['id' => "tz-strtegs{$index}"]])->widget(Selectize::className(), [
            'pluginOptions' => [
                'valueField' => 'name',
                'labelField' => 'name',
                'searchField' => ['name'],
                'options' => $tz->getAlltags(Yii::$app->params['wpDbs'][Yii::$app->params['selectedHost']], Yii::$app->params['selectedHost']),
                // define list of plugins
                'plugins' => ['remove_button'],
                'persist' => false,
                'createOnBlur' => true,
                'create' => true,
            ],
            'options' => ['id' => "tz-strtegs{$index}"]
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

    <?=
    /* $form->field($tz, 'strtegs')->input('text', [
      'placeholder' => 'тег,тег,тег',
      ]) */''
    ?>
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
            $form->field($tz, 'wayMaster')
            ->radioList([
                '0' => 'КМ',
                '1' => 'Мастер',
    ]);
    ?>
    <?php
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
    <div class="form-inline" >

    </div>
    <?php
    $form::end(); 
    ?>
</div>


