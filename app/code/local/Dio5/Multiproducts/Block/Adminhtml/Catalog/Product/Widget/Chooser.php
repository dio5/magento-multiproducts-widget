<?php

class Dio5_Multiproducts_Block_Adminhtml_Catalog_Product_Widget_Chooser extends
Mage_Adminhtml_Block_Catalog_Product_Widget_Chooser {

    protected $_selectedProducts = array();

    /**
     * Block construction, prepare grid params
     *
     * @param array $arguments Object data
     */
    public function __construct($arguments = array()) {
        parent::__construct($arguments);
        $this->setUseMassaction(true);
    }

    protected function _prepareLayout() {
        $this->setChild('choose_button', $this->getLayout()->createBlock('adminhtml/widget_button')
                        ->setData(array(
                            'label' => Mage::helper('adminhtml')->__('Choose selected'),
                            'onclick' => $this->getJsObjectName() . '.doChoose()'
                        ))
        );
        return parent::_prepareLayout();
    }

    public function getChooseButtonHtml() {
        return $this->getChildHtml('choose_button');
    }

    public function getMainButtonsHtml() {
        $html = '';
        if ($this->getFilterVisibility()) {
            $html.= $this->getResetFilterButtonHtml();
            $html.= $this->getSearchButtonHtml();
            $html .= '<br /><br />';
            $html.= $this->getChooseButtonHtml();
        }
        return $html;
    }

    /**
     * Prepare chooser element HTML
     *
     * @param Varien_Data_Form_Element_Abstract $element Form Element
     * @return Varien_Data_Form_Element_Abstract
     */
    public function prepareElementHtml(Varien_Data_Form_Element_Abstract $element) {
        $uniqId = Mage::helper('core')->uniqHash($element->getId());
        $sourceUrl = $this->getUrl('*/multiproducts/chooser', array(
            'uniq_id' => $uniqId,
            'use_massaction' => true,
        ));

        $chooser = $this->getLayout()->createBlock('widget/adminhtml_widget_chooser')
                ->setElement($element)
                ->setTranslationHelper($this->getTranslationHelper())
                ->setConfig($this->getConfig())
                ->setFieldsetId($this->getFieldsetId())
                ->setSourceUrl($sourceUrl)
                ->setUniqId($uniqId);
        
        if ($element->getValue()) {
            $label = "";
            $ids = explode(',', $element->getValue());
            $products = $this->_getProductsByIDs($ids);
            if ($products) {
                foreach ($products as $product) {
                    $label .= $product->getName(). '<br />';                    
                }
                $chooser->setLabel($label);
            }
        }
        $element->setData('after_element_html', $chooser->toHtml());
        return $element;
    }

    protected function _getProductsByIDs($ids) {
        $products = Mage::getModel('catalog/product')->getResourceCollection();
        $products->addAttributeToSelect('*');
        $products->addStoreFilter(Mage::app()->getStore()->getId());
        Mage::getSingleton('catalog/product_status')->addSaleableFilterToCollection($products);
        //Mage::getSingleton('cataloginventory/stock')->addInStockFilterToCollection($products);
        $products->addFieldToFilter('entity_id', array('in' => $ids));
        $products->load();
        return $products;
    }

    /**
     * Checkbox Check JS Callback
     *
     * @return string
     */
    public function getCheckboxCheckCallback() {
        if ($this->getUseMassaction()) {
            return "function (grid, element) {
                $(grid.containerId).fire('product:changed', {element: element});
            }";
        }
    }

    /**
     * Grid Row JS Callback
     *
     * @return string
     */
    public function getRowClickCallback() {
        if (!$this->getUseMassaction()) {
            $chooserJsObject = $this->getId();
            return '
                function (grid, event) {
                    var trElement = Event.findElement(event, "tr");
                    var productId = trElement.down("td").innerHTML;
                    var productName = trElement.down("td").next().next().innerHTML;
                    var optionLabel = productName;
                    var optionValue = "product/" + productId.replace(/^\s+|\s+$/g,"");
                    if (grid.categoryId) {
                        optionValue += "/" + grid.categoryId;
                    }
                    if (grid.categoryName) {
                        optionLabel = grid.categoryName + " / " + optionLabel;
                    }
                    ' . $chooserJsObject . '.setElementValue(optionValue);
                    ' . $chooserJsObject . '.setElementLabel(optionLabel);
                    ' . $chooserJsObject . '.close();
                }
            ';
        }
    }

    /**
     * Category Tree node onClick listener js function
     *
     * @return string
     */
    public function getCategoryClickListenerJs() {
        $js = '
            function (node, e) {
                {jsObject}.addVarToUrl("category_id", node.attributes.id);
                {jsObject}.reload({jsObject}.url);
                {jsObject}.categoryId = node.attributes.id != "none" ? node.attributes.id : false;
                {jsObject}.categoryName = node.attributes.id != "none" ? node.text : false;
            }
        ';
        $js = str_replace('{jsObject}', $this->getJsObjectName(), $js);
        return $js;
    }

    /**
     * 
     */
    public function getAdditionalJavascript() {
        $chooserJsObject = $this->getId();
        $js = '
        {jsObject}.doChoose = function(node,e){
            var checked = $$("#' . $chooserJsObject . '_table tbody input:checkbox[name=in_products]:checked");
            if(checked.length){
            var values = "";
            var labels = "";
            for(var i = 0; i<checked.length; i++){
                if(i > 0) {
                    values += ",";
                    labels += "<br />";
                }
                values += checked[i].value;                
                labels += checked[i].up("td").next().next().next().innerHTML;
            }
            ' . $chooserJsObject . '.setElementLabel(labels);
            ' . $chooserJsObject . '.setElementValue(values);
            ' . $chooserJsObject . '.close();
          }           
        }           
';
        $js = str_replace('{jsObject}', $this->getJsObjectName(), $js);
        return $js;
    }

    /**
     * Filter checked/unchecked rows in grid
     *
     * @param Mage_Adminhtml_Block_Widget_Grid_Column $column
     * @return Mage_Adminhtml_Block_Catalog_Product_Widget_Chooser
     */
    protected function _addColumnFilterToCollection($column) {
        if ($column->getId() == 'in_products') {
            $selected = $this->getSelectedProducts();
            if ($column->getFilter()->getValue()) {
                $this->getCollection()->addFieldToFilter('entity_id', array('in' => $selected));
            } else {
                $this->getCollection()->addFieldToFilter('entity_id', array('nin' => $selected));
            }
        } else {
            parent::_addColumnFilterToCollection($column);
        }
        return $this;
    }

    /**
     * Prepare products collection, defined collection filters (category, product type)
     *
     * @return Mage_Adminhtml_Block_Widget_Grid
     */
    protected function _prepareCollection() {
        /* @var $collection Mage_Catalog_Model_Resource_Eav_Mysql4_Product_Collection */
        $collection = Mage::getResourceModel('catalog/product_collection')
                ->setStoreId(0)
                ->addAttributeToSelect('name');

        if ($categoryId = $this->getCategoryId()) {
            $category = Mage::getModel('catalog/category')->load($categoryId);
            if ($category->getId()) {
                // $collection->addCategoryFilter($category);
                $productIds = $category->getProductsPosition();
                $productIds = array_keys($productIds);
                if (empty($productIds)) {
                    $productIds = 0;
                }
                $collection->addFieldToFilter('entity_id', array('in' => $productIds));
            }
        }

        if ($productTypeId = $this->getProductTypeId()) {
            $collection->addAttributeToFilter('type_id', $productTypeId);
        }

        $this->setCollection($collection);
        return parent::_prepareCollection();
    }

    /**
     * Prepare columns for products grid
     *
     * @return Mage_Adminhtml_Block_Widget_Grid
     */
    protected function _prepareColumns() {
        if ($this->getUseMassaction()) {
            $this->addColumn('in_products', array(
                'header_css_class' => 'a-center',
                'type' => 'checkbox',
                'name' => 'in_products',
                'inline_css' => 'checkbox entities',
                'field_name' => 'in_products',
                'values' => $this->getSelectedProducts(),
                'align' => 'center',
                'index' => 'entity_id',
                'use_index' => true,
            ));
        }

        $this->addColumn('entity_id', array(
            'header' => Mage::helper('catalog')->__('ID'),
            'sortable' => true,
            'width' => '60px',
            'index' => 'entity_id'
        ));
        $this->addColumn('chooser_sku', array(
            'header' => Mage::helper('catalog')->__('SKU'),
            'name' => 'chooser_sku',
            'width' => '80px',
            'index' => 'sku'
        ));
        $this->addColumn('chooser_name', array(
            'header' => Mage::helper('catalog')->__('Product Name'),
            'name' => 'chooser_name',
            'index' => 'name'
        ));

        return parent::_prepareColumns();
    }

    /**
     * Adds additional parameter to URL for loading only products grid
     *
     * @return string
     */
    public function getGridUrl() {
        return $this->getUrl('*/multiproducts/chooser', array(
                    'products_grid' => true,
                    '_current' => true,
                    'uniq_id' => $this->getId(),
                    'use_massaction' => $this->getUseMassaction(),
                    'product_type_id' => $this->getProductTypeId()
        ));
    }

    /**
     * Setter
     *
     * @param array $selectedProducts
     * @return Mage_Adminhtml_Block_Catalog_Product_Widget_Chooser
     */
    public function setSelectedProducts($selectedProducts) {
        $this->_selectedProducts = $selectedProducts;
        return $this;
    }

    /**
     * Getter
     *
     * @return array
     */
    public function getSelectedProducts() {
        if ($selectedProducts = $this->getRequest()->getParam('selected_products', null)) {
            $this->setSelectedProducts($selectedProducts);
        }
        return $this->_selectedProducts;
    }

}

