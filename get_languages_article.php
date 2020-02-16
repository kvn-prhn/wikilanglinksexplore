<?php

require __DIR__ . '/vendor/autoload.php';

use Cache\Adapter\Apcu\ApcuCachePool;
use GuzzleHttp\Client; 

header("Content-Type: application/json");  
header("Accept-Language: *");

$title = isset($_GET['title']) ? filter_var($_GET['title'], FILTER_SANITIZE_STRING) : "";
$search_lang = isset($_GET['search_lang']) ? filter_var($_GET['search_lang'], FILTER_SANITIZE_STRING) : "";

$normalized = $title; 
$result_langs = array(); 

if ($title != "" && $search_lang != "") 
{
    $pool  = new ApcuCachePool(); 
    $client = new Client([ 'base_uri' => "https://en.wikipedia.org" ]);
    $cacheKey = sha1("Get-Langs-In-Article-{$title}-{$search_lang}"); 
    if ($pool->hasItem($cacheKey)) 
    {
        echo json_encode($pool->getItem($cacheKey)->get());
        return;
    } 
    else 
    { 
        $endpoint_params = array(
            'action' => 'query',
            'format' => 'json',
            'prop' => 'langlinks', 
            'lllimit' => 500,
            'redirects' => '',
            'llprop' => 'langname'
        );
        $params_built = http_build_query($endpoint_params); 
        $endpoint = "https://{$search_lang}.wikipedia.org/w/api.php?{$params_built}&titles={$title}";
        
        $response_guzzle = $client->get($endpoint);
        if ($response_guzzle->getStatusCode() == 200) 
        {  
            $response_raw = $response_guzzle->getBody();
            $response = json_decode($response_raw); 
            
            if ($response && property_exists($response, 'query'))
            {
                if (property_exists($response->query, 'pages'))
                {
                    foreach ($response->query->pages as $lpage) 
                    {
                        if (property_exists($lpage, 'langlinks'))
                        {
                            foreach ($lpage->langlinks as $llink)
                            {
                                if (property_exists($llink, 'lang') && property_exists($llink, 'langname'))
                                {
                                    array_push($result_langs, array(
                                        "title" => $llink->langname,
                                        "l2_title" => $llink->{'*'},
                                        "code" => $llink->lang
                                    )); 
                                }
                            }
                        }
                    }
                }
                if (property_exists($response->query, 'normalized') && count($response->query->normalized) > 0 && 
                        property_exists($response->query->normalized[0], 'to'))
                {
                    $normalized = $response->query->normalized[0]->to; 
                }
            }

            if ($result_langs && $normalized)
            {
                $newCacheItem = $pool->getItem($cacheKey);
                $newCacheItem->set(array(
                    'normalized' => $normalized,
                    'result_langs' => $result_langs
                ));
                $pool->save($newCacheItem);
            }
        
            echo json_encode(array(
                'normalized' => $normalized,
                'result_langs' => $result_langs
            ));
        }
    }
}

?>