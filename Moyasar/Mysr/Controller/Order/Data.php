<?php

namespace Moyasar\Mysr\Controller\Order;

use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Sales\Api\Data\OrderAddressInterface;

class Data implements ActionInterface
{
    private $context;
    protected $checkoutSession;

    public function __construct(Context $context, Session $checkoutSession)
    {
        $this->context = $context;
        $this->checkoutSession = $checkoutSession;
    }

    public function execute()
    {
        $order = $this->checkoutSession->getLastRealOrder();
        $data = [
            'order_id' => $order->getId(),
            'real_order_id' => $order->getRealOrderId(),
        ];

        if ($address = $order->getShippingAddress()) {
            $data = array_merge($data, $this->mapAddress($address));
        }

        return $this->context
            ->getResultFactory()
            ->create(ResultFactory::TYPE_JSON)
            ->setData($data);
    }

    private function mapAddress(OrderAddressInterface $address)
    {
        $keys = [
            OrderAddressInterface::FIRSTNAME,
            OrderAddressInterface::MIDDLENAME,
            OrderAddressInterface::LASTNAME,
            OrderAddressInterface::STREET,
            OrderAddressInterface::CITY,
            OrderAddressInterface::REGION,
            OrderAddressInterface::POSTCODE,
            OrderAddressInterface::EMAIL,
            OrderAddressInterface::TELEPHONE,
            OrderAddressInterface::COMPANY,
        ];

        $prefix = $address->getAddressType();

        return array_merge(...array_map(function ($key) use ($address, $prefix) {
            return [$prefix . "_" . $key => $address->getData($key)];
        }, $keys));
    }
}
