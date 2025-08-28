<?php

use Bitrix\Catalog\StoreProductTable;
use Bitrix\Main\Event;
use Bitrix\Main\EventManager;
use Bitrix\Main\EventResult;
use Bitrix\Sale\BasketItem;
use Bitrix\Sale\ResultError;
use Sotbit\Multibasket\Entity\MBasketTable;
use Sotbit\Multibasket\Entity\MBasketItemTable;

// Удаляем стандартный обработчик
UnRegisterModuleDependences(
    'sale',
    'OnSaleBasketItemRefreshData',
    'sotbit.multibasket',
    'Sotbit\Multibasket\Listeners\CheckStoreListener',
    'checkAddedItems'
);

EventManager::getInstance()->addEventHandler(
    'sale',
    'OnSaleBasketItemRefreshData',
    'myCustomBasketCheck'
);
function getProductProperties(int $productId, ?int $iblockId = null): array
{
    if (!$iblockId) {
        $rsProduct = CIBlockElement::GetByID($productId);
        if ($arProduct = $rsProduct->Fetch()) {
            $iblockId = (int) $arProduct['IBLOCK_ID'];
        }
    }

    $properties = [];

    if ($iblockId) {
        $rsProperties = CIBlockElement::GetProperty(
            $iblockId,
            $productId,
            ['sort' => 'asc'],
            ['ACTIVE' => 'Y']
        );

        while ($arProperty = $rsProperties->Fetch()) {
            $properties[$arProperty['CODE']] = $arProperty['VALUE'];
        }
    }

    return $properties;
}

function getCurrentBasketStoreId(int $fuserId): ?int
{
    $list = MBasketTable::getList([
        'filter' => [
            'FUSER_ID' => $fuserId,
            'CURRENT_BASKET' => '1',
        ],
        'limit' => 1,
    ]);

    if ($arBasket = $list->fetch()) {
        return (int) $arBasket['STORE_ID'];
    }

    return null;
}

function myCustomBasketCheck(Event $event)
{
    /** @var BasketItem $basketItem */
    $basketItem = $event->getParameter('ENTITY');
    $productId = (int) $basketItem->getField('PRODUCT_ID');

    if (!$productId) {
        return;
    }

    $productProperties = getProductProperties($productId);
    $fuserId = (int) $basketItem->getField('FUSER_ID');
    $storeId = getCurrentBasketStoreId($fuserId);

    $storeCurrentProduct = StoreProductTable::getList([
        'filter' => [
            '=PRODUCT_ID' => $productId,
            '=STORE_ID' => $storeId,
        ],
        'select' => ['AMOUNT'],
    ])->fetch();

    $amount = $storeCurrentProduct ? (int) $storeCurrentProduct['AMOUNT'] : 0;

    if ($amount > 0) {
        return EventResult::SUCCESS;
    }

    $storeFallbackProduct = StoreProductTable::getList([
        'filter' => [
            '=PRODUCT_ID' => $productId,
            '=STORE_ID' => $productProperties['SKLAD'],
        ],
        'select' => ['AMOUNT'],
    ])->fetch();

    $amount = $storeFallbackProduct ? (int) $storeFallbackProduct['AMOUNT'] : 0;

    if ($amount > 0) {
        $currentBasket = MBasketTable::getList([
            'filter' => [
                'FUSER_ID' => $fuserId,
                'CURRENT_BASKET' => '1',
            ],
            'limit' => 1,
        ])->fetch();

        if ($currentBasket) {
            MBasketTable::update((int) $currentBasket['ID'], [
                'CURRENT_BASKET' => '0',
            ]);
        }

        $newBasket = MBasketTable::getList([
            'filter' => [
                'FUSER_ID' => $fuserId,
                'STORE_ID' => $productProperties['SKLAD'],
            ],
            'limit' => 1,
        ])->fetch();

        if ($newBasket) {
            MBasketTable::update((int) $newBasket['ID'], [
                'CURRENT_BASKET' => '1',
            ]);

            $prod = MBasketItemTable::getList([
                'filter' => [
                    'PRODUCT_ID' => $productId,
                ],
                'limit' => 1,
            ])->fetch();

            if ($prod) {
                MBasketItemTable::update((int) $prod['ID'], [
                    'MULTIBASKET_ID' => $newBasket['ID'],
                ]);
            }
        }

        return EventResult::SUCCESS;
    }

    return new EventResult(
        EventResult::ERROR,
        new ResultError('Недостаточно товара на складе')
    );
}
