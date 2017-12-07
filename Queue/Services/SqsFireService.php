<?php

namespace App\Src\Queue\Services;

use App\Src\Queue\Controllers\SqsClientController;
use App\Src\Aws\Api\SqsApiService;
use App\Exceptions\RuntimeException;
use App\Http\Controllers\Controller;
use Illuminate\Queue\CallQueuedHandler;

/**
 * キュー基盤# ジョブ実行クラス
 */
class SqsFireService
{
    /** @var  SqsApiService */
    private $api_service;

    /** @var ¥Aws¥Result  */
    protected $fire_responce;
    protected $delete_responce;

    /**
     * SqsFireTheJobService constructor.
     * @param SqsApiService $sqs_api_service
     */
    function __construct(SqsApiService $sqs_api_service)
    {
        $this->api_service = $sqs_api_service;
    }

    /**
     *  ジョブを実行
     *
     *   @param  int      $debug_num      デバッグ用
     *   @param  string   $manage_min_id  現在処理中のシーケンス番号
     *   @param  mixed    $attributes     属性情報、実行コード
     *   @return void
     *   @throws RuntimeException
     */
    public function fire($debug_num, $manage_min_id, $attributes)
    {
        echo "$debug_num: [OK] $manage_min_id - " . $attributes['customer']    // デバッグ用
            . " - " . $attributes['one_time_before_id']                        // デバッグ用
            . " - " . $attributes['one_time_before_customer'] . "\n";        // デバッグ用
        try {
            $command = $this->resolveCommand($attributes);          // ジョブメッセージのペイロードを取得
            $this->fire_responce = \Bus::dispatchNow($command);   // ジョブクラスの再生処理
            echo("============================== dispatchNow!!!").PHP_EOL;  // デバッグ用
        } catch (\Exception $e) {
                \Log::error('ジョブのdispathエラー：'.$e->getMessage());
                throw new RuntimeException($e->getMessage(), $e->getCode(), $e->getPrevious());
        }
    }

    /**
     * ジョブメッセージのペイロードを取得
     *
     * @param array $attributes
     * @return mixed
     */
    private function resolveCommand(array $attributes)
    {
        $payload = json_decode($attributes['Body'], true);         // メッセージ本文をデコード
        $command = unserialize($payload['data']['command']);      // 実行コードをアンシリアル化
        if (method_exists($command, 'failed')) $command->failed();
        return $command;
    }
}
