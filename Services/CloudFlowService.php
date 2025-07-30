<?php

namespace App\Services;

use cloudflow;
use Psr\Log\LoggerInterface;

require dirname(__DIR__).'/../lib/CloudFlow/CloudFlowApi.php';

class CloudFlowService
{
    private cloudflow\api_helper $apiHelper;
    private string $cfUserPassword;
    private LoggerInterface $logger;
    private string $cfApprovalWorkflow;
    private string $cfStepAndRepeatWorkflow;

    public function __construct(string $cfUserPassword, LoggerInterface $logger, string $cfApprovalWorkflow, string $cfStepAndRepeatWorkflow)
    {
        $this->cfUserPassword = $cfUserPassword;
        $this->logger = $logger;
        $this->cfApprovalWorkflow = $cfApprovalWorkflow;
        $this->cfStepAndRepeatWorkflow = $cfStepAndRepeatWorkflow;
    }

    /**
     * @throws \Exception
     */
    public function sendApprovalRequest($variables, string $workFlowName = 'startWF')
    {
        $this->apiHelper = new cloudflow\api_helper();
        $this->apiHelper->set_address('https://***REDACTED***');
        if (!$this->createSession()) {
            $this->logger->critical(sprintf('SAMPLE: %s::%s: No session created for CloudFlowService found.', __CLASS__, __FUNCTION__));
            throw new \Exception('No session created for CloudFlowService found.');
        }

        try {
            return cloudflow\hub\process_from_whitepaper_with_files_and_variables(
                $this->cfApprovalWorkflow, $workFlowName, [], $variables, 10
            );
        } catch (\Exception $exception) {
            $this->logger->critical(sprintf('SAMPLE: %s::%s: msg: %s', __CLASS__, __FUNCTION__, $exception->getMessage()));
        }
    }

    /**
     * @return mixed|void
     *
     * @throws \Exception
     */
    public function sendStepAndRepeatRequest($variables, string $workFlowName = 'startWF')
    {
        $this->apiHelper = new cloudflow\api_helper();
        $this->apiHelper->set_address('https://***REDACTED***');
        if (!$this->createSession()) {
            $this->logger->critical(sprintf('SAMPLE: %s::%s: No session created for CloudFlowService found.',
                __CLASS__, __FUNCTION__));
            throw new \Exception('No session created for CloudFlowService found.');
        }

        try {
            return cloudflow\hub\process_from_whitepaper_with_files_and_variables(
                $this->cfStepAndRepeatWorkflow, $workFlowName, [], $variables, 10
            );
        } catch (\Exception $exception) {
            $this->logger->critical(sprintf('SAMPLE: %s::%s: msg: %s', __CLASS__, __FUNCTION__, $exception->getMessage()));
        }
    }

    /**
     * @throws \Exception
     */
    public function sendApprovalRejection($variables)
    {
        $this->apiHelper = new cloudflow\api_helper();
        $this->apiHelper->set_address('https://***REDACTED***');
        if (!$this->createSession()) {
            $this->logger->critical(sprintf('SAMPLE: %s::%s: No session created for CloudFlowService found.', __CLASS__, __FUNCTION__));
            throw new \Exception('No session created for CloudFlowService found.');
        }

        try {
            return cloudflow\approval\assess(
                $variables['approval_id'], $variables['user_email'], 'reject'
            );
        } catch (\Exception $exception) {
            $this->logger->critical(sprintf('SAMPLE: %s::%s: msg: %s', __CLASS__, __FUNCTION__, $exception->getMessage()));
        }
    }

    private function createSession(): bool
    {
        $options = [
            'password' => $this->cfUserPassword,
        ];
        $sessionResponse = cloudflow\auth\create_session('admin', $options);
        if (!isset($sessionResponse['session'])) {
            return false;
        }

        $this->apiHelper->set_session($sessionResponse['session']);

        return true;
    }
}
