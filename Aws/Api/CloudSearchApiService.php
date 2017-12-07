<?php

namespace App\Src\Aws\Api;

use Aws\CloudSearch\CloudSearchClient;

/**
 * AWS CloudSearch 管理用クラス（APIラッパー）
 *
 * AWS SDK for PHP::CloudSearchDomainClient
 *  http://docs.aws.amazon.com/aws-sdk-php/v3/api/api-cloudsearchdomain-2013-01-01.html
 *
 */
class CloudSearchApiService
{
    /** @var CloudSearchClient */
    private $cs_client;

    /** @var string */
    private  $user_name;

    /** @var string インデックスフィールドタイプ */
    const FIELD_TYPE__TEXT = 'text';

    /** @var string ドメイン名ベース::member_data_valuesテーブル用 */
    const CS_ENDPOINT_BASE__MEM = 'mem-search-';

    /** @var string ドメイン名ベース::vn_answerテーブル用 */
    const CS_ENDPOINT_BASE__VN = 'vn-search-';

    /** @var string ドメイン名ベース::sr_answerテーブル用 */
    const CS_ENDPOINT_BASE__SR = 'sr-search-';

    /**
     * CloudSearchApiService constructor.
     */
    function __construct()
    {
        $config = [
            'profile' => 'default',
            'version' => 'latest',
            'region' => 'ap-northeast-1'
        ];
        $this->cs_client = CloudSearchClient::factory($config);
    }

    /**
     * 転置インデックスドメインを作成（システム基盤系統ごとに1つずつ作成）
     *
     * @param string $domain_name
     * @return obj   \Aws\Result
     */
    public function createDomain($domain_name)
    {
        $result = $this->cs_client->createDomain([
            'DomainName' => $domain_name,
        ]);
        return $result;
    }

    /**
     * ドメイン一覧を返す
     *
     * @description ドメイン:: RDBでのテーブルに相当
     * @return obj \Aws\Result
     */
    public function listDomainNames()
    {
        $result = $this->cs_client->listDomainNames();
        return $result;
    }

    /**
     * ドメインにインデックスフィールドを作成
     *
     * @description 新規ユーザー追加時に使用する想定
     * @description ドメイン:: RDBでのテーブルに相当
     * @description フィールド:: RDBでのカラムに相当
     * @description おおむね15分前後の時間を要する
     * @param string $domain_name
     * @param string $field_name
     * @param string $field_type
     * @return obj   \Aws\Result
     */
    public function defineIndexField($domain_name, $field_name, $field_type)
    {
        switch ($field_type) {
            // フィールドタイプがテキストの場合
            case self::FIELD_TYPE__TEXT:
                $result= $this->cs_client->defineIndexField([
                    'DomainName' => $domain_name,
                    'IndexField' => [
                        'IndexFieldName' => $field_name,
                        'IndexFieldType' => $field_type,
                        'TextOptions' => [
                            'AnalysisScheme' => '_ja_default_',  # 日本語分析スキーム
                            'HighlightEnabled' => true,  # フィールドにハイライトを返す
                            'ReturnEnabled' => true,  # 検索結果でフィールドの内容を返す
                            'SortEnabled' => true,  # フィールドを使用して検索結果をソート
                        ],
                    ],
                ]);
                break;

            // フィールドタイプがテキスト意外の場合
            default:
                $result = $this->cs_client->defineIndexField([
                    'DomainName' => $domain_name,
                    'IndexField' => [
                        'IndexFieldName' => $field_name,
                        'IndexFieldType' => $field_type
                    ],
                ]);
                break;
        }

        return $result;
    }

    /**
     * 検索インデックスを構築、および再構築
     *
     * @description フィールド追加/削除時、およびドキュメント定義変更時に実施
     * @param string $domain_name
     * @return obj   \Aws\Result|bool
     */
    public function indexDocuments($domain_name)
    {
        $result = $this->cs_client->indexDocuments([
            'DomainName' => $domain_name,
        ]);
        return $result;
    }

    /**
     * 対象ユーザーの検索エンドポイントを取得する
     *
     * @param $endpoint_system_set
     * @return string
     * @throws \Exception
     */
    public function getDomainEndpoint($endpoint_system_set)
    {
        if (preg_match('/^mem-search-.*?([0-9]+)/', $endpoint_system_set, $matches)) {
            $domain_name = self::CS_ENDPOINT_BASE__MEM . $matches[1];
        } else if (preg_match('/^vn-search-.*?([0-9]+)/', $endpoint_system_set, $matches)) {
            $domain_name = self::CS_ENDPOINT_BASE__VN . $matches[1];
        } else if (preg_match('/^sr-search-.*?([0-9]+)/', $endpoint_system_set, $matches)) {
            $domain_name = self::CS_ENDPOINT_BASE__SR . $matches[1];
        } else {
            throw new \Exception('Please Check the .env [CLOUDSEARCH_NAME_XXX=XXXXX]');
        }

        // ドメイン名から検索エンドポイントを取得
        $result = $this->cs_client->describeDomains(['DomainNames' => [$domain_name]]);

        // 検索エンドポイントURI組み立て
        $search_domain_endpoint  = 'https://'. $result['DomainStatusList'][0]['SearchService']['Endpoint'];

        return $search_domain_endpoint;
    }

    /**
     * スケーリングパラメーターを設定
     *
     * @param string $domain_name
     * @return obj   \Aws\Result
     * @description DesiredInstancetype search.m1.small|search.m1.large|search.m2.xlarge|
     *                                     search.m2.2xlarge|search.m3.medium|search.m3.large|
     *                                      search.m3.xlarge|search.m3.2xlarge
     */
    public function updateScalingParameters($domain_name)
    {
        $result = $this->cs_client->updateScalingParameters([
            'DomainName' => $domain_name,
            'ScalingParameters' => [
                'DesiredInstanceType' => 'search.m1.large',
                'DesiredPartitionCount' => 1,
                'DesiredReplicationCount' => 2,
            ],
        ]);
        return $result;
    }

    /**
     * スケーリングパラメーターを参照
     *
     * @param $domain_name
     * @return \Aws\Result|bool
     */
    public function describeScalingParameters($domain_name)
    {
        $result = $this->cs_client->describeScalingParameters([
            'DomainName' => $domain_name
        ]);
        return $result;
    }

    /**
     * アベイラビリティゾーン設定
     *
     * @param string $domain_name
     * @return obj \Aws\Result
     * @description 所要時間15分～25分程度
     */
    public function updateAvailabilityOptions($domain_name)
    {
        $result = $this->cs_client->updateAvailabilityOptions([
            'DomainName' => $domain_name,
            'MultiAZ' => true,
        ]);
        return $result;
    }

    /**
     * アクセスポリシーを設定（接続元IP制限、IAMロール制御等）
     *
     * @param $domain_name
     * @param $access_policies
     * @return \Aws\Result
     */
    public function updateServiceAccessPolicies($domain_name, $access_policies)
    {
        $result = $this->cs_client->updateServiceAccessPolicies([
            'DomainName' => $domain_name,
            'AccessPolicies' => $access_policies,
        ]);
        return $result;
    }
}
