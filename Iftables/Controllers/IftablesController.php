<?php

namespace App\Src\Iftables\Controllers;

use App\Src\Iftables\Services\IftablesFileService;
use App\Src\Iftables\Services\IftablesUploadService;
use App\Src\Aws\Api\S3ApiService;

/**
 * 外部連携基盤# コントローラー
 */
class IftablesController
{
    /** @var object */
    private $file_service;
    private $upload_service;
    private $api_service;

    const FTP_BASEDIR      = '/home/ftp/';              // FTPの公開ルートディレクトリ
    const IFDATAS_DIR      = '/ifdatas/BACKUP';        // Input用(外部システム -> kaoアプリ)
    const OUTPUTDATAS_DIR = '/outputdatas/BACKUP';    // Output用(kaoアプリ -> 外部システム)
    const S3_BUCKET = 'kaonavi-application-storage';
    const FILE_FORMAT ='*.csv';                         // 対象ファイルのフォーマットを指定可能
    const TARGET_TIME = 'since 24 hour ago';

    /**
     * IftablesController constructor.
     * @param IftablesFileService $file_service
     * @param S3ApiService $api_service
     * @param IftablesUploadService $upload_service
     */
    function __construct(
        IftablesFileService $file_service,
        S3ApiService $api_service,
        IftablesUploadService  $upload_service)
    {
        $this->file_service = $file_service;
        $this->api_service   = $api_service;
        $this->upload_service = $upload_service;
    }

    /**
     * 外部連携タスクを開始
     *
     * @return bool
     */
    public function run()
    {
        // FTPカスタマーのディレクトリパスを取得
        $ftp_paths = $this->file_service->createFtpCustomerList(
            self::FTP_BASEDIR,
            self::IFDATAS_DIR
        );
        // カスタマー毎のオブジェクトをS3の所定パスに順次アップロード
        $result = $this->upload_service->uploadAllByObjects(
            self::FILE_FORMAT,
            self::S3_BUCKET,
            $ftp_paths
        );
        if (! $result) return false;
        return true;
    }
}

