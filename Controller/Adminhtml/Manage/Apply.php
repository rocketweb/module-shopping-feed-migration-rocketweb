<?php

declare(strict_types=1);

namespace RocketWeb\ShoppingFeedMigration\Controller\Adminhtml\Manage;

use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Data\Form\FormKey\Validator;
use RocketWeb\ShoppingFeedMigration\Model\Migration;

class Apply extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'RocketWeb_ShoppingFeedMigration::migration';
    public function __construct(Action\Context $context, private Migration $migration, private Validator $formKeyValidator)
    {
        parent::__construct($context);
    }
    public function execute()
    {
        try {
            if (!$this->formKeyValidator->validate($this->getRequest())) {
                throw new \DomainException('Your form expired. Reload the preview.');
            }
            $id = filter_var($this->getRequest()->getParam('feed'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if (!$id) {
                throw new \DomainException('Select a legacy feed.');
            }
            $token = (string)$this->getRequest()->getParam('token');
            $action = (string)$this->getRequest()->getParam('operation');
            $result = $action === 'import'
                ? $this->migration->import($id, $token, (string)$this->getRequest()->getParam('backup_reference'))
                : $this->migration->transition($id, $action, $token);
            $this->messageManager->addSuccessMessage(__('Migration state: %1. Destination feed ID: %2.', $result['state'], $result['target_id']));
        } catch (\DomainException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        } catch (\Throwable $e) {
            $this->messageManager->addErrorMessage(__('Migration failed. Check schema compatibility and database connectivity.'));
        }
        return $this->resultRedirectFactory->create()->setPath('*/*/index');
    }
}
