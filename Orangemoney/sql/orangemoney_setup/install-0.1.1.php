<?php

    $installer = $this;
    $installer->startSetup();
    $installer->run("
    DROP TABLE IF EXISTS {$this->getTable('ynote_orangemoney')};
    CREATE TABLE {$this->getTable('ynote_orangemoney')} (
    `id_order` int(11) NOT NULL,
    `id_notification` varchar(255) NOT NULL,
    `id_accesstoken` varchar(255) NOT NULL,
    `order_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id_order`)
    ) ENGINE =innoDB CHARACTER SET utf8 COLLATE utf8_bin COMMENT = 'Ynote OrangeMoney';
    ");
    
    $installer->endSetup();