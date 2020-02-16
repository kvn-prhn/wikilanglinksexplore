<?php

namespace WikiLangProject
{
    use Cache\Adapter\Apcu\ApcuCachePool;
    use GuzzleHttp\Client; 

    class WikiLangLinks
    {
        private $pool; 
        private $cacheExpireTime;

        // guzzle client
        private $client;  

        private $lang1;
        private $lang2;
        private $linksPerRequestMax;

        public function __construct($l1, $l2, $linksPerRequestMax, $maxRequestsPerList)
        {
            $this->pool  = new ApcuCachePool(); 
            $this->cacheExpireTime = 500; 

            $this->lang1 = $l1;
            $this->lang2 = $l2;
            $this->linksPerRequestMax = $linksPerRequestMax;

            $this->maxRequestsPerList = $maxRequestsPerList;
            $this->client = new Client([ 'base_uri' => "https://en.wikipedia.org" ]); 
        }

        public function GetCrossLinks1to2($seed1) 
        {
            $cacheKey = sha1("GetCrossLinks1to2-{$seed1}-{$this->lang1}-{$this->lang2}");
            if ($this->pool->hasItem($cacheKey)) {
                return $this->pool->getItem($cacheKey)->get(); 
            } 

            $seed1_encoded = $this->process_wiki_title($seed1); 
            $endpoint_params = array(
                'action' => 'query',
                'format' => 'json',
                'prop' => 'langlinks',
                'titles' => $seed1_encoded,
                'lllang' => $this->lang2,
                'lllimit' => $this->linksPerRequestMax,
                'redirects' => ''
            ); 
            $params_built = http_build_query($endpoint_params);
            $endpoint = "https://{$this->lang1}.wikipedia.org/w/api.php?{$params_built}"; 
            $response_raw = $this->get_contents_from_endpoint($endpoint); 

            $response = json_decode($response_raw); 
             
            error_log($endpoint . "\n", 3, "debug_log.log");
            $seed2 = "";
            if ($response && property_exists($response, 'query')) 
            {
                if (property_exists($response->query, 'pages')) {
                    foreach($response->query->pages as $page) 
                    { 
                        if (property_exists($page, 'langlinks'))
                        {
                            foreach ($page->langlinks as $llink)
                            {
                                if (property_exists($llink, 'lang') && $llink->lang == $this->lang2 && property_exists($llink, '*'))
                                {
                                    if ($seed2 != '') 
                                    { 
                                        $seed2 = $seed2 . "|" . $llink->{'*'};  
                                    }
                                    else 
                                    {
                                        $seed2 = $llink->{'*'}; 
                                    }
                                }
                            }
                        }
                    }
                }
                if (property_exists($response->query, 'normalized') && count($response->query->normalized) > 0 && 
                        property_exists($response->query->normalized[0], 'to')) {
                    $seed1 = $response->query->normalized[0]->to; 
                }
            }
            $seed2_encoded = $this->process_wiki_title_noencode($seed2);
            
            // redo this for each article with a negtive namespace? 
            $lang2_links_result = $this->GetLinksInArticle($seed2_encoded, $this->lang2); 
            $lang1_links_result = $this->GetLinksInArticle($seed1_encoded, $this->lang1); 
            
            $lang1_retranslate = $this->GetLanguageLinks($lang1_links_result, $this->lang1, $this->lang2, "t1", "t2");
            $lang2_retranslate = $this->GetLanguageLinks($lang2_links_result, $this->lang2, $this->lang1, "t2", "t1");
            
            $lang1_retranslate_only = array();
            $lang2_retranslate_only = array();
            $both_langs_retranslate = array();

            foreach ($lang1_links_result as $lang1_link_item)
            {
                $in_both = false;
                $translated2 = null;
                foreach ($lang2_retranslate as $lang2_rt_item_i)
                {
                    if ($lang2_rt_item_i["t1"] == $lang1_link_item) 
                    {
                        $in_both = true;
                        $translated2 = $lang2_rt_item_i;
                    break;
                    }
                }
                if ($in_both) 
                {
                    array_push($both_langs_retranslate, $translated2);
                }
                else
                {
                    array_push($lang1_retranslate_only, $lang1_link_item); 
                }
            }
            
            foreach ($lang2_links_result as $lang2_link_item)
            {
                $in_both = false;
                $translated1 = null;
                foreach ($lang1_retranslate as $lang1_rt_item_i)
                {
                    if ($lang1_rt_item_i["t2"] == $lang2_link_item)  
                    {
                        $in_both = true;
                        $translated1 = $lang1_rt_item_i;
                    break;
                    }
                }
                if ($in_both) 
                {
                    // make sure this doesn't already exist
                    $already_in_both = false;
                    foreach ($both_langs_retranslate as $both_item)
                    {
                        if ($both_item["t2"] == $lang2_link_item) 
                        {
                            $already_in_both = true;
                        break;
                        }
                    }
                    if (!$already_in_both)
                    {
                        array_push($both_langs_retranslate, $translated1); 
                    }
                }
                else
                {
                    array_push($lang2_retranslate_only, $lang2_link_item); 
                }
            }

            // get language names
            $endpoint_langname_params = array(
                'action' => 'query',
                'format' => 'json',
                'meta' => 'languageinfo',
                'liprop' => 'name',
                'uselang' => 'en'
            );
            $params_langname_built = http_build_query($endpoint_langname_params);
            $endpoint_langname = "https://{$this->lang1}.wikipedia.org/w/api.php?{$params_langname_built}&licode={$this->lang1}|{$this->lang2}"; 
            
            error_log($endpoint_langname . "\n", 3, "debug_log.log");

            $response_langname_raw = $this->get_contents_from_endpoint($endpoint_langname); 
            $response_langname = json_decode($response_langname_raw); 
            $seed1_langname = $this->lang1;
            $seed2_langname = $this->lang2;

            if ($response_langname && property_exists($response_langname, 'query') && 
                    property_exists($response_langname->query, 'languageinfo') &&
                    property_exists($response_langname->query->languageinfo, $this->lang1) &&
                    property_exists($response_langname->query->languageinfo, $this->lang2) && 
                    property_exists($response_langname->query->languageinfo->{$this->lang1}, "name") &&
                    property_exists($response_langname->query->languageinfo->{$this->lang2}, "name")) {
                $seed1_langname = $response_langname->query->languageinfo->{$this->lang1}->name;
                $seed2_langname = $response_langname->query->languageinfo->{$this->lang2}->name;
            }

            $result = array( 
                'link1_retranslate_only_count' => count($lang1_retranslate_only),
                'link2_retranslate_only_count' => count($lang2_retranslate_only),
                'both_langs_retranslate_count' => count($both_langs_retranslate), 
                'seed1' => $seed1,
                'seed2' => $seed2,
                'seed1_langname' => $seed1_langname,
                'seed2_langname' => $seed2_langname,
                'lang1_retranslate_only' => $lang1_retranslate_only,
                'lang2_retranslate_only' => $lang2_retranslate_only,
                'both_langs_retranslate' => $both_langs_retranslate
            ); 

            if ($result)
            {
                $newCacheItem = $this->pool->getItem($cacheKey);
                $newCacheItem->set($result);
                $this->pool->save($newCacheItem); 
            }
            
            return $result; 
        }

