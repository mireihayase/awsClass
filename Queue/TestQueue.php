<?php

namespace App\Src\Queue;
use App\Src\Aws\Api\SqsApiService;
use Illuminate\Queue\Jobs\SqsJob;
use Illuminate\Queue\CallQueuedHandler;
use Illuminate\Queue\Worker;
use Illuminate\Queue\QueueManager;
use Illuminate\Container\Container;
use Illuminate\Queue\Connectors\SqsConnector;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Foundation\Application;
use Illuminate\Queue\Capsule\Manager;
use Aws\Sqs\SqsClient;
use App\Src\Queue\Services\SqsJobService;
use App\Http\Controllers\Controller;
use Illuminate\Queue\Console\WorkCommand;
use Illuminate\Queue\SqsQueue;
use Illuminate\Console\Command;
use App\Jobs\UpdateKaonaviDateJob;
use App\Src\Queue\Services\SqsEnqueueService;
use App\Src\Queue\Services\SqsFireService;
use Illuminate\Contracts\Queue\Job;
use Prophecy\Call\Call;
use Queue;
use Bus;
use DB;

class TestQueue
{
    public $instance;
    public $container;
    public $dispatcher;
    protected $manage_min_seq;
    protected $user;

    public function run()
    {
        $client = new SqsApiService();
        $queue_url = $client->getQueueUrl(env('QUEUE_NAME'));
        $result = $client->purgeQueue($queue_url['QueueUrl']);
/*
        $seq  = 12;
        $job_id = 100;
        $job_name = 'test100';
        $user = 'tom';
        $time = '2016-03-14';
        $pos = 100;
        $manage_min_seq = 5;
        $updated_at = '2016-03-14';
*/
#        DB::transaction(function () use ($job_id, $job_name, $user, $time) {
#            $result = DB::connection('sqsdb')
#                ->table('sqs_status_check')
#                ->orderBy('seq', 'desc')
#                ->select(['seq'])
#                ->first();
#        });
        var_dump($result);

        /*
        foreach ($result as $seq_key => $user_key) {
            $this->manage_min_seq = $seq_key;
            $this->user = $user_key;
        }
        */

        /*
        app()->bind(Container::class, function () {
            return new Container();
        });
        $this->container = app()->make(Container::class);

        $config = array(
            'profile' => 'default',
            'version' => 'latest',
            'region' => 'ap-northeast-1');
        $sqsClient = SqsClient::factory($config);

        $sqs = new SqsApiService();
        // 指定したキューのエンドポイント名を取得
        $queueUrl = $sqs->getQueueUrl('TestQueue');
        // 指定したキューからメッセージを取得
        $result = $sqs->receiveMessage($queueUrl);
        // メッセージを取り出す
        $deleteResponce = $sqsClient->deleteMessage(array('QueueUrl' => $queueUrl['QueueUrl'],
            'ReceiptHandle' => $result['Messages'][0]['ReceiptHandle'],
            'Entries' => array(
                array(
                    'Id' => '1',
                ),
            ),
        ));


        print $deleteResponce['@metadata']['statusCode'];
        */

#        $messages   = $result->get('Messages');
#        $attributes['Body'] = $messages[0]['Body'];

        // メッセージ本文をデコードする
#       $payload = json_decode($attributes['Body'], true);
#        list($class, $method) = explode('@', $payload['job']);
        // メッセージ本文をデコードする
#        $command = unserialize($payload['data']['command']);
#        if (method_exists($command, 'failed')) {
#            $command->failed();
#        }
#        var_dump($payload_unserial);
#        Bus::dispatchNow($command);
#        $sqs->deleteJob($queueUrl, $result);
#        Queue::pop();


        /*
        $config = array(
            'driver' => 'sqs',
            'key' => 'AKIAJUOV4NGVFFNHBQDQ',
            'secret' => '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',
            'profile' => 'default',
            'queue' => 'https://sqs.ap-northeast-1.amazonaws.com/844603484513/TestQueue',
            'version' => 'latest',
            'region' => 'ap-northeast-1');
        $sqsClient = SqsClient::factory($config);

        $sqsJob = new SqsJob(
            $this->container,
            $sqsClient,
            'https://sqs.ap-northeast-1.amazonaws.com/844603484513/TestQueue',
            $attributes
        );
        */

        /*
        echo "##### attributes['Body']".PHP_EOL;
        var_dump($attributes);

        // ジョブメッセージをJSONデコード
        echo "##### json_decode attributes['Body']".PHP_EOL;
        $payload = json_decode($attributes['Body'], true);
        var_dump($payload);

        echo "##### payload['job']".PHP_EOL;
        var_dump($payload['job']);

        echo "##### payload['data']".PHP_EOL;
        $command = $payload['data'];
        var_dump($command);

        $manager = new Manager();
        $queueManager = $manager->getQueueManager();
        var_dump($queueManager);
        $sqsJob = new SqsJob(
            $queueManager,
            $sqsClient,
            'https://sqs.ap-northeast-1.amazonaws.com/844603484513/TestQueue',
            $attributes['Body']
        );
    */

        #$sqsJobService = new SqsJobService();
        #$sqsJobService->resolveAndFire($payload);
        #$manager = new Manager();
        #$container = $manager->getContainer();

        /*
        $app = app();
        $queueManager = new QueueManager(app());
        $queueManager->connected();
        $con = $queueManager->connection('sqs');
        */


#        $job = $sqsQueue->pop();
#        var_dump($job);

#        $manager->addConnection($config);

#        $con = $manager->connection();
#        $worker = new Worker($manager->getQueueManager());

#        $worker->process($con, $attributes);

#        $sqsJob = new SqsJob($queueManager, $sqsClient, 'TestQueue', $attributes);
#        $sqsJob->fire();

#        $worker->
#        $sqsJob = new SqsJob();

        /*
        $call = new Illuminate\Queue\CallQueuedHandler();
        $app = new Application();
        $queueManager = new QueueManager($app);
        $worker = new Worker($queueManager);
        */
    }
}
