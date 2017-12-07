<?php

namespace App\Src\TextSearch;

/**
 * AWS CloudSearch 転置インデックス作成/削除用 ドキュメント管理インタフェース
 */
interface CloudSearchDocumentInterface
{
    /**
     * Implement when object needs to be filled with a $hit AWS object
     *
     * @param array $hit
     */
    public function fillWithHit(array $hit);
}