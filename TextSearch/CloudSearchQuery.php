<?php

namespace App\Src\TextSearch;

/**
 * AWS CloudSearch 転置インデックス検索用 ドキュメント準備クラス
 */
class CloudSearchQuery implements CloudSearchQueryInterface
{
    /** @var String 検索条件のフィールド名を定義 */
    const INDEX_FIELD__CUSTOMER = 'customer:';
    const INDEX_FIELD__MEM_SHEET_ID = 'sheet_id:';
    const INDEX_FIELD__MEM_MEMBER_ID = 'member_id:';
    const INDEX_FIELD__MEM_MEM_DATA_DEFINITION_ID = 'member_data_definition_id:';

    /** @var String キーワード検索のフィールド名を定義 */
    const INDEX_FIELD__MEM_TEXT = 'data:';                   // 形態素解析エンジン用（こちらを優先、ノイズ防止）
    const INDEX_FIELD__MEM_TEXT_TOKEN = 'data_token:';     // N-gramエンジン用（取りこぼし止）
    const INDEX_FIELD__VN_TEXT = 'answer:';                  // 形態素解析エンジン用（こちらを優先、ノイズ防止）
    const INDEX_FIELD__VN_TEXT_TOKEN = 'answer_token:';    // N-gramエンジン用（取りこぼし防止）
    const INDEX_FIELD__SR_TEXT = 'answer:';                  // 形態素解析エンジン用（こちらを優先、ノイズ防止）
    const INDEX_FIELD__SR_TEXT_TOKEN = 'answer_token:';    // N-gramエンジン用（取りこぼし防止）

    /** @var array 検索用文字列一時置き場*/
    private $search_string = [];
    private $sheet_ids = [];
    private $member_ids = [];
    private $member_data_definition_ids = [];
    private $customer;

    /** @var string キーワード検索用フラグ */
    private $search_key_flg;

    /** @var string クエリー文字列  */
    private $query;

    /** @var int クエリーサイズ  */
    private $size = 10;

    /** @var int クエリーオフセット  */
    private $start = 0;

    /** @var array クエリ用ファセット  */
    private $facet;

    /** @var string */
    private $cursor;

    /** @var string 重みづけソート順序[if-idf::desc|asc} */
    private $sort = '_score desc';

    /** @var array クエリ用オプション  */
    private $queryOptions;

    /**
     * 最大クエリレスポンスサイズをセット
     *
     * @param int $size
     */
    public function setSize($size)
    {
        $this->size = (int) $size;
    }

    /**
     * 最大クエリレスポンスサイズを取得
     *
     * @return int
     */
    public function getSize()
    {
        return $this->size;
    }

    /**
     * @param int $start
     */
    public function setStart($start)
    {
        $this->start = (int) $start;
    }

    /**
     * @return int
     */
    public function getStart()
    {
        return $this->start;
    }

    /**
     * クエリー::ファセットパラメーターをセット
     *
     * @param array|object $facet
     */
    public function setFacet($facet)
    {
        $this->facet = $facet;
    }

    /**
     * @return bool
     */
    public function facetIsEmpty()
    {
        if (is_null($this->facet)) {
            return true;
        }
        return false;
    }

    /**
     * クエリー::ファセットパラメーターを取得
     *
     * @return string
     */
    public function getFacet()
    {
        return json_encode($this->facet);
    }

    /**
     * if-idf::重みづけソート順を指定
     *
     * @param string $sort
     */
    public function setSort($sort)
    {
        $this->sort = $sort;
    }

    /**
     * if-idf::重みづけソート順序を取得
     *
     * @return string
     */
    public function getSort()
    {
        return $this->sort;
    }

    /**
     * @param $cursor
     */
    public function setCursor($cursor)
    {
        $this->cursor = $cursor;
    }

    /**
     * @return string
     */
    public function getCursor()
    {
        return $this->cursor;
    }

