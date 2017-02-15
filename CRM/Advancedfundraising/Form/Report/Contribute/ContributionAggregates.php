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
 *
 * This is the base class for contribution aggregate reports. The report constructs a table
 * for a series of one or more data ranges and comparison ranges
 * The comparison range is the range to be compared against the main date range
 * A third range can be defined which is the report_range - currently only the 'from'
 * part (reportingStartDate) of this is implemented
 *
 * 4 types of comparitive data can be derived
 *  1) renewals - people who gave in both the base & the comparison period
 *  2) lapsed - people who gave in the comparison period only
 *  3) prior - people who gave in the report range before the main date range but not since
 *  3) new - people who gave in the base period but not the comparison period
 *  4) reactivations - people who gave in the new period but not the comparison period
 *    but also gave on an earlier occasion during the report universe
 *
 *    (not derived but easy to add are comparisons about increases & decreased in quantity/ amount)
 *
 *  Where are reportingStartDate is set the report 'universe' is only  those contributions after that date
 *
 *  The report builds up the pairs of ranges (base & comparison) for 3 main scenarios
 *    1) comparison is a future range, in this case the comparison period generally starts the day after the
 *    main period. This is used where we want to look at one period & see what happened to the
 *    donors from that period in the next period - did they lapse or renew (no reports use this at the moment)
 *
 *    2) comparison is 'allprior' - ie. any contributions in the report universe prior to the base date
 *    are treated as comparison. This used for the Recovery report where we see if people who gave
 *    prior to the base period gave (reactivated) or didn't give (lapsed) in the base period
 *
 *    3) comparison is prior - in this case the comparison is a prior range but does not go back as far as
 *    the report universe unless it co-incides with it. This is used for the renewals report
 *
 *   4) comparison is 'none - there is no comparison range (e.g. for 'first')
 */
class CRM_Advancedfundraising_Form_Report_Contribute_ContributionAggregates extends CRM_Advancedfundraising_Form_Report_ReportBase {
  CONST OP_SINGLEDATE = 3;
  protected $_add2groupSupported = FALSE;
  protected $_ranges = array();
  protected $_reportingStartDate = NULL;
  protected $_comparisonType = 'future'; // is the comparison period future, a priorrange, or all prior (after the reporting range starts)
  protected $_barChartLegend = NULL;
  protected $_baseEntity = NULL;
  protected $_tempTables = array();
  protected $_graphData = array();
  /**
   * These are the labels for the available statuses.
   * Reports can over-ride them
   */
  protected $_statusLabels = array(
    'renewed' => 'Renewed',
    'lapsed' => 'Lapsed',
    'prior' => 'All lapsed',
    'recovered' => 'Recovered',
    'new' => 'New',
    'first' => 'First',
    'increased' => 'Donors who have increased their giving',
    'reduced' => 'Donors who have reduced their giving',
    'every' => 'All donors',
    );
  /**
   *
   * @var array statuses to include in report
   */
  protected $_statuses = array();
  /**
   *
   * @var array aggregates to calculate for the report
   * aggregates are for calculating $ amount rather than number of
   * people that fit the criteria
   */
  protected $_aggregates = array();
  /**
   * This is here as a way to determine what to potentially put in the url links as filters
   * There is probably a better way...
   * @var unknown_type
   */
  protected $_potentialCriteria = array(
    'financial_type_id_value',
    'financial_type_id_op',
    'contribution_type_id_value',
    'contribution_type_id_op',
    'payment_instrument_id_op',
    'payment_instrument_id_value',
    'contribution_status_id_value',
    'contribution_status_id_op',
    'contribution_is_test_op',
    'contribution_is_test_value',
    'total_amount_min',
    'total_amount_max',
    'total_amount_op',
    'total_amount_value',
    'tagid_op',
    'tagid_value',
    'gid_op',
    'gid_value',
    'contact_type_value',
    'contact_type_op',
  );

  /**
   * Instruction to add a % on a stacked bar chart
   * @var boolean
   */
  protected $tagPercent = NULL;

  function buildChart(&$rows) {
    $graphData = array();
    foreach ($this->_statuses as $status){
      $graphData['labels'][]  = $this->_statusLabels[$status];
    }
    if($this->_params['charts'] == 'multiplePieChart'){
      return $this->mulitplePieChart($rows, $graphData);
    }

    foreach ($rows as $index => $row) {
      $graphData['xlabels'][] = $this->_params['contribution_baseline_interval_value'] . ts(" months to ") . $row['to_date'];
      $graphData['end_date'][] = $row['to_date'];
      $statusValues = array();
      foreach ($this->_statuses as $status){
        $statusValues[] = (integer) $row[$status];
      }
      $graphData['values'][] = $statusValues;
    }
    // build the chart.
    $config = CRM_Core_Config::Singleton();
    $graphData['xname'] = '';
    $graphData['yname'] = ts("Number of Donors");
    $graphData['legend'] = ts($this->_barChartLegend);
    if(count($rows) > 2) {
      // we need the labels for the tooltips but more than 2 bars & they look a mess
      // we want them to rotate - but rotate makes them disappear :-(
      // still maybe someone will fix it one day & they will re-appear. For not disappeared is
      // better than munted
      $graphData['xlabelAngle'] = 30;
    }

    $graphData = array_merge($graphData, $this->_graphData);
    $chart = new CRM_Advancedfundraising_Form_Report_OpenFlashChart();
    $chart->buildChart($graphData, $this->_params['charts']);
    $this->assign('chartType', $this->_params['charts']);
  }

