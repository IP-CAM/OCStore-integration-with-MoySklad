<?php

use \GuzzleHttp\Client;
use MoySklad\MoySklad;
use MoySklad\Entities\Assortment;
use MoySklad\Entities\Products\Product;
use MoySklad\Entities\Products\Variant;
use MoySklad\Entities\Folders\ProductFolder;
use MoySklad\Entities\Reports\StockReport;
use MoySklad\Components\FilterQuery;
use MoySklad\Components\Specs\QuerySpecs\Reports\StockReportQuerySpecs;

// Для создания и работы с заказами
use MoySklad\Lists\EntityList;
use MoySklad\Entities\Counterparty;
use MoySklad\Entities\Organization;
use MoySklad\Entities\Documents\Orders\CustomerOrder;
use MoySklad\Entities\Documents\Positions\CustomerOrderPosition;
use MoySklad\Components\Specs\QuerySpecs\QuerySpecs;

/**
 * Undocumented class
 */
class ControllerExtensionModuleMoysklad extends Controller
{
    /**
     * Идентификатор магазина в OpenCart
     *
     * @var integer
     */
    private $openCartStoreId            = 0;

    /**
     * Идентификатор языковой локализации в OpenCart
     *
     * @var integer
     */
    private $openCartLanguageId         = 0;

    /**
     * Наименование модуля
     *
     * @var string
     */
    private $moduleTitle                = "moysklad";

    /**
     * Массив для сортировки категорий в OpenCart
     *
     * @var array
     */
    private $openCartCategorySortOrder  = [
        'Кроссовки'          => 0,
        'Кеды'               => 1,
        'Большие размеры'    => 2,
        'Ботинки Timberland' => 3,
        'Угги Ugg Australia' => 4,
        'Сандалии'           => 5,
        'Рюкзаки'            => 6,
        'Аксессуары'         => 7,
        'Распродажа'         => 8,
    ];

    private $images                     = [];
    private $image_dir                  = 'catalog/moysklad/';
    private $category_path_delimiter    = '/';
    private $error                      = [];
    private $debug_events               = [];
    private $token                      = null;
    private $assortment                 = [];
    
    private $exts_path                  = 'extension/extension';
    private $ext_path                   = 'extension/module/moysklad';
    
    private $client                     = null;
    
    public function __construct($registry)
    {
        parent::__construct($registry);
        
        setlocale(LC_ALL, 'ru_RU.UTF-8');
        mb_internal_encoding("UTF-8");
        mb_regex_encoding('UTF-8');
        
        $this->load->model('catalog/category');
        $this->load->model('catalog/product');
        $this->load->model('catalog/attribute');
        $this->load->model('catalog/attribute_group');
        $this->load->model('catalog/manufacturer');
        $this->load->model('catalog/option');
        $this->load->model('catalog/url_alias');
        $this->load->model($this->ext_path);

        $this->load->model('extension/event');
        $this->load->model('extension/extension');

        $this->modelEvent = 'extension_event';
        $this->modelExtension = 'extension_extension';
        
        $this->load->library('translit');
        
        $this->openCartLanguageId  = (int)$this->config->get('config_language_id');
        $this->openCartStoreId     = (int)$this->config->get('config_store_id');
        $this->client       = new \GuzzleHttp\Client();
        $this->sklad        = MoySklad::getInstance(MOYSKLAD_LOGIN, MOYSKLAD_PASSWORD);
        
        if(isset($this->session->data['token'])) {
            $this->token = $this->session->data['token'];
        }
    }
    
    public function __destruct(){}
    
