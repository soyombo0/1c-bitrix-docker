<?
include 'vpl-vue/tools.php';

include 'include/vplfavorite.php';
include 'include/vplcompare.php';
require_once($_SERVER['DOCUMENT_ROOT'] . "/local/php_interface/include/giveJSONSber.php");
require_once($_SERVER['DOCUMENT_ROOT'] . "/local/php_interface/include/GoogleTableAgent.php");
require_once($_SERVER['DOCUMENT_ROOT'] . "/local/php_interface/include/ozon.php");
require_once($_SERVER['DOCUMENT_ROOT']."/local/php_interface/include/sale_delivery/yandexExpDelivery/yandexExpressConstants.php");
use Bitrix\Main;
use Yandex\Market;


define('CATALOG_ID', 5);
define('REVIEWS_ID', 11);
define('DETAIL_INFO_TABS_ID', 12);
define('PROP_GROUP_ID', 15);
//id правила корзины с накопительной программой
define('BONUS_ID', 2);
//id инфоблока разделов без поддержки SPA
define('NO_SPA_ID', 16);
define('SBERMERCHANT', 76568);

define('RE_SITE_KEY', '6LfK8WklAAAAAIdgjiylphQN8AZSGxLdLaVA7iGH');
define('RE_SEC_KEY', '6LfK8WklAAAAACfd9RHnP7ETLzNzHZAEAR5SzJVl');


global $arrSaleFilter;
$arrSaleFilter = array('PROPERTY_PROMO'=>'ДА');

//отладка vue
//define('VUEJS_DEBUG', true);
//в билде не забыть включить минификацию bundle.config.js

require __DIR__ . '/include/linkToProp.php';
AddEventHandler("iblock", "OnIBlockPropertyBuildList", array("LinkToProp", "GetUserTypeDescription"));


//получение массива элементов для отрисовки списка карточек товаров
function getCardData($arItems){

    $arReturn = array();
    \Bitrix\Main\Loader::includeModule('iblock');
    \Bitrix\Main\Loader::includeModule('catalog');

    foreach($arItems as $key=>$arItem){
        $image = false;
        $secondImage = false;
        if($arItem['PROPERTIES']['GALLERY']['VALUE'][0] && intval($arItem['PROPERTIES']['GALLERY']['VALUE'][0])){
            $img = CFile::ResizeImageGet($arItem['PROPERTIES']['GALLERY']['VALUE'][0], array('width'=>360,'height'=>360));
            $image = $img['src'];

            if($arItem['PROPERTIES']['GALLERY']['VALUE'][1] && intval($arItem['PROPERTIES']['GALLERY']['VALUE'][1])){
                $img = CFile::ResizeImageGet($arItem['PROPERTIES']['GALLERY']['VALUE'][1], array('width'=>360,'height'=>360));
                $secondImage = $img['src'];
            }
        }else{
            if(intval($arItem['PREVIEW_PICTURE']['ID'])){
                $img = CFile::ResizeImageGet($arItem['PREVIEW_PICTURE']['ID'], array('width'=>360,'height'=>360));
                $image = $img['src'];
            }
        }

        $price = $arItem["MIN_PRICE"]["DISCOUNT_VALUE"];
        if(!$price && isset($arItem["ITEM_PRICES"]) && is_array($arItem["ITEM_PRICES"][0]) ){
            $price = $arItem["ITEM_PRICES"][0]["PRICE"];
        }

        // сим карта в название
        $resSectIds = CIBlockElement::GetElementGroups($arItem['ID'], true);
        $arSectIds = array();
        while($ar_group = $resSectIds->Fetch()){
            $arSectIds[] = $ar_group["ID"];

            $parentSectionIterator = \Bitrix\Iblock\SectionTable::getList([
                'select' => [
                    'SECTION_ID' => 'SECTION_SECTION.ID',
                    'IBLOCK_SECTION_ID' => 'SECTION_SECTION.IBLOCK_SECTION_ID',
                ],
                'filter' => [
                    '=ID' => $ar_group["ID"],
                ],
                'runtime' => [
                    'SECTION_SECTION' => [
                        'data_type' => '\Bitrix\Iblock\SectionTable',
                        'reference' => [
                            '=this.IBLOCK_ID' => 'ref.IBLOCK_ID',
                            '>=this.LEFT_MARGIN' => 'ref.LEFT_MARGIN',
                            '<=this.RIGHT_MARGIN' => 'ref.RIGHT_MARGIN',
                        ],
                        'join_type' => 'inner'
                    ],
                ],
            ]);

            while ($parentSection = $parentSectionIterator->fetch()) {
                $arSectIds[] = $parentSection['SECTION_ID'];
                //$parentSections[$parentSection['SECTION_ID']] = $parentSection;
            }

        }

        $itemName = $arItem['NAME'];


        $entity = \Bitrix\Iblock\Model\Section::compileEntityByIblock($arItem['IBLOCK_ID']);
        $rsSection = $entity::getList(array(
            "select" => array("UF_ADD_SIM", "ID"),
            "filter" => array("ACTIVE" => "Y", "GLOBAL_ACTIVE" => "Y", 'ID' => $arSectIds)
        ));


        $addSim = false;
        while ($arSection=$rsSection->fetch()){
            if(!empty($arSection['UF_ADD_SIM']) && $arSection['UF_ADD_SIM']){
                $addSim = true;
            }
        }

        if($addSim){
            if($element = \Bitrix\Iblock\Iblock::wakeUp($arItem['IBLOCK_ID'])->getEntityDataClass()::getList([
                'select' => ['ID', 'PROP_SIM.ITEM'],
                'filter' => [
                    'ID' => $arItem['ID'],
                ],
            ])->fetchObject()){
                if($prop = $element->getPropSim()){
                    $itemName .= ' ' . $prop->getItem()->getValue();
                }
            }
        }
        //-- сим карта в название

        $groupRes = CIBlockElement::GetElementGroups($arItem['ID'], true, array());
        while ($group = $groupRes->Fetch()) {
            $itemSectionID = $group['IBLOCK_SECTION_ID'];
        }

        $arProduct = CCatalogProduct::GetByID( $arItem['ID'] );
        $pathList = CIBlockSection::GetNavChain($arItem['IBLOCK_ID'], $itemSectionID, array(), true);

        $sectionArr = array(
            "ID" => $pathList[array_key_last($pathList)]["ID"],
            "NAME" => $pathList[array_key_last($pathList)]["NAME"],
        );

        $propsRes = CIBlockElement::GetList(array(), array("IBLOCK_ID" => $arItem['IBLOCK_ID'], "ID" => $arItem['ID']), false, array(), array("ID", "NAME", "CATALOG_GROUP_1", "PROPERTY_OLD_PRICE", "IBLOCK_SECTION_ID", "PROPERTY_PROP_COLOR", "PROPERTY_PROP_BRAND"));
        while ($props = $propsRes->GetNextElement()) {
            $listFields = $props->GetFields();
            $itemPropVariant = $listFields['PROPERTY_PROP_COLOR_VALUE'];
            $itemPropBrand = $listFields['PROPERTY_PROP_BRAND_VALUE'];

            if (isset($itemPropBrand) && $itemPropBrand == $pathList[array_key_last($pathList)]["NAME"]) {
                end($pathList);
                $prevSection = prev($pathList);
                $sectionArr["ID"] = $prevSection["ID"];
                $sectionArr["NAME"] = $prevSection["NAME"];
            }
        }

        $arReturn[]=array(
            'ID'=>$arItem['ID'],
            'NAME'=>$itemName,
            'IMAGE'=>$image,
            'SECOND_IMAGE'=>$secondImage,
            'DETAIL_PAGE_URL'=>$arItem['DETAIL_PAGE_URL'],
            'PRICE'=>$price,
            'OLD_PRICE'=>$arItem["PROPERTIES"]["OLD_PRICE"]["VALUE"],
            'RATING'=>$arItem["PROPERTIES"]["RATING"]["VALUE"],
            'TREND'=>$arItem["PROPERTIES"]["TREND"]["VALUE_XML_ID"],
            'VIGODA'=>$arItem["PROPERTIES"]["VIGODA"]["VALUE_XML_ID"],            
            'NEW'=>$arItem["PROPERTIES"]["NEW"]["VALUE_XML_ID"],
            'BRAND'=>$itemPropBrand,
            'VARIANT'=>$itemPropVariant,
            'QUANTITY'=>$arProduct['QUANTITY'],
            'INDEX'=>$key,
            'ROOT_SECTION_ID'=>$pathList[0]["ID"],
            'ROOT_SECTION_NAME'=>$pathList[0]["NAME"],
            'SECTION_ID'=>$sectionArr["ID"],
            'SECTION_NAME'=>$sectionArr["NAME"],
            'PATH_LIST'=>$pathList,
            'PREORDER' => $arItem["PROPERTIES"]["PREORDER"]["VALUE_XML_ID"],
            'PREORDER_DATE' => $arItem["PROPERTIES"]["PREORDER_DATE"]["VALUE"],
        );
    }

    return $arReturn;
}

