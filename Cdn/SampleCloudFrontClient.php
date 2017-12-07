<?php

namespace App\Src\Aws\Api;
require_once __DIR__ . '/../../../../../vendor/autoload.php';

use App\Src\Aws\Api\CloudFrontApiService;

/**
 * AWS CloudFront接続用 Sample Client
 *
 * AWS SDK for PHP::CloudFrontClient
 *  http://docs.aws.amazon.com/aws-sdk-php/v2/api/class-Aws.CloudFront_2012_05_05.CloudFrontClient.html
 */

$cf_client = new CloudFrontApiService();

/* CloudFront接続情報保持 */
// 画像コンテンツキャッシュ接続URI::CloudFront経由。
// 紐づきS3バケット <-> static.kaonavi.jpバケット
// 近日中に接続ドメインURIを static.kaonavi.jpにする見通しです。
$cf_url = 'd3s86t36799fik.cloudfront.net';
// 後日マッピングさせます。
#$cf_url = 'static.kaonavi.jp';

// 取得したいオブジェクトを絶対パスで指定
// さしあたりは、app/resouces/assets/images ディレクトリのみをアップロードしています。
$object = 'images/500.jpg';

// 秘密鍵と紐づくキーペアID
$cf_key_id = 'APKAILWISEO4KXEUDZJQ';

// 署名用秘密鍵をS3から取得し、APサーバの所定パスに配置しておく
// /var/www/html/kaonavi/.aws/pk-kao3-cloudfront.pem
# wget https://s3-ap-northeast-1.amazonaws.com/kaonaviapp/pk-kao3-cloudfront.pem
$cf_key = env('HOME') . "/.aws/pk-kao3-cloudfront.pem";

// 署名シグネチャ付きURLを取得
$signedUrl = $cf_client->getSignedUrl(
    $cf_url,      # S3と紐づくCloudFrontのドメインURIを指定
    $object,      # 対象オブジェクトを絶対パスで指定
    $cf_key,      # 署名用秘密鍵の配置パスを指定
    $cf_key_id    # 秘密鍵と紐づくキーペアID（基本的には固定値）
);

print $signedUrl.PHP_EOL;


/*
 * 2016/04/21 feature/aws-dev7 マージ用サンプル
 *
 */
// CloudFrontのディストリビューションIDを指定(S3データストアと紐づくキャッシュストア[無効化エントリの集合])
// static.kaonavi.jp用
$cf_distributionId = 'EN9IZP08PBER0';
// CloudFrontのインバリッドIDを指定(S3のデータストアと紐づく無効化エントリ)
#$cf_invalidationId = 'I6X1TO1QHSW8S';

// パージ対象のキャッシュファイルを指定(S3の配置パスと紐づく)
#$path = [
#    '/user/eig/image/display/90_120/list_999999.jpg',
#];
// パージ対象のオブジェクトファイルを指定(複数ファイル指定可、ワイルドカード指定可)
$paths = [
#    '/user/eig/image/display/90_120/list_9999.jpg',
#    '/user/eig/image/display/90_120/list_a0009.jpg',
#    '/user/eig/image/display/170_227/smart_9999.jpg',
#    '/user/eig/image/display/60_80/*',
    '/user/eig/image/display/90_120/list_999999.jpg',
];

// 指定したエッジキャッシュをパージし、無効化する。
// S3::画像系ファイルのアップロード・更新にかませるフックの利用を想定しています。
$result = $cf_client->createInvalidation(
    $cf_distributionId,
    $paths
);

// キャッシュの無効化リスト(エントリの集合)を取得する
#$result = $cf_client->listInvalidations($cf_distributionId);
#print_r($result);

// itemのサマリーを取得
#$items = $result->search('InvalidationList.Items');
#print_r($items);

// 指定した無効化エントリを取得する
#$result = $cf_client->getInvalidation($cf_distributionId, $cf_invalidationId);
#print_r($result);
