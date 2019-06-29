<?php

use common\widgets\Alert;
use frontend\models\Tz;
use \yii2mod\selectize\Selectize;
use common\models\User;
use yii\helpers\Html;
use dosamigos\tinymce\TinyMce;
use \common\components\dez\Tools;
use yii\bootstrap\Modal;

$this->title = 'Редактирование ТЗ';
$data = Yii::$app->request->get('id');
$tz = Tz::findOne($data);
if (!$tz) {
    throw new \yii\web\HttpException(404, 'The requested Item could not be found.');
}
?> 

<h1 style="display: inline-block">Редактирование</h1>    

<div id="stage" style="float: right;
     vertical-align: bottom;
     display: inline-block;
     margin-top: 29px;
     margin-bottom: 10px;" >
    <p style="display: inline-block">
        <?= ($tz->author ? 'Автор ' . User::getuserstoid($tz->author)[0]['byname'] : '') ?>
    </p>
    <p style="display: inline-block">
        <?= ($tz->corrector ? "&rArr;" . 'Корректор ' . User::getuserstoid($tz->corrector)[0]['byname'] : '') ?>
    </p>
    <p style="display: inline-block">
        <?= ($tz->publisher ? "&rArr;" . 'Публиковщик ' . User::getuserstoid($tz->publisher)[0]['byname'] : '') ?>
    </p>

    <p style="display: inline-block">
        (<?= $tz->stage ?>)
    </p>
