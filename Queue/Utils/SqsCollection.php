<?php

namespace App\Src\Queue\Utils;

/*
| ----------------------------------------------------------------------------------
|  Kaonavi queueing service 雑多処理用クラス
| ----------------------------------------------------------------------------------
|
*/
class SqsCollection
{

    /**
     * 現在の時刻を取得(ミリ秒単位)
     * @return mixed|string
     */
    public function getTime()
    {
        $date = new \DateTime();
        $time = microtime();
        $time_list = explode(' ', $time);
        $time_micro = explode('.', $time_list[0]);
        // ミリ秒単位で現在時刻を取得
        $time = $date->format('Y-m-d H:i:s.') . substr($time_micro[1], 0, 3);

        return $time;
    }

    /**
     * 配列の中からランダムにユーザーを作成する
     * @return mixed
     */
    public function createRandomUser()
    {
        srand((float) microtime() * 10000000);
        $input = array("don", "vyg", "tom", "ckp", "eig");
        $randKeys = array_rand($input, 2);
        $randUser = $input[$randKeys[0]];

        return $randUser;
    }
}
