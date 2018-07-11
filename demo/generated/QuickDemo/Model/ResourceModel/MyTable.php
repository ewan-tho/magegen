<?php

namespace Ewan\QuickDemo\Model\ResourceModel;

class MyTable extends \Magento\Framework\Model\ResourceModel\Db\AbstractDb
{
    const TABLE = 'my_table';
    const PRIMARY_KEY = 'id';

    /**
     * Model Initialization
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init(self::TABLE, self::PRIMARY_KEY);
    }
}
