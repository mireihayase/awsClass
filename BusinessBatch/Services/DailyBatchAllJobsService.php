<?php

namespace App\Src\BusinessBatch\Services;
use \Artisan;

/**
 * 日次業務バッチ用サービスクラス
 */
class DailyBatchAllJobsService
{
    /**
     * @param string $customer
     */
    public function doAllJobs($customer)
    {
        self::doArtisanCommand($customer);
    }

    /**
     * 定義済みジョブ用アルチザンコマンドを実行
     * @param string $customer     カスタマーコード
     * @param array $batch_key_ids 有効なバッチID(ジョブID?）
     * @description カスタマー個別のジョブを実行。
     *                マスタジョブテーブルと、user.batch_definitionsをJoinしたい。
     */
    private static function doArtisanCommand($customer, $batch_key_ids = [])
    {

        // 顔写真取り込みバッチ
#        if ($batch_key_ids['import_face_images']) {
            Artisan::call("import_face_images", [
                'customer_code' => $customer,
            ]);
            echo "php artisan import_face_images $customer".PHP_EOL;
#        }

        // カオナビ日付更新バッチ
#        if ($batch_key_ids['update_kaonavi_date']) {
            Artisan::call("update_kaonavi_date", [
                'customer_code' => $customer,
            ]);
            echo "php artisan update_kaonavi_date $customer".PHP_EOL;
#        }

        // 勤続年数・年齢計算バッチ
#        if ($batch_key_ids['update_members']) {
            Artisan::call("update_members", [
                'customer_code' => $customer,
            ]);
            echo "php artisan update_members $customer".PHP_EOL;
#        }

        // XXXXXXXXXバッチ
        // ... バッチの数だけ差し込んでいく

        // XXXXXXXXXバッチ
        // ... バッチの数だけ差し込んでいく

        // XXXXXXXXXバッチ
        // ... バッチの数だけ差し込んでいく
    }
}
