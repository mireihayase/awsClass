<?php

namespace App\Src\Queue\Services;

use App\Src\Aws\Api\SqsApiService;
use App\Src\Queue\Models\SqsDbService;
use App\Src\Queue\Models\SqsDbStatusCheck;
use DB;
use Log;

/**
 * キュー基盤# FIFO処理・ジョブ検索サービスクラス
 */
class SqsFifoService
{
    /** @var String  */
    private $regex = "([678a-z])+?";

    /** @var int  */
    private $one_time_before_id;

    /** @var string  */
    private $one_time_before_customer;

    /** @var bool 同一カスタマー継続処理判定フラグ */
    private $ongoing_flg;

    /** @var bool ジョブ実行権判定フラグ */
    private $execute_flg;

    /** @var SqsApiService  */
    private $api_service;

    /** @var SqsDbStatusCheck  */
    private $db_status_check;

    /**  @var string */
    const NEXT_SEQ = 'next';

    /**
     * SqsFifoService constructor.
     *
     * @param $one_time_before_id
     * @param $one_time_before_customer
     * @param $ongoing_flg
     * @param $exec_flg
     */
    function __construct(
        $one_time_before_id,
        $one_time_before_customer,
        $ongoing_flg,
        $exec_flg
    )
    {
        $this->setOneTimeBeforeId($one_time_before_id);
        $this->setOneTimeBeforeCustomer($one_time_before_customer);
        $this->setOngoingFlg($ongoing_flg);
        $this->setExecFlg($exec_flg);
        $this->api_service = new sqsApiService();
        $this->db_status_check = new sqsDbStatusCheck();
    }

    /**
     * [オンメモリで利用] 前回、Worker自身が実行したカスタマー名を記憶
     * @param  string $one_time_before_customer
     * @return void
     */
    public function setOneTimeBeforeCustomer($one_time_before_customer)
    {
        $this->one_time_before_customer = $one_time_before_customer;
    }

    /**
     * [オンメモリで利用] 前回、Worker自身が実行したカスタマー名を取得
     * @return mixed  $one_time_before_customer
     */
    public function getOneTimeBeforeCustomer()
    {
        return $this->one_time_before_customer;
    }

    /**
     * [オンメモリで利用] 前回、Worker自身が実行したIDを記憶
     * @param  int $one_time_before_id
     * @return void
     */
    public function setOneTimeBeforeId($one_time_before_id)
    {
        $this->one_time_before_id = $one_time_before_id;
    }

    /**
     * [オンメモリで利用] 前回、Worker自身が実行したIDを取得
     * @return mixed  $one_time_before_id
     */
    public function getOneTimeBeforeId()
    {
        return $this->one_time_before_id;
    }

    /**
     * [オンメモリで利用] Worker自身の継続処理判定フラグを記憶
     * @param  bool  $ongoing_flg
     * @return void
     */
    public function setOngoingFlg($ongoing_flg)
    {
        $this->ongoing_flg = $ongoing_flg;
    }

    /**
     * [オンメモリで利用] Worker自身の、同一カスタマー継続処理判定フラグを記憶
     * @return mixed $ongoing_flg
     */
    public function getOngoingFlg()
    {
        return $this->ongoing_flg;
    }

    /**
     * [オンメモリで利用] Worker自身の、ジョブ実行権判定フラグを記憶
     * @param  bool  $exec_flg
     * @return void
     */
    public function setExecFlg($exec_flg)
    {
        $this->execute_flg = $exec_flg;
    }

    /**
     * [オンメモリで利用] Worker自身の実行権判定フラグを記憶
     * @return mixed $exec_flg
     */
    public function getExecFlg()
    {
        return $this->execute_flg;
    }

    /**
     * 正規表現のチェック用定義文字列
     * @return String $regex
     */
    public function getRegex()
    {
        return $this->regex;
    }

    /**
     * ワーカーそれぞれのインスタンスに、継続処理フラグおよび固有の判別情報を付加
     * @param   mixed $attributes  現在処理中のジョブの詳細情報
     * @return SqsFifoService
     */
    public function setMetaInformationToInstance($attributes)
    {
        // 継続処理中
        if ($attributes['switch_flg'] == 0 || $attributes['switch_flg'] == 1) {
            return new SqsFifoService(
                $attributes['id'],
                $attributes['customer'],
                $attributes['next_job_flg'],
                true
            );
        }
        // 継続処理中ではないが、ジョブ実行権はある場合
        if ($attributes['switch_flg'] == 2) {
            return new SqsFifoService(
                $attributes['id'],
                $attributes['customer'],
                false,
                false
            );
        }
    }

