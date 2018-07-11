<?php

namespace Ewan\QuickDemo\Model;

use Ewan\QuickDemo\Api\Data\MyTableInterface;

/**
 * Class MyTable
 *
 * @package Ewan\QuickDemo
 */
class MyTable extends \Magento\Framework\Model\AbstractModel implements MyTableInterface
{
    protected function _construct()
    {
        $this->_init(\Ewan\QuickDemo\Model\ResourceModel\MyTable::class);
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->getData(self::ID);
    }

    /**
     * @return int
     */
    public function getFieldA()
    {
        return $this->getData(self::FIELD_A);
    }

    /**
     * @param int $fieldA
     *
     * @return $this
     */
    public function setFieldA($fieldA)
    {
        return $this->setData(self::FIELD_A, $fieldA);
    }

    /**
     * @return string
     */
    public function getFieldB()
    {
        return $this->getData(self::FIELD_B);
    }

    /**
     * @param string $fieldB
     *
     * @return $this
     */
    public function setFieldB($fieldB)
    {
        return $this->setData(self::FIELD_B, $fieldB);
    }

    /**
     * @return string
     */
    public function getFieldC()
    {
        return $this->getData(self::FIELD_C);
    }

    /**
     * @param string $fieldC
     *
     * @return $this
     */
    public function setFieldC($fieldC)
    {
        return $this->setData(self::FIELD_C, $fieldC);
    }

    /**
     * @return float
     */
    public function getFieldD()
    {
        return $this->getData(self::FIELD_D);
    }

    /**
     * @param float $fieldD
     *
     * @return $this
     */
    public function setFieldD($fieldD)
    {
        return $this->setData(self::FIELD_D, $fieldD);
    }

    /**
     * @return string
     */
    public function getFieldE()
    {
        return $this->getData(self::FIELD_E);
    }

    /**
     * @param string $fieldE
     *
     * @return $this
     */
    public function setFieldE($fieldE)
    {
        return $this->setData(self::FIELD_E, $fieldE);
    }

}
