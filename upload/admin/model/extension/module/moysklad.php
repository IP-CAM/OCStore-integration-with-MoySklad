<?php
/**
 * Undocumented class
 */
class ModelExtensionModuleMoysklad extends Model
{
    /**
     * Undocumented function
     *
     * @param [type] $manufacturer_name
     * @return void
     */
    public function getManufacturerByName($manufacturer_name)
    {
        $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "manufacturer WHERE name = '" . $this->db->escape($manufacturer_name) . "'");
        return $query->row;
    }

    /**
     * Undocumented function
     *
     * @param [type] $attribute_group_name
     * @return void
     */
    public function getAttributeGroupByName($attribute_group_name)
    {
        $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "attribute_group_description AS agd WHERE agd.language_id = '" . (int)$this->config->get('config_language_id') . "' AND agd.name = '" . $this->db->escape($attribute_group_name) . "'");
        return $query->row;
    }
    
    /**
     * Undocumented function
     *
     * @param [type] $attribute_name
     * @return void
     */
    public function getAttributeByName($attribute_name)
    {
        $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "attribute a LEFT JOIN " . DB_PREFIX . "attribute_description ad ON (a.attribute_id = ad.attribute_id) WHERE ad.name = '" . $this->db->escape($attribute_name) . "' AND ad.language_id = '" . (int)$this->config->get('config_language_id') . "'");
        return $query->row;
    }

    /**
     * Undocumented function
     *
     * @param [type] $category_name
     * @return void
     */
    public function getCategoryByName($category_name)
    {
        $query = $this->db->query("SELECT *, ( SELECT COUNT(parent_id) FROM " . DB_PREFIX . "category WHERE parent_id = c.category_id ) AS children FROM " . DB_PREFIX . "category c LEFT JOIN " . DB_PREFIX . "category_description cd ON (c.category_id = cd.category_id) WHERE cd.name = '" . $this->db->escape($category_name) . "' AND cd.language_id = '" . (int)$this->config->get('config_language_id') . "' ORDER BY c.sort_order, cd.name");
        return $query->row;
    }

    /**
     * Undocumented function
     *
     * @param [type] $category_name
     * @param integer $parent_category_id
     * @return void
     */
    public function getCategoryByNameAndParentId($category_name, $parent_category_id = 0)
    {
        $query = $this->db->query("SELECT *, ( SELECT COUNT(parent_id) FROM " . DB_PREFIX . "category WHERE parent_id = c.category_id ) AS children FROM " . DB_PREFIX . "category c LEFT JOIN " . DB_PREFIX . "category_description cd ON (c.category_id = cd.category_id) WHERE cd.name = '" . $this->db->escape($category_name) . "' AND c.parent_id = '" . (int)$parent_category_id . "' AND cd.language_id = '" . (int)$this->config->get('config_language_id') . "' ORDER BY c.sort_order, cd.name");
        return $query->row;
    }
    
    public function getProductIdByExternalCode($external_code)
    {
        $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "product AS p WHERE p.model = '" . $this->db->escape($external_code) . "'");
        return $query->row['product_id'];
    }
    
    public function setOfferQuantity($external_code, $offer_type, $quantity)
    {
        if($offer_type === 'product') {
            $this->setProductQuantityByExternalCode($external_code, $quantity);
        } else if($offer_type === 'variant') {
            $this->setProductOptionQuantityByExternalCode($external_code, $quantity);
        } else {
            return FALSE;
        }
        
        return TRUE;
    }
    
    public function setProductQuantityByExternalCode($product_external_code, $quantity)
    {
        $query = $this->db->query("UPDATE " . DB_PREFIX . "product AS p SET p.quantity = '" . (int)$quantity . "' WHERE p.model = '" . $this->db->escape($product_external_code) . "'");
    }

    public function setProductOptionQuantityByExternalCode($option_external_code, $quantity)
    {
        $query = $this->db->query("UPDATE " . DB_PREFIX . "product_option_value AS pov SET pov.quantity = '" . (int)$quantity . "' WHERE pov.model = '" . $this->db->escape($option_external_code) . "'");
    }
    
    public function issetProductByExternalCode($external_code)
    {
        $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "product AS p WHERE p.model = '" . $this->db->escape($external_code) . "'");
        
        if($query->num_rows == 0) {
            return false;
        } else {
            return true;
        }
    }
    
    public function cleanDB()
    {
        $this->db->query("TRUNCATE TABLE " . DB_PREFIX . "product");
        $this->db->query("TRUNCATE TABLE " . DB_PREFIX . "product_attribute");
        $this->db->query("TRUNCATE TABLE " . DB_PREFIX . "product_description");
        $this->db->query("TRUNCATE TABLE " . DB_PREFIX . "product_discount");
        $this->db->query("TRUNCATE TABLE " . DB_PREFIX . "product_image");
        $this->db->query("TRUNCATE TABLE " . DB_PREFIX . "product_option");
        $this->db->query("TRUNCATE TABLE " . DB_PREFIX . "product_option_value");
        $this->db->query("TRUNCATE TABLE " . DB_PREFIX . "product_related");
        $this->db->query("TRUNCATE TABLE " . DB_PREFIX . "product_reward");
        $this->db->query("TRUNCATE TABLE " . DB_PREFIX . "product_special");
        $this->db->query("TRUNCATE TABLE " . DB_PREFIX . "product_to_category");
        $this->db->query("TRUNCATE TABLE " . DB_PREFIX . "product_to_download");
        $this->db->query("TRUNCATE TABLE " . DB_PREFIX . "product_to_layout");
        $this->db->query("TRUNCATE TABLE " . DB_PREFIX . "product_to_store");

        $this->db->query("DELETE FROM " . DB_PREFIX . "url_alias WHERE query LIKE 'product_id=%'");

        $this->db->query("TRUNCATE TABLE " . DB_PREFIX . "option");
        $this->db->query("TRUNCATE TABLE " . DB_PREFIX . "option_description");
        $this->db->query("TRUNCATE TABLE " . DB_PREFIX . "option_value");
        $this->db->query("TRUNCATE TABLE " . DB_PREFIX . "option_value_description");

        $this->db->query("TRUNCATE TABLE " . DB_PREFIX . "order_option");

        $this->db->query("TRUNCATE TABLE " . DB_PREFIX . "category");
        $this->db->query("TRUNCATE TABLE " . DB_PREFIX . "category_description");
        $this->db->query("TRUNCATE TABLE " . DB_PREFIX . "category_to_store");
        $this->db->query("TRUNCATE TABLE " . DB_PREFIX . "category_to_layout");
        $this->db->query("TRUNCATE TABLE " . DB_PREFIX . "category_path");

        $this->db->query("DELETE FROM " . DB_PREFIX . "url_alias WHERE query LIKE 'category_id=%'");

        $this->db->query("TRUNCATE TABLE " . DB_PREFIX . "manufacturer");
        $this->db->query("TRUNCATE TABLE " . DB_PREFIX . "manufacturer_description");
        $this->db->query("TRUNCATE TABLE " . DB_PREFIX . "manufacturer_to_store");

        $this->db->query("DELETE FROM " . DB_PREFIX . "url_alias WHERE query LIKE 'manufacturer_id=%'");

        $this->db->query("TRUNCATE TABLE " . DB_PREFIX . "attribute");
        $this->db->query("TRUNCATE TABLE " . DB_PREFIX . "attribute_description");
        $this->db->query("TRUNCATE TABLE " . DB_PREFIX . "attribute_group");
        $this->db->query("TRUNCATE TABLE " . DB_PREFIX . "attribute_group_description");
    }
}
