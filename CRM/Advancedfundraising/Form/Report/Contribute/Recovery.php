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
class CRM_Advancedfundraising_Form_Report_Contribute_Recovery extends CRM_Advancedfundraising_Form_Report_Contribute_ContributionAggregates {
  protected $_temporary = '  ';
  protected $_baseTable = 'civicrm_contact';
  protected $_noFields = TRUE;
  protected $_preConstrain = TRUE; // generate a temp table of contacts that meet criteria & then build temp tables
  protected $_comparisonType = 'prior';
  protected $_chartXName = 'Base contribution period';

  protected $_charts = array(
    '' => 'Tabular',
    'barChartStack' => 'Bar Chart',
    'multiplePieChart' => 'Pie Chart',
  );

  public $_drilldownReport = array('contribute/detail' => 'Link to Detail Report');

  function __construct() {
    $this->_statuses = array('recovered', 'prior');

    $this->_barChartLegend = ts('Contributors previous to the Period behaviour in period');
    $this->reportFilters = array(
      'civicrm_contribution' => array(
        'filters' => array(
          'receive_date' => array(),
          'contribution_baseline_interval' => array(
            'title' => ts('Contribution Time Interval'),
            'pseudofield' => TRUE,
            'operations' => array('eq' => ts('Is equal to'),),
            'default' => 12,
            'type' => CRM_Report_Form::OP_INT,
            'required' => TRUE,
          //  'options' => array('6' => '6 months', '12' => '12 months'),
          ),
          'contribution_recovered_comparison' => array(
            'title' => ts('Renewal timeframe'),
            'pseudofield' => TRUE,
            'operations' => array('eq' => ts('Is equal to'),),
            'default' => 12,
            'type' => CRM_Report_Form::OP_INT,
          ),
          'contribution_timeframe' => array(
            'title' => ts('Number of months to look back'),
            'pseudofield' => TRUE,
            'operations' => array('eq' => ts('Is equal to'),),
            'type' => CRM_Report_Form::OP_INT,
            'default' => 60,
          ),
        )
      ),
    );
    $this->_columns =  array_merge_recursive($this->reportFilters, $this->getContributionColumns(array(
        'fields' => FALSE,
        'order_by' => FALSE,
      )))  ;
    $this->_columns['civicrm_contribution']['filters'] ['contribution_status_id']['default']
      = array(array_search('Completed', $this->_columns['civicrm_contribution']['filters'] ['contribution_status_id']['options']));

    $this->_columns['civicrm_contribution']['filters'] ['receive_date']['operatorType'] = parent::OP_SINGLEDATE;
    $this->_columns['civicrm_contribution']['filters'] ['receive_date']['title'] = 'Cut-off date';
    $this->_columns['civicrm_contribution']['filters'] ['receive_date']['operations'] = array('to' =>  'Is equal to');
    $this->_columns['civicrm_contribution']['filters'] ['receive_date']['default'] = date('m/d/Y',strtotime('31 Dec last year'));
    $this->_columns['civicrm_contribution']['filters'] ['receive_date']['pseudofield'] = TRUE;
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
        'recovered' => ts('Recovered Donors'),
        'prior' => ts('Lapsed')
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
      'no_periods' => 2,
      'statuses' => array('prior', 'recovered'),
    )
    );
  }
}