function getItemData($itemId){
    CModule::IncludeModule('iblock');
    $res = CIBlockElement::GetById($itemId);
    $fields = $res->GetNext();
    if(intval($fields["PREVIEW_PICTURE"])){
        $fields["PREVIEW_PICTURE"] = CFile::GetFileArray($fields['PREVIEW_PICTURE']);
    }else{
        $db_props = CIBlockElement::GetProperty($fields['IBLOCK_ID'], $fields['ID'], array("sort" => "asc"), Array("CODE"=>"GALLERY"));
        if($ar_props = $db_props->Fetch()){
            if(intval($ar_props['VALUE'])){
                $fields["PREVIEW_PICTURE"] = CFile::GetFileArray($ar_props['VALUE']);
            }
        }
    }

    $quantity = 1;
    global $USER;
    $arPrice = CCatalogProduct::GetOptimalPrice($fields['ID'], $quantity, $USER->GetUserGroupArray(), $renewal);
    if (!$arPrice || count($arPrice) <= 0)
    {
        if ($nearestQuantity = CCatalogProduct::GetNearestQuantityPrice($fields['ID'], $quantity, $USER->GetUserGroupArray()))
        {
            $quantity = $nearestQuantity;
            $arPrice = CCatalogProduct::GetOptimalPrice($fields['ID'], $quantity, $USER->GetUserGroupArray(), $renewal);
        }
    }

    $fields["MIN_PRICE"]["DISCOUNT_VALUE"] = $arPrice['DISCOUNT_PRICE'];

    $propRes = CIBlockElement::GetProperty($fields['IBLOCK_ID'], $fields['ID'], array('sort'=>'asc'), array('CODE'=>'OLD_PRICE'));
    $propFields = $propRes->GetNext();
    $fields["PROPERTIES"]["OLD_PRICE"]["VALUE"] = $propFields['VALUE'];

    $propRes = CIBlockElement::GetProperty($fields['IBLOCK_ID'], $fields['ID'], array('sort'=>'asc'), array('CODE'=>'RATING'));
    $propFields = $propRes->GetNext();
    $fields["PROPERTIES"]["RATING"]["VALUE"] = $propFields['VALUE'];

    $propRes = CIBlockElement::GetProperty($fields['IBLOCK_ID'], $fields['ID'], array('sort'=>'asc'), array('CODE'=>'TREND'));
    $propFields = $propRes->GetNext();
    $fields["PROPERTIES"]["TREND"]["VALUE"] = $propFields['VALUE_XML_ID'];

    $propRes = CIBlockElement::GetProperty($fields['IBLOCK_ID'], $fields['ID'], array('sort'=>'asc'), array('CODE'=>'NEW'));
    $propFields = $propRes->GetNext();
    $fields["PROPERTIES"]["NEW"]["VALUE"] = $propFields['VALUE_XML_ID'];

    $propRes = CIBlockElement::GetProperty($fields['IBLOCK_ID'], $fields['ID'], array('sort'=>'asc'), array('CODE'=>'PREORDER'));
    $propFields = $propRes->GetNext();
    $fields["PROPERTIES"]["PREORDER"]["VALUE_XML_ID"] = $propFields['VALUE_XML_ID'];

    $propRes = CIBlockElement::GetProperty($fields['IBLOCK_ID'], $fields['ID'], array('sort'=>'asc'), array('CODE'=>'PREORDER_DATE'));
    $propFields = $propRes->GetNext();
    $fields["PROPERTIES"]["PREORDER_DATE"]["VALUE"] = $propFields['VALUE'];

    $catalogArr = CCatalogProduct::GetByID($itemId);
    $fields['QUANTITY'] = $catalogArr['QUANTITY'];

    return $fields;
}

function getStringMonth($month){
    if(!$month) return '';

    $arMonth = array('','января','февраля','марта','апреля','мая','июня','июля','августа','сентября','октября','ноября','декабря');

    return $arMonth[$month];
}