  function mulitplePieChart(&$rows, $graphData){
    foreach ($rows as $index => $row) {
      $graphData['xlabels'][] = $this->_params['contribution_baseline_interval_value'] . ts(" months to ") . $row['to_date'];
      $graphData['end_date'][] = $row['to_date'];
      foreach ($this->_statuses as $status){
        $graphData['value'][] =
          (integer) $row[$status]
        ;
        $graphData['values'][$index][$status] = (integer) $row[$status];
      }
    }

    // build the chart.

     $graphData['xname'] = 'x';
     $config = CRM_Core_Config::Singleton();
     $graphData['yname'] = "Renewals : ";
     $chartInfo = array('legend' => $this->_barChartLegend);
     $chartInfo['xname'] = ts('Base contribution period');
     $chartInfo['yname'] = ts("Number of Donors");
     $chartData = CRM_Utils_OpenFlashChart::reportChart( $graphData, 'pieChart', $this->_statuses, $chartInfo);
     $this->assign('chartType', 'pieChart');
     $this->assign('chartsData', $graphData['values']);
     $this->assign('chartsLabels', array('status', 'no. contacts'));
     $this->assign('chartInfo', $chartInfo);
  }
  function alterDisplay(&$rows) {
    $queryURL = "reset=1&force=1";
    foreach ($this->_potentialCriteria as $criterion) {
      if (empty($this->_params[$criterion])) {
        continue;
      }
      $criterionValue = is_array($this->_params[$criterion]) ? implode(',', $this->_params[$criterion]) : $this->_params[$criterion];
      $queryURL .= "&{$criterion}=" . $criterionValue;
    }
    if ($this->_reportingStartDate) {
      $queryURL .= "&report_date_from=" . date('Ymd', strtotime($this->_reportingStartDate));
    }
    foreach ($rows as $index => &$row) {
      foreach ($this->_statuses as $status) {
        if (array_key_exists($status, $row)) {
          if (isset($this->_ranges['interval_' . $index]['comparison_from_date'])) {
            $queryURL .= "&comparison_date_from=" . date('Ymd', strtotime($this->_ranges['interval_' . $index]['comparison_from_date']));
          }
          if (isset($this->_ranges['interval_' . $index]['comparison_to_date'])) {
            $queryURL .= "&comparison_date_to=" . date('Ymd', strtotime($this->_ranges['interval_' . $index]['comparison_to_date']));
          }
          $statusUrl = CRM_Report_Utils_Report::getNextUrl('contribute/aggregatedetails', $queryURL . "&receive_date_from=" . date('Ymd', strtotime($row['from_date'])) . "&receive_date_to=" . date('Ymd', strtotime($row['to_date'])) . "&behaviour_type_value={$status}", $this->_absoluteUrl, NULL, $this->_drilldownReport);
          $row[$status . '_link'] = $statusUrl;
        }
      }
    }
    parent::alterDisplay($rows);
  }

