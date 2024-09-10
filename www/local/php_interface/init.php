<?php
//$_SESSION['SESS_CLEAR_CACHE'] = 'Y';
if (file_exists(($rootPathAutoload = $_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php'))) {
    require_once($rootPathAutoload);
}
if (file_exists(($bitrixAutoload = $_SERVER['DOCUMENT_ROOT'] . '/bitrix/vendor/autoload.php'))) {
    require_once($bitrixAutoload);
}
if (file_exists(($libAutoload = $_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/vendor/autoload.php'))) {
    require_once($libAutoload);
}
if (file_exists(($constantsPath = $_SERVER["DOCUMENT_ROOT"]."/local/php_interface/include/constants.php"))) {
    include_once($constantsPath);
}
if (file_exists(($functionsPath = $_SERVER["DOCUMENT_ROOT"]."/local/php_interface/include/functions.php"))) {
    include_once($functionsPath);
}
//\Bex\Monolog\MonologAdapter::loadConfiguration();

function pre($arr){
    echo "<pre>";print_r($arr);echo"</pre>";
}

// function generateSitemap()
// {
//     include $_SERVER["DOCUMENT_ROOT"].'/sitemap/index.php';
//     return "generateSitemap();";
// }

// function testAgent()
// {
//     mail('rk@vzlet.media','test','test');
//     return "testAgent();";
// }