<?php

use MoySklad\MoySklad;
use MoySklad\Entities\Assortment;
use MoySklad\Entities\Products\Product;
use MoySklad\Entities\Products\Variant;
use MoySklad\Entities\Counterparty;
use MoySklad\Entities\Organization;
use MoySklad\Lists\EntityList;
use MoySklad\Entities\Documents\Orders\CustomerOrder;
use MoySklad\Entities\Documents\Positions\CustomerOrderPosition;
use MoySklad\Components\Specs\QuerySpecs\QuerySpecs;
use MoySklad\Components\FilterQuery;

/**
 * ModelExtensionMoyskladOrder class
 */
class ModelExtensionMoyskladOrder extends Model {
    /**
     * Object of MoySklad class
     *
     * @var MoySklad
     */
    private $moysklad;

    /**
     * Constructer of ModelExtensionMoyskladOrder class
     */
    public function __construct() {
        $this->moysklad = MoySklad::getInstance(MOYSKLAD_LOGIN, MOYSKLAD_PASSWORD);
    }

    public function addOrder($data) {
        if (count($data['products']) == 0) {
            return 0;
        }
        
        $products = [];

        // Получаем организацию ММДроп
        $organization = Organization::query($this->moysklad)->byId("a13bdb75-52a5-11e8-9107-504800053c41");

        $counterpartyItems = Counterparty::query($this->moysklad)->filter(
            (new FilterQuery())
                ->eq('phone', $data['telephone'])
        );

        if (count($counterpartyItems)) {
            $counterparty = $counterpartyItems[0];
        } else {
            // Создаем нового клиента
            $counterparty = (new Counterparty($this->moysklad, [
                'name' => sprintf('%s %s', $data['firstname'], $data['lastname']),
                'email' => $data['email'],
                'phone' => $data['telephone'],
            ]))->create();
        }
        
        // Перечень товаров в заказе
        foreach ($data['products'] as $key => $product) {
            if(isset($product['option'])) {
                $externalCode = $product['option'][0]['model'];
            } else {
                $externalCode = $product['model'];
            }

            $productItems = Assortment::query($this->moysklad)->filter(
                (new FilterQuery())
                    ->eq('externalCode', $externalCode)
            );

            $productItem = $productItems[0];
 
            $productItem->quantity = (int) $product['quantity'];
            $productItem->price = (int) ($product['price'] * 100);

            $products[] = $productItems[0]->transformToMetaClass();
        }

        $positions = new EntityList($this->moysklad, $products);
        
        try {
            // Создаем заказ
            $order = (new CustomerOrder($this->moysklad, [
                "name" => sprintf('Заказ с сайта %s №%d', $data['store_name'], $data['order_id']),
                'description' => $data['comment'],
                'vatEnabled' => false,
                'externalCode' => $data['order_id'],
            ]))->buildCreation()
                ->addCounterparty($counterparty)
                ->addOrganization($organization)
                ->addPositionList($positions)
                ->execute();
        } catch(Exception $e) {

        }
    }
}