    /**
     * @param bool|true $shouldUseCursor
     */
    public function useCursor($shouldUseCursor = true)
    {
        if ($shouldUseCursor && empty($this->cursor)) {
            $this->cursor = 'initial';
        } else {
            $this->cursor = null;
        }
    }

    /**
     * クエリーオプションをセット
     *
     * @param string $key
     * @param string $value
     */
    public function setQueryOption($key,$value)
    {
        $this->queryOptions[$key] = $value;
    }

    public function getQueryOptions()
    {
        if(is_array($this->queryOptions) && count($this->queryOptions) > 0)
        {
            return json_encode($this->queryOptions);
        } else {
            return '';
        }
    }

    /**
     * 検索用顧客名をセット
     *
     * @param string $customer
     */
    public function setCustomer($customer)
    {
        $this->customer = $customer;
    }

    /**
     * 検索用顧客名を取得
     *
     * @return string
     */
    protected function getCustomer()
    {
        return $this->customer;
    }

    /**
     * 検索用シートIDをセット
     *
     * @param $sheet_ids
     */
    public function setSheetIds($sheet_ids)
    {
        $this->sheet_ids = $sheet_ids;
    }

    /**
     * 検索用シートIDを取得
     *
     * @return array
     */
    protected function getSheetIds()
    {
        return $this->sheet_ids;
    }

    /**
     * 検索用メンバーIDをセット
     *
     * @param $member_ids
     */
    public function setMemberIds($member_ids)
    {
        $this->member_ids = $member_ids;
    }

    /**
     * 検索用メンバーIDを取得
     *
     * @return array
     */
    protected function getMemberIds()
    {
        return $this->member_ids;
    }

    /**
     * 検索用文字列をセット
     *
     * @param $search_string
     */
    public function setSearchString($search_string)
    {
        $this->search_string = $search_string;
    }

    /**
     * 検索用文字列を取得
     *
     * @return array
     */
    protected function getSearchString()
    {
        return $this->search_string;
    }

    /**
     * 検索用メンバー詳細情報IDをセット
     *
     * @param $mem_data_definition_ids
     */
    public function setMemberDataDefinitionIds($mem_data_definition_ids)
    {
        $this->member_data_definition_ids = $mem_data_definition_ids;
    }

    /**
     * 検索用メンバー詳細情報IDを取得
     *
     * @return array
     */
    protected function getMemberDataDefinitionIds()
    {
        return $this->member_data_definition_ids;
    }

    /**
     * キーワード検索用フラグをセット
     *
     * @param $search_key_flg
     */
    public function setSearchkeyFlg($search_key_flg)
    {
        $this->search_key_flg = $search_key_flg;
    }

    /**
     * キーワード検索用フラグを取得
     *
     * @return bool
     */
    protected function isSearchKeyFlg()
    {
        return $this->search_key_flg;
    }

