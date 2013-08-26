<?php
/*
 +--------------------------------------------------------------------+
 | CiviCRM version 4.3                                                |
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC (c) 2004-2013                                |
 +--------------------------------------------------------------------+
 | This file is a part of CiviCRM.                                    |
 |                                                                    |
 | CiviCRM is free software; you can copy, modify, and distribute it  |
 | under the terms of the GNU Affero General Public License           |
 | Version 3, 19 November 2007 and the CiviCRM Licensing Exception.   |
 |                                                                    |
 | CiviCRM is distributed in the hope that it will be useful, but     |
 | WITHOUT ANY WARRANTY; without even the implied warranty of         |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.               |
 | See the GNU Affero General Public License for more details.        |
 |                                                                    |
 | You should have received a copy of the GNU Affero General Public   |
 | License and the CiviCRM Licensing Exception along                  |
 | with this program; if not, contact CiviCRM LLC                     |
 | at info[AT]civicrm[DOT]org. If you have questions about the        |
 | GNU Affero General Public License or the licensing of CiviCRM,     |
 | see the CiviCRM license FAQ at http://civicrm.org/licensing        |
 +--------------------------------------------------------------------+
*/

/**
 *
 * @package CRM
 * @copyright CiviCRM LLC (c) 2004-2013
 * $Id$
 *
 */
class CRM_Advancedfundraising_Form_Report_Contribute_AggregateDetails extends CRM_Advancedfundraising_Form_Report_Contribute_ContributionAggregates {
  protected $_temporary = '  ';
  protected $_baseTable = 'civicrm_contact';
  protected $_baseEntity = 'contact';
  protected $_noFields = TRUE;
  protected $_preConstrain = TRUE; // generate a temp table of contacts that meet criteria & then build temp tables
  protected $_add2groupSupported = TRUE;

  protected $_charts = array(
    '' => 'Tabular',
  );

  public $_drilldownReport = array('contribute/detail' => 'Link to Detail Report');

  function __construct() {
    $this->reportFilters = array(
      'civicrm_contribution' => array(
        'filters' => array(
          'receive_date' => array(),// just to make it first
          'comparison_date' => array(
            'title' => ts('Comparison Date Range'),
            'pseudofield' => TRUE,
            'type' => CRM_Report_Form::OP_DATE,
            'operatorType' => CRM_Report_Form::OP_DATE,
            'required' => TRUE,
          ),
          'report_date' => array(
            'title' => ts('Report Date Range'),
            'pseudofield' => TRUE,
            'type' => CRM_Report_Form::OP_DATE,
            'operatorType' => CRM_Report_Form::OP_DATE,
            'required' => TRUE,
          ),
          'behaviour_type' => array(
            'title' => ts('Donor Behavior'),
            'pseudofield' => TRUE,
            'type' => CRM_Report_Form::OP_STRING,
            'operatorType' => CRM_Report_Form::OP_SELECT,
            'required' => TRUE,
            'options' => array(
              'renewed' => ts('Renewed Donors'),
              'new' => ts('New Donor (since comparison period'),
              'lapsed' => ts('Lapsed Donors from Comparison Period'),
              'prior' => ts('All Lapsed Donors'),
              'recovered' => ts('Recovered Donors'),
              'first' => ts('First Time Donor'),
              'increased' => ts('Donors with increased giving'),
              'decreased' => ts('Donor with decreased giving'),
              'every' => ts('All donors in main period'),
            ),
          ),
        )
      ),
    );

    $this->_columns =  array_merge_recursive($this->reportFilters, $this->getContributionColumns(array(
        'fields' => FALSE,
        'order_by' => FALSE,
      )))
    + $this->getContactColumns()
    + $this->getContributionSummaryColumns(array('prefix' => 'main', 'prefix_label' => ts('Main Range ')))
    + $this->getContributionSummaryColumns(array('prefix' => 'comparison', 'prefix_label' => ts('Comparison Range ')));
    $this->_columns['civicrm_contact']['fields']['display_name']['default']  = TRUE;
    $this->_columns['civicrm_contact']['fields']['id']['default']  = TRUE;
    $this->_columns['civicrm_contribution']['filters']['receive_date']['pseudofield'] = TRUE;
    $this->_columns['civicrm_contribution']['filters']['contribution_status_id']['default'] = 1;
    $this->_aliases['civicrm_contact']  = 'civicrm_report_contact';
    $this->_tagFilter = TRUE;
    $this->_groupFilter = TRUE;
    parent::__construct();
  }