        // Given an array of wikiepedia article titles in language A, find all 
        // associated articles in language B if they exist and add them to 
        // the result array. Each element of the array returned by this function 
        // has keyA's value as the title in language A and keyB's value as the 
        // associated title in language B. If a title in language A has no 
        // titles in language B, it is not added to the result array. 
        public function GetLanguageLinks($langTitlesA, $langA, $langB, $keyA, $keyB) 
        {
            $langTitlesAImploded = implode("", $langTitlesA);
            $cacheKey = sha1("GetLanguageLinks-{$langTitlesAImploded}-{$langA}-{$langB}-{$keyA}-{$keyB}");
            if ($this->pool->hasItem($cacheKey)) {
                return $this->pool->getItem($cacheKey)->get(); 
            }

            $langA_titles_split = array();
            $langA_splits = count($langTitlesA) / $this->linksPerRequestMax;
            $all_splits = ''; 
            for ($i = 0; $i < $langA_splits; $i++)
            {
                $this_split_arr = array_splice($langTitlesA, 0, $this->linksPerRequestMax);  
                $this_split = '';
                $this_split_not_encode = '';
                foreach($this_split_arr as $split_arr_elm)
                {
                    if ($this_split == '')
                    {
                        $this_split = $this->process_wiki_title_noencode($split_arr_elm);
                        $this_split_not_encode = $split_arr_elm;
                    }
                    else
                    {
                        $this_split = $this_split . "|" . $this->process_wiki_title_noencode($split_arr_elm);
                        $this_split_not_encode = $this_split_not_encode . "|" . $split_arr_elm;
                    }
                }

                array_push($langA_titles_split, $this_split); 

                if ($all_splits == '')
                {
                    $all_splits = $this_split_not_encode;
                }
                else
                {
                    $all_splits = $all_splits . "|" . $this_split_not_encode;
                }
            }

            $langA_titles_retranslated = array();
            $langA_titles_retranslated_promises = array();

            $times = 0;
            foreach ($langA_titles_split as $langA_titles_totranslate) 
            {
                $rt_endpoint_params = array(
                    'action' => 'query',
                    'format' => 'json',
                    'prop' => 'langlinks',
                    'lllang' => $langB,
                    'lllimit' => $this->linksPerRequestMax,
                    'redirects' => ''
                );
                $rt_params_built = http_build_query($rt_endpoint_params); 
                $rt_endpoint = "https://{$langA}.wikipedia.org/w/api.php?{$rt_params_built}&titles={$langA_titles_totranslate}";
                array_push($langA_titles_retranslated_promises, $this->client->getAsync($rt_endpoint));
            }

            $langA_titles_retranslated_promises_results = array();

            foreach ($langA_titles_retranslated_promises as $langA_rt_promise) 
            {
                array_push($langA_titles_retranslated_promises_results, $langA_rt_promise->wait());
            }

            foreach ($langA_titles_retranslated_promises_results as $retranslate_response_resp)
            {
                if ($retranslate_response_resp->getStatusCode() == 200) {
                    $retranslate_response_raw = (string) $retranslate_response_resp->getBody();
                    error_log($rt_endpoint . "\n", 3, "debug_log.log");

                    $retranslate_response = json_decode($retranslate_response_raw); 

                    if ($retranslate_response) { 
                        error_log(json_encode($retranslate_response) . "\n", 3, "debug_log.log");  
                    }

                    if ($retranslate_response && 
                        property_exists($retranslate_response, 'query') && 
                        property_exists($retranslate_response->query, 'pages'))
                    { 
                        foreach($retranslate_response->query->pages as $rtpage) 
                        { 
                            $original_titleA = "";
                            if (property_exists($rtpage, 'title'))
                            { 
                                $original_titleA = $rtpage->title;
                            }
                            if (property_exists($rtpage, 'langlinks'))
                            {
                                foreach ($rtpage->langlinks as $rtlink)
                                {
                                    if (property_exists($rtlink, 'lang') && $rtlink->lang == $langB && property_exists($rtlink, '*'))
                                    {
                                        array_push($langA_titles_retranslated, array($keyA => $original_titleA, $keyB => $rtlink->{'*'}));
                                    }
                                }
                            }
                        }
                    } else {
                        var_dump($retranslate_response);
                        var_dump($rt_endpoint); 
                    }
                }
            }
            
            if ($langA_titles_retranslated)
            {
                $newCacheItem = $this->pool->getItem($cacheKey);
                $newCacheItem->set($langA_titles_retranslated);
                $this->pool->save($newCacheItem); 
            }

            return $langA_titles_retranslated;
        }

