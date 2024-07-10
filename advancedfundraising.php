<?php

require_once 'advancedfundraising.civix.php';

/**
 * Implementation of hook_civicrm_config
 */
function advancedfundraising_civicrm_config(&$config) {
  _advancedfundraising_civix_civicrm_config($config);
}

/**
 * Implementation of hook_civicrm_install
 */
function advancedfundraising_civicrm_install() {
  return _advancedfundraising_civix_civicrm_install();
}

/**
 * Implementation of hook_civicrm_enable
 */
function advancedfundraising_civicrm_enable() {
  return _advancedfundraising_civix_civicrm_enable();
}
