<?php

require __DIR__ . '/vendor/autoload.php';

header("Content-Type: application/json"); 
header("Accept-Language: *");

use WikiLangProject\WikiLangLinks;

$lang1 = isset($_GET['lang1']) ? filter_var($_GET['lang1'], FILTER_SANITIZE_STRING) : "";
$lang2 = isset($_GET['lang2']) ? filter_var($_GET['lang2'], FILTER_SANITIZE_STRING) : "";

$seed1 = isset($_GET['seed1']) ? filter_var($_GET['seed1'], FILTER_SANITIZE_STRING) : ""; 

if ($lang1 != "" && $lang2 != "" && $seed1 != "") 
{
    $helper = new WikiLangLinks($lang1, $lang2, 50, 20); 

    $result = $helper->GetCrossLinks1to2($seed1); 

    echo json_encode($result); 
}


?>