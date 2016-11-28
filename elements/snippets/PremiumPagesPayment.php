<?php
/**
 * @name PremiumPagesPayment
 * @description
 * Usage Example
 * -------------
 * [[!PremiumPagesPayment? &salesPage=`1` &priceTv=`tvPrice` &productsTv=`tvProducts` &tokenVar=`stripe_token`]]
 *
 * Variables
 * ---------
 * @var $modx modX
 * @var $scriptProperties array
 *
 * @param int $salesPage id of page containing other tv's to be referenced
 * @param string $salesPageVar alternately name of $_REQUEST variable containing the $salesPage
 * @param string $priceTv name of tv containing the price to be charged (must be attached to $salesPage)
 * @param string $productsTv name of tv containing the ID's of pages to grant access to (must be attached to $salesPage)
 * @param string $tpl name of chunk to be used for output
 * @param string $errorTpl name of chunk to be used for error output (defaults to $tpl)
 * @param string $token token to be passed to payment processor
 * @param string $tokenVar alternately name of $_REQUEST var containing token value
 *
 * @package premiumpages
 */
// Your core_path will change depending on whether your code is running on your development environment
// or on a production environment (deployed via a Transport Package).  Make sure you follow the pattern
// outlined here. See https://github.com/craftsmancoding/repoman/wiki/Conventions for more info
$core_path = $modx->getOption('premiumpages.core_path', null, MODX_CORE_PATH.'components/premiumPages/');
include_once $core_path .'/vendor/autoload.php';

$salesPage = $modx->getOption('salesPage',$scriptProperties);
$salesPageVar = $modx->getOption('salesPageVar',$scriptProperties);
$priceTv = $modx->getOption('priceTv',$scriptProperties);
$productsTv = $modx->getOption('productsTv',$scriptProperties);
$tpl = $modx->getOption('tpl',$scriptProperties);
$errorTpl = $modx->getOption('errorTpl',$scriptProperties);
$token = $modx->getOption('token',$scriptProperties);
$tokenVar = $modx->getOption('tokenVar',$scriptProperties, 'stripe_token');

if(empty($tpl)){
    $tpl = 'PremiumPagesOutput';
}

if(empty($errorTpl)){
    $errorTpl = $tpl;
}

if(!empty($salesPageVar)){
    $salesPage = $_REQUEST[$salesPageVar];
}

if(!empty($tokenVar)){
    $token = $_REQUEST[$tokenVar];
}

try{
    if(empty($token) || empty($salesPage) || empty($priceTv) || empty($productsTv) || empty($tpl)){
        throw new Exception('PremiumPageHandler Error: Missing one or more configuration options');
    }

    $pph = new PremiumPageHandler($modx);
    $result = $pph->handlePayment($token,$priceTv,$productsTv, $salesPage);

    $placeholders = $result->toArray();

    return $modx->getChunk($tpl, $placeholders);

} catch (Exception $e){
    $placeholders = array(
        'error' => $e->getMessage()
    );
    return $modx->getChunk($errorTpl, $placeholders);
}