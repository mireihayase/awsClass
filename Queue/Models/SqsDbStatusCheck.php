<?php

namespace App\Src\Queue\Models;

use Illuminate\Database\Eloquent\Model;
use App\Src\Queue\Utils\SqsCollection;
use App\Exceptions\RuntimeException;
use DB;
use Log;

/**
 * キュー基盤# 管理用DBステータスチェックテーブル接続クラス
 */
class SqsDbStatusCheck extends Model
{
    /** @var string ジョブの実行状況ステータス */
    const STATUS_READY = 'ready';            // キュー投入済み。実行待ち中。
    const STATUS_RUNNING = 'running';       // ジョブ実行中。
    const STATUS_COMPLETED = 'completed';  // ジョブ実行完了。
    const STATUS_FAILED = 'failed';         // ジョブ異常終了。

    /** @var string ジョブの順序 */
    const CURRVAL = 'curr';
    const NEXTVAL = 'next';

    private $is_authority;

    /**
     * StdClassObject(QueryBuilderの返り値) -> Array に変換
     *
     * @param $result
     * @return mixed
     */
    protected function getObjectVars($result)
    {
        $new_result = null;
        if (!$result == null) $new_result['id'] = get_object_vars($result)['id'];
        return $new_result;
    }

    /**
     * @param $id
     * @return mixed
     */
    public static function showStatus($id)
    {
        $result = DB::connection('sqsdb')
            ->table('sqs_status_check')
            ->where('id', '=', $id)
            ->orderBy('customer', 'asc')
            ->orderBy('id', 'asc')
            ->select(['id', 'job_id', 'job_name', 'customer', 'is_completed', 'status'])
            ->first();
        return get_object_vars($result);
    }

    /**
     * 指定されたIDを1つ返す
     *
     * @param  int $id
     * @param  string $customer
     * @return bool   $flg
     */
    protected function showLows($id, $customer)
    {
        $result = DB::connection('sqsdb')
            ->table('sqs_status_check')
            ->where('customer', '=', "$customer")
            ->where('id', '=', $id)
            ->orderBy('customer', 'asc')
            ->orderBy('id', 'asc')
            ->select(['id'])
            ->value('id');
        return isset($result);
    }

    /**
     * 管理テーブルから、次ジョブのIDとカスタマーを取り出し
     *
     * @param $ongoing_flg bool  継続処理判定フラグ
     * @param $exec_flg    bool  実行権限判定フラグ
     * @param $next_id     int
     * @return mixed
     */
    public function showNextJob($ongoing_flg, $exec_flg, $next_id)
    {
        // カスタマージョブの継続処理中、且つ実行権限がある場合
        // IDのソート順で取り出す
        if ($ongoing_flg) {
#            echo "'============= 1.ongoing_flg: $ongoing_flg".PHP_EOL;
            return DB::connection('sqsdb')
                ->table('sqs_status_check')
                ->where('is_completed', '=', false)
                ->where('is_authority', '=', true)
                ->where('status', '=', self::STATUS_READY)
                ->where('id', '=', $next_id)
                ->orderBy('id', 'asc')
                ->select(['id', 'customer'])
                ->first();
        }
        // カスタマージョブが継続処理中ではないが、実行権限がある場合
        // IDのソート順、かつ継続処理中のIDは全て飛ばして取り出す
        if (!$ongoing_flg && $exec_flg) {
#            echo "'============= 2.!ongoing_flg: $ongoing_flg, exec_flg: $exec_flg".PHP_EOL;
            return DB::connection('sqsdb')
                ->table('sqs_status_check')
                ->where('is_completed', '=', false)
                ->where('is_authority', '=', false)
                ->where('status', '=', self::STATUS_READY)
                ->orderBy('id', 'asc')
                ->select(['id', 'customer'])
                ->first();
        }
        // カスタマージョブが継続処理中ではなく、且つ実行権限もない場合
        if (!$ongoing_flg && !$exec_flg) {
#                echo "'============= 3.!ongoing_flg: $ongoing_flg, !exec_flg: $exec_flg".PHP_EOL;
            return DB::connection('sqsdb')
                ->table('sqs_status_check')
                ->where('is_completed', '=', false)
                ->where('is_authority', '=', false)
                ->where('status', '=', self::STATUS_READY)
                ->orderBy('id', 'asc')
                ->select(['id', 'customer'])
                ->first();
        }
    }

    /**
     * 管理テーブルから、最後尾のジョブを取り出し
     * @return  array  SQL問い合わせ結果を返す
     */
    public function showTailJob()
    {
        $result = DB::connection('sqsdb')
            ->table('sqs_status_check')
            ->orderBy('id', 'desc')
            ->select(['id'])
            ->first();
        $max_seq = $this->getObjectVars($result);
        $pos[] = $next_pos = $max_seq['id'] + 1;
        $pos[] = $e_pos = $next_pos + 1;
        return $pos;
    }

    /**
     * 継続処理の開始判定（ongoing_flg:false, is_completed:0, is_authority:1）
     *
     * @param $id       int    ID
     * @param $customer string 顧客名
     * @return bool
     * @throws RuntimeException
     */
    public function isOngoingJob($id, $customer)
    {
        $result = DB::connection('sqsdb')
            ->table('sqs_status_check')
            ->where('is_completed', '=', false)
            ->where('is_authority', '=', true)
            ->where('id', '=', $id)
            ->where('customer', '=', $customer)
            ->orderBy('id', 'asc')
            ->select(['id', 'customer'])
            ->first();
        $flg = $this->getObjectVars($result);
        return isset($flg);
    }

