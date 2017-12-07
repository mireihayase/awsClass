<?php

namespace App\Src\TextSearch;

use App\Src\Aws\Api\CloudSearchApiService;
use App\Src\Aws\Api\CloudSearchDomainApiService;
use App\Src\TextSearch\Services\CloudSearchSuggestion;

/**
 * AWS CloudSearch接続用 SampleClient
 *
 * ■SDK
 * AWS SDK for PHP::CloudSearchClient
 *  http://docs.aws.amazon.com/aws-sdk-php/v3/api/class-Aws.CloudSearch.CloudSearchClient.html
 * AWS SDK for PHP::CloudSearchDomainClient
 *  http://docs.aws.amazon.com/aws-sdk-php/v3/api/class-Aws.CloudSearchDomain.CloudSearchDomainClient.html
 * ■AWS CloudSearchコンソール
 *  https://ap-northeast-1.console.aws.amazon.com/cloudsearch/home?region=ap-northeast-1
 */
class CloudSearchSample
{
    /** @var CloudSearchApiService  */
    private $cs_client;

    /** @var CloudSearchDomainApiService  */
    private $cs_domain_client;

    /**
     * CloudSearchSample constructor.
     * @param CloudSearchApiService $cs_api_service
     * @param CloudSearchDocument $cs_document
     * @param CloudSearchQuery $cs_query
     * @description
     *  CloudSearch操作用クライアント
     *  接続対象ドメインの検索エンドポイントを取得
     *  .env::CLOUDSEARCH_NAME_MEM=mem-search-0X
     *  .env::CLOUDSEARCH_NAME_VN=vn-search-0X
     *  .env::CLOUDSEARCH_NAME_SR=vn-search-0X
     */
    function __construct(
        CloudSearchApiService $cs_api_service,
        CloudSearchDocument $cs_document,
        CloudSearchQuery $cs_query,
        CloudSearchSuggestion $cs_suggest)
    {
        $this->cs_client = $cs_api_service;
#        $domain_endpoint = $this->cs_client->getDomainEndpoint(env('CLOUDSEARCH_NAME_MEM'));
        $domain_endpoint = $this->cs_client->getDomainEndpoint('mem-search-02');

        $this->cs_suggest = $cs_suggest;
        // CloudSearch管理用クライアント取得
        $this->cs_domain_client = new CloudSearchDomainApiService(
            $domain_endpoint,
            $cs_document,
            $cs_query
        );
    }

