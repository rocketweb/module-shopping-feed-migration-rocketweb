<?php

declare(strict_types=1);

namespace RocketWeb\ShoppingFeedMigration\Block\Adminhtml;

use Magento\Backend\Block\Template;
use RocketWeb\ShoppingFeedMigration\Model\Repository;
use RocketWeb\ShoppingFeedMigration\Model\Migration as Service;

class Migration extends Template
{
    public function __construct(Template\Context $context, private Repository $repository, private Service $service, array $data = [])
    {
        parent::__construct($context, $data);
    }
    public function candidates(): array
    {
        return $this->repository->candidates();
    }
    public function receipts(): array
    {
        return $this->repository->receipts();
    }
    public function formKey(): string
    {
        return $this->getFormKey();
    }
    public function report(): array
    {
        $id = filter_var($this->getRequest()->getParam('feed'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if (!$id) {
            return [];
        }
        try {
            $action = (string)$this->getRequest()->getParam('operation', 'import');
            return $action === 'import' ? $this->service->preview($id) : $this->service->review($id, $action);
        } catch (\DomainException $e) {
            return ['error' => $e->getMessage()];
        } catch (\Throwable $e) {
            return ['error' => 'Unable to prepare a preview. Check module definitions and database schema.'];
        }
    }
}
