<?php

use MoySklad\MoySklad;
use MoySklad\Lists\EntityList;
use Moysklad\Entities\Documents\Orders\CustomerOrder;
use MoySklad\Entities\Misc\State;

class ModelExtensionMoyskladHistory extends Model 
{
    /**
     * Object of MoySklad class
     *
     * @var MoySklad
     */
    private $moysklad;

    /**
     * Constructer of ModelExtensionMoyskladHistory class
     */
    public function __construct($registry) {
        parent::__construct($registry);

        $this->moysklad = MoySklad::getInstance(MOYSKLAD_LOGIN, MOYSKLAD_PASSWORD);
    }

    public function syncOrdersStates()
    {
        $moyskladOrders = $this->getMoyskladOrders();
        $opencartOrders = $this->getOpencartOrders();

        // d($moyskladOrders, $opencartOrders);exit;

        foreach ($opencartOrders as $order_id => $opencartOrder) {
            if (!isset($moyskladOrders[$order_id])) {
                continue;
            }

            if (trim($moyskladOrders[$order_id]) != trim($opencartOrder)) {
                $this->setOpencartOrderState($order_id, $moyskladOrders[$order_id]);
            }
        }

    }

    public function setOpencartOrderState($opencartOrderId, $opencartOrderStateName)
    {
        $opencartOrderStateId = $this->getOpencartOrderStateIdByName($opencartOrderStateName);

        $sql = "UPDATE `" . DB_PREFIX . "order` " .
                "SET order_status_id = " . (int) $opencartOrderStateId . " " . 
                "WHERE order_id = " . (int) $opencartOrderId;

        $query = $this->db->query($sql);
    }

    public function getOpencartOrderStateIdByName($opencartOrderStateName) 
    {
        $query = $this->db->query("SELECT os.order_status_id " . 
                                  "FROM `" . DB_PREFIX . "order_status` os " . 
                                  "WHERE os.language_id = 1 " . 
                                  "AND os.name = '" . trim($opencartOrderStateName) . "'");

        $result = ($query->num_rows == 0) ? 1 : $query->row['order_status_id'];
        
        return $result;
    }

    public function getMoyskladOrders()
    {
        $moyskladOrders = [];
        $moyskladOrderStates = [];

        $stateItems = CustomerOrder::getMetaData($this->moysklad)->states;

        foreach ($stateItems as $stateItem) {
            $moyskladOrderStates[$stateItem->fields->id] = $stateItem->fields->name;
        };
        
        $moyskladOrdersIterator = CustomerOrder::query($this->moysklad)->getList();
        
        foreach ($moyskladOrdersIterator as $moyskladOrder) {
            $moyskladOrderStateId = $moyskladOrder->relations->state->fields->meta->getId();
            $moyskladOrders[$moyskladOrder->fields->externalCode] = $moyskladOrderStates[$moyskladOrderStateId];
        }

        return $moyskladOrders;
    }

    public function getOpencartOrders()
    {
        $opencartOrders = [];

        $sql = "SELECT o.order_id, os.name FROM `" . DB_PREFIX . "order` o LEFT JOIN `" . DB_PREFIX . "order_status` os ON o.order_status_id = os.order_status_id WHERE os.language_id = 1";
        
        $query = $this->db->query($sql);

        foreach ($query->rows as $row) {
            $opencartOrders[$row['order_id']] = $row['name'];
        }

        return $opencartOrders;
    }
}