function checkGoogleCaptcha($response) {

    global $APPLICATION;

    if ($response) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => 'https://www.google.com/recaptcha/api/siteverify',
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => [
                'secret' => RE_SEC_KEY,
                'response' => $response,
                'remoteip' => $_SERVER['REMOTE_ADDR']
            ],
            CURLOPT_RETURNTRANSFER => true
        ]);

        $output = curl_exec($ch);
        curl_close($ch);
        $result = json_decode($output, true);

        if ($result['success'] !== true) {
            return false;
        }
            return true;
    } else {
        return false;
    }
}

function isAuthorized(){
    global $USER;
    return $USER->IsAuthorized();
}

function formatPhone($phone){
    $phone = preg_replace('~\D+~','', $phone);

    if(substr($phone, 0, 1) == 8){
        $phone = substr_replace($phone, '7', 0, 1);
    }

    return $phone;
}

AddEventHandler("main", "OnBeforeUserRegister", "PhoneToLogin");
AddEventHandler("main", "OnBeforeUserAdd", "PhoneToLogin");
AddEventHandler("main", "OnBeforeUserUpdate", "PhoneToLogin");
function PhoneToLogin(&$arFields) {
  if($arFields['LOGIN'] != 'admin' && $arFields['LOGIN'] != 'exchange'){
    if($arFields['PERSONAL_PHONE']){
      $arFields['LOGIN'] = formatPhone($arFields['PERSONAL_PHONE']);
    }else{
      $arFields['PERSONAL_PHONE'] = $arFields['LOGIN'];
    }
  }
}


AddEventHandler("sale", "OnSaleStatusOrderChange", "SendSms");
function SendSms($event)
{
    CModule::IncludeModule('sale');
    if ($arStatus = CSaleStatus::GetByID($event->getField('STATUS_ID'))){
        if($arStatus['NOTIFY'] == 'Y'){
            $propertyCollection = $event->getPropertyCollection();
            $phoneProp = $propertyCollection->getPhone();
            if(!empty($phoneProp->getValue())){
            // $arStatus['NAME']
            // $arStatus['DESCRIPTION']
                CModule::IncludeModule('vpl.smsru');
                $smsTool = new \VPL\Smsru\VPLSMSRuTools();
                $result = $smsTool->Send($phoneProp->getValue(), 'Статус Вашего заказа №'.$event->getField('ACCOUNT_NUMBER').' изменен на "'.$arStatus['NAME'].'"');
            }
        }
    }
}

AddEventHandler("sale", "OnOrderNewSendEmail", "ModifyOrderSaleMails");
function ModifyOrderSaleMails($orderID, &$eventName, &$arFields)
{
       if(CModule::IncludeModule("sale") && CModule::IncludeModule("iblock"))
       {

      CModule::IncludeModule("iblock");
      CModule::IncludeModule("sale");


      $dbBasketItems = CSaleBasket::GetList(
          array("NAME" => "ASC"),
          array("ORDER_ID" => $orderID),
          false,
          false,
          array("PRODUCT_ID", "ID", "NAME", "QUANTITY", "PRICE", "BASE_PRICE", "CURRENCY", "DETAIL_PAGE_URL")
        );


        $orderList .= '<table style="font-size:14px;border-spacing: 0; border-collapse: collapse; width: 100%; margin-bottom: 30px;">';
        $orderList .= '<tbody>';

        while ($arProps = $dbBasketItems->Fetch())
        {

            $res = CIBlockElement::GetList(array(), array('ID'=>$arProps['PRODUCT_ID']),false, false, array('ID','IBLOCK_ID',
              'PREVIEW_PICTURE',
          ));
          $itemFields = $res->GetNext();
          $img = false;
          if(intval($itemFields["PREVIEW_PICTURE"])){
            $img = CFile::GetFileArray($itemFields['PREVIEW_PICTURE']);
        }else{
            $db_props = CIBlockElement::GetProperty($itemFields['IBLOCK_ID'], $itemFields['ID'], array("sort" => "asc"), Array("CODE"=>"GALLERY"));
            if($ar_props = $db_props->Fetch()){
                if(intval($ar_props['VALUE'])){
                    $img= CFile::GetFileArray($ar_props['VALUE']);
                }
            } else {
                $img ='/local/img/svg/no-photo.svg';
            }
        }


          if($itemFields['IBLOCK_ID']==CATALOG_ID){

          }else{
            $mxResult = CCatalogSku::GetProductInfo($itemFields["ID"]);
            if (is_array($mxResult))
            {

              $elemRes = CIBlockElement::GetList(array(), array('IBLOCK_ID'=>IB_CATALOG_ID, 'ID'=>$mxResult['ID']), false, false,
                array('ID', 'NAME', 'PREVIEW_PICTURE'));
              $elemFields = $elemRes->GetNext();

              if(!$itemFields['PROPERTY_ARTICLE_VALUE'])
                $itemFields['PROPERTY_ARTICLE_VALUE'] = $elemFields['PROPERTY_ARTICLE_VALUE'];
              /*
              $db_props = CIBlockElement::GetProperty(CATALOG_ID, $mxResult['ID'], array("sort" => "asc"), Array("CODE"=>"ARTICLE"));
              $ar_props = $db_props->Fetch();
              $itemFields['PROPERTY_ARTICLE_VALUE'] = $ar_props["VALUE"];
              */

/*
              $db_props = CIBlockElement::GetProperty($itemFields['IBLOCK_ID'], $itemFields["ID"], array("sort" => "asc"), Array("CODE"=>"SIZE"));
              $ar_props = $db_props->Fetch();
              $itemFields['PROPERTY_SIZE_VALUE'] = $ar_props["VALUE"];
*/

            }
          }

          $arMeasure = \Bitrix\Catalog\ProductTable::getCurrentRatioWithMeasure($arProps['PRODUCT_ID']);

          $orderList .= '<tr style="border-bottom:1px solid #EEEBF8; padding: 16px 0;">';
          $orderList .= '<td style="padding: 15px 0;padding-right:15px;width:63px;">';
          $orderList .= '  <a href="'.$arProps["DETAIL_PAGE_URL"].'" class="img" style="display:block; min-width:50px;max-width:50px;margin: 0 auto;">';
          $orderList .= '    <img src="https://'.SITE_SERVER_NAME.$img["SRC"].'" alt="'.$arProps['NAME'].'" title="'.$arProps['NAME'].'" style="max-width:100%;vertical-align:top;width: 100%;height: 100%;object-fit:contain;" />';
          $orderList .= '  </a>';
          $orderList .= '</td>';
          $orderList .= '<td style="padding: 15px 0; padding-right:10px;">';
          $orderList .= '  <a href="'.$arProps["DETAIL_PAGE_URL"].'" style="font-weight: 700;font-size: 12px;line-height: 16px;color:#000;text-decoration:none;display:inline-block;">'.$arProps['NAME'].'</a>';
         /* if($itemFields['PROPERTY_ARTICLE_VALUE']){
          $orderList .= '  <p style="margin: 0;color:#6c6c6c;font-size: 12px;line-height:24px;">Артикул: '.$itemFields['PROPERTY_ARTICLE_VALUE'].'</p>';
          }*/
          $orderList .= '</td>';
          $orderList .= '<td style="padding: 12px 15px;line-height:1.3;">';
          $orderList .= '  <div class="prices">';
          $orderList .= '    <div class="price" style="font-size:14px;font-weight:700;line-height: 13px;white-space:nowrap;">'.number_format($arProps["PRICE"], 2, '.', ' ').' руб.</div>';
          if($arProps["PRICE"] != $arProps["BASE_PRICE"]){
            $orderList .= '    <div style="white-space:nowrap;">';
            $orderList .= '      <div class="price-old" style="display:inline-block;font-weight: 400;font-size: 10px;line-height: 13px;color:#4b74a0;text-decoration:line-through;margin-right:5px;vertical-align:middle;">'.number_format($arProps["BASE_PRICE"], 2, '.', ' ').' руб.</div>';
            $orderList .= '      <div class="price-percent" style="display:inline-block;font-weight: 400;font-size: 10px;line-height: 13px;color:#4b74a0;vertical-align:middle;">('.intval(100 - (($arProps["PRICE"] / $arProps["BASE_PRICE"]) * 100)).'%)</div>';
            $orderList .= '    </div>';
            }
          $orderList .= '  </div>';
          $orderList .= '</td>';
          $orderList .= '<td style="padding: 12px 0;padding-right:15px;white-space:nowrap; font-weight: 400;font-size: 13px;line-height: 16px;">';
          $orderList .= '  '.$arProps["QUANTITY"].' '.$arMeasure[$arProps['PRODUCT_ID']]['MEASURE']['SYMBOL_RUS'];
          $orderList .= '</td>';
          $orderList .= '<td style="padding: 12px 0;padding-right:5px;line-height:1.3;">';
          $orderList .= '  <div class="prices">';
          $orderList .= '    <div class="price" style="font-weight: 700;font-size: 15px;line-height: 14px;;white-space:nowrap;">'.number_format($arProps["PRICE"] * $arProps["QUANTITY"], 2, '.', ' ').' руб.</div>';
          $orderList .= '  </div>';
          $orderList .= '</td>';
          $orderList .= '</tr>';

        }
        $orderList .= '</tbody>';
        $orderList .= '</table>';
        $arFields['ORDER_LIST'] = $orderList;
    }
}


