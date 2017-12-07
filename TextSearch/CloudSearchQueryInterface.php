<?php

namespace App\Src\TextSearch;

/**
 * AWS CloudSearch 全文検索用ドキュメント管理インタフェース
 */
interface CloudSearchQueryInterface
{

    /**
     * @return string
     */
    public function getMemQuery();

    /**
     * @return string
     */
    public function getVnQuery();

    /**
     * @return string
     */
#    public function getSrQuery();

    /**
     * @param int $start
     * @return void
     */
    public function setStart($start);

    /**
     * @return int
     */
    public function getStart();

    /**
     * @param int $size
     * @return void
     */
    public function setSize($size);

    /**
     * @return int
     */
    public function getSize();

    /**
     * @param array|object $facet
     * @return void
     */
    public function setFacet($facet);

    /**
     * @return string
     */
    public function getFacet();

    /**
     * @param string $sort
     * @return void
     */
    public function setSort($sort);

    /**
     * @return string
     */
    public function getSort();

    /**
     * @param $cursor
     */
    public function setCursor($cursor);

    /**
     * @return string
     */
    public function getCursor();

    /**
     * @param bool|true $shouldUseCursor
     */
    public function useCursor($shouldUseCursor = true);

    /**
     * @return bool
     */
    public function facetIsEmpty();

    /**
     * @return string
     */
    public function getQueryOptions();

    /**
     * @param string $key
     * @param string $value
     * @return void
     */
    public function setQueryOption($key, $value);
}