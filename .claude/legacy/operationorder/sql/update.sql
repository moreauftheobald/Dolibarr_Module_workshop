/* 
 * Click nbfs://nbhost/SystemFileSystem/Templates/Licenses/license-default.txt to change this license
 * Click nbfs://nbhost/SystemFileSystem/Templates/Other/SQLTemplate.sql to edit this template
 */
/**
 * Author:  noe
 * Created: 28 oct. 2025
 */

ALTER TABLE llx_operationorder_status ADD require_conf VARCHAR(255) NOT NULL DEFAULT '1' AFTER update_vehicule_info;
ALTER TABLE llx_operationorderdet ADD fk_c_operationorder_type INT(11) NULL DEFAULT NULL AFTER pr;
ALTER TABLE llx_operationorderdet ADD total_ht_reimbursement DOUBLE NOT NULL DEFAULT '0' AFTER fk_c_operationorder_type;
ALTER TABLE llx_operationorderdet ADD total_ht_part DOUBLE NOT NULL DEFAULT '0' AFTER total_ht_reimbursement;
ALTER TABLE llx_operationorderdet ADD total_ht_service DOUBLE NOT NULL DEFAULT '0' AFTER total_ht_part;
ALTER TABLE llx_operationorderdet ADD total_ht_external DOUBLE NOT NULL DEFAULT '0' AFTER total_ht_service;
ALTER TABLE llx_operationorderdet ADD total_ht_mo DOUBLE NOT NULL DEFAULT '0' AFTER total_ht_external;
ALTER TABLE llx_operationorderdet ADD total_ht DOUBLE NOT NULL DEFAULT '0' AFTER total_ht_mo;
ALTER TABLE llx_operationorderdet ADD remise_percent DOUBLE NOT NULL DEFAULT '0' AFTER total_ht;
ALTER TABLE llx_operationorderdet ADD remise DOUBLE NOT NULL DEFAULT '0' AFTER remise;
ALTER TABLE llx_c_operationorder_type ADD `fk_soc` int NULL AFTER `label`;


DELETE FROM llx_menu WHERE enabled='$conf->clitheobald->enabled';