AddEventHandler("catalog", "OnGetOptimalPrice", "ModifyGetOptimalPrice");
function ModifyGetOptimalPrice($productId){
    $priceTypeCode = 'BASE'; // код типа цены, исполбзующийся на сайте
    try {
         $arPrice = \Bitrix\Catalog\Model\Price::getList([
             'filter' => [
                 'PRODUCT_ID' => $productId,
                 'CATALOG_GROUP.NAME' => $priceTypeCode
             ],
             'select' => ['*']
         ])->fetch();
         if ($arPrice) {
             $basePrice = [
                 'ID'                => $arPrice['ID'],
                 'CATALOG_GROUP_ID'  => $arPrice['CATALOG_GROUP_ID'],
                 'PRICE'             => $arPrice['PRICE'],
                 'CURRENCY'          => $arPrice['CURRENCY'],
                 'ELEMENT_IBLOCK_ID' => $arPrice['PRODUCT_ID'],
             ];
             return ['PRICE' => $basePrice];
         }
     } catch (\Exception) {}
     return null;
}

$eventManager = \Bitrix\Main\EventManager::getInstance();
$eventManager->addEventHandler('sale', 'onSaleDeliveryHandlersClassNamesBuildList', 'addCustomDeliveryServices');

function addCustomDeliveryServices(\Bitrix\Main\Event $event) {
    $result = new \Bitrix\Main\EventResult(
        \Bitrix\Main\EventResult::SUCCESS, 
        array(
            '\YandexExpressDelivery\YandexExpressDeliveryHandler' => '/local/php_interface/include/sale_delivery/yandexExpDelivery/YandexExpressDeliveryHandler.php'
        )
    );

    return $result;
}

# Connect Bitrix for YandexDelivery
use Bitrix\Main\Loader; 
Loader::includeModule("highloadblock"); 
use Bitrix\Highloadblock as HL; 
use Bitrix\Main\Entity;

$eventManager->addEventHandler(
    "sale",
    "OnSaleStatusOrder",
    "OnSaleStatusOrder"
);
 
