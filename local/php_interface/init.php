<?
use Bitrix\Catalog\StoreProductTable;

function isProductAvailableOnStore($productId, $storeId) {
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
    $av = isProductAvailableOnStore($aFields['PRODUCT_ID'], intval($aFields['PROPS']['SKLAD']['VALUE']));
    file_put_contents(
        $_SERVER["DOCUMENT_ROOT"] . "/basket_debug.log",
        print_r(array($av, $aFields['PROPS']['SKLAD']['VALUE']), true)
    );
}