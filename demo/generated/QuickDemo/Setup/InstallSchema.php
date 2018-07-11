<?php

namespace Ewan\QuickDemo\Setup;

use Magento\Framework\Setup\InstallSchemaInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;

class InstallSchema implements InstallSchemaInterface
{
    /**
     * {@inheritdoc}
     * @throws \Zend_Db_Exception
     */
    public function install(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $setup->startSetup();
        $this->createTableMyTable($setup);
        $setup->endSetup();
    }

/**
     * @param \Magento\Framework\Setup\SchemaSetupInterface $setup
     *
     * @throws \Zend_Db_Exception
     */
    private function createTableMyTable(SchemaSetupInterface $setup)
    {
        $newTable = $setup->getConnection()
            ->newTable('my_table')
            ->addColumn(
                'id',
                \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
                11,
                [
                    'unsigned' => true,
                    'nullable' => false,
                    'primary' => true,
                    'identity' => true
                ],
                ''
            )
            ->addColumn(
                'field_a',
                \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
                11,
                [
                    'nullable' => true
                ],
                ''
            )
            ->addColumn(
                'field_b',
                \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                255,
                [
                    'nullable' => true
                ],
                ''
            )
            ->addColumn(
                'field_c',
                \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                null,
                [
                    'nullable' => true
                ],
                ''
            )
            ->addColumn(
                'field_d',
                \Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL,
                '12,4',
                [
                    'nullable' => true
                ],
                ''
            )
            ->addColumn(
                'field_e',
                \Magento\Framework\DB\Ddl\Table::TYPE_TIMESTAMP,
                null,
                [],
                ''
            );
        $setup->getConnection()->createTable($newTable);
    }
}
