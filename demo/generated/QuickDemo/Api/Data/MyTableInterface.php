<?php

namespace Ewan\QuickDemo\Api\Data;

/**
 * Interface MyTableInterface
 *
 * @package Ewan\QuickDemo
 */
interface MyTableInterface extends \Magento\Framework\Api\ExtensibleDataInterface
{
    const ID = 'id';
    const FIELD_A = 'field_a';
    const FIELD_B = 'field_b';
    const FIELD_C = 'field_c';
    const FIELD_D = 'field_d';
    const FIELD_E = 'field_e';

    /**
     * @return int
     */
    public function getId();

    /**
     * @return int
     */
    public function getFieldA();

    /**
     * @param int $fieldA
     *
     * @return $this
     */
    public function setFieldA($fieldA);

    /**
     * @return string
     */
    public function getFieldB();

    /**
     * @param string $fieldB
     *
     * @return $this
     */
    public function setFieldB($fieldB);

    /**
     * @return string
     */
    public function getFieldC();

    /**
     * @param string $fieldC
     *
     * @return $this
     */
    public function setFieldC($fieldC);

    /**
     * @return float
     */
    public function getFieldD();

    /**
     * @param float $fieldD
     *
     * @return $this
     */
    public function setFieldD($fieldD);

    /**
     * @return string
     */
    public function getFieldE();

    /**
     * @param string $fieldE
     *
     * @return $this
     */
    public function setFieldE($fieldE);

}
