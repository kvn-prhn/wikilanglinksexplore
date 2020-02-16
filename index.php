<?php

require __DIR__ . '/vendor/autoload.php';

use Cache\Adapter\Apcu\ApcuCachePool;
use GuzzleHttp\Client; 

$lang1 = isset($_GET['lang1']) ? filter_var($_GET['lang1'], FILTER_SANITIZE_STRING) : "";
$lang2 = isset($_GET['lang2']) ? filter_var($_GET['lang2'], FILTER_SANITIZE_STRING) : "";
$seed1 = isset($_GET['seed1']) ? filter_var($_GET['seed1'], FILTER_SANITIZE_STRING) : "";

$langs = array(); 

$pool  = new ApcuCachePool(); 
$client = new Client([ 'base_uri' => "https://en.wikipedia.org" ]);
$cacheKey = "Index-Lang-List"; 
if ($pool->hasItem($cacheKey)) {
    $langs = $pool->getItem($cacheKey)->get(); 
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
    $endpoint = "https://en.wikipedia.org/w/api.php?{$params_built}&titles=Main_Page";
    $response_guzzle = $client->get($endpoint);  
    
    if ($response_guzzle->getStatusCode() == 200) 
    {
        $response_raw = (string) $response_guzzle->getBody(); 
        $response = json_decode($response_raw); 
        
        array_push($langs, array("title" => "English", "code" => "en")); 
        
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
                                array_push($langs, array(
                                    "title" => $llink->langname,
                                    "code" => $llink->lang
                                )); 
                            }
                        }
                    }
                }
            } 
        }

        if (count($langs) > 0)
        { 
            $newCacheItem = $pool->getItem($cacheKey);
            $newCacheItem->set($langs);
            $pool->save($newCacheItem); 
        }
    }
}

?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="A set of horizontal menus that switch to vertical
    and which hide at small window widths.">    <title>Wiki Lang Links Explore</title>    
    <link rel="stylesheet" href="https://unpkg.com/purecss@1.0.1/build/pure-min.css" integrity="sha384-" crossorigin="anonymous">
    <link rel="stylesheet" href="css/style.css" type="text/css">
    <script src="https://code.jquery.com/jquery-3.4.1.js"
			  integrity="sha256-WpOohJOqMqqyKL9FccASB9O0KwACQJpFTUBLTYOVvVU="
			  crossorigin="anonymous"></script>
</head>
<body>

<!--[if lte IE 8]>
    <link rel="stylesheet" href="https://unpkg.com/purecss@1.0.1/build/grids-responsive-old-ie-min.css">
<![endif]-->
<!--[if gt IE 8]><!-->
    <link rel="stylesheet" href="https://unpkg.com/purecss@1.0.1/build/grids-responsive-min.css">
<!--<![endif]-->

<div class="custom-wrapper pure-g" id="menu">
    <div class="pure-u-1 pure-u-md-1-2">
        <div class="pure-menu pure-menu-horizontal">
            <a href="./" class="pure-menu-heading custom-brand restart-process-btn">Wiki Lang Links Explore</a>
        </div>
    </div>
    <div class="pure-u-1 pure-u-md-1-2">
        <div class="pure-menu pure-menu-horizontal custom-menu-3 custom-can-transform">
            <ul class="pure-menu-list">
                <li class="pure-menu-item"><a href="#" class="pure-menu-link about-page-link">About</a></li> 
            </ul>
        </div>
    </div>
</div>

