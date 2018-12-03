<?php
/**
 * Class ControllerModule
 *
 * @category MoySklad
 * @package  MoySklad
 * @author   Kard1nal <89671000964@ya.ru>
 * @license  https://opensource.org/licenses/MIT MIT License
 * @link     https://vk.com/webstudio_moscow
 */
class ControllerExtensionModuleMoysklad extends Controller {
    /**
     * Undocumented variable
     *
     * @var [type]
     */
    private $moyskladApiClient;

    /**
     * Undocumented function
     *
     * @param [type] $registry
     */
    public function __construct($registry) {
        parent::__construct($registry);

        // $this->load->library('retailcrm/retailcrm');
        // $this->retailcrmApiClient = $this->retailcrm->getApiClient();
    }

    /**
     * Create order on event
     *
     * @param string    $trigger
     * @param array     $data
     * @param int       $order_id   - order identificator
     *
     * @return void
     */
    public function addOrder($trigger, $data, $order_id = null) {
        $this->load->model('checkout/order');
        $this->load->model('account/order');
        $this->load->model('extension/moysklad/order');

        $data = $this->model_checkout_order->getOrder($order_id);
        $data['products'] = $this->model_account_order->getOrderProducts($order_id);
        $data['totals'] = $this->model_account_order->getOrderTotals($order_id);
        
        foreach ($data['products'] as $key => $product) {
            $productOptions = $this->model_account_order->getOrderOptions($order_id, $product['order_product_id']);

            if (!empty($productOptions)) {
                $data['products'][$key]['option'] = $productOptions;
            }
        }

        $this->model_extension_moysklad_order->addOrder($data);
    }

    /**
     * Create customer on event
     *
     * @param int $customerId customer identificator
     *
     * @return void
     */
    public function addCustomer($parameter1, $parameter2 = null, $parameter3 = null) {
        $this->load->model('account/customer');
        $this->load->model('localisation/country');
        $this->load->model('localisation/zone');

        $customerId = $parameter3;
        $customer = $this->model_account_customer->getCustomer($customerId);

        if ($this->request->post) {
            $country = $this->model_localisation_country->getCountry($this->request->post['country_id']);
            $zone = $this->model_localisation_zone->getZone($this->request->post['zone_id']);

            $customer['address'] = array(
                'address_1'  => $this->request->post['address_1'],
                'address_2'  => $this->request->post['address_2'],
                'city'       => $this->request->post['city'],
                'postcode'   => $this->request->post['postcode'],
                'iso_code_2' => $country['iso_code_2'],
                'zone'       => $zone['name']
            );
        }

        $this->load->model('extension/retailcrm/customer');
        $this->model_extension_retailcrm_customer->sendToCrm($customer, $this->retailcrmApiClient);
    }

    public function getOrderHistory() {
        $this->load->model('extension/moysklad/history');

        $this->model_extension_moysklad_history->syncOrdersStates();
    }

    public function listener() {
        
    }
}
