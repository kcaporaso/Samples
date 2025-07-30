<?php

namespace App\Services;

use App\Entity\PurchaseItem;
use App\Model\HubSpotAssociateObjectsRequest;
use App\Model\HubSpotCreateLineItemRequest;
use App\Model\HubSpotCreateOrderRequest;
use App\Model\HubSpotProductRequest;
use App\Model\HubSpotUpdateOrderRequest;
use App\Repository\PurchaseItemRepository;
use App\Repository\PurchaseRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

class HubSpotService
{
    private PurchaseRepository $purchaseRepository;
    private LoggerInterface $logger;
    private PurchaseItemRepository $purchaseItemRepository;
    private AddressService $addressService;
    private RouterInterface $router;
    private string $accessToken;

    public function __construct(PurchaseRepository $purchaseRepository,
                                PurchaseItemRepository $purchaseItemRepository,
                                LoggerInterface $logger,
                                AddressService $addressService,
                                RouterInterface $router)
    {
        $this->purchaseRepository = $purchaseRepository;
        $this->logger = $logger;
        $this->purchaseItemRepository = $purchaseItemRepository;
        $this->addressService = $addressService;
        $this->router = $router;
    }

    public function createOrder(int $purchaseId): void
    {
        $purchase = $this->purchaseRepository->findOneBy([
            'id' => $purchaseId
        ]);
        if (!$purchase) {
            $this->logger->error(sprintf('SAMPLE: %s::%s Purchase %d not found, can not create order in HubSpot',
                self::class, __FUNCTION__, $purchaseId));
        }

        // Create (or retrieve) the products on each purchase item
        $purchaseItems = $this->purchaseItemRepository->findBy([
            'purchase' => $purchaseId,
        ]);

        if (empty($purchaseItems)) {
            $this->logger->error(sprintf('SAMPLE: %s::%s Purchase Items for %d not found, can not create order in HubSpot',
                self::class, __FUNCTION__, $purchaseId));
        }

        $this->logger->info(sprintf('SAMPLE: %s::%s begin create products in HubSpot for %d',
        self::class, __FUNCTION__, $purchaseId));

        $lineItems = [];
        foreach ($purchaseItems as $purchaseItem) {
            $product = $this->createProduct($purchaseItem);
            if ($product) {
                // createLineItem...
                $lineItems[] = $this->createLineItem($purchaseItem, $product);
            }
        }

        $this->logger->info(sprintf('SAMPLE: %s::%s lineItem count created %d',
            self::class, __FUNCTION__, \count($lineItems)));

        if (!empty($lineItems)) {
            // create the actual hs order object, then associate the line_items.
            $hsCreateOrderRequest = new HubSpotCreateOrderRequest();
            $shippingAddress = $this->addressService->getPurchaseShippingAddress($purchase);
            $billingAddress = $this->addressService->getPurchaseBillingAddress($purchase);

            $hsCreateOrderRequest->setAccessToken($this->accessToken);
            $hsCreateOrderRequest->setProperties([
                'hs_order_name' => 'SAMPLE ID: ' . $purchase->getId(),
                'hs_external_order_id' => $purchase->getId(),
                'hs_currency_code' => 'USD',
                'hs_source_store' => $purchase->getLocation()->getTitle(),
                'hs_fulfillment_status' => $purchase->getStatus()->getTitle(),
                'hs_shipping_address_postal_code' => $shippingAddress->getZip(),
                'hs_shipping_address_city' => $shippingAddress->getCity(),
                'hs_shipping_address_state' => $shippingAddress->getState(),
                'hs_shipping_address_street' => $shippingAddress->getAddress1(),
                'hs_billing_address_street' => $billingAddress->getAddress1(),
                'hs_billing_address_city' => $billingAddress->getCity(),
                'hs_billing_address_state' => $billingAddress->getState(),
                'hs_billing_address_postal_code' => $billingAddress->getZip(),
                'hs_subtotal_price' => $purchase->getSubtotal(),
                'hs_shipping_cost' => ($purchase->getActualShippingCost()) ?: $purchase->getEstShippingCost(),
                'hs_total_price' => $purchase->getTotal(),
                'hs_tax' => $purchase->getTax(),
                'hs_referring_site' => $this->router->generate('order_detail_bs4', [
                    'purchase' => $purchase->getId(),
                ], UrlGeneratorInterface::ABSOLUTE_URL),
                'hs_external_created_date' => $purchase->getCreatedAt()->format('Y-m-d\TH:i:sp'),
            ]);

            $response = $hsCreateOrderRequest->request();
            $hsOrderRecordId = null;
            if (Response::HTTP_OK === $response->getStatusCode() ||
                Response::HTTP_CREATED === $response->getStatusCode()) {
                $order = \json_decode($response->getBody());
                if ($order) {
                    $hsOrderRecordId = $order->id;
                    $this->logger->info(sprintf('SAMPLE: %s::%s Order with record id: %s created successfully',
                        self::class, __FUNCTION__, $hsOrderRecordId));
                    $purchase->setHsRecordId($hsOrderRecordId);
                    $this->purchaseRepository->add($purchase);

                    // associate line items to the order now.
                    foreach ($lineItems as $lineItem) {
                        $hsAssociateRequest = new HubSpotAssociateObjectsRequest();
                        $hsAssociateRequest->setAccessToken($this->accessToken)
                            ->setFromObjectType('line_item')
                            ->setFromObjectId($lineItem)
                            ->setToObjectType('order')
                            ->setToObjectId($hsOrderRecordId)
                        ;
                        $response = $hsAssociateRequest->request();
                        if (Response::HTTP_OK === $response->getStatusCode()) {
                            $this->logger->info(sprintf('SAMPLE: %s::%s line item associated with order record id: %s successfully',
                                self::class, __FUNCTION__, $hsOrderRecordId));
                        } else {
                            $this->logger->error(sprintf('SAMPLE: %s::%s line item association to order failed! %s',
                            self::class, __FUNCTION__, $response->getBody()));
                        }
                    }
                }
            } else {
                $this->logger->error(sprintf('SAMPLE: %s::%s create order failed! %s',
                    self::class, __FUNCTION__, $response->getBody()));
            }
        }
    }

