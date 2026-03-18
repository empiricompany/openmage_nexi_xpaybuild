<?php
/** @var Mage_Core_Model_Resource_Setup $installer */
$installer = $this;
$installer->startSetup();

// Stores gateway tokens (num_contratto for XPay) and display metadata.
// No sensitive cardholder data is stored.

$table = $installer->getConnection()
    ->newTable($installer->getTable('nexi_xpaybuild/saved_card'))
    ->addColumn(
        'id',
        Varien_Db_Ddl_Table::TYPE_INTEGER,
        10,
        array(
            'unsigned'       => true,
            'nullable'       => false,
            'primary'        => true,
            'auto_increment' => true,
        ),
        'Saved Card ID'
    )
    ->addColumn(
        'customer_id',
        Varien_Db_Ddl_Table::TYPE_INTEGER,
        10,
        array(
            'unsigned' => true,
            'nullable' => false,
        ),
        'Customer ID'
    )
    ->addColumn(
        'gateway_type',
        Varien_Db_Ddl_Table::TYPE_VARCHAR,
        10,
        array(
            'nullable' => false,
        ),
        'Gateway Type (XPAY)'
    )
    ->addColumn(
        'gateway_token',
        Varien_Db_Ddl_Table::TYPE_VARCHAR,
        255,
        array(
            'nullable' => false,
        ),
        'Gateway Token (num_contratto for XPay)'
    )
    ->addColumn(
        'masked_pan',
        Varien_Db_Ddl_Table::TYPE_VARCHAR,
        30,
        array(
            'nullable' => true,
            'default'  => null,
        ),
        'Masked PAN (e.g. ****1234)'
    )
    ->addColumn(
        'brand',
        Varien_Db_Ddl_Table::TYPE_VARCHAR,
        50,
        array(
            'nullable' => true,
            'default'  => null,
        ),
        'Card Brand (VISA, MASTERCARD, etc.)'
    )
    ->addColumn(
        'expiry_month',
        Varien_Db_Ddl_Table::TYPE_SMALLINT,
        2,
        array(
            'unsigned' => true,
            'nullable' => true,
            'default'  => null,
        ),
        'Expiry Month (1-12)'
    )
    ->addColumn(
        'expiry_year',
        Varien_Db_Ddl_Table::TYPE_SMALLINT,
        4,
        array(
            'unsigned' => true,
            'nullable' => true,
            'default'  => null,
        ),
        'Expiry Year (e.g. 2028)'
    )
    ->addColumn(
        'is_active',
        Varien_Db_Ddl_Table::TYPE_SMALLINT,
        1,
        array(
            'unsigned' => true,
            'nullable' => false,
            'default'  => 1,
        ),
        'Is Active (1=active, 0=deleted)'
    )
    ->addColumn(
        'created_at',
        Varien_Db_Ddl_Table::TYPE_TIMESTAMP,
        null,
        array(
            'nullable' => false,
            'default'  => Varien_Db_Ddl_Table::TIMESTAMP_INIT,
        ),
        'Creation Time'
    )
    ->addIndex(
        $installer->getIdxName('nexi_xpaybuild/saved_card', array('customer_id', 'is_active')),
        array('customer_id', 'is_active')
    )
    ->addIndex(
        $installer->getIdxName('nexi_xpaybuild/saved_card', array('gateway_token'), Varien_Db_Adapter_Interface::INDEX_TYPE_INDEX),
        array('gateway_token')
    )
    ->addIndex(
        $installer->getIdxName('nexi_xpaybuild/saved_card', array('customer_id', 'gateway_token'), Varien_Db_Adapter_Interface::INDEX_TYPE_UNIQUE),
        array('customer_id', 'gateway_token'),
        array('type' => Varien_Db_Adapter_Interface::INDEX_TYPE_UNIQUE)
    )
    ->addForeignKey(
        $installer->getFkName('nexi_xpaybuild/saved_card', 'customer_id', 'customer/entity', 'entity_id'),
        'customer_id',
        $installer->getTable('customer/entity'),
        'entity_id',
        Varien_Db_Ddl_Table::ACTION_CASCADE,
        Varien_Db_Ddl_Table::ACTION_CASCADE
    )
    ->setComment('Nexi XPay Build - Saved Payment Cards (tokens only)');

$installer->getConnection()->createTable($table);

$installer->endSetup();
