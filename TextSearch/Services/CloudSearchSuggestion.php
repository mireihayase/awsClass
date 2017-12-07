<?php

namespace App\Src\TextSearch\Services;
use App\Src\Aws\Api\CloudSearchApiService;
use App\Src\Aws\Api\CloudSearchDomainApiService;
use App\Src\TextSearch\CloudSearchDocument;
use App\Src\TextSearch\CloudSearchQuery;

class CloudSearchSuggestion
{
    /** @var CloudSearchApiService  */
    private $cs_client;

    /** @var CloudSearchDomainApiService  */
    private $cs_domain_client;

    function __construct(
        CloudSearchApiService $cs_api_service,
        CloudSearchDocument $cs_document,
        CloudSearchQuery $cs_query)
    {
        $this->cs_client = $cs_api_service;
#        $domain_endpoint = $this->cs_client->getDomainEndpoint(env('CLOUDSEARCH_NAME_MEM'));
        $domain_endpoint = $this->cs_client->getDomainEndpoint('mem-search-02');

        // CloudSearch管理用クライアント取得
        $this->cs_domain_client = new CloudSearchDomainApiService(
            $domain_endpoint,
            $cs_document,
            $cs_query
        );
    }

    public function searchSuggest($suggest_str, $suggester)
    {
        return $this->cs_domain_client->searchSuggest($suggest_str, $suggester);

    }
}