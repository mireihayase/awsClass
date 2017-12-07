<?php

namespace App\Src\Queue\Controllers;

use Aws\sqs\SqsClient;
use App\Src\Queue\Models\SqsDbStatusCheck;
use App\Src\Queue\Models\SqsDbEpos;
use App\Src\Aws\Api\SqsApiService;
use App\Src\Queue\Utils\SqsCollection;

/**
 * Kaonavi queueing service テスト用ジョブ投入クラス
 */
class SqsClientController
{
    /** @var  String queue_name */
    protected $queue_name;

    /** @var null|static  */
    private $client   = null;

    /**
     * SqsClientController constructor.
     */
    function __construct()
    {
	    $config = [
	        'profile' => 'default',
	        'version' => 'latest',
	        'region'  => 'ap-northeast-1'
        ];
	    $this->client = SqsClient::factory($config);
    }

    /**
     * 管理用テーブル初期化
     *
     * @return mixed
     */
    public function initData()
    {
        $dbStatusCheck = new SqsDbStatusCheck();
        $result = $dbStatusCheck->truncateTable();
        if ($result) {
            print $result.PHP_EOL;
        }
        return $result;
    }

    /**
     * テスト用ジョブ投入(AWS SQS + Manage Table)
     *
     */
    public function run()
    {
        // Create instance
        $sqs = new SqsClientController();
        $dbStatusCheck = new SqsDbStatusCheck();
        $dbEpos = new SqsDbEpos();
        $sqsApiService = new SqsApiService();
        $sqsCollection = new SqsCollection();

        // 疑似ジョブタスク定義
        $attributes = [
            [ 'job_id' => 1, 'job_name' => 'hatsurei', 'customer' => 'eig'],
        ];
/*
                $attributes = [
                    [ 'job_id' => 1, 'job_name' => 'hatsurei', 'customer' => 'vyg'],
                    [ 'job_id' => 2, 'job_name' => 'search_optimize_data', 'customer' => 'hde'],
                    [ 'job_id' => 3, 'job_name' => 'sr_create_ledgersheet', 'customer' => 'tom'],
                    [ 'job_id' => 4, 'job_name' => 'output_file', 'customer' => 'eig'],
                    [ 'job_id' => 5, 'job_name' => 'calculation_continuous_age', 'customer' => 'don'],
                    [ 'job_id' => 6, 'job_name' => 'replacement_mem_csv', 'customer' => 'san'],
                    [ 'job_id' => 6, 'job_name' => 'replacement_mem_csv', 'customer' => 'san'],
                    [ 'job_id' => 7, 'job_name' => 'replacement_voice_csv', 'customer' => 'san'],
                    [ 'job_id' => 7, 'job_name' => 'replacement_voice_csv', 'customer' => 'rns'],
                    [ 'job_id' => 8, 'job_name' => 'upload_photos', 'customer' => 'rns'],
                    [ 'job_id' => 8, 'job_name' => 'upload_photos', 'customer' => 'rns'],
                    [ 'job_id' => 8, 'job_name' => 'upload_photos', 'customer' => 'tom'],
                    [ 'job_id' => 8, 'job_name' => 'upload_photos', 'customer' => 'tom'],
                    [ 'job_id' => 8, 'job_name' => 'upload_photos', 'customer' => 'tom'],
                    [ 'job_id' => 9, 'job_name' => 'manege_work_a', 'customer' => 'tom'],
                    [ 'job_id' => 9, 'job_name' => 'manege_work_a', 'customer' => 'tcx'],
                    [ 'job_id' => 4, 'job_name' => 'output_file', 'customer' => 'don'],
                    [ 'job_id' => 4, 'job_name' => 'output_file', 'customer' => 'don'],
                    [ 'job_id' => 7, 'job_name' => 'replacement_voice_csv', 'customer' => 'don'],
                    [ 'job_id' => 10, 'job_name' => 'manege_work_b', 'customer' => 'don'],
                    [ 'job_id' => 10, 'job_name' => 'manege_work_b', 'customer' => 'don'],
                    [ 'job_id' => 11, 'job_name' => 'manege_work_c', 'customer' => 'rns'],
                    [ 'job_id' => 11, 'job_name' => 'manege_work_c', 'customer' => 'rns'],
                    [ 'job_id' => 11, 'job_name' => 'manege_work_c', 'customer' => 'rns'],
                    [ 'job_id' => 12, 'job_name' => 'manege_work_d', 'customer' => 'tom'],
                    [ 'job_id' => 12, 'job_name' => 'manege_work_d', 'customer' => 'tom'],
                    [ 'job_id' => 12, 'job_name' => 'manege_work_d', 'customer' => 'tom'],
                    [ 'job_id' => 13, 'job_name' => 'manege_work_e', 'customer' => 'eig'],
                    [ 'job_id' => 14, 'job_name' => 'manege_work_f', 'customer' => 'vrs'],
                    [ 'job_id' => 10, 'job_name' => 'manege_work_b', 'customer' => 'don'],
                    [ 'job_id' => 10, 'job_name' => 'manege_work_b', 'customer' => 'don'],
                    [ 'job_id' => 10, 'job_name' => 'manege_work_b', 'customer' => 'don'],
                    [ 'job_id' => 10, 'job_name' => 'manege_work_b', 'customer' => 'don'],
                    [ 'job_id' => 15, 'job_name' => 'manege_work_g', 'customer' => 'rns'],
                    [ 'job_id' => 16, 'job_name' => 'manege_work_h', 'customer' => 'vyg']
                ];
        */

        // 疑似ジョブタスクを管理テーブルに登録
        foreach ($attributes as $value)
        {
            // ジョブタスクを取り出し
            $job_id  = $value['job_id'];
            $job_name = $value['job_name'];
            $customer    = $value['customer'];

            // 最後尾にいるジョブの位置を特定
            $pos = $dbStatusCheck->showTailJob();

            // 現在の時刻を取得
            $time = \App\Utils\CalcUtil::formatDate(new \Carbon\Carbon(), 'Y-m-d H:i:s');

            //指定したキューのエンドポイント名を取得
            $queueUrl = $sqsApiService->getQueueUrl(env('QUEUE_NAME'));

            // ジョブ管理レコードを追加
            $dbStatusCheck->createManageRecord($job_id, $job_name, $customer, $time);

            // SQSへジョブをエンキュー
            $sqsApiService->sendMessage(
                $queueUrl,
                $pos[0],
                $job_id,
                $job_name,
                'hoge2',   // <-- ジョブ名を指定
                $customer,
                $time
            );

#            // エンキューのポジションを1つずらす
#            $dbEpos->shiftTheFifoPosition($pos[1]);
        }
    }
}
