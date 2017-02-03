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
class CRM_Advancedfundraising_Form_Report_Contribute_New extends CRM_Advancedfundraising_Form_Report_Contribute_ContributionAggregates {
  protected $_baseTable = 'civicrm_contact';
  protected $_noFields = TRUE;
  protected $_preConstrain = TRUE; // generate a temp table of contacts that meet criteria & then build temp tables
  protected $_comparisonType = 'none';
  protected $_chartXName = 'Time Period';

  protected $_charts = array(
    '' => 'Tabular',
    'barChart' => 'Bar Chart',
   );

  public $_drilldownReport = array('contribute/detail' => 'Link to Detail Report');

  function __construct() {
    $this->_statuses = array('first');

    $this->_barChartLegend = ts('New Contributors');
    $this->reportFilters = array(
      'civicrm_contribution' => array(
        'filters' => array(
          'receive_date' => array(),
          'contribution_baseline_interval' => array(
            'title' => ts('Contribution Time Interval'),
            'pseudofield' => TRUE,
         //   'operations' => array('eq' => ts('Is equal to'),),
            'default' => 3,
            'operatorType' => CRM_Report_Form::OP_SELECT,
            'required' => TRUE,
            'options' => array('1' => 'Monthly', '3' => 'Quarterly', '6' => '6 monthly', '12' => 'Yearly'),
          ),
          'contribution_no_periods' => array(
            'title' => ts('Number of periods to show'),
            'pseudofield' => TRUE,
            'operatorType' => CRM_Report_Form::OP_SELECT,
            'default' => 4,
            'operations' => array('eq' => 'Is equal to'),
            'type' => CRM_Report_Form::OP_INT,
            'options' => array(1 => 1, 2 => 2, 3 => 3, 4 => 4, 5 => 5, 6 => 6 ),
          ),
          'contribution_timeframe' => array(
            'title' => ts('Number of months to look back'),
            'pseudofield' => TRUE,
            'operations' => array('eq' => ts('Is equal to'),),
            'type' => CRM_Report_Form::OP_INT,
            'default' => 120,
          ),
        )
      ),
    );
    $this->_columns =  array_merge_recursive($this->reportFilters, $this->getContributionColumns(array(
        'fields' => FALSE,
        'order_by' => FALSE,
      )))  ;

    $this->_columns['civicrm_contribution']['filters'] ['receive_date']['operatorType'] = parent::OP_SINGLEDATE;
    $this->_columns['civicrm_contribution']['filters'] ['receive_date']['title'] = 'End Date of Reporting Period';
    $this->_columns['civicrm_contribution']['filters'] ['receive_date']['operations'] = array('to' =>  'Is equal to');
    $this->_columns['civicrm_contribution']['filters'] ['receive_date']['default'] = date('m/d/Y',strtotime($this->getLastDayOfQuarter()));
    $this->_columns['civicrm_contribution']['filters'] ['receive_date']['pseudofield'] = TRUE;
    $this->_aliases['civicrm_contact']  = 'civicrm_report_contact';
    $this->_columns['civicrm_contribution']['filters'] ['contribution_status_id']['default']
    = array(array_search('Completed', $this->_columns['civicrm_contribution']['filters'] ['contribution_status_id']['options']));

    $this->_tagFilter = TRUE;
    $this->_groupFilter = TRUE;

    parent::__construct();
  }

  function buildChart(&$rows) {
    $this->_graphData['xname'] = ts('Period');
    $this->_graphData['yname'] = ts("Number of Donors");
    foreach ($rows as $index => $row) {
      $this->_graphData['xlabels'][] = ts(" to ") . $row['to_date'];
    }
    parent::buildChart($rows);
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
      ) + $this->constrainedFromClause();
    }
  }
/**
 * @todo consider moving ranges & start date setting to construct or post
 * @return array
 */
  function constrainedFromClause(){
    return array(
      'timebased_contribution_from_contact'
    );
  }

  function select(){
    if(!$this->_preConstrained){
      parent::select();
    }
    else{
      $columns = array(
        'from_date' => ts('From date'),
        'to_date' => ts('To Date'),
        'first' => ts('New Donors'),
      );
      foreach ($columns as $column => $title){
        $select[]= " $column ";
        $this->_columnHeaders[$column] = array('title' => $title);
      }
      $this->_select = " SELECT " . implode(', ', $select);
    }
  }

  function where() {
    parent::where();
  }

  function groupBy() {
    parent::groupBy();
    // not sure why this would be in this function - copy & paste
    $this->assign('chartSupported', TRUE);
  }

  function statistics(&$rows) {
    $statistics = parent::statistics($rows);
    return $statistics;
  }

/**
 * (non-PHPdoc)
 * @see CRM_Extendedreport_Form_Report_Advancedfundraising::beginPostProcess()
 */
  function beginPostProcess() {
    parent::beginPostProcess();
    $this->setReportingStartDate(array(
      'start_offset' => 'contribution_timeframe_value',
      'start_offset_unit' => 'month',)
    );
    $this->constructRanges(array(
      'cutoff_date' => 'receive_date_value',
      'start_offset' => 'contribution_timeframe_value',
      'start_offset_unit' => 'month',
      'offset_unit' => 'month',
      'offset' => 'contribution_baseline_interval_value',
      'comparison_offset' => 'contribution_recovered_comparison_value',
      'comparison_offset_unit' => 'month',
      'comparison_offset_type' => 'prior', ///
      'no_periods' => 'contribution_no_periods_value',
      'statuses' => array('prior', 'recovered'),
    )
    );
  }
}

