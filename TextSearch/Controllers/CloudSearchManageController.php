<?php

namespace App\Src\TextSearch\Controllers;

use App\Src\Aws\Api\CloudSearchApiService;
use App\Http\Controllers\Controller;

/**
 * CloudSearch操作用# 基盤系統増設時ドメイン初期セットアップ用コントローラー
 *
 * ■処理内容
 * - 1.新規ドメインを作成(RDBでのテーブルに相当)
 * - 2.マルチAZ設定
 * - 3.アクセスポリシー設定
 * - 4.転置インデックスフィールドを作成(RDBでのカラムに相当)
 * - 5.転置インデックスを構築
 * - 6.インスタンススペック、レプリカ冗長化設定
 */
class CloudSearchManageController extends Controller
{
    /** @var string ドメイン名ベース::member_data_values用 */
    const CS_DOMAIN_BASE__MEM = 'mem-search-';

    /** @var string ドメイン名ベース::vn_answer用 */
    const CS_DOMAIN_BASE__VN = 'vn-search-';

    /** @var string ドメイン名ベース::sr_answer用 */
    const CS_DOMAIN_BASE__SR = 'sr-search-';

    /** @var  CloudSearchApiService */
    private $cs_api_service;

    /** @var  string ドメイン名::member_data_values用 */
    private $domain_name_mem;

    /** @var  string ドメイン名::vn_answers */
    private $domain_name_vn;

    /** @var  string ドメイン名::sr_answers */
    private $domain_name_sr;