    public function createProduct(PurchaseItem $purchaseItem): ?string
    {
        // look up Product in HS first, this is done via the hs_sku property which is our Product ID.
        $hsProductRequest = new HubSpotProductRequest();
        // @TODO get AccessToken from CompanyOptions
        $hsProductRequest->setAccessToken($this->accessToken)
            ->setObject(HubSpotProductRequest::OBJECT)
            ->setLookupProperty(HubSpotProductRequest::LOOKUP_PROPERTY)
            ->setMethod(Request::METHOD_GET)
        ;
        $hsProductRequest->setHsSku($purchaseItem->getProduct()->getId());

        $response = $hsProductRequest->request();
        $hsProductRecordId = null;
        if (Response::HTTP_NOT_FOUND === $response->getStatusCode()) {
            $this->logger->warning(sprintf('SAMPLE: %s::%s Product with hs_sku=%d not found',
                self::class, __FUNCTION__, $purchaseItem->getProduct()->getId()));

            // Product not found, so create it.
            $hsProductRequest->setLookupProperty('')
                ->setEndpoint(HubSpotProductRequest::END_POINT)
                ->setMethod(Request::METHOD_POST)
            ;
            $hsProductRequest->setProperties([
                'name' => $purchaseItem->getTitle(),
                'price' => $purchaseItem->getPurchaseItemCostOption()->getPriceView(),
                'hs_sku' => $purchaseItem->getProduct()->getId(),
                'description' => $purchaseItem->getTitle(),
            ]);
            $hsCreateProductResponse = $hsProductRequest->request();
            if (Response::HTTP_CREATED === $hsCreateProductResponse->getStatusCode() ||
                Response::HTTP_OK === $hsCreateProductResponse->getStatusCode()) {
                $product = \json_decode($hsCreateProductResponse->getBody());
                if ($product) {
                    $hsProductRecordId = $product->id;
                    $this->logger->info(sprintf('SAMPLE: %s::%s Product with hs_sku=%d record id: %s created successfully',
                        self::class, __FUNCTION__, $purchaseItem->getProduct()->getId(), $hsProductRecordId));
                }
            } else {
                $this->logger->error(sprintf('SAMPLE: %s::%s did not create product: %s, %s',
                    self::class, __FUNCTION__, $hsCreateProductResponse->getStatusCode(),
                    $hsCreateProductResponse->getBody()));
            }
        } else if (Response::HTTP_OK === $response->getStatusCode()) {
            $this->logger->info(sprintf('SAMPLE: %s::%s Product with hs_sku=%d found',
                self::class, __FUNCTION__, $purchaseItem->getProduct()->getId()));
            // Product found, fetch Record ID.
            $product = \json_decode($response->getBody());
            if ($product) {
                $hsProductRecordId = $product->id;
            }
        } else {
            // something bad happened.
            $this->logger->error(sprintf('SAMPLE: %s::%s something unexpected happened: %s, %s',
            self::class, __FUNCTION__, $response->getStatusCode(), $response->getBody()));
        }

        return $hsProductRecordId;
    }