    /**
     * ジョブメッセージを検索／デキュー
     *
     * @param $manage_min_id
     * @param $customer
     * @return mixed
     * @throws RuntimeException
     */
    public function searchJob($manage_min_id, $customer)
    {

        $queue_url = $this->api_service->getQueueUrl(env('QUEUE_NAME'));  // 指定したキューのエンドポイント名を取得
        $result   = $this->api_service->receiveMessage($queue_url);         // 指定したキューからメッセージを取得
        $messages   = $result->get('Messages');                             // メッセージから、ジョブを取り出す
        $attributes['Body']  = $messages[0]['Body'];                       // メッセージから、ジョブデータを取り出す

        // 属性情報(id, customer, job_id)、付属情報を取り出す
        $attributes['id'] = $messages[0]['MessageAttributes']['id']['StringValue'];
        $attributes['customer'] = $messages[0]['MessageAttributes']['customer']['StringValue'];
        $attributes['job_id'] = $messages[0]['MessageAttributes']['job_id']['StringValue'];
        $attributes['QueueUrl'] = $queue_url;
        $attributes['ReceiptHandle'] = $result;

        // 各Workerがオンメモリに格納された各フラグを取得し、それぞれが状態管理
        $this->one_time_before_customer = $this->getOneTimeBeforeCustomer();
        $this->one_time_before_id  = $this->getOneTimeBeforeId();
        $this->ongoing_flg     = $this->getOngoingFlg();

        // ジョブがキューに存在し、且つ実行するべき管理レコードがあるか？
        if(isset($messages) && ! $manage_min_id == null) {
            // idがマッチ(SQS.id == manage_table.id)しているか？
            if ($attributes['id'] == $manage_min_id) {
                /** 実行順序の一貫性のためにFIFOをたもつ **/
                // カスタマーにおいて、未完了ジョブの最小IDを取得
                $minimum_id = $this->db_status_check->getMinIdByCustomerJob($customer);
                // 未完了ジョブの最小IDより小さければ何もしない
                if ($manage_min_id > $minimum_id['id']) {
                    echo "'=====debug 4" . PHP_EOL;
                    $attributes['switch_flg'] = 4;  // デバッグ用分岐フラグ
                    return $attributes;
                } else {
                    // 同一カスタマーにおいて、idは連番の先頭か？（例.現在:5、次:6、次;7 ...）
                    if ($this->db_status_check->isSerialNumId($manage_min_id, $customer, self::NEXT_SEQ)) {
                        DB::transaction(function () use ($manage_min_id, $customer) {
                            // 同一カスタマーの連番のジョブ全ての実行権を取得
                            $this->db_status_check->setAuthority($manage_min_id, $customer);
                            // ジョブ実行状態のステータスを更新(ready -> running)
                            $this->db_status_check->updateStatus(
                                $manage_min_id, $customer, SqsDbStatusCheck::STATUS_RUNNING);
                        });
                        // 前回実行したカスタマーと同じで且つ、前回実行したIDと連番か？
                        if ($customer === $this->one_time_before_customer &&
                            $manage_min_id == $this->one_time_before_id + 1
                        ) {
                            echo "'=====debug 1" . PHP_EOL;

                            // ジョブ実行状態のステータスを更新(ready -> running)
                            $this->db_status_check->updateStatus($manage_min_id, $customer, SqsDbStatusCheck::STATUS_RUNNING);
                            $this->setExecFlg(true);

                            $attributes['switch_flg'] = 1;  // デバッグ用分岐フラグ
                            // 実行済みジョブのID・カスタマー名をオンメモリに格納
                            $attributes['one_time_before_id'] = $this->getOneTimeBeforeId();
                            $attributes['one_time_before_customer'] = $this->getOneTimeBeforeCustomer();

                            // 次IDのジョブが存在する？ (存在する:true , 存在しない:false)
                            $attributes['next_job_flg'] = $this->db_status_check
                                ->isSerialNumId($manage_min_id, $customer, self::NEXT_SEQ);
                            return $attributes;
                        // 同一カスタマーにおいて、連番開始のジョブか？
                        } elseif ($this->getOngoingFlg() === false &&
                            $this->db_status_check->isOngoingJob($manage_min_id, $customer)
                        ) {
                            echo "'=====debug 0" . PHP_EOL;

                            // ジョブ実行状態のステータスを更新(ready -> running)
                            $this->db_status_check->updateStatus(
                                $manage_min_id, $customer, SqsDbStatusCheck::STATUS_RUNNING);
                            $this->setExecFlg(true);

                            $attributes['switch_flg'] = 0;  // デバッグ用分岐フラグ
                            $attributes['one_time_before_id'] = $this->getOneTimeBeforeId();
                            $attributes['one_time_before_customer'] = $this->getOneTimeBeforeCustomer();

                            // 次IDのジョブが存在するか確認 (存在する:true , 存在しない:false)
                            $attributes['next_job_flg'] = $this->db_status_check
                                ->isSerialNumId($manage_min_id, $customer, self::NEXT_SEQ);
                            return $attributes;
                        // 前回実行したカスタマーでなければ継続すべきではない
                        } else {
                            echo "'=====debug 3" . PHP_EOL;
                            $attributes['switch_flg'] = 3;  // デバッグ用分岐フラグ
                            return $attributes;
                        }
                    // ジョブが(1つ/1カスタマー)しかないので、継続処理を考慮せずに実行
                    } else {
                        echo "'=====debug 2" . PHP_EOL;

                        // ジョブ実行状態のステータスを更新(ready -> running)
                        $this->db_status_check->updateStatus($manage_min_id, $customer, SqsDbStatusCheck::STATUS_RUNNING);
                        $this->setExecFlg(true);

                        $attributes['switch_flg'] = 2;  // デバッグ用分岐フラグ
                        $attributes['one_time_before_id'] = $this->getOneTimeBeforeId();
                        $attributes['one_time_before_customer'] = $this->getOneTimeBeforeCustomer();
                        return $attributes;
                    }
                }
            // IDがアンマッチ(manage_table.id <> SQS.id)
            // キューから次のメッセージを取得
            } else {
                echo "'=====debug 5".PHP_EOL;
                $attributes['switch_flg'] = 5;  // デバッグ用分岐フラグ
                return $attributes;
            }
        // キューにメッセージがなければ、一定時間後に再びポーリング
        } else {
            echo "'=====debug 6".PHP_EOL;
            $attributes['switch_flg'] = 6;
            return $attributes;
        }
	}
}
