<?php

namespace App\Src\Aws\Api;

use Aws\Sqs\SqsClient;

/**
 * AWS SQS API 接続クラス
 */
class SqsApiService
{
    /** @var static  */
    private $client;

    /**
     * Create a new command instance.
     *
     */
    function __construct()
    {
        $config = [
            'profile' => 'default',
            'version' => 'latest',
            'region' => 'ap-northeast-1'
        ];
        $this->client = SqsClient::factory($config);
    }

    /**
     * 指定したキューのエンドポイント名を取得
     * @param   string $queue_name  SQSキュー名
     * @return  string $queueUrl
     */
    public function getQueueUrl($queue_name)
    {
        return $this->client->getQueueUrl([
            'QueueName' => $queue_name
        ]);
    }

    /**
     * 指定したキューからメッセージを取得
     *
     * @param $queue_url
     * @return \Aws\Result
     */
    public function receiveMessage($queue_url)
    {
        return $this->client->receiveMessage([
            'QueueUrl' => $queue_url['QueueUrl'],
            'MessageAttributeNames' => ['All'],  // メッセージ属性を取得
        ]);
    }

    /**
     * 実行済みジョブをSQSキューから削除
     *
     * @param $queue_url
     * @param $result
     * @return \Aws\Result
     */
    public function deleteJob($queue_url, $result)
    {
        return $this->client->deleteMessage([
            'QueueUrl' => $queue_url['QueueUrl'],
            'ReceiptHandle' => $result['Messages'][0]['ReceiptHandle'],
            'Entries' => [
                [
                    'Id' => '1',
                ],
            ],
        ]);
    }

    /**
     * 指定したキューにメッセージを格納
     *
     * @param $queue_url
     * @param $next_id
     * @param $job_id
     * @param $job_name
     * @param $str
     * @param $customer
     * @param $time
     * @return \Aws\Result
     */
    public function sendMessage($queue_url, $next_id, $job_id, $job_name, $str, $customer, $time)
    {
        return $this->client->sendMessageBatch([
            'QueueUrl' => $queue_url['QueueUrl'],
            'Entries' => [
                [
                    'Id' => '1',
                    'MessageBody' => $str,
                    'MessageAttributes' => [
                        'id' => [
                            'StringValue' => $next_id,
                            'DataType' => 'Number',
                        ],
                        'job_id' => [
                            'StringValue' => $job_id,
                            'DataType' => 'Number',
                        ],
                        'job_name' => [
                            'StringValue' => $job_name,
                            'DataType' => 'String',
                        ],
                        'customer' => [
                            'StringValue' => $customer,
                            'DataType' => 'String',
                        ],
                        'created_at' => [
                            'StringValue' => $time,
                            'DataType' => 'String',
                        ],
                    ],
                ],
            ],
        ]);
    }

    /**
     * @param $queue_url
     * @return \Aws\Result
     */
    public function purgeQueue($queue_url)
    {
        return $this->client->purgeQueue([
            'QueueUrl' => $queue_url,
        ]);
    }
}