function OnSaleStatusOrder($orderID, $status) {
    if ($status == 'C') {

        // GET TIME NOW
        $datetime = new DateTime();
        $nowTimestamp = $datetime->getTimestamp();
        $years = date('y');
        $months = date('m');
        $days = date('d');
        $hours = date('H');
        $minutes = date('i');

        # Set Properpties
        $methodDeliveryID = YANDEX_DELIVERY_METHOD_DELIVERY_ID;
        $hlbl = YANDEX_DELIVERY_HLBLOCK_ID; // Указываем ID нашего highloadblock блока к которому будет делать запросы
        $hlblock = HL\HighloadBlockTable::getById($hlbl)->fetch(); 

        $entity = HL\HighloadBlockTable::compileEntity($hlblock); 
        $entity_data_class = $entity->getDataClass(); 

        $rsData = $entity_data_class::getList(
            [
                'select' => [
                    '*'
                ],

                'order' => [
                    'ID' => 'ASC'
                ],

                'filter' => [
                    'UF_3_1_ORDER_ID' => $orderID 
                ]
            ]
        );

        $arData = $rsData->Fetch();
        $json = json_decode($arData['UF_3_1_JSON'], true);
        $arFields = json_decode($arData['UF_3_1_AR_FIELDS'], true);

        if ($arFields['DELIVERY_ID'] == $methodDeliveryID && $arFields['PRICE_DELIVERY'] != 0 && $arFields['PRICE_DELIVERY'] != NULL) {
            # GET ORDER
            $order = Bitrix\Sale\Order::load($orderID);
            
            # GET DATE_INSERT RECORD IN HLBLOCK
            # DATETIME OBJECT
            $dateInsertRecordHL = $order->getField('DATE_INSERT');
            # TIMESTAMP OBJECT
            $timeInsert = $dateInsertRecordHL->getTimestamp();
            
            if ( ($nowTimestamp - $timeInsert) > YANDEX_DELIVERY_LIFETIME_PAYLOAD){
                
                $items = [];

                foreach ($json['items'] as $item) {
                    $items[] = [
                        'quantity' => $item['quantity'],
                        // 'size' => $item['size'],
                        // 'weight' => $item['weight']
                    ];
                }

                $requirements = [
                    'pro_courier' => false,
                    'skip_door_to_door' => false,
                    'taxi_classes' => [
                        'express'
                    ]
                ];

                $route_points = [];

                foreach ($json['route_points'] as $point) {
                    $route_points[] = [
                        'coordinates' => $point['address']['coordinates'],
                        'fullname' => $point['address']['fullname']
                    ];
                }

                $request = [
                    'items' => $items,
                    'requirements' => $requirements,
                    'route_points' => $route_points
                ];

                $request = json_encode($request);
                $req = curl_init();
                curl_setopt_array($req, [
                    CURLOPT_URL => YANDEX_DELIVERY_CALCULATE_PRICE_URL_PRODUCTION,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_ENCODING => "",
                    CURLOPT_MAXREDIRS => 10,
                    CURLOPT_TIMEOUT => 30,
                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                    CURLOPT_CUSTOMREQUEST => "POST",
                    CURLOPT_POSTFIELDS => $request,
                    CURLOPT_HTTPHEADER => [
                        "Accept-Language: ru",
                        "Authorization: Bearer ".YANDEX_DELIVERY_API_KEY,
                        "Content-Type: application/json"
                    ],
                ]);

                # SEND REQUEST
                $res = curl_exec($req);
                curl_close($req);
                
                # PRINT RESULT
                $response = json_decode($res, true);

                // --- DEBUG ---
                $fp = fopen($_SERVER["DOCUMENT_ROOT"]."/logs/order-".$orderID."-calc.txt", 'w+');
                fwrite($fp, print_r($response, TRUE));
                fclose($fp);
                // --- DEBUG ---

                $search = false;
                foreach ($response['offers'] as $it) {
                    switch ($it['description']) {
                        case YANDEX_DELIVERY_DEFAULT_CALCULATE_DESCRIPTION_FOR_PRICE:
                            $json['offer_payload'] = $it['payload'];
                            $search = true;

                            $fp = fopen($_SERVER["DOCUMENT_ROOT"]."/logs/order-".$orderID."-2h.txt", 'w+');
                            fwrite($fp, print_r($it, TRUE));
                            fclose($fp);

                            break;
                        case YANDEX_DELIVERY_DEFAULT_CALCULATE_DESCRIPTION_FOR_PRICE_ALT:
                            $json['offer_payload'] = $it['payload'];
                            $search = true;

                            $fp = fopen($_SERVER["DOCUMENT_ROOT"]."/logs/order-".$orderID."-1h.txt", 'w+');
                            fwrite($fp, print_r($it, TRUE));
                            fclose($fp);
                            
                            break;
                        case YANDEX_DELIVERY_DEFAULT_CALCULATE_DESCRIPTION_FOR_PRICE_ALT_ALT:
                            $json['offer_payload'] = $it['payload'];
                            $search = true;

                            $fp = fopen($_SERVER["DOCUMENT_ROOT"]."/logs/order-".$orderID."-4h.txt", 'w+');
                            fwrite($fp, print_r($it, TRUE));
                            fclose($fp);

                            break;
                    }

                    if ($search) {
                        break;
                    }
                }
                
            }

            $json = json_encode($json);

            // --- DEBUG ---
            $fp3 = fopen($_SERVER["DOCUMENT_ROOT"]."/logs/order-".$orderID."-json.txt", 'w+');
            fwrite($fp3, print_r($json, TRUE));
            fclose($fp3);
            // --- DEBUG ---

            $curl = curl_init();

            # SETUP REQUEST TO SEND JSON VIA POST
            curl_setopt_array($curl, [
                CURLOPT_URL => YANDEX_DELIVERY_CREATE_ORDER_URL_PRODUCTION.$orderID,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "POST",
                CURLOPT_POSTFIELDS => $json,
                CURLOPT_HTTPHEADER => [
                "Accept-Language: RU",
                "Authorization: Bearer ".YANDEX_DELIVERY_API_KEY,
                "Content-Type: application/json"
                ],
            ]);
            
            $res = curl_exec($curl);
            $err = curl_error($curl);

            // --- DEBUG ---
            $fp = fopen($_SERVER["DOCUMENT_ROOT"]."/logs/order-".$orderID."-result.txt", 'a+');
            fwrite($fp, print_r($res, TRUE));
            fclose($fp);

            if ($err > 0){
                $fp = fopen($_SERVER["DOCUMENT_ROOT"]."/logs/order-".$orderID."-errors.txt", 'a+');
                fwrite($fp, print_r($err, TRUE));
                fclose($fp);
            }
            // --- DEBUG ---

            curl_close($curl);
        }
    }
}

$eventManager->addEventHandler(
        "sale",
        "OnOrderAdd",
        "OnOrderAdd"
    );