        // Given an article title and a language, find all the links 
        // in that article in that langauge and return it as an 
        // array of strings where each string is an article title 
        public function GetLinksInArticle($title_encode, $langcode)
        {
            $cacheKey = sha1("GetLinksInArticle-{$title_encode}-{$langcode}");
            if ($this->pool->hasItem($cacheKey)) {
                return $this->pool->getItem($cacheKey)->get(); 
            }

            $debug_endpoints = array();
            $result = array();
            $plcontinue_val = '';
            $complete = false;
            $requests_made = 0;
            
            while (!$complete && $requests_made < $this->maxRequestsPerList) 
            {
                $links_endpoint_params = array(
                    'action' => 'query',
                    'format' => 'json',
                    'prop' => 'links',
                    'redirects' => '',
                    'pllimit' => $this->linksPerRequestMax
                );
                if ($plcontinue_val != '') {
                    $links_endpoint_params['plcontinue'] = $plcontinue_val;
                }
                $links_params_built = http_build_query($links_endpoint_params); 
                // need to encode? 
                $links_endpoint = "https://{$langcode}.wikipedia.org/w/api.php?{$links_params_built}&titles={$title_encode}";
                array_push($debug_endpoints, $links_endpoint);
                
                $links_response_raw = $this->get_contents_from_endpoint($links_endpoint);
                
                $links_response = json_decode($links_response_raw);
                
                error_log("links_endpoint:\t" . $links_endpoint . "\n", 3, "debug_log.log"); 
                
                if ($links_response &&
                    property_exists($links_response, 'query') && 
                    property_exists($links_response->query, 'pages')) 
                {
                    foreach ($links_response->query->pages as $lpage) 
                    {
                        if (property_exists($lpage, 'links'))
                        {
                            foreach ($lpage->links as $double_link)
                            {
                                if (property_exists($double_link, 'title'))
                                {
                                    array_push($result, $double_link->title);
                                    error_log("title of linked article found:\t" . $double_link->title . "\n", 3, "debug_log.log"); 
                                }
                            }
                        }
                    }
                }

                $plcontinue_val = ""; 
                if ($links_response && !property_exists($links_response, 'batchcomplete') && 
                    property_exists($links_response, 'continue') && property_exists($links_response->continue, 'plcontinue'))
                {
                    $split_plcontinue = explode('|', $links_response->continue->plcontinue); 
                    
                    if ($split_plcontinue)
                    {
                        foreach($split_plcontinue as $split_plcontinue_piece)
                        {
                            if ($plcontinue_val == "") 
                            {
                                $plcontinue_val = $this->process_wiki_title_noencode($split_plcontinue_piece); 
                            }
                            else 
                            {
                                $plcontinue_val = $plcontinue_val . "|" . $this->process_wiki_title_noencode($split_plcontinue_piece); 
                            }
                        }
                    }
                }

                $complete = $plcontinue_val == "";
                $requests_made = $requests_made + 1; 
            }

            if ($result)
            {
                $newCacheItem = $this->pool->getItem($cacheKey);
                $newCacheItem->set($result);
                $this->pool->save($newCacheItem); 
            }

            return $result; 
        }

        private function process_wiki_title($t) {
            return str_replace(" ", "_", utf8_encode($t));
        }

        private function process_wiki_title_noencode($t) {
            return str_replace(" ", "_", $t);
        }

        private function get_contents_from_endpoint($endpoint) {
            $response = $this->client->get($endpoint);
            if ($response->getStatusCode() == 200) {
                return (string) $response->getBody();
            }
            return ""; 
        }
    }
}

?>