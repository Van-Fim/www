<?
use Bitrix\Catalog\StoreProductTable;

function isProductAvailableOnStore($productId, $storeId)
{
    $storeProduct = StoreProductTable::getList([
        'filter' => [
            '=PRODUCT_ID' => $productId,
            '=STORE_ID' => $storeId
        ],
        'select' => ['AMOUNT', 'QUANTITY_RESERVED']
    ])->fetch();

    if (!$storeProduct) {
        return false; // Нет записи — товара нет на складе
    }

    $available = $storeProduct['AMOUNT'] - $storeProduct['QUANTITY_RESERVED'];
    return $available > 0;
}

AddEventHandler('sale', 'OnBeforeBasketAdd', 'ddOnBeforeBasketAdd');
function ddOnBeforeBasketAdd(&$aFields)
{
    if (!CModule::IncludeModule("sotbit.multibasket") || 
        !CModule::IncludeModule("catalog") || 
        !CModule::IncludeModule("iblock")) {
        return true;
    }
    //$CURRENT_ID = \Sotbit\Multibasket\Multibasket::getCurrentBasket();
    $CURRENT_ID = 2;
    $is_current_exist = isProductAvailableOnStore($aFields['PRODUCT_ID'], intval($CURRENT_ID));
    $is_standart_exist = isProductAvailableOnStore($aFields['PRODUCT_ID'], intval($aFields['PROPS']['SKLAD']['VALUE']));

    if ($is_current_exist == 0 && $is_standart_exist == 0) {
        global $APPLICATION;
        $APPLICATION->ThrowException("Товара нет на складе!");
        return false;
    }
}