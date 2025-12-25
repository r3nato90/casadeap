-- Adiciona colunas para WishPag e VersellPay (gateways)
ALTER TABLE `gateways`
  ADD COLUMN `wishpag_base_url` VARCHAR(255) NULL AFTER `ondapay_secret`,
  ADD COLUMN `wishpag_secret_key` VARCHAR(255) NULL AFTER `wishpag_base_url`,
  ADD COLUMN `wishpag_company_id` VARCHAR(255) NULL AFTER `wishpag_secret_key`,
  ADD COLUMN `versell_base_url` VARCHAR(255) NULL AFTER `wishpag_company_id`,
  ADD COLUMN `versell_vspi` VARCHAR(255) NULL AFTER `versell_base_url`,
  ADD COLUMN `versell_vsps` VARCHAR(255) NULL AFTER `versell_vspi`;
