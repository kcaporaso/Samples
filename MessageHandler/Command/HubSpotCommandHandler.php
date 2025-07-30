<?php

namespace App\MessageHandler\Command;

use App\Message\Command\CreateOrderInHubSpot;
use App\Message\Command\UpdateOrderInHubSpot;
use App\Repository\PurchaseRepository;
use App\Services\HubSpotService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

class HubSpotCommandHandler
{
    private HubSpotService $hubSpotService;
    private PurchaseRepository $purchaseRepository;
    private LoggerInterface $logger;

    public function __construct(HubSpotService $hubSpotService,
                                PurchaseRepository $purchaseRepository,
                                LoggerInterface $logger)
    {
        $this->hubSpotService = $hubSpotService;
        $this->purchaseRepository = $purchaseRepository;
        $this->logger = $logger;
    }

    #[AsMessageHandler]
    public function createOrderInHubSpot(CreateOrderInHubSpot $command): void
    {
        $purchase = $this->purchaseRepository->findOneBy([
            'id' => $command->getPurchaseId(),
        ]);
        if ($purchase) {
            if ($purchase->getLocation()->getCompany()->getCompanyOption()->getHubSpotAccessToken()) {
                $this->hubSpotService->setAccessToken($purchase->getLocation()->getCompany()->getCompanyOption()->getHubSpotAccessToken());
                if ($purchase->getLocation()->getHsRecordId()) {
                    // Kick off the series to create the order in HubSpot.
                    $this->logger->info(sprintf('SAMPLE: %s::%s: kick off createOrderInHubSpot for %d',
                        self::class, __FUNCTION__, $purchase->getId()));

                    $this->hubSpotService->createOrder($command->getPurchaseId());
                }
            } else {
                $this->logger->warning(sprintf('SAMPLE: %s::%s: No Access Token defined for %d',
                    self::class, __FUNCTION__, $purchase->getId()));
            }
        }
    }

    #[AsMessageHandler]
    public function updateOrderInHubSpot(UpdateOrderInHubSpot $command): void
    {
        $purchase = $this->purchaseRepository->findOneBy([
            'id' => $command->getPurchaseId(),
        ]);
        if ($purchase) {
            if ($purchase->getLocation()->getCompany()->getCompanyOption()->getHubSpotAccessToken()) {
                $this->hubSpotService->setAccessToken($purchase->getLocation()->getCompany()->getCompanyOption()->getHubSpotAccessToken());
                if ($purchase->getLocation()->getHsRecordId() && $purchase->getHsRecordId()) {
                    $this->logger->info(sprintf('SAMPLE: %s::%s: kick off updateOrderInHubSpot for %d',
                        self::class, __FUNCTION__, $purchase->getId()));

                    // updateOrder.
                    $response = $this->hubSpotService->updateOrder(
                        $command->getPurchaseId(), $command->getProperty(), $command->getValue()
                    );

                    $this->logger->info(sprintf('SAMPLE: %s::%s: response %d',
                        self::class, __FUNCTION__, (int) $response));
                }
            } else {
                $this->logger->warning(sprintf('SAMPLE: %s::%s: No Access Token defined for %d',
                    self::class, __FUNCTION__, $purchase->getId()));
            }
        }
    }
}