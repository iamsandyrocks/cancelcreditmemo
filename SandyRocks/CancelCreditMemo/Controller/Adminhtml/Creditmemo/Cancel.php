<?php


namespace SandyRocks\CancelCreditMemo\Controller\Adminhtml\Creditmemo;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Magento\Backend\App\Action\Context;
use Magento\Ui\Component\MassAction\Filter;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory;
use Magento\Sales\Api\OrderManagementInterface;

class Cancel extends \Magento\Sales\Controller\Adminhtml\Order\AbstractMassAction
{

    protected $orderManagement;

    public function __construct(
        Context $context,
        Filter $filter,
        CollectionFactory $collectionFactory,
        OrderManagementInterface $orderManagement,
        \Magento\Framework\App\RequestInterface $request,
        \Magento\Sales\Model\Order\Creditmemo $creditmemoModel,
        \Magento\Framework\Message\ManagerInterface $messageManager
    ) {
        parent::__construct($context, $filter);
        $this->collectionFactory = $collectionFactory;
        $this->orderManagement = $orderManagement;
        $this->request = $request;
        $this->creditmemoModel = $creditmemoModel;
        $this->messageManager = $messageManager;
    }

    protected function massAction(AbstractCollection $collection)
    {    

        $cm_not_canceled = array();
        $cm_canceled = array();
        $itemsArray = $this->request->getPostValue('selected');
        foreach ($itemsArray as $key => $creditmemoId) {
            $creditmemo = $this->creditmemoModel->load($creditmemoId);
            if($creditmemo->getState() != 3){
                $order = $creditmemo->getOrder();
                $creditmemo->setState(\Magento\Sales\Model\Order\Creditmemo::STATE_CANCELED);
                foreach ($creditmemo->getAllItems() as $item) {
                    $getQtyReturned = $item->getOrderItem()->getQtyRefunded();
                    $creditmemoQty = $item->getQty();
                    $item->getOrderItem()->setQtyRefunded($getQtyReturned - $creditmemoQty);
                    $item->save();        
                }
                $order->setTotalRefunded($order->getTotalRefunded() - $creditmemo->getGrandTotal());
                $creditmemo->save();
                $order->save();
                $cm_canceled[] = $creditmemo->getIncrementId();
            }
            else{
                $cm_not_canceled[] = $creditmemo->getIncrementId();
            }
        }

        if(!empty($cm_canceled)){
            $this->messageManager->addSuccess(__('Credit Memos/Credit Memo Successfully canceled. : '.implode(',',$cm_canceled)));
        }
        if(!empty($cm_not_canceled)){
            $this->messageManager->addError(__('Unable to Cancel Credit Memos/Credit Memo #: '.implode(',',$cm_not_canceled)));
        }
        return $this->resultRedirectFactory->create()->setPath('sales/creditmemo/');
    }
}