function OnOrderAdd($ID, $arFields) {
    $hlbl = YANDEX_DELIVERY_HLBLOCK_ID;
    $hlblock = \Bitrix\Highloadblock\HighloadblockTable::getById($hlbl)->fetch();

    $entity = \Bitrix\Highloadblock\HighloadblockTable::compileEntity($hlblock);
    $entity_data_class = $entity->getDataClass();

    $methodDeliveryID = YANDEX_DELIVERY_METHOD_DELIVERY_ID;
    $coordsDeliveryID = YANDEX_DELIVERY_END_ADDRESS_COORDS_FIELD_ID;
    $payloadID = YANDEX_DELIVERY_PAYLOAD_FIELD_ID;

    // --- DEBUG ---
    // $file = fopen($_SERVER["DOCUMENT_ROOT"]."/logs/order-".$ID."-arFields.txt", 'w+');
    // fwrite($file, print_r($arFields, TRUE));
    // fclose($file);
    // --- DEBUG ---

    # WRITE DATA IN HL
    # CHECK YANDEX DELIVERY AND PRICE NOT NULL OR ZERO
    if ($arFields['DELIVERY_ID'] == $methodDeliveryID && $arFields['PRICE_DELIVERY'] != 0 && $arFields['PRICE_DELIVERY'] != NULL) {

        # GET BASKETITEMS
        $items = [];

        # ID DELIVERY POINT
        $dropPointId = random_int(0, 9999999);
        # ID STORE - так как только один в Москве, то id = 1
        $pickupPointId = 1;

        foreach ($arFields['BASKET_ITEMS'] as $item) {

            # GET DIMENSIONS
            // $dimensions = unserialize($item['DIMENSIONS']);
            // $heightStr = number_format((float) $dimensions['HEIGHT'], 2, '.', '');
            // $lengthStr = number_format((float) $dimensions['LENGTH'], 2, '.', '');
            // $widthStr = number_format((float) $dimensions['WIDTH'], 2, '.', '');

            $items[] = [
                'cost_currency' => $item['CURRENCY'],
                'cost_value' => (STRING) $item['PRICE'],
                'droppof_point' => $dropPointId,
                'pickup_point' => $pickupPointId,
                'quantity' => (INTEGER) $item['QUANTITY'],

                // 'size' => [
                //     'height' => (DOUBLE) $heightStr / 1000,
                //     'length' => (DOUBLE) $lengthStr / 1000,
                //     'width' => (DOUBLE) $widthStr / 1000,
                // ],

                // 'weight' => (INTEGER) $item['WEIGHT'] / 1000,
                'title' => $item['NAME']
            ];
        }

        # SET USER DATA
        $userName = $arFields["ORDER_PROP"][6];
        $userPhone = $arFields["ORDER_PROP"][4];

        # GET ROUTE POINTS
        $unformat_coords = $arFields['ORDER_PROP'][$coordsDeliveryID];

        # FORMAT END COORDS
        $coords = explode(', ', $unformat_coords);
        $coords[0] = (DOUBLE) $coords[0];
        $coords[1] = (DOUBLE) $coords[1];

        # ADDRESS PARSING
        $address = $arFields['ORDER_PROP'][1];
        $parsingAddress = explode(',', $address);
        $city = 'Москва';
        $street = $parsingAddress[0];
        $house = $parsingAddress[1];
        $porch = preg_replace("/[^0-9]/", '', $parsingAddress[2]);
        $flat = (INTEGER) preg_replace("/[^0-9]/", '', $parsingAddress[3]);
        $end_address = "г. ".$city.", ".$street.", ".$house;

        # START COORDS
        $start_coords = YANDEX_DELIVERY_DEFAULT_ADDRESS_COORDS;
        $start_address = YANDEX_DELIVERY_DEFAULT_ADDRESS_FULLNAME;

        $offer_payload = $arFields['ORDER_PROP'][$payloadID];

        # SET JSON ARRAY

        # AUTOMATIC ACCEPT ORDER AFTER CREATING
        $json['auto_accept'] = false;

        $json['callback_properties']['callback_url'] = YANDEX_DELIVERY_CALLBACK_URL_CR_OR_DL.$ID."/"; 
        $json['client_requirements']['taxi_class'] = "express";

        # GET ITEMS
        foreach ($items as $value) {
            $json['items'][] = [
                'cost_currency' => $value['cost_currency'],
                'cost_value' => $value['cost_value'],
                'droppof_point' => $value['droppof_point'],
                'pickup_point' => $value['pickup_point'],
                'quantity' => $value['quantity'],
                // 'size' => [
                //     'height' => $value['size']['height'],
                //     'length' => $value['size']['length'],
                //     'width' => $value['size']['width'],
                // ],
                'title' => $value['title'],
                // 'weight' => $value['weight']
            ];
        }

        $json['offer_payload'] = $offer_payload;

        # Set route_points
        # Start point
        $json['route_points'][0] = [
            'address' => [
                'coordinates' => $start_coords,
                'fullname' => $start_address,
            ],

            # SENDER
            'contact' => [
                'name' => YANDEX_DELIVERY_CONTACT_NAME,
                'phone' => YANDEX_DELIVERY_CONTACT_PHONE,
            ],

            # VISIT ORDER - порядок посещения точки
            'point_id' => $pickupPointId,
            'type' => 'source',
            'visit_order' => 1
        ];

        # FINISH POINT
        $json['route_points'][1] = [
            'address' => [
                'coordinates' => $coords,
                'fullname' => $end_address,
                'city' => $city,
                'street' => $street,
                'building' => $house,
                'flat' => $flat,
                'porch' => $porch
            ],

            'buyout' => [
                'payment_method' => 'card'
            ],

            'contact' => [
                'name' => $userName,
                'phone' => $userPhone,
            ],

            'point_id' => $dropPointId,
            'type' => 'destination',
            'visit_order' => 2
        ];

        $json = json_encode($json);
        $arFields = json_encode($arFields);

        $data = [
            'UF_3_1_ORDER_ID' => $ID,
            'UF_3_1_JSON' => $json,
            'UF_3_1_AR_FIELDS' => $arFields
        ];


        $result = $entity_data_class::add($data);
    }
}

AddEventHandler('catalog', 'OnProductUpdate', ['iblockUpdateClass', 'OnAfterIBlockElementUpdateHandler']);
class iblockUpdateClass {
	public static function OnAfterIBlockElementUpdateHandler($id, &$arFields) {
        CModule::IncludeModule('iblock');

        $data = array(
            'REMAINDER_FOR_1C' => (INTEGER) $arFields['QUANTITY']
        );

        $el = new CIBlockElement;
        $res = CIBlockElement::SetPropertyValuesEx($id, $arFields['IBLOCK_ID'], $data);
	}
}
$eventManager = Main\EventManager::getInstance();

