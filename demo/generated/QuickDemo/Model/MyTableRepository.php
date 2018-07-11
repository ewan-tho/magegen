<?php

namespace Ewan\QuickDemo\Model;

use Magento\Framework\Exception\NotFoundException;
use Ewan\QuickDemo\Api\MyTableRepositoryInterface;
use Ewan\QuickDemo\Api\Data\MyTableInterface;
use Ewan\QuickDemo\Api\Data\MyTableInterfaceFactory;
use Ewan\QuickDemo\Model\ResourceModel\MyTable as MyTableResourceModel;
use Ewan\QuickDemo\Model\ResourceModel\MyTable\Collection as MyTableCollection;
use Ewan\QuickDemo\Model\ResourceModel\MyTable\CollectionFactory as MyTableCollectionFactory;

class MyTableRepository implements MyTableRepositoryInterface
{
    /**
     * @var MyTableResourceModel
     */
    protected $myTableResourceModel;

    /**
     * @var MyTableCollection
     */
    protected $myTableCollection;

    /**
     * @var MyTableCollectionFactory
     */
    protected $myTableCollectionFactory;

    /**
     * @var MyTableInterfaceFactory
     */
    protected $myTableInterfaceFactory;

    /**
     * MyTableRepository constructor.
     *
     * @param MyTableResourceModel $myTableResourceModel
     * @param MyTableCollection $myTableCollection
     * @param MyTableCollectionFactory $myTableCollectionFactory
     * @param MyTableInterfaceFactory $myTableInterfaceFactory
     */
    public function __construct(
        MyTableResourceModel $myTableResourceModel,
        MyTableCollection $myTableCollection,
        MyTableCollectionFactory $myTableCollectionFactory,
        MyTableInterfaceFactory $myTableInterfaceFactory
    ) {
        $this->myTableResourceModel = $myTableResourceModel;
        $this->myTableCollection = $myTableCollection;
        $this->myTableCollectionFactory = $myTableCollectionFactory;
        $this->myTableInterfaceFactory = $myTableInterfaceFactory;
    }

    /**
     * @param int $id
     *
     * @return MyTableInterface
     * @throws \Magento\Framework\Exception\NotFoundException
     */
    public function get($id)
    {
        /** @var \Ewan\QuickDemo\Model\MyTable $myTable */
        $myTable = $this->myTableInterfaceFactory->create();
        $this->myTableResourceModel->load($myTable, $id);
        if (!$myTable->getId()) {
            throw new NotFoundException(__('MyTable not found'));
        }
        return $myTable;
    }

    /**
     * @param int $id
     *
     * @return MyTableInterface
     * @throws \Magento\Framework\Exception\NotFoundException
     */
    public function getById($id)
    {
        return $this->get($id);
    }

    /**
     * @param MyTableInterface $myTable
     *
     * @return MyTableInterface
     * @throws \Exception
     */
    public function save(MyTableInterface $myTable)
    {
        /** @var \Ewan\QuickDemo\Model\MyTable $myTable */
        $myTable->setUpdatedAt((new \DateTime())->format(\Magento\Framework\Stdlib\DateTime::DATETIME_PHP_FORMAT));
        if (!$myTable->getCreatedAt()) {
            $myTable->setCreatedAt($myTable->getUpdatedAt());
        }
        $this->myTableResourceModel->save($myTable);
        return $myTable;
    }

    /**
     * @param MyTableInterface $myTable}
     *
     * @return bool
     * @throws \Exception
     */
    public function delete(MyTableInterface $myTable)
    {
        /** @var \Ewan\QuickDemo\Model\MyTable $myTable */
        $this->myTableResourceModel->delete($myTable);
        return true;
    }

    /**
     * @param int $id
     *
     * @return bool
     * @throws \Exception
     */
    public function deleteById($id)
    {
        return $this->delete($this->get($id));
    }
}
