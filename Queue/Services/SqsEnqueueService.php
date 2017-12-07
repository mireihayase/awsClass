<?php

namespace App\Src\Queue\Services;

use Aws\sqs\SqsClient;
use App\Src\Queue\Models\SqsDbStatusCheck;
use App\Src\Queue\Models\SqsDbEpos;
use App\Src\Queue\Models\SqsDbDpos;
use App\Src\Aws\Api\SqsApiService;
use App\Exceptions\RuntimeException;
use Log;

/**
 * キュー基盤# ジョブ投入用クラス
 */
class SqsEnqueueService
{
    /** @var mixed instances  */
    private $db_status_check;
    private $db_e_pos;
    private $db_d_pos;
    private $sqs_api_service;
    private $message;

    /** @var string */
    private $queue;
    private $job_id;
    private $job_name;
    private $user;
    private $queue_url;
    private $pos;
    private $time;
    private $job;

    /**
     * SqsEnqueueService constructor.
     * @param $queue
     * @param $job_id
     * @param $job_name
     * @param $user
     * @param $job
     */
    function __construct($queue, $job_id, $job_name, $user, $job)
    {
        // Set property
        $this->queue = $queue;
        $this->job_id = $job_id;
        $this->job_name = $job_name;
        $this->user = $user;
        $this->job = $job;
        // Set instances
        $this->db_status_check = new SqsDbStatusCheck();
        $this->sqs_api_service = new SqsApiService();
        $this->db_e_pos = new SqsDbEpos();
        $this->db_d_pos = new SqsDbDpos();
        // Set aws credentials
        $config = [
            'profile' => 'default',
            'version' => 'latest',
            'region'  => 'ap-northeast-1'
        ];
        $this->client = SqsClient::factory($config);
    }

    /**
     * AWS SQSへジョブを投入する
     *
     * @return mixed
     * @throws RuntimeException
     */
    public function pushQueue()
    {
            // 最後尾にいるジョブの位置を特定
            $this->pos = $this->db_status_check->showTailJob();
            // 現在の時刻を取得
            $this->time = \App\Utils\CalcUtil::formatDate(new \Carbon\Carbon(), 'Y-m-d H:i:s');
            //指定したキューのエンドポイント名を取得
            $this->queue_url = $this->sqs_api_service->getQueueUrl(env('QUEUE_NAME'));
            // ジョブ管理レコードを追加
            $insert_id = $this->createManageRecord();
        try {
            // ジョブをシリアライズ化、バイトストリームにエンコード処理
            $this->serialzeDecord();
            // SQSへジョブをエンキュー
            $this->sendMessage();
        } catch (\Exception $e) {
            /**
             * ジョブ投入失敗時のエラー処理
             */
            // 管理レコードを削除
            $this->db_status_check->deleteLow($insert_id);
            Log::error('SQS enqeue error：'.$e->getMessage());
            throw new RuntimeException($e->getMessage(), $e->getCode(), $e->getPrevious());
        }
        return $insert_id;
    }

    /**
     * ジョブをシリアライズ化したのち、バイトストリームにエンコード処理
     *
     * @return void
     */
    private function serialzeDecord()
    {
        $this->message = json_encode([
            'job' => 'Illuminate\Queue\CallQueuedHandler@call',
            'data' => ['command' => serialize(clone $this->job)],
        ]);
    }

    /**
     * SQSへジョブをエンキュー
     *
     * @return mixed
     */
    private function sendMessage()
    {
        return $this->sqs_api_service->sendMessage(
            $this->queue_url,
            $this->pos[0],
            $this->job_id,
            $this->job_name,
            $this->message,
            $this->user,
            $this->time
        );
    }

    /**
     * ジョブ管理レコードを追加
     *
     * @return int 管理レコード挿入時に自動採番されたI
     */
    private function createManageRecord()
    {
        return $this->db_status_check->createManageRecord(
            $this->job_id,
            $this->job_name,
            $this->user,
            $this->time
        );
    }
}