    /** @var string IAMロール/セキュリティポリシー */
    // ※絞る接続元IP/IAMの内容について、精査が必要
    const ACCESS_POLICIES = '{
        "Version": "2012-10-17",
        "Statement": [{
            "Sid": "",
            "Effect": "Allow",
            "Principal": {
                "AWS": "*"
            },
            "Action": "cloudsearch:*",
            "Condition": {
                "IpAddress": {
                    "aws:SourceIp": "0.0.0.0/0"
                }
            }
        }]
    }';

    /**
     * CloudSearchManageController constructor.
     *
     * @param CloudSearchApiService $cs_api_service
     */
    function __construct(CloudSearchApiService $cs_api_service)
    {
        $this->cs_api_service = $cs_api_service;
    }

    /**
     * 接続ドメインごとに基盤系統のシステムセットを連結
     *
     * @param $system_set
     * @return void
     */
    public function setDomainName($system_set)
    {
        $this->setDomainNameMem(self::CS_DOMAIN_BASE__MEM . $system_set);
        $this->setDomainNameVn(self::CS_DOMAIN_BASE__VN . $system_set);
        $this->setDomainNameSr(self::CS_DOMAIN_BASE__SR . $system_set);
    }

    /**
     * 接続ドメイン名をセット: mem-search
     *
     * @param $domain_name
     */
    protected function setDomainNameMem($domain_name)
    {
        $this->domain_name_mem = $domain_name;
    }

    /**
     * 接続ドメイン名をセット: vn-search
     *
     * @param $domain_name
     */
    protected function setDomainNameVn($domain_name)
    {
        $this->domain_name_vn = $domain_name;
    }

    /**
     * 接続ドメイン名をセット: sr-search
     *
     * @param $domain_name
     */
    protected function setDomainNameSr($domain_name)
    {
        $this->domain_name_sr = $domain_name;
    }

    /**
     * 接続ドメイン名を返す: mem-search
     *
     * @return string
     */
    protected function getDomainNameMem()
    {
        return $this->domain_name_mem;
    }

    /**
     * 接続ドメイン名を返す: vn-search
     *
     * @return string
     */
    protected function getDomainNameVn()
    {
        return $this->domain_name_vn;
    }

    /**
     * 接続ドメイン名を返す: sr-search
     *
     * @return string
     */
    protected function getDomainNameSr()
    {
        return $this->domain_name_sr;
    }

    /**
     * インデックスフィールド作成
     *
     * @param $domain_name
     * @param $field_name
     * @param $field_type
     * @return \App\Src\Aws\Api\CloudSearch\obj   \Aws\Result|bool
     */
    protected function defineIndexFields($domain_name, $field_name, $field_type)
    {
        $result = $this->cs_api_service->defineIndexField(
            $domain_name,
            $field_name,
            $field_type
        );

        return $result;
    }

    /**
     * 新規システム基盤系統用の初期化を開始
     *
     * @return void|bool
     */
    public function initialDomain()
    {
        /**
         * 1.新規ドメインを作成(RDBでのテーブルに相当)
         */
        // mem-search
        $result = $this->cs_api_service->createDomain($this->getDomainNameMem());

        // vn-search
        $result = $this->cs_api_service->createDomain($this->getDomainNameVn());

        // sr-search
#        $result = $this->cs_api_service->createDomain($this->getDomainNameSr());


        /**
         * 2.マルチAZ設定
         */
        // mem-search
        $result = $this->cs_api_service->updateAvailabilityOptions($this->getDomainNameMem());

        // vn-search
        $result = $this->cs_api_service->updateAvailabilityOptions($this->getDomainNameVn());

        // sr-search
#        $result = $this->cs_api_service->updateAvailabilityOptions($this->getDomainNameSr());


        /**
         * 3.アクセスポリシー設定
         */
        // mem-search
        $result = $this->cs_api_service->updateServiceAccessPolicies(
            $this->getDomainNameMem(),
            self::ACCESS_POLICIES
        );

        // vn-search
        $result = $this->cs_api_service->updateServiceAccessPolicies(
            $this->getDomainNameVn(),
            self::ACCESS_POLICIES
        );

        // sr-search
#        $result = $this->cs_api_service->updateServiceAccessPolicies(
#            $this->getDomainNameSr(),
#            self::ACCESS_POLICIES
#        );


        /**
         * 4.転置インデックスフィールドを作成(RDBでのカラムに相当)
         */
        // mem-search用
        // idカラム
        $result = $this->defineIndexFields(
            $this->getDomainNameMem(),
            'id',
            'int'
        );

        // member_idカラム
        $result = $this->defineIndexFields(
            $this->getDomainNameMem(),
            'member_id',
            'int'
        );

        // sheet_idカラム
        $result = $this->defineIndexFields(
            $this->getDomainNameMem(),
            'sheet_id',
            'int'
        );

        // member_data_definition_idカラム
        $result = $this->defineIndexFields(
            $this->getDomainNameMem(),
            'member_data_definition_id',
            'int'
        );

        // dataカラム
        $result = $this->defineIndexFields(
            $this->getDomainNameMem(),
            'data',
            'text'
        );

        // dataカラム
        $result = $this->defineIndexFields(
            $this->getDomainNameMem(),
            'data_token',
            'text'
        );

        // record_orderカラム
        $result = $this->defineIndexFields(
            $this->getDomainNameMem(),
            'record_order',
            'int'
        );

        // batch_key_idカラム
        $result = $this->defineIndexFields(
            $this->getDomainNameMem(),
            'batch_key_id',
            'int'
        );

        // file_data_idカラム
        $result = $this->defineIndexFields(
            $this->getDomainNameMem(),
            'file_data_id',
            'int'
        );

        // created_atカラム
        $this->defineIndexFields(
            $this->getDomainNameMem(),
            'created_at',
            'literal'
        );

        // updated_atカラム
        $this->defineIndexFields(
            $this->getDomainNameMem(),
            'updated_at',
            'literal'
        );

        // customerカラム
        $this->defineIndexFields(
            $this->getDomainNameMem(),
            'customer',
            'literal'
        );

        // vn-search用
        // ...
        // vn用を実装::インデックス化するカラムを精査
        // ...
        // idカラム
        $this->defineIndexFields(
            $this->getDomainNameVn(),
            'id',
            'int'
        );

        // vn_event_idカラム
        $this->defineIndexFields(
            $this->getDomainNameVn(),
            'vn_event_id',
            'int'
        );

        // member_idカラム
        $this->defineIndexFields(
            $this->getDomainNameVn(),
            'member_id',
            'int'
        );

        // vn_form_definition_idカラム
        $this->defineIndexFields(
            $this->getDomainNameVn(),
            'vn_form_definition_id',
            'int'
        );

        // answerカラム
        $this->defineIndexFields(
            $this->getDomainNameVn(),
            'answer',
            'text'
        );

        // file_data_idカラム
        $this->defineIndexFields(
            $this->getDomainNameVn(),
            'file_data_id',
            'int'
        );

        // created_atカラム
        $this->defineIndexFields(
            $this->getDomainNameVn(),
            'created_at',
            'literal'
        );

        // updated_atカラム
        $this->defineIndexFields(
            $this->getDomainNameVn(),
            'updated_at',
            'literal'
        );

        // customerカラム
        $this->defineIndexFields(
            $this->getDomainNameVn(),
            'customer',
            'literal'
        );

        // sr-search用
         // ...
         // sr用を実装::インデックス化するカラムを精査し、実装する
         // 現在、スキーマ要件が固まっていない模様
         // ...


        /**
         * 5.転置インデックスを構築
         */
        // mem-search
        $result = $this->cs_api_service->indexDocuments($this->getDomainNameMem());

        // vn-search
        $result = $this->cs_api_service->indexDocuments($this->getDomainNameVn());

        // sr-search
#        $result = $this->cs_api_service->indexDocuments($this->getDomainNameSr());
    }
}