    /**
     * サンプルメソッド
     *
     * @return void
     */
    public function run()
    {
        // 時刻取得
        $date = \App\Utils\CalcUtil::formatDate(new \Carbon\Carbon(), 'Y-m-d H:i:s');

        /**
         * キーワード全文検索 サンプル
         *
         * @description
         *  $search_string 複数指定「可」
         *  $sheet_id 複数指定「可」
         *  $don 複数指定「不可」
         */
#        $customer = ['eig];
#        $search_string = ['全文検索テスト'];
#        $search_string = ['全文検索テスト', '正社員'];
#        $sheet_ids = [];
#        $sheet_ids = [1];
#        $sheet_ids = [0,1];
#        $member_ids = [];
#        $member_ids = [0];
#        $member_ids = [0,1];
#        $member_data_definition_ids = [12,682];
        $search_string = ['京都'];
        $customer = 'eig';
        $sheet_ids = [];
#        $member_ids = [680,681,682,10,108];
#        $member_ids = [1, 4];
        $member_ids = [0,1];
#        $member_data_definition_ids = [452, 461];
        $member_data_definition_ids = [];

#        $result = $this->cs_domain_client->searchIndex(
#            $search_string,    # require
#            $customer,         # require
#            $sheet_ids,        # not require
#            $member_ids,       # not require
#            $member_data_definition_ids,  # not require
#            false              # キーワード検索用フラグ(bool[false:OR, true:AND])
#        );

#        $query = '(and customer:\'don\'(or data:\'派遣\' data:\'管理職\')(or sheet_id:0)(or member_id:0 member_id:1))';
#        $query = '(and customer:\'don\'(and data:\'CS全文検索テスト\' data:\'管理職\'))';
#        $query = '(and customer:\'don\'(or data:\'派遣\' data:\'管理職\'))';
#        $result = $this->cs_domain_client->search($query);


        /**
         * サジェスト検索 サンプル
         */
        $suggest_str = '高橋';
        $suggester = 'suggest_data_01';
#        $result = $this->cs_domain_client->searchSuggest($suggest_str, $suggester);
        $result = $this->cs_suggest->searchSuggest($suggest_str, $suggester);



        /**
         * 転置インデックス作成 サンプル
         * @description 引き渡すArrayフォーマットは以下です。
         */
        // mem-search用
        $mem_params = [
            [
                'id' => 201609261538001,
                'member_id' => 0,
                'sheet_id' =>  0,
                'member_data_definition_id' => 0,
                'data' =>  '全文検索テスト_東京都',
                'record_order' => 0,
                'batch_key_id' => 0,
                'file_data_id' => 0,
                'created_at' => $date,
                'updated_at' => $date,
                'customer' => $customer
            ],
            [
                'id' => 201609261538002,
                'member_id' => 1,
                'sheet_id' =>  1,
                'member_data_definition_id' => 1,
                'data' =>  '全文検索テスト_東京都',
                'record_order' => 1,
                'batch_key_id' => 1,
                'file_data_id' => 1,
                'created_at' => $date,
                'updated_at' => $date,
                'customer' => $customer
            ],
            [
                'id' => 201609261538003,
                'member_id' => 3,
                'sheet_id' =>  3,
                'member_data_definition_id' => 3,
                'data' =>  '全文検索テスト_東京都',
                'record_order' => 3,
                'batch_key_id' => 3,
                'file_data_id' => 3,
                'created_at' => $date,
                'updated_at' => $date,
                'customer' => $customer
            ],

            // [
            //   ...
            // ]

        ];

        // vn-search用
        $vn_params = [
            [
                'id' => 201607071234001,
                'vn_event_id' =>  201607071234001,
                'member_id' =>  201607071234001,
                'vn_form_definition_id' => 201607071234001,
                'answer' =>  'CS全文検索テスト_VN_201607071234001',
                'file_data_id' => 201607071234001,
                'created_at' => $date,
                'updated_at' => $date,
                'customer' => $customer
            ],
            [
                'id' => 201607071234002,
                'vn_event_id' =>  201607071234002,
                'member_id' =>  201607071234002,
                'vn_form_definition_id' => 201607071234002,
                'answer' =>  'CS全文検索テスト_VN_201607071234002',
                'file_data_id' => 201607071234002,
                'created_at' => $date,
                'updated_at' => $date,
                'customer' => $customer
            ],
            [
                'id' => 201607071234003,
                'vn_event_id' =>  201607071234003,
                'member_id' =>  201607071234003,
                'vn_form_definition_id' => 201607071234003,
                'answer' =>  'CS全文検索テスト_VN_201607071234003',
                'file_data_id' => 201607071234003,
                'created_at' => $date,
                'updated_at' => $date,
                'customer' => $customer
            ],

            // [
            //   ...
            // ]
        ];


        /**
         * 転置インデックス作成 サンプル
         *
         */
#      $result = $this->cs_domain_client->doPostDocuments($mem_params);


        /**
         * 転置インデックス削除 サンプル
         *
         * @description インデックス作成と同じフォーマットです。
         */
#       $result = $this->cs_domain_client->doDeleteDocuments($mem_params);

        /**
         * SDFアップロード サンプル
         *
         */
#        $updateDocuments = file_get_contents('/var/tmp/cs_uploadDoc/uploadDoc1.json');
#        $result = $this->cs_domain_client->uploadDocumentsNew($updateDocuments);

        var_dump($result);
    }
}
