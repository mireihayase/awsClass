<?php

namespace App\Src\Queue\Models;

use Illuminate\Database\Eloquent\Model;
use App\Src\Queue\SqsCollection;
use App\Exceptions\RuntimeException;
use DB;
use Log;

/**
 * Kaonavi queueing service エンキューポジション管理テーブル接続クラス
 */
class SqsDbEpos extends Model
{
    /**
     * FIFOポジションを1つインクリメントする
     *
     * @param $pos
     * @throws RuntimeException
     */
    public function shiftTheFifoPosition($pos)
    {
        try {
            DB::transaction(function () use ($pos) {
                DB::connection('sqsdb')
                    ->table('sqs_e_pos')
                    ->increment('position');
                });
        } catch (\Exception $e) {
            Log::error('FIFOポジションインクリメントエラー：'.$e->getMessage());
            throw new RuntimeException(
                $e->getMessage(),
                $e->getCode(),
                $e->getPrevious()
            );
        }
    }
}