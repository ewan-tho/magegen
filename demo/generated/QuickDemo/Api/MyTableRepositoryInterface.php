<?php

namespace Ewan\QuickDemo\Api;

use Ewan\QuickDemo\Api\Data\MyTableInterface;
use Magento\Framework\Api\SearchCriteriaInterface;

interface MyTableRepositoryInterface
{
    /**
     * @param int $id
     *
     * @return MyTableInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function get($id);

    /**
     * @param int $id
     *
     * @return MyTableInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getById($id);

    /**
     * @param \Magento\Framework\Api\SearchCriteriaInterface $searchCriteria
     *
     * @return MyTableInterface
     */
    // @todo: public function getList(SearchCriteriaInterface $searchCriteria);

    /**
     * @param MyTableInterface $myTable
     *
     * @return MyTableInterface
     */
    public function save(MyTableInterface $myTable);

    /**
     * @param MyTableInterface $myTable
     *
     * @return bool
     */
    public function delete(MyTableInterface $myTable);

    /**
     * @param int $id
     *
     * @return bool
     */
    public function deleteById($id);
}
