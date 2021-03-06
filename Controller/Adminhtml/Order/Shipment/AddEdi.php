<?php
/**
 *
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Markant\Bring\Controller\Adminhtml\Order\Shipment;

use Magento\Backend\App\Action;
use Magento\Framework\Stdlib\DateTime;
use Magento\Sales\Model\Order\Shipment;
use Markant\Bring\Model\Carrier;
use Markantnorge\Bring\API\Contract\Booking\BookingRequest;

class AddEdi extends \Magento\Backend\App\Action
{

    const XML_PATH = 'carriers/bring/';



    /**
     * @var \Magento\Shipping\Controller\Adminhtml\Order\ShipmentLoader
     */
    protected $shipmentLoader;

    protected $_scopeConfig;

    protected $_bookingClient;

    /**
     * @param Action\Context $context
     * @param \Magento\Shipping\Controller\Adminhtml\Order\ShipmentLoader $shipmentLoader
     */
    public function __construct(
        Action\Context $context,
        \Magento\Shipping\Controller\Adminhtml\Order\ShipmentLoader $shipmentLoader,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Markant\Bring\Model\BookingClientServiceFactory $bookingClient
    ) {
        $this->_bookingClient = $bookingClient;
        $this->shipmentLoader = $shipmentLoader;
        $this->_scopeConfig = $scopeConfig;
        parent::__construct($context);
    }

    /**
     * @return bool
     */
    protected function _isAllowed()
    {
        return $this->_authorization->isAllowed('Magento_Sales::shipment');
    }


    protected function _getEdiSender ($shipment) {
        $sender = new BookingRequest\Consignment\Address();
        if ($adr1 = $this->getConfig('booking/origin/street_line_1')) {
            $sender->setAddressLine($adr1);
        }
        if ($adr2 = $this->getConfig('booking/origin/street_line_2')) {
            $sender->setAddressLine2($adr2);
        }
        $sender->setCity($this->getConfig('booking/origin/city'));
        $sender->setCountryCode($this->getConfig('booking/origin/country_id'));
        $sender->setName($this->getConfig('booking/origin/name'));
        $sender->setPostalCode($this->getConfig('booking/origin/postcode'));
        $sender->setReference($shipment->getOrderId());
        return $sender;
    }

    /**
     * Add new tracking number action
     *
     * @return void
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function execute()
    {

        try {
            $weight = (float)$this->getRequest()->getPost('weight');
            $length = (float)$this->getRequest()->getPost('length');
            $width = (float)$this->getRequest()->getPost('width');
            $height = (float)$this->getRequest()->getPost('height');

            $shippingDateTime = $this->getRequest()->getPost('shipping_date_time');
            $shippingDateTimeObj = \DateTime::createFromFormat('Y-m-d H:i', $shippingDateTime);




            if (empty($weight) || $weight <= 0) {
                throw new \Magento\Framework\Exception\LocalizedException(__('Please enter a weight.'));
            }
            if (empty($length) || $length <= 0) {
                throw new \Magento\Framework\Exception\LocalizedException(__('Please enter a length.'));
            }
            if (empty($width) || $width <= 0) {
                throw new \Magento\Framework\Exception\LocalizedException(__('Please enter a width.'));
            }
            if (empty($height) || $height <= 0) {
                throw new \Magento\Framework\Exception\LocalizedException(__('Please enter a height.'));
            }

            if ($shippingDateTimeObj === false) {
                throw new \Magento\Framework\Exception\LocalizedException(__('Date format of Shipping Date must be "YYYY-MM-DD HH:MM".'));
            }


            // Find the shipment!
            $this->shipmentLoader->setOrderId($this->getRequest()->getParam('order_id'));
            $this->shipmentLoader->setShipmentId($this->getRequest()->getParam('shipment_id'));
            $this->shipmentLoader->setShipment($this->getRequest()->getParam('shipment'));
            $this->shipmentLoader->setTracking($this->getRequest()->getParam('tracking'));
            $shipment = $this->shipmentLoader->load();


            if ($shipment) {


                $bringCustomerNumber = $this->getConfig('booking/default_customer');
                $bringTestMode = (bool)$this->getConfig('booking/test');
                $bringProductId = $this->getRequest()->getPost('product');



                $bringReturnProductId = $this->getRequest()->getPost('return_product');


                $shippingAddress = $shipment->getShippingAddress();



                //
                // Build up request to send to bring.
                //


                // Create Package

                $consignmentPackage = new BookingRequest\Consignment\Package();
                $consignmentPackage->setWeightInKg($weight);
                $consignmentPackage->setDimensionHeightInCm($height);
                $consignmentPackage->setDimensionLengthInCm($length);
                $consignmentPackage->setDimensionWidthInCm($width);


                // Create Bring Product

                $bringProduct = new BookingRequest\Consignment\Product();
                $bringProduct->setId($bringProductId);
                $bringProduct->setCustomerNumber($bringCustomerNumber);

                //adding services to the $bringProduct
                $mappings = \Markantnorge\Bring\API\Contract\Booking\BookingRequest\Consignment\Product::serviceMapping();
                //$bpArr = $this->getNullifiedServicesArray();
                $bpArr = [];
                foreach ($mappings[$bringProductId] as $bp){
                    $bpArr[$bp] = $this->getServiceValue($bp, $shippingAddress);
                }
                $bringProduct->setServices($bpArr);

                // Create Consignment

                $consignment = new BookingRequest\Consignment();
                $consignment->addPackage($consignmentPackage);
                $consignment->setProduct($bringProduct);
                $consignment->setShippingDateTime($shippingDateTimeObj);


                // Create Recipient.

                $recipient = new BookingRequest\Consignment\Address();
                $addresses = $shippingAddress->getStreet();
                if (isset($addresses[0])) {
                    $recipient->setAddressLine($addresses[0]);
                }
                if (isset($addresses[1])) {
                    $recipient->setAddressLine2($addresses[1]);
                }
                $recipient->setCity($shippingAddress->getCity());
                $recipient->setCountryCode($shippingAddress->getCountryId());
                $recipient->setName($shippingAddress->getName());
                $recipient->setPostalCode($shippingAddress->getPostcode());
                $recipient->setReference($shipment->getOrderId()); // order id as reference.
                //create contact for recepient

                $recipientContact = new BookingRequest\Consignment\Contact();
                $recipientContact->setName($shippingAddress->getName());
                $recipientContact->setEmail($shippingAddress->getEmail());
                $recipientContact->setPhoneNumber($shippingAddress->getTelephone());
                $recipient->setContact($recipientContact);


                // Create Contact for Sender.
                $contact = new BookingRequest\Consignment\Contact();
                $contact->setName($this->getConfig('booking/origin/name'));
                $contact->setEmail($this->getConfig('booking/origin/email'));
                $contact->setPhoneNumber($this->getConfig('booking/origin/phone_number'));


                $sender = $this->_getEdiSender($shipment);
                $sender->setContact($contact);

                // Lets validate sender, since these settings must be displayed in a much nicer manner....
                try {
                    $sender->validate();
                } catch (\Markantnorge\Bring\API\Contract\ContractValidationException $e) {
                    throw new \Magento\Framework\Exception\LocalizedException(__('Shipping Origin is required. Configure shipping origin under Sales -> Shipping Methods -> Bring -> Bring Booking -> Shipping Origin.'));
                }


                $consignment->setRecipient($recipient);
                $consignment->setSender($sender);




                $message = new BookingRequest();
                $message->addConsignment($consignment);
                $message->setTestIndicator($bringTestMode);


                /** @var \Markant\Bring\Model\BookingClientService $clientFactory */
                $clientFactory =  $this->_bookingClient->create();
                /** @var \Markantnorge\Bring\API\Client\BookingClient $client */
                $client = $clientFactory->getBookingClient();

                $result = $client->bookShipment($message);


                $consignment = $result['consignments'][0];

                if (empty($consignment['errors'])) {



                    /** @var \Markant\Bring\Model\Order\Shipment\Edi $edi */
                    $edi = $this->_objectManager->create(
                        'Markant\Bring\Model\Order\Shipment\Edi'
                    );
                    $edi = $edi->setWeight(
                        $weight
                    )->setLength(
                        $length
                    )->setWidth(
                        $width
                    )->setHeight(
                        $height
                    );

                    $conf = $consignment['confirmation'];
                    $consignmentNumber = $conf['consignmentNumber'];
                    $labels = $conf['links']['labels'];
                    $waybill = $conf['links']['waybill'];
                    $tracking = $conf['links']['tracking'];
                    $earliestPickup = $conf['dateAndTimes']['earliestPickup']; // Unknown.
                    $expectedDelivery = $conf['dateAndTimes']['expectedDelivery']; // Unix time + 1000
                    $packages = [];
                    foreach($conf['packages'] as $package) {
                        $packages[] = $package['packageNumber'];
                    }

                    $edi->setConsignmentNumber($consignmentNumber)
                        ->setLabelUrl($labels);
                    $edi->setWaybill($waybill);
                    $edi->setTrackingUrl($tracking);




                    // Do we want to order a return label as well ? In that case we create a second booking request.

                    if ($bringReturnProductId) {

                        // Create Package

                        $consignmentPackage = new BookingRequest\Consignment\Package();
                        $consignmentPackage->setWeightInKg(0);
                        $consignmentPackage->setDimensionHeightInCm(0);
                        $consignmentPackage->setDimensionLengthInCm(0);
                        $consignmentPackage->setDimensionWidthInCm(0);

                        // Create Product

                        $bringProduct = new BookingRequest\Consignment\Product();
                        $bringProduct->setId($bringReturnProductId);
                        $bringProduct->setCustomerNumber($bringCustomerNumber);

                        // Create Consignment

                        $returnConsignment = new BookingRequest\Consignment();
                        $returnConsignment->addPackage($consignmentPackage);
                        $returnConsignment->setProduct($bringProduct);
                        $returnConsignment->setShippingDateTime($shippingDateTimeObj);

                        $recipient->setContact($contact);

                        $sender = $this->_getEdiSender($shipment);

                        $returnConsignment->setRecipient($sender);
                        $returnConsignment->setSender($recipient);

                        // Create BookingRequest

                        $returnMessage = new BookingRequest();
                        $returnMessage->addConsignment($returnConsignment);
                        $returnMessage->setTestIndicator($bringTestMode);


                        $result2 = $client->bookShipment($returnMessage);

                        $returnConsignment = $result2['consignments'][0];

                        $confReturn = $returnConsignment['confirmation'];
                        $labels = $confReturn['links']['labels'];
                        $edi->setReturnLabelUrl($labels);
                    }


                    if ($expectedDelivery) {
                        $dt = new \DateTime();
                        $dt->setTimestamp($expectedDelivery / 1000);
                        $edi->setExpectedDelivery($dt);
                    }
                    $edi->setPackageNumbers($packages);

                    $this->addEdi($shipment, $edi)->save();


                    // ADD TRACKING TO SHIPMENT....
                    /** @var \Magento\Sales\Model\Order\Shipment\Track $track */
                    $track = $this->_objectManager->create(
                        'Magento\Sales\Model\Order\Shipment\Track'
                    )->setNumber(
                        $consignmentNumber
                    )->setCarrierCode(
                        Carrier::CARRIER_CODE
                    )->setTitle(
                        $consignmentNumber
                    );
                    $shipment->addTrack($track)->save();

                    $response = $this->resultRedirectFactory->create();
                    $response->setPath('sales/shipment/view/shipment_id/' . $shipment->getId());



                    // Reload the whole page
                    // Now...
                    // This is undocumented API.... If this does not work we need to rewrite to using regular post form...
                    $response = [
                        'error' => false,
                        'message' => __("Successfully ordered EDI shipment from Bring. Consignment number: $consignmentNumber. Print out labels below.")
                    ];
                    /*
                    $this->_view->loadLayout();
                    $this->_view->getPage()->getConfig()->getTitle()->prepend(__('EDI Bookings'));
                    $response = $this->_view->getLayout()->getBlock('bring_edi_orders')->toHtml();
                    */
                } else {
                    throw new \Exception("Could not book consignment due to errors. " . var_export($consignment['errors'], true));
                }




            } else {
                $response = [
                    'error' => true,
                    'message' => __('We can\'t initialize shipment for adding edi.'),
                ];
            }
        } catch (\Markantnorge\Bring\API\Client\BookingClientException $e) {
            $response = [
                'error' => true,
                'message' => __('Bring API Error:') . " {$e->getDetaildMessage()}.",
            ];
        } catch (\Markantnorge\Bring\API\Contract\ContractValidationException $e) {
            $response = [
                'error' => true,
                'message' => __('Configuration error:') . " {$e->getMessage()}.",
            ];
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            $response = ['error' => true, 'message' => $e->getMessage()];
        } catch (\Exception $e) {
            $response = ['error' => true, 'message' => __('Cannot book EDI.')  . " {$e->getMessage()}."];
        }

        // SET MESSAGE

        if ($response['error']) {
            $this->getMessageManager()->addError($response['message']);
        } else {
            $this->getMessageManager()->addSuccess($response['message']);
        }

        // REDIRECT.

        $this->_redirect('sales/shipment/view/shipment_id/' . $shipment->getId());

    }

    public function getConfig ($key) {
        return $this->_scopeConfig->getValue(
            self::XML_PATH . $key
        );
    }

    public function getServiceValue($serviceName, \Magento\Sales\Model\Order\Address $shippingAddress){
        $value = null;
        if($serviceName == \Markantnorge\Bring\API\Contract\Booking\BookingRequest\Consignment\Product::ADDITIONAL_SERVICE_RECIPIENT_NOTIFICATION){
            $value  = [];
            $value['email'] = $shippingAddress->getEmail();
            $value['mobile'] = $shippingAddress->getTelephone();
        }
        else if($serviceName == \Markantnorge\Bring\API\Contract\Booking\BookingRequest\Consignment\Product::ADDITIONAL_SERVICE_DELIVERY_OPTION){
            $value = $this->getConfig('calculation/default_delivery_option');
        }
        return $value;
    }

    public function getNullifiedServicesArray(){
        $services = [];
        $services[\Markantnorge\Bring\API\Contract\Booking\BookingRequest\Consignment\Product::ADDITIONAL_SERVICE_CASH_ON_DELIVERY] = null;
        $services[\Markantnorge\Bring\API\Contract\Booking\BookingRequest\Consignment\Product::ADDITIONAL_SERVICE_RECIPIENT_NOTIFICATION] = null;
        $services[\Markantnorge\Bring\API\Contract\Booking\BookingRequest\Consignment\Product::ADDITIONAL_SERVICE_SOCIAL_CONTROL] = null;
        $services[\Markantnorge\Bring\API\Contract\Booking\BookingRequest\Consignment\Product::ADDITIONAL_SERVICE_SIMPLE_DELIVERY] = null;
        $services[\Markantnorge\Bring\API\Contract\Booking\BookingRequest\Consignment\Product::ADDITIONAL_SERVICE_DELIVERY_OPTION] = null;
        $services[\Markantnorge\Bring\API\Contract\Booking\BookingRequest\Consignment\Product::ADDITIONAL_SERVICE_SATURDAY_DELIVERY] = null;
        $services[\Markantnorge\Bring\API\Contract\Booking\BookingRequest\Consignment\Product::ADDITIONAL_SERVICE_FLEX_DELIVERY] = null;
        $services[\Markantnorge\Bring\API\Contract\Booking\BookingRequest\Consignment\Product::ADDITIONAL_SERVICE_PHONE_NOTIFICATION] = null;
        $services[\Markantnorge\Bring\API\Contract\Booking\BookingRequest\Consignment\Product::ADDITIONAL_SERVICE_DELIVERY_INDOORS] = null;
        return $services;
    }

    public function addEdi(Shipment $shipment, \Markant\Bring\Model\Order\Shipment\Edi $edi) {

        $edi->setShipment(
            $shipment
        )->setParentId(
            $shipment->getId()
        )->setOrderId(
            $shipment->getOrderId()
        )->setStoreId(
            $shipment->getStoreId()
        );
        return $edi;
    }
}
