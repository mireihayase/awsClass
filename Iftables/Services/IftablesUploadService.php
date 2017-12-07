<?php

namespace App\Src\Iftables\Services;

use Aws\S3\S3Client;
use App\Src\Iftables\Services\IftablesFileService;
use App\Src\Aws\Api\S3ApiService;
use Carbon\Carbon;

/**
 * 外部連携基盤# アップロードサービス
 */
class IftablesUploadService
{
    /** @var object  */
    private $file_service;
    private $api_service;

    /**
     * IftablesUploadService constructor.
     * @param \App\Src\Iftables\Services\IftablesFileService $file_service
     * @param S3ApiService $api_service
     */
    function __construct(IftablesFileService $file_service, S3ApiService $api_service)
    {
        $this->file_service = $file_service;
        $this->api_service  = $api_service;
    }

    /**
     * 指定したオブジェクトをS3へ全てアップロード(※当日のものが対象)
     *
     * @param $bucket
     * @param $ftp_paths
     * @param $regexp
     * @return bool
     */
    public function uploadAllByObjects($regexp, $bucket, $ftp_paths)
    {
        // 当日の日付を取得（YYYYmm表記: [ex] 201602）
        $YYYYmm = \Carbon\Carbon::now()->format('Ym');

        foreach ($ftp_paths as $customer => $ftp_path) {
            $itemList = $this->file_service->getItemList($ftp_path, $regexp);
            foreach ($itemList as $itempath) {
                // [共通処理] ファイル名/ディレクトリ名を取り出す
                $itemname = basename($itempath);
                $itemdir  = dirname($itempath);
                // ログインユーザの基本情報をアップロード
                if (preg_match('#BASE$#', $itemdir)) {
                    $s3Path = "user/$customer/csv/login_user/import/$YYYYmm/$itemname";
                    $res = $this->uploadObject($bucket, $s3Path, $itempath);
                    if (! $res) return false;
                }
                // 所属情報をアップロード
                else if (preg_match('#DEP$#', $itemdir)) {
                    $s3Path = "user/$customer/csv/department/import/$YYYYmm/$itemname";
                    $res = $this->uploadObject($bucket, $s3Path, $itempath);
                    if (! $res) return false;
                }
                // カスタム所属情報をアップロード
                else if (preg_match('#depsinfo$#', $itemdir)) {
                    $s3Path = "user/$customer/csv/custom_permission/department/import/$YYYYmm/$itemname";
                    $res = $this->uploadObject($bucket, $s3Path, $itempath);
                    if (! $res) return false;
                }
                // 個別情報をアップロード
                else if (preg_match("#/[1]{1}[2-8]{1}$#", $itemdir, $matches)) {
                    $num = trim($matches[0], '/');
                    $s3Path = "user/$customer/csv/csv_io/import/$num/$YYYYmm/$itemname";
                    $res = $this->uploadObject($bucket, $s3Path, $itempath);
                    if (! $res) return false;
                }
                // メンバーカスタム情報をアップロード
                #
                #   S3との紐づけを要確認
                #
            }
        }
        return true;
    }

    /**
     * オブジェクトをS3へアップロード
     *
     * @param $bucket
     * @param $s3_path
     * @param $itempath
     * @return bool
     */
    private function uploadObject($bucket, $s3_path, $itempath)
    {
        try {
            $result = $this->api_service->putObject($bucket, $s3_path, $itempath);
        } catch (\Exception $e) {
            \Log::error($e);
            return false;
        }
        print $result['ObjectURL'].PHP_EOL;
        return true;
    }
}