</div>
<?php
?>
<div class="col-lg-12 form-group well">
    <?php
    $form = \yii\widgets\ActiveForm::begin([
                'id' => 'host-form-edit',
                'validateOnBlur' => false,
                'method' => 'post',
                'enableAjaxValidation' => true,
                'action' => ['/tz/tzedit', 'host' => Yii::$app->request->get('host', 0), 'id' => $data],
//        'id' => 'host-form',
//                'validateOnBlur' => false,
//                'options' => [
//                    'data-pjax' => false
//                ],
//                'action' => ['/tz/tzedit', 'host' => Yii::$app->request->get('host', 0), 'id' => $data]
    ]);
    ?>


    <?=
    //readonly
    $form->field($tz, 'title')->input('text', [
        'placeholder' => 'Заголовок',
        'value' => $tz->title /* . ($tz->urgently ? '(Срочно)' : '') */,
        'readonly' => !Yii::$app->user->identity->can([User::ROLE_SUPERADMIN], 'can', FALSE),
        'options' => [
            'maxlength' => 255
        ]
    ])
    ?>

    <?php if ($tz->hostId != 'Без публикации') { ?>
        <?php if (Yii::$app->user->identity->can([User::ROLE_KM, User::ROLE_SUPERADMIN, User::ROLE_EDITOR, User::ROLE_PUBLISHER], 'can', FALSE)) { ?>
            <?php
            setlocale(LC_ALL, "Russian_Russia.1251");
            if (in_array(Yii::$app->params['selectedHost'], array_keys(Yii::$app->params['wpDbs']))) {

                Yii::info($items = $tz->getTerms(Yii::$app->params['wpDbs'][Yii::$app->params['selectedHost']]));
                //natcasesort($items);
                if (Yii::$app->user->identity->can([User::ROLE_SUPERADMIN], 'can', FALSE)) {
                    echo $form->field($tz, 'category')->dropDownList($items);
                } else {
                    echo $form->field($tz, 'category')->dropDownList($items, ['disabled' => 'disabled']);
                }
            } else {
                echo $form->field($tz, 'category')->input('text', [
                    'placeholder' => 'Категория',
                    'readonly' => !Yii::$app->user->identity->can([User::ROLE_SUPERADMIN], 'can', FALSE),]);
            }
            ?>
        <?php } ?>
    <?php } ?>
    <?php if (Yii::$app->user->identity->can([User::ROLE_KM, User::ROLE_SUPERADMIN, User::ROLE_EDITOR, User::ROLE_AUTHOR, User::ROLE_PUBLISHER, User::ROLE_MASTER], 'can', FALSE) || (Yii::$app->user->identity->can([User::ROLE_CORRECTOR], 'can', FALSE) && Yii::$app->user->id == '60')) { ?>
        <?=
        $form->field($tz, 'text')->textarea(['placeholder' => 'Текст ТЗ',
            'readonly' => !Yii::$app->user->identity->can([User::ROLE_SUPERADMIN, User::ROLE_EDITOR], 'can', FALSE),
            'rows' => 15,])
        ?>
    <?php } ?> 
    <?php
    if ($tz->hostId != 'Без публикации') {
        Yii::info('hostId');
        Yii::info($tz->hostId);
        Yii::info(Yii::$app->params['wpDbs'][$tz->hostId]);
        ?>       
        <?php if (Yii::$app->user->identity->can([User::ROLE_SUPERADMIN], 'can', FALSE)) { ?>
            <?=
            $form->field($tz, 'strtegs')->widget(Selectize::className(), [
                'pluginOptions' => [
                    'valueField' => 'name',
                    'labelField' => 'name',
                    'searchField' => ['name'],
                    'options' => $tz->getAlltags(Yii::$app->params['wpDbs'][$tz->hostId], $tz->hostId),
                    // define list of plugins
                    'plugins' => ['remove_button'],
                    'persist' => false,
                    'createOnBlur' => true,
                    'create' => true,
                ]
            ]);
            ?>
        <?php } else { ?>
            <?php if (Yii::$app->user->identity->can([User::ROLE_KM, User::ROLE_PUBLISHER], 'can', FALSE)) { ?>
                <?=
                $form->field($tz, 'strtegs')->input('text', [
                    'placeholder' => 'Теги',
                    'readonly' => TRUE,]);
                ?>
            <?php } ?>
        <?php } ?>
    <?php } ?>

    <?php
    //echo trim(preg_replace('~\s+~s', '', str_replace(["&nbsp;"], '', (strip_tags($tz->textArticle)))));


    echo $form->field($tz, 'textArticle')->widget(TinyMce::className(), [
        'options' => ['rows' => 25],
        'language' => 'ru',
        'clientOptions' => [
            'plugins' => [
                "advlist autolink lists link charmap print preview anchor",
                "searchreplace visualblocks code fullscreen",
                "insertdatetime media table contextmenu powerpaste textcolor"
            ],
            'powerpaste_word_import' => 'clean',
            'powerpaste_allow_local_images' => false,
            'textcolor_map' => [
                "FFFF00", "Yellow",
            ],
            'toolbar' => "undo redo | styleselect | bold italic | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | link image | backcolor"
        ]
    ])->label('Текст статьи (' . Tools::units($tz->textArticle) . ')');
    ?>

    <?php if ($tz->hostId != 'Без публикации') { ?>
        <?php if (Yii::$app->user->identity->can([User::ROLE_SUPERADMIN, User::ROLE_KM, User::ROLE_PUBLISHER, User::ROLE_MASTER, User::ROLE_EDITOR], 'can', FALSE)) { ?>
            <?=
            //readonly

            $form->field($tz, 'url')->input('text', [
                'placeholder' => 'Ссылка на статью',
                'readonly' => !Yii::$app->user->identity->can([User::ROLE_SUPERADMIN, User::ROLE_KM, User::ROLE_PUBLISHER, User::ROLE_MASTER], 'can', FALSE) &&
                !($tz->needPublisher == 0 && Yii::$app->user->identity->can([User::ROLE_EDITOR], 'can', FALSE)),
                'options' => [
                    'maxlength' => 255
                ],
            ])
            ?>
        <?php } ?>
    <?php } ?>

    <?php if (Yii::$app->user->identity->can([User::ROLE_PUBLISHER], 'can', FALSE)) { ?>
        <?=
        //readonly

        $form->field($tz, 'doc')->input('text', [
            'placeholder' => 'Кол-во добавленных документов',
            'options' => [
                'maxlength' => 255
            ]
        ])
        ?>

    <?php } ?>
    <?php if (Yii::$app->user->identity->can([User::ROLE_AUTHOR], 'can', FALSE) && ($tz->status == Tz::statusNazAuthor)) { ?>
        <?=
        $form->field($tz, 'uniqueUrl') ->input('text', [
            'placeholder' => 'https://text.ru/antiplagiat/5d00c0ee7b19d',
            'value' => $tz->uniqueUrl,
            'options' => [
                'maxlength' => 255
            ],
        ])->hint('<a href="/openAccess.png" id="uniqueUrl" style="vertical-align: middle; color="red" target="_blank"> Обязательно откройте доступ к уникальности текста для всех </a>')
        ?>
    <?php } else if (Yii::$app->user->identity->can([User::ROLE_EDITOR, User::ROLE_SUPERADMIN], 'can', FALSE) && $tz->status == Tz::statusNapAuthor) { ?>
        <?=
        $form->field($tz, 'uniqueUrl');
        ?>
    <?php } else { ?>
        <?=
        $form->field($tz, 'uniqueUrl')->input('text', [
            'placeholder' => 'https://text.ru/antiplagiat/5d00c0ee7b19d',
//            'enableAjaxValidation' => false, 
            'value' => $tz->uniqueUrl,
            'disabled' => true,
            'options' => [
                'maxlength' => 255
            ]
        ])->label('Ссылка на уникальность ' . Html::a('<i class="fa fa-eye"></i>', $tz->uniqueUrl, [
            'target' => '_blank',
        ]))
        ?>
    <?php } ?>

    <?php if ($tz->hostId != 'Без публикации') { ?>
        <?php if (Yii::$app->user->identity->can([User::ROLE_SUPERADMIN, User::ROLE_KM, User::ROLE_EDITOR, User::ROLE_PUBLISHER], 'can', FALSE)) { ?>
            <?=
            $form->field($tz, 'KeysStringDecode', [
                'errorOptions' => ['encode' => false, 'class' => 'help-block']
            ])->textarea([
                'placeholder' => 'Ключ|Частота',
                'readonly' => !Yii::$app->user->identity->can([User::ROLE_SUPERADMIN], 'can', FALSE),
                'rows' => 8,
                'name' => \yii\helpers\Html::getInputName($tz, 'keysString'),
                'doubleEncode' => false
            ])->label('Ключи (' . $tz->totalFrequency . ')');
            ?>
        <?php } ?>
    <?php } ?>




    <?php ?>
    <?php if (Yii::$app->user->identity->can([User::ROLE_SUPERADMIN, User::ROLE_KM, User::ROLE_PUBLISHER], 'can', FALSE)) { ?>
        <?=
        $form->field($tz, 'comments')->textarea([
            'placeholder' => 'Текст комментария',
            'readonly' => !Yii::$app->user->identity->can([User::ROLE_SUPERADMIN], 'can', FALSE),
            'rows' => 3,
        ])
        ?>
    <?php } ?>


    <?php if (Yii::$app->user->identity->can([User::ROLE_SUPERADMIN, User::ROLE_EDITOR], 'can', FALSE)) { ?>
        <?php
//        if ($tz->status == 0) {
//            foreach (Yii::$app->params['users'] as $user => $role) {
//                if ($role['disabled'] != true) {
//                    if (($role['role'] == User::ROLE_AUTHOR) && in_array(Yii::$app->params['selectedHost'], $role['hosts'])) {
//                        $rez[$role['id']] = $role['byname'];
//                    }
//                }
//            }
//            //if ($tz->status != 0) {
//                echo $form->field($tz, 'author')
//                        ->dropDownList($rez, [
//                            'prompt' => 'Выберите автора'
//                ]);
//            //}
//            
//        }
//        switch ($tz->status) {
//            case 0:
//            case 1:
//                foreach (Yii::$app->params['users'] as $user => $role) {
//                    if ($role['disabled'] != true) {
//                        if (($role['role'] == User::ROLE_AUTHOR) && in_array(Yii::$app->params['selectedHost'], $role['hosts'])) {
//                            $rez[$role['id']] = $role['byname'];
//                        }
//                    }
//                }
//                if ($tz->status == 1) {
//                    echo $form->field($tz, 'author')
//                            ->dropDownList($rez, [
//                                'prompt' => 'Выберите автора'
//                    ]);
//                }
//            case 2:
//            case 3:
//                foreach (Yii::$app->params['users'] as $user => $role) {
//                    if ($role['disabled'] != true) {
//                        if (($role['role'] == User::ROLE_CORRECTOR) && in_array(Yii::$app->params['selectedHost'], $role['hosts'])) {
//                            $rez[$role['id']] = $role['byname'];
//                        }
//                    }
//                }
//                if ($tz->status == 3) {
//                    echo $form->field($tz, 'corrector')
//                            ->dropDownList($rez, [
//                                'prompt' => 'Выберите корректора'
//                    ]);
//                }
//            case 4:
//            case 5:
//                foreach (Yii::$app->params['users'] as $user => $role) {
//                    if ($role['disabled'] != true) {
//                        if (($role['role'] == User::ROLE_PUBLISHER)) {
//                            $rez[$role['id']] = $role['byname'] . '(' . User::getquantitywork($role['id'], 'publisher') . ')';
//                        }
//                    }
//                }
//                if ($tz->status == 5) {
//                    echo $form->field($tz, 'publisher')
//                            ->dropDownList($rez, [
//                                'prompt' => 'Выберите публиковщика'
//                    ]);
//                }
//        }

        if ($tz->status == 1) {
            foreach (Yii::$app->params['users'] as $user => $role) {
                if ($role['disabled'] != true) {
                    if (($role['role'] == User::ROLE_AUTHOR) && in_array(Yii::$app->params['selectedHost'], $role['hosts'])) {
                        $rez[$role['id']] = $role['byname'];
                    }
                }
            }
            if ($tz->status == 1) {
                echo $form->field($tz, 'author')
                        ->dropDownList($rez, [
                            'prompt' => 'Выберите автора'
                ]);
            }
        }
        if ($tz->status == 3) {
            foreach (Yii::$app->params['users'] as $user => $role) {
                if ($role['disabled'] != true) {
                    if (($role['role'] == User::ROLE_CORRECTOR) && in_array(Yii::$app->params['selectedHost'], $role['hosts'])) {
                        $rez[$role['id']] = $role['byname'];
                    }
                }
            }
            if ($tz->status == 3) {
                echo $form->field($tz, 'corrector')
                        ->dropDownList($rez, [
                            'prompt' => 'Выберите корректора'
                ]);
            }
        }
        if ($tz->status == 5) {
            foreach (Yii::$app->params['users'] as $user => $role) {
                if ($role['disabled'] != true) {
                    if (($role['role'] == User::ROLE_PUBLISHER) && in_array(Yii::$app->params['selectedHost'], $role['hosts'])) {
                        $rez[$role['id']] = $role['byname'] . '(' . User::getquantitywork($role['id'], 'publisher') . ')';
                    }
                }
            }
            if ($tz->status == 5 && $rez != NULL) {
                echo $form->field($tz, 'publisher')
                        ->dropDownList($rez, [
                            'prompt' => 'Выберите публиковщика'
                ]);
            }
        }
        ?>
        <?=
        $form->field($tz, 'urgently')->checkbox(['label' => 'Срочно', 'disabled' => !Yii::$app->user->identity->can([User::ROLE_SUPERADMIN, User::ROLE_EDITOR], 'can', FALSE)])
        ?>
        <?=
        $form->field($tz, 'expertTesting')->checkbox(['label' => 'Требуется проверка экспертом'])
        ?> 
    <?php } ?> 
    <?php if (Yii::$app->user->identity->can([User::ROLE_AUTHOR, User::ROLE_CORRECTOR, User::ROLE_PUBLISHER, User::ROLE_SEO, User::ROLE_MASTER, User::ROLE_EDITOR], 'can', FALSE) && $tz->returncom) { ?>
        <?=
        $form->field($tz, 'returncom')->textarea([
            'placeholder' => 'Пояснения к возврату ТЗ',
            'readonly' => TRUE,
            'rows' => 2,
            'required' => TRUE,
        ])
        ?>
    <?php } ?>
    <?php if (Yii::$app->user->identity->can([User::ROLE_SUPERADMIN, User::ROLE_EDITOR], 'can', FALSE) && ($tz->status >= Tz::statusEdit)) { ?>
        <?=
        $form->field($tz, 'editorcom')->textarea([
            'placeholder' => 'Пояснения к ТЗ',
            'readonly' => Yii::$app->user->identity->can([User::ROLE_SUPERADMIN], 'can', FALSE),
            'rows' => 2,
            'required' => TRUE,
        ])
        ?>
    <?php } ?>

    <?= Alert::widget() ?>

    <?php if (Yii::$app->user->identity->can([], 'cannot', FALSE)) { ?>
        <div class="form-inline"  style="display: inline; float: left; padding-right: 15px;">
            <?=
            \yii\helpers\Html::button('<i class="fa fa-floppy-o" style="vertical-align: middle"></i> Сохранить', [
                'class' => 'btn btn-success isModal',
                'id' => 'saveButton',
                'actionButton' => "/tz/tzedit?host=" . Yii::$app->request->get('host', 0) . "&id=" . $data,
            ])
            ?>
        </div>
    <?php } ?> 


    <div class="form-inline" style="display: inline;">
        <span  class="form-group col-lg-2 text-right inline-block" style= "width:auto;" > 
            <button type="button" id="gobackButton" class="btn btn-success orange"><i class="fa fa-caret-square-o-right" style="padding-right:4px;"></i>Вернуться назад</a>
        </span>
    </div>

    <?php if (Yii::$app->user->identity->can([User::ROLE_KM, User::ROLE_SUPERADMIN, User::ROLE_EDITOR], 'can', FALSE)) { ?>
        <?php if (!in_array($tz->status, [0, 1, 2, 3, 4, 5, 7, 8, 9]) || ($tz->status == Tz::statusReadyForDesign && $tz->needPublisher == 0) || ($tz->status == Tz::statusNapAuthor && $tz->needPublisher == 0 && $tz->needCorrector == 0)) { ?>
            <div class="form-inline" style="display: inline;">
                <span  class="form-group col-lg-2 text-right inline-block" style= "width:auto;" > 
                    <button type="button" id="sendCheckButton" actionButton="/tz/sendforreview?host=<?= Yii::$app->params['selectedHostId'] ?>&id=<?= $tz->id ?>"<?= in_array($tz->status, [0, 1, 2, 3, 4, 5, 7, 8, 9]) && (($tz->status == Tz::statusReadyForDesign && $tz->needPublisher == 1) || ($tz->status == Tz::statusNapAuthor && $tz->needPublisher == 1 && $tz->needCorrector == 1)) ? 'disabled' : '' ?> class="btn btn-success blue isModal"><i class="fa fa-check-circle-o" style="padding-right:4px;"></i>Отправить на проверку</button>

                </span>
            </div>
        <?php } ?>
        <?php if ($tz->status == Tz::statusReadyForDesign && $tz->hostId == 'Без публикации') { ?>  
            <div class="form-inline" style="display: inline;">
                <span  class="form-group col-lg-2 text-right inline-block" style= "width:auto;" > 
                    <button type="button" id="sendCheckButton" actionButton="/tz/sendforreview?host=<?= Yii::$app->params['selectedHostId'] ?>&id=<?= $tz->id ?>" class="btn btn-success blue isModal"><i class="fa fa-check-circle-o" style="padding-right:4px;"></i>Завершить</button>
                </span>
            </div>
        <?php } ?>
        <?php // if (in_array($tz->status, [0, 2, 4]) && (($tz->status == Tz::statusReadyForDesign || $tz->status == Tz::statusNapAuthor) && $tz->needPublisher == 1)) { ?>
        <?php if ($tz->status == Tz::statusNew || ($tz->status == Tz::statusNapAuthor && ($tz->needCorrector == 1 || $tz->needPublisher == 1)) || ($tz->status == Tz::statusReadyForDesign && $tz->needPublisher == 1)) { ?>
            <?php if (!($tz->status == Tz::statusReadyForDesign && $tz->hostId == 'Без публикации')) { ?> 
                <?php if (Yii::$app->user->identity->can([User::ROLE_EDITOR], 'can', FALSE) && !($tz->status == Tz::statusNew || $tz->status == Tz::statusNapAuthor || $tz->status == Tz::statusReadyForDesign)) { ?>
                    <div class="form-inline" style="display: inline;">
                        <span  class="form-group col-lg-2 text-right inline-block" style= "width:auto;" > 
                            <button type="button" id="sendCheckButton" actionButton="/tz/sendforreview?host=<?= Yii::$app->params['selectedHostId'] ?>&id=<?= $tz->id ?>"<?= in_array($tz->status, [0, 1, 2, 3, 5, 6, 7, 8, 9]) ? 'disabled' : '' ?> class="btn btn-success blue isModal"><i class="fa fa-check-circle-o" style="padding-right:4px;"></i>Отправить на проверку</button>
                        </span>
                    </div>
                <?php } else { ?>
                    <div class="form-inline" style="display: inline;"  >
                        <span  class="form-group col-lg-2 text-right inline-block" style= "width:auto;" > 
                            <button type="button" id="appointShow"  class="btn btn-success blue"><i class="fa fa-user-o" style="padding-right:4px;"></i>Назначить
                                <?php
                                switch ($tz->status) {
                                    case 0 :
                                        echo 'автора';
                                        break;
                                    case 2 :
                                        if ($tz->needCorrector) {
                                            echo 'корректировщика';
                                        } else {
                                            echo 'публиковщика';
                                        }
                                        break;
                                    case 4 :
                                        echo 'публиковщика';
                                        break;
                                }
                                ?>
                        </span>
                    </div>
                <?php } ?>
            <?php } ?>
        <?php } ?>
    <?php } else { ?>
        <div class="form-inline" style="display: inline;">
            <span  class="form-group col-lg-2 text-right inline-block" style= "width:auto;" >
                <button type="button" id="sendCheckButton" actionButton="/tz/sendforreview?host=<?= Yii::$app->params['selectedHostId'] ?>&id=<?= $tz->id ?>"<?= in_array($tz->status, [0, 2, 4, 6]) ? 'disabled' : '' ?> class="btn btn-success blue isModal"><i class="fa fa-check-circle-o" style="padding-right:4px;"></i><?= ((Yii::$app->user->identity->can([User::ROLE_SUPERADMIN], 'can', FALSE)) && $tz->status == Tz::statusVerification ? 'Завершить' : ((Yii::$app->user->identity->can([User::ROLE_EXPERT], 'can', FALSE)) || (Yii::$app->user->identity->can([User::ROLE_SUPERADMIN], 'can', FALSE) && $tz->status == Tz::statusExpertTesting) || (Yii::$app->user->identity->can([User::ROLE_EDITOR], 'can', FALSE) && $tz->status == Tz::statusExpertTesting) ? 'Отправить' : 'Отправить на проверку' ) ) ?></button> 
            </span> 
        </div>
    <?php } ?>

    <?php
    $form::end();
    ?>
    <?php if (Yii::$app->user->identity->can([User::ROLE_SUPERADMIN, User::ROLE_EDITOR], 'can', FALSE)) { ?>
        <div class="form-inline" style="display: inline;">
            <span  class="form-group col-lg-2 text-right inline-block" style= "width:auto;" > 
                <button type="button"  id="skipButton" <?= ($tz->status == 2 && ($tz->needPublisher == 1 || $tz->needCorrector == 1)) || ($tz->status == 4 && $tz->needPublisher == 1) ? 'style="display:inline"' : 'style="display:none"' ?> class="btn btn-success red" ><i class="fa fa-user-o" style="padding-right:4px;"></i>Пропустить 
                    <?php
                    switch ($tz->status) {
                        case 2 :
                            if ($tz->needCorrector == 1) {
                                echo 'корректировщика';
                            } else {
                                echo 'публиковщика';
                            }
                            break;
                        case 4 :
                            echo 'публиковщика';
                            break;
                    }
                    ?>
                </button>
            </span>
        </div>
    <?php } ?>

    <?php if (Yii::$app->user->identity->can([User::ROLE_KM, User::ROLE_SUPERADMIN, User::ROLE_EDITOR, User::ROLE_EXPERT], 'can', FALSE)) { ?> 
        <?php if (!in_array($tz->status, [Tz::statusNazAuthor, Tz::statusPublished, Tz::statusAdjustment, Tz::statusDesign, Tz::statusNew])) { //если это не данные статусы  ?>
            <?php if (Yii::$app->user->identity->can([User::ROLE_KM], 'can', FALSE)) { ?>
                <div class="form-inline" style="display: inline;">
                    <span  class="form-group col-lg-2 text-right inline-block" style= "width:auto;" > 
                        <button type="submit"  id="revisionHide" <?= in_array($tz->status, [0, 1, 3, 5, 7, 8, 9]) ? 'style="display:none"' : '' ?> class="btn btn-success "style="background-color: #279176;" ><i class="fa fa-times" style="padding-right:4px;"></i>Отправить на доработку</button>
                    </span>
                </div>
            <?php } else { ?>
                <div class="form-inline" style="display: inline;">
                    <span  class="form-group col-lg-2 text-right inline-block" style= "width:auto;" > 
                        <button type="submit"  id="revisionHide" <?= in_array($tz->status, [0, 1, 3, 5, 7, 9]) ? 'style="display:none"' : '' ?> class="btn btn-success "style="background-color: #279176;" ><i class="fa fa-times" style="padding-right:4px;"></i>Отправить на доработку</button>
                    </span> 
                </div>
            <?php } ?> 
        <?php } ?>

        <?php if (Yii::$app->user->identity->can([User::ROLE_SUPERADMIN], 'can', FALSE)) { ?>
            <?php if ($tz->status == Tz::statusVerification) { ?>
                <div class="form-inline" style="display: inline;">
                    <span  class="form-group col-lg-2 text-right inline-block" style= "width:auto; " > 
                        <button id="finishButton" actionButton="/tz/sendforreview?host=<?= Yii::$app->params['selectedHostId'] ?>&id=<?= $tz->id ?>" class="btn btn-success red isModal"><i class="fa fa-check" aria-hidden="true" style="padding-right: 4px;"></i>Завершить</a>
                    </span>
                </div>
            <?php } ?>
        <?php } ?> 

        <div class="form-revision" style="<?= ($tz->returncom ? 'display: none;' : '') ?>" >
            <?php
            $form2 = \yii\widgets\ActiveForm::begin([
                        'id' => 'host-form2',
                        'method' => 'post',
                        'enableAjaxValidation' => false,
                        'validateOnBlur' => false,
//                        'options' => [
//                            'data-pjax' => false
//                        ],
                        'action' => ['/tz/sendreturn', 'host' => Yii::$app->request->get('host', 0), 'id' => $tz->id],
            ]);
            ?>
            <?=
            $form2->field($tz, 'returncom')->textarea([
                'placeholder' => 'Пояснения к возврату ТЗ',
                'readonly' => !Yii::$app->user->identity->can([User::ROLE_KM, User::ROLE_SUPERADMIN, User::ROLE_EDITOR, User::ROLE_EXPERT], 'can', FALSE),
                'rows' => 2,
                'required' => TRUE,
            ])
            ?>
            <div class="form-inline"  style="display: inline; float: left; padding-right: 10px; ">
                <?=
                \yii\helpers\Html::button('<i class="fa fa-times" style="vertical-align: middle; padding-right: 10px;"></i>Отправить на доработку', [
                    'class' => 'btn btn-success isModalInt',
                    'id' => 'revisionButton',
                    'actionButton' => "/tz/sendreturn?host=" . Yii::$app->request->get('host', 0) . "&id=" . $tz->id,
                ])
                ?>
            </div>
            <?php /*
              <?php if (Yii::$app->user->identity->can([User::ROLE_SUPERADMIN, User::ROLE_EDITOR], 'can', FALSE) && $tz->status != Tz::statusExpertTesting && $tz->wayMaster == 0 && !(in_array($tz->status, [Tz::statusNapAuthor, Tz::statusReadyForDesign]))) { ?>
              <p style="margin-bottom: -6px;"><input name="addres" type="radio" value="km" required>Контент-менеджер</p>
              <p style="margin-bottom: -6px;"><input name="addres" type="radio" value="seo" required>Сеошник</p>
              <?php } ?>
             */ ?>
        </div>
        <?php
        $form2::end();
    }
    ?>



    <div class="form-inline" id="select" style="width: 100%; padding-top: 15px;" >
        <?php
        if (Yii::$app->user->identity->can([User::ROLE_SUPERADMIN, User::ROLE_EDITOR], 'can', FALSE)) {
            switch ($tz->status) {
                case 0:
                case 1:

                    if (!$tz->author) {
                        $rez['default'] = 'Назначить автора';
                    }
                    $param = ['options' => ['default' => ['Selected' => true]]];
                    foreach (Yii::$app->params['users'] as $user => $role) {
                        if ($role['disabled'] != true) {
                            if (($role['role'] == User::ROLE_AUTHOR) && in_array(Yii::$app->params['selectedHost'], $role['hosts'])) {
                                //$rez[$role['id']] = $role['byname'] . '(Автор)';
                                $rez[$role['id']] = $role['byname'] . '(' . User::getquantitywork($role['id'], 'author') . ')';
                            }
                        }
                    }
                    echo (Html::activeDropDownList($tz, 'author', $rez, $param));
                    break;

                case 2:
                case 3:
//                    if (!$tz->corrector) {
//                        $rez['default'] = 'Назначить корректора';
//                    }
//                    foreach (Yii::$app->params['users'] as $user => $role) {
//                        if ($role['disabled'] != true) {
//                            if ($role['role'] == User::ROLE_CORRECTOR) {
//                                $rez[$role['id']] = $role['byname'] . '(Корректор)';
//                            }
//                        }
//                    }
//                    echo Html::activeDropDownList($tz, 'corrector', $rez);
//                    break;


                    if (!$tz->corrector) {
                        $rez['default'] = 'Назначить корректора';
                    }
                    $needRole = User::ROLE_CORRECTOR;
                    $needRoleName = 'corrector';
                    if (!$tz->needCorrector) {
                        $needRole = User::ROLE_PUBLISHER;
                        $needRoleName = 'publisher';
                        $rez['default'] = 'Назначить публиковщика';
                        if (!$tz->needPublisher) {
                            break;
                        }
                    }
                    foreach (Yii::$app->params['users'] as $user => $role) {
                        if ($role['disabled'] != true) {
                            if ($role['role'] == $needRole && in_array(Yii::$app->params['selectedHost'], $role['hosts'])) {
                                $rez[$role['id']] = $role['byname'] . '(' . User::getquantitywork($role['id'], $needRoleName) . ')';
                            }
                        }
                    }
                    echo Html::activeDropDownList($tz, 'corrector', $rez);
                    break;
                case 4:
                case 5:
                    if ($tz->needPublisher) {
                        if (!$tz->publisher) {
                            $rez['default'] = 'Назначить публиковщика';
                        }
                        foreach (Yii::$app->params['users'] as $user => $role) {
                            if ($role['disabled'] != true) {
                                if ($role['role'] == User::ROLE_PUBLISHER && in_array(Yii::$app->params['selectedHost'], $role['hosts'])) {
                                    $rez[$role['id']] = $role['byname'] . '(' . User::getquantitywork($role['id'], 'publisher') . ')';
                                }
                            }
                        }
                        if ($rez != NULL) {
                            echo Html::activeDropDownList($tz, 'publisher', $rez);
                        }
                    }
                    break;

                case 6:
                case 7:
                    if (!$tz->seo) {
                        $rez['default'] = 'Назначить SEO';
                    }
                    foreach (Yii::$app->params['users'] as $user => $role) {
                        if ($role['disabled'] != true) {
                            if ($role['role'] == User::ROLE_SEO) {
                                $rez[$role['id']] = $role['byname'] . '(SEO)';
                            }
                        }
                    }
                    echo Html::activeDropDownList($tz, 'seo', $rez);
                    break;
            }
        }
        ?>
    </div>
</div>