$eventManager->addEventHandler('yandex.market', 'onExportOfferWriteData', function(Main\Event $event) {

	/** @var $tagResultList Market\Result\XmlNode[] */
	/** @var $elementList array */
	/** @var $context array */
	/** @var $parentList array */
	/** @var $tagElement \SimpleXMLElement */
	$tagResultList = $event->getParameter('TAG_RESULT_LIST');
	$elementList = $event->getParameter('ELEMENT_LIST');
	$context = $event->getParameter('CONTEXT');
	$parentList = $event->getParameter('PARENT_LIST');

	foreach ($tagResultList as $elementId => $tagResult)
	{
		if ($tagResult->isSuccess())
		{
			$tagNode = $tagResult->getXmlElement();
			$element = $elementList[$elementId];
			$parent = null;

			if (isset($element['PARENT_ID']))
			{
				$parent = $parentList[$element['PARENT_ID']];

				$tagNode->addChild('offer_id', $parent['ID']);
			}
            $fp = fopen($_SERVER["DOCUMENT_ROOT"]."/yml_LOG.txt", 'a+'); // Открываем файл в режиме записи при этом указатель сдвигается на последний байт файла
            //fwrite($fp, print_r($context, TRUE));
            fclose($fp);
            if ($context['SETUP_ID']==11){$tagNode->addChild('min_price',$tagNode->price);}
			$tagResult->invalidateXmlContents();
		}
	}

});

\Bitrix\Main\EventManager::getInstance()->addEventHandler(
    'sale',
    'OnSaleStatusOrder',
    'sendSdekOrder'
);

function sendSdekOrder($orderId, $status) {
    $fp = fopen($_SERVER["DOCUMENT_ROOT"]."/ASendSdekORder.txt", 'w+');

    fwrite($fp, print_r([
        'orderId' => $orderId,
        'status' => $status
    ], TRUE));
    fclose($fp);

    if ($status == 'C') {
        $fp = fopen($_SERVER["DOCUMENT_ROOT"]."/AinsurIN.txt", 'w+');
        fwrite($fp, print_r('YES', TRUE));
        fclose($fp);

        \Bitrix\Main\Loader::includeModule('sale');
        // GET ORDER
        $order = \Bitrix\Sale\Order::load($orderId);

        $fp = fopen($_SERVER["DOCUMENT_ROOT"]."/AinsurORDER.txt", 'w+');
        fwrite($fp, print_r($order, TRUE));
        fclose($fp);

        // GET ORDER DATA
        $propertyCollection = $order->getPropertyCollection();
        $arrProps = $propertyCollection->getArray();

        $arrOrderData = [];
        $arrOrderData['total_price'] = $order->getPrice() - $order->getField('PRICE_DELIVERY');
        foreach ($arrProps['properties'] as $prop) {
            if ($prop['IS_ADDRESS'] == 'Y') {
                $arrOrderData['address'] = $prop['VALUE'][0];
            }

            if ($prop['CODE'] == 'FIO') {
                $arrOrderData['name'] = $prop['VALUE'][0];
            }

            if ($prop['IS_EMAIL'] == 'Y') {
                $arrOrderData['email'] = $prop['VALUE'][0];
            }

            if ($prop['IS_PHONE'] == 'Y') {
                $arrOrderData['phone'] = $prop['VALUE'][0];
            }

            if ($prop['CODE'] == 'IPOLSDEK_CNTDTARIF') {
                $arrOrderData['service'] = $prop['VALUE'][0];
            }

            if ($prop['CODE'] == 'IPOLSDEK_INSURANCE') {
                $arrOrderData['insurance'] = $prop['VALUE'][0];
            }
        }

        if ($arrOrderData['address']) {
            $pos = strpos($arrOrderData['address'], "#S");

            // Извлекаем подстроку, начиная с позиции "#S"
            $arrOrderData['PST'] = substr($arrOrderData['address'], $pos + 2);
        }

        include_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/js/'.CDeliverySDEK::$MODULE_ID.'/jsloader.php');
        $geoData = json_decode($_COOKIE['geoData'], true);

        $requestContent = [
            'isBeznal'          => 'Y',
            'minVats'           => $arrOrderData['insurance'],
            'comment'           => null,
            'estimatedCost'     => $arrOrderData['total_price'],
            'service'           => $arrOrderData['service'],
            'location'          => sdekHelper::getCity($geoData['code']),
            'name'              => $arrOrderData['name'],
            'email'             => $arrOrderData['email'],
            'phone'             => $arrOrderData['phone'],
            'NDSGoods'          => 'VATX',
            'NDSDelivery'       => 'VATX',
            'address'           => $arrOrderData['address'],
            'PST'               => $arrOrderData['PST'],
            'from_loc_street'   => 'Барклая',
            'from_loc_house'    => '10',
            'from_loc_flat'     => '1',
            'sender_company'    => 'ID-STORE',
            'sender_name'       => 'Михаил',
            'sender_phone'      => '+79111234567',
            'seller_name'       => 'ID-STORE',
            'seller_phone'      => '+79111234567',
            'seller_address'    => 'Барклая 10',
            'account'           => '4',
            'GABS[D_L]'         => '40',
            'GABS[D_W]'         => '30',
            'GABS[D_H]'         => '20',
            'GABS[W]'           => '1',
            'isdek_action'      => 'saveAndSend',
            'orderId'           => $orderId,
            'mode'              => 'order',
            'isdek_token'       => sdekHelper::getModuleToken()
        ];

        $requestContent1 = [
            'price'             => $arrOrderData['total_price'],
            'tarif'             => $arrOrderData['service'],
            'cityTo'            => sdekHelper::getCity($geoData['code']),
            'account'           => '4',
            'shipment'          => null,
            'packs'             => null,
            'GABS[D_L]'         => '40',
            'GABS[D_W]'         => '30',
            'GABS[D_H]'         => '20',
            'GABS[W]'           => '1',
            'delivery'          => '119', // get delivery id
            'paysystem'         => '6', // get poysystem
            'person'            => '1', // person
            'isdek_action'      => 'extCountDeliv',
            'orderId'           => $orderId,
            'mode'              => 'order',
            'isdek_token'       => sdekHelper::getWidgetToken()
        ];

        $fp = fopen($_SERVER["DOCUMENT_ROOT"]."/AinsurREQ.txt", 'w+');
        fwrite($fp, print_r($requestContent, TRUE));
        fclose($fp);

        if ($_SERVER['HTTPS'] == 'on') {
            $url = 'https://'.$_SERVER['HTTP_HOST'].'/bitrix/js/ipol.sdek/ajax.php';
        } else {
            $url = 'http://'.$_SERVER['HTTP_HOST'].'/bitrix/js/ipol.sdek/ajax.php';
        } 
        
        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL 			=> $url,
            CURLOPT_RETURNTRANSFER 	=> true,
            CURLOPT_ENCODING 		=> "",
            CURLOPT_MAXREDIRS 		=> 10,
            CURLOPT_TIMEOUT 		=> 30,
            CURLOPT_HTTP_VERSION 	=> CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST 	=> "POST",
            CURLOPT_POSTFIELDS 		=> $requestContent,
            CURLOPT_HTTPHEADER 		=> [
                "Content-Type: multipart/form-data",
            ],
        ]);

        $response = curl_exec($curl);
        $err = curl_error($curl);

        curl_close($curl);

        if ($err) {
            $fp = fopen($_SERVER["DOCUMENT_ROOT"]."/Ainsurance.txt", 'a+');
            fwrite($fp, print_r($err, TRUE));
            fclose($fp);
            // echo json_encode($err);
        } else {
            $updatedString = str_replace("'", '"', $response);
            $arr = json_decode($updatedString, true);

            $fp = fopen($_SERVER["DOCUMENT_ROOT"]."/Ainsurance.txt", 'a+');
            fwrite($fp, print_r($arr, TRUE));
            fwrite($fp, print_r("\n", TRUE));
            fwrite($fp, print_r($response, TRUE));
            fwrite($fp, print_r($updatedString, TRUE));
            fclose($fp);

        }
            
    }
}