  /**
   * Convert descriptor into a series of ranges.
   *
   * Note that the $extra array
   * may denote parameters or values (this allows us to easily flick between
   * allowing things like the offset_unit or no_periods to be hard-coded in the report or an
   * option
   *
   * @param array $extra
   *
   *  - 'cutoff_date' end date of primary period. A date or a field name - e.g. 'receive_date_to' = use $this->_params['receive_date_to']
   *  - primary_from_date last from & last to are an alternative to a single cut-off date
   *  - primary_to_date - they describe the main range of the primary reporting period
   *                      (the base of the other periods).
   *                      primary_to_date is effectively a pseudonym for cutoff_date for 'prior'
   *                      ranges
   *  - 'offset' => 1 - number of units to go forwards or backwards to establish start of
   *                  main period compared to start of previous main period (e.g are we looking
   *                  at periods one year apart)
   *  - 'offset_unit' e.g 'year', -
   *  - 'comparison_offset' => 18,
   *  - 'comparison_offset_unit' => 'month',
   *  - 'no_periods' => 4,
   *  - 'start_offset' => 60 - this is for defining the reporting period start date (so a 'new' donor gave since the
   *                           reporting period started.
   *  - 'start_offset_unit' => 'month'
   */
  function constructRanges($extra) {
    $vars = array(
      'cutoff_date',
      'no_periods',
      'offset_unit',
      'offset',
      'comparison_offset',
      'comparison_offset_unit',
      'start_offset',
      'start_offset_unit',
      'primary_from_date',
      'primary_to_date'
    );
    foreach ($vars as $var) {
      if (isset($extra[$var]) && ! empty($this->_params[$extra[$var]])) {
        $$var = $this->_params[$extra[$var]];
      }
      else {
        $$var = empty($extra[$var]) ? NULL : $extra[$var];
      }
    }
    if (! empty($primary_from_date)) {
      //we have been given a specific range of dates rather than a cutoff-date
      // so we will calc our start date as start date of final period less the no_periods * offset
      $startDate = date('Y-m-d', strtotime("- " . (($no_periods - 1) * $offset) . " $offset_unit ", strtotime($primary_from_date)));
    }
    else {
      // start of our period is the cutoff date - the sum of all our periods + one day (as ranges expected to run 01 Jan to 31 Dec etc)
      $startDate = date('Y-m-d', strtotime("- " . ($no_periods * $offset) . " $offset_unit ", strtotime('+ 1 day', strtotime($cutoff_date))));
    }
    $this->_ranges = array();
    for($i = 0; $i < $no_periods; $i ++) {
      if ($this->_comparisonType == 'future') {
        $this->constructFutureRanges($i, $startDate, $no_periods, $offset_unit, $offset, $comparison_offset, $comparison_offset_unit, $start_offset, $start_offset_unit);
      }
      elseif ($primary_from_date && $primary_to_date) {
        $this->constructPriorDefinedRanges($i, $startDate, $no_periods, $offset_unit, $offset, $comparison_offset, $comparison_offset_unit, $start_offset, $start_offset_unit, $primary_from_date, $primary_to_date);
      }
      elseif ($this->_comparisonType == 'allprior' || $this->_comparisonType == 'prior') {
        $this->constructPriorRanges($i, $startDate, $no_periods, $offset_unit, $offset, $comparison_offset, $comparison_offset_unit, $start_offset, $start_offset_unit);
      }

      else {
        $this->constructSingleRange($i, $startDate, $no_periods, $offset_unit, $offset, $comparison_offset, $comparison_offset_unit, $start_offset, $start_offset_unit);
      }
    }
    return $this->_ranges;
  }
  /**
 *
 * @param integer $i
 * @param string $startDate
 * @param integer $no_periods
 * @param string $offset_unit
 * @param integer $offset
 * @param string $comparison_offset
 * @param integer $comparison_offset_unit
 * @param string $start_offset
 * @param integer $start_offset_unit
 */
  function constructFutureRanges($i, $startDate, $no_periods, $offset_unit, $offset, $comparison_offset, $comparison_offset_unit, $start_offset, $start_offset_unit) {
    $rangestart = date('Y-m-d', strtotime('+ ' . ($i * $offset) . " $offset_unit ", strtotime($startDate)));
    $rangeEnd = date('Y-m-d', strtotime(" +  $offset  $offset_unit", strtotime('- 1 day', strtotime($rangestart))));
    $rangeComparisonStart = date('Y-m-d', strtotime(' + 1 day', strtotime($rangeEnd)));
    $rangeComparisonEnd = date('Y-m-d', strtotime(" + $comparison_offset $comparison_offset_unit", strtotime('- 1 day', strtotime($rangeComparisonStart))));
    $this->_ranges['interval_' . $i] = array(
      'from_date' => $rangestart,
      'to_date' => $rangeEnd,
      'comparison_from_date' => $rangeComparisonStart,
      'comparison_to_date' => $rangeComparisonEnd
    );
  }

