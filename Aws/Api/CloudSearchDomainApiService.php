<?php

namespace App\Src\Aws\Api;

use Aws\CloudSearchDomain\CloudSearchDomainClient;
use App\Src\TextSearch\CloudSearchDocument;
use App\Src\TextSearch\CloudSearchQuery;
use App\Src\TextSearch\CloudSearchDocumentInterface;
use App\Src\TextSearch\CloudSearchQueryInterface;

/**
 * AWS CloudSearch 転置インデックス操作用クラス（APIラッパー）
 *
 * @package App\Src\Aws\Api\CloudSearch
 * AWS SDK for PHP::CloudSearchDomainClient
 * http://docs.aws.amazon.com/aws-sdk-php/v3/api/api-cloudsearchdomain-2013-01-01.html
 */
class CloudSearchDomainApiService
{
    /** @var string 各種検索ドメインの接頭辞 */
    const SEARCH_DOMAIN_TYPE__MEM = 'mem';
    const SEARCH_DOMAIN_TYPE__VN = 'vn';
    const SEARCH_DOMAIN_TYPE__SR = 'sr';

    /** @var string 全文検索クエリータイプ(詳細) */
    const QUERY_TYPE = 'structured';

    /** @var CloudSearchDomainApiService */
    private $cs_domain_client;

    /** @var CloudSearchDocument */
    private $cs_document_client;

    /** @var CloudSearchQuery */
    private $cs_query_client;

    /** @var string ユーザー接続ドメイン判別用接頭辞 */
    private $user_domain_prefix;

    /**
     * CloudSearchDomainApiService constructor.
     *
     * @param $domain_endpoint
     * @param CloudSearchDocumentInterface $cs_document
     * @param CloudSearchQueryInterface $cs_query
     */
    function __construct(
        $domain_endpoint,
        CloudSearchDocumentInterface $cs_document,
        CloudSearchQueryInterface $cs_query)
    {
        $config = [
            'profile' => 'default',
            'version' => 'latest',
            'region' => 'ap-northeast-1',
            'endpoint' => $domain_endpoint
        ];
        $this->cs_domain_client = CloudSearchDomainClient::factory($config);
        $this->cs_document_client = $cs_document;
        $this->cs_query_client = $cs_query;

        // 接続ドメインの接頭辞をセット(mem|vn|sr)
        $this->setDomainPrefix($domain_endpoint);
    }

    /**
     * 全文検索用APIラッパー
     *
     * @param $search_str     string
     * @param $customer       string
     * @param $sheet_ids      array
     * @param $member_ids     array
     * @param $search_key_flg bool
     * @return \Aws\Result|bool
     * @throws \Exception
     */
    public function searchIndex($search_str, $customer, $sheet_ids = [],
                                $member_ids = [], $mem_data_definition_ids = [], $search_key_flg = true)
    {
        // 検索用文字列、および各種検索条件を一時置き場にセット
        $this->cs_query_client->setSearchString($search_str);
        $this->cs_query_client->setCustomer($customer);
        $this->cs_query_client->setSheetIds($sheet_ids);
        $this->cs_query_client->setMemberIds($member_ids);
        $this->cs_query_client->setMemberDataDefinitionIds($mem_data_definition_ids);

        // キーワード検索用フラグをセット
        $this->cs_query_client->setSearchkeyFlg($search_key_flg);

        // 接続ドメインに応じて、組み立てたクエリーを取得
        switch ($this->user_domain_prefix) {
            case self::SEARCH_DOMAIN_TYPE__MEM:
                $query = $this->cs_query_client->getMemQuery();
                break;
            case self::SEARCH_DOMAIN_TYPE__VN:
                $query = $this->cs_query_client->getVnQuery();
                break;
            case self::SEARCH_DOMAIN_TYPE__SR:
                $query = $this->cs_query_client->getSrQuery();
                break;
            default:
                throw new \Exception('Please Check the .env [CLOUDSEARCH_NAME_XXX=XXXXX]');
        }

        // [Debug] 組み立済みクエリー
        // print_r($query);  # debug

        // 全文検索リクエスト発行
        $result = $this->search($query);

        return $result;
    }