<div class="main"> 
    <div class="pure-g"> 
        <div class="pure-u-1">
            <a class="no-decorate-link" href="./"><button class="pure-button button-secondary restart-process-btn">Restart</button></a>
        </div>
        <div class="pure-u-1">
            <div class="errors-block hide-step"></div> 
        </div>
    </div>

    <div class="lang-step1">
        <div class="pure-g"> 
            <div class="pure-u-1">
                <form class="pure-form choose-article-form">
                    <fieldset>
                        <label for="choose-article">Enter Article Title</label>
                        <input type="text" id="choose-article" class="choose-article" value="" />

                        <label for="choose-article-lang">Article Language</label>
                        <select type="text" id="choose-article-lang" class="choose-article-lang">
                            <?php 
                                foreach ($langs as $langs_item)
                                {
                                    echo "<option value=\"" . $langs_item["code"] . "\">" . $langs_item["title"] . "</option>";
                                }
                            ?>
                        </select>

                        <input type="submit" class="pure-button pure-button-primary choose-article-btn" value="Choose Article" /> 
                    </fieldset>
                </form>
            </div> 
            <div class="pure-u-1 choose-article-loading loading-block hide-step">
                <div class="loading-img">
                    <img src="img/loading.gif"> 
                </div>
                <div class="loading-text">
                    Finding language links for the article <strong><span class="article-title"></strong> in the language <strong><span class="lang-title"></span></strong></span>
                </div>
            </div> 
        </div>
    </div>

    <div class="lang-step2 hide-step">
        <div class="pure-g"> 
            <div class="pure-u-1">
                <form class="pure-form compare-article-links-form">
                    <fieldset>
                        <p>Article: <strong><span class="chosen-article-title"></span></strong></p>
                        <p>Article Language: <strong><span class="chosen-article-lang"></span></strong></p>

                        <label for="compare-lang1">Language 1 to Compare</label>
                        <select type="text" id="compare-lang1" class="compare-lang1"></select>

                        <label for="compare-lang2">Language 2 to Compare</label>
                        <select type="text" id="compare-lang2" class="compare-lang2"></select>

                        <input type="submit" class="pure-button pure-button-primary compare-article-links-btn" value="Compare Links" />
                    </fieldset>
                </form>
            </div> 
            <div class="pure-u-1 compare-article-loading loading-block hide-step">
                <div class="loading-img">
                    <img src="img/loading.gif"> 
                </div>
                <div class="loading-text">
                    Getting links in the articles and comparing their links (this might take a while)
                </div>
            </div>  
        </div>
    </div>

    <div class="lang-step3 hide-step">
        <div class="pure-g">
            <div class="pure-u-1-3">
                <strong><div class="lang1-header col-header"></div></strong>
                <div class="lang1-title col-title"></div>
                <div class="lang1-countlabel col-countlabel"></div>
            </div>
            <div class="pure-u-1-3">
            <strong><div class="both-header col-header"></div></strong>
                <div class="both-title col-title"></div>
                <div class="both-countlabel col-countlabel"></div>
            </div>
            <div class="pure-u-1-3">
                <strong><div class="lang2-header col-header"></div></strong>
                <div class="lang2-title col-title"></div>
                <div class="lang2-countlabel col-countlabel"></div>
            </div> 
        </div>

        <div class="pure-g"> 
            <div class="pure-u-1 compare-article-loading loading-block">
                <div class="loading-img">
                    <img src="img/loading.gif"> 
                </div>
                <div class="loading-text">
                    Getting links in the articles and comparing the links.
                </div>
            </div>  

            <div class="pure-u-1-3">
                <div class="lang-content lang1-content hide-step"></div>
            </div>
            <div class="pure-u-1-3">
                <div class="lang-content both-content hide-step"></div>
            </div>
            <div class="pure-u-1-3">
                <div class="lang-content lang2-content hide-step"></div>
            </div>
        </div>
    </div>
</div>

<div class="about-page hide-step">
    <div class="main"> 
        <div class="about-page-close">X</div>
        <p>
            Wiki Lang Links Explore compares the links in one Wikipedia article between two different language versions of that article.
        </p>
        <p>
            Looking at what article links authors decide to put in an article might give insight into how writers of that language view that topic by seeing what topics that article links. Some languages have a smaller community of writers it, so articles from these Wikipedia articles may have fewer links. 
        </p>
        <p>
            If two different language versions of an article share many links, this may either be because the authors of those two languages think the same topics are important and related for that article or because one of the two languages have fewer links in its version of the article.
        </p>
        <p>
            View the <a href="https://github.com/kvn-prhn/wikilanglinksexplore">GitHub repo</a>.
        </p>
    </div>  
</div>

<div class="hidden-values" data-lang1="<?= $lang1 ?>" data-lang2="<?= $lang2 ?>" data-seed1="<?= $seed1 ?>"></div>
<form method="get" method="/" class="hidden-values-pass">
    <input type="hidden" name="lang1" class="lang1" />
    <input type="hidden" name="lang2" class="lang2" />
    <input type="hidden" name="seed1" class="seed1" />
</form>

<script src="js/functions.js"></script>

</body>
</html>