	public function index() 
    {
        $this->load->language($this->ext_path);

		$this->document->setTitle($this->language->get('page_title'));

		$this->load->model('setting/setting');

		if ($this->request->server['REQUEST_METHOD'] == 'POST') {
			$this->model_setting_setting->editSetting('moysklad', $this->request->post);

			$this->session->data['success'] = $this->language->get('text_success');

			$this->response->redirect($this->makeLink($this->exts_path, '&type=module'));
		}

		$data['heading_title'] = $this->language->get('heading_title');

		$data['text_edit'] = $this->language->get('text_edit');
		$data['text_enabled'] = $this->language->get('text_enabled');
		$data['text_disabled'] = $this->language->get('text_disabled');

		$data['entry_status'] = $this->language->get('entry_status');

		$data['button_save'] = $this->language->get('button_save');
		$data['button_cancel'] = $this->language->get('button_cancel');

		if (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} else {
			$data['error_warning'] = '';
		}

		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->makeLink('common/dashboard')
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_extension'),
			'href' => $this->makeLink($this->exts_path, '&type=module')
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->makeLink($this->ext_path)
		);

		$data['action'] = $this->makeLink($this->ext_path);

		$data['cancel'] = $this->makeLink($this->exts_path, '&type=module');
        
        if(isset($this->session->data['debug_events'])) {
            $data['debug_events'] = $this->session->data['debug_events'];
            unset($this->session->data['debug_events']);
        }
        
        if(isset($this->session->data['moysklad_products_number'])) {
            $data['moysklad_products_number'] = $this->session->data['moysklad_products_number'];
            unset($this->session->data['moysklad_products_number']);
        }
        
