<?php

namespace Src\Aws\Api;

use Aws\S3\S3Client;

/**
 * Kaonavi object storage service S3操作サービスクラス（AWS SDKラッパー）
 * AWS SDK for PHP::S3Client
 *   http://docs.aws.amazon.com/aws-sdk-php/v2/api/class-Aws.S3.S3Client.html
 */
class S3ApiService
{
    /** @var Aws\S3\S3Client */
    protected $client;

    function __construct()
    {
        $config = array(
            'profile' => 'default',
            'version' => 'latest',
            'region'  => 'ap-northeast-1'
        );
        $this->client = S3Client::factory($config);
    }

    /**
     * S3オブジェクトのURIを取得する
     *
     * @param $bucket
     * @param $key_prefix
     * @param $expires
     * @return string
     */
    public function getObjectUrl($bucket, $key_prefix, $expires = null)
    {
        return $this->client->getObjectUrl(
            $bucket,
            $key_prefix,
            $expires
        );
    }

    /**
     * 全てのS3バケットを一覧表示する
     *
     * @param void
     * @return void
     */
    public function listBuckets()
    {
        $result = $this->client->listBuckets();
        foreach ($result['Buckets'] as $bucket) {
            echo "{$bucket['Name']} - {$bucket['CreationDate']}\n";
        }
    }

    /**
     * 指定バケットのオブジェクトを一覧表示
     *
     * @param $bucket
     * @return void
     */
    public function listObjects($bucket)
    {
        $iterator = $this->client->getIterator('ListObjects', array(
            'Bucket' => $bucket
        ));
        foreach ($iterator as $object) {
            echo $object['Key'] . "<br>";
        }
    }

    /**
     * 指定オブジェクトをアップロード
     * - キャッシュコントロール用のメタパラメーターをデフォルトで付与::CacheControl,Expires,Metadata[array]等
     *   クライアントからのリクエスト時にレスポンスヘッダーに上記値を挿入して、レスポンスを返す。
     *
     * @param string $bucket
     * @param string key_prefix
     * @param string $filepath
     * @param array $options
     * @return \Aws\Result
     */
    public function putObject($bucket, $key_prefix, $filepath, $options = [])
    {
        return $this->client->putObject([
            'Bucket' => $bucket,
            'Key' => $key_prefix,                                # s3上での配置パス
            'SourceFile' => $filepath,                           # アップロード対象のフルパス
            'ContentType' => mime_content_type($filepath),       # Content-Typeを指定（AWS側で自動付与）
#            'ACL' => 'public-read',                             # アクセス許可設定（infoがデフォルト)
            'StorageClass' => 'STANDARD',                        # ストレージクラスを指定(汎用、低頻度アクセス、アーカイブ)
            'Expires' => date("Y-m-d", strtotime("+2 week")),    # キャッシュの有効期限（2週間に設定）
            'CacheControl' => 'max-age=1209600',                 # 再リクエスト要求までの期間（2週間に設定）
#            'Metadata' => array(                                # ユーザー定義メタデータを指定
#                'param1' => 'value1',
#                'param2' => 'value2'
#            )
            'ServerSideEncryption' => 'AES256'
        ]);
    }

    /**
     * ディレクトリをアップロード
     *
     * @param $dir
     * @param $bucket
     * @param null $key_prefix
     * @param array $options
     * @retun void
     */
    public function uploadDirectory($dir, $bucket, $key_prefix = null, $options = [])
    {
        $this->client->uploadDirectory(
            $dir,
            $bucket,
            $key_prefix,
            $options
        );
    }

    /**
     * S3オブジェクトを削除する
     *   dirの場合、末尾に "/" が必要
     *
     * @param $bucket
     * @param $key_prefix
     * @return \Aws\Result
     */
    public function deleteObject($bucket, $key_prefix)
    {
        return $this->client->deleteObject([
            'Bucket' => $bucket,
            'Key' => $key_prefix
        ]);
    }

    /**
     * 正規表現でマッチしたS3オブジェクトを削除（ディレクトリを再起的に削除。ファイル指定も可能）
     *
     * @param $backet
     * @param $keyPrefix
     * @param string $regex
     * @param array $options
     * @return void
     */
    public function deleteMatchingObjects($backet, $keyPrefix, $regex = '', $options = [])
    {
        $this->client->deleteMatchingObjects(
            $backet,
            $keyPrefix,
            $regex,
            $options
        );
    }

    /**
     * 指定のS3オブジェクトの内容を確認する
     *
     * @param $bucket
     * @param $key_prefix
     * @return \Aws\Result
     */
    public function getObject($bucket, $key_prefix)
    {
        return $this->client->getObject([
            'Bucket'  => $bucket,
            'Key'     => $key_prefix,
        ]);
    }

    /**
     * 指定のS3オブジェクトをダウンロードする（ファイル）
     *
     * @param $bucket
     * @param $key_prefix
     * @param string $save_as
     * @return \Aws\Result
     */
    public function getSaveObject($bucket, $key_prefix, $save_as = './obj')
    {
        return $this->client->getObject([
            'Bucket'  => $bucket,
            'Key'     => $key_prefix,
            'SaveAs'  => $save_as,
        ]);
    }

    /**
     * 指定のS3オブジェクトをダウンロードする（ディレクトリ）
     *
     * @param $dir
     * @param $bucket
     * @param null $key_prefix
     * @param array $options
     */
    public function downloadBucket($dir, $bucket, $key_prefix = null, $options = [])
    {
        $this->client->downloadBucket(
            $dir,
            $bucket,
            $key_prefix,
            $options
        );
    }

    /**
     * 指定のS3オブジェクトファイルを移動する（同一ディレクトリであれば名前変更）
     *
     * @param String $src_bucket
     * @param String $dst_bucket
     * @param String $src_obj
     * @param String $dst_obj
     */
    public function mvObject($src_bucket, $dst_bucket, $src_obj, $dst_obj)
    {
        system("aws s3 mv s3://$src_bucket/$src_obj s3://$dst_bucket/$dst_obj");
    }

    /**
     * 指定のS3オブジェクトファイルをコピーする
     *
     * @param $bucket
     * @param $src_key_prefix
     * @param $dst_key_prefix
     * @return \Aws\Result
     */
    function copyObject($bucket, $src_key_prefix, $dst_key_prefix)
    {
        return $this->client->copyObject([
            'Bucket'      => $bucket,
            'CopySource' => "$bucket"."/"."$src_key_prefix",
            'Key'         => $dst_key_prefix,
        ]);
    }
}