    /**
     * 管理テーブルからIDの連番チェック
     *
     * @param  int $id ID
     * @param  string $customer 顧客名
     * @param  string $val 識別子
     * @return bool   $flg      SQL問い合わせ結果
     * @throws RuntimeException
     */
    public function isSerialNumId($id, $customer, $val)
    {
        // 取り出したジョブのIDは前回と連番か？
        if ($val === self::CURRVAL) {
            $currval = $id - 1;
            $result = DB::connection('sqsdb')
                ->table('sqs_status_check')
                ->where('customer', '=', $customer)
                ->where('id', '=', $currval)
                ->orderBy('customer', 'asc')
                ->orderBy('id', 'asc')
                ->select(['id', 'customer'])
                ->first();
        }
        // 次のIDのジョブは存在するか？
        if ($val === self::NEXTVAL) {
            $nextval = $id + 1;
            $result = DB::connection('sqsdb')
                ->table('sqs_status_check')
                ->where('customer', '=', $customer)
                ->where('id', '=', $nextval)
                ->orderBy('customer', 'asc')
                ->orderBy('id', 'asc')
                ->select(['id', 'customer'])
                ->first();
        }
        $flg = $this->getObjectVars($result);
        return isset($flg);
    }

    /**
     * 指定ユーザにおける、未完了ジョブの最小IDを取得
     *
     * @param $customer string 顧客名
     * @return mixed
     * @throws RuntimeException
     */
    public function getMinIdByCustomerJob($customer)
    {
        $result = DB::connection('sqsdb')
            ->table('sqs_status_check')
            ->where('customer', '=', $customer)
            ->where('is_completed', '=', false)
            ->orderBy('id', 'asc')
            ->select(['id'])
            ->first();
        $minimum_seq = $this->getObjectVars($result);
        return $minimum_seq;
    }

    /**
     *  管理テーブルに管理レコードを1件追加
     *
     * @param $job_id
     * @param $job_name
     * @param $customer
     * @param $time
     * @return int 管理レコード挿入時に自動採番されたID
     */
    public function createManageRecord($job_id, $job_name, $customer, $time)
    {
        return DB::connection('sqsdb')
            ->table('sqs_status_check')
            ->insertGetId([
                'job_id' => $job_id,
                'job_name' => "$job_name",
                'customer' => "$customer",
                'is_completed' => false,
                'is_authority' => false,
                'status' => self::STATUS_READY,
                'created_at' => "$time"
            ]);
    }

    /**
     *  管理テーブルに実行済みフラグを立てる（ソフトデリート）
     *
     * @param $manage_min_id
     * @param $time
     * @param $status
     */
    public function createCompletedFlag($manage_min_id, $time, $status)
    {
        // ジョブ実行完了時
        if ($status == self::STATUS_COMPLETED) {
            DB::connection('sqsdb')
                ->table('sqs_status_check')
                ->where('id', '=', $manage_min_id)
                ->update([
                    'is_completed' => true,
                    'status' => self::STATUS_COMPLETED,
                    'updated_at' => "$time",
                ]);
        }
        // ジョブ実行エラー時
        if ($status == self::STATUS_FAILED) {
            DB::connection('sqsdb')
                ->table('sqs_status_check')
                ->where('id', '=', $manage_min_id)
                ->update([
                    'is_completed' => true,
                    'status' => self::STATUS_FAILED,
                    'updated_at' => "$time",
                ]);
        }
    }

    /**
     * ジョブ実行状態を更新
     *
     * @param $id
     * @param $customer
     * @param $status_flg
     * @return void
     */
    public function updateStatus($id, $customer, $status_flg)
    {
        switch ($status_flg) {
            case "running":
                $status = self::STATUS_RUNNING;
                break;
            case "completed":
                $status = self::STATUS_COMPLETED;
                break;
            case "failed":
                $status = self::STATUS_FAILED;
                break;
            default:
                $status = self::STATUS_READY;
        }

        DB::connection('sqsdb')
            ->table('sqs_status_check')
            ->where('id', '=', $id)
            ->where('customer', '=', $customer)
            ->update(['status' => $status]);
    }

    /**
     * 指定のIDから連番のジョブに継続処理権限を付与、および剥奪
     *
     * @param $id
     * @param $customer
     * @param $authority_flg
     */
    public function setAuthority($id, $customer, $authority_flg = true)
    {
        if ($authority_flg) {
            while (true) {
                // 同一カスタマー意外には権限を与えない
                if (!$flag = $this->showLows($id, $customer)) break;
                // 継続処理権限を付与
                DB::connection('sqsdb')
                    ->table('sqs_status_check')
                    ->where('id', '=', $id)
                    ->where('customer', '=', $customer)
                    ->update(['is_authority' => true]);
                $id += 1;
            }
        }
        if (!$authority_flg) {
            while (true) {
                // 同一カスタマー意外には権限を与えない
                if (!$flag = $this->showLows($id, $customer)) break;
                // 継続処理権限を付与
                DB::connection('sqsdb')
                    ->table('sqs_status_check')
                    ->where('id', '=', $id)
                    ->where('customer', '=', $customer)
                    ->update(['is_authority' => false]);
                $id += 1;
            }
        }
    }

    public function deleteLow($id)
    {
        DB::connection('sqsdb')
            ->table('sqs_status_check')
            ->where('id', '=', $id)
            ->delete();
    }

    /**
     * Sqs管理テーブルをリフレッシュ
     *
     * @return mixed
     */
    public function truncateTable()
    {
       DB::connection('sqsdb')
           ->table('sqs_e_pos')
           ->update(['position' => 1]);
       DB::connection('sqsdb')
           ->table('sqs_d_pos')
           ->update(['position' => 1]);
       return DB::delete('TRUNCATE TABLE sqs_manage.sqs_status_check');
    }
}