  /**
   *
   * @param integer $i
   * @param string $startDate
   * @param integer $no_periods
   * @param string $offset_unit
   * @param integer $offset
   * @param string $comparison_offset
   * @param integer $comparison_offset_unit
   */
  function constructPriorRanges($i, $startDate, $no_periods, $offset_unit, $offset, $comparison_offset, $comparison_offset_unit, $start_offset, $start_offset_unit) {
    $rangestart = date('Y-m-d', strtotime('+ ' . ($i * $offset) . " $offset_unit ", strtotime($startDate)));
    $rangeEnd = date('Y-m-d', strtotime(" +  $offset  $offset_unit", strtotime('- 1 day', strtotime($rangestart))));
    $rangeComparisonEnd = date('Y-m-d', strtotime('- 1 day', strtotime($rangestart)));
    $rangeComparisonStart = date('Y-m-d', strtotime(" - $comparison_offset $comparison_offset_unit", strtotime('+ 1 day', strtotime($rangeComparisonEnd))));
    if ($this->_reportingStartDate && $this->_comparisonType == 'allprior') {
      $rangeComparisonStart = $this->_reportingStartDate;
    }

    $this->_ranges['interval_' . $i] = array(
      'from_date' => $rangestart,
      'to_date' => $rangeEnd,
      'comparison_from_date' => $rangeComparisonStart,
      'comparison_to_date' => $rangeComparisonEnd
    );
  }
  /**
   * Here we are constructing a set of Year to Date ranges
   * ie we want to compare '01 jan 2012 to 22 May 2012' with '01 Jan 2011 to 22 May 2011'
   * @param integer $i
   * @param string $startDate
   * @param integer $no_periods
   * @param string $offset_unit
   * @param integer $offset
   * @param string $comparison_offset
   * @param integer $comparison_offset_unit
   */
  function constructPriorDefinedRanges($i, $startDate, $no_periods, $offset_unit, $offset, $comparison_offset, $comparison_offset_unit, $start_offset, $start_offset_unit, $primary_from_date, $primary_to_date) {
    // the start date we are given tells use the m & d of the end date for each ytd span
    // but we need to reset the start date to be the 1st day of the following year
    $rangeStart = date('Y-m-d', strtotime('- ' . ($i * $offset) . " $offset_unit ", strtotime($primary_from_date)));
    $rangeEnd = date('Y-m-d', strtotime('- ' . ($i * $offset) . " $offset_unit ", strtotime($primary_to_date)));
    // in the normal prior we assume that the comparison ends the day before the main range starts.
    // in this case we will assume it is offset from the main range end by the comparison offset
    $rangeComparisonEnd = date('Y-m-d', strtotime('- ' . ($comparison_offset) . " $comparison_offset_unit ", strtotime($rangeEnd)));
    $rangeComparisonStart = date('Y-m-d', strtotime('- ' . ($comparison_offset) . " $comparison_offset_unit ", strtotime($rangeStart)));
    $this->_ranges['interval_' . $i] = array(
      'from_date' => $rangeStart,
      'to_date' => $rangeEnd,
      'comparison_from_date' => $rangeComparisonStart,
      'comparison_to_date' => $rangeComparisonEnd
    );
  }
  /**
   *
   * @param integer $i
   * @param string $startDate
   * @param integer $no_periods
   * @param string $offset_unit
   * @param integer $offset
   * @param string $comparison_offset
   * @param integer $comparison_offset_unit
   */
  function constructSingleRange($i, $startDate, $no_periods, $offset_unit, $offset, $comparison_offset, $comparison_offset_unit, $start_offset, $start_offset_unit) {
    $rangestart = date('Y-m-d', strtotime('+ ' . ($i * $offset) . " $offset_unit ", strtotime($startDate)));
    $rangeEnd = date('Y-m-d', strtotime(" +  $offset  $offset_unit", strtotime('- 1 day', strtotime($rangestart))));
    if ($this->_reportingStartDate && $this->_comparisonType == 'allprior') {
      $rangeComparisonStart = $this->_reportingStartDate;
    }
    $this->_ranges['interval_' . $i] = array(
      'from_date' => $rangestart,
      'to_date' => $rangeEnd
    );
  }

