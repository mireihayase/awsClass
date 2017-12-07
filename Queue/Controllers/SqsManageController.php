<?php

namespace App\Src\Queue\Controllers;

use App\Src\Queue\Models\SqsDbStatusCheck;
use App\Src\Queue\Models\SqsDbDpos;
use App\Src\Queue\Services\SqsFireService;
use App\Src\Queue\Services\SqsFifoService;
use App\Src\Aws\Api\SqsApiService;
use App\Src\Queue\Services\SqsHandleService;
use App\Src\Queue\Services\SqsJobService;
use App\Http\Controllers\Controller;
use App\Exceptions\RuntimeException;

/**
 * キュー基盤# 管理コントローラー
 */
class SqsManageController extends Controller
{
    /** @var SqsFifoService  */
    private $fifo_service;

    /** @var SqsDbStatusCheck */
    private $db_status_check;

    /** @var SqsDbDPos */
    private $db_d_pos;

    /** @var SqsFireService  */
    private $job_service;

    /** @var SqsHandleService */
    private $handle_service;

    /** @var null  */
    private $manage_min_id;

    /** @var int  */
    private $debug_num;

    /**
     * SqsManageController constructor.
     * @param SqsDbStatusCheck $db_status_check
     * @param SqsDbDpos $db_dpos
     * @param SqsFireService $job_service
     * @param SqsHandleService $handle_service
     */
    function __construct(
        SqsDbStatusCheck $db_status_check,
        SqsDbDpos $db_dpos,
        SqsFireService $job_service,
        SqsHandleService $handle_service
    )
    {
        $this->fifo_service = new SqsFifoService(
            null,   // 前回実行したIDを初期化
            null,   // 前回実行したカスタマー名を初期化
            false,  // 継続処理判定フラグを初期化
            false   // 実行権判定フラグを初期化
        );
        $this->db_status_check = $db_status_check;
        $this->db_d_pos = $db_dpos;
        $this->job_service = $job_service;
        $this->handle_service = $handle_service;
        $this->manage_min_id = null;
        $this->customer = null;
        $this->debug_num = 1;
    }

    /**
     * キューイング処理のワーカーを、逐次ポーリングで実行する
     *
     * @return void
     */
    public function run()
    {
        while (true) {
            $on_going_flg = $this->fifo_service->getOngoingFlg();  // 自身の継続処理判定フラグを取得
            $exec_flg = $this->fifo_service->getExecFlg();         // 自身の実行権判定フラグを取得
            $one_time_before_id = $this->fifo_service->getOneTimeBeforeId();  // 自身が前回実行したIDを取得
            $next_id = $one_time_before_id + 1;

            if ($on_going_flg)  echo " ongoing_flg: true \n";    // カスタマージョブを継続処理中である: デバッグ用
            if ( ! $on_going_flg) echo " ongoing_flg: false \n"; // カスタマージョブを継続処理中でない: デバッグ用

            try {
                // 継続状態に応じた先頭ジョブを、管理テーブルから取り出す
                $result = $this->db_status_check->showNextJob($on_going_flg, $exec_flg, $next_id);
                if ( ! $result == null) {   // 実行すべきジョブが管理テーブルに存在すれば
                    // stdClassObject(QueryBuilderの返り値) -> Array に変換
                    $this->manage_min_id = get_object_vars($result)['id'];
                    $this->customer = get_object_vars($result)['customer'];
                } else {
                    $this->manage_min_id = null;
                    $this->customer = null;
                }
                // 実行すべきジョブを探す
                $attributes = $this->fifo_service->searchJob(
                    $this->manage_min_id,
                    $this->customer
                );
            } catch (RuntimeException $e) {
                return false;
            }

            // 以下の実行条件を満たしているならばジョブを実行する
            //  - 1.非継続処理中かつ、連番開始IDの場合
            //  - 2.前回実行したカスタマーと同じで且つ、前回実行したIDと連番の場合
            //  - 3.ジョブが(1つ/1カスタマー)しかなく且つ、継続処理を考慮しない場合
            switch ($attributes['switch_flg']) {
                // ジョブを実行する条件を満たしている
                case 0:
                case 1:
                case 2:
//                    try {
                        // ジョブハンドラーを呼び出し
                        $this->handle_service->handle($this->debug_num, $this->manage_min_id, $attributes);
                        // 自身のオンメモリに、継続処理フラグおよび固有の判別情報を付加
                        $this->fifo_service = $this->fifo_service->setMetaInformationToInstance($attributes);
//                    } catch (\Exception $e) {
//                        return false;
//                    }
                    break;
                // 順序性を保つため継続処理するべきではない
                case 3:
                case 4:
                    echo "$this->debug_num: [NG] ",$attributes['id']." - ".$attributes['customer']
                            . " :It should not be continued ... \n";
                    break;
                // 取り出したジョブが一致しない(sqs jobs <> manage table jobs)
                case 5:
                    echo "$this->debug_num: [NG] manage_id $this->manage_min_id <> sqs_id "
                        . $attributes['id'] ."\n";
                    break;
                // キューが空、または実行すべきジョブがない
                case 6:
                    echo "$this->debug_num: [NG] job is empty ... \n";
                    sleep(2);
                    break;
                // 謎のエラー
                default:
                    echo "$this->debug_num: [???] \n";
                    break;
            }
            $this->debug_num += 1;
            sleep(1);
        }
    }
}
