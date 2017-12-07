<?php

namespace App\Src\Queue\Services;
use App\Src\Queue\Models\SqsDbStatusCheck;

/**
 * キュー基盤# ジョブ実行状態返却クラス
 */
class SqsReturnJobStatusService
{
    public static function returnJobStatus($id)
    {
        return SqsDbStatusCheck::showStatus($id);
    }
}
