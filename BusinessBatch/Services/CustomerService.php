<?php

namespace App\Src\BusinessBatch\Services;

use App\Src\BusinessBatch\Models\Customer;
use \DB;

/**
 * 共有用 Common DB 操作サービスクラス
 */
class CustomerService
{
    function __construct()
    {
    }

    /**
     * 現在有効な全てのカスタマーコードを返す
     *
     * @return array
     */
    public function getAllCustomerCode()
    {
        try {
#        DB::enableQueryLog();          # クエリの中身を確認[デバッグ用]
            $customers = Customer::on('commondb')
                ->whereRaw('is_active = ?', array(1))
                ->orderBy('id')
                ->get();
#        var_dump(DB::getQueryLog());   # クエリの中身を確認[デバッグ用]
        } catch (\Exception $e) {
            return false;
        }

        foreach ($customers as $customer) {
            $result[] = $customer->customer_code;
        }

        return $result;
    }

    /**
     * 新規顧客追加ロジック
     */
#    public function registCustomer()
#    {
#    }

}