        $data['buttons'] = $this->getModuleButtons();
        
		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view($this->ext_path, $data));
    }

    /**
     * Install method
     *
     * @return void
     */
    public function install()
    {
        $this->load->model('setting/setting');

        $this->model_setting_setting->editSetting(
            $this->moduleTitle,
            array(
                $this->moduleTitle . '_status' => 1,
                $this->moduleTitle . '_country' => array($this->config->get('config_country_id'))
            )
        );

        $this->addEvents();
    }

    /**
     * Uninstall method
     *
     * @return void
     */
    public function uninstall()
    {
        $this->load->model('setting/setting');
        $this->model_setting_setting->editSetting(
            $this->moduleTitle,
            array($this->moduleTitle . '_status' => 0)
        );

        $this->deleteEvents();
    }

    /**
     * Добавляет события к стандартным действиям в OpenCart
     *
     * @return void
     */
    public function addEvents()
    {
        $this->{'model_' . $this->modelEvent}
        ->addEvent(
            $this->moduleTitle,
            'catalog/model/checkout/order/addOrder/after',
            'extension/module/moysklad/addOrder'
        );
    }

    /**
     * Удаляет события к стандартным действиям в OpenCart
     *
     * @return void
     */
    public function deleteEvents()
    {
        $this->{'model_' . $this->modelEvent}->deleteEvent($this->moduleTitle);
    }

    /**
     * Запуск синхронизации товарных предложений OpenCart и amoCRM
     *
     * @return void
     */
    public function syncOpenCartAndMoySkladOffers()
    {
        // Получим товары из OpenCart и МойСклад
        $moySkladProducts = $this->getMoySkladProducts();
        $openCartProducts = $this->getOpenCartProducts();

        // Разделим на новые и существующие
        $oldMoySkladProducts = array_intersect_key($moySkladProducts, $openCartProducts);
        $newMoySkladProducts = array_diff_key($moySkladProducts, $openCartProducts);

        // Получим дополнительные картинки
        $this->getImages();

        // Обновим существующие товары из МойСклад в OpenCart
        if (!empty($oldMoySkladProducts)) {
            foreach ($oldMoySkladProducts as $syncId => $oldMoySkladProduct) {
                $oldOpenCartProduct = $this->getOpenCartProductData($oldMoySkladProduct);
                $this->editOpenCartProduct($openCartProducts[$syncId]['product_id'], $oldOpenCartProduct);
            }
        }

        // Добавим новые товары из МойСклад в OpenCart
        if (!empty($newMoySkladProducts)) {
            foreach ($newMoySkladProducts as $newMoySkladProduct) {
                $openCartProduct = $this->getOpenCartProductData($newMoySkladProduct);
                $this->addOpenCartProduct($openCartProduct);
            }
        }

        $this->syncOpenCartAndMoySkladOffersQuantity();
        
        $this->cleanOpenCartSeoCache();
    }

    /**
     * Запуск синхронизации остатков товарных предложений OpenCart и amoCRM
     *
     * @return void
     */
    public function syncOpenCartAndMoySkladOffersQuantity()
    {
        $_moySkladOffers = Assortment::query($this->sklad)->getList();
        $moySkladOffers = [];

        foreach($_moySkladOffers as $moySkladOffer) {
            $moySkladOffers[$moySkladOffer->fields->id] = $moySkladOffer->fields->quantity;
        }
        
        $moySkladProducts = Product::query($this->sklad)->getList();
        
        foreach($moySkladProducts as $moySkladProduct) {
            $moySkladProductQuantity = [];
            
            if($moySkladProduct->fields->modificationsCount > 0) {
                $moySkladProductVariants = Variant::query($this->sklad)->filter(
                    (new FilterQuery())
                        ->eq("productid", $moySkladProduct->fields->id)
                );
                
                foreach($moySkladProductVariants as $moySkladProductVariant) {
                    $moySkladProductQuantity[] = $moySkladOffers[$moySkladProductVariant->fields->id];
                    
                    $this->model_extension_module_moysklad->setOfferQuantity(
                        $moySkladProductVariant->fields->externalCode,
                        $moySkladProductVariant->fields->meta->type,
                        $moySkladOffers[$moySkladProductVariant->fields->id]
                    );
                }
                
                $this->model_extension_module_moysklad->setOfferQuantity(
                    $moySkladProduct->fields->externalCode,
                    $moySkladProduct->fields->meta->type,
                    array_sum($moySkladProductQuantity)
                );
            } else {
                $this->model_extension_module_moysklad->setOfferQuantity(
                    $moySkladProduct->fields->externalCode,
                    $moySkladProduct->fields->meta->type,
                    $moySkladOffers[$moySkladProduct->fields->id]
                );
            }
        }
    }

    /**
     * Получает массив с данными для добавления или обновления товара в OpenCart
     *
     * @param MoySklad\Entities\Products\Product $moySkladProduct
     * @return array
     */
    private function getOpenCartProductData($moySkladProduct)
    {
        $moySkladProductFields = $moySkladProduct->fields;

        $moySkladProductId = $moySkladProductFields->id;
        $moySkladProductStatus = !$moySkladProductFields->archived;
        $moySkladProductСode = $moySkladProductFields->code;
        $moySkladProductExternalСode = $moySkladProductFields->externalCode;
        $moySkladProductArticle = $moySkladProductFields->article;
        $moySkladProductName = $moySkladProductFields->name;

        $openCartCategoryInfo = $this->getOpenCartProductCategoryId($moySkladProduct);
        $openCartProductCategories = $openCartCategoryInfo['product_category'];
        $openCartProductMainCategory = $openCartCategoryInfo['main_category_id'];

        $openCartProductDescription = $this->getOpenCartProductDescription($moySkladProduct);
        $openCartProductImage = $this->getOpenCartProductImage($moySkladProduct);
        $openCartProductImages = $this->getOpenCartProductImages($moySkladProductArticle);
        $openCartProductAttributes = $this->getOpenCartProductAttributes($moySkladProduct);   
        $openCartProductManufacturerId = $this->getOpenCartProductManufacturierId($moySkladProduct);
        $openCartProductOptions = $this->getOpenCartProductOptions($moySkladProduct);
        $openCartProductPriceSale = $moySkladProduct->getSalePrice('Цена розничная')->value / 100;
        $openCartProductPriceOpt = $moySkladProduct->getSalePrice('Цена оптовая')->value / 100;
        $openCartProductPriceDrop = $moySkladProduct->getSalePrice('Цена дропшиппинг')->value / 100;
        $openCartProductKeyword = $this->getOpenCartKeyword($moySkladProductName);
        $openCartProductStore = $this->getOpenCartStore();
        $openCartProductSortOrder = $this->getOpenCartProductSortOrder($moySkladProduct);
        $openCartProductStickers = $this->getOpenCartProductStickers($moySkladProduct);

        return [
            'upc'                   => $moySkladProductId,
            'ean'                   => '',
            'jan'                   => $openCartProductStickers,
            'isbn'                  => '',
            'mpn'                   => $openCartProductPriceSale,
            'quantity'              => 1,
            'upc'                   => '',
            'sku'                   => $moySkladProductСode,
            'model'                 => $moySkladProductExternalСode,
            'location'              => $openCartProductPriceDrop,
            'minimum'               => '',
            'subtract'              => '',
            'stock_status_id'       => '',
            'date_available'        => '',
            'manufacturer_id'       => $openCartProductManufacturerId,
            'options_buy'           => '',
            'shipping'              => 1,
            'points'                => '',
            'weight'                => '',
            'weight_class_id'       => '',
            'length'                => '',
            'width'                 => '',
            'height'                => '',
            'length_class_id'       => '',
            'status'                => $moySkladProductStatus,
            'tax_class_id'          => '',
            'sort_order'            => $openCartProductSortOrder,
            'keyword'               => $openCartProductKeyword,
            'product_category'      => $openCartProductCategories,
            'product_image'         => $openCartProductImages,
            'main_category_id'      => $openCartProductMainCategory,
            'product_description'   => $openCartProductDescription,
            'product_attribute'     => $openCartProductAttributes,
            'product_option'        => $openCartProductOptions,
            'price'                 => $openCartProductPriceDrop,
            'image'                 => $openCartProductImage,
            'product_store'         => $openCartProductStore,
        ];
    }

    /**
     * Получает массив с продуктами из OpenCart
     *
     * @return array
     */
    private function getOpenCartProducts()
    {
        $products = [];

        $results = $this->model_catalog_product->getProducts();

        foreach ($results as $result) {
            $products[$result['model']] = $result;
        }

        return $products;
    }

    /**
     * Получает массив с продуктами из МойСклад
     *
     * @return void
     */
    private function getMoySkladProducts()
    {
        $products = [];

        $results = Product::query($this->sklad)->filter(
            (new FilterQuery())
                ->eq('archived', 'true')
                ->eq('archived', 'false')
        );

        foreach ($results as $result) {
            $products[$result->fields->externalCode] = $result;
        }

        return $products;
    }

    private function addOpenCartProduct($openCartProductData)
    {
        return $this->model_catalog_product->addProduct($openCartProductData);
    }
        
    private function editOpenCartProduct($openCartProductId, $openCartProductData)
    {
        return $this->model_catalog_product->editProduct($openCartProductId, $openCartProductData);
    }

    private function getOpenCartProductDescription($moySkladProduct)
    {
        $moySkladProductFields = $moySkladProduct->fields;

        if(!isset($moySkladProductFields->description)) {
            $moySkladProductFields->description = '';
        }
        
        return [
            $this->openCartLanguageId => [
                'name'  => $moySkladProductFields->name,
                'short_description' => '',
                'description' => $moySkladProductFields->description,
                'meta_title' => '',
                'meta_h1' => '',
                'meta_description' => '',
                'meta_keyword' => '',
                'tag' => '',
            ],
        ];
    }
    
    /**
    private function getOpenCartProductImage($product_article)
    {
        if(isset($this->images[$product_article])) {
            if(isset($this->images[$product_article]['main'])) {
                return $this->images[$product_article]['main'];
            }
        }
        
        return null;
    }
    **/
    
    private function getOpenCartProductImage($moysklad_product)
    {
        if(!isset($moysklad_product->image)) {
            return '';
        }
        
        $external_code = $moysklad_product->fields->article;
        $extension     = pathinfo($moysklad_product->image->filename, PATHINFO_EXTENSION);
        $file          = $moysklad_product->getImage();
        
        if(empty($file) && empty($external_code) && empty($extension)) {
            return '';
        }
        
        $filename = $this->image_dir . $external_code . '.' . $extension;
        
        if(!file_exists(DIR_IMAGE . $filename)) {
            if(file_put_contents(DIR_IMAGE . $filename, $file) === FALSE) {
                return '';
            }
        }
        
        return $filename;
    }
    
    private function getOpenCartProductImages($moySkladProductArticle)
    {
        $additional_images = [];
        
        if(isset($this->images[$moySkladProductArticle])) {
            if(isset($this->images[$moySkladProductArticle]['additional'])) {
                if(is_array($this->images[$moySkladProductArticle]['additional'])) {
                    $images = $this->images[$moySkladProductArticle]['additional'];
                    
                    foreach($images as $sort_order => $image) {
                        $additional_images[] = [
                            'image'         => $image,
                            'sort_order'    => $sort_order,
                            'video'         => '',
                        ];
                    }

                    return $additional_images;
                }
            }
        }
        
        return null;
    }
    
    private function getOpenCartProductAttributes($moySkladProduct)
    {
        $moySkladProductFields = $moySkladProduct->fields;

        if (!isset($moySkladProductFields->attributes)) {
            return null;
        }

        if (
            !isset($moySkladProductFields->attributes->attrs)
            && !is_array($moySkladProductFields->attributes->attrs)
            && empty($moySkladProductFields->attributes->attrs)
        ) {
            return null;
        }

        $moySkladProductAttributes = $moySkladProductFields->attributes->attrs;
        $openCartProductAttributes = [];

        foreach($moySkladProductAttributes as $moySkladProductAttribute) {
            $moySkladProductAttributeName = $moySkladProductAttribute->name;

            if(stristr($moySkladProductAttributeName, 'Атрибут | ') !== false) {
                $tempArray = explode(' | ', $moySkladProductAttributeName);
                
                $attributeGroupName     = $tempArray[0];
                $attributeGroupId       = $this->getOpenCartProductAttributeGroupId($attributeGroupName);
                
                $attributeName          = $tempArray[1];
                $attributeValue         = $moySkladProductAttribute->value->name;
                $attributeId            = $this->getOpenCartProductAttributeId($attributeName, $attributeGroupId);
                
                $openCartProductAttributes[] = [
                    'attribute_id' => $attributeId,
                    'product_attribute_description' => [
                        $this->openCartLanguageId => [
                            'text' => $attributeValue,
                        ],
                    ],
                ];
            }
        }

        return $openCartProductAttributes;
    }
    
    private function getOpenCartProductOptions($moySkladProduct)
    {
        if($moySkladProduct->modificationsCount == 0) {
            return [];
        }

        $moySkladProductId = $moySkladProduct->fields->id;

        $variants = Variant::query($this->sklad)->filter(
            (new FilterQuery())
                ->eq("productid", $moySkladProductId)
        );
        
        $product_option_values = [];
        
        foreach($variants as $variant) {
            if(isset($variant->fields) && isset($variant->fields->characteristics)) {
                $variant_fields = $variant->fields;
                $characteristics = $variant_fields->characteristics;
            } else {
                continue;
            }
            
            foreach($characteristics as $characteristic) {
                $option_id = $this->getOpenCartProductOptionId($characteristic->name);
                $option_value_id = $this->getOpenCartProductOptionValueId($option_id, $characteristic->value);
                
                $product_option_values[] = [
                    'option_value_id'           => $option_value_id,
                    'product_option_value_id'   => '',
                    'option_id'                 => $option_id,
                    'type'                      => 'radio',
                    'quantity'                  => 1,
                    'subtract'                  => 1,
                    'model'                     => $variant_fields->externalCode,
                    'opt_image'                 => '',
                    'price_prefix'              => '+',
                    'price'                     => '',
                    'points_prefix'             => '+',
                    'points'                    => '',
                    'weight_prefix'             => '+',
                    'weight'                    => '',
                ];
            }
        }
        
        $product_options[] = [
            'option_id'             => $option_id,
            'product_option_id'     => '',
            'name'                  => $characteristic->name,
            'required'              => 1,
            'type'                  => 'radio',
            'product_option_value'  => $product_option_values,
        ];
        
        return $product_options;
    }
    
    private function getOpenCartProductAttributeGroupId($attribute_group_name)
    {
        $attribute_group = $this->model_extension_module_moysklad->getAttributeGroupByName($attribute_group_name);
        
        if(isset($attribute_group['attribute_group_id'])) {
            $attribute_group_id = $attribute_group['attribute_group_id'];
        } else {
            $attribute_group_id = $this->model_catalog_attribute_group->addAttributeGroup([
                'sort_order' => 0,
                'attribute_group_description' => [
                    $this->openCartLanguageId => [
                        'name' => $attribute_group_name,
                    ],
                ],
            ]);
        }
        
        return $attribute_group_id;
    }
    
    private function getOpenCartProductAttributeId($attribute_name, $attribute_group_id) 
    {
        $attribute = $this->model_extension_module_moysklad->getAttributeByName($attribute_name);
        
        if(isset($attribute['attribute_id'])) {
            $attribute_id = $attribute['attribute_id'];
        } else {
            $attribute_id = $this->model_catalog_attribute->addAttribute([
                'attribute_group_id' => $attribute_group_id,
                'sort_order' => 0,
                'attribute_description' => [
                    $this->openCartLanguageId => [
                        'name' => $attribute_name,
                    ],
                ],
            ]);
        }
        
        return $attribute_id;
    }
    
    private function getOpenCartProductManufacturierId($moySkladProduct) 
    {
        $moySkladProductFields = $moySkladProduct->fields;

        if (!isset($moySkladProductFields->attributes)) {
            return null;
        }

        if (
            !isset($moySkladProductFields->attributes->attrs)
            && !is_array($moySkladProductFields->attributes->attrs)
            && empty($moySkladProductFields->attributes->attrs)
        ) {
            return null;
        }

        $moySkladProductAttributes = $moySkladProductFields->attributes->attrs;

        foreach ($moySkladProductAttributes as $attribute) {
            if ($attribute->name == 'Производитель') {
                $manufacturer_name = $attribute->value->name;
            }
        }

        if(empty($manufacturer_name)) {
            return null;
        }
        
        $manufacturer = $this->model_extension_module_moysklad->getManufacturerByName($manufacturer_name);
        
        if(isset($manufacturer['manufacturer_id']) && !empty($manufacturer)) {
            $manufacturer_id = $manufacturer['manufacturer_id'];
        } else {
            $keyword = Translit::transliterate($manufacturer_name);
            $keyword = $this->validateOpenCartKeyword($keyword);
            
            $manufacturer_id = $this->model_catalog_manufacturer->addManufacturer([
                'manufacturer_description' => [
                    $this->openCartLanguageId => [
                        'name' => $manufacturer_name,
                        'description' => '',
                        'meta_title' => '',
                        'meta_h1' => '',
                        'meta_description' => '',
                        'meta_keyword' => '',
                        
                    ], 
                ],
                'sort_order' => 0,
                'keyword' => $keyword,
                'manufacturer_store' => [
                    0 => $this->openCartStoreId,
                ],
            ]);
        }
        
        return $manufacturer_id;
    }
    
    private function getOpenCartProductCategoryId($moySkladProduct) 
    {
        $categoryPath = $moySkladProduct->pathName;
        $categories = explode($this->category_path_delimiter, $categoryPath);
        $parent_category_id = 0;
        $product_category = [];
        
        foreach($categories as $category_name) {
            $category = $this->model_extension_module_moysklad->getCategoryByNameAndParentId($category_name, $parent_category_id);
            
            if(empty($category)) {
                $category_description = [
                    $this->openCartLanguageId => [
                        'name'  => $category_name,
                        'short_description' => '',
                        'description' => '',
                        'meta_title' => '',
                        'meta_h1' => '',
                        'meta_description' => '',
                        'meta_keyword' => '',
                        'tag' => '',
                    ],
                ];
                
                $keyword = Translit::transliterate($category_name);
                $keyword = $this->validateOpenCartKeyword($keyword);

                $sort_order = $this->getOpenCartCateroySortOrder($category_name);
                
                $category_id = $this->model_catalog_category->addCategory([
                    'parent_id'     => $parent_category_id,
                    'column'        => '',
                    'sort_order'    => $sort_order,
                    'status'        => 1,
                    'keyword'       => $keyword,
                    'top'           => ($parent_category_id == 0) ? 1 : 0,
                    'category_description' => $category_description,
                    'category_store' => [
                        0 => $this->openCartStoreId,
                    ],
                ]);
            } else {
                if(isset($category['category_id'])) {
                    $category_id = $category['category_id'];
                } else {
                    var_dump($categories);
                    exit;
                }
            }
            
            $product_category[] = $category_id;
            $parent_category_id = $category_id;
        }
        
        return [
            'product_category' => $product_category,
            'main_category_id' => $category_id,
        ];
    }
    
    private function getOpenCartProductOptionId($option_name) 
    {
        $options = $this->model_catalog_option->getOptions(['filter_name' => $option_name]);
        
        if(count($options) == 0) {
            $option_id = $this->model_catalog_option->addOption([
                'type'          => 'radio',
                'sort_order'    => 0,
                'option_description' => [
                    $this->openCartLanguageId => [
                        'name'  => $option_name,
                    ],
                ],
            ]);
        } else {
            $option_id = array_pop($options)['option_id'];
        }

        return $option_id;
    }
    
    private function getOpenCartProductOptionValueId($option_id, $option_value_name) 
    {
        $option_values = [];
        
        $option = $this->model_catalog_option->getOption($option_id);
        $results = $this->model_catalog_option->getOptionValues($option_id);
        $option_values_names = array_column($results, 'name');
        
        if(!in_array($option_value_name, $option_values_names)) {
            $option_values[] = [
                'option_value_id'           => '',
                'image'                     => '',
                'sort_order'                => 0,
                'option_value_description'  => [
                    $this->openCartLanguageId => [
                        'name' => $option_value_name,
                    ],
                ],
            ];
        } else {
            $option_values_names_index = array_search($option_value_name, $option_values_names);
            return $results[$option_values_names_index]['option_value_id'];
        }
        
        foreach($results as $result) {
            $option_values[] = [
                'option_value_id'           => $result['option_value_id'],
                'image'                     => $result['image'],
                'sort_order'                => $result['sort_order'],
                'option_value_description'  => [
                    $this->openCartLanguageId => [
                        'name' => $result['name'],
                    ],
                ],
            ];
        }

        $this->model_catalog_option->editOption($option_id, [
            'type'          => $option['type'],
            'sort_order'    => $option['sort_order'],
            'option_description' => [
                $option['language_id'] => [
                    'name'  => $option['name'],
                ],
            ],
            'option_value' => $option_values,
        ]);
        
        $results = $this->model_catalog_option->getOptionValues($option_id);
        $option_values_names = array_column($results, 'name');
        $option_values_names_index = array_search($option_value_name, $option_values_names);
        return $results[$option_values_names_index]['option_value_id'];
    }

    private function getOpenCartProductSortOrder($moySkladProduct)
    {
        $moySkladProductFields = $moySkladProduct->fields;

        if (!isset($moySkladProductFields->attributes)) {
            return null;
        }

        if (
            !isset($moySkladProductFields->attributes->attrs)
            && !is_array($moySkladProductFields->attributes->attrs)
            && empty($moySkladProductFields->attributes->attrs)
        ) {
            return null;
        }

        $moySkladProductAttributes = $moySkladProductFields->attributes->attrs;
        $moySkladProductSortOrder = 100;

        foreach ($moySkladProductAttributes as $moySkladProductAttribute) {
            if ($moySkladProductAttribute->name == 'Порядок сортировки') {
                $moySkladProductSortOrder = $moySkladProductAttribute->value;
            }
        }

        return $moySkladProductSortOrder;
    }

    private function getOpenCartProductStickers($moySkladProduct)
    {
        $moySkladProductFields = $moySkladProduct->fields;

        if (!isset($moySkladProductFields->attributes)) {
            return null;
        }

        if (
            !isset($moySkladProductFields->attributes->attrs)
            && !is_array($moySkladProductFields->attributes->attrs)
            && empty($moySkladProductFields->attributes->attrs)
        ) {
            return null;
        }

        $moySkladProductAttributes = $moySkladProductFields->attributes->attrs;

        $moySkladProductStickers = [
            'Хит продаж' => 0,
            'Новинка' => 0,
        ];

        foreach ($moySkladProductAttributes as $moySkladProductAttribute) {
            if ($moySkladProductAttribute->name == 'Хит продаж' && !empty($moySkladProductAttribute->value)) {
                $moySkladProductStickers['Хит продаж'] = $moySkladProductAttribute->value;
            } else if ($moySkladProductAttribute->name == 'Новинка' && !empty($moySkladProductAttribute->value)) {
                $moySkladProductStickers['Новинка'] = $moySkladProductAttribute->value;
            }
        }

        $result = $moySkladProductStickers['Хит продаж'] . $moySkladProductStickers['Новинка'];

        return $result;
    }

    private function getOpenCartCateroySortOrder($openCartCategoryName)
    {
        $openCartCategorySortOrder = 100;

        if (isset($this->openCartCategorySortOrder[$openCartCategoryName])) {
            $openCartCategorySortOrder = $this->openCartCategorySortOrder[$openCartCategoryName];
        }

        return $openCartCategorySortOrder;
    }

    private function getOpenCartStore()
    {
        return [0 => $this->openCartStoreId];
    }

    private function getOpenCartKeyword($string)
    {
        $string = Translit::transliterate($string);
        $string = $this->validateOpenCartKeyword($string);

        return $string;
    }

    private function validateOpenCartKeyword($keyword) {
		// if (utf8_strlen($keyword) > 0) {
			// $url_aliases = $this->model_catalog_url_alias->getUrlAliasesNumber($keyword);

			// if ($url_aliases->num_rows > 0 ) {
				// return $keyword . '-1';
			// }
            
            // return $keyword;
		// }
        
        return $keyword;
	}
    
    private function cleanOpenCartSeoCache()
    {
        $response = $this->client->request(
            'GET',
            $this->makeLink('octeam_tools/seo_manager/clear')
        );
    }

        
    // private function cleanOpenCartDataBase()
    public function cleanOpenCartDataBase()
    {
        $this->model_extension_module_moysklad->cleanDB();
    }
    
    private function getModuleFunctions()
    {
        $functions = [];
        
        $exceptions = [
            '__construct',
            '__destruct',
            '__call',
            '__callStatic',
            '__get',
            '__set',
            '__isset',
            '__unset',
            '__sleep',
            '__wakeup',
            '__toString',
            '__invoke',
            '__set_state',
            '__clone',
            '__debugInfo',
            'index',
        ];
        
        $class_name = get_class($this);
        $class = new ReflectionClass($class_name);
        $class_methods = $class->getMethods(ReflectionMethod::IS_PUBLIC);
        
        foreach($class_methods as $class_method) {
            if(!in_array($class_method->name, $exceptions)){
                $functions[] = $class_method->name;
            }
        }
        
        return $functions;
    }
    
    private function getModuleButtons()
    {
        $buttons = [];
        $functions = $this->getModuleFunctions();
        
        foreach($functions as $function) {
            $buttons[] = [
                'class' => 'btn btn-primary',
                'href' => $this->makeLink($this->ext_path . '/' . $function),
                'title' => $this->language->get('text_' . $function),
                'text' => $this->language->get('text_' . $function),
            ];
        }
        
        return $buttons;
    }

    private function getImages()
    {
        $pattern = DIR_IMAGE . $this->image_dir . '*';
        $image_pathes = glob($pattern);
        
        foreach($image_pathes as $image_path) {
            $path_parts = pathinfo($image_path);
            $image_parts = explode('_', $path_parts['filename']);
            $image_parts_number = count($image_parts);
            $product_article = $image_parts[0];
            
            if($image_parts_number == 2) {
                $this->images[$product_article]['additional'][$image_parts[1]] = $this->image_dir . $path_parts['basename'];
            } elseif($image_parts_number == 1) {
                $this->images[$product_article]['main'] = $this->image_dir . $path_parts['basename'];
            } else {
                throw new RuntimeException('Incorrect image filename "' . $path_parts['basename'] . '"');
            }
        }
        
        return $this;
    }
    
    private function goBack()
    {
        $this->response->redirect($this->makeLink($this->ext_path));
    }
    
    private function makeLink($action, $params = '')
    {
        return $this->url->link($action, 'token=' . $this->token . $params, true);
    }
}