AddEventHandler('ipol.sdek', 'onGoodsToRequest', 'reassembleGoods');
function reassembleGoods(&$arTG,$oId){
    $order = \Bitrix\Sale\Order::load($oId);

    $deliveryId = $order->getField('DELIVERY_ID');
    if ($deliveryId == 55) {
        for ($i = 0; $i < count($arTG); $i++) { 
            $arTG[$i]['cstPrice'] = 1;
        }
    } else {
        $propertyCollection = $order->getPropertyCollection();
        $propValue = $propertyCollection->getItemByOrderPropertyId(58)->getValue();

        if ($propValue == 'N') {
            for ($i = 0; $i < count($arTG); $i++) { 
                $arTG[$i]['cstPrice'] = 1;
            }
        }

        $fp = fopen($_SERVER["DOCUMENT_ROOT"]."/AEVENTSDEKe.txt", 'a+');
        fwrite($fp, print_r([
            'arTG' => $arTG,
            'old' => $oId
        ], TRUE));
        fclose($fp);
    }
	
/* s
	$arTG - массив с данными о товарах (указатель)
	$oId - ID заказа
*/

}

// $eventManager = \Bitrix\Main\EventManager::getInstance();
// $eventManager->addEventHandler('sale', 'OnSaleOrderBeforeSaved', ['OrderEvents', 'onBeforeOrderSaveHandler']);

// class OrderEvents {
// 	public static function onBeforeOrderSaveHandler(\Bitrix\Main\Event $event) {

//         $result = $event->getParameter('RESULT');
// 		$shipment = $event->getParameter('SHIPMENT');
// 		$deliveryID = $event->getParameter('DELIVERY_ID');

//         if($shipment->getCollection()->getOrder()->getPaymentSystemId()['0'] == '1'){
//             if($deliveryID == 118 || $deliveryID == 119){
//                 $basketPrice = $shipment->getCollection()->getOrder()->getPrice();
//                 $deliveryPrice = $result->getDeliveryPrice();
//                 $newValue =  $deliveryPrice + ($basketPrice * 0.0075);
//                 $result->setDeliveryPrice($newValue);
//                 //$shipment->setBasePriceDelivery($newValue);
//                 return new \Bitrix\Main\EventResult(
//                     \Bitrix\Main\EventResult::SUCCESS,
//                     array(
//                         "RESULT" => $result,
//                     )
//                 );
//             }
//         }
// 	}
// }

AddEventHandler("sale", "OnOrderSave", "OrderMySave");
function OrderMySave($orderID, $fields, $orderFields){
    // DELIVERY PART
    $order = Bitrix\Sale\Order::load($orderID);
    $deliveryId = $order->getField('DELIVERY_ID');
    $customPriceFlag = $orderFields['ORDER_PROP'][59];
    
    if (($deliveryId == 56 || $deliveryId == 57) && $customPriceFlag != 'Y') {
        $propertyCollection = $order->getPropertyCollection();
        $insurance = $propertyCollection->getItemByOrderPropertyId(58)->getValue();
        
        if ($insurance == 'Y') {
            $propertyCollection = $order->getPropertyCollection();
            $propValue = $propertyCollection->getItemByOrderPropertyId(59);

            $basketPrice = $order->getField('PRICE');
            $deliveryPrice = $order->getField('PRICE_DELIVERY');
            $newValue = $deliveryPrice + ceil(0.0075 * ($basketPrice - $deliveryPrice));

            $shipmentCollection = $order->getShipmentCollection();
            
            foreach($shipmentCollection as $shipment) {
                if(!$shipment->isSystem()) {
                    $shipment->setBasePriceDelivery($newValue, false);
                    $propValue->setValue('Y');
                    $order->save();
                }
            }
        }
    }

    // COUPON PART
    $couponList = \Bitrix\Sale\Internals\OrderCouponsTable::getList(array(
        'select' => array('COUPON'),
        'filter' => array('=ORDER_ID' => $orderID)
    ));		
    while ($coupon = $couponList->fetch())
        {
            $orderdata['promocode']=$coupon['COUPON'];
        }

	if (!empty($orderdata['promocode'])) {
		$order = Bitrix\Sale\Order::load($orderID);
        if ((INTEGER) $order->getPersonTypeId() != 2) {
            $propertyCollection = $order->getPropertyCollection();
            $orderPropertyCode='PROMO_B';
            $property = $propertyCollection->getItemByOrderPropertyCode($orderPropertyCode);
            $property->setValue($orderdata['promocode']);
            $order->save();
        }

	} 
}

function agentSetNewProps()
{
    $now = new DateTime();
    $arSelect = Array("ID");
    $arFilter = Array("IBLOCK_ID"=>5, "<DATE_CREATE"=>$now->modify('-30 day')->format('d.m.Y H:i:s'), array(
		"LOGIC" => "OR",
		array("!PROPERTY_NEW" => false),
		array("!PROPERTY_NEWRAZDEL" => false),
	));
    $res = CIBlockElement::GetList(Array(), $arFilter, false, false, $arSelect);
    while($ob = $res->GetNextElement())
    {
        $arFields = $ob->GetFields();
    
        CIBlockElement::SetPropertyValuesEx($arFields["ID"], false, array("NEW" => false));
        CIBlockElement::SetPropertyValuesEx($arFields["ID"], false, array("NEWRAZDEL" => false));
    }

    return "agentSetNewProps();";
}