    /**
     * インデックス内を全文検索
     *
     * @param $query
     * @param array $options オプション用データセット
     * @return \Aws\Result|bool
     */
    public function search($query, $options = [])
    {
        $result = $this->cs_domain_client->search([
            'query' => $query,
            'queryParser' => self::QUERY_TYPE,  # 全文検索クエリタイプ::simple|structured|Lucene|DisMax
            'size' => 10000,            # AWSからリターンする上限数(10000が限界値)
            'sort' => '_score desc',  # 重みづけ(tf-idfアルゴリズム)が高い順にソート
        ]);
        return $result;
    }

    public function searchSuggest($search_str, $suggester)
    {
        return $this->cs_domain_client->suggest([
            'query' => $search_str,
            'suggester' => $suggester
        ]);
    }


    /**
     * 転置インデックス作成用APIラッパー
     * ※CSV->SDF変換後のバルク作成用の機能も検討
     *
     * @param array $master_data 転置インデックスのもととなるマスターデータ
     * @return \Aws\Result|bool
     * @throws \Exception
     */
    public function doPostDocuments(array $master_data)
    {
        // 転置インデックスの作成用マスタデータを、一時置き場にセット
        $this->cs_document_client->setMasterToWorkArea($master_data);

        // 接続ドメインに応じて、組み立て済みアップロード用ドキュメントを取得
        switch ($this->getDomainPrefix()) {
            case 'mem':
                $documents = $this->cs_document_client->getPostMemDocuments();
                break;
            case 'vn':
                $documents = $this->cs_document_client->getPostVnDocuments();
                break;
#            case 'sr':
#                $documents = $this->cs_document_client->getPostSrDocuments();
#                break;
            default:
                throw new \Exception('Please Check the .env [CLOUDSEARCH_NAME_XXX=XXXXX]');
        }

        // ドキュメントをアップロードし、転置インデックスを作成
        $result = $this->uploadDocuments($documents);

        return $result;
    }

    /**
     * 転置インデックス削除用APIラッパー
     *
     * @param array $master_data
     * @return \Aws\Result|bool
     */
    public function doDeleteDocuments(array $master_data)
    {
        // 転置インデックスの削除用マスタデータを、一時置き場にセット
        $this->cs_document_client->setMasterToWorkArea($master_data);

        // 組み立て済みアップロード用ドキュメントを取得
        $documents = $this->cs_document_client->getDeleteDocuments();

        // ドキュメントをアップロード
        $result = $this->uploadDocuments($documents);

        return $result;
    }

    /**
     * 転置インデックス作成/削除 共用API
     *
     * @param array $documents 組み立て済みアップロード用ドキュメント
     * @return \Aws\Result|bool
     */
    protected function uploadDocuments($documents)
    {
        $args = [
            'contentType' => 'application/json',
            'documents'   => json_encode($documents)
        ];

        // ドキュメントをアップロードし、転置インデックスを削除
        $result = $this->cs_domain_client->uploadDocuments($args);

        return $result;
    }

    /**
     * ドメイン接続用の接頭辞をセット
     *
     * @param $domain_endpoint
     * @return void
     * @throws \Exception
     */
    protected function setDomainPrefix($domain_endpoint)
    {
        // mem_search 接続用の接頭辞をセット
        if (preg_match('/search-mem/', $domain_endpoint)) {
            $this->user_domain_prefix = 'mem';
        // vn_answers 接続用の接頭辞をセット
        } elseif (preg_match('/search-vn/', $domain_endpoint)) {
            $this->user_domain_prefix = 'vn';
        // sr_answers 接続用の接頭辞をセット
        } elseif (preg_match('/search-sr/', $domain_endpoint)) {
            $this->user_domain_prefix = 'sr';
        } else {
            throw new \Exception('Please Check the .env [CLOUDSEARCH_NAME_XXX=XXXXX]');
        }
    }

    /**
     * ドメイン接続用の接頭辞を取得
     *
     * @return string
     */
    protected function getDomainPrefix()
    {
        return $this->user_domain_prefix;
    }
}