    public function createLineItem(PurchaseItem $purchaseItem, string $hsProductRecordId): ?string
    {
        $hsCreateLineItemRequest = new HubSpotCreateLineItemRequest();
        $hsCreateLineItemRequest->setAccessToken($this->accessToken)
            ->setObject(HubSpotCreateLineItemRequest::OBJECT)
            ->setEndpoint(HubSpotCreateLineItemRequest::END_POINT)
            ->setHsObjectId($hsProductRecordId)
            ->setMethod(Request::METHOD_POST)
        ;
        $hsCreateLineItemRequest->setProperties([
            'quantity' => $purchaseItem->getPurchaseItemCostOption()->getQuantity(),
            'price' => $purchaseItem->getPurchaseItemCostOption()->getPriceView()/$purchaseItem->getPurchaseItemCostOption()->getQuantity(),
            'amount' => $purchaseItem->getPurchaseItemCostOption()->getPriceView(),
            'hs_object_id' => $hsCreateLineItemRequest->getHsObjectId(),
            'name' => $purchaseItem->getTitle(),
        ]);
        $hsLineItemRecordId = null;
        $hsCreateLineItemResponse = $hsCreateLineItemRequest->request();
        if (Response::HTTP_CREATED === $hsCreateLineItemResponse->getStatusCode() ||
            Response::HTTP_OK === $hsCreateLineItemResponse->getStatusCode()) {
            $lineItem = \json_decode($hsCreateLineItemResponse->getBody());
            if ($lineItem) {
                $hsLineItemRecordId = $lineItem->id;
                $this->logger->info(sprintf('SAMPLE: %s::%s LineItem with Product hs_object_id=%d record id: %s created successfully',
                    self::class, __FUNCTION__, $hsProductRecordId, $hsLineItemRecordId));
            }
        } else {
            $this->logger->error(sprintf('SAMPLE: %s::%s did not create LineItem: %s, %s',
                self::class, __FUNCTION__, $hsCreateLineItemResponse->getStatusCode(),
                $hsCreateLineItemResponse->getBody()));
        }

        return $hsLineItemRecordId;
    }

    /**
     * We update one property=value at a time on an order.
     * @param int $purchaseId
     * @param string $property
     * @param string $value
     * @return bool
     */
    public function updateOrder(int $purchaseId, string $property, string $value): bool
    {
        $purchase = $this->purchaseRepository->findOneBy([
            'id' => $purchaseId
        ]);

        if (!$purchase) {
            $this->logger->error(sprintf('SAMPLE: %s::%s Purchase %d not found.',
                self::class, __FUNCTION__, $purchaseId));

            return false;
        }

        if (!$purchase->getHsRecordId()) {
            $this->logger->warning(sprintf('SAMPLE: %s::%s Purchase %d does not have a hsRecordId.',
                self::class, __FUNCTION__, $purchaseId));

            return false;
        }

        $hsUpdateOrderRequest = new HubSpotUpdateOrderRequest();
        $hsUpdateOrderRequest->setAccessToken($this->accessToken)
                             ->setHsOrderRecordId($purchase->getHsRecordId());
        $prop = $hsUpdateOrderRequest->map($property);
        if (!$prop) {
            $this->logger->error(sprintf('SAMPLE: %s::%s Purchase %d does not know how to map %s.',
                self::class, __FUNCTION__, $purchaseId, $property));

            return false;
        }

        $hsUpdateOrderRequest->setProperties([
             $prop => $value,
        ]);

        $hsUpdateOrderResponse = $hsUpdateOrderRequest->request();
        if (Response::HTTP_OK === $hsUpdateOrderResponse->getStatusCode()) {
            $this->logger->info(sprintf('SAMPLE: %s::%s Purchase %d updated %s=%s successfully',
                self::class, __FUNCTION__, $purchaseId, $property, $value));

            return true;
        } else {
            $this->logger->error(sprintf('SAMPLE: %s::%s Purchase %d failed to update %s=%s',
                self::class, __FUNCTION__, $purchaseId, $property, $value));

            return false;
        }
    }

    public function setAccessToken(string $accessToken): void
    {
        $this->accessToken = $accessToken;
    }
}