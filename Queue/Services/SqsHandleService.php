<?php

namespace App\Src\Queue\Services;

use App\Src\Queue\Controllers\SqsClientController;
use App\Src\Aws\Api\SqsApiService;
use App\Exceptions\RuntimeException;
use App\Http\Controllers\Controller;
use App\Src\Queue\Services\SqsFireService;
use App\Src\Queue\Models\SqsDbStatusCheck;
use App\Src\Queue\Models\SqsDbDpos;
use Illuminate\Queue\CallQueuedHandler;
use DB;
use Log;

/**
 * キュー基盤# ジョブ実行ハンドルクラス
 */
class SqsHandleService
{
    /** @var  SqsFireTheJobService */
    private $fire_the_job_service;

    /** @var  SqsDbStatusCheck */
    private $db_status_check;

    /** @var  SqsApiService */
    private $api_service;

    /** @var  SqsDbDpos */
    private $db_d_pos;

    /** @var ¥Aws¥Result  */
    private $fire_responce;

    /**
     * SqsHandleJobService constructor.
     * @param \App\Src\Queue\Services\SqsFireService $fire_service
     * @param SqsDbStatusCheck $db_status_check
     * @param SqsApiService $api_service
     * @param SqsDbDpos $db_d_pos
     */
    function __construct(
        SqsFireService $fire_service,
        SqsDbStatusCheck $db_status_check,
        SqsApiService $api_service,
        SqsDbDpos $db_d_pos
    )
    {
        $this->fire_service = $fire_service;
        $this->db_status_check = $db_status_check;
        $this->api_service = $api_service;
        $this->db_d_pos = $db_d_pos;
    }

    /**
     * ジョブ実行をハンドルする
     *
     * @param $debug_num int
     * @param $manage_min_id int
     * @param $attributes array
     * @return bool
     */
    public function handle($debug_num, $manage_min_id, $attributes)
    {
        try {
            // 1. ジョブを実行
            $this->fire_service->fire($debug_num, $manage_min_id, $attributes);
        } catch (\Exception $e) {
            /**
             * ジョブ実行失敗時のエラー処理
             */
            $time = \App\Utils\CalcUtil::formatDate(new \Carbon\Carbon(), 'Y-m-d H:i:s');  // 現在の時刻を取得
            DB::transaction(function() use($manage_min_id, $time, $debug_num, $attributes) {
                // 実行状態を更新 (status:running->failed)、ソフトデリート(is_completed:0->1)
                $this->db_status_check->createCompletedFlag($manage_min_id, $time, SqsDbStatusCheck::STATUS_FAILED);
                // 実行継続権限を剥奪 (1 -> 0 [同一連番カスタマーにおいて全て対象])
                $this->db_status_check->setAuthority($manage_min_id + 1, $attributes['customer'], false);
            });
            // SQSキュー# 実行エラージョブを削除
            $this->api_service->deleteJob(
                $attributes['QueueUrl'],
                $attributes['ReceiptHandle']
            );
            Log::error('ジョブ実行エラー：' . $e->getMessage());
            throw new RuntimeException(
                $e->getMessage(),
                $e->getCode(),
                $e->getPrevious()
            );
        }

        $time = \App\Utils\CalcUtil::formatDate(new \Carbon\Carbon(), 'Y-m-d H:i:s');  // 現在の時刻を取得
        DB::transaction(function() use ($debug_num, $manage_min_id, $attributes, $time) {
            // 2. 管理テーブル# 実行済みジョブレコードを削除（ソフトデリート）
            $this->db_status_check->createCompletedFlag($manage_min_id, $time, "completed");
            // 3. デキューのFIFOポジションを1つインクリメント
            $this->db_d_pos->shiftTheFifoPosition($manage_min_id, 'd_pos');
        });
        // 4. SQSキュー# 実行済みジョブを削除
        $this->api_service->deleteJob(
            $attributes['QueueUrl'],
            $attributes['ReceiptHandle']
        );
    }
}