    /**
     * 検索用クエリ文字列を組み立てる# mem_search用
     * 完成形クエリーサンプル#
     *  [キーワードOR検索]: [京都|部長]
     *   (and customer:'eig'(or (or data:'京都' data_token:'京都' )(or data:'部長' data_token:'部長' ))(or sheet_id:0 ))
     *  [キーワードAND検索]: [京都 部長]
     *   (and customer:'eig'(and (or data:'京都' data_token:'京都' )(or data:'部長' data_token:'部長' ))(or sheet_id:0 ))
     *
     * 形態素解析用フィールド::dataに検索データがあれば結果を返す。
     * なければ、N-gram用フィールド::data_tokenを探して結果を返す。
     *
     * @description  ドメイン側の要件次第で、チューニングと並行して検索を拡充していく
     * @return string 検索用クエリー
     */
    public function getMemQuery()
    {
        // [require] メインクエリーを作成: customerフィールド
        $main_query_string = '(and ' . self::INDEX_FIELD__CUSTOMER . "'" . $this->getCustomer() . "'";

        // [require] サブクエリーを組み立てる: dataフィールド(形態素解析エンジン)／data_tokenフィールド(N-gramエンジン)
        if ($this->isSearchKeyFlg()) {
            $main_query_string .= '(and ';  // キーワード検索フラグがtrueであれば、AND検索
        } else {
            $main_query_string .= '(or ';   // キーワード検索フラグがfalseであれば、OR検索
        }
        foreach ($this->getSearchString() as $query) {
            $main_query_string .= '(or ';
            $main_query_string .= self::INDEX_FIELD__MEM_TEXT . "'" . "$query" . "'" . " ".          // dataフィールド用
                                  self::INDEX_FIELD__MEM_TEXT_TOKEN . "'" . "$query" . "'" . " ";   // data_tokenフィールド用
            $main_query_string .= ')';
        }
        $main_query_string .= ')';

        // [not require] サブクエリーを組み立てる: sheet_idsフィールド
        if ( ! empty($this->getSheetIds())) {
            $sub_query_string = '(or ';
            foreach ($this->getSheetIds() as $sheet_id) {
                $sub_query_string .= self::INDEX_FIELD__MEM_SHEET_ID . "$sheet_id" . " ";
            }
            $main_query_string .= $sub_query_string . ')';
        }

        // [not require] サブクエリーを組み立てる: member_idsフィールド
        if ( ! empty($this->getMemberIds())) {
            $sub_query_string = '(or ';
            foreach ($this->getMemberIds() as $member_id) {
                $sub_query_string .= self::INDEX_FIELD__MEM_MEMBER_ID . "$member_id" . " ";
            }
            $main_query_string .= $sub_query_string . ')';
        }

        // [not require] サブクエリーを組み立てる: member_data_definition_idsフィールド
        if ( ! empty($this->getMemberDataDefinitionIds())) {
            $sub_query_string = '(or ';
            foreach ($this->getMemberDataDefinitionIds()as $mem_data_definition_id) {
                $sub_query_string .= self::INDEX_FIELD__MEM_MEM_DATA_DEFINITION_ID . "$mem_data_definition_id" . " ";
            }
            $main_query_string .= $sub_query_string . ')';
        }

        // メインクエリーを仕上げる
        $main_query_string .=  ')';

        \Log::info('CloudSearch検索クエリー：'.$main_query_string);   // debug
        return $main_query_string;
    }

    /**
     * 検索用クエリ文字列を組み立てる# vn_search用
     *
     * @description  ビジネスロジックの要件次第で、チューニングと並行して検索を拡充していく
     * @return string 検索用クエリー
     */
    public function getVnQuery()
    {
        // [require] メインクエリーを作成: answerフィールド
        $main_query_string = '(and ';
        foreach ($this->getSearchString() as $query) {
            $main_query_string .= self::INDEX_FIELD__VN_TEXT . "'" . "$query" . "'";
        }

        // [require] メインクエリーを作成: customerフィールド
        $main_query_string .= self::INDEX_FIELD__CUSTOMER . "'" . $this->getCustomer() . "'";

        // クエリを組み立てる
        $query_string = $main_query_string . ')';

        return $query_string;
    }

    /**
     * 検索用クエリ文字列を組み立てる# sr_search用
     *
     * @description  ビジネスロジックの要件次第で、チューニングと並行して検索を拡充していく
     * @return string 検索用クエリー
     */
    public function getSrQuery()
    {
        // メインクエリーを作成: answerフィールド
        $main_query_string = '(and ';
        foreach ($this->getSearchString() as $query) {
            $main_query_string .= self::INDEX_FIELD__SR_TEXT . "'" . "$query" . "'";
        }

        // メインクエリーを作成: customerフィールド
        $main_query_string .= self::INDEX_FIELD__CUSTOMER . "'" . $this->getCustomer() . "'";

        // クエリを組み立てる
        $query_string = $main_query_string . ')';

        return $query_string;
    }
}