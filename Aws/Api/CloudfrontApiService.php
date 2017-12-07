<?php

namespace App\Src\Aws\Api;

use Aws\CloudFront\CloudFrontClient;

/**
 * Kaonavi CloudFront service CloudFront操作サービスクラス（AWS SDKラッパー）
 *
 * AWS SDK for PHP::CloudFrontClient
 *   http://docs.aws.amazon.com/aws-sdk-php/v2/api/class-Aws.CloudFront_2012_05_05.CloudFrontClient.html
 */
class CloudFrontApiService
{
    protected $client;

    /**
     * CloudFrontApiService constructor.
     */
    function __construct()
    {
        $config = array(
            'profile' => 'default',
            'version' => 'latest',
#            'region'  => 'ap-northeast-1'
            'region'  => 'us-east-1'
        );
        $this->client = CloudFrontClient::factory($config);
    }

    /**
     * 署名シグネチャ付きURLを発行して返す
     *
     * @param $cf_url string     S3上でのディレクトリパス
     * @param $object string     対象オブジェクト
     * @param $cf_key string     署名用秘密鍵
     * @param $cf_key_id string  秘密鍵と紐づくキーペアID
     * @param array $options     オプション設定を指定
     * @return string            署名シグネチャ付きURL
     */
    public function getSignedUrl($cf_url, $object, $cf_key, $cf_key_id, array $options = [])
    {
        $result = $this->client->getSignedUrl(array(
            'url'       => 'https://' . $cf_url . '/' . $object,
            'private_key' => $cf_key,
            'key_pair_id' => $cf_key_id,
            'expires'     => strtotime('+5 minutes'),
            $options
        ));
        return $result;
    }

    /**
     * 指定した無効化キャッシュのリストを取得する
     *
     * @param $distribution_id string S3のバケットと紐づくCloudFrontキャッシュストア
     * @return \Aws\Result
     */
    public function listInvalidations($distribution_id)
    {
        $result = $this->client->listInvalidations(array(
            'DistributionId' => $distribution_id,
        ));
        return $result;
    }

    /**
     * 指定した無効化キャッシュエントリを取得する
     *
     * @param $distribution_id string S3のバケットと紐づくCloudFrontキャッシュストア
     * @param $invalidation_id   string 無効化キャッシュエントリ
     * @return \Aws\Result
     */
    public function getInvalidation($distribution_id, $invalidation_id)
    {
        $result = $this->client->getInvalidation(array(
            'DistributionId' => $distribution_id,
            'Id' => $invalidation_id,
        ));
        return $result;
    }

    /**
     * 指定したエッジキャッシュをパージし、無効化する。
     *
     * @param $distribution_id string S3のバケットと紐づくCloudFrontキャッシュストア
     * @param $key_prefix      string 無効化したいオブジェクトの配置パス
     *                          (ex.ワイルドカード指定：/user/eig/image/display/*)
     *                          (ex.ファイル指定：      /user/eig/image/dispaly/32_43/icon_a0005.jpg)
     * @return \Aws\Result
     */
    public function createInvalidation($distribution_id, $key_prefix)
    {
        $result = $this->client->createInvalidation([
            'DistributionId' => $distribution_id,
            'InvalidationBatch' => [
                'Paths' => [
                    'Quantity' => count($key_prefix),
                    'Items' => $key_prefix,
                ],
                'CallerReference' => time(),
            ],
        ]);
        return $result;
    }
}