  /*
    *      )
  *  but they are constructed in the construct fn -
  *  @todo we should move table construction to separate fn
    *
    *
    */
  function joinContributionMulitplePeriods($prefix, $extra) {
    if (! $this->_preConstrained) {
      if (empty($this->_aliases['civicrm_contact'])) {
        $this->_aliases['civicrm_contact'] = 'civicontact';
      }

      //we are just going to add our where clause here
      if (isset($this->_params['receive_date_value'])) {
        $this->_params['receive_date_to'] = $this->_params['receive_date_value'];
      }
      if (! empty($extra['start_offset'])) {
        $this->_params['receive_date_from'] = $this->_reportingStartDate;
      }
      else {
        $this->_params['receive_date_from'] = $this->_ranges['interval_0']['from_date'];
      }
      return;
    }
    unset($this->_params['receive_date_from']);
    unset($this->_params['receive_date_to']);
    $this->_columns['civicrm_contribution']['filters']['receive_date']['pseudofield'] = TRUE;
    $columnStr = NULL;
    if(isset($this->_tempTables['civicrm_contribution_multi'])){
      $tempTable = $this->_tempTables['civicrm_contribution_multi'];
    }
    else{
      $tempTable = $this->constructComparisonTable(CRM_Utils_Array::Value('extra_fields', $extra));
      $this->_tempTables['civicrm_contribution_multi'] = $tempTable;
    }
    //@todo hack differentiating summary based on contact & contribution report
    // do something better -
    // Follow up note - I may no longer be doing any based on 'contribution'
    if ($this->_baseEntity == 'contribution') {
      if (empty($this->aliases['civicrm_contribution'])) {
        $this->aliases['civicrm_contribution'] = 'contribution_civireport';
      }
      $baseFrom = " {$this->_baseTable} " . (empty($this->_aliases[$this->_baseTable]) ? '' : $this->_aliases[$this->_baseTable]);
      $this->_from = str_replace('FROM' . $baseFrom, "FROM  $tempTable tmptable INNER JOIN civicrm_contribution
       {$this->aliases['civicrm_contribution']} ON tmptable.cid = {$this->aliases['civicrm_contribution']}.contact_id
       AND tmptable.interval_0_{$this->_params['behaviour_type_value']} = 1
       AND {$this->aliases['civicrm_contribution']}.is_test=0
       INNER JOIN $baseFrom ON {$this->_aliases[$this->_baseTable]}.id = {$this->_aliases['civicrm_contribution']}.contact_id
       ", $this->_from);
    }
    else {
      if(isset($this->_tempTables['civicrm_contribution_multi_summary'])){
        $tempTableSummary = $this->_tempTables['civicrm_contribution_multi_summary'];
      }
      else{
        $this->createSummaryTable($tempTable);
        $tempTableSummary = $this->_tempTables['civicrm_contribution_multi_summary'] = $tempTable . '_summary';
      }
      $this->_from = " FROM {$tempTable}_summary";

    }
    $this->whereClauses = array();
  }
  /**
 * Set the report date range where the report dates are defined by an end date and
 * an offset
 * @param array $startParams
 *  - start_offset
 *  - start_offset_unit
 */
  function setReportingStartDate($startParams) {
    if (! empty($startParams['start_offset']) && ! $this->_reportingStartDate) {
      $startOffset = CRM_Utils_Array::Value($startParams['start_offset'], $this->_params, $startParams['start_offset']);
      $startOffsetUnit = CRM_Utils_Array::Value($startParams['start_offset_unit'], $this->_params, $startParams['start_offset_unit']);
      $this->_reportingStartDate = date('Y-m-d', strtotime("-  $startOffset  $startOffsetUnit ", strtotime($this->_params['receive_date_value'])));
    }
  }
  /**
   * constrainedWhere applies to Where clauses applied AFTER the
   * 'pre-constrained' report universe is created.
   *
   * For example the universe might be limited to a group of contacts in the first round
   * in the second round this Where clause is applied
   * (non-PHPdoc)
   * @see CRM_Extendedreport_Form_Report_Advancedfundraising::constrainedWhere()
   */
  function constrainedWhere() {
    if (empty($this->constrainedWhereClauses)) {
      $this->_where = "WHERE ( 1 ) ";
      $this->_having = "";
    }
    else {
      $this->_where = "WHERE " . implode(' AND ', $this->constrainedWhereClauses);
    }
  }
  /*
  * Here we have one period & a comparison
  * Receive date from / to are compulsory for this
  * as are comparison_dates & type
  *
  */
  function joinContributionSinglePeriod($prefix, $extra) {
    //@todo this setting of aliases is just a hack
    if (empty($this->_aliases['civicrm_contact'])) {
      $this->_aliases['civicrm_contact'] = 'civicontact';
    }
    if (empty($this->_aliases['civicrm_contribution'])) {
      $this->aliases['civicrm_contribution'] = 'contribution_civireport';
    }
    if (! $this->_preConstrained) {
      return;
    }
    //@todo - not sure if we need this separate from 'mulitple' - main difference is handling around 'receive_date
    // because in single we are using the receive date


    $tempTable = $this->constructComparisonTable();
    $baseFrom = " {$this->_baseTable} " . (empty($this->_aliases[$this->_baseTable]) ? '' : $this->_aliases[$this->_baseTable]);
    $baseClause = "
      FROM  {$this->_baseTable} tmpcontacts
      INNER JOIN  $tempTable tmpConttable ON tmpcontacts.id = tmpConttable.cid
      INNER JOIN civicrm_contact {$this->_aliases[$this->_baseTable]} ON {$this->_aliases[$this->_baseTable]}.id = tmpcontacts.id";
    if ($this->_baseEntity == 'contribution') {
      // this will result in one line per contribution
      $baseClause .= "
        LEFT JOIN civicrm_contribution {$this->_aliases['civicrm_contribution']}
          ON tmpConttable.cid = {$this->_aliases['civicrm_contribution']}.contact_id
          AND {$this->_aliases['civicrm_contribution']}.receive_date
          BETWEEN '{$this->_ranges['interval_0']['from_date']}' AND
          '{$this->_ranges['interval_0']['to_date']}'";
    }
    $this->_from = str_replace('FROM' . $baseFrom, $baseClause, $this->_from);
    $this->constrainedWhereClauses = array(
      "tmpConttable.interval_0_{$this->_params['behaviour_type_value']} = 1"
    );
  }


  /**
  * Build array of contributions against contact
  *
  * so far only used to add contact_type
  *  array('contact_type' => 'contact_type VARCHAR(50) NULL,')
  * Note that at the moment the column has to be in contact or contribution table
  * and be unique (as no prefixing)
  *
  *  $this->_ranges needs to hold ranges like
  *
  *    'date_ranges' => array(
  *      'first_date_range' => array(
  *        'from_date' => '2009-01-01',
  *        'to_date' => '2010-06-06',
  *        'comparison_from_date' => '2008-01-01',
  *        'comparison_to_date' => '2009-01-01',
  *        ),
  *      'second_date_range => array(
  *        from_date' => '2011-01-01',
  *        'to_date' => '2011-06-06',
  *        'comparison_from_date' => '2010-01-01',
  *        'comparison_to_date' => '2010-06-01',),
  */
  function constructComparisonTable() {
    $tempTable = 'civicrm_report_temp_conts' . date('d_H_I') . substr(md5(serialize($this->_ranges) . serialize($this->whereClauses)), 0, 6);
    if($this->tableExists($tempTable)) {
      return $tempTable;
    }
    $columnStr = '';
    $betweenClauses = array();
    foreach ($this->_ranges as $alias => &$specs) {

      $specs['between'] = "
      BETWEEN '{$specs['from_date']}'
      AND '{$specs['to_date']} 23:59:59'";

      if (isset($specs['comparison_from_date'])) {
        $specs['comparison_between'] = "
          BETWEEN '{$specs['comparison_from_date']}'
          AND '{$specs['comparison_to_date']} 23:59:59'";

        $betweenClauses[] = " {$specs['comparison_between']}";
      }
      $betweenClauses[] = " {$specs['between']}";
      $columnStr .= "  {$alias}_amount DECIMAL NOT NULL default 0, {$alias}_no DECIMAL NOT NULL default 0, ";
      $columnStr .= "  {$alias}_catch_amount DECIMAL NOT NULL default 0, {$alias}_catch_no DECIMAL NOT NULL default 0, ";
      foreach ($this->_statuses as $status) {
        $columnStr .= "  {$alias}_{$status} TINYINT NOT NULL default 0, ";
      }
    }

    $temporary = $this->_temporary;

    CRM_Core_DAO::executeQuery("DROP TABLE IF EXISTS $tempTable");
    $createTablesql = "
                  CREATE  $temporary TABLE $tempTable (
                  `cid` INT(10) UNSIGNED NULL DEFAULT '0' COMMENT 'Contact ID',
                  `first_receive_date` DATE NOT NULL,
                  $columnStr
                  `total_amount` FLOAT NOT NULL,
                  INDEX `ContributionId` (`cid`)
                  )
                  COLLATE='utf8_unicode_ci'
                  ENGINE=HEAP;";
    $contributionClause = $receiveClause = '';
    if (! empty($this->whereClauses['civicrm_contribution'])) {
      foreach ($this->whereClauses['civicrm_contribution'] as $clause) {
        if (stristr($clause, 'receive_date')) {
          $receiveClause = " AND " . $clause;
        }
        else {
          $contributionClause = " AND " . $clause;
        }
      }
    }

    $insertContributionRecordsSql = "
      INSERT INTO $tempTable (cid, first_receive_date, total_amount)
      SELECT {$this->_aliases[$this->_baseTable]}.id ,
        min(receive_date), sum(total_amount)
      FROM {$this->_baseTable} {$this->_aliases[$this->_baseTable]}
      INNER JOIN civicrm_contribution {$this->_aliases['civicrm_contribution']}
        ON {$this->_aliases[$this->_baseTable]}.id =  {$this->_aliases['civicrm_contribution']}.contact_id
      WHERE  {$this->_aliases['civicrm_contribution']}.contribution_status_id = 1
        AND {$this->_aliases['civicrm_contribution']}.is_test = 0
          $receiveClause
          $contributionClause
      GROUP BY {$this->_aliases[$this->_baseTable]}.id
      ";
    //insert data about primary range
    foreach ($this->_ranges as $rangeName => &$rangeSpecs) {
      $table_name = CRM_Core_DAO::createTempTableName();
      $inserts[] = "CREATE  $temporary TABLE $table_name (
                  `contact_id` INT(10) UNSIGNED NULL DEFAULT '0' COMMENT 'Contact ID',
                  `total_amount` FLOAT NOT NULL,
                  `no_cont` INT(10) UNSIGNED NULL DEFAULT '0',
                  INDEX `contact_id` (`contact_id`)
                  )
                  COLLATE='utf8_unicode_ci'
                  ENGINE=HEAP;";
      $inserts[] = "INSERT INTO $table_name SELECT contact_id, sum({$this->_aliases['civicrm_contribution']}.total_amount) as total_amount,
                      count({$this->_aliases['civicrm_contribution']}.id) as no_cont
                    FROM $tempTable tmp
                    INNER JOIN civicrm_contribution  {$this->_aliases['civicrm_contribution']} ON tmp.cid = {$this->_aliases['civicrm_contribution']}.contact_id
                    WHERE {$this->_aliases['civicrm_contribution']}.receive_date
                    BETWEEN '{$rangeSpecs['from_date']}' AND '{$rangeSpecs['to_date']} 23:59:59'
                    $contributionClause
                    GROUP BY contact_id
                  ";

      $inserts[] = " UPDATE $tempTable t,
                  $table_name as conts
                  SET {$rangeName}_amount = conts.total_amount,
                  {$rangeName}_no = no_cont
                  WHERE t.cid = contact_id
                  ";
      //insert data about comparison range
      // if we are only looking at 'new' then there might not be a comparison period
      if (isset($rangeSpecs['comparison_from_date'])) {
        $table_name = CRM_Core_DAO::createTempTableName();
        $inserts[] = "CREATE  $temporary TABLE $table_name (
                    `contact_id` INT(10) UNSIGNED NULL DEFAULT '0' COMMENT 'Contact ID',
                    `total_amount` FLOAT NOT NULL,
                    `no_cont` INT(10) UNSIGNED NULL DEFAULT '0',
                    INDEX `contact_id` (`contact_id`)
                    )
                    COLLATE='utf8_unicode_ci'
                    ENGINE=HEAP;";

        $inserts[] = "SELECT contact_id
                , sum({$this->_aliases['civicrm_contribution']}.total_amount) as total_amount
                , count({$this->_aliases['civicrm_contribution']}.id) as no_cont
              FROM $tempTable tmp
                INNER JOIN civicrm_contribution  {$this->_aliases['civicrm_contribution']} ON tmp.cid = {$this->_aliases['civicrm_contribution']}.contact_id
              WHERE {$this->_aliases['civicrm_contribution']}.receive_date
                BETWEEN '{$rangeSpecs['comparison_from_date']}' AND '{$rangeSpecs['comparison_to_date']} 23:59:59'
                $contributionClause
              GROUP BY contact_id";

        $inserts[] = "UPDATE $tempTable t, $table_name as conts
            SET
              {$rangeName}_catch_amount = conts.total_amount,
              {$rangeName}_catch_no = no_cont
            WHERE t.cid = contact_id
         ";
      }
      foreach ($this->_statuses as $status) {
        $statusClauses[] = "
           {$rangeName}_{$status} = " . $this->getStatusClause($status, $rangeName, $rangeSpecs);
      }
    }
    if (! empty($statusClauses)) {
      $inserts[] = " UPDATE $tempTable t SET " . implode(',', $statusClauses);
    }
    CRM_Core_DAO::executeQuery($createTablesql);
    CRM_Core_DAO::executeQuery($insertContributionRecordsSql);
    foreach ($inserts as $sql) {
      CRM_Core_DAO::executeQuery($sql);
    }
    return $tempTable;
  }
  /**
 *
 * @param string $tempTable
 * @param array $this->_ranges
 * @return string
 */
  function createSummaryTable($tempTable) {
    $tempTableSummary = $tempTable . '_summary';
    if($this->tableExists($tempTableSummary)) {
      return $tempTableSummary;
    }
    $first = TRUE;
    foreach ($this->_ranges as $rangeName => &$rangeSpecs) {
      // could do this above but will probably want this creation in a separate function
      $sql = "
      SELECT
      '$rangeName' as range_name,
      '{$rangeSpecs['from_date']}' as from_date,
      '{$rangeSpecs['to_date']}' as to_date ";
      foreach ($this->_statuses as $status) {
        $sql .= " , SUM(
          {$rangeName}_{$status}
        ) AS {$status} ";
      }
      foreach ($this->_aggregates as $aggregate) {
        $sql .= " , SUM(
        {$rangeName}_amount
        ) AS {$status}_total
          , SUM(
        {$rangeName}_catch_amount
        ) AS comparison_{$status}_total ";
      }

      $sql .= " FROM {$tempTable}";
      if($first) {
        $summarySQL = "CREATE table $tempTableSummary $sql";
        $first = FALSE;
      }
      else {
        $summarySQL = "INSERT INTO $tempTableSummary $sql";
      }
      CRM_Core_DAO::executeQuery($summarySQL);
    }

  }
  /**
 * Wrapper for status clauses
 * @param string $status
 * @param string $rangeName
 */
  function getStatusClause($status, $rangeName, $rangeSpecs) {
    $fn = 'get' . ucfirst($status) . 'Clause';
    return $this->$fn($rangeName, $rangeSpecs);
  }

  /**
 * Get Clause for lapsed
 */
  function getLapsedClause($rangeName, $rangeSpecs) {
    return "
        IF (
          {$rangeName}_amount = 0 AND {$rangeName}_catch_amount > 0, 1,  0
         )
    ";
  }

  /**
   * Get Clause for lapsed
   */
  function getPriorClause($rangeName, $rangeSpecs) {
    return "
    IF (
    {$rangeName}_amount = 0 AND first_receive_date < '{$rangeSpecs['from_date']}', 1,  0
    )
    ";
  }
  /**
   * Get Clause for Recovered
   */
  function getRecoveredClause($rangeName, $rangeSpecs) {
    return "
        IF (
         {$rangeName}_amount > 0 AND (
           {$rangeName}_catch_amount = 0 AND first_receive_date < '{$rangeSpecs['from_date']}'
         ) , 1,  0
        )
     ";
  }

  /**
   * Get Clause for Renewed
   * These are where the contribution happened in both periods
   * - note that the term 'renewal' & the term Recovered are easily confused
   * but recovered is used where the comparison period is 'prior' but not 'priorall'
   * so there is a period not covered in the comparison period but covered in the
   * report 'universe'
   */
  function getRenewedClause($rangeName, $rangeFromDate) {
    return "
      IF (
        {$rangeName}_amount > 0 AND {$rangeName}_catch_amount > 0, 1,  0
      )
    ";
  }
  /**
 * Get donors who gave in main period but not in catchment perioed
 *
 * @param string $rangeName
 * @param array $rangeFromDate
 * @return string  Clause
 */
  function getNewClause($rangeName, $rangeFromDate) {
    return "
    IF (
    {$rangeName}_amount > 0 AND {$rangeName}_catch_amount = 0, 1,  0
    )
    ";
  }
  /**
 * Get number of donors who gave in the main period
 *
 * @param string $rangeName
 * @param array $rangeFromDate
 * @return string  Clause
 */
  function getEveryClause($rangeName, $rangeSpecs) {
    return "
    IF (
      {$rangeName}_amount > 0, 1,  0
    )
    ";
  }

  /**
   * Get number of donors who gave for the first time
   *
   * @param string $rangeName
   * @param array $rangeFromDate
   * @return string  Clause
   */
  function getFirstClause($rangeName, $rangeSpecs){
    return "
    IF (
    first_receive_date {$rangeSpecs['between']}, 1,  0
    )
    ";
  }

  /**
   * Get number of donors who gave in the main period
   *
   * @param string $rangeName
   * @param array $rangeFromDate
   * @return string  Clause
   */
  function getTotalClause($rangeName, $rangeSpecs) {
    return "
    {$rangeName}_amount
    ";
  }

  /**
   * Get Clause for increased Donors
   *
   * @param string $rangeName
   * @param array $rangeSpecs
   * @return string clause for increased donor
   */
  function getIncreasedClause($rangeName, $rangeSpecs) {
    return "
    IF (
    {$rangeName}_amount > 0 AND {$rangeName}_amount > {$rangeName}_catch_amount AND {$rangeName}_catch_amount > 0, 1,  0
    )
    ";
  }

  /**
   * Get Clause for increased Donors
   *
   * @param string $rangeName
   * @param array $rangeSpecs
   * @return string clause for increased donor
   */
  function getDecreasedClause($rangeName, $rangeSpecs) {
    return "
    IF (
    {$rangeName}_amount < 0 AND {$rangeName}_amount < {$rangeName}_catch_amount , 1,  0
    )
    ";
  }

  /**
   * (non-PHPdoc)
   * @see CRM_Extendedreport_Form_Report_Advancedfundraising::getAvailableJoins()
   */
  function getAvailableJoins() {
    return parent::getAvailableJoins() + array(
      'timebased_contribution_from_contact' => array(
        'callback' => 'joinContributionMulitplePeriods'
      ),
      'single_contribution_comparison_from_contact' => array(
        'callback' => 'joinContributionSinglePeriod'
      )
    );
  }
  /**
   * We have some overloaded vars which could either be a constant of a param - convert
   * @param unknown_type $vars
   */
  function getVarsFromParams(&$vars) {
  }

  /**
   * Get last date of last quarter
   */
  function getLastDayOfQuarter() {
    $month = date('m') - 1;
    switch ($month) {
      case 1:
      case 2:
      case 3:
        return (date('Y-12-31'));
      case 4:
      case 5:
      case 6:
        return (date('Y-03-31'));
      case 7:
      case 8:
      case 9:
        return (date('Y-06-30'));
      case 10:
      case 0:
      case 11:
        return (date('Y-09-30'));
    }
  }


  function statistics(&$rows) {
    $statistics = parent::statistics($rows);
    $tempTable = $this->constructComparisonTable();
    // this 'isMultipleRanges stuff is poor man's formatting - to avoid altering the tpl in this
    // extension @ this stage.
    $spacing = $isMultipleRanges = '';
    if(count($this->_ranges) > 1) {
      $spacing = '&nbsp &nbsp  &nbsp  &nbsp  &nbsp  &nbsp ';
      $isMultipleRanges = TRUE;
    }
    foreach ($this->_ranges as $index => $range) {
      $select = ' SELECT ';
      $select .= "
        sum({$index}_amount) as {$index}_amount,
        sum({$index}_no) as {$index}_no,
        ROUND(avg({$index}_amount), 2) as {$index}_avg,
        max({$index}_amount) as {$index}_max,
        min({$index}_amount) as {$index}_min
      ";

    $sql = $select . " FROM " . $tempTable . " WHERE {$index}_amount > 0 ";
    $dao = CRM_Core_DAO::executeQuery($sql);

    while ($dao->fetch()) {
        $amountStr = $index . '_amount';
        $noStr = $index . '_no';
        $avgStr = $index . '_avg';
        $maxStr = $index . '_max';
        $minStr = $index . '_min';
        $rangeStr = CRM_Utils_Date::customFormat($this->_ranges[$index]['from_date']) . ts(' to ') .  CRM_Utils_Date::customFormat($this->_ranges[$index]['to_date']);

        if($isMultipleRanges) {
          $statistics['counts']['header'. $index] = array(
            'title' => $rangeStr,
            'value' => '',
            'type' => CRM_Utils_Type::T_MONEY,
          );
        }
        $statistics['counts']['amount'. $index] = array(
          'title' => $spacing . ts('Total Amount Contributed '),
          'value' => $dao->$amountStr,
          'type' => CRM_Utils_Type::T_MONEY,
        );
        $statistics['counts']['count'. $index] = array(
          'title' => $spacing . ts('Total Number of Contributions'),
          'value' => $dao->$noStr,
        );
        $statistics['counts']['avg' . $index] = array(
          'title' => $spacing . ts('Average Value of Contribution'),
          'value' => $dao->$avgStr,
          'type' => CRM_Utils_Type::T_MONEY,
        );
        $statistics['counts']['max' . $index] = array(
          'title' => $spacing . ts('Largest Contribution'),
          'value' =>  $dao->$maxStr,
          'type' => CRM_Utils_Type::T_MONEY,
        );
        $statistics['counts']['min' . $index] = array(
          'title' => $spacing . ts('Smallest Contribution'),
          'value' =>  $dao->$minStr,
          'type' => CRM_Utils_Type::T_MONEY,
        );
      }

    }

    if($isMultipleRanges) {
      unset($statistics['counts']['rowsFound']);
    }
    else{
      $statistics['counts']['rowsFound']['title']  = ts('Total Rows (number of contributors)');
    }
    return $statistics;

  }
}

