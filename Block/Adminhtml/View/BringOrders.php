<?php
namespace Markant\Bring\Block\Adminhtml\View;

use Markant\Bring\Model\Config\Source\BringMethod;

class BringOrders extends \Magento\Backend\Block\Template
{
    const XML_GLOBAL_PATH = 'carriers/bring/global/';
    const XML_PATH = 'carriers/bring/booking/';

    /**
     * @var \Markant\Bring\Model\ResourceModel\Order\Shipment\Edi\CollectionFactory
     */
    protected $_ediCollectionFactory;

    /**
     * Core registry
     *
     * @var \Magento\Framework\Registry
     */
    protected $_coreRegistry = null;

    /**
     * @var \Magento\Shipping\Model\Config
     */
    protected $_shippingConfig;

    /**
     * @param \Magento\Backend\Block\Template\Context $context
     * @param \Magento\Shipping\Model\Config $shippingConfig
     * @param \Magento\Framework\Registry $registry
     * @param array $data
     */
    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        \Magento\Shipping\Model\Config $shippingConfig,
        \Magento\Framework\Registry $registry,
        \Markant\Bring\Model\ResourceModel\Order\Shipment\Edi\CollectionFactory $ediCollectionFactory,
        array $data = []
    ) {
        $this->_shippingConfig = $shippingConfig;
        $this->_coreRegistry = $registry;
        $this->_ediCollectionFactory = $ediCollectionFactory;
        parent::__construct($context, $data);
    }


    /**
     * Retrieve shipment model instance
     *
     * @return \Magento\Sales\Model\Order\Shipment
     */
    public function getShipment()
    {
        return $this->_coreRegistry->registry('current_shipment');
    }

    /**
     * @return \Magento\Sales\Model\Order
     */
    public function getOrder()
    {
        return $this->getShipment()->getOrder();
    }

    public function getCurrentTime () {
        return $this->_localeDate->date();
    }


    /**
     * Prepares layout of block
     *
     * @return void
     */
    protected function _prepareLayout()
    {

        $onclick = "submitAndReloadArea($('shipment_edi_info').parentNode, '" . $this->getSubmitUrl() . "')";
        $this->addChild(
            'save_button',
            'Magento\Backend\Block\Widget\Button',
            ['label' => __('Book shipment'), 'class' => 'save', 'onclick' => $onclick]
        );
    }

    /**
     * Retrieve save url
     *
     * @return string
     */
    public function getSubmitUrl()
    {
        return $this->getUrl('adminhtml/*/addEdi/', ['shipment_id' => $this->getShipment()->getId()]);
    }

    /**
     * Retrieve save button html
     *
     * @return string
     */
    public function getSaveButtonHtml()
    {
        return $this->getChildHtml('save_button');
    }

    public function getAllEdis () {
        $shipment = $this->getShipment();
        /** @var \Markant\Bring\Model\ResourceModel\Order\Shipment\Edi\Collection $collection */
        $collection = $this->_ediCollectionFactory->create()->setShipmentFilter($shipment->getId());
        return $collection;
    }
    public function getBringProducts () {
        return BringMethod::products();
    }
    /**
     * Retrieve remove url
     *
     * @param \Markant\Bring\Model\Order\Shipment\Edi $edi
     * @return string
     */
    public function getRemoveUrl($edi)
    {
        return $this->getUrl(
            'adminhtml/*/removeEdi/',
            ['shipment_id' => $this->getShipment()->getId(), 'edi_id' => $edi->getId()]
        );
    }

    public function getDefaultPackageWidth () {
        return $this->getConfig('package/width');
    }

    public function getDefaultPackageLength () {
        return $this->getConfig('package/length');
    }

    public function getDefaultPackageHeight () {
        return $this->getConfig('package/height');
    }


    public function getConfig ($key) {
        return $this->_scopeConfig->getValue(
            self::XML_PATH . $key,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $this->getData('store')
        );
    }
    public function getGlobalConfig ($key) {
        return $this->_scopeConfig->getValue(
            self::XML_GLOBAL_PATH . $key,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $this->getData('store')
        );
    }
}