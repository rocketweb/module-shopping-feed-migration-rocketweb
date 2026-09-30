<?php

declare(strict_types=1);

namespace RocketWeb\ShoppingFeedMigration\Controller\Adminhtml\Manage;

use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\View\Result\PageFactory;

class Index extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'RocketWeb_ShoppingFeedMigration::migration';
    public function __construct(Action\Context $context, private PageFactory $pageFactory)
    {
        parent::__construct($context);
    }
    public function execute()
    {
        $page = $this->pageFactory->create();
        $page->getConfig()->getTitle()->prepend(__('Import Rocket Web Feeds'));
        return $page;
    }
}
