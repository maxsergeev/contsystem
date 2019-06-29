<?php
/* @var $this yii\web\View */

use common\models\User;
use frontend\models\Tz;
use yii\helpers\Html;

/* @var $dataProvider \yii\data\ArrayDataProvider */
/* @var $searchModel frontend\models\tzearch */

$this->title = 'Yandex API';
$this->registerCss(<<<CSS
    .popover {
        max-width: 100%;
    }
    .inline-block{
        display: inline-block; 
    }
CSS
);
if (!Yii::$app->user->isGuest) {
    if (Yii::$app->user->identity->can([User::ROLE_SUPERADMIN, User::ROLE_EDITOR], 'can', False)) {
        $this->registerJs(<<<JS
    $(document).ready(function () {
    $("input#uploadform-textfile").change(function () { 
        var options = {
            target: 'div.col-lg-v',
            success: function () {
                $('span#container-import-tz > a > i').css('display', 'inline-block')
                $('span#container-import-tz > a > div').css('display', 'none')
                $('div#fancy_overlay').css('display', 'none');
                $('div.marker').css('display', 'none');
                if ($('div.import > form').length==1) {
                    $('#host-form1').css('display', 'block');
                }
            },

        };
        $('form.upload-form').ajaxSubmit(options);
    });
    var dropZone = $('body'),
            maxFileSize = 100000000; // максимальный размер файла - 100 мб.
    if (typeof (window.FileReader) == 'undefined') {
        dropZone.text('Не поддерживается браузером!');
        dropZone.addClass('error');
    } 
    
    if ($('span.host-label').html().trim() !== 'Все сайты'){
    dropZone[0].ondragover = function () {
        dropZone.addClass('hover');
        $('html').css('cursor', 'no-drop');     
        $('div#fancy_overlay').css('display', 'block');
        $('div.marker').css('display', 'block');
        return false;
        };
    };

    dropZone[0].ondragleave = function () {
        dropZone.removeClass('hover');
        return false;
    };
    dropZone[0].ondrop = function (event) {
        $('img#loader-import').css('display', 'block');
        event.preventDefault();
        var file = event.dataTransfer.files;
        $("input#uploadform-textfile").prop('files', file).change();
    };
    function stateChange(event) {
        if (event.target.readyState == 4) {
            if (event.target.status == 200) {

                dropZone.text('Загрузка успешно завершена!');
            } else {
                dropZone.text('Произошла ошибка!');
                dropZone.addClass('error');
            }
        }
    }


});
    
JS
        );
    }
}
if (Yii::$app->user->can(\common\models\User::ROLE_SUPERADMIN) || true) {
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
    $('body').on('change', 'div.toggle input', ajax);
    new Clipboard('a[href="#keys"]', {
        text: function(trigger) {
            return $($(trigger.getAttribute('data-content'))[1]).text().trim(/(^\R*)|(\R*)$/);
        }
    });
    new Clipboard('a.a-title', {
        text: function(trigger) {
            return trigger.getAttribute('data-url');
        }
    });
    new Clipboard('.btn-tz button#btn-copy-urls', {
        text: function(trigger) {
            return $(trigger).data('urls');
            
        }
    });
    $('a.a-title').click((e) => e.preventDefault());
    $('body').on('click', 'a[href="#keys"]', (e) => {
        $.notify({
                message : 'Значение скопировано в буфер обмена',
            }, {
                type : 'success',
                placement: {
                    from: "bottom",
                    align: "right"
                } 
        });
        return false;
    });
    $('body').on('mousedown keydown', '.btn-tz button', function(e)  {
        let _self = this;
        let rows = $('#grid-tz').yiiGridView('getSelectedRows');
        rows = $('#grid-tz').find('tr').filter((k,e) => {return rows.indexOf($(e).data('key')) >= 0});
        switch ($(_self).attr('id').toLowerCase()){
            case 'btn-copy-urls' :
                if($(rows).length > 0){
                    dataurl = '';
                    $('input[type="checkbox"][name="selection[]"]').each(function () {                    
                     if ($(this).prop("checked") && $(this).parent().parent().find('td > a.a-title').attr('data-url')){
                       dataurl= dataurl +  $(this).parent().parent().find('td > a.a-title').attr('data-url') + '\\n';
                    }
                    });
                    $(_self).data('urls', dataurl);
                    if($('input[type="checkbox"][name="selection[]"]:checked').length!=$('i.fa-external-link').length){
                    $.notify({
                        message : 'Не во всех выбранных ТЗ есть URL',
                    }, {
                        type : 'warning',
                        placement: {
                            from: "bottom",
                            align: "right"
                        } 
                    });
                         }
                    else{
                    $.notify({
                        message : 'Url скопированы в буфер обмена',
                    }, {
                        type : 'success',
                        placement: {
                            from: "bottom",
                            align: "right"
                        } 
                    });
            }
                }
                break;
            case 'btn-add-bunch':
                let _id = $('#grid-tz').yiiGridView('getSelectedRows').map((e) => 'id[]=' + e).join('&');
                $.ajax({
                    url : '/tz/tz-ajax',
                    type : 'post',
                    data : 'action=addBunch&st=true&' + _id,
                    success : (d) => {
                        if(!d.error){
                            $(rows).each((k,e) => {
                                    $('body').off('change', 'div.toggle input', ajax);
                                    $(e).removeClass('warning').addClass('success').find('div.toggle input').bootstrapToggle('on');
                                    $('body').on('change', 'div.toggle input', ajax);
                                }
                            );
                            // $.pjax.reload('#pjax');
                        } else {
                            console.log(d.errors);
                        }
                    },
                    error : (d) => {
                        console.log(d);
                    }
                });
                break;
            case 'btn-add-all':
                $('#grid-tz').find('tr.warning input[name="selection[]"]').prop('checked', 'checked');
                $('#grid-tz').find('input[name="selection[]"]').trigger('change');
                break;
        }
    });
    $('body').on('change', '#grid-tz input[name="selection[]"]', (e) => {
        $('#check-counter span').text($('#grid-tz').find('input[name="selection[]"]').filter(
            (k,e) => {
                return $(e).prop('checked');
            }).length);
        if($('input[type="checkbox"][name="selection[]"]:checked').length){
            $('#btn-copy-urls, #btn-add-bunch ').prop('disabled', false);
            $('select#multi_select').removeAttr('disabled');
             $('span#container-slide-tz > a').removeAttr('disabled');
             $('span#container-export-tz > a').removeAttr('disabled');
            
           
        } else {
            $('#btn-copy-urls, #btn-add-bunch').prop('disabled', true);
            $('select#multi_select').attr('disabled', true);
            $('span#container-slide-tz > a').attr('disabled', 'disabled');
            $('span#container-export-tz > a').attr('disabled', 'disabled');
        }
    });
    $('body').on('change' , '.select-on-check-all', () => {
        $('#grid-tz').find('input[name="selection[]"]').trigger('change');
    });
    $('#add-tz-button').click((e) => {
        let _self = e.currentTarget;
        e.preventDefault();
        $(_self).find('i').attr('class', 'fa fa-spin fa-spinner');
        if($(_self).hasClass('btn-success')){
            $('#container-add-tz').animate({
                height: 'show'
            }, 200);
            $(_self).removeClass('btn-success').addClass('btn-danger').html('<i class="fa fa-minus"></i> Скрыть'); 
        } else {
            $('#container-add-tz').animate({
                height: 'toggle'
            }, 200);
            $(_self).removeClass('btn-danger').addClass('btn-success').html('<i class="fa fa-plus"></i> Добавить');
        }
    });
    $('body').on('input', '.form-filter', (e) => {
        let _self = e.currentTarget;
        if(!$(_self).val())
            $(_self).trigger(jQuery.Event('keyup', {key: 'Enter'}));
    });
    // Поиск, фильтр по дате
    $('body').on('apply.daterangepicker keyup', '.form-filter', (e) => {
        var uri = window.location.toString();
            if (uri.indexOf("&") > 0) {
                var clean_uri = uri.substring(0, uri.indexOf("&"));
                window.history.replaceState({}, document.title, clean_uri);
                
            }
            
        
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
     $('body').on('change', "div#searchworks > select", function () {
      var str = location.search.replace( /&?TzSearch\[worker\]=\w+/,'' );
      history.pushState('','',str+'&TzSearch[worker]='+$(this).val());
      $.pjax.reload('#pjax');
     });
    // $('#test').bootstrapToggle().bootstrapToggle();
    
    
JS
    );
} else {
    $this->registerJs(<<<JS
    new Clipboard('a.a-title', {
        text: function(trigger) {
            return trigger.getAttribute('data-url');
        }
    });
    $('#add-tz-button').click((e) => {
        let _self = e.currentTarget;
        e.preventDefault();
        $(_self).find('i').attr('class', 'fa fa-spin fa-spinner');
        if($(_self).hasClass('btn-success')){
            $('#container-add-tz').animate({
                height: 'show'
            }, 200);
            $(_self).removeClass('btn-success').addClass('btn-danger').html('<i class="fa fa-minus"></i> Скрыть форму');
        } else {
            $('#container-add-tz').animate({
                height: 'toggle'
            }, 200);
            $(_self).removeClass('btn-danger').addClass('btn-success').html('<i class="fa fa-plus"></i> Добавить');
        }
    });
    // Поиск, фильтр по дате
    $('body').on('blur keyup', '.form-filter', (e) => {
        let _self = e.currentTarget;
        if((e.type == 'keyup' && e.key == 'Enter') || e.type == 'apply'){
            if(decodeURIComponent(location.search).indexOf($(_self).attr('name')) > 0){
                let search = decodeURIComponent(location.search).replace(new RegExp($(_self).attr('name').replace('[', '\\[').replace(']', '\\]') + '=[^&]*', 'g'),$(_self).attr('name') + '=' + $(_self).val());
                history.pushState('','',location.pathname + search);
            } else {
                history.pushState('', '', location.pathname + (location.search ? location.search + '&' : '?') + $(_self).attr('name') + '=' + $(_self).val());
            }
            $.pjax.reload('#pjax');
        }
    });
    
JS
    );
}
?>
<div class="row">
    <div class="col-lg-10" style='padding-bottom: 15px;<?= Yii::$app->user->identity->can([User::ROLE_SUPERADMIN, User::ROLE_EDITOR], 'can', FALSE) ? "width:100%;" : "width:100%;" ?>'>
        <?=
        Html::input('text', Html::getInputName($searchModel, 'title'), $searchModel->title, [
            'class' => 'form-control form-filter',
            'id' => 'formReset',
            'placeholder' => 'Введите главный ключ или ссылку на статью для поиска'
        ]);
        ?>
        <i class="fa fa-times" id="clickReset" aria-hidden="true"></i>
    </div>
    <?php if (Yii::$app->params['selectedHost'] != 'Все сайты') { ?>
        <?php if (Yii::$app->user->identity->can([User::ROLE_SUPERADMIN, User::ROLE_EDITOR], 'can', FALSE)) { ?>
            <span class="form-group col-lg-2 text-right inline-block" style="width:11%;">
                <?=
                \yii\helpers\Html::a('<i class="fa fa-plus" style="vertical-align: middle"></i> Добавить', ['tz/tzadd', 'host' => Yii::$app->request->get('host', 0)], [
                    'class' => 'btn btn-success',
                    'id' => 'add-tz-button'
                ])
                ?>

            </span>
        <?php } ?>
        <?php if (Yii::$app->user->identity->can([User::ROLE_SUPERADMIN, User::ROLE_EDITOR], 'can', FALSE)) { ?>
            <span id="container-import-tz" class="form-group col-lg-2 text-right inline-block" style="width:11%;">
                <a class="btn btn-success blue"><i class="fa fa-caret-square-o-left" style="padding-right:4px;"></i> <div class="fa" id="preloader_import"><img src="js/gif-load.gif"> </div><span>Импорт</span></a>

            </span>
        <?php } ?>
    <?php } ?>
    <?php /*    <span class="col-lg-12 " id="send_mail" >
      <form id="form_send_tz" action="tz/sendtz" method="post" autocomplete="on">
      <input name="email" class="input_mail col-lg-9" style="width: 87%;" type="email" required placeholder="Введите email:">
      <span id="container-send-tz" class="form-group col-lg-2 text-right inline-block" style="width:11%;">
      <a class="btn btn-success red"><i class="fa fa-paper-plane-o" style="padding-right:4px;"></i> <div class="fa" id="preloader_import_red"><img src="js/gif-loadred.gif"> </div><span>Отправить ТЗ</span></a>
      </span>
      </form>
      </span> */ ?>



    <span class="form-group col-lg-2 text-right inline-block" style="width:2%; display: none;">         
        <?php
        echo $this->render('tz_load', ['model' => new \frontend\models\UploadForm()]);
        ?>
    </span>
    <div class="col-lg-12" id="container-add-tz" style="display: none;">
        <?php
        echo $this->render('tz_add', ['tz' => new \frontend\models\tz()]);
        ?>

    </div>
    <div class="col-lg-v" ></div>

    <?php
    \yii\widgets\Pjax::begin([
        'options' => [
            'id' => 'pjax'
        ],
        'timeout' => 8000
    ]);
    ?>


    <?php if (Yii::$app->user->can(\common\models\User::ROLE_SUPERADMIN, User::ROLE_EDITOR)): ?>
        <div class="col-lg-2 btn-group btn-tz">
            <?=
            Html::button(
                    '<i class="fa fa-link"></i> Скопировать URL', [
                'id' => 'btn-copy-urls',
                'class' => 'btn btn-primary btn-sm',
                'disabled' => true
                    ]
            )
            ?>
            <?php
//            Html::button(
//                    '<i class="fa fa-list"></i> Выделить незавершенные', [
//                'id' => 'btn-add-all',
//                'class' => 'btn btn-warning btn-sm'
//                    ]
//            )
            ?>
        </div>  
    <?php endif; ?>  
    <?php if (Yii::$app->user->identity->can([User::ROLE_KM, User::ROLE_SUPERADMIN, User::ROLE_EDITOR], 'can', FALSE)) { ?>
        <div class="col-lg-3" id="searchworks" style="padding-bottom: 15px;" > 

            <?php
            if (Yii::$app->request->get('type') == 'author') {
                echo(Html::activeDropDownList($searchModel, 'worker', User::getallworkerforselect('author'), array('prompt' => 'Фильтр по авторам')));
            } elseif (Yii::$app->request->get('type') == 'corrector') {
                echo(Html::activeDropDownList($searchModel, 'worker', User::getallworkerforselect('corrector'), array('prompt' => 'Фильтр по корректорам')));
            } elseif (Yii::$app->request->get('type') == 'publisher') {
                echo(Html::activeDropDownList($searchModel, 'worker', User::getallworkerforselect('publisher'), array('prompt' => 'Фильтр по публиковщикам')));
            } else {
                echo(Html::activeDropDownList($searchModel, 'worker', User::getallworkerforselect(''), array('prompt' => 'Фильтр по исполнителю')));
            }
            ?>

        </div>
    <?php } ?>


    <?php if (!Yii::$app->user->identity->can([User::ROLE_PUBLISHER], 'can', FALSE)) { ?> 
        <div class="<?= Yii::$app->user->identity->can([User::ROLE_SUPERADMIN, User::ROLE_EDITOR, User::ROLE_KM], 'can', FALSE) ? 'col-lg-9' : 'col-lg-12' ?> form-group" style=<?= Yii::$app->user->identity->can([User::ROLE_SUPERADMIN, User::ROLE_EDITOR], 'can', FALSE) ? ( Yii::$app->params['selectedHost'] == 'Все сайты' ? "float:right;width:58%;" : "float:right;width:36%;" ) : ''; ?> >
        <?php } else { ?>
            <div class="<?= Yii::$app->user->identity->can([User::ROLE_PUBLISHER], 'can', FALSE) ? 'col-lg-12' : 'col-lg-9' ?> form-group"  >    
            <?php } ?>
            <?=
            \kartik\daterange\DateRangePicker::widget([
                'name' => Html::getInputName($searchModel, 'dateCreate'),
                'convertFormat' => true,
                'value' => $searchModel->dateCreate,
                'options' => [
                    'placeholder' => 'Фильтр по дате',
                    'class' => 'form-control form-filter'
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
                    'opens' => 'left'
                        ], ($searchModel->dateCreate ? [
                            'startDate' => explode('-', ($searchModel->dateCreate))[0],
                            'endDate' => explode('-', ($searchModel->dateCreate))[1]
                                ] : []))
            ])
            ?>
        </div>

        <?php if (!Yii::$app->user->identity->can([User::ROLE_PUBLISHER], 'can', FALSE)) { ?>   
            <div class="col-lg-2" id="sortdate" style="padding-bottom: 15px; display: none;" >
                <?=
                Html::activeDropDownList($searchModel, 'worker', ['0' => 'Дата создания', '1' => 'Дата изменения'], array('prompt' => 'Cортировкa'));
                ?>
            </div>
        <?php } ?>
        <!--//Вкладки для КМ-->   
        <?php
        switch (Yii::$app->user->identity->role) {

            case User::ROLE_KM :
                ?>   
                <div class="col-lg-12 cartbar" id="cartbartab">
                    <ul class="nav nav-tabs" style="margin-bottom: 12px;">
                        <li <?= (Yii::$app->request->get('type') == 'new' ? "class='active'" : "") ?>><a href="<?= '?host=' . Yii::$app->params['selectedHostId'] . '&type=new' ?>">Новые (<?= $searchModel->search(Yii::$app->request->get(), 'new')->getTotalCount() ?>)</a></li>
                        <li <?= (Yii::$app->request->get('type') == 'author' ? "class='active'" : "") ?>><a href="<?= '?host=' . Yii::$app->params['selectedHostId'] . '&type=author' ?>">У автора (<?= $searchModel->search(Yii::$app->request->get(), 'author')->getTotalCount() ?>)</a></li>
                        <li <?= (Yii::$app->request->get('type') == 'expert' ? "class='active'" : "") ?>><a href="<?= '?host=' . Yii::$app->params['selectedHostId'] . '&type=expert' ?>">У эксперта (<?= $searchModel->search(Yii::$app->request->get(), 'expert')->getTotalCount() ?>)</a></li>
                        <li <?= (Yii::$app->request->get('type') == 'corrector' ? "class='active'" : "") ?>><a href="<?= '?host=' . Yii::$app->params['selectedHostId'] . '&type=corrector' ?>">У корректора (<?= $searchModel->search(Yii::$app->request->get(), 'corrector')->getTotalCount() ?>)</a></li>
                        <li <?= (Yii::$app->request->get('type') == 'publisher' ? "class='active'" : "") ?>><a href="<?= '?host=' . Yii::$app->params['selectedHostId'] . '&type=publisher' ?>">У публиковщика (<?= $searchModel->search(Yii::$app->request->get(), 'publisher')->getTotalCount() ?>)</a></li>
                        <li <?= (Yii::$app->request->get('type') == 'work' ? "class='active'" : "") ?>><a href="<?= '?host=' . Yii::$app->params['selectedHostId'] . '&type=work' ?>">Нужно проверить (<?= $searchModel->search(Yii::$app->request->get(), 'work')->getTotalCount() ?>)</a></li>
                        <li <?= (Yii::$app->request->get('type') == 'verification' ? "class='active'" : "") ?>><a href="<?= '?host=' . Yii::$app->params['selectedHostId'] . '&type=verification' ?>">На утверждении (<?= $searchModel->search(Yii::$app->request->get(), 'verification')->getTotalCount() ?>)</a></li>
                        <li <?= (Yii::$app->request->get('type') == 'complite' ? "class='active'" : "") ?>><a href="<?= '?host=' . Yii::$app->params['selectedHostId'] . '&type=complite' ?>">Завершено (<?= $searchModel->search(Yii::$app->request->get(), 'complite')->getTotalCount() ?>)</a></li>
                        <li <?= (Yii::$app->request->get('type') == '' ? "class='active'" : "") ?>><a href="<?= '?host=' . Yii::$app->params['selectedHostId'] ?>">Все (<?= $searchModel->search(Yii::$app->request->get(), '')->getTotalCount() ?>)</a></li>
                    </ul>
                </div>

                <?php
                break;
            case User::ROLE_EDITOR :
                ?>
                <div class="col-lg-12 cartbar">
                    <ul class="nav nav-tabs" style="margin-bottom: 12px;">
                        <li <?= (Yii::$app->request->get('type') == 'new' ? "class='active'" : "") ?>><a href="<?= '?host=' . Yii::$app->params['selectedHostId'] . '&type=new' ?>">Новые (<?= $searchModel->search(Yii::$app->request->get(), 'new')->getTotalCount() ?>)</a></li>
                        <li <?= (Yii::$app->request->get('type') == 'author' ? "class='active'" : "") ?>><a href="<?= '?host=' . Yii::$app->params['selectedHostId'] . '&type=author' ?>">У автора (<?= $searchModel->search(Yii::$app->request->get(), 'author')->getTotalCount() ?>)</a></li>
                        <li <?= (Yii::$app->request->get('type') == 'expert' ? "class='active'" : "") ?>><a href="<?= '?host=' . Yii::$app->params['selectedHostId'] . '&type=expert' ?>">У эксперта (<?= $searchModel->search(Yii::$app->request->get(), 'expert')->getTotalCount() ?>)</a></li>
                        <li <?= (Yii::$app->request->get('type') == 'corrector' ? "class='active'" : "") ?>><a href="<?= '?host=' . Yii::$app->params['selectedHostId'] . '&type=corrector' ?>">У корректора (<?= $searchModel->search(Yii::$app->request->get(), 'corrector')->getTotalCount() ?>)</a></li>
                        <li <?= (Yii::$app->request->get('type') == 'publisher' ? "class='active'" : "") ?>><a href="<?= '?host=' . Yii::$app->params['selectedHostId'] . '&type=publisher' ?>">У публиковщика (<?= $searchModel->search(Yii::$app->request->get(), 'publisher')->getTotalCount() ?>)</a></li>
                        <li <?= (Yii::$app->request->get('type') == 'checkkm' ? "class='active'" : "") ?>><a href="<?= '?host=' . Yii::$app->params['selectedHostId'] . '&type=checkkm' ?>">На проверке у КМ (<?= $searchModel->search(Yii::$app->request->get(), 'checkkm')->getTotalCount() ?>)</a></li>
                        <li <?= (Yii::$app->request->get('type') == 'master' ? "class='active'" : "") ?>><a href="<?= '?host=' . Yii::$app->params['selectedHostId'] . '&type=master' ?>">У Мастера (<?= $searchModel->search(Yii::$app->request->get(), 'master')->getTotalCount() ?>)</a></li>

                        <li <?= (Yii::$app->request->get('type') == 'work' ? "class='active'" : "") ?>><a href="<?= '?host=' . Yii::$app->params['selectedHostId'] . '&type=work' ?>">Нужно проверить (<?= $searchModel->search(Yii::$app->request->get(), 'work')->getTotalCount() ?>)</a></li>
                        <li <?= (Yii::$app->request->get('type') == 'verification' ? "class='active'" : "") ?>><a href="<?= '?host=' . Yii::$app->params['selectedHostId'] . '&type=verification' ?>">На утверждении (<?= $searchModel->search(Yii::$app->request->get(), 'verification')->getTotalCount() ?>)</a></li>
                        <li <?= (Yii::$app->request->get('type') == 'complite' ? "class='active'" : "") ?>><a href="<?= '?host=' . Yii::$app->params['selectedHostId'] . '&type=complite' ?>">Завершено (<?= $searchModel->search(Yii::$app->request->get(), 'complite')->getTotalCount() ?>)</a></li>
                        <li <?= (Yii::$app->request->get('type') == '' ? "class='active'" : "") ?>><a href="<?= '?host=' . Yii::$app->params['selectedHostId'] ?>">Все (<?= $searchModel->search(Yii::$app->request->get(), '')->getTotalCount() ?>)</a></li>
                    </ul>
                </div>
                <?php
                break;
            case User::ROLE_SUPERADMIN :
                ?>
                <div class="col-lg-12 cartbar" id="tab-tz">
                    <ul class="nav nav-tabs" style="margin-bottom: 12px;">
                        <li <?= (Yii::$app->request->get('type') == 'new' ? "class='active'" : "") ?>><a href="<?= '?host=' . Yii::$app->params['selectedHostId'] . '&type=new' ?>">Новые <span>(<?= $searchModel->search(Yii::$app->request->get(), 'new')->getTotalCount() ?>)</span></a></li>
                        <li <?= (Yii::$app->request->get('type') == 'author' ? "class='active'" : "") ?>><a href="<?= '?host=' . Yii::$app->params['selectedHostId'] . '&type=author' ?>">У автора <span>(<?= $searchModel->search(Yii::$app->request->get(), 'author')->getTotalCount() ?>)</span></a></li>
                        <li <?= (Yii::$app->request->get('type') == 'expert' ? "class='active'" : "") ?>><a href="<?= '?host=' . Yii::$app->params['selectedHostId'] . '&type=expert' ?>">У эксперта <span>(<?= $searchModel->search(Yii::$app->request->get(), 'expert')->getTotalCount() ?>)</span></a></li>
                        <li <?= (Yii::$app->request->get('type') == 'corrector' ? "class='active'" : "") ?>><a href="<?= '?host=' . Yii::$app->params['selectedHostId'] . '&type=corrector' ?>">У корректора <span>(<?= $searchModel->search(Yii::$app->request->get(), 'corrector')->getTotalCount() ?>)</span></a></li>
                        <li <?= (Yii::$app->request->get('type') == 'publisher' ? "class='active'" : "") ?>><a href="<?= '?host=' . Yii::$app->params['selectedHostId'] . '&type=publisher' ?>">У публиковщика <span>(<?= $searchModel->search(Yii::$app->request->get(), 'publisher')->getTotalCount() ?>)</span></a></li>
                        <li <?= (Yii::$app->request->get('type') == 'checkkm' ? "class='active'" : "") ?>><a href="<?= '?host=' . Yii::$app->params['selectedHostId'] . '&type=checkkm' ?>">На проверке у КМ <span>(<?= $searchModel->search(Yii::$app->request->get(), 'checkkm')->getTotalCount() ?>)</span></a></li>
                        <li <?= (Yii::$app->request->get('type') == 'master' ? "class='active'" : "") ?>><a href="<?= '?host=' . Yii::$app->params['selectedHostId'] . '&type=master' ?>">У Мастера <span>(<?= $searchModel->search(Yii::$app->request->get(), 'master')->getTotalCount() ?>)</span></a></li>
                        <li <?= (Yii::$app->request->get('type') == 'work' ? "class='active'" : "") ?>><a href="<?= '?host=' . Yii::$app->params['selectedHostId'] . '&type=work' ?>">Нужно проверить <span>(<?= $searchModel->search(Yii::$app->request->get(), 'work')->getTotalCount() ?>)</span></a></li>
                        <li <?= (Yii::$app->request->get('type') == 'complite' ? "class='active'" : "") ?>><a href="<?= '?host=' . Yii::$app->params['selectedHostId'] . '&type=complite' ?>">Завершено <span>(<?= $searchModel->search(Yii::$app->request->get(), 'complite')->getTotalCount() ?>)</span></a></li>
                        <li <?= (Yii::$app->request->get('type') == 'notpay' ? "class='active'" : "") ?>><a href="<?= '?host=' . Yii::$app->params['selectedHostId'] . '&type=notpay' ?>">Не будет оплачено <span>(<?= $searchModel->search(Yii::$app->request->get(), 'notpay')->getTotalCount() ?>)</span></a></li>
                        <li <?= (Yii::$app->request->get('type') == '' ? "class='active'" : "") ?>><a href="<?= '?host=' . Yii::$app->params['selectedHostId'] ?>">Все <span>(<?= $searchModel->search(Yii::$app->request->get(), '')->getTotalCount() ?>)</span></a></li>
                    </ul>
                </div>
                <?php
                break;

            case User::ROLE_AUTHOR:
            case User::ROLE_CORRECTOR:
            case User::ROLE_PUBLISHER:
            case User::ROLE_EXPERT:
            case User::ROLE_SEO:
            case User::ROLE_MASTER:
                ?> 
                <div class="col-lg-12 cartbar"> 
                    <ul class="nav nav-tabs" style="margin-bottom: 12px;">
                        <li <?= (Yii::$app->request->get('type') == '' ? "class='active'" : "") ?>><a href="<?= '?host=' . Yii::$app->params['selectedHostId'] ?>">Все (<?= $searchModel->search(Yii::$app->request->get(), '')->getTotalCount() ?>)</a></li> 
                        <li <?= (Yii::$app->request->get('type') == 'work' ? "class='active'" : "") ?>><a href="<?= '?host=' . Yii::$app->params['selectedHostId'] . '&type=work' ?>">В работе (<?= $searchModel->search(Yii::$app->request->get(), 'work')->getTotalCount() ?>)</a></li>
                        <?php if (!Yii::$app->user->identity->can([User::ROLE_EXPERT], 'can', FALSE)) {
                            ?>
                            <li <?= (Yii::$app->request->get('type') == 'during' ? "class='active'" : "") ?>><a href="<?= '?host=' . Yii::$app->params['selectedHostId'] . '&type=during' ?>">На проверке(<?= $searchModel->search(Yii::$app->request->get(), 'during')->getTotalCount() ?>)</a></li>
                        <?php }
                        ?>
                        <?php if (Yii::$app->user->identity->can([User::ROLE_KM], 'can', FALSE)) {
                            ?>
                            <li <?= (Yii::$app->request->get('type') == 'verification' ? "class='active'" : "") ?>><a href="<?= '?host=' . Yii::$app->params['selectedHostId'] . '&type=verification' ?>">На утверждении (<?= $searchModel->search(Yii::$app->request->get(), 'verification')->getTotalCount() ?>)</a></li>
                        <?php }
                        ?>
                        <li <?= (Yii::$app->request->get('type') == 'complite' ? "class='active'" : "") ?> ><a href="<?= '?host=' . Yii::$app->params['selectedHostId'] . '&type=complite' ?>">Завершено (<?= $searchModel->search(Yii::$app->request->get(), 'complite')->getTotalCount() ?>)</a></li>
                    </ul>
                </div>
                <?php
                break;
        }
        ?>
        <div class="col-lg-12"> 
            <?=
            \yii\grid\GridView::widget([
                'dataProvider' => $dataProvider,
                'filterModel' => false,
                'filterRowOptions' => [
                    'style' => 'display: none;'
                ],
                'summary' => Yii::$app->user->can(\common\models\User::ROLE_SUPERADMIN) ? 'Показаны записи <b>{begin}-{end}</b> из <b>{totalCount}</b>. ' .
                        Html::tag('div', 'Выбрано: ' . Html::tag('span', '0'), [
                            'style' => 'display: inline-block',
                            'id' => 'check-counter'
                        ]) : 'Показаны записи <b>{begin}-{end}</b> из <b>{totalCount}</b>',
                'columns' => [
                    [
                        'class' => '\yii\grid\CheckboxColumn',
                        'visible' => Yii::$app->user->identity->can([User::ROLE_KM, User::ROLE_SUPERADMIN, User::ROLE_EDITOR], 'can', FALSE),
                        'options' => [
                            'style' => [
                                'width' => '6%'
                            ]
                        ],
                        'headerOptions' => [
                            'style' => [
                                'text-align' => 'center'
                            ]
                        ],
                        'contentOptions' => [
                            'style' => [
                                'text-align' => 'center'
                            ]
                        ]
                    ],
                    [
                        'attribute' => 'title',
                        'options' => [
                            'style' => [
                                'width' => '25%'
                            ]
                        ],
                        'content' => function($m, $url) {
                            /* @var $m \frontend\models\tz */
                            if (Yii::$app->user->identity->can([User::ROLE_KM, User::ROLE_SUPERADMIN, User::ROLE_EDITOR, User::ROLE_SEO], 'can', FALSE)) {
                                return (Yii::$app->user->identity->can([User::ROLE_SEO], 'can', FALSE) ? $m->title : Html::a($m->title, 'tz/tz_edit?id=' . $url . '&host=' . common\components\dez\Tools::getHostName($m->hostId), [
                                            'target' => '_blank'
                                        ]))
                                        . ($m->url ?
                                        ' ' . Html::a('<i class="fa fa-external-link"></i>', '#', [
                                            'class' => 'a-title',
                                            'title' => 'Нажмите, чтобы скопировать ссылку в буфер обмена',
                                            'data-url' => $m->url,
                                            'style' => 'margin-left: 10px; display: inline-block'
                                        ]) : '')
                                        . ($m->keys ?
                                        ' ' . Html::a(
                                                '<i class="fa fa-key"></i>', '#keys', [
                                            'data-html' => 'true',
                                            'data-toggle' => 'popover',
                                            'data-placement' => 'right',
                                            'data-trigger' => 'hover',
                                            'data-content' =>
                                            Html::tag('p', 'Суммарно(' . $m->totalFrequency . ')', [
                                                'style' => 'padding-bottom: 10px; display: block;',
                                            ]) .
                                            Html::ul(
                                                    $m->getKeys()->all(), [
                                                'item' => function($e) {
                                                    /* @var $e \frontend\models\Keys */
                                                    return "<li>$e->value|$e->frequency</li>";
                                                },
                                                'style' => 'list-style: none; padding: 0; overflow: hidden; max-height: calc(100vh - 100px);'
                                                    ]
                                            )
                                            . Html::tag('b', 'Кликните, чтобы скопировать в буфер обмена', [
                                                'style' => 'padding-top: 10px; display: block; text-align: center',
                                            ]),
                                            'style' => [
                                                'margin-left' => '8px',
                                                'display' => 'inline-block'
                                            ]
                                                ]
                                        ) : '') . ($m->url ?
                                        ' ' . Html::a('<i class="fa fa-eye"></i>', $m->url, [
                                            'class' => '',
                                            'target' => '_blank',
                                            'title' => 'Нажмите, чтобы перейти на статью',
                                            'data-url' => $m->url,
                                            'style' => 'margin-left: 10px; display: inline-block'
                                        ]) : '')
                                        . (Yii::$app->user->identity->can([User::ROLE_SEO], 'can', FALSE) ? Html::a('<i class="fa  fa-check-circle-o"></i>', "/tz/sendforreview?host=" . Yii::$app->params['selectedHostId'] . "&id=" . $m->id, [
                                            'class' => 'check',
                                            'target' => '_blank',
                                            'title' => 'Нажмите, чтобы отправить на проверку',
                                            'data-url' => "/tz/sendforreview?host=" . Yii::$app->params['selectedHostId'] . "&id=" . $m->id,
                                            'style' => 'margin-left: 10px; display: inline-block'
                                        ]) : '')
                                        . ((((Yii::$app->user->identity->can([User::ROLE_SUPERADMIN], 'can', FALSE)) & ($m->status == 8)) || ((Yii::$app->user->identity->can([User::ROLE_EDITOR], 'can', FALSE)) & ($m->status == 6.5))) ? Html::a('<i class="fa  fa-check-circle-o"></i>', "/tz/sendforreview?host=" . Yii::$app->params['selectedHostId'] . "&id=" . $m->id, [
                                            'class' => 'check',
                                            'target' => '_blank',
                                            'title' => 'Завершить',
                                            'data-url' => "/tz/sendforreview?host=" . Yii::$app->params['selectedHostId'] . "&id=" . $m->id,
                                            'style' => 'margin-left: 10px; display: inline-block'
                                        ]) : '')
                                        . (((Yii::$app->user->identity->can([User::ROLE_SUPERADMIN, User::ROLE_EDITOR], 'can', FALSE)) & ($m->status == 0)) ? Html::a('<i class="fa ">' . ($m->hidden == 0 ? ' Выкл' : ' Вкл') . '</i>', "/tz/tzvisible?host=" . Yii::$app->params['selectedHostId'] . "&id=" . $m->id, [
                                            'class' => 'check1',
                                            'target' => '_blank',
                                            'title' => 'Вкл/Выкл',
                                            'data-url' => "/tz/tzvisible?host=" . Yii::$app->params['selectedHostId'] . "&id=" . $m->id,
                                            'style' => 'margin-left: 10px; display: inline-block'
                                        ]) : '')
                                        . (((Yii::$app->user->identity->can([User::ROLE_KM, User::ROLE_SUPERADMIN, User::ROLE_EDITOR], 'can', FALSE)) & ($m->status == 6 )) ? Html::a('<i class="fa  fa-check-circle-o"></i>', "/tz/sendforreview?host=" . Yii::$app->params['selectedHostId'] . "&id=" . $m->id, [
                                            'class' => 'check',
                                            'target' => '_blank',
                                            'title' => 'Завершить',
                                            'data-url' => "/tz/sendforreview?host=" . Yii::$app->params['selectedHostId'] . "&id=" . $m->id,
                                            'style' => 'margin-left: 10px; display: inline-block'
                                        ]) : '')
                                        . (((Yii::$app->user->identity->can([User::ROLE_KM, User::ROLE_SUPERADMIN, User::ROLE_EDITOR], 'can', FALSE)) & ($m->status == Tz::statusNapAuthor && !$m->needCorrector && !$m->needPublisher )) ? Html::a('<i class="fa  fa-check-circle-o"></i>', "/tz/sendforreview?host=" . Yii::$app->params['selectedHostId'] . "&id=" . $m->id, [
                                            'class' => 'check',
                                            'target' => '_blank',
                                            'title' => 'Завершить',
                                            'data-url' => "/tz/sendforreview?host=" . Yii::$app->params['selectedHostId'] . "&id=" . $m->id,
                                            'style' => 'margin-left: 10px; display: inline-block'
                                        ]) : '')
                                        . (((Yii::$app->user->identity->can([User::ROLE_KM, User::ROLE_SUPERADMIN, User::ROLE_EDITOR], 'can', FALSE)) & ($m->status == Tz::statusReadyForDesign && !$m->needPublisher )) ? Html::a('<i class="fa  fa-check-circle-o"></i>', "/tz/sendforreview?host=" . Yii::$app->params['selectedHostId'] . "&id=" . $m->id, [
                                            'class' => 'check',
                                            'target' => '_blank',
                                            'title' => 'Завершить',
                                            'data-url' => "/tz/sendforreview?host=" . Yii::$app->params['selectedHostId'] . "&id=" . $m->id,
                                            'style' => 'margin-left: 10px; display: inline-block'
                                        ]) : '')       
                                        . (((Yii::$app->user->identity->can([User::ROLE_KM, User::ROLE_SUPERADMIN, User::ROLE_EDITOR], 'can', FALSE)) & ($m->uniqueUrl != '' )) ? Html::a('<i class="fa fa-copyright"></i>', $m->uniqueUrl, [
                                            'class' => '',
                                            'target' => '_blank',
                                            'title' => 'Посмотреть уникальность',
                                            'data-url' => $m->uniqueUrl,
                                            'style' => 'margin-left: 10px; display: inline-block'
                                        ]) : '');
                                                
                                                
                                                
                            } elseif (Yii::$app->user->identity->can([User::ROLE_AUTHOR, User::ROLE_MASTER], 'can', FALSE)) {
                                return Html::a($m->title, 'tz/tz_edit?id=' . $url . '&host=' . Yii::$app->params['selectedHostId'], [
                                            'target' => '_blank'
                                        ])
                                        . ($m->textArticle ? ($m->status < 1.5 ? Html::a('<i class="fa  fa-check-circle-o"></i>', "/tz/sendforreview?host=" . Yii::$app->params['selectedHostId'] . "&id=" . $m->id, [
                                            'class' => 'check',
                                            'target' => '_blank',
                                            'title' => 'Нажмите, чтобы отправить на проверку',
                                            'data-url' => "/tz/sendforreview?host=" . Yii::$app->params['selectedHostId'] . "&id=" . $m->id,
                                            'style' => 'margin-left: 10px; display: inline-block'
                                        ]) : '') : '')
                                ;
                            } else {
                                return Html::a($m->title, 'tz/tz_edit?id=' . $url . '&host=' . Yii::$app->params['selectedHostId'], [
                                            'target' => '_blank'
                                ]);
                            }
                        }
                    ],
                    //КАТЕГОРИИ
                    [
                        'attribute' => 'category',
                        'visible' => (Yii::$app->params['selectedHost'] == 'Без публикации' ? FALSE : TRUE) && Yii::$app->user->identity->can([User::ROLE_KM, User::ROLE_SUPERADMIN, User::ROLE_EDITOR], 'can', FALSE),
                        'options' => [
                            'style' => [
                                'width' => '11%'
                            ]
                        ],
                        'contentOptions' => [
                            'style' => ['text-align' => 'center']
                        ],
                    ],
                    //ОТВЕТСТВЕННЫЙ АДМИН
                    [
                        'header' => 'Добавил',
                        'visible' => Yii::$app->user->identity->can([User::ROLE_KM], 'can', FALSE),
                        'options' => [
                            'style' => [
                                'width' => '6%'
                            ]
                        ],
                        'content' => function ($model, $key, $index, $column) {
                            Yii::info(Yii::$app->user->identity->getuserstoid($model->admin));
                            return Yii::$app->user->identity->getuserstoid($model->admin)[0]['byname'];
                        }
                    ],
                    //ТЕГИ
//                    [
//                        'header' => 'Теги',
//                        'attribute' => 'strtegs',
//                        'visible' => (Yii::$app->params['selectedHost'] == 'Без публикации' ? FALSE : TRUE) && Yii::$app->user->identity->can([User::ROLE_KM, User::ROLE_SUPERADMIN, User::ROLE_EDITOR], 'can', FALSE),
//                        'options' => [
//                            'style' => [
//                                'width' => '6%'
//                            ]
//                        ],
//                    ],
                    //
                    //Исполнитель
                    [
                        'header' => 'Исполнитель',
                        'visible' => (Yii::$app->user->identity->can([User::ROLE_KM], 'can', FALSE) || (Yii::$app->user->identity->can([User::ROLE_SUPERADMIN], 'can', FALSE)) && Yii::$app->request->get('type') == ('new')) || Yii::$app->user->identity->can([User::ROLE_SUPERADMIN], 'can', FALSE) || Yii::$app->user->identity->can([User::ROLE_EDITOR], 'can', FALSE),
                        'value' => function ($model, $key, $index, $column) {
                            //$rez['default'] = 'Назначить';
                            if ($model->hidden == 1) {
                                return'';
                            }
                            switch ($model->status) {
                                case 0.5:
                                    return User::getuserstoid($model->master)[0]['byname'];
                                    break;
                                case 0:
                                case 1:
                                    if (!$model->author) {
                                        $rez['default'] = 'Назначить автора';
                                    }
                                    foreach (Yii::$app->params['users'] as $user => $role) {
                                        if ($role['disabled'] != true) {
                                            if (($role['role'] == User::ROLE_AUTHOR) && in_array($model->hostId, $role['hosts'])) {
                                                $rez[$role['id']] = $role['byname'] . '(' . User::getquantitywork($role['id'], 'author') . ')';
                                            }
                                        }
                                    }

                                    return Html::activeDropDownList($model, 'author', $rez);

                                    break;
                                case 2:
                                case 3:

                                    if (!$model->corrector) {
                                        $rez['default'] = 'Назначить корректора';
                                    }
                                    $needRole = User::ROLE_CORRECTOR;
                                    $needRoleName = 'corrector';
                                    if (!$model->needCorrector) {
                                        $needRole = User::ROLE_PUBLISHER;
                                        $needRoleName = 'publisher';
                                        $rez['default'] = 'Назначить публиковщика';
                                        if (!$model->needPublisher) {
                                            break;
                                        }
                                    }
                                    foreach (Yii::$app->params['users'] as $user => $role) {
                                        if ($role['disabled'] != true) {
                                            if ($role['role'] == $needRole && in_array($model->hostId, $role['hosts'])) {
                                                $rez[$role['id']] = $role['byname'] . '(' . User::getquantitywork($role['id'], $needRoleName) . ')';
                                            }
                                        }
                                    }
                                    if ($model->needCorrector == 1 && $model->status == Tz::statusNapAuthor) {
                                        $rez['skipcorrector'] = 'Пропустить корректора';
                                    }
                                    if ($model->needPublisher == 1 && $model->needCorrector == 0 && $model->status == Tz::statusNapAuthor) {
                                        $rez['skippublisher'] = 'Пропустить публиковщика';
                                    }
                                    return Html::activeDropDownList($model, 'corrector', $rez);
                                    break;

                                case 4:
                                case 5:
                                    if (!$model->needPublisher) {
                                        break;
                                    }
                                    if ($model->hostId == 'Без публикации') {
                                        break;
                                    }
                                    if (!$model->publisher) {
                                        $rez['default'] = 'Назначить публиковщика';
                                    }

                                    foreach (Yii::$app->params['users'] as $user => $role) {
                                        if ($role['disabled'] != true) {
                                            if ($role['role'] == User::ROLE_PUBLISHER && in_array($model->hostId, $role['hosts'])) {
                                                $rez[$role['id']] = $role['byname'] . '(' . User::getquantitywork($role['id'], 'publisher') . ')';
                                            }
                                        }
                                    }
                                    if ($model->needPublisher == 1 && $model->status == Tz::statusReadyForDesign) {
                                        $rez['skippublisher'] = 'Пропустить публиковщика';
                                    }
                                    return Html::activeDropDownList($model, 'publisher', $rez);
                                    break;

                                case 6:
                                    return'';
                                    break;

                                case 7:
                                    if (!$model->seo) {
                                        $rez['default'] = 'Назначить SEO';
                                    }
                                    foreach (Yii::$app->params['users'] as $user => $role) {
                                        if ($role['disabled'] != true) {
                                            if ($role['role'] == User::ROLE_SEO) {
                                                $rez[$role['id']] = $role['byname'] . '(' . User::getquantitywork($role['id'], 'seo') . ')';
                                            }
                                        }
                                    }
                                    return Html::activeDropDownList($model, 'seo', $rez);
                                    break;
                            }
                        },
                        'format' => 'raw',
                        'options' => [
                            'style' => [
                                'width' => '9%'
                            ]
                        ],
                        'contentOptions' => [
                            'style' => ['text-align' => 'center']
                        ],
                    ],
                    //
                    //Исполнитель для Редактора и Админа
                    [
                        'header' => 'Исполнитель',
                        'visible' => Yii::$app->user->identity->can([User::ROLE_EDITOR, User::ROLE_SUPERADMIN], 'can', FALSE) && (Yii::$app->request->get('type') == ('author') || Yii::$app->request->get('type') == ('corrector' ) || Yii::$app->request->get('type') == ('publisher')),
                        'value' => function ($model, $key, $index, $column) {

                            //$rez['default'] = 'Назначить';
                            switch ($model->status) {
                                case 0.5:
                                    return User::getuserstoid($model->master)[0]['byname'];
                                    break;
                                case 1:
                                    return User::getuserstoid($model->author)[0]['byname'];
                                    break;

                                case 3:
                                    return User::getuserstoid($model->corrector)[0]['byname'];
                                    break;

                                case 5:
                                    return User::getuserstoid($model->publisher)[0]['byname'];
                                    break;

                                case 6:
                                    return'';
                                    break;

                                case 7:
                                    return User::getuserstoid($model->seo)[0]['byname'];
                                    break;
                            }
                        },
                        'format' => 'raw',
                        'options' => [
                            'style' => [
                                'width' => '15%'
                            ]
                        ],
                        'contentOptions' => [
                            'style' => ['text-align' => 'center']
                        ],
                    ],
                    //Этапы для админа и км  и редактора
                    [
                        'header' => 'Этап',
                        'visible' => Yii::$app->user->identity->can([User::ROLE_KM, User::ROLE_SUPERADMIN, User::ROLE_EDITOR], 'can', FALSE) && !(Yii::$app->request->get('type') == ('author') || Yii::$app->request->get('type') == ('corrector' ) || Yii::$app->request->get('type') == ('publisher') || Yii::$app->request->get('type') == ('new')),
                        'value' => function ($model, $key, $index, $column) {

                            switch ($model->status) {
                                case '0':
                                    if (Yii::$app->user->identity->can([User::ROLE_SUPERADMIN, User::ROLE_EDITOR], 'can', FALSE)) {
                                        $rez['default'] = 'Назначить автора';
                                        foreach (Yii::$app->params['users'] as $user => $role) {
                                            if ($role['disabled'] != true) {
                                                if ($role['role'] == User::ROLE_AUTHOR) {
                                                    $rez[$role['id']] = $role['byname'] . '(' . User::getquantitywork($role['id'], 'author') . ')';
                                                }
                                            }
                                        }
                                        return 'Новое' . Html::activeDropDownList($model, 'author', $rez);
                                    }

                                    return 'Новое';
                                    break;
                                case '0.5':
                                    return 'У Мастера';
                                    break;
                                case '1':
                                    return 'Назначено автору (' . User::getuserstoid($model->author)[0]['byname'] . ')';
                                    break;
                                case '1.5':
                                    return 'Проверяется экспертом ';
                                    break;
                                case '2':
                                    return 'Написано автором';
                                    break;
                                case '3':
                                    return 'На корректировке (' . User::getuserstoid($model->corrector)[0]['byname'] . ')';
                                    break;
                                case '4':
                                    return 'Готово к оформлению';
                                    break;
                                case '5':
                                    return 'На оформлении (' . User::getuserstoid($model->publisher)[0]['byname'] . ')';
                                    break;
                                case '6':
                                    return 'На проверке у КМ';
                                    break;
                                case 6.5:
                                    return 'На проверке у Редактора';
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
                        },
                        'format' => 'raw',
                        'options' => [
                            'style' => [
                                'width' => '9%'
                            ]
                        ],
                        'contentOptions' => [
                            'style' => ['text-align' => 'center']
                        ],
                    ],
                    //Этапы для рабочих
                    [
                        'header' => 'Этап',
                        'visible' => !Yii::$app->user->identity->can([User::ROLE_KM, User::ROLE_SUPERADMIN, User::ROLE_EDITOR], 'can', FALSE),
                        'value' => function ($model, $key, $index, $column) {
                            $user = Yii::$app->user->identity->role;

                            switch ($user) {
                                case User::ROLE_EXPERT:
                                    if ($model->status == 1.5) {
                                        return 'В работе';
                                    } elseif ($model->status > 1.5) {
                                        return 'Завершено';
                                    }
                                    break;
                                case User::ROLE_MASTER:
                                    if ($model->status == Tz::statusNazMaster) {
                                        return 'В работе';
                                    } elseif ($model->status >= Tz::statusEdit && $model->status <= Tz::statusVerified) {
                                        return 'На проверке';
                                    } elseif ($model->status > Tz::statusVerified) {
                                        return 'Завершено';
                                    }
                                    break;
                                case User::ROLE_AUTHOR:
                                    if ($model->status == 1) {
                                        return 'В работе';
                                    } elseif ($model->status == 2 || $model->status == 1.5 || ($model->status == 4 && $model->needCorrector == 0) || ($model->needCorrector == 0 && $model->needPublisher == 0 && $model->status == 6)) {
                                        return 'На проверке';
                                    } elseif (($model->status > 2 && $model->needCorrector == 1) || ($model->status > 4 && $model->needCorrector == 0)) {
                                        return 'Завершено';
                                    }
                                    break;
                                case User::ROLE_CORRECTOR:
                                    if ($model->status == 3) {
                                        return 'В работе';
                                    } elseif ($model->status == 4 || ($model->status == 6 && $model->needPublisher == 0)) {
                                        return 'На проверке';
                                    } elseif ($model->status > 4) {
                                        return 'Завершено';
                                    }
                                    break;
                                case User::ROLE_PUBLISHER:
                                    if ($model->status == 5) {
                                        return 'В работе';
                                    } elseif ($model->status == 6) {
                                        return 'На проверке';
                                    } elseif ($model->status > 6) {
                                        return 'Завершено';
                                    }
                                    break;
                                case User::ROLE_SEO:
                                    if ($model->status == 7) {
                                        return 'В работе';
                                    } elseif ($model->status == 8) {
                                        return 'На проверке';
                                    } elseif ($model->status > 8) {
                                        return 'Завершено';
                                    }
                                    break;
                            }
                        },
                        'format' => 'raw',
                        'options' => [
                            'style' => [
                                'width' => '9%'
                            ]
                        ],
                        'contentOptions' => [
                            'style' => ['text-align' => 'center']
                        ],
                    ],
                    //ДАТА ИЗМЕНЕНИЯ 
                    [
                        'attribute' => 'dateCreate',
                        'visible' => Yii::$app->user->identity->can([User::ROLE_KM, User::ROLE_SUPERADMIN], 'can', FALSE),
                        'options' => [
                            'style' => [
                                'width' => '13%'
                            ]
                        ],
                        'contentOptions' => [
                            'style' => ['text-align' => 'center']
                        ],
                    ],
                    //Хост
                    [
                        'header' => 'Сайт',
                        'attribute' => 'host',
                        'visible' => (Yii::$app->params['selectedHost'] == 'Все сайты' ? TRUE : FALSE),
                        'options' => [
                            'style' => [
                                'width' => '7%'
                            ]
                        ],
                    ],
                    [
                        'class' => '\yii\grid\ActionColumn',
                        'header' => 'Ключи',
                        'visible' => Yii::$app->user->can(\common\models\User::ROLE_SUPERADMIN) && false,
                        'template' => '{showKeys}',
                        'options' => [
                            'style' => [
                                'width' => '6%'
                            ]
                        ],
                        'headerOptions' => [
                            'style' => [
                                'text-align' => 'center'
                            ]
                        ],
                        'contentOptions' => [
                            'style' => [
                                'text-align' => 'center'
                            ]
                        ],
                        'buttons' => [
                            'showKeys' => function($url, $m) {
                                /* @var $m \frontend\models\tz */
                                return Html::a(
                                                '<i class="fa fa-key"></i>', '#tzkeys', [
                                            'data-html' => 'true',
                                            'data-toggle' => 'popover',
                                            'data-placement' => 'left',
                                            'data-trigger' => 'hover',
                                            'data-content' =>
                                            'Суммарно(' . $m->totalFrequency . ')'
                                            . Html::ul(
                                                    $m->getKeys()->all(), [
                                                'item' => function($e) {

                                                    /* @var $e \frontend\models\Keys */
                                                    return "<li>$e->value|$e->frequency</li>";
                                                },
                                                'style' => 'list-style: none; padding: 0;'
                                                    ]
                                            ) . Html::tag('b', 'Кликните, чтобы скопировать в буфер обмена', [
                                                'style' => 'padding-top: 10px; display: block; text-align: center',
                                            ])
                                                ]
                                );
                            }
                        ]
                    ],
                    [
                        'header' => 'Кто добавил',
                        'visible' => Yii::$app->user->identity->can([User::ROLE_SUPERADMIN], 'can', FALSE),
                        'options' => [
                            'style' => [
                                'width' => '2%'
                            ],
                        ],
                        'contentOptions' => [
                            'style' => ['text-align' => 'center']
                        ],
                        'value' => function ($model, $key, $index, $column) {
                            foreach (Yii::$app->params['users'] as $user => $role) {
                                if ($role['disabled'] != true) {
                                    if ($role['id'] == $model->admin) {
                                        $rez = $role['byname'];
                                    }
                                }
                            }
                            return $rez;
                        },
                    ],
                    [
                        'header' => 'Инфо',
                        'options' => [
                            'style' => [
                                'width' => '2%'
                            ]
                        ],
                        'content' => function($m, $url) {
                            if ($m->returncom) {
                                $com = $m->returncom;
                            } elseif (Yii::$app->user->identity->can([User::ROLE_KM, User::ROLE_SUPERADMIN, User::ROLE_EDITOR], 'can', FALSE) && $m->comments) {
                                $com = $m->comments;
                            } else {
                                $com = '';
                            }
                            if ($com != '') {
                                $com = Html::a(
                                                '<i class="fa fa-info-circle"></i>', '#', [
                                            'data-html' => 'true',
                                            'data-toggle' => 'popover',
                                            'data-placement' => 'left',
                                            'data-trigger' => 'hover',
                                            'data-content' => $com,
                                            'style' => [
                                                'margin-left' => '8px',
                                                'display' => 'inline-block'
                                            ]
                                                ]
                                );
                            }
                            return $com;
                        }
                    ],
                    [
                        'class' => \yii\grid\ActionColumn::className(),
                        'visible' => Yii::$app->user->identity->can([User::ROLE_SUPERADMIN, User::ROLE_EDITOR], 'can', FALSE),
                        'template' => '{delete}',
                        'contentOptions' => [
                            'style' => [
                                'width' => '2%'
                            ]
                        ],
                        'buttons' => [
                            'delete' => function($url, $m) {
                                /* @var $m \frontend\models\tz */
                                if (strtotime('+2 day', strtotime($m->dateCreate)) > time()) {
                                    return Html::a(
                                                    '<i class="fa fa-trash"></i>', ['/tz/tz-ajax', 'host' => Yii::$app->request->get('host', 0)], [
                                                'title' => 'Удалить',
                                                'data' => [
                                                    'confirm' => 'Вы уверены, что хотите удалить этот элемент?',
                                                    'method' => 'post',
                                                    'pjax' => true,
                                                    'params' => [
                                                        'action' => 'delete',
                                                        'row' => $m->id,
                                                        'render' => 'tz'
                                                    ]
                                                ]
                                                    ]
                                    );
                                }
                                return '';
                            }
                        ]
                    ],
                ],
                'rowOptions' => function($m) {
                    /* @var $m \frontend\models\tz */
                    if ($m->urgently == 1) {
                        
                    }

                    $user = Yii::$app->user->identity->role;

                    switch ($user) {
                        case User::ROLE_EXPERT:
                            return [
                                'class' => $m->status > 1.5 ? 'success' : ($m->urgently == 1 ? 'urgently' : 'warning') //'warning' 
                            ];

                            break;
                        case User::ROLE_AUTHOR:
                            return [
                                'class' => (($m->status >= 3 && $m->needCorrector == 1) || ($m->status >= 5 && $m->needPublisher == 1 && $m->needCorrector == 0) || $m->status > 6) ? 'success' : ($m->urgently == 1 ? 'urgently' : 'warning') //'warning'
                            ];
//(($m->status >= 3 && $m->needCorrector == 1) || ($m->status >= 5 && $m->needPublisher == 1 && $m->needCorrector == 0) || $m->status > 6)
                            break;
                        case User::ROLE_CORRECTOR:
                            return [
                                'class' => (($m->status >= 5 && $m->needPublisher == 1) || ($m->status > 6 && $m->needPublisher == 0)) ? 'success' : ($m->urgently == 1 ? 'urgently' : 'warning') //'warning'
                            ];

                            break;
                        case 4:
                            return [
                                'class' => $m->status >= 7 ? 'success' : ($m->urgently == 1 ? 'urgently' : 'warning') //'warning'
                            ];

                            break;
                        case 3:
                            return [
                                'class' => $m->status == 9 ? 'success' : ($m->urgently == 1 ? 'urgently' : 'warning') //'warning'
                            ];

                            break;
                        case 1:
                            return [
                                'class' => $m->status >= 7 ? 'success' : ($m->urgently == 1 ? 'urgently' : 'warning') //'warning'
                            ];

                            break;
                    }
                    return [
                        'class' => $m->status == 9 ? 'success' : ($m->urgently == 1 ? 'urgently' : 'warning') //'warning'
                    ];
                },
                'options' => [
                    'id' => 'grid-tz'
                ]
            ])
            ?>
        </div>

        <?php
        \yii\widgets\Pjax::end();
        ?>
    </div>
    <div style="text-align: center"> 
        <?php
        $models = $dataProvider->models;
        $str = 0;
        foreach ($models as $tz) {
            $str = $str + iconv_strlen(trim(preg_replace('~\s+~s', '', str_replace("&nbsp;", '', (strip_tags($tz->textArticle))))), 'UTF-8');
        }
        echo 'Количество символов всех текстов статей: ' . $str;
        ?>
    </div>