  function preProcess() {
    parent::preProcess();
  }

  function from(){
    parent::from();
  }

  function fromClauses( ) {
    if($this->_preConstrained){
      return $this->constrainedFromClause();
    }
    else{
      return array(
        'contribution_from_contact',
        'entitytag_from_contact',
        'single_contribution_comparison_from_contact',
     ) ;
    }
  }

  function constrainedFromClause(){
    $criteria = array();
    foreach ($this->whereClauses['civicrm_contribution'] as $clause){
      if(strpos($clause, 'receive_date') == FALSE){
        $criteria[] = $clause;
      }
    }
    return array(
      'single_contribution_comparison_from_contact',
      'contribution_summary_table_from_contact' => array(
        'comparison' => array(
            'criteria' => array_merge($criteria,array(
              'receive_date BETWEEN '  . date('Ymd000000', strtotime($this->_ranges['interval_0']['comparison_from_date'] ))
              . ' AND ' . date('Ymd235959', strtotime($this->_ranges['interval_0']['comparison_to_date'])),
              'is_test = 0',
            ))
          ),
        'main' => array(
          'criteria' => array_merge($criteria, array(
            'receive_date BETWEEN '  . date('Ymd000000', strtotime($this->_ranges['interval_0']['from_date'] ))
            . ' AND ' . date('Ymd235959', strtotime($this->_ranges['interval_0']['to_date'])),
            'is_test = 0',
          ))
        ),
        ),
    );
  }

  function select(){
    parent::select();
  }

  function where() {
    parent::where();
  }

  function groupBy() {
    parent::groupBy();
    // not sure why this would be in this function - copy & paste
    $this->assign('chartSupported', TRUE);
  }

  function beginPostProcess() {
    parent::beginPostProcess();
    $this->_ranges = array(
      'interval_0' => array()
    );
    $dateFields = array('receive_date' => '', 'comparison_date' => 'comparison_', 'report_date' => 'report_date');
    $earliestDate = date('Y-m-d');
    $latestDate = date('Y-m-d', strtotime('50 years ago'));
    foreach ($dateFields as $fieldName => $prefix){
      $relative = CRM_Utils_Array::value("{$fieldName}_relative", $this->_params);
      $from     = CRM_Utils_Array::value("{$fieldName}_from", $this->_params);
      $to       = CRM_Utils_Array::value("{$fieldName}_to", $this->_params);
      $fromTime = CRM_Utils_Array::value("{$fieldName}_from_time", $this->_params);
      $toTime   = CRM_Utils_Array::value("{$fieldName}_to_time", $this->_params);
      list($from, $to) = CRM_Report_Form::getFromTo($relative, $from, $to,  $fromTime, $toTime);
      $this->_ranges['interval_0'][$prefix . 'from_date'] = DATE('Y-m-d', strtotime($from));
      $this->_ranges['interval_0'][$prefix . 'to_date'] = DATE('Y-m-d', strtotime($to));
      if(strtotime($from) < strtotime($earliestDate)){
        $earliestDate = date('m/d/Y',strtotime($from));
      }
      if(strtotime($to) > strtotime($latestDate)){
        $latestDate = date('m/d/Y', strtotime($to));
      }

    }

    // now we will re-set the receive date range to reflect the largest
    // & smallest dates we are interested in
    $this->_params['report_date_from'] = $earliestDate;
    $this->_params['report_date_to'] = $latestDate;
    $this->_params['report_date_relative'] = '0';
    $this->_columns['civicrm_contribution']['filters']['report_date'] = $this->_columns['civicrm_contribution']['filters']['receive_date'];
    $this->_columns['civicrm_contribution']['filters']['report_date']['title'] = 'Report Date Range';
    $this->_columns['civicrm_contribution']['filters']['report_date']['pseudofield'] = FALSE;
    $this->_statuses = array($this->_params['behaviour_type_value']);
  }


}

