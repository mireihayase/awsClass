<?php

namespace App\Src\BusinessBatch\Controllers;

use App\Src\BusinessBatch\Models\Customer;
use App\Src\BusinessBatch\Services\CustomerService;
use App\Src\BusinessBatch\Services\DailyBatchAllJobsService;
use App\Exceptions\RuntimeException;
use App\Http\Controllers\Controller;
use DB;
use Log;

/**
 * 日次業務バッチ用コントローラークラス
 */
class DailyBatchController extends Controller
{
    /** @var CustomerService */
    protected $customer_service;

    /** @var DailyBatchAllJobsService */
    protected $daily_batch_all_jobs_service;

    /**
     * DailyBatchController constructor.
     * @param CustomerService $customer_service
     * @param DailyBatchAllJobsService $daily_batch_all_jobs_service
     */
    function __construct(CustomerService $customer_service,
                         DailyBatchAllJobsService $daily_batch_all_jobs_service)
    {
        $this->customer_service = $customer_service;
        $this->daily_batch_all_jobs_service = $daily_batch_all_jobs_service;
    }

    /**
     * 全てのカスタマーの日次ジョブを実行
     *
     * @return void
     */
    public function run()
    {
        // カスタマーコード一覧を取得
        $customers = $this->customer_service->getAllCustomerCode();
        if (! $customers) {
            return false;
        }

        // カスタマーごとに順次日次ジョブを実行
        foreach ($customers as $customer) {
            $this->daily_batch_all_jobs_service->doAllJobs($customer);
        }
    }
}
