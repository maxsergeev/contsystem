<?php

use yii\widgets\ActiveForm;
?>

<?php
$form = ActiveForm::begin([
            'action' => ['/tz/upload', 'host' => Yii::$app->request->get('host', 0)],
            'options' => ['enctype' => 'multipart/form-data', 'class' => 'upload-form']
        ])
?>
<span >
<?= $form->field($model, 'textFile[]')->fileInput(['multiple' => true, 'accept' => 'text/*'])->label(false) ?>

</span>
<?php ActiveForm::end() ?>