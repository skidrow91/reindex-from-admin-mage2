<?php
/**
 *
 * Copyright © Alexi
 * See COPYING.txt for license details.
 */
namespace Axl\Reindexer\Controller\Adminhtml\Indexer;

use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Cache\Frontend\Pool;
use Magento\Framework\Indexer\ConfigInterface;
use Magento\Framework\Indexer\IndexerInterfaceFactory;
use Magento\Framework\Indexer\StateInterface;

class Reindex extends \Magento\Backend\App\Action
{
    /**
     * @var ConfigInterface
     */
    protected $config;

    /**
     * @var IndexerInterfaceFactory
     */
    protected $indexerFactory;

    /**
     * @var Pool
     */
    protected $cacheFrontendPool;

    /**
     * @param ConfigInterface $config
     * @param IndexerInterfaceFactory $indexerFactory
     * @param Pool $cacheFrontendPool
     */
    public function __construct(
        Context $context,
        ConfigInterface $config,
        IndexerInterfaceFactory $indexerFactory,
        Pool $cacheFrontendPool
    ) {
        parent::__construct($context);
        $this->config = $config;
        $this->indexerFactory = $indexerFactory;
        $this->cacheFrontendPool = $cacheFrontendPool;
    }

    /**
     * execute reindex
     *
     * @return void
     */
    public function execute()
    {
        $indexerIds = $this->getRequest()->getParam('indexer_ids');
        if (!is_array($indexerIds)) {
            $this->messageManager->addError(__('Please select indexers.'));
        } else {
            try {
                $sharedIndexesComplete = [];
                foreach ($indexerIds as $indexerId) {
                    $indexer = $this->indexerFactory->create();
                    $indexer->load($indexerId);
                    $indexerConfig = $this->config->getIndexer($indexerId);
                    if ($indexer->isInvalid()) {
                        // Skip indexers having shared index that was already complete
                        if (!in_array($indexerConfig['shared_index'], $sharedIndexesComplete)) {
                            $indexer->reindexAll();
                        } else {
                            /** @var \Magento\Indexer\Model\Indexer\State $state */
                            $state = $indexer->getState();
                            $state->setStatus(StateInterface::STATUS_VALID);
                            $state->save();
                        }
                        if ($indexerConfig['shared_index']) {
                            $sharedIndexesComplete[] = $indexerConfig['shared_index'];
                        }
                    }
                }
                foreach ($this->cacheFrontendPool as $cacheFrontend) {
                    $cacheFrontend->clean();
                }
                $this->messageManager->addSuccess(
                    __('%1 indexer(s) are reindexed.', count($indexerIds))
                );
            } catch (\Magento\Framework\Exception\LocalizedException $e) {
                $this->messageManager->addError($e->getMessage());
            } catch (\Exception $e) {
                $this->messageManager->addException(
                    $e,
                    __("Indexer(s) couldn't reindex because of an error.")
                );
            }
        }
        $this->_redirect('*/*/list');
    }

    /**
     * Check ACL permissions
     *
     * @return bool
     */
    protected function _isAllowed()
    {
        switch ($this->_request->getActionName()) {
            case 'reindex':
                return $this->_authorization->isAllowed('Magento_Indexer::changeMode');
        }
        return false;
    }
}
