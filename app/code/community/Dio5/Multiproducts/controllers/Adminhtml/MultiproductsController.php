<?php

class Dio5_Multiproducts_Adminhtml_MultiproductsController extends Mage_Adminhtml_Controller_Action
{
    /**
     * Chooser Source action
     */
    public function chooserAction()
    {
        $uniqId = $this->getRequest()->getParam('uniq_id');
        $massAction = $this->getRequest()->getParam('use_massaction', false);
        $productTypeId = $this->getRequest()->getParam('product_type_id', null);
        $productsGrid = $this->getLayout()->createBlock('multiproducts/adminhtml_catalog_product_widget_chooser', '', array(
            'id'                => $uniqId,
            'use_massaction' => $massAction,
            'product_type_id' => $productTypeId,
            'category_id'       => $this->getRequest()->getParam('category_id')
        ));

        $html = $productsGrid->toHtml();

        if (!$this->getRequest()->getParam('products_grid')) {
            $categoriesTree = $this->getLayout()->createBlock('adminhtml/catalog_category_widget_chooser', '', array(
                'id'                  => $uniqId.'Tree',
                'node_click_listener' => $productsGrid->getCategoryClickListenerJs(),
                'with_empty_node'     => true
            ));

            $html = $this->getLayout()->createBlock('adminhtml/catalog_product_widget_chooser_container')
                ->setTreeHtml($categoriesTree->toHtml())
                ->setGridHtml($html)
                ->toHtml();
        }

        $this->getResponse()->setBody($html);
    }

    protected function _isAllowed() {
        return Mage::getSingleton('admin/session')->isAllowed('cms/widget_instance');
    }

}
