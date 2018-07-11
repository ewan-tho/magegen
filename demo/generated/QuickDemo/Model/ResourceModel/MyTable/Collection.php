<?php

namespace Ewan\QuickDemo\Model\ResourceModel\MyTable;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Ewan\QuickDemo\Model\MyTable;
use Ewan\QuickDemo\Model\ResourceModel\MyTable as MyTableResourceModel;

class Collection extends AbstractCollection
{
    protected function _construct()
    {
        $this->_init(MyTable::class, MyTableResourceModel::class);
        $this->_setIdFieldName(MyTableResourceModel::PRIMARY_KEY);
    }
}
