<?php

namespace frontend\models;

use Yii;
use yii\data\ActiveDataProvider;
use yii\db\Query;
use frontend\models\Tz;
use common\models\User;

class TzSearch extends Tz {

    /**
     * @param $params mixed
     * @return ActiveDataProvider
     */
    public function search($params, $type) {
        $startw = strtotime('Monday previous week') + 3600; //понедельник прошлой недели
        $endw = strtotime('Sunday previous week') + 86399 + 3600; //воскресенье прошлой недели
        
        if ($this->load($params)) {
            ///   $type!=NULL;
        }

        $user = Yii::$app->user->identity->role;
        if ($user == User::ROLE_SUPERADMIN || $user == User::ROLE_EDITOR) {
            $query = static::find();
        } else {
            $query = static::find()->andWhere(['hidden' => 0]);
        }
        if ($user == User::ROLE_MASTER) {
            $query = $query->andWhere(['wayMaster' => 1]);
        } else {
            if ($user == User::ROLE_SUPERADMIN || $user == User::ROLE_EDITOR || $user == User::ROLE_PUBLISHER) {
                
            } else {
                $query = $query->andWhere(['wayMaster' => 0]);
            }
        }
        switch ($user) {
            case User::ROLE_MASTER:
                $query = $query->andWhere(['master' => Yii::$app->user->identity->id])->andWhere(['>', 'status', '0']);
                if (Yii::$app->params['selectedHost'] == 'Все сайты') {
                    $query = $query->andWhere(['hostId' => Yii::$app->user->identity->hosts]);
                } else {
                    $query = $query->andWhere(['hostId' => Yii::$app->params['selectedHost']]);
                }
                if ($type == 'complite') {
                    $query = $query->andWhere(['>', 'status', Tz::statusVerification])->andWhere(['wayMaster' => 1]);
                } elseif ($type == 'work') {
                    $query = $query->andWhere(['status' => Tz::statusNazMaster]);
                } elseif ($type == 'during') {
                    $query = $query->andWhere(['>=', 'status', Tz::statusEdit])->andWhere(['<', 'status', Tz::statusVerified]);
                }
                break;
            case User::ROLE_EXPERT:
                $query = $query->andWhere(['expertTesting' => 1]);
                if (Yii::$app->params['selectedHost'] == 'Все сайты') {
                    $query = $query->andWhere(['hostId' => Yii::$app->user->identity->hosts]);
                } else {
                    $query = $query->andWhere(['hostId' => Yii::$app->params['selectedHost']]);
                }
                if ($type == 'complite') {
                    $query = $query->andWhere(['>', 'status', '1.5']);
                } elseif ($type == 'work') {
                    $query = $query->andWhere(['=', 'status', '1.5']);
                }
                break;

            case User::ROLE_AUTHOR :
                $query = $query->andWhere(['author' => Yii::$app->user->identity->id])->andWhere(['>', 'status', Tz::statusNew]);
                if (Yii::$app->params['selectedHost'] == 'Все сайты') {
                    $query = $query->andWhere(['hostId' => Yii::$app->user->identity->hosts]);
                } else {
                    $query = $query->andWhere(['hostId' => Yii::$app->params['selectedHost']]);
                }
                if ($type == 'complite') {
                    $query = $query->andWhere(['>', 'status', Tz::statusNapAuthor]);
                } elseif ($type == 'work') {
                    $query = $query->andWhere(['status' => Tz::statusNazAuthor]);
                } elseif ($type == 'during') {
                    $query = $query->andWhere(['status' => Tz::statusNapAuthor]);
                }
                break;

            case User::ROLE_CORRECTOR :
                $query = $query->andWhere(['corrector' => Yii::$app->user->identity->id])->andWhere(['>', 'status', Tz::statusNapAuthor]);
                if (Yii::$app->params['selectedHost'] == 'Все сайты') {
                    $query = $query->andWhere(['hostId' => Yii::$app->user->identity->hosts]);
                } else {
                    $query = $query->andWhere(['hostId' => Yii::$app->params['selectedHost']]);
                }
                if ($type == 'complite') {
                    $query = $query->andWhere(['>', 'status', Tz::statusReadyForDesign]);
                } elseif ($type == 'work') {
                    $query = $query->andWhere(['status' => Tz::statusAdjustment]);
                } elseif ($type == 'during') {
                    $query = $query->andWhere(['status' => Tz::statusReadyForDesign]);
                }

                break;

            case User::ROLE_PUBLISHER :
                $query = $query->andWhere(['publisher' => Yii::$app->user->identity->id])->andWhere(['>', 'status', Tz::statusReadyForDesign]);
                if (Yii::$app->params['selectedHost'] == 'Все сайты') {
                    $query = $query->andWhere(['hostId' => Yii::$app->user->identity->hosts]);
                } else {
                    $query = $query->andWhere(['hostId' => Yii::$app->params['selectedHost']]);
                }
                if ($type == 'complite') {
                    $query = $query->andWhere(['>', 'status', Tz::statusSEO]);
                } elseif ($type == 'work') {
                    $query = $query->andWhere(['status' => Tz::statusDesign]);
                } elseif ($type == 'during') {
                    $query = $query->andWhere(['status' => Tz::statusSEO]);
                }

                break;

            case User::ROLE_SEO :
                $query = $query->andWhere(['>', 'status', Tz::statusSEO]);
                if (Yii::$app->params['selectedHost'] == 'Все сайты') {
                    $query = $query->andWhere(['hostId' => Yii::$app->user->identity->hosts]);
                } else {
                    $query = $query->andWhere(['hostId' => Yii::$app->params['selectedHost']]);
                }
                if ($type == 'complite') {
                    $query = $query->andWhere(['>', 'status', Tz::statusVerification]);
                } elseif ($type == 'work') {
                    $query = $query->andWhere(['status' => Tz::statusReadyToPublish]);
                } elseif ($type == 'during') {
                    $query = $query->andWhere(['status' => Tz::statusVerification]);
                }

                break;
            case User::ROLE_EDITOR :
                if (Yii::$app->params['selectedHost'] == 'Все сайты') {
                    $query = $query->andWhere(['hostId' => Yii::$app->user->identity->hosts]);
                } else {
                    $query = $query->andWhere(['hostId' => Yii::$app->params['selectedHost']]);
                }
                if ($type == 'new') {
                    $query = $query->andWhere(['=', 'status', Tz::statusNew]);
                } elseif ($type == 'author') {
                    $query = $query->andWhere(['=', 'status', Tz::statusNazAuthor]);
                } elseif ($type == 'expert') {
                    $query = $query->andWhere(['=', 'status', Tz::statusExpertTesting]);
                } elseif ($type == 'corrector') {
                    $query = $query->andWhere(['status' => Tz::statusAdjustment]);
                } elseif ($type == 'publisher') {
                    $query = $query->andWhere(['status' => Tz::statusDesign]);
                } elseif ($type == 'work') {
                    $query = $query->andWhere(['IN', 'status', [Tz::statusEdit]]);
                } elseif ($type == 'checkkm') {
                    $query = $query->andWhere(['IN', 'status', [Tz::statusNapAuthor, Tz::statusReadyForDesign, Tz::statusSEO]]);
                } elseif ($type == 'master') {
                    $query = $query->andWhere(['=', 'status', Tz::statusNazMaster])->andWhere(['wayMaster' => 1]);
                } elseif ($type == 'verification') {
                    $query = $query->andWhere(['status' => Tz::statusVerification]);
                } elseif ($type == 'complite') {
                    $query = $query->andWhere(['status' => Tz::statusVerified]);
                }

                break;
            case User::ROLE_SUPERADMIN :
                if (Yii::$app->params['selectedHost'] == 'Все сайты') {
                    $query = $query->andWhere(['hostId' => Yii::$app->user->identity->hosts]);
                } else {
                    $query = $query->andWhere(['hostId' => Yii::$app->params['selectedHost']]);
                }
                if ($type == 'new') {
                    $query = $query->andWhere(['=', 'status', Tz::statusNew]);
                } elseif ($type == 'author') {
                    $query = $query->andWhere(['=', 'status', Tz::statusNazAuthor]);
                } elseif ($type == 'expert') {
                    $query = $query->andWhere(['=', 'status', Tz::statusExpertTesting]);
                } elseif ($type == 'corrector') {
                    $query = $query->andWhere(['status' => Tz::statusAdjustment]);
                } elseif ($type == 'publisher') {
                    $query = $query->andWhere(['status' => Tz::statusDesign]);
                } elseif ($type == 'work') {
                    $query = $query->andWhere(['IN', 'status', [Tz::statusVerification, Tz::statusProverkaMaster]]);
                } elseif ($type == 'checkkm') {
                    $query = $query->andWhere(['IN', 'status', [Tz::statusNapAuthor, Tz::statusReadyForDesign, Tz::statusSEO]]);
                } elseif ($type == 'master') {
                    $query = $query->andWhere(['=', 'status', Tz::statusNazMaster])->andWhere(['wayMaster' => 1]);
                } elseif ($type == 'verification') {
                    $query = $query->andWhere(['status' => Tz::statusReadyToPublish]);
                } elseif ($type == 'complite') {
                    $query = $query->andWhere(['status' => Tz::statusVerified]);
                } elseif ($type == 'notpay') {
                    $query = $query->andWhere(['IN', 'status', [Tz::statusNapAuthor, Tz::statusReadyForDesign]])->andWhere(['>', 'dateCreate', $startw])->andWhere(['<', 'dateCreate', $endw]);
                }

                break;
            case User::ROLE_KM :
                if (Yii::$app->params['selectedHost'] == 'Все сайты') {
                    $query = $query->andWhere(['hostId' => Yii::$app->user->identity->hosts]);
                } else {
                    $query = $query->andWhere(['hostId' => Yii::$app->params['selectedHost']]);
                }
                if ($type == 'new') {
                    $query = $query->andWhere(['=', 'status', Tz::statusNew]);
                } elseif ($type == 'author') {
                    $query = $query->andWhere(['=', 'status', Tz::statusNazAuthor]);
                } elseif ($type == 'expert') {
                    $query = $query->andWhere(['=', 'status', Tz::statusExpertTesting]);
                } elseif ($type == 'corrector') {
                    $query = $query->andWhere(['status' => Tz::statusAdjustment]);
                } elseif ($type == 'publisher') {
                    $query = $query->andWhere(['status' => Tz::statusDesign]);
                } elseif ($type == 'work') {
                    $query = $query->andWhere(['IN', 'status', [Tz::statusNapAuthor, Tz::statusReadyForDesign, Tz::statusSEO]]);
                } elseif ($type == 'verification') {
                    $query = $query->andWhere(['status' => [Tz::statusReadyToPublish, Tz::statusEdit]]);
                } elseif ($type == 'complite') {
                    $query = $query->andWhere(['status' => Tz::statusVerification]);
                }
                break;
        }
        $id_worker = Yii::$app->request->get('TzSearch')['worker'];
        $dataProvider = new ActiveDataProvider([
            'query' => $query,
            'sort' => new \yii\data\Sort([
                'attributes' => [
                    'title',
                    'dateCreate',
                    'dateCreate2',
                    'status',
                    'worker'
                ],
                    ])
        ]);
        if ($this->load($params)) {
            $query->andFilterWhere([
                'and',
                ['or', ['like', 'title', $this->title], ['like', 'url', $this->title]],
                [User::getrolenameforid($id_worker) => $id_worker],
                ['status' => $this->status],
                $this->dateCreate ? array_merge(['between', 'dateCreate'], array_map(function($e, $k) {
                                            return !$k ? strtotime($e) : strtotime($e . ' 23:59:59');
                                        }, explode('-', $this->dateCreate), range(0, 1))) : []
            ]);

//            if ($id_worker) {
//                switch (getrolenameforid($id_worker)) {
//                    case ('author'):
//                        $status = 1;
//                        break;
//                    case ('corrector'):
//                        $status = 3;
//                        break;
//                    case ('publisher'):
//                        $status = 5;
//                        break;
//                    case ('seo'):
//                        $status = 7;
//                        break;
//                }
//                $query->andFilterWhere([
//                    ['status' => $status],
//                ]);
//            }
//            $n = $searchModel->search($params, '')->getTotalCount();
//            Yii::info($n);
        }
//        Yii::info($dataProvider->getModels());
//        Yii::info(count($dataProvider->getModels()));
        // $n=$dataProvider->getModels()[0]->getType($user);

        return $dataProvider;
    }

}
