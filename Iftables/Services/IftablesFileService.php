<?php

namespace App\Src\Iftables\Services;

use Aws\S3\S3Client;
use Symfony\Component\Finder\Finder;
use App\Src\Iftables\Controllers\IftablesController;

/**
 * 外部連携基盤# ファイルサービス
 */
class IftablesFileService
{
    /** @var Finder¥Finder  */
    private $finder;

    /**
     * FileService constructor.
     *
     * @param Finder $finder
     */
    function __construct(Finder $finder)
    {
        $this->finder = $finder;
    }

    /**
     * FTPカスタマーのディレクトリパスを取得
     *
     * @param $ftpDir
     * @param $workDir
     * @return mixed
     */
    function createFtpCustomerList ($ftpDir, $workDir)
    {
        if ($handle = opendir($ftpDir)) {
            while (false !== ($entry = readdir($handle))) {
                if ($entry != "." && $entry != "..") {
                    $paths = "$ftpDir"."{$entry}"."$workDir";
                    $ftp_paths[$entry] = $paths;
                }
            }
            closedir($handle);
        }
        return $ftp_paths;
    }

    /**
     * 指定したオブジェクト(CSV/画像等)のパスをディレクトリを掘って取り込む
     *
     * @param $target_dir
     * @param $regexp
     * @return array
     */
    function getItemList($target_dir, $regexp)
    {
        $iterator = Finder::create()
            ->in($target_dir)                  // ディレクトリを指定
            ->name($regexp)                   // ファイル名を指定（ワイルドカードを使用可能）
            ->files()                         // ファイルのみを取得
            ->date(IftablesController::TARGET_TIME);   // 当日のファイルのみを取得

        $list = [];
        foreach ($iterator as $fileinfo) {  // SplFiIeInfoオブジェクト
            $list[] = $fileinfo->getPathname();
        }
        return $list;
    }
}
